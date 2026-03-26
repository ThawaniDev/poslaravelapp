<?php

namespace App\Domain\ProviderSubscription\Jobs;

use App\Domain\ProviderSubscription\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRenewalInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly int $daysBeforeExpiry = 3,
    ) {
        $this->queue = 'billing';
    }

    public function handle(BillingService $billing): void
    {
        $invoices = $billing->generateRenewalInvoices($this->daysBeforeExpiry);

        Log::info('GenerateRenewalInvoicesJob completed', [
            'invoices_generated' => count($invoices),
        ]);
    }
}
