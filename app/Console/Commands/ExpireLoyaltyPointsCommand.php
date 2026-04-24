<?php

namespace App\Console\Commands;

use App\Domain\Core\Models\Organization;
use App\Domain\Customer\Services\LoyaltyService;
use Illuminate\Console\Command;

/**
 * Spec Rule #4 — nightly cron: expire loyalty points older than the
 * configured `points_expiry_months` window for each organisation that
 * has the loyalty programme active.
 */
class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points {--organization= : Limit to a single organization id}';

    protected $description = 'Expire loyalty points older than each organisation\'s configured window.';

    public function handle(LoyaltyService $loyalty): int
    {
        $orgIds = $this->option('organization')
            ? [(string) $this->option('organization')]
            : Organization::query()->pluck('id')->all();

        $totalCustomers = 0;
        foreach ($orgIds as $orgId) {
            try {
                $expired = $loyalty->expireOldPoints((string) $orgId);
                $totalCustomers += $expired;
                if ($expired > 0) {
                    $this->info("Org {$orgId}: expired points for {$expired} customer(s).");
                }
            } catch (\Throwable $e) {
                $this->error("Org {$orgId}: failed - {$e->getMessage()}");
            }
        }

        $this->info("Done. Total customers affected: {$totalCustomers}.");
        return self::SUCCESS;
    }
}
