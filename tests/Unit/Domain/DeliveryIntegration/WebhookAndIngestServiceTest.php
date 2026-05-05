<?php

namespace Tests\Unit\Domain\DeliveryIntegration;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\DTOs\IngestOrderDTO;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use App\Domain\DeliveryIntegration\Services\OrderIngestService;
use App\Domain\DeliveryIntegration\Services\WebhookVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for WebhookVerificationService and OrderIngestService.
 */
class WebhookAndIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private DeliveryPlatformConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'WH Org', 'business_type' => 'restaurant', 'country' => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'WH Branch', 'business_type' => 'restaurant',
            'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => true,
        ]);

        DB::table('delivery_platforms')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Jahez', 'slug' => 'jahez',
            'auth_method' => 'api_key', 'is_active' => true,
            'sort_order' => 1, 'default_commission_percent' => 18.5,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->config = DeliveryPlatformConfig::create([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'api_key' => 'VERIFY-KEY',
            'merchant_id' => 'M-VERIFY',
            'webhook_secret' => 'my-secret',
            'is_enabled' => true,
            'auto_accept' => true,
            'sync_menu_on_product_change' => false,
            'menu_sync_interval_hours' => 6,
            'status' => 'active',
        ]);
    }

    // ─── WebhookVerificationService ───────────────────────────────────────

    public function test_verify_returns_true_for_valid_sha256_signature(): void
    {
        $service = new WebhookVerificationService();
        $body    = '{"event":"new_order","order_id":"HS-001"}';
        $sig     = 'sha256=' . hash_hmac('sha256', $body, 'my-secret');

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $sig);

        $this->assertTrue($service->verify($request, $this->config));
    }

    public function test_verify_returns_true_for_raw_hash_without_prefix(): void
    {
        $service = new WebhookVerificationService();
        $body    = '{"event":"new_order"}';
        $sig     = hash_hmac('sha256', $body, 'my-secret');  // no prefix

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $sig);

        $this->assertTrue($service->verify($request, $this->config));
    }

    public function test_verify_returns_false_for_wrong_secret(): void
    {
        $service = new WebhookVerificationService();
        $body    = '{"event":"new_order"}';
        $sig     = 'sha256=' . hash_hmac('sha256', $body, 'wrong-secret');

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $sig);

        $this->assertFalse($service->verify($request, $this->config));
    }

    public function test_verify_returns_false_when_no_signature_header(): void
    {
        $service = new WebhookVerificationService();
        $body    = '{"event":"new_order"}';

        $request = Request::create('/', 'POST', [], [], [], [], $body);

        $this->assertFalse($service->verify($request, $this->config));
    }

    public function test_verify_returns_true_when_no_secret_configured(): void
    {
        $this->config->update(['webhook_secret' => null]);
        $service = new WebhookVerificationService();
        $request = Request::create('/', 'POST', [], [], [], [], '{}');

        $this->assertTrue($service->verify($request, $this->config));
    }

    public function test_verify_accepts_x_hub_signature_256_header(): void
    {
        $service = new WebhookVerificationService();
        $body    = '{"event":"new_order"}';
        $sig     = 'sha256=' . hash_hmac('sha256', $body, 'my-secret');

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Hub-Signature-256', $sig);

        $this->assertTrue($service->verify($request, $this->config));
    }

    public function test_verify_accepts_x_signature_header(): void
    {
        $service = new WebhookVerificationService();
        $body    = '{"event":"new_order"}';
        $sig     = 'sha256=' . hash_hmac('sha256', $body, 'my-secret');

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Signature', $sig);

        $this->assertTrue($service->verify($request, $this->config));
    }

    public function test_log_webhook_creates_record(): void
    {
        $service = new WebhookVerificationService();
        $request = Request::create('/', 'POST', ['event' => 'new_order']);

        $log = $service->logWebhook($request, 'jahez', $this->store->id, true, 'new_order');

        $this->assertInstanceOf(DeliveryWebhookLog::class, $log);
        $this->assertEquals('jahez', $log->platform);
        $this->assertEquals($this->store->id, $log->store_id);
        $this->assertTrue($log->signature_valid);
        $this->assertFalse($log->processed);
    }

    public function test_mark_processed_updates_log(): void
    {
        $service = new WebhookVerificationService();
        $request = Request::create('/', 'POST', []);
        $log     = $service->logWebhook($request, 'jahez', $this->store->id, true, 'new_order');

        $service->markProcessed($log, true);
        $fresh = $log->fresh();

        $this->assertTrue($fresh->processed);
        $this->assertEquals('success', $fresh->processing_result);
    }

    public function test_mark_processed_failure_stores_error_message(): void
    {
        $service = new WebhookVerificationService();
        $request = Request::create('/', 'POST', []);
        $log     = $service->logWebhook($request, 'jahez', $this->store->id, true, 'new_order');

        $service->markProcessed($log, false, 'Something went wrong');
        $fresh = $log->fresh();

        $this->assertTrue($fresh->processed); // markProcessed always sets processed=true
        $this->assertEquals('failed', $fresh->processing_result);
        $this->assertEquals('Something went wrong', $fresh->error_message);
    }

    public function test_log_webhook_redacts_authorization_header(): void
    {
        $service = new WebhookVerificationService();
        $request = Request::create('/', 'POST', []);
        $request->headers->set('Authorization', 'Bearer super-secret-token');

        $log = $service->logWebhook($request, 'jahez', $this->store->id, true, 'new_order');

        $headers = is_array($log->headers) ? $log->headers : json_decode($log->headers, true);
        $this->assertNotEquals(['Bearer super-secret-token'], $headers['authorization'] ?? null);
    }

    // ─── OrderIngestService ───────────────────────────────────────────────

    private function buildDto(string $externalOrderId, array $fields = []): IngestOrderDTO
    {
        return new IngestOrderDTO(
            storeId: $this->store->id,
            platform: 'jahez',
            externalOrderId: $externalOrderId,
            customerName: $fields['customer_name'] ?? 'Unit Customer',
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
    }

    public function test_ingest_creates_order_mapping(): void
    {
        $service = app(OrderIngestService::class);
        $order   = $service->ingest($this->buildDto('UNIT-001'));

        $this->assertInstanceOf(DeliveryOrderMapping::class, $order);
        $this->assertEquals('jahez', $order->platform->value);
        $this->assertEquals('UNIT-001', $order->external_order_id);
        $this->assertEquals($this->store->id, $order->store_id);
    }

    public function test_ingest_with_auto_accept_sets_accepted_status(): void
    {
        $this->config->update(['auto_accept' => true]);

        $service = app(OrderIngestService::class);
        $order   = $service->ingest($this->buildDto('AA-001'));

        $this->assertEquals('accepted', $order->delivery_status->value);
    }

    public function test_ingest_without_auto_accept_sets_pending_status(): void
    {
        $this->config->update(['auto_accept' => false]);

        $service = app(OrderIngestService::class);
        $order   = $service->ingest($this->buildDto('PENDING-001'));

        $this->assertEquals('pending', $order->delivery_status->value);
    }

    public function test_ingest_deduplicates_same_external_order_id(): void
    {
        $service = app(OrderIngestService::class);
        $dto     = $this->buildDto('DUP-UNIT-001');

        $first  = $service->ingest($dto);
        $second = $service->ingest($dto);

        $this->assertEquals($first->id, $second->id);
        $this->assertCount(1, DeliveryOrderMapping::where('external_order_id', 'DUP-UNIT-001')->get());
    }

    public function test_ingest_returns_null_for_disabled_config(): void
    {
        $this->config->update(['is_enabled' => false]);

        $service = app(OrderIngestService::class);
        $result  = $service->ingest($this->buildDto('DISABLED-001'));

        $this->assertNull($result);
    }

    public function test_ingest_calculates_commission_from_platform_default(): void
    {
        $service = app(OrderIngestService::class);
        $order   = $service->ingest($this->buildDto('COMM-UNIT-001', [
            'subtotal' => 100, 'delivery_fee' => 10, 'total' => 110,
        ]));

        // Platform default: 18.5%, totalAmount=110 → 110 * 18.5 / 100 ≈ 20.35
        $this->assertEqualsWithDelta(20.35, (float) $order->commission_amount, 0.01);
    }

    public function test_ingest_respects_daily_order_limit(): void
    {
        $this->config->update(['max_daily_orders' => 1]);
        $service = app(OrderIngestService::class);

        $service->ingest($this->buildDto('LIMIT-UNIT-001'));
        $second = $service->ingest($this->buildDto('LIMIT-UNIT-002'));

        $this->assertNull($second, 'Daily limit should block second order');
    }
}
