<?php

namespace App\Domain\PosTerminal\Services;

use App\Domain\Auth\Models\User;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\PosTerminal\Models\TransactionItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function list(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Transaction::where('store_id', $storeId);

        if (!empty($filters['session_id'])) {
            $query->where('pos_session_id', $filters['session_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('transaction_number', 'like', "%{$filters['search']}%");
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function find(string $transactionId): Transaction
    {
        return Transaction::with(['transactionItems'])->findOrFail($transactionId);
    }

    public function create(array $data, User $actor): Transaction
    {
        return DB::transaction(function () use ($data, $actor) {
            $transaction = Transaction::create([
                'organization_id' => $actor->organization_id,
                'store_id' => $actor->store_id,
                'register_id' => $data['register_id'] ?? null,
                'pos_session_id' => $data['pos_session_id'] ?? null,
                'cashier_id' => $actor->id,
                'customer_id' => $data['customer_id'] ?? null,
                'transaction_number' => $data['transaction_number'] ?? $this->generateNumber($actor->store_id),
                'type' => $data['type'] ?? TransactionType::Sale->value,
                'status' => $data['status'] ?? TransactionStatus::Completed->value,
                'subtotal' => $data['subtotal'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'tip_amount' => $data['tip_amount'] ?? 0,
                'total_amount' => $data['total_amount'] ?? 0,
                'is_tax_exempt' => $data['is_tax_exempt'] ?? false,
                'return_transaction_id' => $data['return_transaction_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sync_version' => 1,
            ]);

            // Create transaction items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $item['product_id'] ?? null,
                        'barcode' => $item['barcode'] ?? null,
                        'product_name' => $item['product_name'],
                        'product_name_ar' => $item['product_name_ar'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'cost_price' => $item['cost_price'] ?? 0,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'line_total' => $item['line_total'],
                        'is_return_item' => $item['is_return_item'] ?? false,
                    ]);
                }
            }

            // Update session counters if session exists
            if ($transaction->pos_session_id) {
                $session = $transaction->posSession;
                if ($session) {
                    $session->increment('transaction_count');
                    if ($transaction->type === TransactionType::Sale) {
                        $session->increment('total_cash_sales', (float) $transaction->total_amount);
                    }
                }
            }

            return $transaction->load('transactionItems');
        });
    }

    public function void(Transaction $transaction, User $actor): Transaction
    {
        if ($transaction->status === TransactionStatus::Voided) {
            throw new \RuntimeException('This transaction is already voided.');
        }

        if ($transaction->status !== TransactionStatus::Completed) {
            throw new \RuntimeException('Only completed transactions can be voided.');
        }

        $transaction->update([
            'status' => TransactionStatus::Voided,
            'sync_version' => ($transaction->sync_version ?? 0) + 1,
        ]);

        // Update session counters
        if ($transaction->pos_session_id) {
            $session = $transaction->posSession;
            if ($session) {
                $session->increment('total_voids', (float) $transaction->total_amount);
            }
        }

        return $transaction->fresh();
    }

    private function generateNumber(string $storeId): string
    {
        $date = now()->format('Ymd');
        $count = Transaction::where('store_id', $storeId)
            ->where('transaction_number', 'like', "TXN-{$date}-%")
            ->count();

        return sprintf('TXN-%s-%04d', $date, $count + 1);
    }
}
