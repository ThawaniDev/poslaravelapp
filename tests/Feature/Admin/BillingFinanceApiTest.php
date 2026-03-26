<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Billing\Models\HardwareSale;
use App\Domain\Billing\Models\ImplementationFee;
use App\Domain\Billing\Models\PaymentGatewayConfig;
use App\Domain\Billing\Models\PaymentRetryRule;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Organization $org;
    private Store $store;
    private SubscriptionPlan $plan;
    private StoreSubscription $subscription;
    private Invoice $paidInvoice;
    private Invoice $pendingInvoice;
    private Invoice $failedInvoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name' => 'Finance Admin',
            'email' => 'finance@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        $this->org = Organization::forceCreate([
            'name' => 'Test Org',
            'is_active' => true,
        ]);

        $this->store = Store::forceCreate([
            'name' => 'Test Store',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $this->plan = SubscriptionPlan::forceCreate([
            'name' => 'Pro Plan',
            'name_ar' => 'خطة برو',
            'slug' => 'pro_plan',
            'description' => 'Professional plan',
            'monthly_price' => 50.00,
            'annual_price' => 500.00,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'is_active' => true,
            'is_highlighted' => false,
            'sort_order' => 1,
        ]);

        $this->subscription = StoreSubscription::forceCreate([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $this->paidInvoice = Invoice::forceCreate([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-PAID001',
            'amount' => 50.00,
            'tax' => 7.50,
            'total' => 57.50,
            'status' => 'paid',
            'due_date' => now()->subDays(5),
            'paid_at' => now()->subDays(3),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(3),
        ]);

        InvoiceLineItem::forceCreate([
            'invoice_id' => $this->paidInvoice->id,
            'description' => 'Pro Plan Subscription',
            'quantity' => 1,
            'unit_price' => 50.00,
            'total' => 50.00,
        ]);

        $this->pendingInvoice = Invoice::forceCreate([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-PEND001',
            'amount' => 50.00,
            'tax' => 7.50,
            'total' => 57.50,
            'status' => 'pending',
            'due_date' => now()->addDays(5),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->failedInvoice = Invoice::forceCreate([
            'store_subscription_id' => $this->subscription->id,
            'invoice_number' => 'INV-FAIL001',
            'amount' => 50.00,
            'tax' => 7.50,
            'total' => 57.50,
            'status' => 'failed',
            'due_date' => now()->subDays(2),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(1),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  INVOICE LIST & SHOW
    // ═══════════════════════════════════════════════════════════

    public function test_list_invoices_returns_all(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/invoices');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_list_invoices_filter_by_status(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/invoices?status=paid');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_list_invoices_search_by_invoice_number(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/invoices?search=PEND001');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_list_invoices_filter_by_amount_range(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/invoices?amount_min=50&amount_max=60');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_show_invoice_returns_detail_with_line_items(): void
    {
        $response = $this->getJson("/api/v2/admin/billing/invoices/{$this->paidInvoice->id}");
        $response->assertOk()
            ->assertJsonPath('data.id', $this->paidInvoice->id)
            ->assertJsonPath('data.invoice_number', 'INV-PAID001')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonStructure(['data' => ['line_items']]);

        $this->assertCount(1, $response->json('data.line_items'));
    }

    public function test_show_invoice_not_found(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/invoices/' . Str::uuid());
        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  MANUAL INVOICE CREATION
    // ═══════════════════════════════════════════════════════════

    public function test_create_manual_invoice(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/invoices', [
            'store_subscription_id' => $this->subscription->id,
            'line_items' => [
                ['description' => 'Custom Setup Fee', 'quantity' => 1, 'unit_price' => 100.00],
                ['description' => 'Training Session', 'quantity' => 2, 'unit_price' => 50.00],
            ],
            'tax_rate' => 15,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(200.00, $data['amount']); // 100 + 2*50
        $this->assertEquals(30.00, $data['tax']); // 200 * 15%
        $this->assertEquals(230.00, $data['total']); // 200 + 30
        $this->assertEquals('pending', $data['status']);
        $this->assertCount(2, $data['line_items']);
    }

    public function test_create_manual_invoice_with_custom_due_date(): void
    {
        $dueDate = now()->addDays(14)->format('Y-m-d');

        $response = $this->postJson('/api/v2/admin/billing/invoices', [
            'store_subscription_id' => $this->subscription->id,
            'line_items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50.00],
            ],
            'due_date' => $dueDate,
        ]);

        $response->assertCreated();
    }

    public function test_create_manual_invoice_requires_line_items(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/invoices', [
            'store_subscription_id' => $this->subscription->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['line_items']);
    }

    public function test_create_manual_invoice_validates_subscription(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/invoices', [
            'store_subscription_id' => Str::uuid()->toString(),
            'line_items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 10.00],
            ],
        ]);

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════
    //  MARK INVOICE PAID
    // ═══════════════════════════════════════════════════════════

    public function test_mark_invoice_paid(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/invoices/{$this->pendingInvoice->id}/mark-paid");
        $response->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertNotNull($response->json('data.paid_at'));
    }

    public function test_mark_already_paid_invoice_fails(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/invoices/{$this->paidInvoice->id}/mark-paid");
        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invoice is already paid');
    }

    public function test_mark_invoice_paid_not_found(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/invoices/' . Str::uuid() . '/mark-paid');
        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  REFUND PROCESSING
    // ═══════════════════════════════════════════════════════════

    public function test_process_full_refund(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/invoices/{$this->paidInvoice->id}/refund", [
            'amount' => 57.50,
            'reason' => 'Customer requested cancellation',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'refunded');

        // Should have the original line item + refund line item
        $this->assertCount(2, $response->json('data.line_items'));
    }

    public function test_process_partial_refund(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/invoices/{$this->paidInvoice->id}/refund", [
            'amount' => 20.00,
            'reason' => 'Partial service issue',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid'); // Stays paid for partial
    }

    public function test_refund_exceeds_total_fails(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/invoices/{$this->paidInvoice->id}/refund", [
            'amount' => 999.99,
            'reason' => 'Too much',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Refund amount exceeds invoice total');
    }

    public function test_refund_unpaid_invoice_fails(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/invoices/{$this->pendingInvoice->id}/refund", [
            'amount' => 10.00,
            'reason' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only paid invoices can be refunded');
    }

    public function test_refund_requires_amount_and_reason(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/invoices/{$this->paidInvoice->id}/refund", []);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'reason']);
    }

    // ═══════════════════════════════════════════════════════════
    //  INVOICE PDF URL
    // ═══════════════════════════════════════════════════════════

    public function test_invoice_pdf_url(): void
    {
        $response = $this->getJson("/api/v2/admin/billing/invoices/{$this->paidInvoice->id}/pdf");
        $response->assertOk()
            ->assertJsonPath('data.invoice_id', $this->paidInvoice->id)
            ->assertJsonPath('data.invoice_number', 'INV-PAID001');
    }

    public function test_invoice_pdf_not_found(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/invoices/' . Str::uuid() . '/pdf');
        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  FAILED PAYMENTS
    // ═══════════════════════════════════════════════════════════

    public function test_list_failed_payments(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/failed-payments');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_retry_failed_payment(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/failed-payments/{$this->failedInvoice->id}/retry");
        $response->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_retry_non_failed_payment_fails(): void
    {
        $response = $this->postJson("/api/v2/admin/billing/failed-payments/{$this->paidInvoice->id}/retry");
        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only failed invoices can be retried');
    }

    public function test_retry_not_found(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/failed-payments/' . Str::uuid() . '/retry');
        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  PAYMENT RETRY RULES
    // ═══════════════════════════════════════════════════════════

    public function test_get_retry_rules_defaults(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/retry-rules');
        $response->assertOk()
            ->assertJsonPath('data.max_retries', 3)
            ->assertJsonPath('data.retry_interval_hours', 24)
            ->assertJsonPath('data.grace_period_after_failure_days', 7);
    }

    public function test_update_retry_rules_creates_if_not_exists(): void
    {
        $response = $this->putJson('/api/v2/admin/billing/retry-rules', [
            'max_retries' => 5,
            'retry_interval_hours' => 12,
            'grace_period_after_failure_days' => 14,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.max_retries', 5)
            ->assertJsonPath('data.retry_interval_hours', 12)
            ->assertJsonPath('data.grace_period_after_failure_days', 14);
    }

    public function test_update_retry_rules_updates_existing(): void
    {
        PaymentRetryRule::forceCreate([
            'id' => Str::uuid()->toString(),
            'max_retries' => 3,
            'retry_interval_hours' => 24,
            'grace_period_after_failure_days' => 7,
            'updated_at' => now(),
        ]);

        $response = $this->putJson('/api/v2/admin/billing/retry-rules', [
            'max_retries' => 5,
            'retry_interval_hours' => 48,
            'grace_period_after_failure_days' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.max_retries', 5)
            ->assertJsonPath('data.retry_interval_hours', 48);

        $this->assertEquals(1, PaymentRetryRule::count());
    }

    public function test_update_retry_rules_validates_input(): void
    {
        $response = $this->putJson('/api/v2/admin/billing/retry-rules', [
            'max_retries' => 0,
            'retry_interval_hours' => 200,
            'grace_period_after_failure_days' => -1,
        ]);

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════
    //  REVENUE DASHBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_revenue_dashboard(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/revenue');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'mrr', 'arr', 'revenue_by_status',
                    'upcoming_renewals', 'hardware_revenue',
                    'implementation_revenue', 'total_invoices',
                    'paid_invoices', 'failed_invoices',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['total_invoices']);
        $this->assertEquals(1, $data['paid_invoices']);
        $this->assertEquals(1, $data['failed_invoices']);
    }

    // ═══════════════════════════════════════════════════════════
    //  PAYMENT GATEWAY CONFIGS
    // ═══════════════════════════════════════════════════════════

    public function test_list_gateways_empty(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/gateways');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_create_gateway(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/gateways', [
            'gateway_name' => 'thawani_pay',
            'credentials' => [
                'key' => 'pk_test_123',
                'secret' => 'sk_test_456',
                'merchant_id' => 'merch_789',
            ],
            'webhook_url' => 'https://example.com/webhook',
            'environment' => 'sandbox',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.gateway_name', 'thawani_pay')
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.has_credentials', true);

        // Credentials should not be exposed
        $this->assertArrayNotHasKey('credentials_encrypted', $response->json('data'));
    }

    public function test_create_duplicate_gateway_same_environment_fails(): void
    {
        PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => 'stripe',
            'credentials_encrypted' => encrypt(['key' => 'k']),
            'environment' => 'sandbox',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v2/admin/billing/gateways', [
            'gateway_name' => 'stripe',
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'environment' => 'sandbox',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Gateway config already exists for this environment');
    }

    public function test_show_gateway(): void
    {
        $gateway = PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => 'moyasar',
            'credentials_encrypted' => encrypt(['key' => 'k', 'secret' => 's']),
            'webhook_url' => 'https://hook.example.com',
            'environment' => 'production',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/billing/gateways/{$gateway->id}");
        $response->assertOk()
            ->assertJsonPath('data.gateway_name', 'moyasar')
            ->assertJsonPath('data.environment', 'production');
    }

    public function test_update_gateway(): void
    {
        $gateway = PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => 'stripe',
            'credentials_encrypted' => encrypt(['key' => 'k', 'secret' => 's']),
            'environment' => 'sandbox',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v2/admin/billing/gateways/{$gateway->id}", [
            'is_active' => false,
            'webhook_url' => 'https://new-hook.example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_delete_gateway(): void
    {
        $gateway = PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => 'test_gw',
            'credentials_encrypted' => encrypt(['key' => 'k', 'secret' => 's']),
            'environment' => 'sandbox',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v2/admin/billing/gateways/{$gateway->id}");
        $response->assertOk();

        $this->assertNull(PaymentGatewayConfig::find($gateway->id));
    }

    public function test_test_gateway_connection(): void
    {
        $gateway = PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => 'stripe',
            'credentials_encrypted' => encrypt(['key' => 'k', 'secret' => 's']),
            'environment' => 'sandbox',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v2/admin/billing/gateways/{$gateway->id}/test");
        $response->assertOk()
            ->assertJsonPath('data.connection_status', 'ok')
            ->assertJsonPath('data.gateway_name', 'stripe');
    }

    public function test_test_gateway_not_found(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/gateways/' . Str::uuid() . '/test');
        $response->assertNotFound();
    }

    public function test_list_gateways_filter_by_environment(): void
    {
        PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => 'stripe',
            'credentials_encrypted' => encrypt(['key' => 'k', 'secret' => 's']),
            'environment' => 'sandbox',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => 'stripe',
            'credentials_encrypted' => encrypt(['key' => 'k', 'secret' => 's']),
            'environment' => 'production',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/billing/gateways?environment=sandbox');
        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ═══════════════════════════════════════════════════════════
    //  HARDWARE SALES
    // ═══════════════════════════════════════════════════════════

    public function test_list_hardware_sales_empty(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/hardware-sales');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_create_hardware_sale(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/hardware-sales', [
            'store_id' => $this->store->id,
            'item_type' => 'terminal',
            'item_description' => 'Sunmi V2 Terminal',
            'serial_number' => 'SN-12345',
            'amount' => 1500.00,
            'notes' => 'Delivered to main branch',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item_type', 'terminal')
            ->assertJsonPath('data.serial_number', 'SN-12345');

        $this->assertEquals(1500.00, $response->json('data.amount'));
    }

    public function test_create_hardware_sale_validates_item_type(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/hardware-sales', [
            'store_id' => $this->store->id,
            'item_type' => 'invalid_type',
            'amount' => 100,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_type']);
    }

    public function test_show_hardware_sale(): void
    {
        $sale = HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'printer',
            'item_description' => 'Receipt Printer',
            'serial_number' => 'PR-999',
            'amount' => 200.00,
            'sold_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/billing/hardware-sales/{$sale->id}");
        $response->assertOk()
            ->assertJsonPath('data.item_type', 'printer')
            ->assertJsonPath('data.serial_number', 'PR-999');
    }

    public function test_update_hardware_sale(): void
    {
        $sale = HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'scanner',
            'amount' => 300.00,
            'sold_at' => now(),
        ]);

        $response = $this->putJson("/api/v2/admin/billing/hardware-sales/{$sale->id}", [
            'amount' => 350.00,
            'serial_number' => 'SC-555',
        ]);

        $response->assertOk();
        $this->assertEquals(350.00, $response->json('data.amount'));
        $this->assertEquals('SC-555', $response->json('data.serial_number'));
    }

    public function test_delete_hardware_sale(): void
    {
        $sale = HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'other',
            'amount' => 100.00,
            'sold_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v2/admin/billing/hardware-sales/{$sale->id}");
        $response->assertOk();

        $this->assertNull(HardwareSale::find($sale->id));
    }

    public function test_list_hardware_sales_filter_by_store(): void
    {
        $store2 = Store::forceCreate(['name' => 'Store 2', 'is_active' => true]);

        HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'terminal',
            'amount' => 100,
            'sold_at' => now(),
        ]);

        HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $store2->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'printer',
            'amount' => 200,
            'sold_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/billing/hardware-sales?store_id={$this->store->id}");
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_list_hardware_sales_filter_by_item_type(): void
    {
        HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'terminal',
            'amount' => 100,
            'sold_at' => now(),
        ]);

        HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'printer',
            'amount' => 200,
            'sold_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/billing/hardware-sales?item_type=terminal');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_hardware_sale_search_by_serial(): void
    {
        HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'sold_by' => $this->admin->id,
            'item_type' => 'terminal',
            'serial_number' => 'UNIQUE-XYZ-123',
            'amount' => 100,
            'sold_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/billing/hardware-sales?search=XYZ-123');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_hardware_sale_not_found(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/hardware-sales/' . Str::uuid());
        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  IMPLEMENTATION / TRAINING FEES
    // ═══════════════════════════════════════════════════════════

    public function test_list_implementation_fees_empty(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/implementation-fees');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_create_implementation_fee(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/implementation-fees', [
            'store_id' => $this->store->id,
            'fee_type' => 'setup',
            'amount' => 500.00,
            'notes' => 'Initial setup for POS system',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.fee_type', 'setup')
            ->assertJsonPath('data.status', 'invoiced');

        $this->assertEquals(500.00, $response->json('data.amount'));
    }

    public function test_create_implementation_fee_training_type(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/implementation-fees', [
            'store_id' => $this->store->id,
            'fee_type' => 'training',
            'amount' => 300.00,
            'status' => 'paid',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.fee_type', 'training')
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_create_implementation_fee_validates_fee_type(): void
    {
        $response = $this->postJson('/api/v2/admin/billing/implementation-fees', [
            'store_id' => $this->store->id,
            'fee_type' => 'invalid',
            'amount' => 100,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['fee_type']);
    }

    public function test_show_implementation_fee(): void
    {
        $fee = ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'custom_dev',
            'amount' => 2000.00,
            'status' => 'invoiced',
            'notes' => 'Custom integration work',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/billing/implementation-fees/{$fee->id}");
        $response->assertOk()
            ->assertJsonPath('data.fee_type', 'custom_dev');

        $this->assertEquals(2000.00, $response->json('data.amount'));
    }

    public function test_update_implementation_fee(): void
    {
        $fee = ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'setup',
            'amount' => 500.00,
            'status' => 'invoiced',
            'created_at' => now(),
        ]);

        $response = $this->putJson("/api/v2/admin/billing/implementation-fees/{$fee->id}", [
            'status' => 'paid',
            'amount' => 550.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertEquals(550.00, $response->json('data.amount'));
    }

    public function test_delete_implementation_fee(): void
    {
        $fee = ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'training',
            'amount' => 200.00,
            'status' => 'invoiced',
            'created_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v2/admin/billing/implementation-fees/{$fee->id}");
        $response->assertOk();

        $this->assertNull(ImplementationFee::find($fee->id));
    }

    public function test_list_implementation_fees_filter_by_store(): void
    {
        $store2 = Store::forceCreate(['name' => 'Store B', 'is_active' => true]);

        ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'setup',
            'amount' => 100,
            'status' => 'invoiced',
            'created_at' => now(),
        ]);

        ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $store2->id,
            'fee_type' => 'training',
            'amount' => 200,
            'status' => 'paid',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/billing/implementation-fees?store_id={$this->store->id}");
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_list_implementation_fees_filter_by_type(): void
    {
        ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'setup',
            'amount' => 100,
            'status' => 'invoiced',
            'created_at' => now(),
        ]);

        ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'training',
            'amount' => 200,
            'status' => 'invoiced',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/billing/implementation-fees?fee_type=setup');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_list_implementation_fees_filter_by_status(): void
    {
        ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'setup',
            'amount' => 100,
            'status' => 'invoiced',
            'created_at' => now(),
        ]);

        ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'fee_type' => 'training',
            'amount' => 200,
            'status' => 'paid',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/billing/implementation-fees?status=paid');
        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_implementation_fee_not_found(): void
    {
        $response = $this->getJson('/api/v2/admin/billing/implementation-fees/' . Str::uuid());
        $response->assertNotFound();
    }
}
