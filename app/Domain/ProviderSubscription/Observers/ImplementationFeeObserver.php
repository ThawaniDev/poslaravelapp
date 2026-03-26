<?php

namespace App\Domain\ProviderSubscription\Observers;

use App\Domain\Billing\Models\ImplementationFee;
use App\Domain\ProviderSubscription\Services\BillingService;
use Illuminate\Support\Facades\Log;

class ImplementationFeeObserver
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function created(ImplementationFee $fee): void
    {
        // Don't override manually-set terminal statuses
        if ($fee->status === 'paid') {
            return;
        }

        try {
            $invoice = $this->billing->generateImplementationFeeInvoice($fee);

            if ($invoice) {
                $fee->update(['status' => 'invoiced']);

                Log::info('Auto-generated invoice for implementation fee', [
                    'fee_id' => $fee->id,
                    'invoice_id' => $invoice->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to auto-generate implementation fee invoice', [
                'fee_id' => $fee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
