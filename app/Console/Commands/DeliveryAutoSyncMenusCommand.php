<?php

namespace App\Console\Commands;

use App\Domain\DeliveryIntegration\Jobs\MenuSyncJob;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Console\Command;

class DeliveryAutoSyncMenusCommand extends Command
{
    protected $signature = 'delivery:auto-sync-menus';

    protected $description = 'Dispatch MenuSyncJob for delivery configs whose interval has elapsed.';

    public function handle(): int
    {
        $count = 0;
        DeliveryPlatformConfig::where('is_enabled', true)
            ->where('sync_menu_on_product_change', true)
            ->each(function (DeliveryPlatformConfig $cfg) use (&$count) {
                $intervalHours = $cfg->menu_sync_interval_hours ?: 6;
                $lastSync = $cfg->last_menu_sync_at;

                if ($lastSync === null || $lastSync->lt(now()->subHours($intervalHours))) {
                    MenuSyncJob::dispatch($cfg->id, []);
                    $count++;
                }
            });

        $this->info("Dispatched {$count} menu sync job(s).");

        return self::SUCCESS;
    }
}
