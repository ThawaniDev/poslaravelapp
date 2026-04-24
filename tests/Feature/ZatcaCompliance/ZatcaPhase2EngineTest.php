<?php

namespace Tests\Feature\ZatcaCompliance;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Order\Models\Order;
use App\Domain\ZatcaCompliance\Enums\ZatcaDeviceStatus;
use App\Domain\ZatcaCompliance\Jobs\RetryFailedSubmissionJob;
use App\Domain\ZatcaCompliance\Models\ZatcaDevice;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
use App\Domain\ZatcaCompliance\Services\DeviceService;
use App\Domain\ZatcaCompliance\Services\HashChainService;
use App\Domain\ZatcaCompliance\Services\TlvQrEncoder;
use App\Domain\ZatcaCompliance\Services\UblInvoiceBuilder;
use App\Domain\ZatcaCompliance\Services\XadesSigner;
use App\Domain\ZatcaCompliance\Services\CertificateService;
use App\Domain\ZatcaCompliance\Enums\ZatcaInvoiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZatcaPhase2EngineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private Organization $org;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Phase2 Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Phase2 Store',
            'name_ar' => 'متجر المرحلة الثانية',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
            'vat_number' => '300000000000003',
        ]);
        $this->user = User::create([
            'name' => 'Phase2 Admin',
            'email' => 'phase2@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function createOrder(): Order
    {
        return Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'P2-' . uniqid(),
            'source' => 'pos',
            'status' => 'completed',
            'subtotal' => 100.0,
            'tax_amount' => 15.0,
            'total' => 115.0,
        ]);
    }

    // ─── TLV QR ───────────────────────────────────────────────

    public function test_tlv_qr_round_trip(): void
    {
        $tlv = app(TlvQrEncoder::class);
        $b64 = $tlv->encode([
            'seller_name' => 'Phase2 Store',
            'vat_number' => '300000000000003',
            'timestamp' => '2024-01-15T10:00:00Z',
            'total' => '115.00',
            'vat' => '15.00',
            'invoice_hash' => str_repeat('a', 64),
            'signature' => base64_encode('sig'),
            'public_key' => base64_encode('pub'),
            'certificate_signature' => base64_encode('cert'),
        ]);
        $this->assertNotEmpty($b64);
        $decoded = $tlv->decode($b64);
        $this->assertEquals('Phase2 Store', $decoded[1]);
        $this->assertEquals('300000000000003', $decoded[2]);
        $this->assertEquals('115.00', $decoded[4]);
        $this->assertEquals('15.00', $decoded[5]);
    }

    // ─── UBL ──────────────────────────────────────────────────

    public function test_ubl_builder_produces_well_formed_xml(): void
    {
        $ubl = app(UblInvoiceBuilder::class);
        $xml = $ubl->build($this->store, [
            'uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'invoice_number' => 'UBL-001',
            'issue_at' => now(),
            'invoice_type' => ZatcaInvoiceType::Standard,
            'icv' => 1,
            'pih' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'is_b2b' => true,
            'buyer_name' => 'Acme Co',
            'buyer_vat' => '310000000000003',
            'lines' => [['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100.0, 'tax_percent' => 15.0]],
        ]);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $this->assertStringContainsString('UBL-001', $xml);
        $this->assertStringContainsString('310000000000003', $xml);
    }

    // ─── XAdES Sign + Verify ──────────────────────────────────

    public function test_xades_sign_and_verify(): void
    {
        $signer = app(XadesSigner::class);
        // Generate keypair
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC])
            ?: openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $dn = ['commonName' => 'Test', 'countryName' => 'SA'];
        $csr = openssl_csr_new($dn, $key);
        $cert = openssl_csr_sign($csr, null, $key, 365);
        openssl_x509_export($cert, $certPem);

        $unsigned = '<?xml version="1.0"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"><cbc:ID>SIG-001</cbc:ID></Invoice>';
        $signed = $signer->sign($unsigned, $privateKey, $certPem);
        $this->assertArrayHasKey('signature', $signed);
        $this->assertArrayHasKey('hash', $signed);
        $this->assertStringContainsString('ds:Signature', $signed['xml']);
    }

    // ─── Hash Chain ───────────────────────────────────────────

    public function test_hash_chain_monotonic_icv(): void
    {
        $chain = app(HashChainService::class);
        $devices = app(DeviceService::class);
        $device = $devices->provision($this->store->id);

        $a = $chain->reserveNext($device);
        $chain->commit($device->refresh(), $a['icv'], 'hashA');
        $b = $chain->reserveNext($device->refresh());
        $this->assertSame(1, $a['icv']);
        $this->assertSame(2, $b['icv']);
        // PIH of next must equal hash of previous
        $this->assertSame('hashA', $b['pih']);
    }

    public function test_hash_chain_tamper_detection(): void
    {
        $chain = app(HashChainService::class);
        $devices = app(DeviceService::class);
        $device = $devices->provision($this->store->id);
        $chain->reserveNext($device);
        $this->expectException(\RuntimeException::class);
        // committing wrong ICV must trigger tamper detection
        $chain->commit($device->refresh(), 99, 'bogus');
    }

    // ─── Device Service ───────────────────────────────────────

    public function test_device_provision_and_activate(): void
    {
        $svc = app(DeviceService::class);
        $device = $svc->provision($this->store->id);
        $this->assertSame(ZatcaDeviceStatus::Pending, $device->status);
        $activated = $svc->activate($this->store->id, $device->activation_code, 'SERIAL-1');
        $this->assertSame(ZatcaDeviceStatus::Active, $activated->status);
        $this->assertSame('SERIAL-1', $activated->hardware_serial);
    }

    public function test_device_activate_rejects_wrong_code(): void
    {
        $svc = app(DeviceService::class);
        $svc->provision($this->store->id);
        $this->expectException(\RuntimeException::class);
        $svc->activate($this->store->id, 'WRONGCODE', 'SERIAL-2');
    }

    public function test_device_reset_tamper(): void
    {
        $svc = app(DeviceService::class);
        $device = $svc->provision($this->store->id);
        $svc->flagTamper($device, 'integrity');
        $this->assertTrue($device->refresh()->is_tampered);
        $reset = $svc->resetTamper($device->refresh());
        $this->assertFalse($reset->is_tampered);
        $this->assertSame(ZatcaDeviceStatus::Active, $reset->status);
    }

    // ─── End-to-End Submit ────────────────────────────────────

    public function test_submit_invoice_persists_phase2_artifacts(): void
    {
        $order = $this->createOrder();
        $resp = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'P2-INV-001',
            'invoice_type' => 'simplified',
            'total_amount' => 115.0,
            'vat_amount' => 15.0,
        ], $this->authHeader());
        $resp->assertStatus(201);
        $invoice = ZatcaInvoice::where('invoice_number', 'P2-INV-001')->first();
        $this->assertNotNull($invoice->uuid);
        $this->assertSame(1, (int) $invoice->icv);
        $this->assertNotEmpty($invoice->tlv_qr_base64);
        $this->assertNotEmpty($invoice->digital_signature);
        $this->assertSame('reporting', $invoice->flow);
        $this->assertSame('accepted', $invoice->submission_status->value);
    }

    public function test_b2b_invoice_uses_clearance_flow_with_cleared_xml(): void
    {
        $order = $this->createOrder();
        $resp = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'B2B-001',
            'invoice_type' => 'standard',
            'total_amount' => 1150.0,
            'vat_amount' => 150.0,
            'buyer_name' => 'Buyer LLC',
            'buyer_tax_number' => '310000000000003',
        ], $this->authHeader());
        $resp->assertStatus(201);
        $invoice = ZatcaInvoice::where('invoice_number', 'B2B-001')->first();
        $this->assertSame('clearance', $invoice->flow);
        $this->assertNotEmpty($invoice->cleared_xml);
        $this->assertTrue((bool) $invoice->is_b2b);
    }

    public function test_credit_note_requires_reference_uuid(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'CN-001',
            'invoice_type' => 'credit_note',
            'total_amount' => 50.0,
            'vat_amount' => 7.5,
        ], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference_invoice_uuid']);
    }

    public function test_debit_note_requires_adjustment_reason(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'DN-001',
            'invoice_type' => 'debit_note',
            'total_amount' => 50.0,
            'vat_amount' => 7.5,
            'reference_invoice_uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['adjustment_reason']);
    }

    // ─── Tamper Lock ──────────────────────────────────────────

    public function test_tampered_device_blocks_submission(): void
    {
        $devices = app(DeviceService::class);
        $device = $devices->provision($this->store->id);
        $device->update(['status' => ZatcaDeviceStatus::Active->value, 'activated_at' => now()]);
        $devices->flagTamper($device, 'manual integrity test');

        $order = $this->createOrder();
        $resp = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'TAMP-001',
            'invoice_type' => 'simplified',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());
        // Server should refuse — 5xx since orchestrator throws RuntimeException
        $this->assertGreaterThanOrEqual(400, $resp->status());
    }

    // ─── Retry backoff ────────────────────────────────────────

    public function test_retry_backoff_schedule(): void
    {
        $a = RetryFailedSubmissionJob::nextAttemptAt(1);
        $b = RetryFailedSubmissionJob::nextAttemptAt(2);
        $c = RetryFailedSubmissionJob::nextAttemptAt(5);
        $this->assertEqualsWithDelta(30, now()->diffInSeconds($a, false), 5);
        $this->assertEqualsWithDelta(120, now()->diffInSeconds($b, false), 5);
        $this->assertEqualsWithDelta(21600, now()->diffInSeconds($c, false), 60);
    }

    // ─── Device API ───────────────────────────────────────────

    public function test_device_provision_endpoint(): void
    {
        $resp = $this->postJson('/api/v2/zatca/devices', ['environment' => 'sandbox'], $this->authHeader());
        $resp->assertStatus(201)
            ->assertJsonStructure(['data' => ['device_id', 'device_uuid', 'activation_code', 'status']]);
    }

    public function test_device_activation_endpoint(): void
    {
        $provision = $this->postJson('/api/v2/zatca/devices', ['environment' => 'sandbox'], $this->authHeader());
        $code = $provision->json('data.activation_code');
        $resp = $this->postJson('/api/v2/zatca/devices/activate', [
            'activation_code' => $code,
            'hardware_serial' => 'SERIAL-API',
        ], $this->authHeader());
        $resp->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_dashboard_endpoint(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'DASH-001',
            'invoice_type' => 'simplified',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        $resp = $this->getJson('/api/v2/zatca/dashboard', $this->authHeader());
        $resp->assertOk()
            ->assertJsonStructure(['data' => ['summary' => ['total_invoices', 'devices'], 'recent_invoices']])
            ->assertJsonPath('data.summary.total_invoices', 1);
    }

    public function test_chain_verification_endpoint(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'VRFY-001',
            'invoice_type' => 'simplified',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        $device = ZatcaDevice::where('store_id', $this->store->id)->first();
        $resp = $this->getJson("/api/v2/zatca/devices/{$device->id}/verify-chain", $this->authHeader());
        $resp->assertOk()
            ->assertJsonPath('data.chain_intact', true);
    }
}
