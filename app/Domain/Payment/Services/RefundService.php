<?php

namespace App\Domain\Payment\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\PaymentMethodKey;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\Shared\Traits\ScopesStoreQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RefundService
{
    use ScopesStoreQuery;

    /**
     * List refunds for a specific payment.
     */
    public function listForPayment(string $paymentId, int $perPage = 20): LengthAwarePaginator
    {
        return Refund::where('payment_id', $paymentId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * List all refunds for the given stores.
     */
    public function listForStore(string|array $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Refund::query()
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->select('refunds.*');

        $query = $this->scopeByStore($query, $storeId, 'transactions.store_id');

        if (!empty($filters['status'])) {
            $query->where('refunds.status', $filters['status']);
        }
        if (!empty($filters['method'])) {
            $query->where('refunds.method', $filters['method']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('refunds.created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('refunds.created_at', '<=', $filters['end_date']);
        }

        return $query->orderByDesc('refunds.created_at')->paginate($perPage);
    }

    /**
     * Create a refund against a payment.
     *
     * @throws \RuntimeException if refund exceeds remaining refundable amount
     */
    public function create(Payment $payment, array $data, User $actor): Refund
    {
        $totalRefunded = (float) $payment->refunds()->where('status', RefundStatus::Completed)->sum('amount');
        $refundableAmount = (float) $payment->amount - $totalRefunded;

        $requestedAmount = (float) $data['amount'];

        if ($requestedAmount > $refundableAmount + 0.001) {
            throw new \RuntimeException(
                "Refund amount ({$requestedAmount}) exceeds refundable amount ({$refundableAmount})."
            );
        }

        $refund = Refund::create([
            'return_id'        => $data['return_id'] ?? $payment->id, // self-reference when no formal return
            'payment_id'       => $payment->id,
            'method'           => $data['method'] ?? $payment->method?->value ?? $payment->method,
            'amount'           => $requestedAmount,
            'reference_number' => $data['reference_number'] ?? null,
            'status'           => RefundStatus::Completed,
            'processed_by'     => $actor->id,
        ]);

        // Mark payment as refunded if fully refunded
        $newTotalRefunded = $totalRefunded + $requestedAmount;
        if (abs($newTotalRefunded - (float) $payment->amount) < 0.01) {
            $payment->update(['status' => 'refunded']);
        } elseif ($newTotalRefunded > 0) {
            $payment->update(['status' => 'partial_refund']);
        }

        return $refund;
    }

    public function find(string $id): Refund
    {
        return Refund::findOrFail($id);
    }
}
