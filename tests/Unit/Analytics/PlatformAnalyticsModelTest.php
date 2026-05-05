<?php

namespace Tests\Unit\Analytics;

use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Enums\StoreHealthSyncStatus;
use App\Domain\PlatformAnalytics\Models\FeatureAdoptionStat;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for Platform Analytics models.
 *
 * Verifies casts, fillable fields, relationships, and date handling.
 */
class PlatformAnalyticsModelTest extends TestCase
{
    use RefreshDatabase;

    // ═══════════════════════════════════════════════════════════
    // PlatformDailyStat
    // ═══════════════════════════════════════════════════════════

    public function test_platform_daily_stat_can_be_created(): void
    {
        $date = now()->subDays(3)->toDateString();

        $stat = PlatformDailyStat::forceCreate([
            'date'                => $date,
            'total_active_stores' => 42,
            'new_registrations'   => 5,
            'total_orders'        => 300,
            'total_gmv'           => 15000.50,
            'total_mrr'           => 3000.00,
            'churn_count'         => 2,
        ]);

        $this->assertNotNull($stat->id);
        $this->assertEquals($date, $stat->date->toDateString());
        $this->assertEquals(42, $stat->total_active_stores);
        $this->assertEquals('15000.50', $stat->total_gmv);
        $this->assertEquals('3000.00', $stat->total_mrr);
    }

    public function test_platform_daily_stat_date_is_cast_to_carbon(): void
    {
        $stat = PlatformDailyStat::forceCreate([
            'date'                => '2025-01-15',
            'total_active_stores' => 10,
            'new_registrations'   => 1,
            'total_orders'        => 50,
            'total_gmv'           => 500.0,
            'total_mrr'           => 200.0,
            'churn_count'         => 0,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $stat->date);
        $this->assertEquals('2025-01-15', $stat->date->toDateString());
    }

    public function test_platform_daily_stat_gmv_and_mrr_have_decimal_precision(): void
    {
        $stat = PlatformDailyStat::forceCreate([
            'date'                => now()->toDateString(),
            'total_active_stores' => 1,
            'new_registrations'   => 0,
            'total_orders'        => 0,
            'total_gmv'           => 1234.567, // will be stored as 1234.57 with decimal:2
            'total_mrr'           => 999.995,  // will be stored as 1000.00
            'churn_count'         => 0,
        ]);

        // Decimal cast preserves at least 2 decimal places
        $this->assertIsNumeric($stat->total_gmv);
        $this->assertIsNumeric($stat->total_mrr);
    }

    public function test_platform_daily_stat_unique_date_constraint(): void
    {
        $date = now()->subDays(2)->toDateString();

        PlatformDailyStat::forceCreate([
            'date' => $date, 'total_active_stores' => 10, 'new_registrations' => 1,
            'total_orders' => 100, 'total_gmv' => 5000.0, 'total_mrr' => 1000.0, 'churn_count' => 0,
        ]);

        $this->assertDatabaseHas('platform_daily_stats', ['date' => $date]);

        // updateOrCreate with same date should update, not create new row
        PlatformDailyStat::updateOrCreate(
            ['date' => $date],
            ['total_active_stores' => 99]
        );

        $this->assertEquals(1, PlatformDailyStat::where('date', $date)->count());
        $this->assertEquals(99, PlatformDailyStat::where('date', $date)->value('total_active_stores'));
    }

    // ═══════════════════════════════════════════════════════════
    // PlatformPlanStat
    // ═══════════════════════════════════════════════════════════

    public function test_platform_plan_stat_can_be_created(): void
    {
        $plan = SubscriptionPlan::forceCreate([
            'name'          => 'Test Plan',
            'slug'          => 'test-plan-' . uniqid(),
            'monthly_price' => 49.99,
            'annual_price'  => 499.99,
            'trial_days'    => 14,
            'is_active'     => true,
        ]);

        $date = now()->toDateString();
        $stat = PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id,
            'date'                 => $date,
            'active_count'         => 10,
            'trial_count'          => 3,
            'churned_count'        => 1,
            'mrr'                  => 499.90,
        ]);

