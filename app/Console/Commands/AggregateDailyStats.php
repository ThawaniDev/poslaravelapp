<?php

namespace App\Console\Commands;

use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Illuminate\Console\Command;

class AggregateDailyStats extends Command
{
    protected $signature = 'platform:aggregate-daily-stats {--date= : Date to aggregate (Y-m-d), defaults to yesterday}';

    protected $description = 'Aggregate daily platform statistics into platform_daily_stats table';

    public function handle(): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))->toDateString()
            : now()->subDay()->toDateString();

        $this->info("Aggregating stats for {$date}...");

        $totalActiveStores = Store::where('is_active', true)->count();
        $newRegistrations = Store::whereDate('created_at', $date)->count();

        $activeSubscriptions = StoreSubscription::where('status', 'active');
        $totalMrr = (float) $activeSubscriptions->sum('monthly_price');

        $churnCount = StoreSubscription::where('status', 'cancelled')
            ->whereDate('cancelled_at', $date)
            ->count();

        PlatformDailyStat::updateOrCreate(
            ['date' => $date],
            [
                'total_active_stores' => $totalActiveStores,
                'new_registrations' => $newRegistrations,
                'total_orders' => 0,
                'total_gmv' => 0,
                'total_mrr' => $totalMrr,
                'churn_count' => $churnCount,
            ]
        );

        $this->info("Stats aggregated for {$date}: {$totalActiveStores} active stores, MRR: {$totalMrr}");

        return self::SUCCESS;
    }
}
