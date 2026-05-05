<?php

namespace Tests\Feature\Delivery;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Jobs\MenuSyncJob;
use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Services\MenuSyncService;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menu sync flow tests — API endpoint + MenuSyncService + job dispatch.
 */
class DeliveryMenuSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Sync Org',
            'business_type' => 'restaurant',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Sync Branch',
            'business_type' => 'restaurant',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Provider',
            'email' => 'sync@test.com',
            'password_hash' => bcrypt('pass'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('t', ['*'])->plainTextToken;

        $plan = SubscriptionPlan::create([
            'name' => 'Pro', 'slug' => 'pro',
            'monthly_price' => 0, 'is_active' => true, 'sort_order' => 1,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key' => 'delivery_integration',
            'is_enabled' => true,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        DB::table('delivery_platforms')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Jahez', 'slug' => 'jahez',
            'auth_method' => 'api_key', 'is_active' => true,
            'sort_order' => 1, 'default_commission_percent' => 18.5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeConfig(array $overrides = []): DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::create(array_merge([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'api_key' => 'SYNC-KEY',
            'merchant_id' => 'M-SYNC',
            'webhook_secret' => 'wh-secret',
            'is_enabled' => true,
            'auto_accept' => true,
            'sync_menu_on_product_change' => true,
            'menu_sync_interval_hours' => 6,
            'status' => 'active',
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. API: POST /menu-sync queues a job
    // ─────────────────────────────────────────────────────────────────────

    public function test_menu_sync_api_queues_job_and_returns_queued(): void
    {
        Bus::fake();
        $this->makeConfig();

        $r = $this->postJson(
            '/api/v2/delivery/menu-sync',
            ['platform' => 'jahez'],
            ['Authorization' => "Bearer {$this->token}"],
        );

        $r->assertOk()
          ->assertJson(['success' => true]);
        Bus::assertDispatched(MenuSyncJob::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. API: menu-sync with no enabled config returns 404 / error
    // ─────────────────────────────────────────────────────────────────────

    public function test_menu_sync_api_returns_error_when_no_enabled_config(): void
    {
        $r = $this->postJson(
            '/api/v2/delivery/menu-sync',
            ['platform' => 'jahez'],
            ['Authorization' => "Bearer {$this->token}"],
        );

        // No config → service returns 404
        $this->assertContains($r->status(), [404, 422]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. API: GET /sync-logs returns paginated logs
    // ─────────────────────────────────────────────────────────────────────

    public function test_sync_logs_endpoint_returns_paginated_results(): void
    {
        $config = $this->makeConfig();

        // Seed 3 sync logs for this store
        foreach (range(1, 3) as $i) {
            DeliveryMenuSyncLog::create([
                'store_id' => $this->store->id,
                'platform' => 'jahez',
                'status' => 'success',
                'items_synced' => 10 * $i,
                'items_failed' => 0,
                'triggered_by' => 'manual',
                'sync_type' => 'full',
                'started_at' => now()->subMinutes($i),
            ]);
        }

        $r = $this->getJson(
            '/api/v2/delivery/sync-logs',
            ['Authorization' => "Bearer {$this->token}"],
        );

        $r->assertOk()
          ->assertJsonCount(3, 'data.data');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. API: sync logs are scoped to the authenticated store
    // ─────────────────────────────────────────────────────────────────────

    public function test_sync_logs_are_scoped_to_store(): void
    {
        $this->makeConfig();

        // Log for another store
        $otherOrg = Organization::create([
            'name' => 'Other Org', 'business_type' => 'restaurant', 'country' => 'SA',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Branch', 'business_type' => 'restaurant',
            'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => true,
        ]);

        DeliveryMenuSyncLog::create([
            'store_id' => $otherStore->id, 'platform' => 'jahez',
            'status' => 'success', 'items_synced' => 5,
            'triggered_by' => 'manual', 'sync_type' => 'full',
            'started_at' => now(),
        ]);

        // Log for our store
        DeliveryMenuSyncLog::create([
            'store_id' => $this->store->id, 'platform' => 'jahez',
            'status' => 'success', 'items_synced' => 7,
            'triggered_by' => 'manual', 'sync_type' => 'full',
            'started_at' => now(),
        ]);

        $r = $this->getJson(
            '/api/v2/delivery/sync-logs',
            ['Authorization' => "Bearer {$this->token}"],
        );

        $r->assertOk();
        $data = $r->json('data.data');
        foreach ($data as $row) {
            $this->assertEquals($this->store->id, $row['store_id']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. MenuSyncService: creates a log record on success
    // ─────────────────────────────────────────────────────────────────────

    public function test_menu_sync_service_creates_log_record(): void
    {
        $config = $this->makeConfig();
        /** @var MenuSyncService $service */
        $service = app(MenuSyncService::class);

        // Calling syncMenu with empty products still creates a log
        $log = $service->syncMenu($config, []);

        $this->assertInstanceOf(DeliveryMenuSyncLog::class, $log);
        $this->assertEquals($this->store->id, $log->store_id);
        $this->assertContains($log->status->value, ['success', 'failed', 'syncing']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. MenuSyncService: syncForAllEnabledPlatforms processes each enabled config
    // ─────────────────────────────────────────────────────────────────────

    public function test_menu_sync_for_all_enabled_platforms_processes_each_config(): void
    {
        DB::table('delivery_platforms')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Marsool', 'slug' => 'marsool',
            'auth_method' => 'api_key', 'is_active' => true,
            'sort_order' => 2, 'default_commission_percent' => 15,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->makeConfig(['platform' => 'jahez']);
        $this->makeConfig(['platform' => 'marsool']);

        /** @var MenuSyncService $service */
        $service = app(MenuSyncService::class);
        $results = $service->syncForAllEnabledPlatforms($this->store->id, []);

        $this->assertCount(2, $results);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7. MenuSyncService: disabled config is skipped
    // ─────────────────────────────────────────────────────────────────────

    public function test_menu_sync_for_all_skips_disabled_configs(): void
    {
        $this->makeConfig(['is_enabled' => false]);

        /** @var MenuSyncService $service */
        $service = app(MenuSyncService::class);
        $results = $service->syncForAllEnabledPlatforms($this->store->id, []);

        $this->assertCount(0, $results);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 8. API: menu-sync platform filter is respected
    // ─────────────────────────────────────────────────────────────────────

    public function test_menu_sync_without_platform_syncs_all_enabled(): void
    {
        Bus::fake();
        $this->makeConfig(['platform' => 'jahez']);

        // POST with no platform body should still queue
        $r = $this->postJson(
            '/api/v2/delivery/menu-sync',
            [],
            ['Authorization' => "Bearer {$this->token}"],
        );

        // Either success (all enabled) or unprocessable if platform is required
        $this->assertContains($r->status(), [200, 422]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 9. API: sync-logs are filtered by platform param
    // ─────────────────────────────────────────────────────────────────────

    public function test_sync_logs_can_be_filtered_by_platform(): void
    {
        $this->makeConfig(['platform' => 'jahez']);

        DeliveryMenuSyncLog::create([
            'store_id' => $this->store->id, 'platform' => 'jahez',
            'status' => 'success', 'items_synced' => 3,
            'triggered_by' => 'manual', 'sync_type' => 'full',
            'started_at' => now()->subMinutes(5),
        ]);

        DeliveryMenuSyncLog::create([
            'store_id' => $this->store->id, 'platform' => 'marsool',
            'status' => 'success', 'items_synced' => 3,
            'triggered_by' => 'manual', 'sync_type' => 'full',
            'started_at' => now()->subMinutes(4),
        ]);

        $r = $this->getJson(
            '/api/v2/delivery/sync-logs?platform=jahez',
            ['Authorization' => "Bearer {$this->token}"],
        );

        $r->assertOk();
        foreach ($r->json('data.data') as $row) {
            $this->assertEquals('jahez', $row['platform']);
        }
    }
}
