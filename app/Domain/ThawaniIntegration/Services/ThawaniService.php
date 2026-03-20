<?php

namespace App\Domain\ThawaniIntegration\Services;

use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniOrderMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniSettlement;

class ThawaniService
{
    public function getConfig(string $storeId): ?ThawaniStoreConfig
    {
        return ThawaniStoreConfig::where('store_id', $storeId)->first();
    }

    public function saveConfig(string $storeId, array $data): ThawaniStoreConfig
    {
        $attributes = [
            'is_connected' => $data['is_connected'] ?? false,
            'auto_sync_products' => $data['auto_sync_products'] ?? false,
            'auto_sync_inventory' => $data['auto_sync_inventory'] ?? false,
            'auto_accept_orders' => $data['auto_accept_orders'] ?? false,
            'operating_hours_json' => $data['operating_hours'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'connected_at' => ($data['is_connected'] ?? false) ? now() : null,
        ];

        if (!empty($data['thawani_store_id'])) {
            $attributes['thawani_store_id'] = $data['thawani_store_id'];
        }

        $existing = ThawaniStoreConfig::where('store_id', $storeId)->first();

        if ($existing) {
            $existing->update($attributes);
            return $existing->fresh();
        }

        return ThawaniStoreConfig::create(array_merge(
            ['store_id' => $storeId, 'thawani_store_id' => $data['thawani_store_id'] ?? $storeId],
            $attributes,
        ));
    }

    public function disconnect(string $storeId): bool
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
        if (!$config) {
            return false;
        }

        $config->update(['is_connected' => false, 'connected_at' => null]);
        return true;
    }

    public function getOrders(string $storeId, array $filters = []): array
    {
        $query = ThawaniOrderMapping::where('store_id', $storeId);

        if (!empty($filters['status'])) {
            $query->where('thawani_order_status', $filters['status']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->toArray();
    }

    public function getProductMappings(string $storeId): array
    {
        return ThawaniProductMapping::where('store_id', $storeId)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function getSettlements(string $storeId, array $filters = []): array
    {
        $query = ThawaniSettlement::where('store_id', $storeId);

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->toArray();
    }

    public function getStats(string $storeId): array
    {
        $config = ThawaniStoreConfig::where('store_id', $storeId)->first();
        $orders = ThawaniOrderMapping::where('store_id', $storeId);
        $settlements = ThawaniSettlement::where('store_id', $storeId);

        return [
            'is_connected' => $config?->is_connected ?? false,
            'thawani_store_id' => $config?->thawani_store_id,
            'total_orders' => (clone $orders)->count(),
            'total_products_mapped' => ThawaniProductMapping::where('store_id', $storeId)->count(),
            'total_settlements' => (clone $settlements)->count(),
            'pending_orders' => (clone $orders)->where('thawani_order_status', 'pending')->count(),
        ];
    }
}
