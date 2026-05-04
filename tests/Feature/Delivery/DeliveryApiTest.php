<?php

namespace Tests\Feature\Delivery;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\DTOs\IngestOrderDTO;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryStatusPushLog;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use App\Domain\DeliveryIntegration\Services\OrderIngestService;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
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
            'name' => 'Delivery Org',
            'business_type' => 'restaurant',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Restaurant Branch',
            'business_type' => 'restaurant',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'owner@delivery.com',
            'password_hash' => bcrypt('secret'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('t', ['*'])->plainTextToken;

        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
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

        // Seed delivery platform registry rows
        $platforms = [
            ['slug' => 'jahez', 'name' => 'Jahez', 'auth_method' => 'api_key', 'default_commission_percent' => 18.5],
            ['slug' => 'hungerstation', 'name' => 'HungerStation', 'auth_method' => 'oauth2', 'default_commission_percent' => 22.0],
            ['slug' => 'marsool', 'name' => 'Marsool', 'auth_method' => 'api_key', 'default_commission_percent' => 15.0],
            ['slug' => 'keeta', 'name' => 'Keeta', 'auth_method' => 'api_key', 'default_commission_percent' => 17.0],
            ['slug' => 'noon_food', 'name' => 'Noon Food', 'auth_method' => 'oauth2', 'default_commission_percent' => 20.0],
        ];
        foreach ($platforms as $p) {
            \Illuminate\Support\Facades\DB::table('delivery_platforms')->insert([
                'id' => (string) Str::uuid(),
                'name' => $p['name'],
                'slug' => $p['slug'],
                'auth_method' => $p['auth_method'],
                'is_active' => true,
                'sort_order' => 1,
                'default_commission_percent' => $p['default_commission_percent'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function makeConfig(array $overrides = []): DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::create(array_merge([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'api_key' => 'TEST-API-KEY',
            'merchant_id' => 'M-100',
            'webhook_secret' => 'wh-secret-123',
            'is_enabled' => true,
            'auto_accept' => true,
            'sync_menu_on_product_change' => true,
            'menu_sync_interval_hours' => 6,
            'status' => 'active',
        ], $overrides));
    }

    // ─── Configs CRUD ─────────────────────────────────────

    public function test_can_list_configs(): void
    {
        $this->makeConfig();
        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/configs');
        $r->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $r->json('data'));
    }

    public function test_can_save_config(): void
    {
        $r = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez',
            'api_key' => 'NEW-KEY',
            'is_enabled' => true,
            'auto_accept' => false,
            'max_daily_orders' => 100,
        ]);

        $r->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('delivery_platform_configs', [
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'auto_accept' => false,
            'max_daily_orders' => 100,
        ]);
    }

    public function test_save_config_rejects_unknown_platform(): void
    {
        $r = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform' => 'unknown_platform',
        ]);
        $r->assertStatus(422);
    }

    public function test_can_toggle_config(): void
    {
        $config = $this->makeConfig(['is_enabled' => true]);
        $r = $this->withToken($this->token)->putJson("/api/v2/delivery/configs/{$config->id}/toggle");
        $r->assertOk();
        $this->assertFalse((bool) $config->fresh()->is_enabled);
    }

    public function test_can_get_config_detail(): void
    {
        $config = $this->makeConfig();
        $r = $this->withToken($this->token)->getJson("/api/v2/delivery/configs/{$config->id}");
        $r->assertOk()->assertJsonPath('success', true);
        // api_key is hidden
        $this->assertArrayNotHasKey('api_key', $r->json('data'));
    }

    public function test_can_delete_config(): void
    {
        $config = $this->makeConfig();
        $r = $this->withToken($this->token)->deleteJson("/api/v2/delivery/configs/{$config->id}");
        $r->assertOk();
        $this->assertDatabaseMissing('delivery_platform_configs', ['id' => $config->id]);
    }

    public function test_delete_returns_404_for_missing_config(): void
    {
        $r = $this->withToken($this->token)->deleteJson('/api/v2/delivery/configs/'.Str::uuid());
        $r->assertStatus(404);
    }

    // ─── Platforms list ───────────────────────────────────

    public function test_list_platforms_returns_active_registry(): void
    {
        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/platforms');
        $r->assertOk();
        $this->assertGreaterThanOrEqual(5, count($r->json('data')));
    }

    // ─── Stats ─────────────────────────────────────────────

    public function test_stats_returns_aggregate(): void
    {
        $this->makeConfig();
        $this->makeOrder(['delivery_status' => 'delivered', 'total_amount' => 50]);
        $this->makeOrder(['delivery_status' => 'pending']);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/stats');
        $r->assertOk();
        $this->assertEquals(1, $r->json('data.active_platforms'));
        $this->assertEquals(2, $r->json('data.total_orders'));
        $this->assertEquals(1, $r->json('data.completed_orders'));
        $this->assertEquals(1, $r->json('data.pending_orders'));
    }

    // ─── Orders ────────────────────────────────────────────

    private function makeOrder(array $overrides = []): DeliveryOrderMapping
    {
        return DeliveryOrderMapping::create(array_merge([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'EXT-'.Str::random(8),
            'delivery_status' => 'pending',
            'customer_name' => 'Ali',
            'customer_phone' => '966500000000',
            'subtotal' => 100,
            'delivery_fee' => 10,
            'total_amount' => 110,
            'items_count' => 2,
            'commission_percent' => 18.5,
            'commission_amount' => 18.5,
        ], $overrides));
    }

    public function test_can_list_orders(): void
    {
        $this->makeOrder();
        $this->makeOrder(['delivery_status' => 'accepted']);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/orders');
        $r->assertOk();
        $this->assertCount(2, $r->json('data.data'));
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        $this->makeOrder(['delivery_status' => 'pending']);
        $this->makeOrder(['delivery_status' => 'delivered']);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/orders?status=delivered');
        $r->assertOk();
        $this->assertCount(1, $r->json('data.data'));
        $this->assertEquals('delivered', $r->json('data.data.0.delivery_status'));
    }

    public function test_can_get_order_detail(): void
    {
        $order = $this->makeOrder();
        $r = $this->withToken($this->token)->getJson("/api/v2/delivery/orders/{$order->id}");
        $r->assertOk()->assertJsonPath('data.id', $order->id);
    }

    public function test_can_update_order_status_to_accepted(): void
    {
        $this->makeConfig();
        $order = $this->makeOrder(['delivery_status' => 'pending']);

        $r = $this->withToken($this->token)->putJson("/api/v2/delivery/orders/{$order->id}/status", [
            'status' => 'accepted',
        ]);
        $r->assertOk();

        $fresh = $order->fresh();
        $this->assertEquals('accepted', $fresh->delivery_status->value);
        $this->assertNotNull($fresh->accepted_at);
    }

    public function test_can_reject_order_with_rejection_reason(): void
    {
        $this->makeConfig();
        $order = $this->makeOrder(['delivery_status' => 'pending']);

        $r = $this->withToken($this->token)->putJson("/api/v2/delivery/orders/{$order->id}/status", [
            'status' => 'rejected',
            'rejection_reason' => 'Out of stock',
        ]);
        $r->assertOk();

        $fresh = $order->fresh();
        $this->assertEquals('rejected', $fresh->delivery_status->value);
        $this->assertEquals('Out of stock', $fresh->rejection_reason);
    }

    public function test_old_reason_key_is_ignored_on_rejection(): void
    {
        $this->makeConfig();
        $order = $this->makeOrder(['delivery_status' => 'pending']);

        // 'reason' is not the correct key — should be 'rejection_reason'
        // The endpoint should still succeed (reason is just ignored) but rejection_reason stays null
        $r = $this->withToken($this->token)->putJson("/api/v2/delivery/orders/{$order->id}/status", [
            'status' => 'rejected',
            'reason' => 'This key should be ignored',
        ]);
        $r->assertOk();
        $fresh = $order->fresh();
        $this->assertEquals('rejected', $fresh->delivery_status->value);
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_save_config_accepts_new_timeout_and_operating_hours_fields(): void
    {
        $hours = [
            ['day_of_week' => 1, 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
            ['day_of_week' => 5, 'open_time' => '12:00', 'close_time' => '23:00', 'is_closed' => false],
        ];

        $r = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez',
            'api_key' => 'KEY-TIMEOUT-TEST',
            'is_enabled' => true,
            'auto_accept' => true,
            'auto_accept_timeout_seconds' => 600,
            'operating_hours_json' => $hours,
        ]);

        $r->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('delivery_platform_configs', [
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'auto_accept_timeout_seconds' => 600,
        ]);

        // Verify operating_hours_json is stored and returned
        $this->assertEquals(600, $r->json('data.auto_accept_timeout_seconds'));
        $this->assertIsArray($r->json('data.operating_hours_json'));
    }

    public function test_save_config_rejects_invalid_timeout_value(): void
    {
        $r = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform' => 'jahez',
            'api_key' => 'KEY-BAD-TIMEOUT',
            'auto_accept_timeout_seconds' => 30, // below minimum of 60
        ]);
        $r->assertStatus(422);
    }

    public function test_active_orders_excludes_terminal_states(): void
    {
        $this->makeOrder(['delivery_status' => 'pending']);
        $this->makeOrder(['delivery_status' => 'accepted']);
        $this->makeOrder(['delivery_status' => 'delivered']);
        $this->makeOrder(['delivery_status' => 'cancelled']);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/orders/active');
        $r->assertOk();
        $statuses = collect($r->json('data'))->pluck('delivery_status')->all();
        $this->assertNotContains('delivered', $statuses);
        $this->assertNotContains('cancelled', $statuses);
    }

    // ─── Order Ingest Service ─────────────────────────────

    public function test_ingest_creates_order_with_commission(): void
    {
        $this->makeConfig(['platform' => 'jahez', 'auto_accept' => true]);

        $dto = new IngestOrderDTO(
            storeId: $this->store->id,
            platform: 'jahez',
            externalOrderId: 'EXT-INGEST-1',
            customerName: 'Sara',
            customerPhone: '966500111222',
            deliveryAddress: 'Riyadh',
            subtotal: 200,
            deliveryFee: 15,
            totalAmount: 215,
            commissionAmount: 0,
            commissionPercent: null,
            items: [['name' => 'Burger', 'qty' => 2, 'price' => 100]],
        );

        $order = app(OrderIngestService::class)->ingest($dto);

        $this->assertNotNull($order);
        $this->assertEquals('accepted', $order->delivery_status->value);
        $this->assertNotNull($order->accepted_at);
        // commission = 215 * 18.5 / 100 = 39.775 → rounded 39.78
        $this->assertEqualsWithDelta(39.78, (float) $order->commission_amount, 0.01);
        $this->assertEquals(18.5, (float) $order->commission_percent);
    }

    public function test_ingest_dedupes_by_external_order_id(): void
    {
        $this->makeConfig();
        $service = app(OrderIngestService::class);

        $dto = new IngestOrderDTO(
            storeId: $this->store->id,
            platform: 'jahez',
            externalOrderId: 'DUP-1',
            customerName: 'X',
            customerPhone: null,
            deliveryAddress: null,
            subtotal: 100,
            deliveryFee: 0,
            totalAmount: 100,
            commissionAmount: 0,
            commissionPercent: null,
            items: [],
        );

        $first = $service->ingest($dto);
        $second = $service->ingest($dto);

        $this->assertNotNull($first);
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, DeliveryOrderMapping::where('external_order_id', 'DUP-1')->count());
    }

    public function test_ingest_rejects_when_daily_limit_reached(): void
    {
        $config = $this->makeConfig(['max_daily_orders' => 2, 'daily_order_count' => 2]);
        $service = app(OrderIngestService::class);

        $dto = new IngestOrderDTO(
            storeId: $this->store->id,
            platform: 'jahez',
            externalOrderId: 'OVER-LIMIT',
            customerName: 'X',
            customerPhone: null,
            deliveryAddress: null,
            subtotal: 100,
            deliveryFee: 0,
            totalAmount: 100,
            commissionAmount: 0,
            commissionPercent: null,
            items: [],
        );

        $result = $service->ingest($dto);
        $this->assertNull($result);
    }

    public function test_ingest_with_manual_accept_leaves_pending(): void
    {
        $this->makeConfig(['auto_accept' => false]);
        $service = app(OrderIngestService::class);

        $dto = new IngestOrderDTO(
            storeId: $this->store->id,
            platform: 'jahez',
            externalOrderId: 'MANUAL-1',
            customerName: 'X',
            customerPhone: null,
            deliveryAddress: null,
            subtotal: 50,
            deliveryFee: 0,
            totalAmount: 50,
            commissionAmount: 0,
            commissionPercent: null,
            items: [],
        );

        $order = $service->ingest($dto);
        $this->assertEquals('pending', $order->delivery_status->value);
        $this->assertNull($order->accepted_at);
    }

    // ─── Webhook ──────────────────────────────────────────

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->makeConfig(['webhook_secret' => 'real-secret']);

        $payload = ['external_order_id' => 'WH-1', 'customer_name' => 'X', 'total' => 100];
        $r = $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            $payload,
            ['X-Webhook-Signature' => 'sha256=DEADBEEF']
        );

        $r->assertStatus(401);
        $this->assertDatabaseHas('delivery_webhook_logs', [
            'platform' => 'jahez',
            'signature_valid' => false,
        ]);
    }

    public function test_webhook_accepts_valid_signature_and_creates_order(): void
    {
        $secret = 'super-secret';
        $this->makeConfig(['webhook_secret' => $secret, 'auto_accept' => true]);

        $payload = [
            'order_id' => 'WH-OK-1',
            'customer_name' => 'Ahmed',
            'customer_phone' => '966500999000',
            'total' => 250,
            'subtotal' => 230,
            'delivery_fee' => 20,
            'items' => [['name' => 'Pizza', 'qty' => 1, 'price' => 230]],
            'event' => 'new_order',
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        $r = $this->call(
            'POST',
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Webhook-Signature' => $signature,
                'HTTP_Accept' => 'application/json',
            ],
            $rawBody
        );

        $r->assertOk();
        $this->assertDatabaseHas('delivery_order_mappings', [
            'external_order_id' => 'WH-OK-1',
            'platform' => 'jahez',
            'delivery_status' => 'accepted',
        ]);
    }

    public function test_webhook_returns_404_for_unknown_config(): void
    {
        $unknownStoreId = (string) Str::uuid();
        $r = $this->postJson("/api/v2/delivery/webhook/jahez/{$unknownStoreId}", []);
        $r->assertStatus(404);
    }

    // ─── Logs endpoints ───────────────────────────────────

    public function test_can_list_sync_logs(): void
    {
        $this->makeConfig();
        \App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'status' => 'success',
            'items_synced' => 25,
            'items_failed' => 0,
            'triggered_by' => 'manual',
            'sync_type' => 'full',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/sync-logs');
        $r->assertOk();
        $this->assertCount(1, $r->json('data.data'));
    }

    public function test_can_list_webhook_logs(): void
    {
        DeliveryWebhookLog::create([
            'platform' => 'jahez',
            'store_id' => $this->store->id,
            'event_type' => 'new_order',
            'payload' => ['x' => 1],
            'signature_valid' => true,
            'processed' => true,
            'processing_result' => 'success',
            'received_at' => now(),
        ]);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/webhook-logs');
        $r->assertOk();
        $this->assertCount(1, $r->json('data.data'));
    }

    public function test_can_list_status_push_logs(): void
    {
        $order = $this->makeOrder();
        DeliveryStatusPushLog::create([
            'delivery_order_mapping_id' => $order->id,
            'platform' => 'jahez',
            'status_pushed' => 'accepted',
            'attempt_number' => 1,
            'success' => true,
            'http_status_code' => 200,
            'pushed_at' => now(),
        ]);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/status-push-logs');
        $r->assertOk();
        $this->assertCount(1, $r->json('data.data'));
    }

    // ─── Auth / subscription gating ───────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $r = $this->getJson('/api/v2/delivery/stats');
        $r->assertStatus(401);
    }

    // ─── Boolean-false persistence regression ─────────────

    public function test_saving_false_values_does_not_strip_them(): void
    {
        // Create a config with boolean fields set to true
        $config = $this->makeConfig([
            'auto_accept' => true,
            'sync_menu_on_product_change' => true,
            'is_enabled' => true,
        ]);

        // Update with all booleans set to false — POST with same platform updates existing
        $r = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform'                  => 'jahez',
            'is_enabled'                => false,
            'auto_accept'               => false,
            'sync_menu_on_product_change' => false,
            'auto_accept_timeout_seconds' => 60,
            'menu_sync_interval_hours'  => 1,
        ]);

        $r->assertOk();
        $config->refresh();
        $this->assertFalse((bool) $config->auto_accept, 'auto_accept should persist as false');
        $this->assertFalse((bool) $config->sync_menu_on_product_change, 'sync_menu_on_product_change should persist as false');
        $this->assertFalse((bool) $config->is_enabled, 'is_enabled should persist as false');
    }

    // ─── Plan limit enforcement ────────────────────────────

    public function test_plan_limit_blocks_creating_too_many_configs(): void
    {
        // Set a limit of 1 delivery integration on the plan
        $plan = \App\Domain\ProviderSubscription\Models\StoreSubscription::where(
            'organization_id', $this->org->id
        )->first()->subscriptionPlan;

        \App\Domain\Subscription\Models\PlanLimit::updateOrCreate(
            ['subscription_plan_id' => $plan->id, 'limit_key' => 'max_delivery_platforms'],
            ['limit_value' => 1]
        );

        // First config should succeed
        $r1 = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform'   => 'jahez',
            'api_key'    => 'KEY-1',
            'is_enabled' => true,
        ]);
        $r1->assertOk();

        // Second config should be blocked
        $r2 = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform'   => 'hungerstation',
            'api_key'    => 'KEY-2',
            'is_enabled' => true,
        ]);
        $r2->assertStatus(403);
        $r2->assertJsonFragment(['success' => false]);
    }

    public function test_update_at_limit_succeeds(): void
    {
        $plan = \App\Domain\ProviderSubscription\Models\StoreSubscription::where(
            'organization_id', $this->org->id
        )->first()->subscriptionPlan;

        \App\Domain\Subscription\Models\PlanLimit::updateOrCreate(
            ['subscription_plan_id' => $plan->id, 'limit_key' => 'max_delivery_platforms'],
            ['limit_value' => 1]
        );

        $config = $this->makeConfig();

        // Updating the existing config (not creating a new one) should succeed even at limit
        $r = $this->withToken($this->token)->postJson('/api/v2/delivery/configs', [
            'platform'   => 'jahez',
            'is_enabled' => false,
            'auto_accept' => false,
        ]);
        $r->assertOk();
    }

    // ─── Platforms endpoint returns fields ─────────────────

    public function test_platforms_endpoint_returns_fields(): void
    {
        // Add a platform field to the jahez platform
        $platform = \App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform
            ::where('slug', 'jahez')->first();

        \Illuminate\Support\Facades\DB::table('delivery_platform_fields')->insert([
            'id'                   => (string) Str::uuid(),
            'delivery_platform_id' => $platform->id,
            'field_key'            => 'api_key',
            'field_label'          => 'API Key',
            'field_type'           => 'password',
            'is_required'          => true,
            'sort_order'           => 1,
        ]);

        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/platforms');
        $r->assertOk();

        $platforms = $r->json('data');
        $jahez = collect($platforms)->firstWhere('slug', 'jahez');

        $this->assertNotNull($jahez, 'jahez platform should be present');
        $this->assertArrayHasKey('fields', $jahez);
        $this->assertCount(1, $jahez['fields']);
        $this->assertEquals('api_key', $jahez['fields'][0]['field_key']);
        $this->assertEquals('password', $jahez['fields'][0]['field_type']);
        $this->assertTrue($jahez['fields'][0]['is_required']);
    }

    public function test_platforms_endpoint_returns_empty_fields_array_when_none_defined(): void
    {
        $r = $this->withToken($this->token)->getJson('/api/v2/delivery/platforms');
        $r->assertOk();

        $platforms = $r->json('data');
        foreach ($platforms as $platform) {
            $slug = $platform['slug'];
            $this->assertArrayHasKey('fields', $platform, "Platform {$slug} must have 'fields' key");
            $this->assertIsArray($platform['fields']);
        }
    }
}
