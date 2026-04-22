<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Enums\StockTransferStatus;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * List transfers for an organization.
     */
    public function list(string $organizationId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return StockTransfer::where('organization_id', $organizationId)
            ->with(['fromStore', 'toStore', 'createdBy'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get a single transfer with items.
     */
    public function find(string $organizationId, string $id): StockTransfer
    {
        return StockTransfer::with(['stockTransferItems.product', 'fromStore', 'toStore'])
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    /**
     * Create a pending transfer.
     */
    public function create(array $data, array $items): StockTransfer
    {
        return DB::transaction(function () use ($data, $items) {
            $transfer = StockTransfer::create([
                'organization_id' => $data['organization_id'],
                'from_store_id' => $data['from_store_id'],
                'to_store_id' => $data['to_store_id'],
                'status' => StockTransferStatus::Pending,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity_sent' => $item['quantity_sent'],
                ]);
            }

            return $transfer->load('stockTransferItems');
        });
    }

    /**
     * Approve transfer → status = in_transit. Source stock is RESERVED (moved
     * from `quantity` minus into `reserved_quantity`) so it can no longer be
     * sold or wasted, but the on-hand `quantity` stays put until the
     * destination receives. The actual TransferOut movement is written on
     * receive(), giving us a true 2-phase commit across stores.
     */
    public function approve(string $id, string $organizationId, string $userId): StockTransfer
    {
        return DB::transaction(function () use ($id, $organizationId, $userId) {
            $transfer = StockTransfer::where('organization_id', $organizationId)
                ->with('stockTransferItems')->lockForUpdate()->findOrFail($id);

            if ($transfer->status !== StockTransferStatus::Pending) {
                throw new \RuntimeException('Only pending transfers can be approved.');
            }

            // Reserve source stock per line — fails if any line lacks
            // sufficient available (quantity - reserved) at source.
            foreach ($transfer->stockTransferItems as $item) {
                $this->stockService->reserve(
                    storeId: $transfer->from_store_id,
                    productId: $item->product_id,
                    qty: (float) $item->quantity_sent,
                );
            }

            $transfer->status = StockTransferStatus::InTransit;
            $transfer->approved_by = $userId;
            $transfer->approved_at = now();
            $transfer->save();

            return $transfer;
        });
    }

    /**
     * Receive transfer → status = completed.
     *  - Cap received_qty <= sent_qty (no over-receive into destination).
     *  - Variance = sent - received is recorded on the line for audit
     *    (in-transit loss / damage). Source still loses the full sent qty
     *    (it left the store), destination only credits what arrived.
     *  - Reservation at source is released (it has now physically left).
     */
    public function receive(string $id, string $organizationId, string $userId, array $receivedItems = []): StockTransfer
    {
        return DB::transaction(function () use ($id, $organizationId, $userId, $receivedItems) {
            $transfer = StockTransfer::where('organization_id', $organizationId)
                ->with('stockTransferItems')->lockForUpdate()->findOrFail($id);

            if ($transfer->status !== StockTransferStatus::InTransit) {
                throw new \RuntimeException('Only in-transit transfers can be received.');
            }

            // Build lookup: product_id → quantity_received
            $receivedLookup = [];
            foreach ($receivedItems as $ri) {
                $receivedLookup[$ri['product_id']] = (float) $ri['quantity_received'];
            }

            foreach ($transfer->stockTransferItems as $item) {
                $sentQty = (float) $item->quantity_sent;
                $receivedQty = $receivedLookup[$item->product_id] ?? $sentQty;

                if ($receivedQty < 0) {
                    throw new \RuntimeException("Received quantity cannot be negative for product {$item->product_id}.");
                }
                if ($receivedQty > $sentQty) {
                    throw new \RuntimeException(
                        "Cannot receive {$receivedQty} of product {$item->product_id}: "
                        . "only {$sentQty} were sent."
                    );
                }

                $variance = $sentQty - $receivedQty;
                $item->quantity_received = $receivedQty;
                if ($variance > 0) {
                    $item->variance_qty = $variance;
                    $item->variance_reason = 'In-transit loss / damage';
                }
                $item->save();

                // Release source reservation, then deduct full sent qty from
                // source on-hand (TransferOut). Destination credits only what
                // arrived (TransferIn).
                $this->stockService->releaseReservation(
                    storeId: $transfer->from_store_id,
                    productId: $item->product_id,
                    qty: $sentQty,
                );

                $this->stockService->adjustStock(
                    storeId: $transfer->from_store_id,
                    productId: $item->product_id,
                    type: StockMovementType::TransferOut,
                    quantity: $sentQty,
                    referenceType: StockReferenceType::Transfer,
                    referenceId: $transfer->id,
                    reason: $variance > 0 ? "Transfer out (variance: -{$variance})" : null,
                    performedBy: $userId,
                );

                if ($receivedQty > 0) {
                    $this->stockService->adjustStock(
                        storeId: $transfer->to_store_id,
                        productId: $item->product_id,
                        type: StockMovementType::TransferIn,
                        quantity: $receivedQty,
                        referenceType: StockReferenceType::Transfer,
                        referenceId: $transfer->id,
                        performedBy: $userId,
                    );
                }
            }

            $transfer->status = StockTransferStatus::Completed;
            $transfer->received_by = $userId;
            $transfer->received_at = now();
            $transfer->save();

            return $transfer->fresh(['stockTransferItems.product', 'fromStore', 'toStore']);
        });
    }

    /**
     * Cancel a transfer.
     *  - Pending: simple status change.
     *  - InTransit: release the source reservation (stock returns to free pool).
     *  - Completed/Cancelled: not allowed.
     */
    public function cancel(string $id, string $organizationId): StockTransfer
    {
        return DB::transaction(function () use ($id, $organizationId) {
            $transfer = StockTransfer::where('organization_id', $organizationId)
                ->with('stockTransferItems')->lockForUpdate()->findOrFail($id);

            if (!in_array($transfer->status, [StockTransferStatus::Pending, StockTransferStatus::InTransit], true)) {
                throw new \RuntimeException('Only pending or in-transit transfers can be cancelled.');
            }

            if ($transfer->status === StockTransferStatus::InTransit) {
                foreach ($transfer->stockTransferItems as $item) {
                    $this->stockService->releaseReservation(
                        storeId: $transfer->from_store_id,
                        productId: $item->product_id,
                        qty: (float) $item->quantity_sent,
                    );
                }
            }

            $transfer->status = StockTransferStatus::Cancelled;
            $transfer->save();

            return $transfer;
        });
    }
}
