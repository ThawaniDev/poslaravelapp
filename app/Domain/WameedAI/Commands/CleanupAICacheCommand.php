<?php

namespace App\Domain\WameedAI\Commands;

use App\Domain\WameedAI\Services\AIUsageTrackingService;
use Illuminate\Console\Command;

class CleanupAICacheCommand extends Command
{
    protected $signature = 'ai:cleanup-cache';
    protected $description = 'Remove expired AI cache entries';

    public function handle(AIUsageTrackingService $service): int
    {
        $this->info('Cleaning up expired AI cache entries...');

        $deleted = $service->cleanupExpiredCache();

        $this->info("Deleted {$deleted} expired cache entries.");
        return self::SUCCESS;
    }
}
