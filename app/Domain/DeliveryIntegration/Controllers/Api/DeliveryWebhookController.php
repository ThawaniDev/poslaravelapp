<?php

namespace App\Domain\DeliveryIntegration\Controllers\Api;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Services\OrderIngestService;
use App\Domain\DeliveryIntegration\Services\WebhookVerificationService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryWebhookController extends BaseApiController
{
    public function __construct(
        private readonly WebhookVerificationService $webhookService,
        private readonly OrderIngestService $ingestService,
    ) {}

    /**
     * POST /api/v2/delivery/webhook/{platform}/{storeId}
     *
     * Inbound webhook endpoint for delivery platforms.
     * No auth:sanctum — authenticated via webhook signature.
     */
    public function handle(Request $request, string $platform, string $storeId): JsonResponse
    {
        $config = DeliveryPlatformConfig::where('store_id', $storeId)
            ->where('platform', $platform)
            ->first();

        if (! $config) {
            Log::warning('Webhook: unknown config', [
                'platform' => $platform,
                'store_id' => $storeId,
            ]);

            return $this->notFound('Unknown platform configuration');
        }

        $eventType = $request->header('X-Event-Type')
            ?? $request->input('event')
            ?? $request->input('event_type')
            ?? 'unknown';

        $verified = $this->webhookService->verify($request, $config);
        $log = $this->webhookService->logWebhook($request, $platform, $storeId, $verified, $eventType);

        if (! $verified) {
            Log::warning('Webhook: signature verification failed', [
                'platform' => $platform,
                'store_id' => $storeId,
                'log_id' => $log->id,
            ]);

            $this->webhookService->markProcessed($log, false, 'Signature verification failed');

            return $this->error('Invalid webhook signature', 401);
        }

        try {
            $this->processWebhook($platform, $storeId, $eventType, $request->all());
            $this->webhookService->markProcessed($log, true);

            return $this->success(null, 'Webhook processed');
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'platform' => $platform,
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            $this->webhookService->markProcessed($log, false, $e->getMessage());

            return $this->error('Webhook processing failed', 500);
        }
    }

    private function processWebhook(string $platform, string $storeId, string $eventType, array $payload): void
    {
        match ($eventType) {
            'new_order', 'order.created', 'order_created' => $this->handleNewOrder($platform, $storeId, $payload),
            'order_update', 'order.updated', 'order_status_changed' => $this->handleOrderUpdate($platform, $storeId, $payload),
            'order_cancelled', 'order.cancelled' => $this->handleOrderCancelled($platform, $storeId, $payload),
            default => Log::info("Webhook: unhandled event type '{$eventType}'", [
                'platform' => $platform,
                'store_id' => $storeId,
            ]),
        };
    }

    private function handleNewOrder(string $platform, string $storeId, array $payload): void
    {
        $this->ingestService->ingestFromWebhook($storeId, $platform, $payload);
    }

    private function handleOrderUpdate(string $platform, string $storeId, array $payload): void
    {
        // External status updates (e.g., driver assigned, delivered)
        $externalOrderId = $payload['order_id'] ?? $payload['id'] ?? $payload['external_order_id'] ?? null;
        $newStatus = $payload['status'] ?? $payload['order_status'] ?? null;

        if ($externalOrderId && $newStatus) {
            $order = \App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping::where('platform', $platform)
                ->where('external_order_id', $externalOrderId)
                ->first();

            if ($order) {
                $statusMap = [
                    'picked_up' => 'dispatched',
                    'out_for_delivery' => 'dispatched',
                    'delivered' => 'delivered',
                    'completed' => 'delivered',
                ];

                $mappedStatus = $statusMap[strtolower($newStatus)] ?? null;
                if ($mappedStatus && $order->canTransitionTo(\App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus::from($mappedStatus))) {
                    $order->update([
                        'delivery_status' => $mappedStatus,
                        $mappedStatus === 'dispatched' ? 'dispatched_at' : 'delivered_at' => now(),
                    ]);
                }
            }
        }
    }

    private function handleOrderCancelled(string $platform, string $storeId, array $payload): void
    {
        $externalOrderId = $payload['order_id'] ?? $payload['id'] ?? null;

        if ($externalOrderId) {
            $order = \App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping::where('platform', $platform)
                ->where('external_order_id', $externalOrderId)
                ->first();

            if ($order && ! $order->isTerminal()) {
                $order->update([
                    'delivery_status' => 'cancelled',
                    'rejection_reason' => $payload['reason'] ?? $payload['cancellation_reason'] ?? 'Cancelled by platform',
                ]);
            }
        }
    }
}
