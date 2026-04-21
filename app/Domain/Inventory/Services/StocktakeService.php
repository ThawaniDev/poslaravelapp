<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StocktakeStatus;
use App\Domain\Inventory\Enums\StocktakeType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\StocktakeItem;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StocktakeService
{
    public function __construct(private readonly StockService $stockService) {}

    public function list(string $storeId, int $perPage = 25): LengthAwarePaginator
    {
        return Stocktake::where('store_id', $storeId)
            ->with(['startedBy', 'completedBy'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function find(string $storeId, string $id): Stocktake
    {
        return Stocktake::where('store_id', $storeId)
            ->with(['stocktakeItems.product', 'store', 'category'])
            ->findOrFail($id);
    }

    public function create(array $data, string $userId): Stocktake
    {
        return DB::transaction(function () use ($data, $userId) {
            $type = StocktakeType::from($data['type']);

            $stocktake = Stocktake::create([
                'store_id' => $data['store_id'],
                'reference_number' => 'ST-' . strtoupper(Str::random(8)),
                'type' => $type,
                'status' => StocktakeStatus::InProgress,
                'category_id' => $data['category_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'started_by' => $userId,
            ]);

            // Pre-populate items from current stock levels
            $stockLevelsQuery = StockLevel::where('store_id', $data['store_id'])
                ->where('quantity', '>', 0);

            if ($type === StocktakeType::Category && !empty($data['category_id'])) {
                $stockLevelsQuery->whereHas('product', function ($q) use ($data) {
                    $q->where('category_id', $data['category_id']);
                });
            }

            $stockLevels = $stockLevelsQuery->get();

            foreach ($stockLevels as $level) {
                StocktakeItem::create([
                    'stocktake_id' => $stocktake->id,
                    'product_id' => $level->product_id,
                    'expected_qty' => $level->quantity,
                    'counted_qty' => null,
                    'variance' => null,
                    'cost_impact' => null,
                ]);
            }

            return $stocktake->load('stocktakeItems');
        });
    }

    public function updateCounts(string $id, array $items, string $userId): Stocktake
    {
        return DB::transaction(function () use ($id, $items, $userId) {
            $stocktake = Stocktake::with('stocktakeItems')->findOrFail($id);

            if ($stocktake->status === StocktakeStatus::Completed || $stocktake->status === StocktakeStatus::Cancelled) {
                throw new \RuntimeException('Cannot update counts on a completed or cancelled stocktake.');
            }

            foreach ($items as $item) {
                $existing = $stocktake->stocktakeItems()
                    ->where('product_id', $item['product_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'counted_qty' => $item['counted_qty'],
                        'variance' => $item['counted_qty'] - $existing->expected_qty,
                        'cost_impact' => null, // Will be calculated on apply
                        'notes' => $item['notes'] ?? $existing->notes,
                        'counted_at' => now(),
                    ]);
                } else {
                    // Product not in expected stock — discovered item
                    StocktakeItem::create([
                        'stocktake_id' => $stocktake->id,
                        'product_id' => $item['product_id'],
                        'expected_qty' => 0,
                        'counted_qty' => $item['counted_qty'],
                        'variance' => $item['counted_qty'],
                        'notes' => $item['notes'] ?? null,
                        'counted_at' => now(),
                    ]);
                }
            }

            // Move to review if all items counted
            $uncounted = $stocktake->stocktakeItems()->whereNull('counted_qty')->count();
            if ($uncounted === 0 && $stocktake->status === StocktakeStatus::InProgress) {
                $stocktake->update(['status' => StocktakeStatus::Review]);
            }

            return $stocktake->fresh(['stocktakeItems.product']);
        });
    }

    public function apply(string $id, string $userId): Stocktake
    {
        return DB::transaction(function () use ($id, $userId) {
            $stocktake = Stocktake::with('stocktakeItems')->findOrFail($id);

            if ($stocktake->status === StocktakeStatus::Completed) {
                throw new \RuntimeException('Stocktake is already completed.');
            }

            if ($stocktake->status === StocktakeStatus::Cancelled) {
                throw new \RuntimeException('Cannot apply a cancelled stocktake.');
            }

            foreach ($stocktake->stocktakeItems as $item) {
                if ($item->counted_qty === null) {
                    continue; // Skip uncounted items
                }

                $variance = $item->counted_qty - $item->expected_qty;

                if ($variance != 0) {
                    $level = $this->stockService->getOrCreate($stocktake->store_id, $item->product_id);
                    $unitCost = (float) $level->average_cost;

                    $type = $variance > 0
                        ? StockMovementType::AdjustmentIn
                        : StockMovementType::AdjustmentOut;

                    $this->stockService->adjustStock(
                        storeId: $stocktake->store_id,
                        productId: $item->product_id,
                        type: $type,
                        quantity: abs($variance),
                        unitCost: $unitCost,
                        referenceType: StockReferenceType::Adjustment,
                        referenceId: $stocktake->id,
                        reason: 'Stocktake: ' . $stocktake->reference_number,
                        performedBy: $userId,
                    );

                    $item->update([
                        'cost_impact' => $variance * $unitCost,
                    ]);
                }
            }

            $stocktake->update([
                'status' => StocktakeStatus::Completed,
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);

            return $stocktake->fresh(['stocktakeItems.product']);
        });
    }

    public function cancel(string $id): Stocktake
    {
        $stocktake = Stocktake::findOrFail($id);

        if ($stocktake->status === StocktakeStatus::Completed) {
            throw new \RuntimeException('Cannot cancel a completed stocktake.');
        }

        $stocktake->update(['status' => StocktakeStatus::Cancelled]);

        return $stocktake;
    }
}
