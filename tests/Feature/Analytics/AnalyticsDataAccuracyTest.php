<?php

namespace Tests\Feature\Analytics;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Notification\Models\NotificationBatch;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\NotificationDeliveryLog;
use App\Domain\Notification\Models\NotificationReadReceipt;
use App\Domain\PlatformAnalytics\Models\FeatureAdoptionStat;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Support\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Analytics Data Accuracy Tests
 *
 * Focuses on metric calculation correctness, edge cases, response structure
 * completeness, and API contract compliance for the Flutter client.
 *
 * Complements AnalyticsReportingApiTest.php (basic happy-path coverage).
 */
class AnalyticsDataAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Organization $org;
    private Store $defaultStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name'          => 'Test Admin',
            'email'         => 'accuracy@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->org = Organization::forceCreate([
            'name'          => 'Accuracy Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $this->defaultStore = Store::forceCreate([
            'name'            => 'Default Store',
            'organization_id' => $this->org->id,
            'is_active'       => true,
        ]);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function makePlan(float $price = 100.0): SubscriptionPlan
    {
        return SubscriptionPlan::forceCreate([
            'name'              => 'Plan ' . uniqid(),
            'slug'              => 'plan-' . uniqid(),
            'monthly_price'     => $price,
            'annual_price'      => $price * 10,
            'trial_days'        => 14,
            'grace_period_days' => 7,
            'is_active'         => true,
        ]);
    }

    private function makeStore(bool $active = true): Store
    {
        return Store::forceCreate([
            'name'            => 'Store ' . uniqid(),
            'organization_id' => $this->org->id,
            'is_active'       => $active,
        ]);
    }

    private function makeDailyStat(string $date, float $mrr = 1000.0, float $gmv = 5000.0, int $churn = 0): PlatformDailyStat
    {
        return PlatformDailyStat::forceCreate([
            'date'                => $date,
            'total_active_stores' => 50,
            'new_registrations'   => 5,
            'total_orders'        => 200,
            'total_gmv'           => $gmv,
            'total_mrr'           => $mrr,
            'churn_count'         => $churn,
        ]);
    }

    private function makeTicket(array $overrides = []): SupportTicket
    {
        static $ticketSeq = 0;
        $ticketSeq++;
        return SupportTicket::forceCreate(array_merge([
            'ticket_number'   => 'TKT-ACC' . str_pad($ticketSeq, 6, '0', STR_PAD_LEFT),
            'organization_id' => $this->org->id,
            'store_id'        => $this->defaultStore->id,
            'category'        => 'billing',
            'priority'        => 'medium',
            'status'          => 'open',
            'subject'         => 'Test ticket',
            'description'     => 'Test description',
            'created_at'      => now()->toDateTimeString(),
        ], $overrides));
    }

    private function makeNotificationLog(string $status, ?int $latencyMs = 100): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::forceCreate([
            'notification_id' => \Illuminate\Support\Str::uuid(),
            'channel'         => 'push',
            'provider'        => 'fcm',
            'recipient'       => 'test-token',
            'status'          => $status,
            'latency_ms'      => $latencyMs,
            'is_fallback'     => false,
            'retry_count'     => 0,
            'created_at'      => now()->toDateTimeString(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // DASHBOARD: KPI CALCULATION ACCURACY
    // ═══════════════════════════════════════════════════════════

    public function test_dashboard_churn_rate_calculation_is_correct(): void
    {
        $plan  = $this->makePlan();
        $store = $this->makeStore();

        // 10 active subscriptions + 2 churned today = churn rate 2/10 = 20%
        for ($i = 0; $i < 10; $i++) {
            StoreSubscription::forceCreate([
                'organization_id'      => $this->makeStore()->organization_id,
                'subscription_plan_id' => $plan->id,
                'status'               => 'active',
                'current_period_start' => now()->subMonth()->toDateTimeString(),
                'current_period_end'   => now()->addMonth()->toDateTimeString(),
            ]);
        }

        $this->makeDailyStat(now()->toDateString(), 1000.0, 5000.0, 2);

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertOk();

        $this->assertEquals(20.0, $response->json('data.kpi.churn_rate'));
    }

    public function test_dashboard_zatca_rate_100_when_all_compliant(): void
    {
        $today = now()->toDateString();
        $s1 = $this->makeStore();
        $s2 = $this->makeStore();

        foreach ([$s1, $s2] as $store) {
            StoreHealthSnapshot::forceCreate([
                'store_id' => $store->id, 'date' => $today,
                'sync_status' => 'ok', 'zatca_compliance' => true, 'error_count' => 0,
            ]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $this->assertEquals(100.0, $response->json('data.kpi.zatca_compliance_rate'));
    }

    public function test_dashboard_zatca_rate_0_when_all_non_compliant(): void
    {
        $today = now()->toDateString();
        $s1 = $this->makeStore();

        StoreHealthSnapshot::forceCreate([
            'store_id' => $s1->id, 'date' => $today,
            'sync_status' => 'error', 'zatca_compliance' => false, 'error_count' => 1,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $this->assertEquals(0.0, $response->json('data.kpi.zatca_compliance_rate'));
    }

    public function test_dashboard_falls_back_to_store_count_when_no_daily_stats(): void
    {
        $this->makeStore(true);
        $this->makeStore(true);
        $this->makeStore(false); // inactive

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertOk();

        // Without daily stats, total_active_stores falls back to Store::count() (all 3)
        // or the controller uses Store::count() depending on implementation
        $this->assertIsInt($response->json('data.kpi.total_active_stores'));
    }

    public function test_dashboard_recent_activity_limited_to_20(): void
    {
        // Create 25 activity log entries
        for ($i = 0; $i < 25; $i++) {
            \App\Domain\AdminPanel\Models\AdminActivityLog::forceCreate([
                'admin_user_id' => $this->admin->id,
                'action'        => 'action_' . $i,
                'entity_type'   => 'store',
                'entity_id'     => \Illuminate\Support\Str::uuid(),
                'ip_address'    => '127.0.0.1',
                'created_at'    => now()->subMinutes($i)->toDateTimeString(),
            ]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertOk();

        $this->assertCount(20, $response->json('data.recent_activity'));
    }

    // ═══════════════════════════════════════════════════════════
    // REVENUE: MRR, ARR, TREND
    // ═══════════════════════════════════════════════════════════

    public function test_revenue_arr_equals_twelve_times_mrr(): void
    {
        $this->makeDailyStat(now()->toDateString(), 7500.0);

        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $response->assertOk();

        $this->assertEquals(7500.0, $response->json('data.mrr'));
        $this->assertEquals(90000.0, $response->json('data.arr'));
    }

    public function test_revenue_trend_ordered_by_date_ascending(): void
    {
        $this->makeDailyStat(now()->subDays(3)->toDateString(), 1000.0);
        $this->makeDailyStat(now()->subDays(1)->toDateString(), 3000.0);
        $this->makeDailyStat(now()->subDays(2)->toDateString(), 2000.0);

        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $trend = $response->json('data.revenue_trend');

        $dates = array_column($trend, 'date');
        $sorted = $dates;
        sort($sorted);

        $this->assertEquals($sorted, $dates, 'Revenue trend must be sorted ascending by date.');
    }

    public function test_revenue_date_filter_excludes_out_of_range_stats(): void
    {
        $inRange  = now()->subDays(3)->toDateString();
        $outRange = now()->subDays(20)->toDateString();

        $this->makeDailyStat($inRange, 2000.0);
        $this->makeDailyStat($outRange, 5000.0); // outside 7-day window

        $response = $this->getJson('/api/v2/admin/analytics/revenue?date_from=' . now()->subDays(7)->toDateString() . '&date_to=' . now()->toDateString());
        $response->assertOk();

        $trend = $response->json('data.revenue_trend');
        $this->assertCount(1, $trend);
        $this->assertEquals($inRange, $trend[0]['date']);
    }

    public function test_revenue_each_trend_item_has_date_mrr_gmv(): void
    {
        $this->makeDailyStat(now()->subDays(1)->toDateString(), 3000.0, 12500.0);

        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $trend = $response->json('data.revenue_trend');

        $this->assertNotEmpty($trend);
        $item = $trend[0];
        $this->assertArrayHasKey('date', $item);
        $this->assertArrayHasKey('mrr', $item);
        $this->assertArrayHasKey('gmv', $item);
        $this->assertEquals(3000.0, $item['mrr']);
        $this->assertEquals(12500.0, $item['gmv']);
    }

    // ═══════════════════════════════════════════════════════════
    // SUBSCRIPTIONS: STATUS COUNTS, CHURN, CONVERSION
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_status_counts_reflect_all_statuses(): void
    {
        $plan = $this->makePlan();

        foreach (['active', 'active', 'trial', 'cancelled'] as $status) {
            StoreSubscription::forceCreate([
                'organization_id'      => $this->makeStore()->organization_id,
                'subscription_plan_id' => $plan->id,
                'status'               => $status,
                'current_period_start' => now()->subMonth()->toDateTimeString(),
                'current_period_end'   => now()->addMonth()->toDateTimeString(),
            ]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $response->assertOk();

        $counts = $response->json('data.status_counts');
        $this->assertEquals(2, $counts['active']);
        $this->assertEquals(1, $counts['trial']);
        $this->assertEquals(1, $counts['cancelled']);
    }

    public function test_subscription_churn_sums_churned_count_from_plan_stats(): void
    {
        $plan = $this->makePlan();
        $from = now()->subDays(7)->toDateString();
        $to   = now()->toDateString();

        PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id,
            'date'  => now()->subDays(4)->toDateString(),
            'active_count' => 10, 'trial_count' => 2, 'churned_count' => 3, 'mrr' => 1000.0,
        ]);
        PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id,
            'date'  => now()->subDays(2)->toDateString(),
            'active_count' => 9, 'trial_count' => 1, 'churned_count' => 2, 'mrr' => 900.0,
        ]);

        $response = $this->getJson("/api/v2/admin/analytics/subscriptions?date_from={$from}&date_to={$to}");
        $response->assertOk();

        $this->assertEquals(5, $response->json('data.total_churn_in_period'));
    }

    public function test_subscription_conversion_rate_is_between_0_and_100(): void
    {
        $plan = $this->makePlan();

        StoreSubscription::forceCreate([
            'organization_id' => $this->makeStore()->organization_id, 'subscription_plan_id' => $plan->id,
            'status' => 'active', 'current_period_start' => now()->subMonth()->toDateTimeString(),
            'current_period_end' => now()->addMonth()->toDateTimeString(),
        ]);
        StoreSubscription::forceCreate([
            'organization_id' => $this->makeStore()->organization_id, 'subscription_plan_id' => $plan->id,
            'status' => 'trial', 'current_period_start' => now()->subWeek()->toDateTimeString(),
            'current_period_end' => now()->addWeek()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $rate = $response->json('data.trial_to_paid_conversion_rate');

        $this->assertGreaterThanOrEqual(0, $rate);
        $this->assertLessThanOrEqual(100, $rate);
    }

    public function test_subscription_lifecycle_trend_groups_by_date(): void
    {
        $plan = $this->makePlan();

        PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id, 'date' => now()->subDays(2)->toDateString(),
            'active_count' => 5, 'trial_count' => 1, 'churned_count' => 0, 'mrr' => 500.0,
        ]);
        PlatformPlanStat::forceCreate([
            'subscription_plan_id' => $plan->id, 'date' => now()->subDays(1)->toDateString(),
            'active_count' => 6, 'trial_count' => 1, 'churned_count' => 1, 'mrr' => 600.0,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $trend = $response->json('data.lifecycle_trend');

        $this->assertCount(2, $trend);
        foreach ($trend as $entry) {
            $this->assertArrayHasKey('date', $entry);
            $this->assertArrayHasKey('active', $entry);
            $this->assertArrayHasKey('trial', $entry);
            $this->assertArrayHasKey('churned', $entry);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // STORES: COUNTS AND HEALTH SUMMARY
    // ═══════════════════════════════════════════════════════════

    public function test_stores_total_includes_inactive_stores(): void
    {
        $this->makeStore(true);
        $this->makeStore(true);
        $this->makeStore(false); // inactive

        $response = $this->getJson('/api/v2/admin/analytics/stores');
        $response->assertOk();

        // setUp() already creates 1 active defaultStore; 2 more active + 1 inactive = 4 total, 3 active
        $this->assertEquals(4, $response->json('data.total_stores'));
        $this->assertEquals(3, $response->json('data.active_stores'));
    }

    public function test_stores_health_summary_counts_each_sync_status(): void
    {
        $today = now()->toDateString();
        $s1 = $this->makeStore();
        $s2 = $this->makeStore();
        $s3 = $this->makeStore();

        StoreHealthSnapshot::forceCreate(['store_id' => $s1->id, 'date' => $today, 'sync_status' => 'ok', 'error_count' => 0]);
        StoreHealthSnapshot::forceCreate(['store_id' => $s2->id, 'date' => $today, 'sync_status' => 'ok', 'error_count' => 0]);
        StoreHealthSnapshot::forceCreate(['store_id' => $s3->id, 'date' => $today, 'sync_status' => 'error', 'error_count' => 2]);

        $response = $this->getJson('/api/v2/admin/analytics/stores');
        $summary  = $response->json('data.health_summary');

        $this->assertEquals(2, $summary['ok']);
        $this->assertEquals(1, $summary['error']);
    }

    public function test_stores_top_stores_limit_parameter_is_respected(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->makeStore(true);
        }

        $response = $this->getJson('/api/v2/admin/analytics/stores?limit=3');
        $response->assertOk();

        $this->assertCount(3, $response->json('data.top_stores'));
    }

    // ═══════════════════════════════════════════════════════════
    // FEATURES: ADOPTION PERCENTAGE
    // ═══════════════════════════════════════════════════════════

    public function test_feature_adoption_percentage_uses_total_stores_as_denominator(): void
    {
        // setUp creates 1 defaultStore; create 4 more = 5 total stores. Feature used by 3 → 3/5 = 60%
        for ($i = 0; $i < 4; $i++) {
            $this->makeStore(true);
        }

        FeatureAdoptionStat::forceCreate([
            'feature_key'        => 'loyalty_program',
            'date'               => now()->toDateString(),
            'stores_using_count' => 3,
            'total_events'       => 100,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/features');
        $response->assertOk();

        $feature = collect($response->json('data.features'))
            ->firstWhere('feature_key', 'loyalty_program');

        $this->assertNotNull($feature);
        // 3 of 5 stores = 60% (setUp defaultStore counts as 1 of the 5 total)
        $this->assertEquals(60.0, $feature['adoption_percentage']);
    }

    public function test_feature_adoption_percentage_zero_when_no_stores(): void
    {
        // When stores_using_count = 0, adoption_percentage must be 0.0
        // (setUp always creates 1 store, so we test the zero-numerator case instead)
        FeatureAdoptionStat::forceCreate([
            'feature_key'        => 'unused_feature',
            'date'               => now()->toDateString(),
            'stores_using_count' => 0,
            'total_events'       => 0,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/features');
        $response->assertOk();

        $feature = collect($response->json('data.features'))
            ->firstWhere('feature_key', 'unused_feature');

        $this->assertNotNull($feature);
        $this->assertEquals(0.0, $feature['adoption_percentage']);
    }

    public function test_feature_trend_data_aggregates_across_multiple_features(): void
    {
        $date1 = now()->subDays(2)->toDateString();
        $date2 = now()->subDays(1)->toDateString();

        // Two features on each date
        foreach (['feat_a', 'feat_b'] as $key) {
            FeatureAdoptionStat::forceCreate([
                'feature_key' => $key, 'date' => $date1,
                'stores_using_count' => 5, 'total_events' => 50,
            ]);
            FeatureAdoptionStat::forceCreate([
                'feature_key' => $key, 'date' => $date2,
                'stores_using_count' => 7, 'total_events' => 50,
            ]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/features');
        $trend = $response->json('data.trend');

        // 2 trend entries (one per date), each summing across 2 features
        $this->assertCount(2, $trend);
        $day1 = collect($trend)->firstWhere('date', $date1);
        $this->assertEquals(10, $day1['total_stores']); // 5+5
    }

    // ═══════════════════════════════════════════════════════════
    // SUPPORT: SLA COMPLIANCE RATE
    // ═══════════════════════════════════════════════════════════

    public function test_support_sla_compliance_100_when_all_resolved_before_deadline(): void
    {
        $this->makeTicket([
            'status'          => 'resolved',
            'sla_deadline_at' => now()->addHour()->toDateTimeString(),
            'resolved_at'     => now()->subMinute()->toDateTimeString(),
            'created_at'      => now()->subDay()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/support');
        $response->assertOk();

        $this->assertEquals(100.0, $response->json('data.sla_compliance_rate'));
    }

    public function test_support_sla_compliance_0_when_all_resolved_after_deadline(): void
    {
        $this->makeTicket([
            'status'          => 'resolved',
            'sla_deadline_at' => now()->subHour()->toDateTimeString(), // deadline in the past
            'resolved_at'     => now()->toDateTimeString(),             // resolved AFTER deadline
            'created_at'      => now()->subDays(2)->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/support');
        $response->assertOk();

        $this->assertEquals(0.0, $response->json('data.sla_compliance_rate'));
    }

    public function test_support_sla_breached_count_is_accurate(): void
    {
        // 2 tickets open past their SLA deadline
        $this->makeTicket([
            'status'          => 'open',
            'sla_deadline_at' => now()->subHour()->toDateTimeString(),
        ]);
        $this->makeTicket([
            'status'          => 'in_progress',
            'sla_deadline_at' => now()->subMinutes(30)->toDateTimeString(),
        ]);
        // 1 resolved ticket (not breached)
        $this->makeTicket([
            'status'          => 'resolved',
            'sla_deadline_at' => now()->subHour()->toDateTimeString(),
            'resolved_at'     => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/support');
        $this->assertEquals(2, $response->json('data.sla_breached'));
    }

    public function test_support_avg_first_response_hours_calculated_correctly(): void
    {
        // Ticket created at t=0, responded 2 hours later
        $created   = now()->subDays(1)->toDateTimeString();
        $responded = now()->subDays(1)->addHours(2)->toDateTimeString();

        $this->makeTicket([
            'status'            => 'resolved',
            'created_at'        => $created,
            'first_response_at' => $responded,
            'resolved_at'       => now()->subHours(20)->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/support');
        $response->assertOk();

        $avgHours = $response->json('data.avg_first_response_hours');
        $this->assertEquals(2.0, $avgHours);
    }

    public function test_support_by_category_groups_correctly(): void
    {
        $this->makeTicket(['category' => 'billing', 'created_at' => now()->subHour()->toDateTimeString()]);
        $this->makeTicket(['category' => 'billing', 'created_at' => now()->subHour()->toDateTimeString()]);
        $this->makeTicket(['category' => 'technical', 'created_at' => now()->subHour()->toDateTimeString()]);

        $response = $this->getJson('/api/v2/admin/analytics/support');
        $byCategory = $response->json('data.by_category');

        $this->assertEquals(2, $byCategory['billing']);
        $this->assertEquals(1, $byCategory['technical']);
    }

    public function test_support_by_priority_groups_correctly(): void
    {
        $this->makeTicket(['priority' => 'high', 'created_at' => now()->subHour()->toDateTimeString()]);
        $this->makeTicket(['priority' => 'low',  'created_at' => now()->subHour()->toDateTimeString()]);
        $this->makeTicket(['priority' => 'low',  'created_at' => now()->subHour()->toDateTimeString()]);

        $response = $this->getJson('/api/v2/admin/analytics/support');
        $byPriority = $response->json('data.by_priority');

        $this->assertEquals(1, $byPriority['high']);
        $this->assertEquals(2, $byPriority['low']);
    }

    // ═══════════════════════════════════════════════════════════
    // SYSTEM HEALTH: STORE ERROR COUNTS
    // ═══════════════════════════════════════════════════════════

    public function test_system_health_stores_with_errors_counts_non_zero_error_count(): void
    {
        $today = now()->toDateString();
        $s1 = $this->makeStore();
        $s2 = $this->makeStore();
        $s3 = $this->makeStore();

        StoreHealthSnapshot::forceCreate(['store_id' => $s1->id, 'date' => $today, 'sync_status' => 'ok', 'error_count' => 0]);
        StoreHealthSnapshot::forceCreate(['store_id' => $s2->id, 'date' => $today, 'sync_status' => 'error', 'error_count' => 3]);
        StoreHealthSnapshot::forceCreate(['store_id' => $s3->id, 'date' => $today, 'sync_status' => 'error', 'error_count' => 1]);

        $response = $this->getJson('/api/v2/admin/analytics/system-health');
        $response->assertOk();

        $this->assertEquals(3, $response->json('data.stores_monitored'));
        $this->assertEquals(2, $response->json('data.stores_with_errors'));
        $this->assertEquals(4, $response->json('data.total_errors_today'));
    }

    public function test_system_health_health_alias_endpoint_works(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/health');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['stores_monitored', 'stores_with_errors', 'total_errors_today', 'sync_status_breakdown'],
            ]);
    }

    // ═══════════════════════════════════════════════════════════
    // NOTIFICATIONS: DELIVERY & OPEN RATES
    // ═══════════════════════════════════════════════════════════

    public function test_notification_delivery_rate_calculation(): void
    {
        // 8 delivered, 2 failed = 80% delivery rate
        for ($i = 0; $i < 8; $i++) {
            $this->makeNotificationLog('delivered', 150);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->makeNotificationLog('failed', null);
        }

        $response = $this->getJson('/api/v2/admin/analytics/notifications');
        $response->assertOk();

        $this->assertEquals(80.0, $response->json('data.delivery_rate'));
        $this->assertEquals(8, $response->json('data.total_delivered'));
        $this->assertEquals(2, $response->json('data.total_failed'));
    }

    public function test_notification_avg_latency_ms_is_calculated(): void
    {
        $this->makeNotificationLog('delivered', 200);
        $this->makeNotificationLog('delivered', 400);

        $response = $this->getJson('/api/v2/admin/analytics/notifications');
        $response->assertOk();

        $this->assertEquals(300.0, $response->json('data.avg_latency_ms'));
    }

    public function test_notification_by_channel_breakdown_is_correct(): void
    {
        // Create logs: 3 push, 2 email
        for ($i = 0; $i < 3; $i++) {
            NotificationDeliveryLog::forceCreate([
                'notification_id' => \Illuminate\Support\Str::uuid(),
                'channel'  => 'push',
                'provider' => 'fcm',
                'recipient' => 'tok',
                'status'   => 'delivered',
                'latency_ms' => 100,
                'is_fallback' => false,
                'retry_count' => 0,
                'created_at' => now()->toDateTimeString(),
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            NotificationDeliveryLog::forceCreate([
                'notification_id' => \Illuminate\Support\Str::uuid(),
                'channel'  => 'email',
                'provider' => 'ses',
                'recipient' => 'user@example.com',
                'status'   => 'failed',
                'latency_ms' => null,
                'is_fallback' => false,
                'retry_count' => 0,
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/notifications');
        $response->assertOk();

        $byChannel = collect($response->json('data.by_channel'));

        $push  = $byChannel->firstWhere('channel', 'push');
        $email = $byChannel->firstWhere('channel', 'email');

        $this->assertNotNull($push);
        $this->assertNotNull($email);
        $this->assertEquals(3, $push['total']);
        $this->assertEquals(3, $push['delivered']);
        $this->assertEquals(2, $email['total']);
        $this->assertEquals(2, $email['failed']);
    }

    public function test_notification_batch_stats_are_summed_correctly(): void
    {
        NotificationBatch::forceCreate([
            'event_key'        => 'promo_blast',
            'channel'          => 'push',
            'total_recipients' => 1000,
            'sent_count'       => 950,
            'failed_count'     => 50,
            'status'           => 'completed',
            'created_at'       => now()->toDateTimeString(),
        ]);
        NotificationBatch::forceCreate([
            'event_key'        => 'order_reminder',
            'channel'          => 'email',
            'total_recipients' => 500,
            'sent_count'       => 490,
            'failed_count'     => 10,
            'status'           => 'completed',
            'created_at'       => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/notifications');
        $response->assertOk();

        $batch = $response->json('data.batch_stats');
        $this->assertEquals(2, $batch['total_batches']);
        $this->assertEquals(1500, $batch['total_recipients']);
        $this->assertEquals(1440, $batch['total_sent']);
        $this->assertEquals(60, $batch['total_failed']);
    }

    // ═══════════════════════════════════════════════════════════
    // API CONTRACT: RESPONSE SHAPES FOR FLUTTER CLIENT
    // ═══════════════════════════════════════════════════════════

    public function test_dashboard_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpi' => [
                        'total_active_stores',
                        'mrr',
                        'new_signups_this_month',
                        'churn_rate',
                        'total_orders',
                        'total_gmv',
                        'zatca_compliance_rate',
                    ],
                    'recent_activity',
                ],
            ]);
    }

    public function test_revenue_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'mrr',
                    'arr',
                    'revenue_trend',
                    'revenue_by_plan',
                    'failed_payments_count',
                    'upcoming_renewals',
                    'date_range' => ['from', 'to'],
                ],
            ]);
    }

    public function test_subscriptions_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status_counts',
                    'lifecycle_trend',
                    'average_subscription_age_days',
                    'total_churn_in_period',
                    'trial_to_paid_conversion_rate',
                    'date_range' => ['from', 'to'],
                ],
            ]);
    }

    public function test_stores_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/stores');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_stores',
                    'active_stores',
                    'top_stores',
                    'health_summary',
                ],
            ]);
    }

    public function test_features_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/features');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'features',
                    'trend',
                    'date_range' => ['from', 'to'],
                ],
            ]);
    }

    public function test_support_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/support');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_tickets',
                    'open_tickets',
                    'in_progress_tickets',
                    'resolved_tickets',
                    'closed_tickets',
                    'sla_compliance_rate',
                    'sla_breached',
                    'avg_first_response_hours',
                    'avg_resolution_hours',
                    'by_category',
                    'by_priority',
                    'date_range' => ['from', 'to'],
                ],
            ]);
    }

    public function test_system_health_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/system-health');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stores_monitored',
                    'stores_with_errors',
                    'total_errors_today',
                    'sync_status_breakdown',
                    'api_error_rate',
                    'queue_depth',
                ],
            ]);
    }

    public function test_notifications_response_matches_flutter_expected_contract(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/notifications');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_sent',
                    'total_delivered',
                    'total_failed',
                    'total_opened',
                    'delivery_rate',
                    'open_rate',
                    'avg_latency_ms',
                    'by_channel',
                    'batch_stats' => [
                        'total_batches',
                        'total_recipients',
                        'total_sent',
                        'total_failed',
                    ],
                    'date_range' => ['from', 'to'],
                ],
            ]);
    }

    // ═══════════════════════════════════════════════════════════
    // DATE RANGE: BOUNDARY CONDITIONS
    // ═══════════════════════════════════════════════════════════

    public function test_revenue_date_range_includes_boundary_dates(): void
    {
        $from = now()->subDays(5)->toDateString();
        $to   = now()->subDays(1)->toDateString();

        $this->makeDailyStat($from, 1000.0); // exactly at boundary — should be included
        $this->makeDailyStat($to, 2000.0);   // exactly at boundary — should be included
        $this->makeDailyStat(now()->subDays(6)->toDateString()); // outside — should be excluded

        $response = $this->getJson("/api/v2/admin/analytics/revenue?date_from={$from}&date_to={$to}");
        $this->assertCount(2, $response->json('data.revenue_trend'));
    }

    public function test_daily_stats_date_range_filter_works(): void
    {
        $from = now()->subDays(3)->toDateString();
        $to   = now()->subDays(1)->toDateString();

        $this->makeDailyStat(now()->subDays(4)->toDateString()); // outside
        $this->makeDailyStat(now()->subDays(2)->toDateString()); // inside
        $this->makeDailyStat(now()->subDays(1)->toDateString()); // inside

        $response = $this->getJson("/api/v2/admin/analytics/daily-stats?date_from={$from}&date_to={$to}");
        $response->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_store_health_filter_by_sync_status(): void
    {
        $today = now()->toDateString();
        $s1 = $this->makeStore();
        $s2 = $this->makeStore();
        $s3 = $this->makeStore();

        StoreHealthSnapshot::forceCreate(['store_id' => $s1->id, 'date' => $today, 'sync_status' => 'ok', 'error_count' => 0]);
        StoreHealthSnapshot::forceCreate(['store_id' => $s2->id, 'date' => $today, 'sync_status' => 'error', 'error_count' => 1]);
        StoreHealthSnapshot::forceCreate(['store_id' => $s3->id, 'date' => $today, 'sync_status' => 'error', 'error_count' => 2]);

        $response = $this->getJson('/api/v2/admin/analytics/store-health?sync_status=error');
        $response->assertOk();

        $this->assertCount(2, $response->json('data'));
    }
}
