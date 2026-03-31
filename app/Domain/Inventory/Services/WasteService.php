<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Enums\WasteReason;
use App\Domain\Inventory\Models\WasteRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WasteService
{
    public function __construct(private readonly StockService $stockService) {}

    public function list(string $storeId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = WasteRecord::where('store_id', $storeId)
            ->with(['product'])
            ->orderByDesc('created_at');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, string $userId): WasteRecord
    {
        return DB::transaction(function () use ($data, $userId) {
            // Get unit cost from stock level if not provided
            $unitCost = $data['unit_cost'] ?? null;
            if ($unitCost === null) {
                $level = $this->stockService->getOrCreate($data['store_id'], $data['product_id']);
                $unitCost = (float) $level->average_cost;
            }

            $waste = WasteRecord::create([
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'unit_cost' => $unitCost,
                'reason' => WasteReason::from($data['reason']),
                'batch_number' => $data['batch_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $userId,
            ]);

            // Deduct stock
            $this->stockService->adjustStock(
                storeId: $data['store_id'],
                productId: $data['product_id'],
                type: StockMovementType::Waste,
                quantity: (float) $data['quantity'],
                unitCost: $unitCost,
                referenceType: StockReferenceType::Adjustment,
                referenceId: $waste->id,
                reason: 'Waste: ' . $data['reason'],
                performedBy: $userId,
            );

            return $waste->load('product');
        });
    }
}
