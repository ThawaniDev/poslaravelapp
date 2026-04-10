<?php

namespace App\Domain\WameedAI\Commands;

use App\Domain\WameedAI\Services\AIUsageTrackingService;
use Illuminate\Console\Command;

class AggregateMonthlyUsageCommand extends Command
{
    protected $signature = 'ai:aggregate-monthly {--month= : The month to aggregate (Y-m), defaults to last month}';
    protected $description = 'Aggregate AI usage logs into monthly summaries';

    public function handle(AIUsageTrackingService $service): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');
        $this->info("Aggregating AI monthly usage for {$month}...");

        $service->aggregateMonthly($month);

        $this->info('Monthly aggregation complete.');
        return self::SUCCESS;
    }
}
