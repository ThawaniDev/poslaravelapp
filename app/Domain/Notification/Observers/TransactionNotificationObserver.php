<?php

namespace App\Domain\Notification\Observers;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * Fires:
 *   - finance.large_transaction  when a transaction exceeds a configurable threshold (default SAR 5,000).
 *   - order.payment_failed       when a transaction is marked Voided after being Pending (heuristic for failed payments).
 *   - staff.void_transaction     when a transaction transitions to Voided.
 */
class TransactionNotificationObserver
{
    private const LARGE_TRANSACTION_THRESHOLD = 5000.00;

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function created(Transaction $transaction): void
    {
        try {
            if ($transaction->status !== TransactionStatus::Completed) {
                return;
            }

            $this->maybeFireLargeTransaction($transaction);
        } catch (\Throwable $e) {
            Log::error('TransactionNotificationObserver::created failed', ['error' => $e->getMessage()]);
        }
    }

    public function updated(Transaction $transaction): void
    {
        try {
            if ($transaction->wasChanged('status')) {
                $this->handleStatusChange($transaction);
            }
        } catch (\Throwable $e) {
            Log::error('TransactionNotificationObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }

    private function handleStatusChange(Transaction $transaction): void
    {
        $oldStatus = $transaction->getOriginal('status');
        $newStatus = $transaction->status;

        // Just completed → check large transaction threshold
        if ($newStatus === TransactionStatus::Completed) {
            $this->maybeFireLargeTransaction($transaction);
            return;
        }

        // Voided → fire void notification + (if was pending) payment failed
        if ($newStatus === TransactionStatus::Voided) {
            $this->fireVoid($transaction);

            if ($oldStatus === TransactionStatus::Pending || $oldStatus === 'pending') {
                $this->firePaymentFailed($transaction);
            }
        }
    }

    private function maybeFireLargeTransaction(Transaction $transaction): void
    {
        $amount = (float) $transaction->total_amount;
        if ($amount < self::LARGE_TRANSACTION_THRESHOLD) {
            return;
        }

        $store = $transaction->store;
        $cashier = $transaction->cashier;
        $currency = $store?->currency ?? 'SAR';

        $this->dispatcher->toStoreOwner(
            storeId: $transaction->store_id,
            eventKey: 'finance.large_transaction',
            variables: [
                'transaction_id' => $transaction->transaction_number ?? $transaction->id,
                'amount' => number_format($amount, 2) . ' ' . $currency,
                'cashier_name' => $cashier?->name ?? '—',
                'store_name' => $store?->name ?? '',
            ],
            category: 'finance',
            referenceId: $transaction->id,
            referenceType: 'transaction',
        );
    }

    private function firePaymentFailed(Transaction $transaction): void
    {
        $store = $transaction->store;
        $currency = $store?->currency ?? 'SAR';

        $this->dispatcher->toStoreOwner(
            storeId: $transaction->store_id,
            eventKey: 'order.payment_failed',
            variables: [
                'order_id' => $transaction->transaction_number ?? $transaction->id,
                'total' => number_format((float) $transaction->total_amount, 2) . ' ' . $currency,
                'store_name' => $store?->name ?? '',
            ],
            category: 'payment',
            referenceId: $transaction->id,
            referenceType: 'transaction',
            priority: 'high',
        );
    }

    private function fireVoid(Transaction $transaction): void
    {
        $store = $transaction->store;
        $cashier = $transaction->cashier;
        $currency = $store?->currency ?? 'SAR';

        $this->dispatcher->toStoreOwner(
            storeId: $transaction->store_id,
            eventKey: 'staff.void_transaction',
            variables: [
                'user_name' => $cashier?->name ?? '—',
                'transaction_id' => $transaction->transaction_number ?? $transaction->id,
                'amount' => number_format((float) $transaction->total_amount, 2) . ' ' . $currency,
                'store_name' => $store?->name ?? '',
            ],
            category: 'staff',
            referenceId: $transaction->id,
            referenceType: 'transaction',
        );
    }
}
