<?php

namespace App\Console\Commands;

use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Models\FeatureAdoptionStat;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateDailyStats extends Command
{
    protected $signature = 'platform:aggregate-daily-stats {--date= : Date to aggregate (Y-m-d), defaults to yesterday}';

    protected $description = 'Aggregate daily platform statistics into platform_daily_stats, platform_plan_stats, feature_adoption_stats, and store_health_snapshots tables';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : now()->subDay()->toDateString();

        $this->info("Aggregating stats for {$date}...");

        $this->aggregateDailyStats($date);
        $this->aggregatePlanStats($date);
        $this->aggregateFeatureAdoptionStats($date);
        $this->aggregateStoreHealthSnapshots($date);

        $this->info("✓ All stats aggregated successfully for {$date}");

        return self::SUCCESS;
    }

    // ─── Platform Daily Stats ────────────────────────────────────

    private function aggregateDailyStats(string $date): void
    {
        $totalActiveStores = Store::where('is_active', true)->count();
        $newRegistrations  = Store::whereDate('created_at', $date)->count();

        // MRR = sum of monthly_price for all active subscriptions
        $totalMrr = (float) StoreSubscription::query()
            ->where('store_subscriptions.status', 'active')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'store_subscriptions.subscription_plan_id')
            ->sum('subscription_plans.monthly_price');

        $churnCount = StoreSubscription::where('status', 'cancelled')
            ->whereDate('cancelled_at', $date)
            ->count();

        // Total orders & GMV for the day from transactions table
        [$totalOrders, $totalGmv] = $this->countTransactionsForDate($date);

        PlatformDailyStat::updateOrCreate(
            ['date' => $date],
            [
                'total_active_stores' => $totalActiveStores,
                'new_registrations'   => $newRegistrations,
                'total_orders'        => $totalOrders,
                'total_gmv'           => $totalGmv,
                'total_mrr'           => $totalMrr,
                'churn_count'         => $churnCount,
            ]
        );

        $this->line("  → Daily stats: {$totalActiveStores} stores, MRR: {$totalMrr}, Orders: {$totalOrders}, GMV: {$totalGmv}");
    }

    private function countTransactionsForDate(string $date): array
    {
        // Guard: transactions table may not exist in SQLite tests
        if (!DB::getSchemaBuilder()->hasTable('transactions')) {
            return [0, 0.0];
        }

        $row = Transaction::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as gmv')
            ->first();

        return [(int) ($row->cnt ?? 0), (float) ($row->gmv ?? 0)];
    }

    // ─── Platform Plan Stats ─────────────────────────────────────

    private function aggregatePlanStats(string $date): void
    {
        if (!DB::getSchemaBuilder()->hasTable('subscription_plans')) {
            return;
        }

        $plans = DB::table('subscription_plans')->select('id', 'monthly_price')->get();

        foreach ($plans as $plan) {
            $activeCount = StoreSubscription::where('subscription_plan_id', $plan->id)
                ->where('status', 'active')
                ->count();

            $trialCount = StoreSubscription::where('subscription_plan_id', $plan->id)
                ->where('status', 'trial')
                ->count();

            $churnedCount = StoreSubscription::where('subscription_plan_id', $plan->id)
                ->where('status', 'cancelled')
                ->whereDate('cancelled_at', $date)
                ->count();

            $mrr = $activeCount * (float) $plan->monthly_price;

            PlatformPlanStat::updateOrCreate(
                ['subscription_plan_id' => $plan->id, 'date' => $date],
                [
                    'active_count'  => $activeCount,
                    'trial_count'   => $trialCount,
                    'churned_count' => $churnedCount,
                    'mrr'           => $mrr,
                ]
            );
        }

        $this->line('  → Plan stats aggregated for ' . $plans->count() . ' plans');
    }

    // ─── Feature Adoption Stats ──────────────────────────────────

    private function aggregateFeatureAdoptionStats(string $date): void
    {
        if (!DB::getSchemaBuilder()->hasTable('plan_feature_toggles')) {
            return;
        }

        // Collect all unique feature keys
        $featureKeys = DB::table('plan_feature_toggles')
            ->distinct()
            ->pluck('feature_key');

        $totalStores = Store::count();

        foreach ($featureKeys as $featureKey) {
            // Count stores whose active subscription plan has this feature enabled
            $storesUsingCount = StoreSubscription::query()
                ->where('store_subscriptions.status', 'active')
                ->join('plan_feature_toggles', function ($join) use ($featureKey) {
                    $join->on('plan_feature_toggles.subscription_plan_id', '=', 'store_subscriptions.subscription_plan_id')
                         ->where('plan_feature_toggles.feature_key', $featureKey)
                         ->where('plan_feature_toggles.is_enabled', true);
                })
                ->count();

            FeatureAdoptionStat::updateOrCreate(
                ['feature_key' => $featureKey, 'date' => $date],
                [
                    'stores_using_count' => $storesUsingCount,
                    'total_events'       => $totalStores, // eligible denominator
                ]
            );
        }

        $this->line('  → Feature adoption stats aggregated for ' . $featureKeys->count() . ' features');
    }

    // ─── Store Health Snapshots ──────────────────────────────────

    private function aggregateStoreHealthSnapshots(string $date): void
    {
        // Use a chunked approach for large store counts
        Store::where('is_active', true)->chunkById(200, function ($stores) use ($date) {
            foreach ($stores as $store) {
                $errorCount  = 0;
                $syncStatus  = 'ok';
                $zatcaCompliance = null;
                $lastActivityAt  = null;

                // Check delivery sync errors if table exists
                if (DB::getSchemaBuilder()->hasTable('store_delivery_platforms')) {
                    $deliveryError = DB::table('store_delivery_platforms')
                        ->where('store_id', $store->id)
                        ->whereNotNull('last_error')
                        ->exists();
                    if ($deliveryError) {
                        $syncStatus = 'error';
                        $errorCount++;
                    }
                }

                // Get last transaction date for activity tracking
                if (DB::getSchemaBuilder()->hasTable('transactions')) {
                    $lastTx = DB::table('transactions')
                        ->where('store_id', $store->id)
                        ->orderByDesc('created_at')
                        ->value('created_at');
                    $lastActivityAt = $lastTx;
                }

                StoreHealthSnapshot::updateOrCreate(
                    ['store_id' => $store->id, 'date' => $date],
                    [
                        'sync_status'     => $syncStatus,
                        'zatca_compliance' => $zatcaCompliance,
                        'error_count'     => $errorCount,
                        'last_activity_at' => $lastActivityAt,
                    ]
                );
            }
        });

        $count = Store::where('is_active', true)->count();
        $this->line("  → Store health snapshots written for {$count} stores");
    }
}
