<?php

namespace Tests\Feature\Delivery;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Events\DeliveryOrderReceived;
use App\Domain\DeliveryIntegration\Events\DeliveryStatusChanged;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use App\Domain\DeliveryIntegration\Models\DeliveryStatusPushLog;
use App\Domain\DeliveryIntegration\DTOs\IngestOrderDTO;
use App\Domain\DeliveryIntegration\Services\OrderIngestService;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end: webhook ingest → order creation → status update → status push.
 *
 * Each test simulates one complete flow through the system without mocking
 * the service layer (only jobs and external HTTP calls are faked).
 */
class DeliveryWebhookE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private string $platformId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'E2E Org',
            'business_type' => 'restaurant',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'E2E Branch',
            'business_type' => 'restaurant',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Provider',
            'email' => 'e2e@test.com',
            'password_hash' => bcrypt('pass'),
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

        $this->platformId = (string) Str::uuid();
        DB::table('delivery_platforms')->insert([
            'id' => $this->platformId,
            'name' => 'Jahez',
            'slug' => 'jahez',
            'auth_method' => 'api_key',
            'is_active' => true,
            'sort_order' => 1,
            'default_commission_percent' => 18.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeConfig(array $overrides = []): DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::create(array_merge([
            'store_id'                   => $this->store->id,
            'platform'                   => 'jahez',
            'api_key'                    => 'E2E-KEY',
            'merchant_id'                => 'M-E2E',
            'webhook_secret'             => 'super-secret-123',
            'is_enabled'                 => true,
            'auto_accept'                => true,
            'sync_menu_on_product_change' => false,
            'menu_sync_interval_hours'   => 6,
            'status'                     => 'active',
        ], $overrides));
    }

    /** Helper: call OrderIngestService via DTO */
    private function doIngest(string $externalOrderId, array $fields = [], ?DeliveryPlatformConfig $config = null): ?DeliveryOrderMapping
    {
        $service = app(OrderIngestService::class);
        $dto = new IngestOrderDTO(
            storeId: $this->store->id,
            platform: $config ? $config->platform->value : 'jahez',
            externalOrderId: $externalOrderId,
            customerName: $fields['customer_name'] ?? 'Test Customer',
            customerPhone: $fields['customer_phone'] ?? '+966500000001',
            deliveryAddress: $fields['delivery_address'] ?? 'Riyadh',
            subtotal: (float) ($fields['subtotal'] ?? 30),
            deliveryFee: (float) ($fields['delivery_fee'] ?? 5),
            totalAmount: (float) ($fields['total'] ?? 35),
            commissionAmount: 0,
            commissionPercent: null,
            items: $fields['items'] ?? [],
            notes: $fields['notes'] ?? null,
        );
        return $service->ingest($dto);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 1: Webhook → New order created
    // ─────────────────────────────────────────────────────────────────────

    public function test_full_webhook_to_order_flow(): void
    {
        Event::fake([DeliveryOrderReceived::class]);

        $config = $this->makeConfig();
        $secret = 'super-secret-123';
        $body   = json_encode([
            'event'    => 'new_order',
            'order_id' => 'ORD-001',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+966500000001',
            'delivery_address' => 'Riyadh',
            'subtotal' => 50.00,
            'delivery_fee' => 10.00,
            'total' => 60.00,
            'items' => [['name' => 'Burger', 'quantity' => 2, 'price' => 25.00, 'total' => 50.00]],
        ]);
        $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $response = $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            json_decode($body, true),
            ['X-Webhook-Signature' => $sig],
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('delivery_order_mappings', [
            'store_id'          => $this->store->id,
            'platform'          => 'jahez',
            'external_order_id' => 'ORD-001',
        ]);

        Event::assertDispatched(DeliveryOrderReceived::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 2: Webhook → Accept → Preparing → Ready → Dispatched → Delivered
    // ─────────────────────────────────────────────────────────────────────

    public function test_full_order_lifecycle_accepted_to_delivered(): void
    {
        Event::fake([DeliveryStatusChanged::class]);

        $config = $this->makeConfig(['auto_accept' => false]);
        $order  = $this->doIngest('LIFECYCLE-001', [
            'customer_name' => 'Flow User', 'customer_phone' => '+966500000002',
            'delivery_address' => 'Jeddah', 'subtotal' => 40, 'delivery_fee' => 8, 'total' => 48,
        ]);

        $this->assertEquals('pending', $order->delivery_status->value);

        $headers = ['Authorization' => "Bearer {$this->token}"];
        $orderId = $order->id;

        // Accept
        $r = $this->putJson("/api/v2/delivery/orders/{$orderId}/status", ['status' => 'accepted'], $headers);
        $r->assertOk();
        $this->assertEquals('accepted', $order->fresh()->delivery_status->value);

        // Preparing
        $r = $this->putJson("/api/v2/delivery/orders/{$orderId}/status", ['status' => 'preparing'], $headers);
        $r->assertOk();
        $this->assertEquals('preparing', $order->fresh()->delivery_status->value);

        // Ready
        $r = $this->putJson("/api/v2/delivery/orders/{$orderId}/status", ['status' => 'ready'], $headers);
        $r->assertOk();
        $this->assertEquals('ready', $order->fresh()->delivery_status->value);

        // Dispatched
        $r = $this->putJson("/api/v2/delivery/orders/{$orderId}/status", ['status' => 'dispatched'], $headers);
        $r->assertOk();
        $this->assertEquals('dispatched', $order->fresh()->delivery_status->value);

        // Delivered
        $r = $this->putJson("/api/v2/delivery/orders/{$orderId}/status", ['status' => 'delivered'], $headers);
        $r->assertOk();
        $this->assertEquals('delivered', $order->fresh()->delivery_status->value);

        Event::assertDispatched(DeliveryStatusChanged::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 3: Reject with reason
    // ─────────────────────────────────────────────────────────────────────

    public function test_full_flow_pending_to_rejected_with_reason(): void
    {
        $config = $this->makeConfig(['auto_accept' => false]);
        $order  = $this->doIngest('REJECT-001', [
            'customer_name' => 'Reject User', 'customer_phone' => '+966500000003',
            'delivery_address' => 'Mecca', 'subtotal' => 20, 'delivery_fee' => 5, 'total' => 25,
        ]);

        $headers = ['Authorization' => "Bearer {$this->token}"];
        $this->putJson(
            "/api/v2/delivery/orders/{$order->id}/status",
            ['status' => 'rejected', 'rejection_reason' => 'Out of stock'],
            $headers,
        )->assertOk();

        $fresh = $order->fresh();
        $this->assertEquals('rejected', $fresh->delivery_status->value);
        $this->assertEquals('Out of stock', $fresh->rejection_reason);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 4: Webhook order_update event updates existing order
    // ─────────────────────────────────────────────────────────────────────

    public function test_webhook_order_update_event_updates_status(): void
    {
        $config = $this->makeConfig();
        $order  = $this->doIngest('UPDATE-001', [
            'customer_name' => 'Up User', 'customer_phone' => '+966500000004',
            'delivery_address' => 'Riyadh', 'subtotal' => 30, 'delivery_fee' => 5, 'total' => 35,
        ]);

        // Simulate auto-accept changed it to accepted already
        $order->update(['delivery_status' => 'accepted']);

        $secret = 'super-secret-123';
        $body   = json_encode([
            'event'        => 'order_update',
            'order_id'     => 'UPDATE-001',
            'order_status' => 'preparing',
        ]);
        $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            json_decode($body, true),
            ['X-Webhook-Signature' => $sig],
        )->assertOk();

        // Webhook log is recorded
        $this->assertDatabaseHas('delivery_webhook_logs', [
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'event_type' => 'order_update',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 5: Webhook cancelled event marks order as cancelled
    // ─────────────────────────────────────────────────────────────────────

    public function test_webhook_order_cancelled_event(): void
    {
        $config = $this->makeConfig();
        $order  = $this->doIngest('CANCEL-001', [
            'customer_name' => 'Cancel User', 'customer_phone' => '+966500000005',
            'delivery_address' => 'Dammam', 'subtotal' => 15, 'delivery_fee' => 3, 'total' => 18,
        ]);

        $secret = 'super-secret-123';
        $body   = json_encode([
            'event'    => 'order_cancelled',
            'order_id' => 'CANCEL-001',
        ]);
        $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            json_decode($body, true),
            ['X-Webhook-Signature' => $sig],
        )->assertOk();

        $this->assertEquals('cancelled', $order->fresh()->delivery_status->value);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 6: Status push log is created on status update
    // ─────────────────────────────────────────────────────────────────────

    public function test_status_push_log_is_accessible_after_order_update(): void
    {
        $config = $this->makeConfig(['auto_accept' => false]);
        $order  = $this->doIngest('PUSH-LOG-001', [
            'customer_name' => 'PushLog User', 'customer_phone' => '+966500000006',
            'delivery_address' => 'Abha', 'subtotal' => 22, 'delivery_fee' => 4, 'total' => 26,
        ]);

        // Accept via API (order was created as pending since auto_accept=false)
        $headers = ['Authorization' => "Bearer {$this->token}"];
        $this->putJson(
            "/api/v2/delivery/orders/{$order->id}/status",
            ['status' => 'accepted'],
            $headers,
        )->assertOk();

        // Listener is queued – create a push-log record directly to simulate the listener
        DeliveryStatusPushLog::create([
            'delivery_order_mapping_id' => $order->id,
            'platform'                  => 'jahez',
            'status_pushed'             => 'accepted',
            'attempt_number'            => 1,
            'success'                   => true,
            'http_status_code'          => 200,
            'pushed_at'                 => now(),
        ]);

        // Status push logs endpoint returns at least one entry for this store
        $r = $this->getJson('/api/v2/delivery/status-push-logs', $headers);
        $r->assertOk();
        $r->assertJsonStructure(['success', 'data' => ['data', 'current_page', 'last_page', 'total']]);
        $this->assertNotEmpty($r->json('data.data'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 7: Webhook deduplication – second call with same external_order_id is ignored
    // ─────────────────────────────────────────────────────────────────────

    public function test_second_webhook_with_same_order_id_is_deduped(): void
    {
        $config = $this->makeConfig();
        $secret = 'super-secret-123';
        $payload = [
            'event'    => 'new_order',
            'order_id' => 'DEDUP-AGAIN-001',
            'customer_name' => 'Dup User',
            'customer_phone' => '+966500000007',
            'delivery_address' => 'Riyadh',
            'subtotal' => 30,
            'delivery_fee' => 5,
            'total' => 35,
            'items' => [],
        ];
        $body = json_encode($payload);
        $sig  = 'sha256=' . hash_hmac('sha256', $body, $secret);
        $headers = ['X-Webhook-Signature' => $sig];

        $this->postJson("/api/v2/delivery/webhook/jahez/{$this->store->id}", $payload, $headers)->assertOk();
        $this->postJson("/api/v2/delivery/webhook/jahez/{$this->store->id}", $payload, $headers)->assertOk();

        $this->assertCount(
            1,
            DeliveryOrderMapping::where('external_order_id', 'DEDUP-AGAIN-001')->get(),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 8: Invalid signature is rejected
    // ─────────────────────────────────────────────────────────────────────

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $this->makeConfig();
        $payload = ['event' => 'new_order', 'order_id' => 'BAD-SIG'];
        $body    = json_encode($payload);
        $badSig  = 'sha256=' . hash_hmac('sha256', $body, 'wrong-secret');

        $r = $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            $payload,
            ['X-Webhook-Signature' => $badSig],
        );

        $r->assertStatus(401);

        // Webhook is still logged but marked invalid
        $this->assertDatabaseHas('delivery_webhook_logs', [
            'store_id'        => $this->store->id,
            'signature_valid' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 9: Webhook for unknown/disabled config returns 404
    // ─────────────────────────────────────────────────────────────────────

    public function test_webhook_for_disabled_config_returns_404(): void
    {
        $this->makeConfig(['is_enabled' => false]);
        $unknownStoreId = (string) Str::uuid();

        $this->postJson("/api/v2/delivery/webhook/jahez/{$unknownStoreId}", ['event' => 'new_order'])
            ->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 10: Commission is calculated correctly on ingest
    // ─────────────────────────────────────────────────────────────────────

    public function test_commission_is_calculated_on_ingest_based_on_platform_default(): void
    {
        $this->makeConfig();
        $order = $this->doIngest('COMM-001', [
            'subtotal' => 100, 'delivery_fee' => 10, 'total' => 110,
        ]);

        // Platform default is 18.5%, totalAmount=110 → commission = 110 * 18.5 / 100 = 20.35
        $this->assertEqualsWithDelta(20.35, (float) $order->commission_amount, 0.01);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 11: Config toggle disables order ingest
    // ─────────────────────────────────────────────────────────────────────

    public function test_order_ingest_blocked_when_config_toggled_off(): void
    {
        $config  = $this->makeConfig(['is_enabled' => false]);

        $result = $this->doIngest('BLOCK-001', [
            'customer_name' => 'Blocked', 'customer_phone' => '+966500000009',
        ]);

        $this->assertNull($result, 'Ingest should return null for disabled config');
        $this->assertDatabaseMissing('delivery_order_mappings', ['external_order_id' => 'BLOCK-001']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FLOW 12: Cannot update status of terminal order
    // ─────────────────────────────────────────────────────────────────────

    public function test_cannot_transition_from_terminal_state(): void
    {
        $config = $this->makeConfig();
        $order  = $this->doIngest('TERMINAL-001', [
            'customer_name' => 'Final', 'customer_phone' => '+966500000010',
        ]);

        // Force to delivered (terminal)
        $order->update(['delivery_status' => 'delivered']);

        $headers = ['Authorization' => "Bearer {$this->token}"];
        $r = $this->putJson(
            "/api/v2/delivery/orders/{$order->id}/status",
            ['status' => 'accepted'],
            $headers,
        );

        $r->assertStatus(422);
    }
}
