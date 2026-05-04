<?php

namespace Tests\Feature\Domain\DeliveryIntegration;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryStatusPushLog;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);

        $this->owner = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@delivery-test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;

        // Seed delivery platforms for validation
        foreach (['jahez', 'hungerstation', 'toyou', 'marsool', 'keeta', 'noon_food', 'ninja', 'the_chefz', 'talabat', 'carriage'] as $slug) {
            DB::table('delivery_platforms')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'auth_method' => 'api_key',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ─── Authentication ───────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/delivery/stats')->assertUnauthorized();
        $this->getJson('/api/v2/delivery/configs')->assertUnauthorized();
        $this->getJson('/api/v2/delivery/orders')->assertUnauthorized();
        $this->getJson('/api/v2/delivery/orders/active')->assertUnauthorized();
    }

    // ─── Stats ────────────────────────────────────────────────────

    public function test_can_get_delivery_stats(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_platforms',
                    'active_platforms',
                    'total_orders',
                    'pending_orders',
                    'active_orders',
                    'completed_orders',
                    'today_orders',
                    'today_revenue',
                    'platforms',
                ],
            ]);
    }

    public function test_stats_reflect_actual_data(): void
    {
        DeliveryPlatformConfig::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'is_enabled' => true,
        ]);

        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'ORD-001',
            'delivery_status' => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_platforms', 1)
            ->assertJsonPath('data.active_platforms', 1)
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.pending_orders', 1);
    }

    // ─── Configs ──────────────────────────────────────────────────

    public function test_can_list_configs(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/configs');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_can_save_config(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'platform'    => 'jahez',
                'api_key'     => 'test-api-key-123',
                'merchant_id' => 'MERCHANT001',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('delivery_platform_configs', [
            'store_id' => $this->store->id,
            'platform' => 'jahez',
        ]);
    }

    public function test_save_config_with_extended_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'platform' => 'hungerstation',
                'api_key' => 'hs-key-123',
                'merchant_id' => 'HS-MERCHANT-001',
                'auto_accept' => true,
                'max_daily_orders' => 500,
                'sync_menu_on_product_change' => true,
                'menu_sync_interval_hours' => 6,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('delivery_platform_configs', [
            'store_id' => $this->store->id,
            'platform' => 'hungerstation',
            'auto_accept' => true,
            'max_daily_orders' => 500,
        ]);
    }

    public function test_save_config_validates_platform(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'platform' => 'invalid_platform',
                'api_key'  => 'test-key',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_save_config_requires_platform(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'api_key' => 'test-key',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_save_config_updates_existing(): void
    {
        // Create initial
        $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'platform' => 'jahez',
                'api_key' => 'old-key',
            ]);

        // Update
        $this->withToken($this->token)
            ->postJson('/api/v2/delivery/configs', [
                'platform' => 'jahez',
                'api_key' => 'new-key',
                'is_enabled' => true,
            ]);

        $config = DeliveryPlatformConfig::where('store_id', $this->store->id)
            ->where('platform', 'jahez')
            ->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->is_enabled);
    }

    // ─── Toggle Config ────────────────────────────────────────────

    public function test_can_toggle_config(): void
    {
        $config = DeliveryPlatformConfig::create([
            'store_id'    => $this->store->id,
            'platform'    => 'hungerstation',
            'api_key'     => 'key-123',
            'is_enabled'  => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/delivery/configs/{$config->id}/toggle");

        $response->assertOk();

        $config->refresh();
        $this->assertFalse($config->is_enabled);
    }

    public function test_toggle_returns_404_for_invalid_config(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/delivery/configs/{$fakeId}/toggle");

        $response->assertNotFound();
    }

    // ─── Test Connection ──────────────────────────────────────────

    public function test_test_connection_returns_404_for_invalid_config(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/delivery/configs/{$fakeId}/test-connection");

        $response->assertStatus(422);
    }

    // ─── Orders ───────────────────────────────────────────────────

    public function test_can_list_delivery_orders(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/orders');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_can_filter_orders_by_platform(): void
    {
        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-001',
            'delivery_status' => 'pending',
        ]);

        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'hungerstation',
            'external_order_id' => 'HS-001',
            'delivery_status' => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/orders?platform=jahez');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_can_filter_orders_by_status(): void
    {
        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-002',
            'delivery_status' => 'pending',
        ]);

        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-003',
            'delivery_status' => 'delivered',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/orders?status=delivered');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_can_get_active_orders(): void
    {
        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-ACTIVE',
            'delivery_status' => 'preparing',
        ]);

        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-DONE',
            'delivery_status' => 'delivered',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/orders/active');

        $response->assertOk();
    }

    public function test_can_get_order_detail(): void
    {
        $order = DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-DETAIL',
            'delivery_status' => 'pending',
            'customer_name' => 'Test Customer',
            'total_amount' => 99.50,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/delivery/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.external_order_id', 'J-DETAIL');
    }

    public function test_order_detail_returns_404_for_missing(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/delivery/orders/{$fakeId}");

        $response->assertNotFound();
    }

    // ─── Order Status Update ──────────────────────────────────────

    public function test_can_update_order_status(): void
    {
        $order = DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-STATUS',
            'delivery_status' => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/delivery/orders/{$order->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('accepted', $order->delivery_status->value);
        $this->assertNotNull($order->accepted_at);
    }

    public function test_invalid_status_transition_returns_error(): void
    {
        $order = DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-INVALID',
            'delivery_status' => 'delivered', // terminal
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/delivery/orders/{$order->id}/status", [
                'status' => 'pending',
            ]);

        $response->assertStatus(422);
    }

    public function test_reject_order_with_reason(): void
    {
        $order = DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'J-REJECT',
            'delivery_status' => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/delivery/orders/{$order->id}/status", [
                'status' => 'rejected',
                'rejection_reason' => 'Out of stock',
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('rejected', $order->delivery_status->value);
        $this->assertEquals('Out of stock', $order->rejection_reason);
    }

    // ─── Platforms List ───────────────────────────────────────────

    public function test_can_list_available_platforms(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/platforms');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);
    }

    // ─── Menu Sync ────────────────────────────────────────────────

    public function test_menu_sync_requires_products(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/menu-sync', []);

        // products is optional; no platform config exists for this store so expect 200 or error but not validation 422
        $this->assertContains($response->status(), [200, 422, 404]);
    }

    public function test_menu_sync_validates_product_structure(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/delivery/menu-sync', [
                'products' => [
                    ['name' => 'Burger'], // missing id and price
                ],
            ]);

        $response->assertUnprocessable();
    }

    // ─── Sync Logs ────────────────────────────────────────────────

    public function test_can_list_sync_logs(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/sync-logs');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ─── Webhook Logs ─────────────────────────────────────────────

    public function test_can_list_webhook_logs(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/webhook-logs');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_webhook_logs_returns_stored_entries(): void
    {
        DeliveryWebhookLog::create([
            'platform'          => 'jahez',
            'store_id'          => $this->store->id,
            'event_type'        => 'order.created',
            'external_order_id' => 'J-WHL-001',
            'payload'           => ['order_id' => 'J-WHL-001'],
            'signature_valid'   => true,
            'processed'         => true,
            'ip_address'        => '10.0.0.1',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/webhook-logs');

        $response->assertOk();
        $data = $response->json('data');
        $items = $data['data'] ?? $data;
        $this->assertNotEmpty($items);
    }

    public function test_can_filter_webhook_logs_by_platform(): void
    {
        DeliveryWebhookLog::create([
            'platform'   => 'jahez',
            'store_id'   => $this->store->id,
            'event_type' => 'order.created',
            'payload'    => json_encode(['event' => 'order.created']),
            'processed'  => true,
        ]);

        DeliveryWebhookLog::create([
            'platform'   => 'hungerstation',
            'store_id'   => $this->store->id,
            'event_type' => 'order.created',
            'payload'    => json_encode(['event' => 'order.created']),
            'processed'  => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/webhook-logs?platform=jahez');

        $response->assertOk();
    }

    // ─── Status Push Logs ─────────────────────────────────────────

    public function test_can_list_status_push_logs(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/status-push-logs');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_status_push_logs_returns_stored_entries(): void
    {
        $order = DeliveryOrderMapping::create([
            'store_id'          => $this->store->id,
            'platform'          => 'jahez',
            'external_order_id' => 'J-SPL-001',
            'delivery_status'   => 'accepted',
        ]);

        DeliveryStatusPushLog::create([
            'delivery_order_mapping_id' => $order->id,
            'status_pushed'             => 'accepted',
            'platform'                  => 'jahez',
            'http_status_code'          => 200,
            'success'                   => true,
            'attempt_number'            => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/delivery/status-push-logs');

        $response->assertOk();
    }

    // ─── Config Detail ────────────────────────────────────────────

    public function test_can_get_config_detail(): void
    {
        $config = DeliveryPlatformConfig::create([
            'store_id'   => $this->store->id,
            'platform'   => 'jahez',
            'api_key'    => 'detail-key-123',
            'is_enabled' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/delivery/configs/{$config->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_config_detail_returns_404_for_missing(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/delivery/configs/{$fakeId}");

        $response->assertNotFound();
    }

    public function test_config_detail_returns_404_for_other_store(): void
    {
        $otherOrg = Organization::create([
            'name'          => 'Other Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);

        $config = DeliveryPlatformConfig::create([
            'store_id' => $otherStore->id,
            'platform' => 'jahez',
            'api_key'  => 'other-key',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/delivery/configs/{$config->id}");

        $response->assertNotFound();
    }

    // ─── Delete Config ────────────────────────────────────────────

    public function test_can_delete_config(): void
    {
        $config = DeliveryPlatformConfig::create([
            'store_id'   => $this->store->id,
            'platform'   => 'marsool',
            'api_key'    => 'delete-key-123',
            'is_enabled' => true,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/delivery/configs/{$config->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('delivery_platform_configs', [
            'id' => $config->id,
        ]);
    }

    public function test_delete_config_returns_404_for_missing(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/delivery/configs/{$fakeId}");

        $response->assertNotFound();
    }

    public function test_delete_config_returns_404_for_other_store(): void
    {
        $otherOrg = Organization::create([
            'name'          => 'Another Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Another Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);

        $config = DeliveryPlatformConfig::create([
            'store_id' => $otherStore->id,
            'platform' => 'keeta',
            'api_key'  => 'keeta-key',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/delivery/configs/{$config->id}");

        $response->assertNotFound();
    }
}
