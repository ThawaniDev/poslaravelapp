<?php

namespace App\Domain\ProviderSubscription\Observers;

use App\Domain\Billing\Models\HardwareSale;
use App\Domain\ProviderSubscription\Services\BillingService;
use Illuminate\Support\Facades\Log;

class HardwareSaleObserver
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function created(HardwareSale $sale): void
    {
        try {
            $invoice = $this->billing->generateHardwareSaleInvoice($sale);

            if ($invoice) {
                Log::info('Auto-generated invoice for hardware sale', [
                    'sale_id' => $sale->id,
                    'invoice_id' => $invoice->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to auto-generate hardware sale invoice', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
