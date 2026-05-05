<?php

namespace Tests\Unit\Analytics;

use App\Console\Commands\AggregateDailyStats;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Models\FeatureAdoptionStat;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for the platform:aggregate-daily-stats console command.
 *
 * Each test verifies a single aggregation behaviour — the command is called
 * via artisan() so all four aggregators run against real in-memory data.
 */
class AggregateDailyStatsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::now()->subDay()->toDateString();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function makeOrg(): Organization
    {
        return Organization::forceCreate([
            'name'          => 'Org ' . uniqid(),
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);
    }

    private function makeStore(bool $active = true): Store
    {
        return Store::forceCreate([
            'name'            => 'Store ' . uniqid(),
            'organization_id' => $this->makeOrg()->id,
            'is_active'       => $active,
        ]);
    }

    private function makePlan(float $monthlyPrice = 100.0): SubscriptionPlan
    {
        return SubscriptionPlan::forceCreate([
            'name'             => 'Plan ' . uniqid(),
            'slug'             => 'plan-' . uniqid(),
            'monthly_price'    => $monthlyPrice,
            'annual_price'     => $monthlyPrice * 10,
            'trial_days'       => 14,
            'grace_period_days'=> 7,
            'is_active'        => true,
        ]);
    }

    private function makeSubscription(Store $store, SubscriptionPlan $plan, string $status = 'active', ?string $cancelledAt = null): StoreSubscription
    {
        $data = [
            'organization_id'        => $store->organization_id,
            'subscription_plan_id'   => $plan->id,
            'status'                 => $status,
            'current_period_start'   => now()->subMonth()->toDateTimeString(),
            'current_period_end'     => now()->addMonth()->toDateTimeString(),
        ];

        if ($cancelledAt) {
            $data['cancelled_at'] = $cancelledAt;
        }

        return StoreSubscription::forceCreate($data);
    }

    // ═══════════════════════════════════════════════════════════
    // COMMAND: DEFAULTS & SIGNATURE
    // ═══════════════════════════════════════════════════════════

    public function test_command_exits_successfully(): void
    {
        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date])
            ->assertExitCode(0);
    }

    public function test_command_defaults_to_yesterday_when_no_date_given(): void
    {
        $this->artisan('platform:aggregate-daily-stats')
            ->assertExitCode(0);

        $yesterday = now()->subDay()->toDateString();
        $this->assertDatabaseHas('platform_daily_stats', ['date' => $yesterday]);
    }

    public function test_command_accepts_explicit_date(): void
    {
        $targetDate = now()->subDays(5)->toDateString();

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $targetDate])
            ->assertExitCode(0);

        $this->assertDatabaseHas('platform_daily_stats', ['date' => $targetDate]);
    }

    // ═══════════════════════════════════════════════════════════
    // AGGREGATOR: platform_daily_stats
    // ═══════════════════════════════════════════════════════════

    public function test_aggregates_total_active_store_count(): void
    {
        $this->makeStore(true);
        $this->makeStore(true);
        $this->makeStore(false); // inactive, should NOT count

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $row = PlatformDailyStat::where('date', $this->date)->first();
        $this->assertNotNull($row);
        $this->assertEquals(2, $row->total_active_stores);
    }

    public function test_aggregates_new_registrations_for_the_date(): void
    {
        $org = $this->makeOrg();
        // 2 stores registered on the target date
        Store::forceCreate(['name' => 'New1', 'organization_id' => $org->id, 'is_active' => true, 'created_at' => $this->date . ' 09:00:00']);
        Store::forceCreate(['name' => 'New2', 'organization_id' => $org->id, 'is_active' => true, 'created_at' => $this->date . ' 15:30:00']);
        // 1 store registered on a different day
        Store::forceCreate(['name' => 'Old', 'organization_id' => $org->id, 'is_active' => true, 'created_at' => now()->subDays(3)->toDateTimeString()]);

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $row = PlatformDailyStat::where('date', $this->date)->first();
        $this->assertEquals(2, $row->new_registrations);
    }

    public function test_aggregates_mrr_from_active_subscriptions(): void
    {
        $store1 = $this->makeStore();
        $store2 = $this->makeStore();
        $store3 = $this->makeStore();

        $plan100 = $this->makePlan(100.0);
        $plan200 = $this->makePlan(200.0);

        // 2 active subscriptions at 100 + 1 at 200 = MRR 400
        $this->makeSubscription($store1, $plan100, 'active');
        $this->makeSubscription($store2, $plan100, 'active');
        $this->makeSubscription($store3, $plan200, 'active');

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $row = PlatformDailyStat::where('date', $this->date)->first();
        $this->assertEquals(400.0, (float) $row->total_mrr);
    }

    public function test_cancelled_subscriptions_are_excluded_from_mrr(): void
    {
        $store1 = $this->makeStore();
        $store2 = $this->makeStore();
        $plan   = $this->makePlan(150.0);

        $this->makeSubscription($store1, $plan, 'active');
        $this->makeSubscription($store2, $plan, 'cancelled', $this->date . ' 12:00:00');

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $row = PlatformDailyStat::where('date', $this->date)->first();
        $this->assertEquals(150.0, (float) $row->total_mrr);
    }

    public function test_aggregates_churn_count_for_the_date(): void
    {
        $store1 = $this->makeStore();
        $store2 = $this->makeStore();
        $store3 = $this->makeStore();
        $plan   = $this->makePlan(100.0);

        // 2 cancelled on target date
        $this->makeSubscription($store1, $plan, 'cancelled', $this->date . ' 10:00:00');
        $this->makeSubscription($store2, $plan, 'cancelled', $this->date . ' 14:00:00');
        // 1 cancelled on a different day
        $this->makeSubscription($store3, $plan, 'cancelled', now()->subDays(3)->toDateTimeString());

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $row = PlatformDailyStat::where('date', $this->date)->first();
        $this->assertEquals(2, $row->churn_count);
    }

    public function test_idempotent_upsert_does_not_create_duplicate(): void
    {
        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);
        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $count = PlatformDailyStat::where('date', $this->date)->count();
        $this->assertEquals(1, $count, 'Running the command twice should not create duplicates.');
    }

    // ═══════════════════════════════════════════════════════════
    // AGGREGATOR: platform_plan_stats
    // ═══════════════════════════════════════════════════════════

    public function test_aggregates_plan_stats_per_plan(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('subscription_plans')) {
            $this->markTestSkipped('subscription_plans table not present.');
        }

        $store1 = $this->makeStore();
        $store2 = $this->makeStore();
        $store3 = $this->makeStore();
        $plan   = $this->makePlan(50.0);

        $this->makeSubscription($store1, $plan, 'active');
        $this->makeSubscription($store2, $plan, 'trial');
        $this->makeSubscription($store3, $plan, 'cancelled', $this->date . ' 10:00:00');

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $stat = PlatformPlanStat::where('subscription_plan_id', $plan->id)
            ->where('date', $this->date)
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->active_count);
        $this->assertEquals(1, $stat->trial_count);
        $this->assertEquals(1, $stat->churned_count);
    }

    public function test_plan_stats_mrr_equals_active_count_times_monthly_price(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('subscription_plans')) {
            $this->markTestSkipped('subscription_plans table not present.');
        }

        $store1 = $this->makeStore();
        $store2 = $this->makeStore();
        $plan   = $this->makePlan(75.0);

        $this->makeSubscription($store1, $plan, 'active');
        $this->makeSubscription($store2, $plan, 'active');

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $stat = PlatformPlanStat::where('subscription_plan_id', $plan->id)
            ->where('date', $this->date)
            ->first();

        $this->assertEquals(150.0, (float) $stat->mrr, 'MRR = 2 active * 75 = 150');
    }

    // ═══════════════════════════════════════════════════════════
    // AGGREGATOR: store_health_snapshots
    // ═══════════════════════════════════════════════════════════

    public function test_creates_health_snapshot_for_each_active_store(): void
    {
        $this->makeStore(true);
        $this->makeStore(true);
        $this->makeStore(false); // inactive, should be skipped

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $count = StoreHealthSnapshot::where('date', $this->date)->count();
        $this->assertEquals(2, $count, 'Only active stores should have snapshots.');
    }

    public function test_store_health_snapshot_default_sync_status_is_ok(): void
    {
        $store = $this->makeStore(true);

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $snapshot = StoreHealthSnapshot::where('store_id', $store->id)
            ->where('date', $this->date)
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertEquals('ok', $snapshot->sync_status->value ?? $snapshot->sync_status);
        $this->assertEquals(0, $snapshot->error_count);
    }

    // ═══════════════════════════════════════════════════════════
    // AGGREGATOR: feature_adoption_stats
    // ═══════════════════════════════════════════════════════════

    public function test_aggregates_feature_adoption_stats_when_table_exists(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('plan_feature_toggles')) {
            $this->markTestSkipped('plan_feature_toggles table not present.');
        }

        $store = $this->makeStore();
        $plan  = $this->makePlan();
        $this->makeSubscription($store, $plan, 'active');

        DB::table('plan_feature_toggles')->insert([
            'id'                   => \Illuminate\Support\Str::uuid(),
            'subscription_plan_id' => $plan->id,
            'feature_key'          => 'test_feature_x',
            'is_enabled'           => true,
        ]);

        $this->artisan('platform:aggregate-daily-stats', ['--date' => $this->date]);

        $stat = FeatureAdoptionStat::where('feature_key', 'test_feature_x')
            ->where('date', $this->date)
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->stores_using_count);
    }
}
