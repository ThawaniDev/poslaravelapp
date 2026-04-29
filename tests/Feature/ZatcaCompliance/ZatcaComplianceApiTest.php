<?php

namespace Tests\Feature\ZatcaCompliance;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Order\Models\Order;
use App\Domain\ZatcaCompliance\Models\ZatcaCertificate;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZatcaComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private Organization $org;
    private string $token;

    private User $otherUser;
    private Store $otherStore;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        // ZATCA migration skips SQLite — create tables manually for tests
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            if (!Schema::hasTable('zatca_invoices')) {
                Schema::create('zatca_invoices', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('order_id');
                $table->string('invoice_number', 50);
                $table->string('invoice_type', 20);
                $table->text('invoice_xml');
                $table->string('invoice_hash', 64);
                $table->string('previous_invoice_hash', 64);
                $table->text('digital_signature');
                $table->text('qr_code_data');
                $table->decimal('total_amount', 12, 2);
                $table->decimal('vat_amount', 12, 2);
                $table->string('submission_status', 20)->default('pending');
                $table->string('zatca_response_code', 10)->nullable();
                $table->text('zatca_response_message')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
            }

            if (!Schema::hasTable('zatca_certificates')) {
                Schema::create('zatca_certificates', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('certificate_type', 20);
                $table->text('certificate_pem');
                $table->string('ccsid', 100);
                $table->timestamp('issued_at');
                $table->timestamp('expires_at');
                $table->string('status', 20)->default('active');
                $table->timestamp('created_at')->nullable();
            });
            }
        }

        $this->org = Organization::create([
            'name' => 'ZATCA Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'SA Store',
            'name_ar' => 'متجر السعودية',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'ZATCA Admin',
            'email' => 'zatca@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Another store for isolation tests
        $otherOrg = Organization::create([
            'name' => 'Other ZATCA Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other SA Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->otherUser = User::create([
            'name' => 'Other Admin',
            'email' => 'other-zatca@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->otherStore->id,
            'organization_id' => $otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function authHeader(?string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    private function createOrder(?string $storeId = null): Order
    {
        return Order::create([
            'store_id' => $storeId ?? $this->store->id,
            'order_number' => 'ORD-' . uniqid(),
            'source' => 'pos',
            'status' => 'completed',
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'total' => 115.00,
        ]);
    }

    // ─── Authentication ──────────────────────────────────────

    public function test_unauthenticated_cannot_enroll(): void
    {
        $this->postJson('/api/v2/zatca/enroll')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_invoices(): void
    {
        $this->getJson('/api/v2/zatca/invoices')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_summary(): void
    {
        $this->getJson('/api/v2/zatca/compliance-summary')
            ->assertUnauthorized();
    }

    // ─── Enrollment ──────────────────────────────────────────

    public function test_enroll_requires_otp_and_environment(): void
    {
        $this->postJson('/api/v2/zatca/enroll', [], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['otp', 'environment']);
    }

    public function test_enroll_otp_must_be_6_digits(): void
    {
        $this->postJson('/api/v2/zatca/enroll', [
            'otp' => '123',
            'environment' => 'simulation',
        ], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_enroll_environment_must_be_valid(): void
    {
        $this->postJson('/api/v2/zatca/enroll', [
            'otp' => '123456',
            'environment' => 'invalid',
        ], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['environment']);
    }

    public function test_enroll_simulation_success(): void
    {
        $response = $this->postJson('/api/v2/zatca/enroll', [
            'otp' => '123456',
            'environment' => 'simulation',
        ], $this->authHeader());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'certificate_id', 'ccsid', 'issued_at', 'expires_at', 'environment',
            ]]);

        $this->assertEquals('simulation', $response->json('data.environment'));
        $this->assertDatabaseHas('zatca_certificates', [
            'store_id' => $this->store->id,
            'certificate_type' => 'compliance',
            'status' => 'active',
        ]);
    }

    public function test_enroll_production_success(): void
    {
        $response = $this->postJson('/api/v2/zatca/enroll', [
            'otp' => '654321',
            'environment' => 'production',
        ], $this->authHeader());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.environment', 'production');

        $this->assertDatabaseHas('zatca_certificates', [
            'store_id' => $this->store->id,
            'certificate_type' => 'production',
        ]);
    }

    // ─── Renew Certificate ───────────────────────────────────

    public function test_renew_creates_new_certificate(): void
    {
        // First enroll
        $this->postJson('/api/v2/zatca/enroll', [
            'otp' => '123456',
            'environment' => 'simulation',
        ], $this->authHeader());

        // Then renew
        $response = $this->postJson('/api/v2/zatca/renew', [], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'certificate_id', 'ccsid', 'issued_at', 'expires_at',
            ]]);

        // In stub mode the PCSID is self-signed so we deliberately keep the
        // compliance cert Active (so the operator can re-attempt the exchange
        // against the real ZATCA endpoint without losing their CCSID).
        $this->assertDatabaseHas('zatca_certificates', [
            'store_id' => $this->store->id,
            'certificate_type' => 'compliance',
            'status' => 'active',
        ]);

        // New cert should be active production
        $this->assertDatabaseHas('zatca_certificates', [
            'store_id' => $this->store->id,
            'certificate_type' => 'production',
            'status' => 'active',
        ]);
    }

    // ─── Submit Invoice ──────────────────────────────────────

    public function test_submit_invoice_requires_fields(): void
    {
        $this->postJson('/api/v2/zatca/submit-invoice', [], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'order_id', 'invoice_number', 'invoice_type', 'total_amount', 'vat_amount',
            ]);
    }

    public function test_submit_invoice_validates_invoice_type(): void
    {
        $order = $this->createOrder();

        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-001',
            'invoice_type' => 'invalid_type',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_type']);
    }

    public function test_submit_invoice_success(): void
    {
        $order = $this->createOrder();

        $response = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-001',
            'invoice_type' => 'standard',
            'total_amount' => 115.00,
            'vat_amount' => 15.00,
        ], $this->authHeader());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'invoice_id', 'invoice_number', 'invoice_hash',
                'submission_status', 'submitted_at',
            ]]);

        $this->assertEquals('accepted', $response->json('data.submission_status'));
        $this->assertDatabaseHas('zatca_invoices', [
            'store_id' => $this->store->id,
            'invoice_number' => 'INV-001',
            'submission_status' => 'accepted',
        ]);
    }

    public function test_submit_invoice_hash_chain(): void
    {
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        $resp1 = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order1->id,
            'invoice_number' => 'INV-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        $resp2 = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order2->id,
            'invoice_number' => 'INV-002',
            'invoice_type' => 'standard',
            'total_amount' => 200,
            'vat_amount' => 30,
        ], $this->authHeader());

        $hash1 = $resp1->json('data.invoice_hash');
        $hash2 = $resp2->json('data.invoice_hash');

        // Hashes must differ
        $this->assertNotEquals($hash1, $hash2);

        // Second invoice's previous_hash should equal first invoice's hash
        $invoice2 = ZatcaInvoice::where('invoice_number', 'INV-002')->first();
        $this->assertEquals($hash1, $invoice2->previous_invoice_hash);
    }

    // ─── Submit Batch ────────────────────────────────────────

    public function test_submit_batch_requires_invoices(): void
    {
        $this->postJson('/api/v2/zatca/submit-batch', [], $this->authHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoices']);
    }

    public function test_submit_batch_success(): void
    {
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        $response = $this->postJson('/api/v2/zatca/submit-batch', [
            'invoices' => [
                [
                    'order_id' => $order1->id,
                    'invoice_number' => 'BATCH-001',
                    'invoice_type' => 'standard',
                    'total_amount' => 100,
                    'vat_amount' => 15,
                ],
                [
                    'order_id' => $order2->id,
                    'invoice_number' => 'BATCH-002',
                    'invoice_type' => 'simplified',
                    'total_amount' => 50,
                    'vat_amount' => 7.5,
                ],
            ],
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.accepted', 2)
            ->assertJsonPath('data.rejected', 0);
    }

    // ─── List Invoices ───────────────────────────────────────

    public function test_list_invoices_empty(): void
    {
        $this->getJson('/api/v2/zatca/invoices', $this->authHeader())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_list_invoices_returns_data(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-LIST-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        $response = $this->getJson('/api/v2/zatca/invoices', $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_list_invoices_filters_by_status(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-FILTER-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        // Filter by accepted — should find it
        $this->getJson('/api/v2/zatca/invoices?status=accepted', $this->authHeader())
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        // Filter by pending — should find none (auto-accepted by simulation)
        $this->getJson('/api/v2/zatca/invoices?status=pending', $this->authHeader())
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_list_invoices_data_isolation(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-ISO-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        // Reset auth guards so session doesn't carry over
        $this->app['auth']->forgetGuards();

        // Other user should see zero invoices
        $this->getJson('/api/v2/zatca/invoices', $this->authHeader($this->otherToken))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    // ─── Invoice XML ─────────────────────────────────────────

    public function test_invoice_xml_not_found(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $this->getJson("/api/v2/zatca/invoices/{$fakeId}/xml", $this->authHeader())
            ->assertNotFound();
    }

    public function test_invoice_xml_returns_xml(): void
    {
        $order = $this->createOrder();
        $resp = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-XML-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
            'invoice_xml' => '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><ID>INV-XML-001</ID></Invoice>',
        ], $this->authHeader());

        $invoiceId = $resp->json('data.invoice_id');

        $this->getJson("/api/v2/zatca/invoices/{$invoiceId}/xml", $this->authHeader())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_invoice_xml_data_isolation(): void
    {
        $order = $this->createOrder();
        $resp = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-ISO-XML',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        $invoiceId = $resp->json('data.invoice_id');

        // Reset auth guards so session doesn't carry over
        $this->app['auth']->forgetGuards();

        // Other user should NOT be able to access this invoice's XML
        $this->getJson("/api/v2/zatca/invoices/{$invoiceId}/xml", $this->authHeader($this->otherToken))
            ->assertNotFound();
    }

    // ─── Compliance Summary ──────────────────────────────────

    public function test_compliance_summary_empty(): void
    {
        $response = $this->getJson('/api/v2/zatca/compliance-summary', $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_invoices', 0)
            ->assertJsonPath('data.accepted', 0)
            ->assertJsonPath('data.rejected', 0)
            ->assertJsonPath('data.pending', 0)
            ->assertJsonPath('data.success_rate', 0)
            ->assertJsonPath('data.certificate', null);
    }

    public function test_compliance_summary_with_data(): void
    {
        // Enroll first to have a certificate
        $this->postJson('/api/v2/zatca/enroll', [
            'otp' => '123456',
            'environment' => 'simulation',
        ], $this->authHeader());

        // Submit an invoice
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'INV-SUM-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        $response = $this->getJson('/api/v2/zatca/compliance-summary', $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('data.total_invoices', 1)
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonStructure(['data' => [
                'certificate' => ['id', 'type', 'ccsid', 'issued_at', 'expires_at', 'days_until_expiry'],
            ]]);

        $this->assertEquals(100.0, (float) $response->json('data.success_rate'));
    }

    // ─── VAT Report ──────────────────────────────────────────

    public function test_vat_report_empty(): void
    {
        $response = $this->getJson('/api/v2/zatca/vat-report', $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.standard_invoices.count', 0)
            ->assertJsonPath('data.simplified_invoices.count', 0)
            ->assertJsonPath('data.total_vat_collected', 0)
            ->assertJsonPath('data.total_amount', 0);
    }

    public function test_vat_report_with_mixed_invoices(): void
    {
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        // Submit standard invoice
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order1->id,
            'invoice_number' => 'VAT-STD-001',
            'invoice_type' => 'standard',
            'total_amount' => 1000,
            'vat_amount' => 150,
        ], $this->authHeader());

        // Submit simplified invoice
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order2->id,
            'invoice_number' => 'VAT-SMP-001',
            'invoice_type' => 'simplified',
            'total_amount' => 500,
            'vat_amount' => 75,
        ], $this->authHeader());

        $response = $this->getJson('/api/v2/zatca/vat-report', $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('data.standard_invoices.count', 1)
            ->assertJsonPath('data.simplified_invoices.count', 1);

        $data = $response->json('data');
        $this->assertEquals(1000.0, (float) $data['standard_invoices']['total_amount']);
        $this->assertEquals(150.0, (float) $data['standard_invoices']['total_vat']);
        $this->assertEquals(500.0, (float) $data['simplified_invoices']['total_amount']);
        $this->assertEquals(75.0, (float) $data['simplified_invoices']['total_vat']);
        $this->assertEquals(225.0, (float) $data['total_vat_collected']);
        $this->assertEquals(1500.0, (float) $data['total_amount']);
    }

    public function test_vat_report_filters_by_date(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'VAT-DATE-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        // Future date range — should return empty
        $this->getJson('/api/v2/zatca/vat-report?date_from=2099-01-01&date_to=2099-12-31', $this->authHeader())
            ->assertOk()
            ->assertJsonPath('data.standard_invoices.count', 0);

        // Today's date range — should include the invoice
        $today = now()->format('Y-m-d');
        $this->getJson("/api/v2/zatca/vat-report?date_from={$today}&date_to={$today}", $this->authHeader())
            ->assertOk()
            ->assertJsonPath('data.standard_invoices.count', 1);
    }

    public function test_vat_report_data_isolation(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'VAT-ISO-001',
            'invoice_type' => 'standard',
            'total_amount' => 100,
            'vat_amount' => 15,
        ], $this->authHeader());

        // Reset auth guards so session doesn't carry over
        $this->app['auth']->forgetGuards();

        // Other user should see zero
        $response = $this->getJson('/api/v2/zatca/vat-report', $this->authHeader($this->otherToken));
        $response->assertOk();
        $this->assertEquals(0, (float) $response->json('data.total_amount'));
    }

    // ─── Phase 2 provider visibility ──────────────────────────────

    public function test_invoice_detail_returns_xml_qr_and_response(): void
    {
        $order = $this->createOrder();
        $submit = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'DETAIL-001',
            'invoice_type' => 'standard',
            'total_amount' => 115.00,
            'vat_amount' => 15.00,
        ], $this->authHeader())->assertStatus(201);

        $invoiceId = $submit->json('data.invoice_id');
        $response = $this->getJson("/api/v2/zatca/invoices/{$invoiceId}", $this->authHeader());
        $response->assertOk()
            ->assertJsonStructure(['data' => ['invoice', 'qr_code_base64', 'xml']]);
        $this->assertNotEmpty($response->json('data.xml'));
    }

    public function test_invoice_detail_data_isolation(): void
    {
        $order = $this->createOrder();
        $submit = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'DETAIL-ISO-001',
            'invoice_type' => 'standard',
            'total_amount' => 115.00,
            'vat_amount' => 15.00,
        ], $this->authHeader())->assertStatus(201);

        $invoiceId = $submit->json('data.invoice_id');
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v2/zatca/invoices/{$invoiceId}", $this->authHeader($this->otherToken))
            ->assertNotFound();
    }

    public function test_retry_submission_reports_already_accepted(): void
    {
        $order = $this->createOrder();
        $submit = $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'RETRY-001',
            'invoice_type' => 'standard',
            'total_amount' => 115.00,
            'vat_amount' => 15.00,
        ], $this->authHeader())->assertStatus(201);

        $invoiceId = $submit->json('data.invoice_id');
        $response = $this->postJson("/api/v2/zatca/invoices/{$invoiceId}/retry", [], $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.submission_status', 'accepted');
    }

    public function test_retry_submission_404_for_unknown_invoice(): void
    {
        $this->postJson('/api/v2/zatca/invoices/00000000-0000-0000-0000-000000000000/retry', [], $this->authHeader())
            ->assertNotFound();
    }

    public function test_connection_status_unconnected_when_no_certificate(): void
    {
        $response = $this->getJson('/api/v2/zatca/connection', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonStructure(['data' => [
                'environment', 'is_production', 'is_healthy', 'connected',
                'certificate', 'devices', 'queue_depth', 'last_success', 'last_error',
            ]]);
    }

    public function test_connection_status_after_submission(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'CONN-001',
            'invoice_type' => 'standard',
            'total_amount' => 115.00,
            'vat_amount' => 15.00,
        ], $this->authHeader())->assertStatus(201);

        $response = $this->getJson('/api/v2/zatca/connection', $this->authHeader())->assertOk();
        $this->assertTrue($response->json('data.connected'));
        $this->assertNotNull($response->json('data.certificate.expires_at'));
        $this->assertSame(0, $response->json('data.devices.tampered'));
        $this->assertNotNull($response->json('data.last_success.invoice_number'));
    }

    public function test_admin_overview_aggregates_caller_org_stores(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/v2/zatca/submit-invoice', [
            'order_id' => $order->id,
            'invoice_number' => 'OVR-001',
            'invoice_type' => 'standard',
            'total_amount' => 115.00,
            'vat_amount' => 15.00,
        ], $this->authHeader())->assertStatus(201);

        $response = $this->getJson('/api/v2/admin/zatca/overview', $this->authHeader())->assertOk();
        $totals = $response->json('data.totals');
        $this->assertGreaterThanOrEqual(1, $totals['stores']);
        $this->assertGreaterThanOrEqual(1, $totals['accepted']);
        $this->assertIsArray($response->json('data.stores'));
    }
}
