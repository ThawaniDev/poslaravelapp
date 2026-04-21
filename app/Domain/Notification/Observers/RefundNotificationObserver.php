<?php

namespace App\Domain\Notification\Observers;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Models\Refund;
use Illuminate\Support\Facades\Log;

/**
 * Fires:
 *   - order.refund_requested when a refund row is created (status defaults to pending).
 *   - order.refund_approved  when a refund transitions to Completed.
 */
class RefundNotificationObserver
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function created(Refund $refund): void
    {
        try {
            $context = $this->context($refund);
            if (! $context) {
                return;
            }

            $this->dispatcher->toStoreOwner(
                storeId: $context['store_id'],
                eventKey: 'order.refund_requested',
                variables: [
                    'order_id' => $context['order_id'],
                    'amount' => $context['amount'],
                    'store_name' => $context['store_name'],
                ],
                category: 'order',
                referenceId: $refund->id,
                referenceType: 'refund',
            );
        } catch (\Throwable $e) {
            Log::error('RefundNotificationObserver::created failed', ['error' => $e->getMessage()]);
        }
    }

    public function updated(Refund $refund): void
    {
        if (! $refund->wasChanged('status')) {
            return;
        }

        if ($refund->status !== RefundStatus::Completed) {
            return;
        }

        try {
            $context = $this->context($refund);
            if (! $context) {
                return;
            }

            $this->dispatcher->toStoreOwner(
                storeId: $context['store_id'],
                eventKey: 'order.refund_approved',
                variables: [
                    'order_id' => $context['order_id'],
                    'amount' => $context['amount'],
                    'store_name' => $context['store_name'],
                ],
                category: 'order',
                referenceId: $refund->id,
                referenceType: 'refund',
            );
        } catch (\Throwable $e) {
            Log::error('RefundNotificationObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Resolve the store + order context for a refund, returning null when
     * the refund cannot be linked to a store (notifications are skipped).
     *
     * @return array{store_id:string,store_name:string,order_id:string,amount:string}|null
     */
    private function context(Refund $refund): ?array
    {
        $payment = $refund->payment_id ? \App\Domain\Payment\Models\Payment::find($refund->payment_id) : null;
        $transaction = $payment?->transaction;
        $store = $transaction?->store;
        $storeId = $transaction?->store_id;

        if (! $storeId) {
            return null;
        }

        $currency = $store?->currency ?? 'SAR';

        return [
            'store_id' => $storeId,
            'store_name' => $store?->name ?? '',
            'order_id' => $transaction?->transaction_number ?? $transaction?->id ?? $refund->id,
            'amount' => number_format((float) $refund->amount, 2) . ' ' . $currency,
        ];
    }
}
