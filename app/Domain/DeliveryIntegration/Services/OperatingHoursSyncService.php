<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Support\Facades\Log;

/**
 * Pushes the configured operating-hours JSON for each enabled platform
 * config to the corresponding adapter.
 *
 * `operating_hours_json` is expected to be a 7-element array, one per
 * weekday (0=Sunday … 6=Saturday), each element either:
 *   - {"closed": true}
 *   - {"open": "09:00", "close": "23:00"}
 */
class OperatingHoursSyncService
{
    public function syncForConfig(DeliveryPlatformConfig $config): array
    {
        $hours = $config->operating_hours_json;

        if (empty($hours) || ! is_array($hours)) {
            return ['success' => false, 'message' => 'no_operating_hours_configured'];
        }

        $adapter = DeliveryAdapterFactory::make($config);

        try {
            $result = $adapter->syncOperatingHours($hours, $config->getCredentials());

            $config->update(['operating_hours_synced' => (bool) ($result['success'] ?? false)]);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('delivery.operating_hours.sync_failed', [
                'config_id' => $config->id,
                'platform' => $config->platform instanceof \BackedEnum
                    ? $config->platform->value
                    : (string) $config->platform,
                'error' => $e->getMessage(),
            ]);

            $config->update(['operating_hours_synced' => false]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function syncForStore(string $storeId): array
    {
        return DeliveryPlatformConfig::query()
            ->where('store_id', $storeId)
            ->where('is_enabled', true)
            ->get()
            ->map(fn ($cfg) => [
                'config_id' => $cfg->id,
                'result' => $this->syncForConfig($cfg),
            ])
            ->all();
    }
}
