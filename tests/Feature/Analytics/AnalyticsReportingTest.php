<?php

namespace Tests\Feature\Analytics;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Models\FeatureAdoptionStat;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature tests for the AnalyticsReportingController (P6).
 *
 * All tests use SQLite :memory: via the test schema migration.
 * Permission/plan middleware is bypassed by BypassPermissionMiddleware.
 */
class AnalyticsReportingTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Organization $org;
    private Store $store;
    private string $token;

    // ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'          => 'Analytics Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Analytics Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->admin = AdminUser::create([
            'name'          => 'Platform Admin',
            'email'         => 'admin@analytics.test',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        $this->token = $this->admin->createToken('test', ['*'])->plainTextToken;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function analyticsGet(string $uri, array $params = []): \Illuminate\Testing\TestResponse
    {
        $query = $params ? '?' . http_build_query($params) : '';
        return $this->withToken($this->token)->getJson("/api/v2/admin/analytics{$uri}{$query}");
    }

    private function analyticsPost(string $uri, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson("/api/v2/admin/analytics{$uri}", $body);
    }

    private function createDailyStat(string $date, array $overrides = []): PlatformDailyStat
    {
        return PlatformDailyStat::create(array_merge([
            'date'                 => $date,
            'total_active_stores'  => 10,
            'new_registrations'    => 2,
            'total_orders'         => 50,
            'total_gmv'            => 5000.00,
            'total_mrr'            => 1200.00,
            'churn_count'          => 1,
        ], $overrides));
    }

    private function createPlanStat(string $date, string $planId, array $overrides = []): PlatformPlanStat
    {
        return PlatformPlanStat::create(array_merge([
            'date'                  => $date,
            'subscription_plan_id'  => $planId,
            'active_count'          => 5,
            'trial_count'           => 1,
            'churned_count'         => 0,
            'mrr'                   => 500.00,
        ], $overrides));
    }

    private function createFeatureStat(string $date, string $featureKey, array $overrides = []): FeatureAdoptionStat
    {
        return FeatureAdoptionStat::create(array_merge([
            'date'               => $date,
            'feature_key'        => $featureKey,
            'stores_using_count' => 3,
            'total_events'       => 120,
        ], $overrides));
    }

    private function createHealthSnapshot(string $storeId, string $date, array $overrides = []): StoreHealthSnapshot
    {
        return StoreHealthSnapshot::create(array_merge([
            'store_id'         => $storeId,
            'date'             => $date,
            'sync_status'      => 'ok',
            'zatca_compliance' => true,
            'error_count'      => 0,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────
    // Authentication Guard
    // ─────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v2/admin/analytics/dashboard');
        $response->assertUnauthorized();
    }

    // ─────────────────────────────────────────────────────────────
    // Dashboard Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_dashboard_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'kpi',
                    'recent_activity',
                ],
            ]);
    }

    public function test_dashboard_kpi_contains_expected_keys(): void
    {
        $this->createDailyStat(now()->toDateString());

        $response = $this->analyticsGet('/dashboard');

        $response->assertOk();
        $data = $response->json('data.kpi');
        $this->assertArrayHasKey('total_active_stores', $data);
        $this->assertArrayHasKey('mrr', $data);
    }

    // ─────────────────────────────────────────────────────────────
    // Revenue Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_revenue_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/revenue');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'mrr',
                    'arr',
                    'revenue_trend',
                    'revenue_by_plan',
                    'failed_payments_count',
                    'upcoming_renewals',
                ],
            ]);
    }

    public function test_revenue_filters_by_date_range(): void
    {
        $this->createDailyStat('2025-01-01', ['total_mrr' => 1000.00]);
        $this->createDailyStat('2025-02-01', ['total_mrr' => 2000.00]);
        $this->createDailyStat('2025-03-01', ['total_mrr' => 3000.00]);

        $response = $this->analyticsGet('/revenue', [
            'date_from' => '2025-01-01',
            'date_to'   => '2025-01-31',
        ]);

        $response->assertOk();
        $trend = $response->json('data.revenue_trend');
        $this->assertCount(1, $trend);
    }

    // ─────────────────────────────────────────────────────────────
    // Subscriptions Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_subscriptions_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/subscriptions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'status_counts',
                    'lifecycle_trend',
                    'average_subscription_age_days',
                    'total_churn_in_period',
                    'trial_to_paid_conversion_rate',
                ],
            ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Feature Adoption Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_features_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/features');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'features',
                    'trend',
                ],
            ]);
    }

    public function test_features_returns_feature_adoption_data(): void
    {
        $today = now()->toDateString();
        $this->createFeatureStat($today, 'zatca_einvoicing', ['stores_using_count' => 8]);
        $this->createFeatureStat($today, 'delivery_integration', ['stores_using_count' => 5]);

        $response = $this->analyticsGet('/features');

        $response->assertOk();
        $features = $response->json('data.features');
        $this->assertNotEmpty($features);

        // Each feature should have adoption_percentage
        foreach ($features as $feature) {
            $this->assertArrayHasKey('feature_key', $feature);
            $this->assertArrayHasKey('adoption_percentage', $feature);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Store Performance Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_stores_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/stores');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_stores',
                    'active_stores',
                    'top_stores',
                    'health_summary',
                ],
            ]);
    }

    public function test_stores_returns_correct_total_count(): void
    {
        $today = now()->toDateString();
        $this->createHealthSnapshot($this->store->id, $today);

        $response = $this->analyticsGet('/stores');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.total_stores'));
    }

    // ─────────────────────────────────────────────────────────────
    // System Health Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_system_health_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/system-health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'stores_monitored',
                    'stores_with_errors',
                    'total_errors_today',
                    'sync_status_breakdown',
                ],
            ]);
    }

    public function test_system_health_counts_stores_with_errors(): void
    {
        $today = now()->toDateString();
        $this->createHealthSnapshot($this->store->id, $today, [
            'sync_status'  => 'ok',
            'error_count'  => 0,
        ]);

        // Create a second store with errors
        $store2 = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Error Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => false,
        ]);
        $this->createHealthSnapshot($store2->id, $today, [
            'sync_status'  => 'error',
            'error_count'  => 3,
        ]);

        $response = $this->analyticsGet('/system-health');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['stores_with_errors']);
    }

    // ─────────────────────────────────────────────────────────────
    // Support Analytics Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_support_analytics_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/support');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
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
                ],
            ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Notifications Analytics Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_notifications_analytics_returns_success_structure(): void
    {
        $response = $this->analyticsGet('/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_sent',
                    'total_delivered',
                    'total_failed',
                    'total_opened',
                    'delivery_rate',
                    'open_rate',
                    'avg_latency_ms',
                    'by_channel',
                    'batch_stats',
                ],
            ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Daily Stats Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_daily_stats_returns_paginated_data(): void
    {
        $this->createDailyStat(now()->subDays(2)->toDateString());
        $this->createDailyStat(now()->subDays(1)->toDateString());
        $this->createDailyStat(now()->toDateString());

        $response = $this->analyticsGet('/daily-stats');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(3, $data);
        $this->assertArrayHasKey('date', $data[0]);
    }

    public function test_daily_stats_filters_by_date_range(): void
    {
        $this->createDailyStat('2025-01-01');
        $this->createDailyStat('2025-02-01');

        $response = $this->analyticsGet('/daily-stats', [
            'date_from' => '2025-01-01',
            'date_to'   => '2025-01-31',
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ─────────────────────────────────────────────────────────────
    // Plan Stats Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_plan_stats_returns_success(): void
    {
        $response = $this->analyticsGet('/plan-stats');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─────────────────────────────────────────────────────────────
    // Feature Stats Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_feature_stats_returns_success(): void
    {
        $response = $this->analyticsGet('/feature-stats');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─────────────────────────────────────────────────────────────
    // Store Health Endpoint
    // ─────────────────────────────────────────────────────────────

    public function test_store_health_returns_paginated_data(): void
    {
        $today = now()->toDateString();
        $this->createHealthSnapshot($this->store->id, $today);

        $response = $this->analyticsGet('/store-health');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    // ─────────────────────────────────────────────────────────────
    // Export Endpoints
    // ─────────────────────────────────────────────────────────────

    public function test_export_revenue_returns_download_url(): void
    {
        $response = $this->analyticsPost('/export/revenue', [
            'date_from' => '2025-01-01',
            'date_to'   => '2025-01-31',
            'format'    => 'csv',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'export_type',
                    'format',
                    'filename',
                ],
            ]);

        $this->assertEquals('revenue', $response->json('data.export_type'));
        $this->assertEquals('csv', $response->json('data.format'));
    }

    public function test_export_subscriptions_returns_download_url(): void
    {
        $response = $this->analyticsPost('/export/subscriptions', [
            'date_from' => '2025-01-01',
            'date_to'   => '2025-01-31',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'export_type',
                    'filename',
                ],
            ]);
    }

    public function test_export_stores_returns_download_url(): void
    {
        $response = $this->analyticsPost('/export/stores', []);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'export_type',
                    'filename',
                ],
            ]);
    }
}
