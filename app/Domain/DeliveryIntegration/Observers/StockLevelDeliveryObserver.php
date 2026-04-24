<?php

namespace App\Domain\DeliveryIntegration\Observers;

use App\Domain\DeliveryIntegration\Jobs\ToggleProductAvailabilityJob;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\Inventory\Models\StockLevel;

/**
 * Spec Rule #3: Within 60s of stock_quantity transitioning to/from 0,
 * mark product (un)available on every connected delivery platform for
 * the affected store.
 *
 * Listens to StockLevel updates and dispatches ToggleProductAvailabilityJob
 * per enabled DeliveryPlatformConfig in that store.
 */
class StockLevelDeliveryObserver
{
    public function updated(StockLevel $level): void
    {
        if (! $level->wasChanged('quantity')) {
            return;
        }

        $newQty = (float) $level->quantity;
        $oldQty = (float) $level->getOriginal('quantity');

        $wentOutOfStock = $oldQty > 0 && $newQty <= 0;
        $cameBackInStock = $oldQty <= 0 && $newQty > 0;

        if (! $wentOutOfStock && ! $cameBackInStock) {
            return;
        }

        $available = $cameBackInStock;

        DeliveryPlatformConfig::query()
            ->where('store_id', $level->store_id)
            ->where('is_enabled', true)
            ->get(['id'])
            ->each(function (DeliveryPlatformConfig $config) use ($level, $available) {
                ToggleProductAvailabilityJob::dispatch(
                    $config->id,
                    $level->product_id,
                    $available,
                );
            });
    }
}
