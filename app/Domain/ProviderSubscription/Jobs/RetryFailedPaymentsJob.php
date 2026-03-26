<?php

namespace App\Domain\ProviderSubscription\Jobs;

use App\Domain\ProviderSubscription\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->queue = 'billing';
    }

    public function handle(BillingService $billing): void
    {
        $results = $billing->retryFailedPayments();

        Log::info('RetryFailedPaymentsJob completed', [
            'retries_attempted' => count($results),
        ]);
    }
}
