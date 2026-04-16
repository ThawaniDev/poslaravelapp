<?php

namespace App\Domain\Notification\Observers;

use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

class StockLevelNotificationObserver
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function updated(StockLevel $stockLevel): void
    {
        if (! $stockLevel->wasChanged('quantity')) {
            return;
        }

        try {
            $product = $stockLevel->product;
            $store = $stockLevel->store ?? $product?->store;
            $storeId = $stockLevel->store_id ?? $product?->store_id;

            if (! $storeId) {
                return;
            }

            $productName = $product?->name ?? 'Unknown';
            $storeName = $store?->name ?? '';
            $qty = (float) $stockLevel->quantity;
            $reorderPoint = (float) ($stockLevel->reorder_point ?? 0);

            // Out of stock
            if ($qty <= 0) {
                $this->dispatcher->toStoreOwner(
                    storeId: $storeId,
                    eventKey: 'inventory.out_of_stock',
                    variables: [
                        'product_name' => $productName,
                        'store_name' => $storeName,
                    ],
                    category: 'inventory',
                    referenceId: $product?->id,
                    referenceType: 'product',
                );
                return;
            }

            // Low stock (below reorder point)
            if ($reorderPoint > 0 && $qty <= $reorderPoint) {
                $this->dispatcher->toStoreOwner(
                    storeId: $storeId,
                    eventKey: 'inventory.low_stock',
                    variables: [
                        'product_name' => $productName,
                        'current_qty' => (string) $qty,
                        'reorder_point' => (string) $reorderPoint,
                        'store_name' => $storeName,
                    ],
                    category: 'inventory',
                    referenceId: $product?->id,
                    referenceType: 'product',
                );
            }
        } catch (\Throwable $e) {
            Log::error('StockLevelNotificationObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }
}
