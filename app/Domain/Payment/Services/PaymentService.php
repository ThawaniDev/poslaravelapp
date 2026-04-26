<?php

namespace App\Domain\Payment\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\PaymentMethodKey;
use App\Domain\Payment\Models\Payment;
use App\Domain\Shared\Traits\ScopesStoreQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentService
{
    use ScopesStoreQuery;

    public function list(string|array $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Payment::query()
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->select('payments.*');

        $query = $this->scopeByStore($query, $storeId, 'transactions.store_id');

        if (!empty($filters['method'])) {
            $query->where('payments.method', $filters['method']);
        }

        if (!empty($filters['transaction_id'])) {
            $query->where('payments.transaction_id', $filters['transaction_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('payments.status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('payments.created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('payments.created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('payments.card_last_four', 'like', "%{$filters['search']}%")
                  ->orWhere('payments.card_reference', 'like', "%{$filters['search']}%")
                  ->orWhere('payments.gift_card_code', 'like', "%{$filters['search']}%")
                  ->orWhere('payments.nearpay_transaction_id', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderByDesc('payments.created_at')->paginate($perPage);
    }

    public function find(string $paymentId): Payment
    {
        return Payment::findOrFail($paymentId);
    }

    public function create(array $data, User $actor): Payment
    {
        return Payment::create([
            'transaction_id' => $data['transaction_id'],
            'method' => $data['method'],
            'amount' => $data['amount'],
            'cash_tendered' => $data['cash_tendered'] ?? null,
            'change_given' => $data['change_given'] ?? null,
            'tip_amount' => $data['tip_amount'] ?? 0,
            'card_brand' => $data['card_brand'] ?? null,
            'card_last_four' => $data['card_last_four'] ?? null,
            'card_auth_code' => $data['card_auth_code'] ?? null,
            'card_reference' => $data['card_reference'] ?? null,
            'gift_card_code' => $data['gift_card_code'] ?? null,
            'coupon_code' => $data['coupon_code'] ?? null,
            'loyalty_points_used' => $data['loyalty_points_used'] ?? 0,
            'created_at' => now(),
        ]);
    }
}
