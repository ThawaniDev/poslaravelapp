<?php

namespace Tests\Feature\WameedAI;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\WameedAI\Models\AIBillingInvoice;
use App\Domain\WameedAI\Models\AIBillingInvoiceItem;
use App\Domain\WameedAI\Models\AIBillingPayment;
use App\Domain\WameedAI\Models\AIBillingSetting;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIStoreBillingConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use App\Domain\WameedAI\Services\AIBillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comprehensive tests for Wameed AI Billing System.
 *
 * Tests the full billing lifecycle: settings CRUD, store config management,
 * invoice generation, payments, auto-disable, gateway billing check,
 * and both store-side and admin API endpoints.
 */
class AIBillingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private Organization $org;
    private Store $store;
    private Store $store2;
    private string $ownerToken;
    private string $adminToken;
    private AIFeatureDefinition $feature;
    private AIFeatureDefinition $feature2;
    private AIBillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        self::$invoiceCounter = 0;
        $this->createTables();
        $this->seedData();
        $this->billingService = new AIBillingService();
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 1: BILLING SERVICE — canStoreUseAI()
    // ═══════════════════════════════════════════════════════════

    public function test_can_store_use_ai_returns_true_when_enabled(): void
    {
        [$allowed, $reason] = $this->billingService->canStoreUseAI($this->store->id, $this->org->id);
        $this->assertTrue($allowed);
        $this->assertNull($reason);
    }

    public function test_can_store_use_ai_returns_true_when_billing_disabled(): void
    {
        AIBillingSetting::setValue('billing_enabled', 'false');
        [$allowed, $reason] = $this->billingService->canStoreUseAI($this->store->id, $this->org->id);
        $this->assertTrue($allowed);
        $this->assertNull($reason);
    }

    public function test_can_store_use_ai_returns_false_when_ai_disabled(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['is_ai_enabled' => false, 'disabled_reason' => 'disabled_by_admin']);

        [$allowed, $reason] = $this->billingService->canStoreUseAI($this->store->id, $this->org->id);
        $this->assertFalse($allowed);
        $this->assertEquals('disabled_by_admin', $reason);
    }

    public function test_can_store_use_ai_returns_false_when_monthly_limit_exceeded(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['monthly_limit_usd' => 1.00]);

        // Create usage that exceeds the limit (raw cost $0.90 * 1.2 margin = $1.08 > $1.00)
        $this->createUsageLog($this->store->id, 'smart_reorder', 0.90);

        [$allowed, $reason] = $this->billingService->canStoreUseAI($this->store->id, $this->org->id);
        $this->assertFalse($allowed);
        $this->assertEquals('monthly_limit_exceeded', $reason);
    }

    public function test_can_store_use_ai_within_limit_when_usage_is_low(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['monthly_limit_usd' => 10.00]);

        $this->createUsageLog($this->store->id, 'smart_reorder', 0.50);

        [$allowed, $reason] = $this->billingService->canStoreUseAI($this->store->id, $this->org->id);
        $this->assertTrue($allowed);
        $this->assertNull($reason);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 2: BILLING SERVICE — Cost Calculations
    // ═══════════════════════════════════════════════════════════

    public function test_current_month_billed_cost_applies_margin(): void
    {
        $this->createUsageLog($this->store->id, 'smart_reorder', 1.00);

        $billedCost = $this->billingService->getCurrentMonthBilledCost($this->store->id);
        // 1.00 * 1.20 = 1.20
        $this->assertEquals(1.20, $billedCost);
    }

    public function test_current_month_billed_cost_uses_custom_margin(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['custom_margin_percentage' => 30.0]);

        $this->createUsageLog($this->store->id, 'smart_reorder', 1.00);

        $billedCost = $this->billingService->getCurrentMonthBilledCost($this->store->id);
        // 1.00 * 1.30 = 1.30
        $this->assertEquals(1.30, $billedCost);
    }

    public function test_current_month_raw_cost_excludes_failed_requests(): void
    {
        $this->createUsageLog($this->store->id, 'smart_reorder', 1.00, 'success');
        $this->createUsageLog($this->store->id, 'smart_reorder', 0.50, 'error');

        $rawCost = $this->billingService->getCurrentMonthRawCost($this->store->id);
        $this->assertEquals(1.00, $rawCost);
    }

    public function test_effective_margin_falls_back_to_global(): void
    {
        $margin = $this->billingService->getEffectiveMarginForStore($this->store->id);
        $this->assertEquals(20.0, $margin);
    }

    public function test_effective_margin_uses_custom_when_set(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['custom_margin_percentage' => 15.0]);

        $margin = $this->billingService->getEffectiveMarginForStore($this->store->id);
        $this->assertEquals(15.0, $margin);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 3: BILLING SERVICE — Invoice Generation
    // ═══════════════════════════════════════════════════════════

    public function test_generate_monthly_invoices_creates_invoices_for_stores_with_usage(): void
    {
        // Create usage for last month
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 2.50, 'success', $lastMonth);
        $this->createUsageLog($this->store->id, 'daily_summary', 1.00, 'success', $lastMonth);
        $this->createUsageLog($this->store2->id, 'smart_reorder', 0.75, 'success', $lastMonth);

        $result = $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);

        $this->assertEquals(2, $result['generated']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertCount(2, $result['invoices']);
    }

    public function test_generate_invoice_calculates_correct_amounts(): void
    {
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 2.00, 'success', $lastMonth);
        $this->createUsageLog($this->store->id, 'daily_summary', 1.00, 'success', $lastMonth);

        $result = $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);
        $invoice = AIBillingInvoice::forStore($this->store->id)->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(3.0, (float) $invoice->raw_cost_usd);
        $this->assertEquals(20.0, (float) $invoice->margin_percentage);
        $this->assertEquals(0.6, (float) $invoice->margin_amount_usd); // 3.0 * 0.20
        $this->assertEquals(3.6, (float) $invoice->billed_amount_usd); // 3.0 + 0.6
        $this->assertEquals('pending', $invoice->status);
    }

    public function test_generate_invoice_creates_line_items_per_feature(): void
    {
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 2.00, 'success', $lastMonth);
        $this->createUsageLog($this->store->id, 'daily_summary', 1.00, 'success', $lastMonth);

        $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);
        $invoice = AIBillingInvoice::forStore($this->store->id)->with('items')->first();

        $this->assertCount(2, $invoice->items);
        $slugs = $invoice->items->pluck('feature_slug')->toArray();
        $this->assertContains('smart_reorder', $slugs);
        $this->assertContains('daily_summary', $slugs);
    }

    public function test_generate_invoice_skips_duplicate_for_same_period(): void
    {
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 2.00, 'success', $lastMonth);

        $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);
        $result2 = $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);

        $this->assertEquals(0, $result2['generated']);
        $invoiceCount = AIBillingInvoice::forStore($this->store->id)->count();
        $this->assertEquals(1, $invoiceCount);
    }

    public function test_generate_invoice_skips_below_minimum_billable(): void
    {
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 0.001, 'success', $lastMonth);

        AIBillingSetting::setValue('min_billable_amount_usd', '1.00');
        $result = $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);

        $this->assertEquals(0, $result['generated']);
    }

    public function test_invoice_number_format(): void
    {
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 1.00, 'success', $lastMonth);

        $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);
        $invoice = AIBillingInvoice::forStore($this->store->id)->first();

        $this->assertMatchesRegularExpression('/^AI-[A-F0-9]{8}-\d{6}$/', $invoice->invoice_number);
    }

    public function test_invoice_due_date_respects_grace_days(): void
    {
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 1.00, 'success', $lastMonth);

        AIBillingSetting::setValue('auto_disable_grace_days', '7');
        $this->billingService->generateMonthlyInvoices($lastMonth->year, $lastMonth->month);
        $invoice = AIBillingInvoice::forStore($this->store->id)->first();

        $expectedDue = Carbon::create($lastMonth->year, $lastMonth->month, 1)->addMonth()->addDays(7);
        $this->assertEquals($expectedDue->toDateString(), $invoice->due_date->toDateString());
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 4: BILLING SERVICE — Payments
    // ═══════════════════════════════════════════════════════════

    public function test_record_payment_creates_payment_record(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);

        $payment = $this->billingService->recordPayment(
            $invoice->id, 5.00, 'bank_transfer', 'REF-123', 'Partial payment'
        );

        $this->assertEquals(5.00, (float) $payment->amount_usd);
        $this->assertEquals('bank_transfer', $payment->payment_method);
        $this->assertEquals('REF-123', $payment->reference);
    }

    public function test_record_full_payment_marks_invoice_paid(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);

        $this->billingService->recordPayment($invoice->id, 10.00, 'manual');
        $invoice->refresh();

        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_partial_payments_mark_paid_when_total_reaches_amount(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);

        $this->billingService->recordPayment($invoice->id, 6.00, 'manual');
        $invoice->refresh();
        $this->assertEquals('pending', $invoice->status);

        $this->billingService->recordPayment($invoice->id, 4.00, 'manual');
        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_payment_re_enables_store_disabled_for_overdue(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['is_ai_enabled' => false, 'disabled_reason' => 'overdue_invoice', 'disabled_at' => now()]);

        $invoice = $this->createTestInvoice($this->store->id, 10.00, 'overdue');
        $this->billingService->recordPayment($invoice->id, 10.00, 'manual');

        $config->refresh();
        $this->assertTrue($config->is_ai_enabled);
        $this->assertNull($config->disabled_reason);
    }

    public function test_mark_invoice_paid_convenience_method(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);

        $result = $this->billingService->markInvoicePaid($invoice->id, 'cash', 'CASH-001');
        $this->assertEquals('paid', $result->status);
        $this->assertCount(1, $result->payments);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 5: BILLING SERVICE — Auto-Disable Overdue
    // ═══════════════════════════════════════════════════════════

    public function test_check_overdue_marks_invoices_and_disables_stores(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00, 'pending');
        $invoice->update(['due_date' => now()->subDays(1)]);

        $result = $this->billingService->checkAndDisableOverdueStores();

        $invoice->refresh();
        $this->assertEquals('overdue', $invoice->status);
        $this->assertEquals(1, $result['disabled']);
        $this->assertContains($this->store->id, $result['stores']);

        $config = AIStoreBillingConfig::where('store_id', $this->store->id)->first();
        $this->assertFalse($config->is_ai_enabled);
        $this->assertEquals('overdue_invoice', $config->disabled_reason);
    }

    public function test_check_overdue_skips_when_billing_disabled(): void
    {
        AIBillingSetting::setValue('billing_enabled', 'false');
        $invoice = $this->createTestInvoice($this->store->id, 10.00, 'pending');
        $invoice->update(['due_date' => now()->subDays(1)]);

        $result = $this->billingService->checkAndDisableOverdueStores();
        $this->assertEquals(0, $result['disabled']);
    }

    public function test_check_overdue_does_not_affect_paid_invoices(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00, 'paid');
        $invoice->update(['due_date' => now()->subDays(1)]);

        $result = $this->billingService->checkAndDisableOverdueStores();
        $this->assertEquals(0, $result['disabled']);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 6: BILLING SERVICE — Enable/Disable Store
    // ═══════════════════════════════════════════════════════════

    public function test_enable_store_ai(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['is_ai_enabled' => false, 'disabled_reason' => 'test']);

        $result = $this->billingService->enableStoreAI($this->store->id, $this->org->id);
        $this->assertTrue($result->is_ai_enabled);
        $this->assertNull($result->disabled_reason);
    }

    public function test_disable_store_ai(): void
    {
        $result = $this->billingService->disableStoreAI($this->store->id, $this->org->id, 'manual_disable');
        $this->assertFalse($result->is_ai_enabled);
        $this->assertEquals('manual_disable', $result->disabled_reason);
    }

    public function test_update_store_billing_config(): void
    {
        $result = $this->billingService->updateStoreBillingConfig($this->store->id, $this->org->id, [
            'monthly_limit_usd' => 100.00,
            'custom_margin_percentage' => 25.0,
            'notes' => 'Premium store',
        ]);

        $this->assertEquals(100.00, (float) $result->monthly_limit_usd);
        $this->assertEquals(25.0, (float) $result->custom_margin_percentage);
        $this->assertEquals('Premium store', $result->notes);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 7: STORE-SIDE API — Billing Summary
    // GET /api/v2/wameed-ai/billing/summary
    // ═══════════════════════════════════════════════════════════

    public function test_billing_summary_returns_correct_structure(): void
    {
        $this->createUsageLog($this->store->id, 'smart_reorder', 1.50);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'config' => [
                        'is_ai_enabled', 'monthly_limit_usd', 'effective_limit_usd',
                        'margin_percentage', 'disabled_reason', 'disabled_at',
                    ],
                    'current_month' => [
                        'year', 'month', 'total_requests', 'total_tokens',
                        'raw_cost_usd', 'margin_percentage', 'margin_amount_usd',
                        'billed_cost_usd', 'limit_usd', 'limit_percentage',
                        'by_feature',
                    ],
                    'recent_invoices',
                ],
            ]);
    }

    public function test_billing_summary_field_types_match_flutter(): void
    {
        $this->createUsageLog($this->store->id, 'smart_reorder', 1.50);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/summary');

        $data = $response->json('data');

        // Config types
        $this->assertIsBool($data['config']['is_ai_enabled']);
        $this->assertIsNumeric($data['config']['monthly_limit_usd']);
        $this->assertIsNumeric($data['config']['effective_limit_usd']);
        $this->assertIsNumeric($data['config']['margin_percentage']);

        // Current month types
        $this->assertIsInt($data['current_month']['year']);
        $this->assertIsInt($data['current_month']['month']);
        $this->assertIsInt($data['current_month']['total_requests']);
        $this->assertIsInt($data['current_month']['total_tokens']);
        $this->assertIsNumeric($data['current_month']['raw_cost_usd']);
        $this->assertIsNumeric($data['current_month']['billed_cost_usd']);
    }

    public function test_billing_summary_shows_correct_cost_with_margin(): void
    {
        $this->createUsageLog($this->store->id, 'smart_reorder', 2.00);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/summary');

        $month = $response->json('data.current_month');
        $this->assertEquals(2.0, $month['raw_cost_usd']);
        $this->assertEquals(20.0, $month['margin_percentage']);
        $this->assertEquals(0.4, $month['margin_amount_usd']);
        $this->assertEquals(2.4, $month['billed_cost_usd']);
    }

    public function test_billing_summary_shows_feature_breakdown(): void
    {
        $this->createUsageLog($this->store->id, 'smart_reorder', 1.50);
        $this->createUsageLog($this->store->id, 'daily_summary', 0.50);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/summary');

        $features = $response->json('data.current_month.by_feature');
        $this->assertCount(2, $features);

        $slugs = collect($features)->pluck('feature_slug')->toArray();
        $this->assertContains('smart_reorder', $slugs);
        $this->assertContains('daily_summary', $slugs);
    }

    public function test_billing_summary_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/wameed-ai/billing/summary');
        $response->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 8: STORE-SIDE API — Billing Invoices
    // GET /api/v2/wameed-ai/billing/invoices
    // ═══════════════════════════════════════════════════════════

    public function test_billing_invoices_returns_paginated_list(): void
    {
        $this->createTestInvoice($this->store->id, 10.00);
        $this->createTestInvoice($this->store->id, 15.00, 'paid', 5, 2025);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/invoices');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'data' => [
                        '*' => [
                            'id', 'invoice_number', 'year', 'month',
                            'billed_amount_usd', 'status', 'due_date',
                        ],
                    ],
                    'current_page', 'last_page', 'total', 'per_page',
                ],
            ]);
    }

    public function test_billing_invoices_only_returns_own_store(): void
    {
        $this->createTestInvoice($this->store->id, 10.00);
        $this->createTestInvoice($this->store2->id, 15.00);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/invoices');

        $invoices = $response->json('data.data');
        $this->assertCount(1, $invoices);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 9: STORE-SIDE API — Invoice Detail
    // GET /api/v2/wameed-ai/billing/invoices/{invoiceId}
    // ═══════════════════════════════════════════════════════════

    public function test_billing_invoice_detail_returns_full_data(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);
        $this->createTestInvoiceItem($invoice->id, 'smart_reorder', 7.00);
        $this->createTestInvoiceItem($invoice->id, 'daily_summary', 3.00);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson("/api/v2/wameed-ai/billing/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'id', 'invoice_number', 'year', 'month',
                    'period_start', 'period_end',
                    'total_requests', 'total_tokens',
                    'raw_cost_usd', 'margin_percentage', 'margin_amount_usd',
                    'billed_amount_usd', 'status', 'due_date',
                    'items' => [
                        '*' => [
                            'feature_slug', 'feature_name', 'feature_name_ar',
                            'request_count', 'total_tokens',
                            'raw_cost_usd', 'billed_cost_usd',
                        ],
                    ],
                    'payments',
                ],
            ]);
    }

    public function test_billing_invoice_detail_rejects_other_store(): void
    {
        $invoice = $this->createTestInvoice($this->store2->id, 10.00);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson("/api/v2/wameed-ai/billing/invoices/{$invoice->id}");

        $response->assertNotFound();
    }

    public function test_billing_invoice_detail_field_types(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);
        $this->createTestInvoiceItem($invoice->id, 'smart_reorder', 10.00);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson("/api/v2/wameed-ai/billing/invoices/{$invoice->id}");

        $data = $response->json('data');

        $this->assertIsString($data['id']);
        $this->assertIsString($data['invoice_number']);
        $this->assertIsInt($data['year']);
        $this->assertIsInt($data['month']);
        $this->assertIsString($data['period_start']);
        $this->assertIsString($data['period_end']);
        $this->assertIsInt($data['total_requests']);
        $this->assertIsNumeric($data['raw_cost_usd']);
        $this->assertIsNumeric($data['billed_amount_usd']);
        $this->assertIsString($data['status']);
        $this->assertIsString($data['due_date']);
        $this->assertIsArray($data['items']);
        $this->assertIsArray($data['payments']);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 10: ADMIN API — Settings
    // GET/PUT /api/v2/admin/wameed-ai/billing/settings
    // ═══════════════════════════════════════════════════════════

    public function test_admin_get_billing_settings(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v2/admin/wameed-ai/billing/settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    '*' => ['key', 'value', 'description'],
                ],
            ]);

        $settings = collect($response->json('data'));
        $this->assertTrue($settings->pluck('key')->contains('margin_percentage'));
        $this->assertTrue($settings->pluck('key')->contains('billing_enabled'));
        $this->assertTrue($settings->pluck('key')->contains('auto_disable_grace_days'));
    }

    public function test_admin_update_billing_settings(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->putJson('/api/v2/admin/wameed-ai/billing/settings', [
                'settings' => [
                    ['key' => 'margin_percentage', 'value' => '25'],
                    ['key' => 'auto_disable_grace_days', 'value' => '7'],
                ],
            ]);

        $response->assertOk();
        $this->assertEquals('25', AIBillingSetting::getValue('margin_percentage'));
        $this->assertEquals('7', AIBillingSetting::getValue('auto_disable_grace_days'));
    }

    public function test_admin_settings_requires_manage_permission(): void
    {
        // Create a user with NO admin permissions
        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier-billing@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $cashierToken = $cashier->createToken('test', ['*'])->plainTextToken;

        $cashierRole = Role::create([
            'name' => 'cashier_billing_test',
            'guard_name' => 'sanctum',
            'store_id' => $this->store->id,
        ]);
        Permission::firstOrCreate(['name' => 'wameed_ai.view', 'guard_name' => 'sanctum']);
        $cashierRole->givePermissionTo(['wameed_ai.view']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $cashier->assignRole($cashierRole);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$cashierToken}",
            'X-Store-Id' => $this->store->id,
            'X-Organization-Id' => $this->org->id,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/admin/wameed-ai/billing/settings');

        $response->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 11: ADMIN API — Dashboard
    // GET /api/v2/admin/wameed-ai/billing/dashboard
    // ═══════════════════════════════════════════════════════════

    public function test_admin_billing_dashboard_returns_overview(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v2/admin/wameed-ai/billing/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'settings', 'invoice_stats', 'store_stats',
                    'top_stores', 'revenue_trend',
                ],
            ]);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 12: ADMIN API — Invoice Management
    // ═══════════════════════════════════════════════════════════

    public function test_admin_list_invoices(): void
    {
        $this->createTestInvoice($this->store->id, 10.00);
        $this->createTestInvoice($this->store2->id, 20.00);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v2/admin/wameed-ai/billing/invoices');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->json('data.total'));
    }

    public function test_admin_invoice_detail(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v2/admin/wameed-ai/billing/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id);
    }

    public function test_admin_mark_invoice_paid(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v2/admin/wameed-ai/billing/invoices/{$invoice->id}/mark-paid", [
                'payment_method' => 'bank_transfer',
                'reference' => 'ADMIN-PAY-001',
            ]);

        $response->assertOk();
        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_admin_record_partial_payment(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v2/admin/wameed-ai/billing/invoices/{$invoice->id}/record-payment", [
                'amount_usd' => 5.00,
                'payment_method' => 'cash',
                'reference' => 'PARTIAL-001',
                'notes' => 'First installment',
            ]);

        $response->assertCreated();
        $this->assertEquals(1, AIBillingPayment::where('ai_billing_invoice_id', $invoice->id)->count());
    }

    public function test_admin_generate_invoices(): void
    {
        $lastMonth = now()->subMonth();
        $this->createUsageLog($this->store->id, 'smart_reorder', 5.00, 'success', $lastMonth);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v2/admin/wameed-ai/billing/generate-invoices', [
                'year' => $lastMonth->year,
                'month' => $lastMonth->month,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.generated', 1);
    }

    public function test_admin_check_overdue(): void
    {
        $invoice = $this->createTestInvoice($this->store->id, 10.00);
        $invoice->update(['due_date' => now()->subDays(1)]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v2/admin/wameed-ai/billing/check-overdue');

        $response->assertOk();
        $invoice->refresh();
        $this->assertEquals('overdue', $invoice->status);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 13: ADMIN API — Store Config Management
    // ═══════════════════════════════════════════════════════════

    public function test_admin_list_store_configs(): void
    {
        AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v2/admin/wameed-ai/billing/stores');

        $response->assertOk();
    }

    public function test_admin_store_config_detail(): void
    {
        AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v2/admin/wameed-ai/billing/stores/{$this->store->id}");

        $response->assertOk();
    }

    public function test_admin_update_store_config(): void
    {
        AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v2/admin/wameed-ai/billing/stores/{$this->store->id}", [
                'monthly_limit_usd' => 200.00,
                'custom_margin_percentage' => 15.0,
                'notes' => 'VIP store',
            ]);

        $response->assertOk();
        $config = AIStoreBillingConfig::where('store_id', $this->store->id)->first();
        $this->assertEquals(200.00, (float) $config->monthly_limit_usd);
        $this->assertEquals(15.0, (float) $config->custom_margin_percentage);
    }

    public function test_admin_toggle_store_ai_enable(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['is_ai_enabled' => false]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v2/admin/wameed-ai/billing/stores/{$this->store->id}/toggle-ai", [
                'enabled' => true,
            ]);

        $response->assertOk();
        $config->refresh();
        $this->assertTrue($config->is_ai_enabled);
    }

    public function test_admin_toggle_store_ai_disable(): void
    {
        AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v2/admin/wameed-ai/billing/stores/{$this->store->id}/toggle-ai", [
                'enabled' => false,
                'reason' => 'testing_disable',
            ]);

        $response->assertOk();
        $config = AIStoreBillingConfig::where('store_id', $this->store->id)->first();
        $this->assertFalse($config->is_ai_enabled);
        $this->assertEquals('testing_disable', $config->disabled_reason);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 14: EDGE CASES & DECIMAL PRECISION
    // ═══════════════════════════════════════════════════════════

    public function test_three_decimal_place_pricing(): void
    {
        $this->createUsageLog($this->store->id, 'smart_reorder', 0.005);

        $billedCost = $this->billingService->getCurrentMonthBilledCost($this->store->id);
        // 0.005 * 1.20 = 0.006
        $this->assertEquals(0.006, $billedCost);
    }

    public function test_billing_summary_with_no_usage(): void
    {
        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/summary');

        $response->assertOk();
        $month = $response->json('data.current_month');
        $this->assertEquals(0, $month['total_requests']);
        $this->assertEquals(0, $month['raw_cost_usd']);
        $this->assertEquals(0, $month['billed_cost_usd']);
    }

    public function test_billing_summary_with_zero_limit_shows_no_limit(): void
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($this->store->id, $this->org->id);
        $config->update(['monthly_limit_usd' => 0]);
        AIBillingSetting::setValue('global_monthly_limit_usd', '0');

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/summary');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.current_month.limit_usd'));
        $this->assertEquals(0, $response->json('data.current_month.limit_percentage'));
    }

    public function test_store_billing_summary_shows_recent_invoices(): void
    {
        $this->createTestInvoice($this->store->id, 10.00, 'paid', 1, 2025);
        $this->createTestInvoice($this->store->id, 15.00, 'pending', 2, 2025);

        $response = $this->withHeaders($this->storeHeaders())
            ->getJson('/api/v2/wameed-ai/billing/summary');

        $invoices = $response->json('data.recent_invoices');
        $this->assertCount(2, $invoices);
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function storeHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->ownerToken}",
            'X-Store-Id' => $this->store->id,
            'X-Organization-Id' => $this->org->id,
            'Accept' => 'application/json',
        ];
    }

    private function adminHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Store-Id' => $this->store->id,
            'X-Organization-Id' => $this->org->id,
            'Accept' => 'application/json',
        ];
    }

    private function createUsageLog(
        string $storeId,
        string $featureSlug,
        float $cost,
        string $status = 'success',
        ?Carbon $createdAt = null,
    ): AIUsageLog {
        return AIUsageLog::create([
            'organization_id' => $this->org->id,
            'store_id' => $storeId,
            'user_id' => $this->owner->id,
            'feature_slug' => $featureSlug,
            'model_used' => 'gpt-4o-mini',
            'input_tokens' => 500,
            'output_tokens' => 200,
            'total_tokens' => 700,
            'estimated_cost_usd' => $cost,
            'status' => $status,
            'latency_ms' => 1200,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    private static int $invoiceCounter = 0;

    private function createTestInvoice(
        string $storeId,
        float $amount,
        string $status = 'pending',
        int $month = 6,
        int $year = 2025,
    ): AIBillingInvoice {
        self::$invoiceCounter++;
        $rawCost = round($amount / 1.2, 5);
        $marginAmount = round($rawCost * 0.2, 5);

        return AIBillingInvoice::create([
            'store_id' => $storeId,
            'organization_id' => $this->org->id,
            'invoice_number' => sprintf('AI-TEST-%04d-%04d%02d', self::$invoiceCounter, $year, $month),
            'year' => $year,
            'month' => $month,
            'period_start' => Carbon::create($year, $month, 1)->toDateString(),
            'period_end' => Carbon::create($year, $month, 1)->endOfMonth()->toDateString(),
            'total_requests' => 50,
            'total_tokens' => 35000,
            'raw_cost_usd' => $rawCost,
            'margin_percentage' => 20.0,
            'margin_amount_usd' => $marginAmount,
            'billed_amount_usd' => $amount,
            'status' => $status,
            'due_date' => Carbon::create($year, $month, 1)->addMonth()->addDays(5)->toDateString(),
            'generated_at' => now(),
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    private function createTestInvoiceItem(string $invoiceId, string $featureSlug, float $billedCost): AIBillingInvoiceItem
    {
        $rawCost = round($billedCost / 1.2, 5);

        return AIBillingInvoiceItem::create([
            'ai_billing_invoice_id' => $invoiceId,
            'feature_slug' => $featureSlug,
            'feature_name' => ucfirst(str_replace('_', ' ', $featureSlug)),
            'feature_name_ar' => $featureSlug,
            'request_count' => 25,
            'total_tokens' => 17500,
            'raw_cost_usd' => $rawCost,
            'billed_cost_usd' => $billedCost,
            'created_at' => now(),
        ]);
    }

    private function seedData(): void
    {
        $this->org = Organization::create([
            'name' => 'Billing Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Billing Test Store',
            'business_type' => 'grocery',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->store2 = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Second Store',
            'business_type' => 'grocery',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $this->owner = User::create([
            'name' => 'Billing Owner',
            'email' => 'billing-owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Billing Admin',
            'email' => 'billing-admin@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->adminToken = $this->admin->createToken('test', ['*'])->plainTextToken;

        // Roles
        $ownerRole = Role::create([
            'name' => 'owner',
            'guard_name' => 'sanctum',
            'store_id' => $this->store->id,
        ]);

        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'sanctum',
            'store_id' => $this->store->id,
        ]);

        // Permissions
        foreach ([
            'wameed_ai.view', 'wameed_ai.manage', 'wameed_ai.use',
            'admin.wameed_ai.manage', 'admin.wameed_ai.view',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        $ownerRole->givePermissionTo(['wameed_ai.view', 'wameed_ai.manage', 'wameed_ai.use']);
        $adminRole->givePermissionTo([
            'wameed_ai.view', 'wameed_ai.manage', 'wameed_ai.use',
            'admin.wameed_ai.manage', 'admin.wameed_ai.view',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->owner->assignRole($ownerRole);
        $this->admin->assignRole($adminRole);

        // Feature definitions
        $this->feature = AIFeatureDefinition::create([
            'slug' => 'smart_reorder',
            'name' => 'Smart Reorder',
            'name_ar' => 'إعادة الطلب الذكي',
            'description' => 'AI reorder suggestions',
            'category' => 'inventory',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);

        $this->feature2 = AIFeatureDefinition::create([
            'slug' => 'daily_summary',
            'name' => 'Daily Summary',
            'name_ar' => 'ملخص يومي',
            'description' => 'AI daily sales summary',
            'category' => 'sales',
            'is_enabled' => true,
            'default_model' => 'gpt-4o-mini',
            'daily_limit' => 50,
            'monthly_limit' => 500,
        ]);

        // Seed billing settings
        foreach ([
            ['key' => 'margin_percentage', 'value' => '20', 'description' => 'Margin percentage on AI costs'],
            ['key' => 'auto_disable_grace_days', 'value' => '5', 'description' => 'Days before auto-disable'],
            ['key' => 'global_monthly_limit_usd', 'value' => '500', 'description' => 'Global monthly limit'],
            ['key' => 'billing_enabled', 'value' => 'true', 'description' => 'Whether billing is enabled'],
            ['key' => 'invoice_generation_day', 'value' => '1', 'description' => 'Day of month to generate invoices'],
            ['key' => 'currency', 'value' => 'USD', 'description' => 'Billing currency'],
            ['key' => 'min_billable_amount_usd', 'value' => '0.01', 'description' => 'Minimum billable amount'],
        ] as $setting) {
            AIBillingSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }

    private function createTables(): void
    {
        // AI tables needed for billing
        if (!Schema::hasTable('ai_feature_definitions')) {
            Schema::create('ai_feature_definitions', function ($table) {
                $table->uuid('id')->primary();
                $table->string('slug', 100)->unique();
                $table->string('name', 255);
                $table->string('name_ar', 255)->nullable();
                $table->text('description')->nullable();
                $table->text('description_ar')->nullable();
                $table->string('category', 50);
                $table->string('icon', 100)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_premium')->default(false);
                $table->string('default_model', 100)->default('gpt-4o-mini');
                $table->integer('default_max_tokens')->default(2048);
                $table->decimal('cost_per_request_estimate', 10, 6)->default(0.001);
                $table->integer('daily_limit')->default(50);
                $table->integer('monthly_limit')->default(500);
                $table->json('requires_subscription_plan')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('store_id');
                $table->uuid('user_id')->nullable();
                $table->uuid('ai_feature_definition_id')->nullable();
                $table->string('feature_slug', 100);
                $table->string('model_used', 100);
                $table->integer('input_tokens')->default(0);
                $table->integer('output_tokens')->default(0);
                $table->integer('total_tokens')->default(0);
                $table->decimal('estimated_cost_usd', 10, 6)->default(0);
                $table->string('request_payload_hash', 255)->nullable();
                $table->boolean('response_cached')->default(false);
                $table->integer('latency_ms')->default(0);
                $table->string('status', 20)->default('success');
                $table->text('error_message')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // Billing tables
        if (!Schema::hasTable('ai_billing_settings')) {
            Schema::create('ai_billing_settings', function ($table) {
                $table->uuid('id')->primary();
                $table->string('key', 100)->unique();
                $table->text('value');
                $table->string('description', 500)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_store_billing_configs')) {
            Schema::create('ai_store_billing_configs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('organization_id');
                $table->boolean('is_ai_enabled')->default(true);
                $table->decimal('monthly_limit_usd', 12, 3)->default(0);
                $table->decimal('custom_margin_percentage', 5, 3)->nullable();
                $table->string('disabled_reason', 100)->nullable();
                $table->timestamp('disabled_at')->nullable();
                $table->timestamp('enabled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique('store_id');
            });
        }

        if (!Schema::hasTable('ai_billing_invoices')) {
            Schema::create('ai_billing_invoices', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->index();
                $table->uuid('organization_id');
                $table->string('invoice_number', 50)->unique();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedInteger('total_requests')->default(0);
                $table->unsignedBigInteger('total_tokens')->default(0);
                $table->decimal('raw_cost_usd', 12, 5)->default(0);
                $table->decimal('margin_percentage', 5, 3)->default(20);
                $table->decimal('margin_amount_usd', 12, 5)->default(0);
                $table->decimal('billed_amount_usd', 12, 5)->default(0);
                $table->string('status', 20)->default('pending');
                $table->date('due_date');
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_reference', 255)->nullable();
                $table->text('payment_notes')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                $table->unique(['store_id', 'year', 'month']);
            });
        }

        if (!Schema::hasTable('ai_billing_invoice_items')) {
            Schema::create('ai_billing_invoice_items', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('ai_billing_invoice_id')->index();
                $table->string('feature_slug', 100);
                $table->string('feature_name', 255);
                $table->string('feature_name_ar', 255)->nullable();
                $table->unsignedInteger('request_count')->default(0);
                $table->unsignedBigInteger('total_tokens')->default(0);
                $table->decimal('raw_cost_usd', 12, 5)->default(0);
                $table->decimal('billed_cost_usd', 12, 5)->default(0);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('ai_billing_payments')) {
            Schema::create('ai_billing_payments', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('ai_billing_invoice_id')->index();
                $table->decimal('amount_usd', 12, 5);
                $table->string('payment_method', 50)->default('manual');
                $table->string('reference', 255)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // Monthly usage summary (referenced by service for aggregation)
        if (!Schema::hasTable('ai_monthly_usage_summaries')) {
            Schema::create('ai_monthly_usage_summaries', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('store_id');
                $table->string('feature_slug', 100);
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedInteger('request_count')->default(0);
                $table->unsignedBigInteger('total_tokens')->default(0);
                $table->decimal('total_cost_usd', 12, 5)->default(0);
                $table->decimal('avg_latency_ms', 10, 2)->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->unsignedInteger('cache_hit_count')->default(0);
                $table->timestamps();
            });
        }
    }
}
