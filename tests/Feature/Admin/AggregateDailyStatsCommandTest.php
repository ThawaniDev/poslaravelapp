<?php

namespace Tests\Feature\Admin;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Analytics\Models\PlatformDailyStat;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateDailyStatsCommandTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlan $plan;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = SubscriptionPlan::forceCreate([
            'name' => 'Test Plan',
            'name_ar' => 'خطة اختبار',
            'slug' => 'test_aggregate',
            'monthly_price' => 50.00,
            'annual_price' => 500.00,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'is_highlighted' => false,
            'sort_order' => 1,
        ]);

        $this->org = Organization::forceCreate([
            'name' => 'Aggregate Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // BASIC EXECUTION
    // ═══════════════════════════════════════════════════════════

    public function test_command_runs_successfully(): void
    {
        $this->artisan('platform:aggregate-daily-stats')
            ->assertSuccessful();
    }

    public function test_creates_daily_stat_record(): void
    {
        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertNotNull($stat);
        $this->assertEquals(now()->subDay()->toDateString(), $stat->date->toDateString());
    }

    public function test_command_with_specific_date(): void
    {
        $date = '2025-01-15';

        $this->artisan("platform:aggregate-daily-stats --date={$date}")
            ->assertSuccessful();

        $stat = PlatformDailyStat::first();
        $this->assertNotNull($stat);
        $this->assertEquals($date, $stat->date->toDateString());
    }

    // ═══════════════════════════════════════════════════════════
    // STORE COUNTS
    // ═══════════════════════════════════════════════════════════

    public function test_counts_active_stores(): void
    {
        Store::forceCreate(['name' => 'Active 1', 'organization_id' => $this->org->id, 'is_active' => true]);
        Store::forceCreate(['name' => 'Active 2', 'organization_id' => $this->org->id, 'is_active' => true]);
        Store::forceCreate(['name' => 'Inactive', 'organization_id' => $this->org->id, 'is_active' => false]);

        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertEquals(2, $stat->total_active_stores);
    }

    public function test_counts_new_registrations_for_date(): void
    {
        Store::forceCreate([
            'name' => 'New Store',
            'organization_id' => $this->org->id,
            'is_active' => true,
            'created_at' => now()->subDay()->startOfDay()->addHours(10),
        ]);

        // Store created today (not yesterday) — should not count
        Store::forceCreate([
            'name' => 'Today Store',
            'organization_id' => $this->org->id,
            'is_active' => true,
            'created_at' => now(),
        ]);

        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->new_registrations);
    }

    // ═══════════════════════════════════════════════════════════
    // CHURN COUNT
    // ═══════════════════════════════════════════════════════════

    public function test_counts_churn_for_date(): void
    {
        StoreSubscription::forceCreate([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'cancelled_at' => now()->subDay()->startOfDay()->addHours(14),
        ]);

        // Cancelled today — should not count for yesterday
        $org2 = Organization::forceCreate(['name' => 'Org 2', 'business_type' => 'grocery', 'country' => 'OM']);
        StoreSubscription::forceCreate([
            'organization_id' => $org2->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'cancelled_at' => now(),
        ]);

        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->churn_count);
    }

    public function test_zero_churn_when_no_cancellations(): void
    {
        StoreSubscription::forceCreate([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertEquals(0, $stat->churn_count);
    }

    // ═══════════════════════════════════════════════════════════
    // IDEMPOTENCY
    // ═══════════════════════════════════════════════════════════

    public function test_update_or_create_is_idempotent(): void
    {
        Store::forceCreate(['name' => 'Store A', 'organization_id' => $this->org->id, 'is_active' => true]);

        // Run twice
        $this->artisan('platform:aggregate-daily-stats');
        $this->artisan('platform:aggregate-daily-stats');

        $count = PlatformDailyStat::count();
        $this->assertEquals(1, $count);
    }

    public function test_updates_values_on_rerun(): void
    {
        Store::forceCreate(['name' => 'Store A', 'organization_id' => $this->org->id, 'is_active' => true]);

        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertEquals(1, $stat->total_active_stores);

        // Add another store re-run
        Store::forceCreate(['name' => 'Store B', 'organization_id' => $this->org->id, 'is_active' => true]);
        $this->artisan('platform:aggregate-daily-stats');

        $stat->refresh();
        $this->assertEquals(2, $stat->total_active_stores);
    }

    // ═══════════════════════════════════════════════════════════
    // EDGE CASES
    // ═══════════════════════════════════════════════════════════

    public function test_handles_empty_database_gracefully(): void
    {
        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertNotNull($stat);
        $this->assertEquals(0, $stat->total_active_stores);
        $this->assertEquals(0, $stat->new_registrations);
        $this->assertEquals(0, $stat->churn_count);
    }

    public function test_sets_total_orders_and_gmv_to_zero(): void
    {
        $this->artisan('platform:aggregate-daily-stats');

        $stat = PlatformDailyStat::first();
        $this->assertEquals(0, $stat->total_orders);
        $this->assertEquals(0.00, (float) $stat->total_gmv);
    }

    public function test_output_contains_date_information(): void
    {
        $yesterday = now()->subDay()->toDateString();

        $this->artisan('platform:aggregate-daily-stats')
            ->expectsOutputToContain($yesterday)
            ->assertSuccessful();
    }

    public function test_custom_date_overrides_default(): void
    {
        $customDate = '2025-06-15';

        $this->artisan("platform:aggregate-daily-stats --date={$customDate}")
            ->assertSuccessful();

        $stat = PlatformDailyStat::first();
        $this->assertNotNull($stat);
        $this->assertEquals($customDate, $stat->date->toDateString());

        // Only one record, no yesterday record
        $this->assertEquals(1, PlatformDailyStat::count());
    }
}
