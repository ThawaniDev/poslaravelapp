<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
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
     * Mark PO as partially or fully received.
     * This updates item quantities received; stock is applied via GoodsReceipt separately.
     */
    public function receive(string $id, string $storeId, array $receivedItems): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $storeId, $receivedItems) {
            $po = PurchaseOrder::where('store_id', $storeId)
                ->with('purchaseOrderItems')->findOrFail($id);

            if (!in_array($po->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])) {
                throw new \RuntimeException('Only sent or partially-received POs can be received.');
            }

            // Build lookup: product_id → quantity_received
            $receivedLookup = [];
            foreach ($receivedItems as $ri) {
                $receivedLookup[$ri['product_id']] = (float) $ri['quantity_received'];
            }

            $allFullyReceived = true;
            foreach ($po->purchaseOrderItems as $item) {
                if (isset($receivedLookup[$item->product_id])) {
                    $item->quantity_received = ($item->quantity_received ?? 0) + $receivedLookup[$item->product_id];
                    $item->save();
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
