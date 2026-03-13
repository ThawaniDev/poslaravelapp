<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockAdjustmentItem;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * List adjustments for a store.
     */
    public function list(string $storeId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return StockAdjustment::where('store_id', $storeId)
            ->with('adjustedBy')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get single adjustment with items.
     */
    public function find(string $id): StockAdjustment
    {
        return StockAdjustment::with(['stockAdjustmentItems.product', 'adjustedBy'])->findOrFail($id);
    }

    /**
     * Create an adjustment and immediately apply it to stock levels.
     */
    public function create(array $data, array $items): StockAdjustment
    {
        return DB::transaction(function () use ($data, $items) {
            $type = StockAdjustmentType::from($data['type']);

            $adjustment = StockAdjustment::create([
                'store_id' => $data['store_id'],
                'type' => $type,
                'reason_code' => $data['reason_code'] ?? null,
                'notes' => $data['notes'] ?? null,
                'adjusted_by' => $data['adjusted_by'] ?? null,
            ]);

            foreach ($items as $item) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                ]);

                $movementType = $type === StockAdjustmentType::Increase
                    ? StockMovementType::AdjustmentIn
                    : StockMovementType::AdjustmentOut;

                $this->stockService->adjustStock(
                    storeId: $data['store_id'],
                    productId: $item['product_id'],
                    type: $movementType,
                    quantity: (float) $item['quantity'],
                    unitCost: isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
                    referenceType: StockReferenceType::Adjustment,
                    referenceId: $adjustment->id,
                    reason: $data['reason_code'] ?? null,
                    performedBy: $data['adjusted_by'] ?? null,
                );
            }

            return $adjustment->load('stockAdjustmentItems');
        });
    }
}
