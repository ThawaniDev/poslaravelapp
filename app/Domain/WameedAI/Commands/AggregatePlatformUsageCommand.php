<?php

namespace App\Domain\WameedAI\Commands;

use App\Domain\WameedAI\Services\AIUsageTrackingService;
use Illuminate\Console\Command;

class AggregatePlatformUsageCommand extends Command
{
    protected $signature = 'ai:aggregate-platform {--date= : The date to aggregate (Y-m-d), defaults to yesterday}';
    protected $description = 'Aggregate AI usage into platform-wide daily summaries';

    public function handle(AIUsageTrackingService $service): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();
        $this->info("Aggregating AI platform usage for {$date}...");

        $service->aggregatePlatformDaily($date);

        $this->info('Platform aggregation complete.');
        return self::SUCCESS;
    }
}
