<?php

namespace App\Domain\Notification\Observers;

use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Fires inventory.adjustment when a manual stock adjustment is created.
 *
 * For multi-item adjustments we fire a single summary notification rather
 * than one per line to avoid spamming the store owner.
 */
class StockAdjustmentNotificationObserver
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function created(StockAdjustment $adjustment): void
    {
        try {
            $store = $adjustment->store;
            $adjustedBy = $adjustment->adjustedBy;
            $items = $adjustment->stockAdjustmentItems()->with('product')->get();

            if ($items->isEmpty()) {
                return;
            }

            $first = $items->first();
            $productName = $first?->product?->name ?? 'Multiple products';
            if ($items->count() > 1) {
                $productName .= ' (+' . ($items->count() - 1) . ')';
            }

            $delta = (float) ($first?->quantity ?? 0);
            $oldQty = '—';
            $newQty = ($delta >= 0 ? '+' : '') . number_format($delta, 2);

            $this->dispatcher->toStoreOwner(
                storeId: $adjustment->store_id,
                eventKey: 'inventory.adjustment',
                variables: [
                    'product_name' => $productName,
                    'old_qty' => $oldQty,
                    'new_qty' => $newQty,
                    'adjusted_by' => $adjustedBy?->name ?? '—',
                    'store_name' => $store?->name ?? '',
                ],
                category: 'inventory',
                referenceId: $adjustment->id,
                referenceType: 'stock_adjustment',
            );
        } catch (\Throwable $e) {
            Log::error('StockAdjustmentNotificationObserver::created failed', ['error' => $e->getMessage()]);
        }
    }
}
