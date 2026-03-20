<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;

class DeliveryService
{
    public function getConfigs(string $storeId): array
    {
        return DeliveryPlatformConfig::where('store_id', $storeId)
            ->orderBy('platform')
            ->get()
            ->toArray();
    }

    public function getConfig(string $configId, string $storeId): ?DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::where('id', $configId)
            ->where('store_id', $storeId)
            ->first();
    }

    public function saveConfig(string $storeId, array $data): DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::updateOrCreate(
            ['store_id' => $storeId, 'platform' => $data['platform']],
            [
                'api_key' => $data['api_key'] ?? null,
                'merchant_id' => $data['merchant_id'] ?? null,
                'webhook_secret' => $data['webhook_secret'] ?? null,
                'branch_id_on_platform' => $data['branch_id_on_platform'] ?? null,
                'is_enabled' => $data['is_enabled'] ?? false,
                'auto_accept' => $data['auto_accept'] ?? false,
                'throttle_limit' => $data['throttle_limit'] ?? null,
            ],
        );
    }

    public function toggleConfig(string $configId, string $storeId): ?DeliveryPlatformConfig
    {
        $config = DeliveryPlatformConfig::where('id', $configId)
            ->where('store_id', $storeId)
            ->first();

        if (!$config) {
            return null;
        }

        $config->update(['is_enabled' => !$config->is_enabled]);
        return $config->fresh();
    }

    public function getOrders(string $storeId, array $filters = []): array
    {
        $query = DeliveryOrderMapping::where('store_id', $storeId);

        if (!empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        if (!empty($filters['status'])) {
            $query->where('delivery_status', $filters['status']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->toArray();
    }

    public function getSyncLogs(string $storeId, array $filters = []): array
    {
        $query = DeliveryMenuSyncLog::where('store_id', $storeId);

        if (!empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->toArray();
    }

    public function getStats(string $storeId): array
    {
        $configs = DeliveryPlatformConfig::where('store_id', $storeId)->get();
        $orders = DeliveryOrderMapping::where('store_id', $storeId);

        return [
            'total_platforms' => $configs->count(),
            'active_platforms' => $configs->where('is_enabled', true)->count(),
            'total_orders' => (clone $orders)->count(),
            'pending_orders' => (clone $orders)->where('delivery_status', 'pending')->count(),
            'completed_orders' => (clone $orders)->where('delivery_status', 'delivered')->count(),
        ];
    }
}
