<?php

namespace Tests\Unit\Analytics;

use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Exports\RevenueExport;
use App\Domain\PlatformAnalytics\Exports\StoresExport;
use App\Domain\PlatformAnalytics\Exports\SubscriptionsExport;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for analytics export classes.
 *
 * Verifies collection filtering, column headings, row mapping, and sheet titles.
 */
class AnalyticsExportTest extends TestCase
{
    use RefreshDatabase;

    private string $dateFrom;
    private string $dateTo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dateFrom = now()->subDays(7)->toDateString();
        $this->dateTo   = now()->toDateString();
    }

    private function makeStat(string $date, float $mrr = 1000.0, float $gmv = 5000.0): PlatformDailyStat
    {
        return PlatformDailyStat::forceCreate([
            'date'                => $date,
            'total_active_stores' => 50,
            'new_registrations'   => 3,
            'total_orders'        => 200,
            'total_gmv'           => $gmv,
            'total_mrr'           => $mrr,
            'churn_count'         => 1,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // RevenueExport
    // ═══════════════════════════════════════════════════════════

    public function test_revenue_export_headings_contain_required_columns(): void
    {
        $export   = new RevenueExport($this->dateFrom, $this->dateTo);
        $headings = $export->headings();

        $this->assertContains('Date', $headings);
        $this->assertContains('MRR (SAR)', $headings);
        $this->assertContains('GMV (SAR)', $headings);
        $this->assertContains('Active Stores', $headings);
        $this->assertContains('Total Orders', $headings);
        $this->assertContains('Churn Count', $headings);
    }

    public function test_revenue_export_collection_respects_date_range(): void
    {
        // Within range
        $this->makeStat(now()->subDays(3)->toDateString());
        $this->makeStat(now()->subDays(1)->toDateString());
        // Outside range
        $this->makeStat(now()->subDays(10)->toDateString());

        $export     = new RevenueExport($this->dateFrom, $this->dateTo);
        $collection = $export->collection();

        $this->assertCount(2, $collection);
    }

    public function test_revenue_export_collection_is_ordered_by_date_asc(): void
    {
        $d1 = now()->subDays(5)->toDateString();
        $d2 = now()->subDays(2)->toDateString();
        $d3 = now()->subDays(1)->toDateString();

        $this->makeStat($d3);
        $this->makeStat($d1);
        $this->makeStat($d2);

        $export = new RevenueExport($this->dateFrom, $this->dateTo);
        $dates  = $export->collection()->pluck('date')->map(fn ($d) => $d->toDateString())->toArray();

        $this->assertEquals([$d1, $d2, $d3], $dates);
    }

    public function test_revenue_export_row_maps_all_fields(): void
    {
        $stat = $this->makeStat(now()->subDays(2)->toDateString(), 2500.0, 12000.75);

        $export = new RevenueExport($this->dateFrom, $this->dateTo);
        $row    = $export->map($stat);

        $this->assertEquals($stat->date->format('Y-m-d'), $row[0]);
        $this->assertEquals('2,500.00', $row[1]); // MRR formatted
        $this->assertEquals('12,000.75', $row[2]); // GMV formatted
        $this->assertEquals(50, $row[3]); // active stores
        $this->assertEquals(3, $row[4]);  // new registrations
        $this->assertEquals(200, $row[5]); // total orders
        $this->assertEquals(1, $row[6]);  // churn count
    }

    public function test_revenue_export_title_includes_date_range(): void
    {
        $export = new RevenueExport($this->dateFrom, $this->dateTo);
        $title  = $export->title();

        $this->assertStringContainsString($this->dateFrom, $title);
        $this->assertStringContainsString($this->dateTo, $title);
    }

    public function test_revenue_export_returns_empty_collection_for_no_data_in_range(): void
    {
        // Only create data outside the range
        $this->makeStat(now()->subDays(20)->toDateString());

        $export = new RevenueExport($this->dateFrom, $this->dateTo);

        $this->assertCount(0, $export->collection());
    }

    // ═══════════════════════════════════════════════════════════
    // SubscriptionsExport
    // ═══════════════════════════════════════════════════════════

    public function test_subscriptions_export_headings_contain_required_columns(): void
    {
        $export   = new SubscriptionsExport($this->dateFrom, $this->dateTo);
        $headings = $export->headings();

        $this->assertContains('Date', $headings);
        $this->assertContains('Plan Name', $headings);
        $this->assertContains('Active Subscriptions', $headings);
        $this->assertContains('Trial Subscriptions', $headings);
        $this->assertContains('Churned', $headings);
        $this->assertContains('MRR (SAR)', $headings);
    }

    public function test_subscriptions_export_collection_respects_date_range(): void
    {
        $plan = SubscriptionPlan::forceCreate([
            'name'          => 'Export Plan',
            'slug'          => 'export-plan-' . uniqid(),
            'monthly_price' => 99.0,
            'annual_price'  => 990.0,
            'trial_days'    => 14,
            'is_active'     => true,
        ]);

        // Within range
        PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id,
            'date'                 => now()->subDays(3)->toDateString(),
            'active_count'         => 10,
            'trial_count'          => 2,
            'churned_count'        => 1,
            'mrr'                  => 990.0,
        ]);

        // Outside range
        PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id,
            'date'                 => now()->subDays(15)->toDateString(),
            'active_count'         => 8,
            'trial_count'          => 1,
            'churned_count'        => 0,
            'mrr'                  => 792.0,
        ]);

        $export = new SubscriptionsExport($this->dateFrom, $this->dateTo);
        $this->assertCount(1, $export->collection());
    }

    public function test_subscriptions_export_row_maps_plan_name(): void
    {
        $plan = SubscriptionPlan::forceCreate([
            'name'          => 'Premium Plan',
            'slug'          => 'premium-plan-' . uniqid(),
            'monthly_price' => 199.0,
            'annual_price'  => 1990.0,
            'trial_days'    => 0,
            'is_active'     => true,
        ]);

        $stat = PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id,
            'date'                 => now()->subDays(2)->toDateString(),
            'active_count'         => 15,
            'trial_count'          => 0,
            'churned_count'        => 2,
            'mrr'                  => 2985.0,
        ]);

        $export = new SubscriptionsExport($this->dateFrom, $this->dateTo);
        $loaded = $export->collection()->first();
        $row    = $export->map($loaded);

        $this->assertEquals($stat->date->toDateString(), $row[0]);
        $this->assertEquals('Premium Plan', $row[1]);
        $this->assertEquals(15, $row[2]); // active
        $this->assertEquals(0, $row[3]);  // trial
        $this->assertEquals(2, $row[4]);  // churned
        $this->assertEquals('2,985.00', $row[5]); // mrr formatted
    }

    // ═══════════════════════════════════════════════════════════
    // StoresExport
    // ═══════════════════════════════════════════════════════════

    public function test_stores_export_headings_contain_required_columns(): void
    {
        $export   = new StoresExport($this->dateFrom, $this->dateTo);
        $headings = $export->headings();

        $this->assertContains('Date', $headings);
        $this->assertContains('Store Name', $headings);
        $this->assertContains('Sync Status', $headings);
        $this->assertContains('ZATCA Compliance', $headings);
        $this->assertContains('Error Count', $headings);
    }

    public function test_stores_export_collection_respects_date_range(): void
    {
        $store = Store::forceCreate(['name' => 'Export Store', 'is_active' => true]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $store->id, 'date' => now()->subDays(2)->toDateString(),
            'sync_status' => 'ok', 'error_count' => 0,
        ]);
        StoreHealthSnapshot::forceCreate([
            'store_id' => $store->id, 'date' => now()->subDays(12)->toDateString(),
            'sync_status' => 'ok', 'error_count' => 0,
        ]);

        $export = new StoresExport($this->dateFrom, $this->dateTo);
        $this->assertCount(1, $export->collection());
    }

    public function test_stores_export_row_maps_zatca_compliance_to_human_readable(): void
    {
        $store = Store::forceCreate(['name' => 'ZATCA Export Store', 'is_active' => true]);

        $snapshot = StoreHealthSnapshot::forceCreate([
            'store_id'         => $store->id,
            'date'             => now()->subDays(1)->toDateString(),
            'sync_status'      => 'ok',
            'zatca_compliance' => true,
            'error_count'      => 0,
        ]);

        $export = new StoresExport($this->dateFrom, $this->dateTo);
        $loaded = $export->collection()->first();
        $row    = $export->map($loaded);

        // Column index 3 = ZATCA Compliance
        $this->assertEquals('Yes', $row[3]);
    }

    public function test_stores_export_null_zatca_compliance_shows_na(): void
    {
        $store = Store::forceCreate(['name' => 'NA ZATCA Store', 'is_active' => true]);

        $snapshot = StoreHealthSnapshot::forceCreate([
            'store_id'         => $store->id,
            'date'             => now()->subDays(1)->toDateString(),
            'sync_status'      => 'ok',
            'zatca_compliance' => null,
            'error_count'      => 0,
        ]);

        $export = new StoresExport($this->dateFrom, $this->dateTo);
        $loaded = $export->collection()->first();
        $row    = $export->map($loaded);

        $this->assertEquals('N/A', $row[3]);
    }
}
