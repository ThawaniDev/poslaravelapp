<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\DTOs\IngestOrderDTO;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Events\DeliveryOrderReceived;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderIngestService
{
    public function ingest(IngestOrderDTO $dto): ?DeliveryOrderMapping
    {
        $config = DeliveryPlatformConfig::where('store_id', $dto->storeId)
            ->where('platform', $dto->platform)
            ->enabled()
            ->first();

        if (! $config) {
            Log::warning('Order ingest: no active config', [
                'store_id' => $dto->storeId,
                'platform' => $dto->platform,
            ]);

            return null;
        }

        if ($config->isDailyLimitReached()) {
            Log::warning('Order ingest: daily limit reached', [
                'store_id' => $dto->storeId,
                'platform' => $dto->platform,
                'daily_count' => $config->daily_order_count,
                'max' => $config->max_daily_orders,
            ]);

            return null;
        }

        $existing = DeliveryOrderMapping::where('platform', $dto->platform)
            ->where('external_order_id', $dto->externalOrderId)
            ->first();

        if ($existing) {
            Log::info('Order ingest: duplicate order', [
                'external_order_id' => $dto->externalOrderId,
                'platform' => $dto->platform,
            ]);

            return $existing;
        }

        $order = DB::transaction(function () use ($dto, $config) {
            $commissionPercent = $this->resolveCommissionPercent($dto->platform);
            $commissionAmount = round(((float) $dto->totalAmount) * $commissionPercent / 100, 2);

            $order = DeliveryOrderMapping::create([
                'store_id' => $dto->storeId,
                'platform' => $dto->platform,
                'external_order_id' => $dto->externalOrderId,
                'order_id' => null,
                'delivery_status' => $config->auto_accept
                    ? DeliveryOrderStatus::Accepted->value
                    : DeliveryOrderStatus::Pending->value,
                'customer_name' => $dto->customerName,
                'customer_phone' => $dto->customerPhone,
                'delivery_address' => $dto->deliveryAddress,
                'delivery_fee' => $dto->deliveryFee,
                'subtotal' => $dto->subtotal,
                'total_amount' => $dto->totalAmount,
                'items_count' => count($dto->items),
                'estimated_prep_minutes' => $dto->estimatedPrepMinutes,
                'notes' => $dto->notes,
                'raw_payload' => $dto->rawPayload,
                'commission_percent' => $commissionPercent,
                'commission_amount' => $commissionAmount,
                'accepted_at' => $config->auto_accept ? now() : null,
            ]);

            $config->incrementDailyOrderCount();

            return $order;
        });

        event(new DeliveryOrderReceived($order));

        // Spec Rule #6: schedule auto-rejection for manual-accept orders.
        if (! $config->auto_accept) {
            $timeout = (int) ($config->auto_accept_timeout_seconds ?? 300);
            \App\Domain\DeliveryIntegration\Jobs\AutoRejectStaleOrderJob::dispatch($order->id, $timeout)
                ->delay(now()->addSeconds($timeout));
        }

        return $order;
    }

    public function ingestFromWebhook(string $storeId, string $platform, array $rawPayload): ?DeliveryOrderMapping
    {
        $adapter = DeliveryAdapterFactory::makeFromSlug($platform, []);
        $normalized = $adapter->normalizeOrderPayload($rawPayload);

        $dto = IngestOrderDTO::fromWebhookPayload(
            storeId: $storeId,
            platform: $platform,
            payload: $normalized,
        );

        return $this->ingest($dto);
    }

    /**
     * Resolve commission percentage from registry or platform integration table.
     * Falls back to 0 if not configured.
     */
    private function resolveCommissionPercent(string $platformSlug): float
    {
        $percent = \App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform::where('slug', $platformSlug)
            ->value('default_commission_percent');

        if ($percent !== null) {
            return (float) $percent;
        }

        // Fall back to platform_delivery_integrations table
        if (\Illuminate\Support\Facades\Schema::hasTable('platform_delivery_integrations')) {
            $percent = \Illuminate\Support\Facades\DB::table('platform_delivery_integrations')
                ->where('platform_slug', $platformSlug)
                ->value('default_commission_percent');

            if ($percent !== null) {
                return (float) $percent;
            }
        }

        return 0.0;
    }
}
