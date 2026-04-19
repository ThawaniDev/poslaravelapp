<?php

namespace App\Domain\Receivable\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Receivable\Enums\ReceivableStatus;
use App\Domain\Receivable\Models\Receivable;
use App\Domain\Receivable\Models\ReceivableLog;
use App\Domain\Receivable\Models\ReceivablePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReceivableService
{
    // ─── Queries ────────────────────────────────────────────────

    public function list(
        string $organizationId,
        array $filters = [],
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = Receivable::where('organization_id', $organizationId)
            ->with(['customer', 'createdBy', 'payments']);

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['receivable_type'])) {
            $query->where('receivable_type', $filters['receivable_type']);
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

        if (! empty($filters['overdue'])) {
            $query->whereDate('due_date', '<', now())
                ->whereIn('status', [ReceivableStatus::Pending, ReceivableStatus::PartiallyPaid]);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function find(string $receivableId): Receivable
    {
        return Receivable::with([
            'customer',
            'createdBy',
            'settledBy',
            'payments.order',
            'payments.settledBy',
            'logs.actor',
        ])->findOrFail($receivableId);
    }

    public function findByCustomer(string $organizationId, string $customerId): Collection
    {
        return Receivable::where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->active()
            ->with(['payments'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getCustomerReceivableBalance(string $organizationId, string $customerId): float
    {
        $totalReceivables = Receivable::where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->active()
            ->sum('amount');

        $totalPaid = ReceivablePayment::whereHas('receivable', function ($q) use ($organizationId, $customerId) {
            $q->where('organization_id', $organizationId)
                ->where('customer_id', $customerId)
                ->active();
        })->sum('amount');

        return round((float) $totalReceivables - (float) $totalPaid, 2);
    }

    public function getSummary(string $organizationId): array
    {
        $receivables = Receivable::where('organization_id', $organizationId);

        return [
            'total_receivables' => (float) (clone $receivables)->sum('amount'),
            'pending_count' => (clone $receivables)->where('status', ReceivableStatus::Pending)->count(),
            'pending_amount' => (float) (clone $receivables)->where('status', ReceivableStatus::Pending)->sum('amount'),
            'partially_paid_count' => (clone $receivables)->where('status', ReceivableStatus::PartiallyPaid)->count(),
            'fully_paid_count' => (clone $receivables)->where('status', ReceivableStatus::FullyPaid)->count(),
            'reversed_count' => (clone $receivables)->where('status', ReceivableStatus::Reversed)->count(),
            'overdue_count' => (clone $receivables)
                ->whereDate('due_date', '<', now())
                ->whereIn('status', [ReceivableStatus::Pending, ReceivableStatus::PartiallyPaid])
                ->count(),
            'total_paid' => (float) ReceivablePayment::whereHas('receivable', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                    ->where('status', '!=', ReceivableStatus::Reversed);
            })->sum('amount'),
        ];
    }

    public function listLogs(Receivable $receivable): Collection
    {
        return $receivable->logs()->with('actor')->get();
    }

    // ─── Mutations ──────────────────────────────────────────────

    public function create(array $data, User $actor): Receivable
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['organization_id'] = $actor->organization_id;
            $data['store_id'] = $actor->store_id;
            $data['created_by'] = $actor->id;
            $data['status'] = ReceivableStatus::Pending->value;
            $data['sync_version'] = 1;

            $receivable = Receivable::create($data);

            $this->log(
                $receivable,
                $actor,
                'created',
                null,
                null,
                (float) $receivable->amount,
                'Receivable created for ' . number_format((float) $receivable->amount, 2),
            );

            return $receivable;
        });
    }

    public function update(Receivable $receivable, array $data, User $actor): Receivable
    {
        return DB::transaction(function () use ($receivable, $data, $actor) {
            $tracked = ['amount', 'due_date', 'description', 'description_ar', 'reference_number', 'receivable_type', 'source'];

            foreach ($tracked as $field) {
                if (array_key_exists($field, $data) && (string) $receivable->{$field} !== (string) $data[$field]) {
                    $this->log(
                        $receivable,
                        $actor,
                        $field . '_updated',
                        (string) $receivable->{$field},
                        (string) $data[$field],
                        $field === 'amount' ? (float) $data[$field] : null,
                        null,
                    );
                }
            }

            if (array_key_exists('notes', $data) && $receivable->notes !== $data['notes']) {
                $this->log($receivable, $actor, 'note_updated', null, null, null, $data['notes']);
            }

            $data['sync_version'] = ($receivable->sync_version ?? 0) + 1;
            $receivable->update($data);

            return $receivable->fresh(['customer', 'createdBy', 'payments', 'logs.actor']);
        });
    }

    public function addNote(Receivable $receivable, string $note, User $actor): Receivable
    {
        return DB::transaction(function () use ($receivable, $note, $actor) {
            $this->log($receivable, $actor, 'note_added', null, null, null, $note);

            return $receivable->fresh(['customer', 'createdBy', 'payments', 'logs.actor']);
        });
    }

    public function recordPayment(Receivable $receivable, array $paymentData, User $actor): ReceivablePayment
    {
        return DB::transaction(function () use ($receivable, $paymentData, $actor) {
            $remaining = $receivable->remaining_balance;

            if ($remaining < (float) $paymentData['amount']) {
                throw new \RuntimeException('Payment amount exceeds outstanding balance.');
            }

            $payment = $receivable->payments()->create([
                'order_id' => $paymentData['order_id'] ?? null,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'amount' => $paymentData['amount'],
                'notes' => $paymentData['notes'] ?? null,
                'settled_by' => $actor->id,
                'settled_at' => now(),
            ]);

            $receivable->refresh();
            $oldStatus = $receivable->status?->value;

            if ($receivable->isFullyPaid()) {
                $receivable->update([
                    'status' => ReceivableStatus::FullyPaid,
                    'settled_by' => $actor->id,
                    'settled_at' => now(),
                ]);
            } else {
                $receivable->update(['status' => ReceivableStatus::PartiallyPaid]);
            }

            $this->log(
                $receivable,
                $actor,
                'payment_recorded',
                null,
                null,
                (float) $payment->amount,
                'Payment of ' . number_format((float) $payment->amount, 2) .
                    ($paymentData['payment_method'] ?? null ? ' via ' . $paymentData['payment_method'] : ''),
                ['payment_id' => $payment->id, 'order_id' => $payment->order_id],
            );

            if ($oldStatus !== $receivable->status?->value) {
                $this->log($receivable, $actor, 'status_changed', $oldStatus, $receivable->status?->value, null, null);
            }

            return $payment->load(['order', 'settledBy']);
        });
    }

    public function reverse(Receivable $receivable, User $actor, string $reason = ''): Receivable
    {
        return DB::transaction(function () use ($receivable, $actor, $reason) {
            $oldStatus = $receivable->status?->value;

            $receivable->update([
                'status' => ReceivableStatus::Reversed,
                'sync_version' => ($receivable->sync_version ?? 0) + 1,
            ]);

            $this->log(
                $receivable,
                $actor,
                'reversed',
                $oldStatus,
                ReceivableStatus::Reversed->value,
                null,
                $reason ?: null,
            );

            return $receivable->fresh(['customer', 'createdBy', 'payments', 'logs.actor']);
        });
    }

    public function delete(Receivable $receivable): void
    {
        if ($receivable->payments()->exists()) {
            throw new \RuntimeException('Cannot delete receivable with recorded payments. Reverse it instead.');
        }

        $receivable->delete();
    }

    // ─── Logging helper ─────────────────────────────────────────

    protected function log(
        Receivable $receivable,
        User $actor,
        string $event,
        ?string $from = null,
        ?string $to = null,
        ?float $amount = null,
        ?string $note = null,
        ?array $meta = null,
    ): ReceivableLog {
        return ReceivableLog::create([
            'receivable_id' => $receivable->id,
            'event' => $event,
            'from_value' => $from,
            'to_value' => $to,
            'amount' => $amount,
            'note' => $note,
            'meta' => $meta,
            'actor_id' => $actor->id,
            'created_at' => now(),
        ]);
    }
}
