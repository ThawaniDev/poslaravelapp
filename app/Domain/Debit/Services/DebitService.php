<?php

namespace App\Domain\Debit\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Debit\Enums\DebitStatus;
use App\Domain\Debit\Models\Debit;
use App\Domain\Debit\Models\DebitAllocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DebitService
{
    // ─── Queries ────────────────────────────────────────────────

    public function list(
        string $organizationId,
        array $filters = [],
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = Debit::where('organization_id', $organizationId)
            ->with(['customer', 'createdBy', 'allocations']);

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['debit_type'])) {
            $query->where('debit_type', $filters['debit_type']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function find(string $debitId): Debit
    {
        return Debit::with([
            'customer',
            'createdBy',
            'allocatedBy',
            'allocations.order',
            'allocations.allocatedBy',
        ])->findOrFail($debitId);
    }

    public function findByCustomer(string $organizationId, string $customerId): Collection
    {
        return Debit::where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->active()
            ->with(['allocations'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getCustomerDebitBalance(string $organizationId, string $customerId): float
    {
        $totalDebits = Debit::where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->active()
            ->sum('amount');

        $totalAllocated = DebitAllocation::whereHas('debit', function ($q) use ($organizationId, $customerId) {
            $q->where('organization_id', $organizationId)
                ->where('customer_id', $customerId)
                ->active();
        })->sum('amount');

        return round((float) $totalDebits - (float) $totalAllocated, 2);
    }

    public function getSummary(string $organizationId): array
    {
        $debits = Debit::where('organization_id', $organizationId);

        return [
            'total_debits' => (float) (clone $debits)->sum('amount'),
            'pending_count' => (clone $debits)->where('status', DebitStatus::Pending)->count(),
            'pending_amount' => (float) (clone $debits)->where('status', DebitStatus::Pending)->sum('amount'),
            'partially_allocated_count' => (clone $debits)->where('status', DebitStatus::PartiallyAllocated)->count(),
            'fully_allocated_count' => (clone $debits)->where('status', DebitStatus::FullyAllocated)->count(),
            'reversed_count' => (clone $debits)->where('status', DebitStatus::Reversed)->count(),
            'total_allocated' => (float) DebitAllocation::whereHas('debit', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                    ->where('status', '!=', DebitStatus::Reversed);
            })->sum('amount'),
        ];
    }

    // ─── CRUD ───────────────────────────────────────────────────

    public function create(array $data, User $actor): Debit
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['organization_id'] = $actor->organization_id;
            $data['store_id'] = $actor->store_id;
            $data['created_by'] = $actor->id;
            $data['status'] = DebitStatus::Pending->value;
            $data['sync_version'] = 1;

            return Debit::create($data);
        });
    }

    public function update(Debit $debit, array $data): Debit
    {
        return DB::transaction(function () use ($debit, $data) {
            $data['sync_version'] = ($debit->sync_version ?? 0) + 1;
            $debit->update($data);

            return $debit->fresh(['customer', 'createdBy', 'allocations']);
        });
    }

    public function allocate(Debit $debit, array $allocationData, User $actor): DebitAllocation
    {
        return DB::transaction(function () use ($debit, $allocationData, $actor) {
            $remaining = $debit->remaining_balance;

            if ($remaining < (float) $allocationData['amount']) {
                throw new \RuntimeException('Allocation amount exceeds remaining debit balance.');
            }

            $allocation = $debit->allocations()->create([
                'order_id' => $allocationData['order_id'],
                'amount' => $allocationData['amount'],
                'notes' => $allocationData['notes'] ?? null,
                'allocated_by' => $actor->id,
                'allocated_at' => now(),
            ]);

            // Refresh to recalculate remaining_balance
            $debit->refresh();

            if ($debit->isFullyAllocated()) {
                $debit->update([
                    'status' => DebitStatus::FullyAllocated,
                    'allocated_by' => $actor->id,
                    'allocated_at' => now(),
                ]);
            } else {
                $debit->update(['status' => DebitStatus::PartiallyAllocated]);
            }

            return $allocation->load(['order', 'allocatedBy']);
        });
    }

    public function reverse(Debit $debit, User $actor, string $reason = ''): Debit
    {
        return DB::transaction(function () use ($debit, $actor, $reason) {
            $notes = $debit->notes;
            if ($reason) {
                $notes = ($notes ? $notes . "\n" : '') . 'Reversed: ' . $reason;
            }

            $debit->update([
                'status' => DebitStatus::Reversed,
                'notes' => $notes,
                'sync_version' => ($debit->sync_version ?? 0) + 1,
            ]);

            return $debit->fresh(['customer', 'createdBy', 'allocations']);
        });
    }

    public function delete(Debit $debit): void
    {
        if ($debit->allocations()->exists()) {
            throw new \RuntimeException('Cannot delete debit with existing allocations. Reverse it instead.');
        }

        $debit->delete();
    }
}
