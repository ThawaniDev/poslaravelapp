<?php

namespace App\Domain\ProviderSubscription\Jobs;

use App\Domain\ProviderSubscription\Services\SoftPosService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetSoftPosCountersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SoftPosService $softPosService): void
    {
        $count = $softPosService->resetPeriodCounters();

        Log::info("[SoftPOS] Monthly counter reset completed. Subscriptions reset: {$count}");
    }
}
