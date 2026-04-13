<?php

namespace App\Domain\ThawaniIntegration\Observers;

use App\Domain\Catalog\Models\Product;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncQueue;
use Illuminate\Support\Facades\Log;

class ThawaniProductObserver
{
    public function created(Product $product): void
    {
        $this->queueProductSync($product, 'create');
    }

    public function updated(Product $product): void
    {
        $this->queueProductSync($product, 'update');
    }

    public function deleted(Product $product): void
    {
        $this->queueProductSync($product, 'delete');
    }

    private function queueProductSync(Product $product, string $action): void
    {
        try {
            // Find all connected stores for this organization that have auto_sync enabled
            $configs = ThawaniStoreConfig::whereHas('store', function ($q) use ($product) {
                    $q->where('organization_id', $product->organization_id);
                })
                ->where('is_connected', true)
                ->where('auto_sync_products', true)
                ->get();

            foreach ($configs as $config) {
                // Only queue if this product has an existing mapping or is a new product
                if ($action !== 'create') {
                    $hasMapping = ThawaniProductMapping::where('store_id', $config->store_id)
                        ->where('product_id', $product->id)
                        ->exists();

                    if (!$hasMapping) continue;
                }

                // Check for duplicate pending items
                $exists = ThawaniSyncQueue::where('store_id', $config->store_id)
                    ->where('entity_type', 'product')
                    ->where('entity_id', $product->id)
                    ->where('action', $action)
                    ->where('status', 'pending')
                    ->exists();

                if (!$exists) {
                    ThawaniSyncQueue::create([
                        'store_id' => $config->store_id,
                        'entity_type' => 'product',
                        'entity_id' => $product->id,
                        'action' => $action,
                        'payload' => $product->only(['name', 'sell_price', 'sku', 'barcode', 'image_url']),
                        'status' => 'pending',
                        'scheduled_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('ThawaniProductObserver: Failed to queue sync', [
                'product_id' => $product->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
