<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\DTOs\PushOrderStatusDTO;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryStatusPushLog;
use Illuminate\Support\Facades\Log;

class StatusPushService
{
    public function pushStatus(DeliveryOrderMapping $order, string $newStatus, array $extra = []): DeliveryStatusPushLog
    {
        $config = DeliveryPlatformConfig::where('store_id', $order->store_id)
            ->where('platform', $order->platform)
            ->enabled()
            ->first();

        $log = DeliveryStatusPushLog::create([
            'delivery_order_mapping_id' => $order->id,
            'platform' => $order->platform,
            'status_pushed' => $newStatus,
            'attempt_number' => $this->getNextAttempt($order->id, $newStatus),
            'request_payload' => array_merge(['status' => $newStatus], $extra),
            'pushed_at' => now(),
        ]);

        if (! $config) {
            $log->update([
                'success' => false,
                'http_status_code' => null,
                'response_payload' => ['error' => 'No active platform config found'],
                'error_message' => 'No active platform config found',
            ]);

            return $log;
        }

        try {
            $adapter = DeliveryAdapterFactory::make($config);
            $result = $adapter->pushOrderStatus(
                $order->external_order_id,
                $newStatus,
                $config->getCredentials(),
                $extra,
            );

            $success = $result['success'] ?? false;

            $log->update([
                'success' => $success,
                'http_status_code' => $result['status_code'] ?? null,
                'response_payload' => $result,
                'error_message' => $success ? null : ($result['message'] ?? 'Push failed'),
            ]);

            if (! $success) {
                throw new \RuntimeException($result['message'] ?? 'Status push returned failure');
            }
        } catch (\Throwable $e) {
            Log::error('Status push failed', [
                'order_id' => $order->id,
                'platform' => $order->platform,
                'status' => $newStatus,
                'attempt' => $log->attempt_number,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'success' => false,
                'response_payload' => ['error' => $e->getMessage()],
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $log->fresh();
    }

    public function pushStatusForDTO(PushOrderStatusDTO $dto): ?DeliveryStatusPushLog
    {
        $order = DeliveryOrderMapping::find($dto->orderMappingId);
        if (! $order) {
            return null;
        }

        return $this->pushStatus($order, $dto->status, [
            'reason' => $dto->reason,
            'estimated_minutes' => $dto->estimatedMinutes,
        ]);
    }

    private function getNextAttempt(string $orderId, string $status): int
    {
        return DeliveryStatusPushLog::where('delivery_order_mapping_id', $orderId)
            ->where('status_pushed', $status)
            ->count() + 1;
    }
}
