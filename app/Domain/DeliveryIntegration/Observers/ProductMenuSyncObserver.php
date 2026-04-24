<?php

namespace App\Domain\DeliveryIntegration\Observers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Jobs\MenuSyncJob;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Spec Rule #2: When a product is created/updated/deleted, schedule a menu
 * sync to every connected delivery platform for every store in the
 * organization that has `sync_menu_on_product_change = true`.
 *
 * Debounced via Cache lock (5 minutes per store+platform) so a burst of
 * product edits does not flood the queue.
 */
class ProductMenuSyncObserver
{
    private const DEBOUNCE_SECONDS = 300; // 5 minutes

    public function created(Product $product): void
    {
        $this->scheduleSync($product);
    }

    public function updated(Product $product): void
    {
        $this->scheduleSync($product);
    }

    public function deleted(Product $product): void
    {
        $this->scheduleSync($product);
    }

    private function scheduleSync(Product $product): void
    {
        $storeIds = Store::query()
            ->where('organization_id', $product->organization_id)
            ->pluck('id');

        if ($storeIds->isEmpty()) {
            return;
        }

        $configs = DeliveryPlatformConfig::query()
            ->whereIn('store_id', $storeIds)
            ->where('is_enabled', true)
            ->where('sync_menu_on_product_change', true)
            ->get(['id', 'store_id', 'platform']);

        foreach ($configs as $config) {
            $platform = is_object($config->platform)
                ? $config->platform->value
                : (string) $config->platform;

            $key = sprintf('delivery:menu_sync_debounce:%s:%s', $config->store_id, $platform);

            $acquired = Cache::add($key, 1, self::DEBOUNCE_SECONDS);

            if ($acquired) {
                MenuSyncJob::dispatch($config->id, [])
                    ->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
            }
        }
    }
}
