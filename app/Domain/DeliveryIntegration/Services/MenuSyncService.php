<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\Enums\MenuSyncTrigger;
use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Support\Facades\Log;

class MenuSyncService
{
    public function syncMenu(DeliveryPlatformConfig $config, array $products, MenuSyncTrigger $trigger = MenuSyncTrigger::Manual): DeliveryMenuSyncLog
    {
        $startTime = microtime(true);
        $adapter = DeliveryAdapterFactory::make($config);

        $log = DeliveryMenuSyncLog::create([
            'store_id' => $config->store_id,
            'platform' => $config->platform->value,
            'status' => 'syncing',
            'items_synced' => count($products),
            'triggered_by' => $trigger->value,
            'sync_type' => 'full',
        ]);

        try {
            $result = $adapter->syncMenu($config->store_id, $products, $config->getCredentials());
            $duration = round(microtime(true) - $startTime, 2);

            $log->update([
                'status' => $result['success'] ? 'success' : 'failed',
                'error_details' => isset($result['message']) ? ['message' => $result['message']] : null,
                'duration_seconds' => $duration,
            ]);

            if ($result['success']) {
                $config->update(['operating_hours_synced' => true]);
            }
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            Log::error('Menu sync failed', [
                'store_id' => $config->store_id,
                'platform' => $config->platform->value,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_details' => ['message' => $e->getMessage()],
                'duration_seconds' => $duration,
            ]);
        }

        return $log->fresh();
    }

    public function syncForAllEnabledPlatforms(string $storeId, array $products, MenuSyncTrigger $trigger = MenuSyncTrigger::Manual): array
    {
        $configs = DeliveryPlatformConfig::forStore($storeId)->enabled()->get();
        $results = [];

        foreach ($configs as $config) {
            $results[] = $this->syncMenu($config, $products, $trigger);
        }

        return $results;
    }

    public function toggleProductAvailability(DeliveryPlatformConfig $config, string $productId, bool $available): array
    {
        $adapter = DeliveryAdapterFactory::make($config);

        return $adapter->toggleProductAvailability($productId, $available, $config->getCredentials());
    }
}
