<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * List purchase orders for a store.
     */
    public function list(string $storeId, int $perPage = 25, ?string $status = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = PurchaseOrder::where('store_id', $storeId)
            ->with(['supplier', 'createdBy']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Get a single PO with items.
     */
    public function find(string $id, string $storeId): PurchaseOrder
    {
        return PurchaseOrder::where('store_id', $storeId)
            ->with(['purchaseOrderItems.product', 'supplier'])->findOrFail($id);
    }

    /**
     * Create a draft PO.
     */
    public function create(array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $totalCost = 0;
            foreach ($items as $item) {
                $totalCost += ($item['quantity_ordered'] ?? 0) * ($item['unit_cost'] ?? 0);
            }

            $po = PurchaseOrder::create([
                'organization_id' => $data['organization_id'],
                'store_id' => $data['store_id'],
                'supplier_id' => $data['supplier_id'],
                'status' => PurchaseOrderStatus::Draft,
                'reference_number' => $data['reference_number'] ?? null,
                'total_cost' => $totalCost,
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                ]);
            }

            return $po->load('purchaseOrderItems');
        });
    }

    /**
     * Mark PO as sent to supplier.
     */
    public function send(string $id, string $storeId): PurchaseOrder
    {
        $po = PurchaseOrder::where('store_id', $storeId)->findOrFail($id);

        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new \RuntimeException('Only draft POs can be sent.');
        }

        $po->status = PurchaseOrderStatus::Sent;
        $po->save();

        return $po;
    }

    /**
     * Mark PO as partially or fully received and CASCADE the receipt to stock:
     * each newly-received quantity is added to stock_levels and an immutable
     * stock_movements row of type Receipt is created with reference back to
     * this PO. WAC is recalculated using the PO line's unit_cost.
     *
     * The delta in this call ($receivedItems[i]['quantity_received']) is what
     * is added to stock — NOT the cumulative quantity_received on the line.
     * This makes partial receipts safe to call repeatedly without double-
     * counting.
     */
    public function receive(string $id, string $storeId, array $receivedItems): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $storeId, $receivedItems) {
            $po = PurchaseOrder::where('store_id', $storeId)
                ->with('purchaseOrderItems')->findOrFail($id);

            if (!in_array($po->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])) {
                throw new \RuntimeException('Only sent or partially-received POs can be received.');
            }

            // Build lookup: product_id → quantity_received in THIS call (delta).
            $receivedLookup = [];
            foreach ($receivedItems as $ri) {
                $receivedLookup[$ri['product_id']] = (float) $ri['quantity_received'];
            }

            $performedBy = Auth::id();
            $allFullyReceived = true;

            foreach ($po->purchaseOrderItems as $item) {
                $delta = $receivedLookup[$item->product_id] ?? 0.0;

                if ($delta > 0) {
                    // 1. Bump the cumulative quantity_received on the PO line.
                    $item->quantity_received = ($item->quantity_received ?? 0) + $delta;
                    $item->save();

                    // 2. Apply the delta to stock_levels + stock_movements.
                    //    Receipt movement type triggers WAC recalculation in
                    //    StockService using this line's unit_cost.
                    $this->stockService->adjustStock(
                        storeId: $storeId,
                        productId: $item->product_id,
                        type: StockMovementType::Receipt,
                        quantity: $delta,
                        unitCost: (float) ($item->unit_cost ?? 0),
                        referenceType: StockReferenceType::PurchaseOrder,
                        referenceId: $po->id,
                        reason: 'Purchase order receipt',
                        performedBy: $performedBy,
                    );
                }

                if (($item->quantity_received ?? 0) < $item->quantity_ordered) {
                    $allFullyReceived = false;
                }
            }

            $po->status = $allFullyReceived
                ? PurchaseOrderStatus::FullyReceived
                : PurchaseOrderStatus::PartiallyReceived;
            $po->save();

            return $po->fresh(['purchaseOrderItems.product', 'supplier']);
        });
    }

    /**
     * Cancel a PO (only draft or sent).
     */
    public function cancel(string $id, string $storeId): PurchaseOrder
    {
        $po = PurchaseOrder::where('store_id', $storeId)->findOrFail($id);

        if (!in_array($po->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent])) {
            throw new \RuntimeException('Only draft or sent POs can be cancelled.');
        }

        $po->status = PurchaseOrderStatus::Cancelled;
        $po->save();

        return $po;
    }
}
