<?php

namespace App\Domain\Notification\Observers;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\PosTerminal\Models\PosSession;
use Illuminate\Support\Facades\Log;

class PosSessionNotificationObserver
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function updated(PosSession $session): void
    {
        if (! $session->wasChanged('status')) {
            return;
        }

        try {
            $newStatus = $session->status;

            // Only notify on session close (status is CashSessionStatus enum)
            if ($newStatus !== CashSessionStatus::Closed) {
                return;
            }

            $store = $session->store;
            $cashier = $session->cashier;
            $cashierName = $cashier?->name ?? 'Unknown';
            $storeName = $store?->name ?? '';

            $openingCash = (float) ($session->opening_cash ?? 0);
            $closingCash = (float) ($session->closing_cash ?? 0);
            $difference = abs($closingCash - $openingCash);

            // Shift closed notification
            $this->dispatcher->toStoreOwner(
                storeId: $session->store_id,
                eventKey: 'finance.shift_closed',
                variables: [
                    'cashier_name' => $cashierName,
                    'total_sales' => number_format((float) ($session->total_sales ?? 0), 2) . ' OMR',
                    'cash_expected' => number_format($openingCash, 2) . ' OMR',
                    'cash_actual' => number_format($closingCash, 2) . ' OMR',
                    'store_name' => $storeName,
                ],
                category: 'finance',
                referenceId: $session->id,
                referenceType: 'pos_session',
            );

            // Cash discrepancy notification (if difference exceeds threshold)
            $expectedCash = (float) ($session->expected_cash ?? $openingCash);
            $actualCash = $closingCash;
            $discrepancy = abs($actualCash - $expectedCash);

            if ($discrepancy > 1) { // More than 1 OMR discrepancy
                $this->dispatcher->toStoreOwner(
                    storeId: $session->store_id,
                    eventKey: 'finance.cash_discrepancy',
                    variables: [
                        'cashier_name' => $cashierName,
                        'expected' => number_format($expectedCash, 2) . ' OMR',
                        'actual' => number_format($actualCash, 2) . ' OMR',
                        'difference' => number_format($discrepancy, 2) . ' OMR',
                        'store_name' => $storeName,
                    ],
                    category: 'finance',
                    referenceId: $session->id,
                    referenceType: 'pos_session',
                    priority: 'high',
                );
            }
        } catch (\Throwable $e) {
            Log::error('PosSessionNotificationObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }
}
