<?php

namespace App\Domain\Payment\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\PaymentMethodKey;
use App\Domain\Payment\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentService
{
    public function list(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Payment::query()
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId)
            ->select('payments.*');

        if (!empty($filters['method'])) {
            $query->where('payments.method', $filters['method']);
        }

        if (!empty($filters['transaction_id'])) {
            $query->where('payments.transaction_id', $filters['transaction_id']);
        }

        return $query->orderByDesc('payments.id')->paginate($perPage);
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
