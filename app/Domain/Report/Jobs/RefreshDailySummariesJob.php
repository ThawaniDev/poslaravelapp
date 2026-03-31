<?php

namespace App\Domain\Report\Jobs;

use App\Domain\Report\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefreshDailySummariesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?string $date = null,
    ) {}

    public function handle(ReportService $reportService): void
    {
        $date = $this->date ?? now()->subDay()->toDateString();

        $storeIds = DB::table('stores')
            ->where('is_active', true)
            ->pluck('id');

        foreach ($storeIds as $storeId) {
            try {
                $reportService->refreshDailySummary($storeId, $date);
                $reportService->refreshProductSummary($storeId, $date);
            } catch (\Throwable $e) {
                Log::error("Failed to refresh summaries for store {$storeId}: {$e->getMessage()}");
            }
        }

        Log::info("Daily summaries refreshed for {$storeIds->count()} stores on date {$date}");
    }
}
