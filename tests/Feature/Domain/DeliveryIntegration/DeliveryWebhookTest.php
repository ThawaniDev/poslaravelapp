<?php

namespace Tests\Feature\Domain\DeliveryIntegration;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private DeliveryPlatformConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Webhook Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Webhook Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
        ]);

        $this->config = DeliveryPlatformConfig::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'api_key' => 'test-key',
            'webhook_secret' => 'test-secret-123',
            'is_enabled' => true,
            'auto_accept' => false,
        ]);
    }

    public function test_webhook_returns_404_for_unknown_config(): void
    {
        $response = $this->postJson(
            "/api/v2/delivery/webhook/unknown/{$this->store->id}",
            ['event' => 'new_order'],
        );

        $response->assertNotFound();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['event' => 'new_order', 'order_id' => 'ORD-123']);

        $response = $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            json_decode($payload, true),
            ['X-Webhook-Signature' => 'invalid-signature'],
        );

        $response->assertStatus(401);

        $this->assertDatabaseHas('delivery_webhook_logs', [
            'platform' => 'jahez',
            'store_id' => $this->store->id,
            'signature_valid' => false,
        ]);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $payload = json_encode(['event' => 'new_order', 'order_id' => 'ORD-456']);
        $signature = hash_hmac('sha256', $payload, 'test-secret-123');

        $response = $this->call(
            'POST',
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $payload,
        );

        $response->assertOk();

        $this->assertDatabaseHas('delivery_webhook_logs', [
            'platform' => 'jahez',
            'store_id' => $this->store->id,
            'signature_valid' => true,
        ]);
    }

    public function test_webhook_ingests_new_order(): void
    {
        $orderPayload = [
            'event' => 'new_order',
            'order_id' => 'JAHEZ-789',
            'customer_name' => 'Ahmed',
            'customer_phone' => '+966500000000',
            'address' => 'Riyadh, Saudi Arabia',
            'sub_total' => 45.00,
            'delivery_fee' => 10.00,
            'total' => 55.00,
            'items' => [
                ['name_en' => 'Burger', 'qty' => 2, 'unit_price' => 22.50],
            ],
        ];

        $payload = json_encode($orderPayload);
        $signature = hash_hmac('sha256', $payload, 'test-secret-123');

        $response = $this->call(
            'POST',
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $payload,
        );

        $response->assertOk();

        $this->assertDatabaseHas('delivery_order_mappings', [
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'JAHEZ-789',
            'delivery_status' => 'pending',
        ]);
    }

    public function test_webhook_auto_accepts_when_enabled(): void
    {
        $this->config->update(['auto_accept' => true]);

        $orderPayload = [
            'event' => 'new_order',
            'order_id' => 'JAHEZ-AUTO',
            'customer_name' => 'Sara',
            'total' => 30.00,
            'items' => [
                ['name_en' => 'Salad', 'qty' => 1, 'unit_price' => 30.00],
            ],
        ];

        $payload = json_encode($orderPayload);
        $signature = hash_hmac('sha256', $payload, 'test-secret-123');

        $this->call(
            'POST',
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $payload,
        );

        $this->assertDatabaseHas('delivery_order_mappings', [
            'external_order_id' => 'JAHEZ-AUTO',
            'delivery_status' => 'accepted',
        ]);
    }

    public function test_webhook_prevents_duplicate_orders(): void
    {
        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'JAHEZ-DUPE',
            'delivery_status' => 'pending',
        ]);

        $orderPayload = [
            'event' => 'new_order',
            'order_id' => 'JAHEZ-DUPE',
            'customer_name' => 'Duplicate',
            'total' => 10.00,
            'items' => [],
        ];

        $payload = json_encode($orderPayload);
        $signature = hash_hmac('sha256', $payload, 'test-secret-123');

        $this->call(
            'POST',
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $payload,
        );

        $count = DeliveryOrderMapping::where('external_order_id', 'JAHEZ-DUPE')->count();
        $this->assertEquals(1, $count);
    }

    public function test_webhook_handles_order_cancellation(): void
    {
        DeliveryOrderMapping::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'external_order_id' => 'JAHEZ-CANCEL',
            'delivery_status' => 'preparing',
        ]);

        $cancelPayload = [
            'event' => 'order_cancelled',
            'order_id' => 'JAHEZ-CANCEL',
            'reason' => 'Customer cancelled',
        ];

        $payload = json_encode($cancelPayload);
        $signature = hash_hmac('sha256', $payload, 'test-secret-123');

        $this->call(
            'POST',
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $payload,
        );

        $this->assertDatabaseHas('delivery_order_mappings', [
            'external_order_id' => 'JAHEZ-CANCEL',
            'delivery_status' => 'cancelled',
        ]);
    }

    public function test_webhook_skips_no_secret_verification(): void
    {
        $this->config->update(['webhook_secret' => null]);

        $response = $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            ['event' => 'new_order', 'order_id' => 'NO-SECRET'],
        );

        $response->assertOk();
    }

    public function test_webhook_logs_are_created(): void
    {
        $this->config->update(['webhook_secret' => null]);

        $this->postJson(
            "/api/v2/delivery/webhook/jahez/{$this->store->id}",
            ['event' => 'test_event', 'data' => 'test'],
        );

        $log = DeliveryWebhookLog::where('store_id', $this->store->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('jahez', $log->platform);
        $this->assertTrue($log->signature_valid);
        $this->assertTrue($log->processed);
    }
}
