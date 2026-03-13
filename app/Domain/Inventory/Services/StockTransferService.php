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
    public function find(string $id): StockTransfer
    {
        return StockTransfer::with(['stockTransferItems.product', 'fromStore', 'toStore'])->findOrFail($id);
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
     * Approve transfer → status = in_transit, deduct from source store.
     */
    public function approve(string $id, string $userId): StockTransfer
    {
        return DB::transaction(function () use ($id, $userId) {
            $transfer = StockTransfer::with('stockTransferItems')->findOrFail($id);

            if ($transfer->status !== StockTransferStatus::Pending) {
                throw new \RuntimeException('Only pending transfers can be approved.');
            }

            // Deduct stock from source store
            foreach ($transfer->stockTransferItems as $item) {
                $this->stockService->adjustStock(
                    storeId: $transfer->from_store_id,
                    productId: $item->product_id,
                    type: StockMovementType::TransferOut,
                    quantity: (float) $item->quantity_sent,
                    referenceType: StockReferenceType::Transfer,
                    referenceId: $transfer->id,
                    performedBy: $userId,
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
     * Receive transfer → status = completed, add to destination store.
     * Accepts received quantities (may differ from sent = variance).
     */
    public function receive(string $id, string $userId, array $receivedItems = []): StockTransfer
    {
        return DB::transaction(function () use ($id, $userId, $receivedItems) {
            $transfer = StockTransfer::with('stockTransferItems')->findOrFail($id);

            if ($transfer->status !== StockTransferStatus::InTransit) {
                throw new \RuntimeException('Only in-transit transfers can be received.');
            }

            // Build lookup: product_id → quantity_received
            $receivedLookup = [];
            foreach ($receivedItems as $ri) {
                $receivedLookup[$ri['product_id']] = (float) $ri['quantity_received'];
            }

            foreach ($transfer->stockTransferItems as $item) {
                $receivedQty = $receivedLookup[$item->product_id] ?? (float) $item->quantity_sent;
                $item->quantity_received = $receivedQty;
                $item->save();

                // Add stock to destination store
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

            $transfer->status = StockTransferStatus::Completed;
            $transfer->received_by = $userId;
            $transfer->received_at = now();
            $transfer->save();

            return $transfer->fresh(['stockTransferItems.product', 'fromStore', 'toStore']);
        });
    }

    /**
     * Cancel a pending transfer (cannot cancel once in-transit or completed).
     */
    public function cancel(string $id): StockTransfer
    {
        $transfer = StockTransfer::findOrFail($id);

        if ($transfer->status !== StockTransferStatus::Pending) {
            throw new \RuntimeException('Only pending transfers can be cancelled.');
        }

        $transfer->status = StockTransferStatus::Cancelled;
        $transfer->save();

        return $transfer;
    }
}
