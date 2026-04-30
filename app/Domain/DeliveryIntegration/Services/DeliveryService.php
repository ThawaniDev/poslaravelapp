<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\Contracts\DeliveryPlatformInterface;
use App\Domain\DeliveryIntegration\DTOs\SavePlatformConfigDTO;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Events\DeliveryStatusChanged;
use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryService
{
    public function getConfigs(string $storeId): array
    {
        return $this->getConfigsCollection($storeId)->toArray();
    }

    public function getConfigsCollection(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return DeliveryPlatformConfig::forStore($storeId)
            ->orderBy('platform')
            ->get();
    }

    public function getConfig(string $configId, string $storeId): ?DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::where('id', $configId)
            ->where('store_id', $storeId)
            ->first();
    }

    public function getConfigByPlatform(string $storeId, string $platform): ?DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::where('store_id', $storeId)
            ->where('platform', $platform)
            ->first();
    }

    public function saveConfig(string $storeId, array|SavePlatformConfigDTO $data): DeliveryPlatformConfig
    {
        $attributes = $data instanceof SavePlatformConfigDTO ? $data->toArray() : $data;

        return DeliveryPlatformConfig::updateOrCreate(
            ['store_id' => $storeId, 'platform' => $attributes['platform']],
            array_filter([
                'api_key' => $attributes['api_key'] ?? null,
                'merchant_id' => $attributes['merchant_id'] ?? null,
                'webhook_secret' => $attributes['webhook_secret'] ?? null,
                'branch_id_on_platform' => $attributes['branch_id_on_platform'] ?? null,
                'is_enabled' => $attributes['is_enabled'] ?? false,
                'auto_accept' => $attributes['auto_accept'] ?? false,
                'auto_accept_timeout_seconds' => $attributes['auto_accept_timeout_seconds'] ?? null,
                'throttle_limit' => $attributes['throttle_limit'] ?? null,
                'max_daily_orders' => $attributes['max_daily_orders'] ?? null,
                'sync_menu_on_product_change' => $attributes['sync_menu_on_product_change'] ?? false,
                'menu_sync_interval_hours' => $attributes['menu_sync_interval_hours'] ?? null,
                'operating_hours_json' => isset($attributes['operating_hours_json']) ? json_encode($attributes['operating_hours_json']) : null,
                'status' => $attributes['status'] ?? 'pending',
            ], fn ($v) => $v !== null),
        );
    }

    public function toggleConfig(string $configId, string $storeId): ?DeliveryPlatformConfig
    {
        $config = DeliveryPlatformConfig::where('id', $configId)
            ->where('store_id', $storeId)
            ->first();

        if (! $config) {
            return null;
        }

        $config->update(['is_enabled' => ! $config->is_enabled]);

        return $config->fresh();
    }

    public function testConnection(string $configId, string $storeId): array
    {
        $config = $this->getConfig($configId, $storeId);
        if (! $config) {
            return ['success' => false, 'message' => 'Configuration not found'];
        }

        $adapter = DeliveryAdapterFactory::make($config);
        $result = $adapter->testConnection($config->getCredentials());

        if ($result['success']) {
            $config->update(['status' => 'active']);
        }

        return $result;
    }

    public function getOrders(string $storeId, array $filters = []): LengthAwarePaginator
    {
        $query = DeliveryOrderMapping::forStore($storeId);

        if (! empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        if (! empty($filters['status'])) {
            $query->where('delivery_status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getOrder(string $orderId, string $storeId): ?DeliveryOrderMapping
    {
        return DeliveryOrderMapping::where('id', $orderId)
            ->where('store_id', $storeId)
            ->with('statusPushLogs')
            ->first();
    }

    public function getActiveOrders(string $storeId): array
    {
        return $this->getActiveOrdersCollection($storeId)->toArray();
    }

    public function getActiveOrdersCollection(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return DeliveryOrderMapping::forStore($storeId)
            ->active()
            ->orderByDesc('created_at')
            ->get();
    }

    public function updateOrderStatus(string $orderId, string $storeId, string $newStatus, ?string $reason = null): ?DeliveryOrderMapping
    {
        $order = DeliveryOrderMapping::where('id', $orderId)
            ->where('store_id', $storeId)
            ->first();

        if (! $order) {
            return null;
        }

        $statusEnum = DeliveryOrderStatus::from($newStatus);
        if (! $order->canTransitionTo($statusEnum)) {
            return null;
        }

        $oldStatus = $order->delivery_status;

        DB::transaction(function () use ($order, $newStatus, $reason) {
            $updates = ['delivery_status' => $newStatus];

            if ($reason && in_array($newStatus, ['rejected', 'cancelled'])) {
                $updates['rejection_reason'] = $reason;
            }

            $timestampMap = [
                'accepted' => 'accepted_at',
                'ready' => 'ready_at',
                'dispatched' => 'dispatched_at',
                'delivered' => 'delivered_at',
            ];

            if (isset($timestampMap[$newStatus])) {
                $updates[$timestampMap[$newStatus]] = now();
            }

            $order->update($updates);
        });

        event(new DeliveryStatusChanged($order->fresh(), $oldStatus, $statusEnum));

        return $order->fresh();
    }

    public function getSyncLogs(string $storeId, array $filters = []): LengthAwarePaginator
    {
        $query = DeliveryMenuSyncLog::where('store_id', $storeId);

        if (! empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        return $query->orderByDesc('started_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getStats(string $storeId): array
    {
        $configs = DeliveryPlatformConfig::forStore($storeId)->get();
        $ordersQuery = DeliveryOrderMapping::forStore($storeId);

        $todayOrders = DeliveryOrderMapping::forStore($storeId)
            ->whereDate('created_at', today());

        return [
            'total_platforms' => $configs->count(),
            'active_platforms' => $configs->where('is_enabled', true)->count(),
            'total_orders' => (clone $ordersQuery)->count(),
            'pending_orders' => (clone $ordersQuery)->pending()->count(),
            'active_orders' => (clone $ordersQuery)->active()->count(),
            'completed_orders' => (clone $ordersQuery)->where('delivery_status', 'delivered')->count(),
            'rejected_orders' => (clone $ordersQuery)->where('delivery_status', 'rejected')->count(),
            'today_orders' => (clone $todayOrders)->count(),
            'today_revenue' => (clone $todayOrders)->where('delivery_status', 'delivered')->sum('total_amount'),
            'platforms' => $configs->map(function ($c) use ($storeId) {
                $platformSlug = $c->platform instanceof \App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform
                    ? $c->platform->value
                    : (string) $c->platform;
                static $platformLabels = null;
                if ($platformLabels === null) {
                    $platformLabels = DeliveryPlatform::pluck('name', 'slug');
                }

                return [
                    'platform' => $platformSlug,
                    'label' => $platformLabels[$platformSlug] ?? ucfirst(str_replace('_', ' ', $platformSlug)),
                    'is_enabled' => $c->is_enabled,
                    'daily_order_count' => $c->daily_order_count,
                    'last_order_at' => $c->last_order_received_at?->toIso8601String(),
                ];
            })->toArray(),
        ];
    }

    public function getAdapter(DeliveryPlatformConfig $config): DeliveryPlatformInterface
    {
        return DeliveryAdapterFactory::make($config);
    }

    public function getWebhookLogs(string $storeId, array $filters = []): LengthAwarePaginator
    {
        $query = \App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog::where('store_id', $storeId);

        if (! empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        return $query->orderByDesc('received_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getStatusPushLogs(string $storeId, array $filters = []): LengthAwarePaginator
    {
        $query = \App\Domain\DeliveryIntegration\Models\DeliveryStatusPushLog::query()
            ->whereHas('deliveryOrderMapping', fn ($q) => $q->where('store_id', $storeId));

        if (! empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        if (! empty($filters['order_id'])) {
            $query->where('delivery_order_mapping_id', $filters['order_id']);
        }

        return $query->orderByDesc('pushed_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function deleteConfig(string $configId, string $storeId): bool
    {
        $config = DeliveryPlatformConfig::where('id', $configId)
            ->where('store_id', $storeId)
            ->first();

        if (! $config) {
            return false;
        }

        $config->delete();

        return true;
    }
}
