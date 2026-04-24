<?php

namespace App\Console\Commands;

use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use App\Domain\DeliveryIntegration\Models\DeliveryStatusPushLog;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use Illuminate\Console\Command;

class DeliveryCleanupLogsCommand extends Command
{
    protected $signature = 'delivery:cleanup-logs {--days=90 : Retention period in days}';

    protected $description = 'Delete delivery webhook / sync / status-push logs older than the retention window.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $webhook = DeliveryWebhookLog::where('received_at', '<', $cutoff)->delete();
        $push = DeliveryStatusPushLog::where('pushed_at', '<', $cutoff)->delete();
        $sync = DeliveryMenuSyncLog::where('started_at', '<', $cutoff)->delete();

        $this->info("Deleted webhook={$webhook}, push={$push}, sync={$sync} log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
