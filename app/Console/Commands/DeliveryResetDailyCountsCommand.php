<?php

namespace App\Console\Commands;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Console\Command;

class DeliveryResetDailyCountsCommand extends Command
{
    protected $signature = 'delivery:reset-daily-counts';

    protected $description = 'Reset daily_order_count to zero on every delivery platform config.';

    public function handle(): int
    {
        $count = DeliveryPlatformConfig::query()->update(['daily_order_count' => 0]);
        $this->info("Reset daily order count on {$count} config(s).");

        return self::SUCCESS;
    }
}