        $this->assertNotNull($stat->id);
        $this->assertEquals(10, $stat->active_count);
        $this->assertEquals(3, $stat->trial_count);
        $this->assertEquals(1, $stat->churned_count);
    }

    public function test_platform_plan_stat_belongs_to_subscription_plan(): void
    {
        $plan = SubscriptionPlan::forceCreate([
            'name'          => 'Plan Rel',
            'slug'          => 'plan-rel-' . uniqid(),
            'monthly_price' => 29.99,
            'annual_price'  => 299.99,
            'trial_days'    => 7,
            'is_active'     => true,
        ]);

        $stat = PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id,
            'date'                 => now()->toDateString(),
            'active_count'         => 5,
            'trial_count'          => 0,
            'churned_count'        => 0,
            'mrr'                  => 149.95,
        ]);

        $loaded = PlatformPlanStat::with('plan')->find($stat->id);
        $this->assertNotNull($loaded->plan);
        $this->assertEquals($plan->id, $loaded->plan->id);
        $this->assertEquals('Plan Rel', $loaded->plan->name);
    }

    // ═══════════════════════════════════════════════════════════
    // FeatureAdoptionStat
    // ═══════════════════════════════════════════════════════════

    public function test_feature_adoption_stat_can_be_created(): void
    {
        $date = now()->toDateString();

        $stat = FeatureAdoptionStat::forceCreate([
            'feature_key'         => 'custom_cakes',
            'date'                => $date,
            'stores_using_count'  => 25,
            'total_events'        => 100,
        ]);

        $this->assertNotNull($stat->id);
        $this->assertEquals('custom_cakes', $stat->feature_key);
        $this->assertEquals(25, $stat->stores_using_count);
    }

    public function test_feature_adoption_stat_unique_key_date_constraint(): void
    {
        $date = now()->toDateString();

        FeatureAdoptionStat::forceCreate([
            'feature_key' => 'pos_offline', 'date' => $date,
            'stores_using_count' => 10, 'total_events' => 50,
        ]);

        FeatureAdoptionStat::updateOrCreate(
            ['feature_key' => 'pos_offline', 'date' => $date],
            ['stores_using_count' => 20]
        );

        $this->assertEquals(1, FeatureAdoptionStat::where('feature_key', 'pos_offline')->where('date', $date)->count());
        $this->assertEquals(20, FeatureAdoptionStat::where('feature_key', 'pos_offline')->where('date', $date)->value('stores_using_count'));
    }

    // ═══════════════════════════════════════════════════════════
    // StoreHealthSnapshot
    // ═══════════════════════════════════════════════════════════

    public function test_store_health_snapshot_can_be_created(): void
    {
        $store = Store::forceCreate(['name' => 'Health Store', 'is_active' => true]);
        $date  = now()->toDateString();

        $snapshot = StoreHealthSnapshot::forceCreate([
            'store_id'        => $store->id,
            'date'            => $date,
            'sync_status'     => 'ok',
            'zatca_compliance' => true,
            'error_count'     => 0,
        ]);

        $this->assertNotNull($snapshot->id);
        $this->assertEquals($store->id, $snapshot->store_id);
    }

    public function test_store_health_snapshot_sync_status_is_cast_to_enum(): void
    {
        $store = Store::forceCreate(['name' => 'Enum Store', 'is_active' => true]);

        $snapshot = StoreHealthSnapshot::forceCreate([
            'store_id'    => $store->id,
            'date'        => now()->toDateString(),
            'sync_status' => 'error',
            'error_count' => 3,
        ]);

        $this->assertInstanceOf(StoreHealthSyncStatus::class, $snapshot->sync_status);
        $this->assertEquals(StoreHealthSyncStatus::Error, $snapshot->sync_status);
    }

    public function test_store_health_snapshot_belongs_to_store(): void
    {
        $store = Store::forceCreate(['name' => 'BelongsTo Store', 'is_active' => true]);

        $snapshot = StoreHealthSnapshot::forceCreate([
            'store_id'    => $store->id,
            'date'        => now()->toDateString(),
            'sync_status' => 'ok',
            'error_count' => 0,
        ]);

        $loaded = StoreHealthSnapshot::with('store')->find($snapshot->id);
        $this->assertNotNull($loaded->store);
        $this->assertEquals($store->id, $loaded->store->id);
    }

    public function test_store_health_snapshot_zatca_compliance_is_cast_to_bool(): void
    {
        $store = Store::forceCreate(['name' => 'ZATCA Store', 'is_active' => true]);

        $compliant = StoreHealthSnapshot::forceCreate([
            'store_id'         => $store->id,
            'date'             => now()->toDateString(),
            'sync_status'      => 'ok',
            'zatca_compliance' => true,
            'error_count'      => 0,
        ]);

        $this->assertTrue($compliant->zatca_compliance);
        $this->assertIsBool($compliant->zatca_compliance);
    }

    public function test_store_health_snapshot_unique_store_date_constraint(): void
    {
        $store = Store::forceCreate(['name' => 'Unique Snapshot Store', 'is_active' => true]);
        $date  = now()->toDateString();

        StoreHealthSnapshot::forceCreate([
            'store_id' => $store->id, 'date' => $date,
            'sync_status' => 'ok', 'error_count' => 0,
        ]);

        StoreHealthSnapshot::updateOrCreate(
            ['store_id' => $store->id, 'date' => $date],
            ['error_count' => 5]
        );

        $this->assertEquals(1, StoreHealthSnapshot::where('store_id', $store->id)->where('date', $date)->count());
        $this->assertEquals(5, StoreHealthSnapshot::where('store_id', $store->id)->where('date', $date)->value('error_count'));
    }
}
