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
            'attempt' => $this->getNextAttempt($order->id, $newStatus),
        ]);

        if (! $config) {
            $log->update([
                'success' => false,
                'response_code' => null,
                'response_body' => ['error' => 'No active platform config found'],
                'pushed_at' => now(),
            ]);

            return $log;
        }

        try {
            $adapter = DeliveryAdapterFactory::make($config);
            $result = $adapter->pushOrderStatus(
                $config->getCredentials(),
                $order->external_order_id,
                $newStatus,
                $extra,
            );

            $log->update([
                'success' => $result['success'] ?? false,
                'response_code' => $result['status_code'] ?? null,
                'response_body' => $result,
                'pushed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Status push failed', [
                'order_id' => $order->id,
                'platform' => $order->platform,
                'status' => $newStatus,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'success' => false,
                'response_body' => ['error' => $e->getMessage()],
                'pushed_at' => now(),
            ]);
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
