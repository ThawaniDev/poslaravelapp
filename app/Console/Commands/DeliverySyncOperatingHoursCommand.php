<?php

namespace App\Console\Commands;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Services\OperatingHoursSyncService;
use Illuminate\Console\Command;

class DeliverySyncOperatingHoursCommand extends Command
{
    protected $signature = 'delivery:sync-operating-hours';

    protected $description = 'Push configured operating hours for every enabled delivery platform config';

    public function handle(OperatingHoursSyncService $service): int
    {
        $count = 0;
        $failed = 0;

        DeliveryPlatformConfig::query()
            ->where('is_enabled', true)
            ->whereNotNull('operating_hours_json')
            ->cursor()
            ->each(function (DeliveryPlatformConfig $cfg) use ($service, &$count, &$failed) {
                $r = $service->syncForConfig($cfg);
                if ($r['success'] ?? false) {
                    $count++;
                } else {
                    $failed++;
                }
            });

        $this->info("Operating hours sync complete: {$count} ok, {$failed} failed");

        return self::SUCCESS;
    }
}
