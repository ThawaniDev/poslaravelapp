<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Analytics\Models\FeatureAdoptionStat;
use App\Domain\Analytics\Models\PlatformDailyStat;
use App\Domain\Analytics\Models\PlatformPlanStat;
use App\Domain\Analytics\Models\StoreHealthSnapshot;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsReportingApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Organization $org;
    private Store $store;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name' => 'Analytics Admin',
            'email' => 'analytics@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->org = Organization::forceCreate([
            'name' => 'Test Analytics Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::forceCreate([
            'name' => 'Test Analytics Store',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $this->plan = SubscriptionPlan::forceCreate([
            'name' => 'Basic Plan',
            'name_ar' => 'خطة أساسية',
            'slug' => 'basic_plan',
            'description' => 'Basic plan for testing',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'is_highlighted' => false,
            'sort_order' => 1,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // AUTH
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        // Reset auth
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v2/admin/analytics/dashboard')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // MAIN DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_main_dashboard_returns_kpis_with_no_data(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
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

    public function test_main_dashboard_calculates_kpis_from_daily_stats(): void
    {
        PlatformDailyStat::forceCreate([
            'date' => now()->toDateString(),
            'total_active_stores' => 150,
            'new_registrations' => 12,
            'total_orders' => 5000,
            'total_gmv' => 250000.00,
            'total_mrr' => 15000.00,
            'churn_count' => 3,
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');

        $response->assertOk();
        $data = $response->json('data.kpi');
        $this->assertEquals(150, $data['total_active_stores']);
        $this->assertEquals(12, $data['new_signups_this_month']);
        $this->assertEquals(5000, $data['total_orders']);
        $this->assertEquals(15000.0, $data['mrr']);
    }

    public function test_main_dashboard_includes_zatca_compliance_rate(): void
    {
        $today = now()->toDateString();

        // 2 compliant, 1 not
        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        $store2 = Store::forceCreate(['name' => 'Store 2', 'is_active' => true]);
        StoreHealthSnapshot::forceCreate([
            'store_id' => $store2->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        $store3 = Store::forceCreate(['name' => 'Store 3', 'is_active' => true]);
        StoreHealthSnapshot::forceCreate([
            'store_id' => $store3->id,
            'date' => $today,
            'sync_status' => 'error',
            'zatca_compliance' => false,
            'error_count' => 5,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertOk();

        $this->assertEquals(66.67, $response->json('data.kpi.zatca_compliance_rate'));
    }

    public function test_main_dashboard_includes_recent_activity(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'test_action',
            'entity_type' => 'store',
            'entity_id' => $this->store->id,
            'details' => json_encode(['message' => 'Did something']),
            'ip_address' => '127.0.0.1',
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertOk();

        $activity = $response->json('data.recent_activity');
        $this->assertCount(1, $activity);
        $this->assertEquals('test_action', $activity[0]['action']);
    }

    // ═══════════════════════════════════════════════════════════
    // REVENUE DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_revenue_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/revenue');

        $response->assertOk()
            ->assertJsonStructure([
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

    public function test_revenue_dashboard_calculates_mrr_and_arr(): void
    {
        PlatformDailyStat::forceCreate([
            'date' => now()->toDateString(),
            'total_active_stores' => 100,
            'new_registrations' => 5,
            'total_orders' => 2000,
            'total_gmv' => 100000.00,
            'total_mrr' => 10000.00,
            'churn_count' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $response->assertOk();

        $this->assertEquals(10000.0, $response->json('data.mrr'));
        $this->assertEquals(120000.0, $response->json('data.arr'));
    }

    public function test_revenue_dashboard_returns_revenue_trend(): void
    {
        $dates = [
            now()->subDays(2)->toDateString(),
            now()->subDays(1)->toDateString(),
            now()->toDateString(),
        ];

        foreach ($dates as $i => $date) {
            PlatformDailyStat::forceCreate([
                'date' => $date,
                'total_active_stores' => 100 + $i,
                'new_registrations' => 1,
                'total_orders' => 100 + ($i * 50),
                'total_gmv' => 5000 + ($i * 1000),
                'total_mrr' => 1000 + ($i * 100),
                'churn_count' => 0,
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $response->assertOk();

        $trend = $response->json('data.revenue_trend');
        $this->assertCount(3, $trend);
        $this->assertEquals($dates[0], $trend[0]['date']);
    }

    public function test_revenue_dashboard_with_date_range_filter(): void
    {
        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(60)->toDateString(),
            'total_active_stores' => 80,
            'new_registrations' => 2,
            'total_orders' => 1000,
            'total_gmv' => 50000.00,
            'total_mrr' => 8000.00,
            'churn_count' => 0,
            'created_at' => now()->toDateTimeString(),
        ]);

        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(5)->toDateString(),
            'total_active_stores' => 100,
            'new_registrations' => 5,
            'total_orders' => 2000,
            'total_gmv' => 100000.00,
            'total_mrr' => 10000.00,
            'churn_count' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        // Default range (last 30 days) should exclude the 60-day-old stat
        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $response->assertOk();

        $trend = $response->json('data.revenue_trend');
        $this->assertCount(1, $trend);

        // Custom range to include both
        $from = now()->subDays(65)->toDateString();
        $to = now()->toDateString();
        $response = $this->getJson("/api/v2/admin/analytics/revenue?date_from={$from}&date_to={$to}");
        $response->assertOk();

        $trend = $response->json('data.revenue_trend');
        $this->assertCount(2, $trend);
    }

    public function test_revenue_dashboard_includes_revenue_by_plan(): void
    {
        $today = now()->toDateString();

        PlatformPlanStat::forceCreate([
            'date' => $today,
            'subscription_plan_id' => $this->plan->id,
            'active_stores' => 50,
            'trial_stores' => 10,
            'churned_stores' => 2,
            'revenue' => 1500.00,
        ]);

        $response = $this->getJson("/api/v2/admin/analytics/revenue?date_to={$today}");
        $response->assertOk();

        $byPlan = $response->json('data.revenue_by_plan');
        $this->assertCount(1, $byPlan);
        $this->assertEquals($this->plan->id, $byPlan[0]['plan_id']);
        $this->assertEquals(50, $byPlan[0]['active_count']);
    }

    public function test_revenue_dashboard_counts_failed_payments(): void
    {
        $sub = StoreSubscription::forceCreate([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        Invoice::forceCreate([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-FAIL-001',
            'status' => 'failed',
            'amount' => 50.00,
        ]);

        Invoice::forceCreate([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-FAIL-002',
            'status' => 'failed',
            'amount' => 50.00,
        ]);

        Invoice::forceCreate([
            'store_subscription_id' => $sub->id,
            'invoice_number' => 'INV-PAID-001',
            'status' => 'paid',
            'amount' => 50.00,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/revenue');
        $response->assertOk();

        $this->assertEquals(2, $response->json('data.failed_payments_count'));
    }

    // ═══════════════════════════════════════════════════════════
    // SUBSCRIPTION DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_subscription_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status_counts',
                    'lifecycle_trend',
                    'average_subscription_age_days',
                    'total_churn_in_period',
                    'trial_to_paid_conversion_rate',
                    'date_range',
                ],
            ]);
    }

    public function test_subscription_dashboard_counts_by_status(): void
    {
        $org2 = Organization::forceCreate(['name' => 'Org 2', 'business_type' => 'grocery', 'country' => 'OM']);
        $org3 = Organization::forceCreate(['name' => 'Org 3', 'business_type' => 'grocery', 'country' => 'OM']);
        $store2 = Store::forceCreate(['name' => 'Store 2', 'organization_id' => $org2->id, 'is_active' => true]);
        $store3 = Store::forceCreate(['name' => 'Store 3', 'organization_id' => $org3->id, 'is_active' => true]);

        StoreSubscription::forceCreate([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        StoreSubscription::forceCreate([
            'organization_id' => $org2->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        StoreSubscription::forceCreate([
            'organization_id' => $org3->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $response->assertOk();

        $counts = $response->json('data.status_counts');
        $this->assertEquals(2, $counts['active']);
        $this->assertEquals(1, $counts['trial']);
    }

    public function test_subscription_dashboard_lifecycle_trend(): void
    {
        $yesterday = now()->subDays(1)->toDateString();

        PlatformPlanStat::forceCreate([
            'date' => $yesterday,
            'subscription_plan_id' => $this->plan->id,
            'active_stores' => 50,
            'trial_stores' => 10,
            'churned_stores' => 2,
            'revenue' => 1500.00,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $response->assertOk();

        $trend = $response->json('data.lifecycle_trend');
        $this->assertNotEmpty($trend);
        $this->assertEquals(50, $trend[0]['active']);
        $this->assertEquals(10, $trend[0]['trial']);
        $this->assertEquals(2, $trend[0]['churned']);
    }

    public function test_subscription_dashboard_total_churn(): void
    {
        $yesterday = now()->subDays(1)->toDateString();

        PlatformPlanStat::forceCreate([
            'date' => $yesterday,
            'subscription_plan_id' => $this->plan->id,
            'active_stores' => 50,
            'trial_stores' => 10,
            'churned_stores' => 5,
            'revenue' => 1500.00,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $response->assertOk();
        $this->assertEquals(5, $response->json('data.total_churn_in_period'));
    }

    public function test_subscription_dashboard_conversion_rate(): void
    {
        $org2 = Organization::forceCreate(['name' => 'Org 2', 'business_type' => 'grocery', 'country' => 'OM']);
        $org3 = Organization::forceCreate(['name' => 'Org 3', 'business_type' => 'grocery', 'country' => 'OM']);
        $store2 = Store::forceCreate(['name' => 'Store 2', 'organization_id' => $org2->id, 'is_active' => true]);
        $store3 = Store::forceCreate(['name' => 'Store 3', 'organization_id' => $org3->id, 'is_active' => true]);

        // 2 active, 1 trial => conversion = 2/(2+1) * 100 = 66.67
        StoreSubscription::forceCreate([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        StoreSubscription::forceCreate([
            'organization_id' => $org2->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        StoreSubscription::forceCreate([
            'organization_id' => $org3->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $response->assertOk();
        $this->assertEquals(66.67, $response->json('data.trial_to_paid_conversion_rate'));
    }

    // ═══════════════════════════════════════════════════════════
    // STORE PERFORMANCE DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_store_performance_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/stores');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_stores',
                    'active_stores',
                    'top_stores',
                    'health_summary',
                ],
            ]);
    }

    public function test_store_performance_counts_stores(): void
    {
        // We already have 1 active store from setUp
        Store::forceCreate(['name' => 'Active Store 2', 'is_active' => true]);
        Store::forceCreate(['name' => 'Inactive Store', 'is_active' => false]);

        $response = $this->getJson('/api/v2/admin/analytics/stores');
        $response->assertOk();

        $this->assertEquals(3, $response->json('data.total_stores'));
        $this->assertEquals(2, $response->json('data.active_stores'));
    }

    public function test_store_performance_top_stores_respects_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Store::forceCreate(['name' => "Store Extra {$i}", 'is_active' => true]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/stores?limit=3');
        $response->assertOk();

        $topStores = $response->json('data.top_stores');
        $this->assertCount(3, $topStores);
    }

    public function test_store_performance_includes_health_summary(): void
    {
        $today = now()->toDateString();
        $store2 = Store::forceCreate(['name' => 'Store 2', 'is_active' => true]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $store2->id,
            'date' => $today,
            'sync_status' => 'error',
            'zatca_compliance' => false,
            'error_count' => 3,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/stores');
        $response->assertOk();

        $health = $response->json('data.health_summary');
        $this->assertEquals(1, $health['ok']);
        $this->assertEquals(1, $health['error']);
    }

    // ═══════════════════════════════════════════════════════════
    // FEATURE ADOPTION DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_feature_adoption_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/features');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'features',
                    'trend',
                    'date_range',
                ],
            ]);
    }

    public function test_feature_adoption_returns_feature_stats(): void
    {
        $today = now()->toDateString();

        FeatureAdoptionStat::forceCreate([
            'date' => $today,
            'feature_key' => 'zatca_invoicing',
            'stores_using' => 80,
            'total_eligible' => 100,
        ]);

        FeatureAdoptionStat::forceCreate([
            'date' => $today,
            'feature_key' => 'inventory_management',
            'stores_using' => 60,
            'total_eligible' => 100,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/features');
        $response->assertOk();

        $features = $response->json('data.features');
        $this->assertCount(2, $features);
        // Should be sorted by stores_using DESC
        $this->assertEquals('zatca_invoicing', $features[0]['feature_key']);
        $this->assertEquals(80, $features[0]['stores_using']);
    }

    public function test_feature_adoption_calculates_adoption_percentage(): void
    {
        $today = now()->toDateString();

        // We have 1 store in setUp, total stores = 1
        FeatureAdoptionStat::forceCreate([
            'date' => $today,
            'feature_key' => 'pos_orders',
            'stores_using' => 1,
            'total_eligible' => 1,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/features');
        $response->assertOk();

        // 1/1 * 100 = 100
        $this->assertEquals(100.0, $response->json('data.features.0.adoption_percentage'));
    }

    public function test_feature_adoption_includes_trend(): void
    {
        $date1 = now()->subDays(2)->toDateString();
        $date2 = now()->subDays(1)->toDateString();

        FeatureAdoptionStat::forceCreate([
            'date' => $date1,
            'feature_key' => 'pos',
            'stores_using' => 10,
            'total_eligible' => 20,
        ]);

        FeatureAdoptionStat::forceCreate([
            'date' => $date2,
            'feature_key' => 'pos',
            'stores_using' => 15,
            'total_eligible' => 20,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/features');
        $response->assertOk();

        $trend = $response->json('data.trend');
        $this->assertCount(2, $trend);
    }

    // ═══════════════════════════════════════════════════════════
    // SUPPORT ANALYTICS DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_support_analytics_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/support');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_support_actions',
                    'open_tickets',
                    'avg_first_response_hours',
                    'avg_resolution_hours',
                    'sla_compliance_rate',
                ],
            ]);
    }

    public function test_support_analytics_counts_support_actions(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'reply_ticket',
            'entity_type' => 'support',
            'entity_id' => null,
            'details' => json_encode(['message' => 'Replied to ticket']),
            'ip_address' => '127.0.0.1',
            'created_at' => now()->toDateTimeString(),
        ]);

        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'close_ticket',
            'entity_type' => 'support',
            'entity_id' => null,
            'details' => json_encode(['message' => 'Closed ticket']),
            'ip_address' => '127.0.0.1',
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/support');
        $response->assertOk();

        $this->assertEquals(2, $response->json('data.total_support_actions'));
    }

    // ═══════════════════════════════════════════════════════════
    // SYSTEM HEALTH DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_system_health_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/health');

        $response->assertOk()
            ->assertJsonStructure([
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

    public function test_system_health_dashboard_counts_errors(): void
    {
        $today = now()->toDateString();
        $store2 = Store::forceCreate(['name' => 'Err Store', 'is_active' => true]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $store2->id,
            'date' => $today,
            'sync_status' => 'error',
            'zatca_compliance' => false,
            'error_count' => 7,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/health');
        $response->assertOk();

        $this->assertEquals(2, $response->json('data.stores_monitored'));
        $this->assertEquals(1, $response->json('data.stores_with_errors'));
        $this->assertEquals(7, $response->json('data.total_errors_today'));
    }

    public function test_system_health_dashboard_sync_status_breakdown(): void
    {
        $today = now()->toDateString();
        $store2 = Store::forceCreate(['name' => 'Store 2', 'is_active' => true]);
        $store3 = Store::forceCreate(['name' => 'Store 3', 'is_active' => true]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $store2->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $store3->id,
            'date' => $today,
            'sync_status' => 'degraded',
            'zatca_compliance' => true,
            'error_count' => 1,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/health');
        $response->assertOk();

        $breakdown = $response->json('data.sync_status_breakdown');
        $this->assertEquals(2, $breakdown['ok']);
        $this->assertEquals(1, $breakdown['degraded']);
    }

    // ═══════════════════════════════════════════════════════════
    // NOTIFICATION ANALYTICS
    // ═══════════════════════════════════════════════════════════

    public function test_notification_analytics_returns_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_sent',
                    'total_delivered',
                    'total_opened',
                    'delivery_rate',
                    'open_rate',
                    'by_channel',
                ],
            ]);
    }

    // ═══════════════════════════════════════════════════════════
    // DAILY STATS RAW ACCESS
    // ═══════════════════════════════════════════════════════════

    public function test_list_daily_stats_returns_data(): void
    {
        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(1)->toDateString(),
            'total_active_stores' => 100,
            'new_registrations' => 5,
            'total_orders' => 2000,
            'total_gmv' => 100000.00,
            'total_mrr' => 10000.00,
            'churn_count' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/daily-stats');
        $response->assertOk();

        $stats = $response->json('data');
        $this->assertCount(1, $stats);
        $this->assertEquals(100, $stats[0]['total_active_stores']);
        $this->assertEquals(10000.0, $stats[0]['total_mrr']);
    }

    public function test_list_daily_stats_respects_date_range(): void
    {
        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(60)->toDateString(),
            'total_active_stores' => 80,
            'new_registrations' => 2,
            'total_orders' => 1000,
            'total_gmv' => 50000.00,
            'total_mrr' => 8000.00,
            'churn_count' => 0,
            'created_at' => now()->toDateTimeString(),
        ]);

        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(1)->toDateString(),
            'total_active_stores' => 100,
            'new_registrations' => 5,
            'total_orders' => 2000,
            'total_gmv' => 100000.00,
            'total_mrr' => 10000.00,
            'churn_count' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        // Default: last 30 days
        $response = $this->getJson('/api/v2/admin/analytics/daily-stats');
        $this->assertCount(1, $response->json('data'));

        // With custom range
        $from = now()->subDays(65)->toDateString();
        $to = now()->toDateString();
        $response = $this->getJson("/api/v2/admin/analytics/daily-stats?date_from={$from}&date_to={$to}");
        $this->assertCount(2, $response->json('data'));
    }

    // ═══════════════════════════════════════════════════════════
    // PLAN STATS RAW ACCESS
    // ═══════════════════════════════════════════════════════════

    public function test_list_plan_stats_returns_data(): void
    {
        PlatformPlanStat::forceCreate([
            'date' => now()->subDays(1)->toDateString(),
            'subscription_plan_id' => $this->plan->id,
            'active_stores' => 50,
            'trial_stores' => 10,
            'churned_stores' => 2,
            'revenue' => 1500.00,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/plan-stats');
        $response->assertOk();

        $stats = $response->json('data');
        $this->assertCount(1, $stats);
        $this->assertEquals($this->plan->id, $stats[0]['plan_id']);
        $this->assertEquals('Basic Plan', $stats[0]['plan_name']);
        $this->assertEquals(50, $stats[0]['active_stores']);
        $this->assertEquals(1500.0, $stats[0]['revenue']);
    }

    // ═══════════════════════════════════════════════════════════
    // FEATURE STATS RAW ACCESS
    // ═══════════════════════════════════════════════════════════

    public function test_list_feature_stats_returns_data(): void
    {
        FeatureAdoptionStat::forceCreate([
            'date' => now()->subDays(1)->toDateString(),
            'feature_key' => 'zatca',
            'stores_using' => 80,
            'total_eligible' => 100,
        ]);

        FeatureAdoptionStat::forceCreate([
            'date' => now()->subDays(1)->toDateString(),
            'feature_key' => 'inventory',
            'stores_using' => 40,
            'total_eligible' => 100,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/feature-stats');
        $response->assertOk();

        $stats = $response->json('data');
        $this->assertCount(2, $stats);
    }

    // ═══════════════════════════════════════════════════════════
    // STORE HEALTH RAW ACCESS
    // ═══════════════════════════════════════════════════════════

    public function test_list_store_health_returns_data(): void
    {
        $today = now()->toDateString();

        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/store-health');
        $response->assertOk();

        $health = $response->json('data');
        $this->assertCount(1, $health);
        $this->assertEquals('ok', $health[0]['sync_status']);
    }

    public function test_list_store_health_filters_by_sync_status(): void
    {
        $today = now()->toDateString();
        $store2 = Store::forceCreate(['name' => 'Store 2', 'is_active' => true]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $store2->id,
            'date' => $today,
            'sync_status' => 'error',
            'zatca_compliance' => false,
            'error_count' => 5,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/store-health?sync_status=error');
        $response->assertOk();

        $health = $response->json('data');
        $this->assertCount(1, $health);
        $this->assertEquals('error', $health[0]['sync_status']);
    }

    public function test_list_store_health_filters_by_date(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $today,
            'sync_status' => 'ok',
            'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        StoreHealthSnapshot::forceCreate([
            'store_id' => $this->store->id,
            'date' => $yesterday,
            'sync_status' => 'degraded',
            'zatca_compliance' => true,
            'error_count' => 2,
        ]);

        // Default shows today
        $response = $this->getJson('/api/v2/admin/analytics/store-health');
        $this->assertCount(1, $response->json('data'));

        // Filter by yesterday
        $response = $this->getJson("/api/v2/admin/analytics/store-health?date={$yesterday}");
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('degraded', $response->json('data.0.sync_status'));
    }

    // ═══════════════════════════════════════════════════════════
    // EXPORT ENDPOINTS
    // ═══════════════════════════════════════════════════════════

    public function test_export_revenue_creates_export(): void
    {
        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(1)->toDateString(),
            'total_active_stores' => 100,
            'new_registrations' => 5,
            'total_orders' => 2000,
            'total_gmv' => 100000.00,
            'total_mrr' => 10000.00,
            'churn_count' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->postJson('/api/v2/admin/analytics/export/revenue', [
            'format' => 'xlsx',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.export_type', 'revenue')
            ->assertJsonPath('data.format', 'xlsx')
            ->assertJsonPath('data.record_count', 1);
    }

    public function test_export_revenue_logs_activity(): void
    {
        $this->postJson('/api/v2/admin/analytics/export/revenue');

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'export_revenue',
            'entity_type' => 'analytics',
        ]);
    }

    public function test_export_subscriptions_creates_export(): void
    {
        $sub = StoreSubscription::forceCreate([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->postJson('/api/v2/admin/analytics/export/subscriptions', [
            'format' => 'csv',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.export_type', 'subscriptions')
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.record_count', 1);
    }

    public function test_export_subscriptions_logs_activity(): void
    {
        $this->postJson('/api/v2/admin/analytics/export/subscriptions');

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'export_subscriptions',
            'entity_type' => 'analytics',
        ]);
    }

    public function test_export_stores_creates_export(): void
    {
        $response = $this->postJson('/api/v2/admin/analytics/export/stores');

        $response->assertOk()
            ->assertJsonPath('data.export_type', 'stores')
            ->assertJsonPath('data.record_count', 1); // 1 store from setUp
    }

    public function test_export_stores_logs_activity(): void
    {
        $this->postJson('/api/v2/admin/analytics/export/stores');

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'export_stores',
            'entity_type' => 'analytics',
        ]);
    }

    public function test_export_revenue_with_custom_date_range(): void
    {
        $from = now()->subDays(60)->toDateString();
        $to = now()->toDateString();

        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(50)->toDateString(),
            'total_active_stores' => 80,
            'new_registrations' => 2,
            'total_orders' => 1000,
            'total_gmv' => 50000.00,
            'total_mrr' => 8000.00,
            'churn_count' => 0,
            'created_at' => now()->toDateTimeString(),
        ]);

        PlatformDailyStat::forceCreate([
            'date' => now()->subDays(5)->toDateString(),
            'total_active_stores' => 100,
            'new_registrations' => 5,
            'total_orders' => 2000,
            'total_gmv' => 100000.00,
            'total_mrr' => 10000.00,
            'churn_count' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->postJson('/api/v2/admin/analytics/export/revenue', [
            'date_from' => $from,
            'date_to' => $to,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.record_count', 2);
    }

    // ═══════════════════════════════════════════════════════════
    // EDGE CASES
    // ═══════════════════════════════════════════════════════════

    public function test_dashboards_handle_empty_data_gracefully(): void
    {
        // All dashboards should work with empty tables
        $endpoints = [
            '/api/v2/admin/analytics/dashboard',
            '/api/v2/admin/analytics/revenue',
            '/api/v2/admin/analytics/subscriptions',
            '/api/v2/admin/analytics/stores',
            '/api/v2/admin/analytics/features',
            '/api/v2/admin/analytics/support',
            '/api/v2/admin/analytics/health',
            '/api/v2/admin/analytics/notifications',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertOk();
        }
    }

    public function test_main_dashboard_churn_rate_zero_when_no_subscriptions(): void
    {
        PlatformDailyStat::forceCreate([
            'date' => now()->toDateString(),
            'total_active_stores' => 0,
            'new_registrations' => 0,
            'total_orders' => 0,
            'total_gmv' => 0,
            'total_mrr' => 0,
            'churn_count' => 5,
            'created_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertOk();
        $this->assertEquals(0, $response->json('data.kpi.churn_rate'));
    }

    public function test_subscription_dashboard_conversion_zero_when_no_subscriptions(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/subscriptions');
        $response->assertOk();
        $this->assertEquals(0, $response->json('data.trial_to_paid_conversion_rate'));
    }

    public function test_feature_adoption_empty_when_no_data_in_range(): void
    {
        // Only data outside the default 30-day range
        FeatureAdoptionStat::forceCreate([
            'date' => now()->subDays(60)->toDateString(),
            'feature_key' => 'old_feature',
            'stores_using' => 10,
            'total_eligible' => 50,
        ]);

        $response = $this->getJson('/api/v2/admin/analytics/features');
        $response->assertOk();
        $this->assertEmpty($response->json('data.features'));
    }

    public function test_multiple_daily_stats_ordered_by_date(): void
    {
        $dates = [
            now()->subDays(5)->toDateString(),
            now()->subDays(3)->toDateString(),
            now()->subDays(1)->toDateString(),
        ];

        foreach ($dates as $i => $date) {
            PlatformDailyStat::forceCreate([
                'date' => $date,
                'total_active_stores' => 100 + $i * 10,
                'new_registrations' => $i,
                'total_orders' => $i * 100,
                'total_gmv' => $i * 10000,
                'total_mrr' => $i * 1000,
                'churn_count' => 0,
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        $response = $this->getJson('/api/v2/admin/analytics/daily-stats');
        $response->assertOk();

        $stats = $response->json('data');
        $this->assertCount(3, $stats);
        // Check ordered by date ASC
        $this->assertEquals($dates[0], $stats[0]['date']);
        $this->assertEquals($dates[2], $stats[2]['date']);
    }

    public function test_revenue_dashboard_filters_by_plan_id(): void
    {
        $today = now()->toDateString();
        $plan2 = SubscriptionPlan::forceCreate([
            'name' => 'Premium Plan',
            'name_ar' => 'خطة متميزة',
            'slug' => 'premium_plan',
            'description' => 'Premium',
            'monthly_price' => 99.99,
            'annual_price' => 999.99,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'is_highlighted' => false,
            'sort_order' => 2,
        ]);

        PlatformPlanStat::forceCreate([
            'date' => $today,
            'subscription_plan_id' => $this->plan->id,
            'active_stores' => 50,
            'trial_stores' => 10,
            'churned_stores' => 2,
            'revenue' => 1500.00,
        ]);

        PlatformPlanStat::forceCreate([
            'date' => $today,
            'subscription_plan_id' => $plan2->id,
            'active_stores' => 30,
            'trial_stores' => 5,
            'churned_stores' => 1,
            'revenue' => 3000.00,
        ]);

        // Filter by basic plan only
        $response = $this->getJson("/api/v2/admin/analytics/revenue?date_to={$today}&plan_id={$this->plan->id}");
        $response->assertOk();

        $byPlan = $response->json('data.revenue_by_plan');
        $this->assertCount(1, $byPlan);
        $this->assertEquals($this->plan->id, $byPlan[0]['plan_id']);
    }
}
