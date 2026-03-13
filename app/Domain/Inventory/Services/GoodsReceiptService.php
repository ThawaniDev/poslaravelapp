<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\GoodsReceiptStatus;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\GoodsReceipt;
use App\Domain\Inventory\Models\GoodsReceiptItem;
use App\Domain\Inventory\Models\StockBatch;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * List goods receipts for a store.
     */
    public function list(string $storeId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return GoodsReceipt::where('store_id', $storeId)
            ->with(['supplier', 'receivedBy'])
            ->orderByDesc('received_at')
            ->paginate($perPage);
    }

    /**
     * Get a single goods receipt with items.
     */
    public function find(string $id): GoodsReceipt
    {
        return GoodsReceipt::with(['goodsReceiptItems.product', 'supplier', 'receivedBy'])->findOrFail($id);
    }

    /**
     * Create a draft goods receipt with items.
     */
    public function create(array $data, array $items): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $items) {
            $totalCost = 0;
            foreach ($items as $item) {
                $totalCost += ($item['quantity'] ?? 0) * ($item['unit_cost'] ?? 0);
            }

            $receipt = GoodsReceipt::create([
                'store_id' => $data['store_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'status' => GoodsReceiptStatus::Draft,
                'total_cost' => $totalCost,
                'notes' => $data['notes'] ?? null,
                'received_by' => $data['received_by'] ?? null,
                'received_at' => now(),
            ]);

            foreach ($items as $item) {
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);
            }

            return $receipt->load('goodsReceiptItems');
        });
    }

    /**
     * Confirm a draft goods receipt — add stock + create batches.
     * Once confirmed, cannot be unconfirmed (business rule).
     */
    public function confirm(string $id, string $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($id, $userId) {
            $receipt = GoodsReceipt::with('goodsReceiptItems')->findOrFail($id);

            if ($receipt->status === GoodsReceiptStatus::Confirmed) {
                throw new \RuntimeException('Goods receipt is already confirmed.');
            }

            foreach ($receipt->goodsReceiptItems as $item) {
                // Add stock level + WAC
                $this->stockService->adjustStock(
                    storeId: $receipt->store_id,
                    productId: $item->product_id,
                    type: StockMovementType::Receipt,
                    quantity: (float) $item->quantity,
                    unitCost: (float) $item->unit_cost,
                    referenceType: StockReferenceType::GoodsReceipt,
                    referenceId: $receipt->id,
                    performedBy: $userId,
                );

                // Create batch record if batch/expiry
                if ($item->batch_number || $item->expiry_date) {
                    StockBatch::create([
                        'store_id' => $receipt->store_id,
                        'product_id' => $item->product_id,
                        'batch_number' => $item->batch_number,
                        'expiry_date' => $item->expiry_date,
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->unit_cost,
                        'goods_receipt_id' => $receipt->id,
                    ]);
                }
            }

            $receipt->status = GoodsReceiptStatus::Confirmed;
            $receipt->confirmed_at = now();
            $receipt->save();

            return $receipt->fresh(['goodsReceiptItems.product', 'supplier']);
        });
    }
}
