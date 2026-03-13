<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
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
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%');
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
    ): StockMovement {
        return DB::transaction(function () use (
            $storeId, $productId, $type, $quantity,
            $unitCost, $referenceType, $referenceId, $reason, $performedBy
        ) {
            $level = $this->getOrCreate($storeId, $productId);

            // Determine signed quantity change
            $signedQty = match ($type) {
                StockMovementType::Receipt,
                StockMovementType::AdjustmentIn,
                StockMovementType::TransferIn => abs($quantity),

                StockMovementType::Sale,
                StockMovementType::AdjustmentOut,
                StockMovementType::TransferOut,
                StockMovementType::Waste,
                StockMovementType::RecipeDeduction => -abs($quantity),
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
            ]);

            return $movement;
        });
    }

    /**
     * Get movement history for a store+product.
     */
    public function movements(string $storeId, ?string $productId = null, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = StockMovement::where('store_id', $storeId)->orderByDesc('created_at');

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
}
