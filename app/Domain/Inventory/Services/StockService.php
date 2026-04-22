<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Core\Models\StoreSettings;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Stock movement types that REMOVE stock (negative effect on quantity).
     * Used for batch consumption + pre-deduction availability checks.
     */
    private const DEDUCTION_TYPES = [
        StockMovementType::Sale,
        StockMovementType::AdjustmentOut,
        StockMovementType::TransferOut,
        StockMovementType::Waste,
        StockMovementType::RecipeDeduction,
        StockMovementType::SupplierReturn,
    ];

    /**
     * Stock available to sell / waste / transfer = on-hand minus reservations.
     * Pass $lock = true to take a row-level lock (must be inside a transaction).
     */
    public function available(string $storeId, string $productId, bool $lock = false): float
    {
        $query = StockLevel::where('store_id', $storeId)->where('product_id', $productId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $level = $query->first();
        if (!$level) {
            return 0.0;
        }
        return (float) $level->quantity - (float) $level->reserved_quantity;
    }

    /**
     * Throw if a deduction of $needed for ($storeId,$productId) would drive
     * available stock below zero AND the store does not allow negative stock.
     * Must be called inside a DB transaction so the row lock is meaningful.
     */
    public function assertSufficientStock(
        string $storeId,
        string $productId,
        float $needed,
        ?string $productName = null,
    ): void {
        $settings = StoreSettings::where('store_id', $storeId)->first();
        if ($settings && !$settings->track_inventory) {
            return; // Inventory tracking disabled — no checks.
        }
        $allowNegative = $settings ? (bool) $settings->allow_negative_stock : false;
        if ($allowNegative) {
            return;
        }
        $available = $this->available($storeId, $productId, lock: true);
        if ($available < $needed) {
            throw new \RuntimeException(
                __('inventory.insufficient_stock', [
                    'product' => $productName ?? $productId,
                    'available' => $available,
                    'requested' => $needed,
                ])
            );
        }
    }

    /**
     * Move on-hand stock into the reserved bucket for a future event
     * (e.g. transfer in-transit). Does NOT write a stock_movements row.
     */
    public function reserve(string $storeId, string $productId, float $qty): void
    {
        DB::transaction(function () use ($storeId, $productId, $qty) {
            $level = StockLevel::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();
            if (!$level) {
                throw new \RuntimeException("No stock level for product {$productId} at store {$storeId}");
            }
            $available = (float) $level->quantity - (float) $level->reserved_quantity;
            if ($available < $qty) {
                throw new \RuntimeException(
                    __('inventory.insufficient_stock', [
                        'product' => $productId,
                        'available' => $available,
                        'requested' => $qty,
                    ])
                );
            }
            $level->reserved_quantity = (float) $level->reserved_quantity + $qty;
            $level->sync_version = ($level->sync_version ?? 0) + 1;
            $level->save();
        });
    }

    /**
     * Release a previously held reservation back to free on-hand stock.
     */
    public function releaseReservation(string $storeId, string $productId, float $qty): void
    {
        DB::transaction(function () use ($storeId, $productId, $qty) {
            $level = StockLevel::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();
            if (!$level) {
                return;
            }
            $level->reserved_quantity = max(0.0, (float) $level->reserved_quantity - $qty);
            $level->sync_version = ($level->sync_version ?? 0) + 1;
            $level->save();
        });
    }

    /**
     * Get stock levels for a store, optionally filtered by product or low-stock.
     */
    public function levels(string $storeId, array $filters = [], int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = StockLevel::where('store_id', $storeId)->with('product');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['low_stock'])) {
            $query->whereColumn('quantity', '<=', 'reorder_point');
        }

        if (!empty($filters['search'])) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']);
            $query->whereHas('product', function ($q) use ($escaped) {
                $q->where('name', 'like', '%' . $escaped . '%');
            });
        }

        return $query->orderBy('quantity', 'asc')->paginate($perPage);
    }

    /**
     * Get a single stock level by store + product, or create with zero qty.
     */
    public function getOrCreate(string $storeId, string $productId): StockLevel
    {
        return StockLevel::firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $productId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'average_cost' => 0, 'sync_version' => 1]
        );
    }

    /**
     * Adjust stock quantity + record immutable movement.
     * WAC recalculated on receipt-type movements.
     *
     * Idempotency: when $idempotencyKey + $referenceType + $referenceId are
     * all provided, a duplicate call returns the previously created movement
     * without re-applying the stock change. Backed by a partial unique index.
     *
     * Batch consumption: when the store has enable_batch_tracking on AND the
     * movement is a deduction, stock_batches are consumed FIFO/FEFO so
     * expiry tracking stays consistent with stock_levels.
     */
    public function adjustStock(
        string $storeId,
        string $productId,
        StockMovementType $type,
        float $quantity,
        ?float $unitCost = null,
        ?StockReferenceType $referenceType = null,
        ?string $referenceId = null,
        ?string $reason = null,
        ?string $performedBy = null,
        ?string $idempotencyKey = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $storeId, $productId, $type, $quantity,
            $unitCost, $referenceType, $referenceId, $reason, $performedBy, $idempotencyKey
        ) {
            // ── Idempotency short-circuit ────────────────────────────────
            if ($idempotencyKey && $referenceType && $referenceId) {
                $existing = StockMovement::where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            // Lock the row to prevent concurrent modification
            $level = StockLevel::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$level) {
                $level = StockLevel::create([
                    'store_id' => $storeId,
                    'product_id' => $productId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'average_cost' => 0,
                    'sync_version' => 1,
                ]);
            }

            // Determine signed quantity change
            $signedQty = match ($type) {
                StockMovementType::Receipt,
                StockMovementType::AdjustmentIn,
                StockMovementType::TransferIn => abs($quantity),

                StockMovementType::Sale,
                StockMovementType::AdjustmentOut,
                StockMovementType::TransferOut,
                StockMovementType::Waste,
                StockMovementType::RecipeDeduction,
                StockMovementType::SupplierReturn => -abs($quantity),
            };

            // Update WAC on receipt
            if ($type === StockMovementType::Receipt && $unitCost !== null && $unitCost > 0) {
                $currentTotalCost = (float) $level->quantity * (float) $level->average_cost;
                $incomingCost = abs($quantity) * $unitCost;
                $newTotalQty = (float) $level->quantity + abs($quantity);
                $level->average_cost = $newTotalQty > 0
                    ? ($currentTotalCost + $incomingCost) / $newTotalQty
                    : $unitCost;
            }

            $level->quantity = (float) $level->quantity + $signedQty;
            $level->sync_version = ($level->sync_version ?? 0) + 1;
            $level->save();

            // ── Batch FIFO/FEFO consumption (deductions only) ───────────
            if (in_array($type, self::DEDUCTION_TYPES, true)) {
                $this->consumeBatchesIfTracked($storeId, $productId, abs($quantity));
            }

            // Create immutable audit movement
            $movement = StockMovement::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'type' => $type,
                'quantity' => $signedQty,
                'unit_cost' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
                'performed_by' => $performedBy,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $movement;
        });
    }

    /**
     * Consume `qty` from stock_batches FIFO by expiry_date (NULLS LAST) then
     * created_at. No-op when the store has batch tracking disabled or has no
     * batch rows for this product (legacy stock without batches).
     */
    private function consumeBatchesIfTracked(string $storeId, string $productId, float $qty): void
    {
        $settings = StoreSettings::where('store_id', $storeId)->first();
        if (!$settings || !$settings->enable_batch_tracking) {
            return;
        }

        $remaining = $qty;
        $batches = StockBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL') // NULLS LAST (cross-DB safe)
            ->orderBy('expiry_date')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (float) $batch->quantity);
            $batch->quantity = (float) $batch->quantity - $take;
            $batch->save();
            $remaining -= $take;
        }
        // If $remaining > 0 we silently allow it — legacy stock not in batches.
    }


    /**
     * Get movement history for a store+product.
     */
    public function movements(string $storeId, ?string $productId = null, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = StockMovement::where('store_id', $storeId)
            ->with('product')
            ->orderByDesc('created_at');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Update reorder point for a stock level.
     */
    public function setReorderPoint(string $stockLevelId, float $reorderPoint, ?float $maxLevel = null): StockLevel
    {
        $level = StockLevel::findOrFail($stockLevelId);
        $level->reorder_point = $reorderPoint;
        if ($maxLevel !== null) {
            $level->max_stock_level = $maxLevel;
        }
        $level->save();
        return $level;
    }

    /**
     * Get batches expiring within given days.
     */
    public function expiryAlerts(string $storeId, int $daysAhead = 30, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return StockBatch::where('store_id', $storeId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($daysAhead))
            ->where('quantity', '>', 0)
            ->with('product')
            ->orderBy('expiry_date')
            ->paginate($perPage);
    }

    /**
     * Get low-stock items that need reordering.
     */
    public function lowStockItems(string $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return StockLevel::where('store_id', $storeId)
            ->whereNotNull('reorder_point')
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->with('product')
            ->get();
    }
}
