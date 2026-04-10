<?php

namespace App\Domain\WameedAI\Commands;

use App\Domain\WameedAI\Services\AIUsageTrackingService;
use Illuminate\Console\Command;

class AggregateDailyUsageCommand extends Command
{
    protected $signature = 'ai:aggregate-daily {--date= : The date to aggregate (Y-m-d), defaults to yesterday}';
    protected $description = 'Aggregate AI usage logs into daily summaries';

    public function handle(AIUsageTrackingService $service): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();
        $this->info("Aggregating AI daily usage for {$date}...");

        $service->aggregateDaily($date);

        $this->info('Daily aggregation complete.');
        return self::SUCCESS;
    }
}
