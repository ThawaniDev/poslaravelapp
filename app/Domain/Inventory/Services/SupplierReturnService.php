<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Enums\SupplierReturnStatus;
use App\Domain\Inventory\Models\SupplierReturn;
use App\Domain\Inventory\Models\SupplierReturnItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierReturnService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * List supplier returns for an organization, filterable by status and supplier.
     */
    public function list(
        string $organizationId,
        ?string $status = null,
        ?string $supplierId = null,
        ?string $search = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = SupplierReturn::where('organization_id', $organizationId)
            ->with(['supplier', 'createdBy'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a single supplier return with items.
     */
    public function find(string $organizationId, string $id): SupplierReturn
    {
        return SupplierReturn::where('organization_id', $organizationId)
            ->with(['items.product', 'supplier', 'createdBy', 'approvedBy'])
            ->findOrFail($id);
    }

    /**
     * Create a draft supplier return with items.
     */
    public function create(array $data, array $items): SupplierReturn
    {
        return DB::transaction(function () use ($data, $items) {
            $totalAmount = 0;
            foreach ($items as $item) {
                $totalAmount += ($item['quantity'] ?? 0) * ($item['unit_cost'] ?? 0);
            }

            $return = SupplierReturn::create([
                'organization_id' => $data['organization_id'],
                'store_id' => $data['store_id'],
                'supplier_id' => $data['supplier_id'],
                'reference_number' => $data['reference_number'] ?? null,
                'status' => SupplierReturnStatus::Draft,
                'reason' => $data['reason'] ?? null,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($items as $item) {
                SupplierReturnItem::create([
                    'supplier_return_id' => $return->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'reason' => $item['reason'] ?? null,
                    'batch_number' => $item['batch_number'] ?? null,
                ]);
            }

            return $return->load('items');
        });
    }

    /**
     * Update a draft supplier return (items can be replaced).
     */
    public function update(SupplierReturn $return, array $data, ?array $items = null): SupplierReturn
    {
        if ($return->status !== SupplierReturnStatus::Draft) {
            throw new \RuntimeException('Only draft returns can be edited.');
        }

        return DB::transaction(function () use ($return, $data, $items) {
            $return->update(array_filter([
                'supplier_id' => $data['supplier_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($v) => $v !== null));

            if ($items !== null) {
                $return->items()->delete();

                $totalAmount = 0;
                foreach ($items as $item) {
                    $totalAmount += ($item['quantity'] ?? 0) * ($item['unit_cost'] ?? 0);
                    SupplierReturnItem::create([
                        'supplier_return_id' => $return->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'] ?? 0,
                        'reason' => $item['reason'] ?? null,
                        'batch_number' => $item['batch_number'] ?? null,
                    ]);
                }

                $return->update(['total_amount' => $totalAmount]);
            }

            return $return->fresh(['items.product', 'supplier', 'createdBy']);
        });
    }

    /**
     * Submit a draft return for approval.
     */
    public function submit(string $id): SupplierReturn
    {
        $return = SupplierReturn::findOrFail($id);

        if ($return->status !== SupplierReturnStatus::Draft) {
            throw new \RuntimeException('Only draft returns can be submitted.');
        }

        $return->update(['status' => SupplierReturnStatus::Submitted]);

        return $return->fresh(['items.product', 'supplier', 'createdBy']);
    }

    /**
     * Approve a submitted return.
     */
    public function approve(string $id, string $approvedBy): SupplierReturn
    {
        $return = SupplierReturn::findOrFail($id);

        if ($return->status !== SupplierReturnStatus::Submitted) {
            throw new \RuntimeException('Only submitted returns can be approved.');
        }

        $return->update([
            'status' => SupplierReturnStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $return->fresh(['items.product', 'supplier', 'createdBy', 'approvedBy']);
    }

    /**
     * Complete an approved return — deducts stock.
     */
    public function complete(string $id): SupplierReturn
    {
        return DB::transaction(function () use ($id) {
            $return = SupplierReturn::with('items')->findOrFail($id);

            if ($return->status !== SupplierReturnStatus::Approved) {
                throw new \RuntimeException('Only approved returns can be completed.');
            }

            // Pre-check: every line must have enough on-hand stock to ship back.
            foreach ($return->items as $item) {
                $this->stockService->assertSufficientStock(
                    storeId: $return->store_id,
                    productId: $item->product_id,
                    needed: (float) $item->quantity,
                );
            }

            foreach ($return->items as $item) {
                $this->stockService->adjustStock(
                    storeId: $return->store_id,
                    productId: $item->product_id,
                    type: StockMovementType::SupplierReturn,
                    quantity: -1 * (float) $item->quantity,
                    unitCost: (float) $item->unit_cost,
                    referenceType: StockReferenceType::SupplierReturn,
                    referenceId: $return->id,
                    performedBy: $return->created_by,
                );
            }

            $return->update([
                'status' => SupplierReturnStatus::Completed,
                'completed_at' => now(),
            ]);

            return $return->fresh(['items.product', 'supplier', 'createdBy', 'approvedBy']);
        });
    }

    /**
     * Cancel a return (only when draft or submitted).
     */
    public function cancel(string $id): SupplierReturn
    {
        $return = SupplierReturn::findOrFail($id);

        if (in_array($return->status, [SupplierReturnStatus::Completed, SupplierReturnStatus::Cancelled])) {
            throw new \RuntimeException('Cannot cancel a completed or already cancelled return.');
        }

        $return->update(['status' => SupplierReturnStatus::Cancelled]);

        return $return->fresh(['items.product', 'supplier', 'createdBy']);
    }

    /**
     * Delete a draft return.
     */
    public function delete(SupplierReturn $return): void
    {
        if ($return->status !== SupplierReturnStatus::Draft) {
            throw new \RuntimeException('Only draft returns can be deleted.');
        }

        $return->items()->delete();
        $return->delete();
    }
}
