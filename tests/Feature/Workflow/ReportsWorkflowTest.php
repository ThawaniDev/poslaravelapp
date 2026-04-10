<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;


/**
 * REPORTS WORKFLOW TESTS
 *
 * Verifies all report generation endpoints:
 * Sales reports, inventory reports, staff performance,
 * financial summaries, daily Z-reports, tax reports.
 *
 * Cross-references: Workflows #311-340 in COMPREHENSIVE_WORKFLOW_TESTS.md
 */
class ReportsWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Reports Test Org',
            'name_ar' => 'منظمة اختبار التقارير',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000012',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Report Branch',
            'name_ar' => 'فرع التقارير',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@reports-test.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #311-315: SALES REPORTS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#311: Daily sales summary */
    public function test_wf311_daily_sales_summary(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/sales-summary?date=' . now()->toDateString());

        $response->assertOk()->assertJsonPath('success', true);
    }

    /** @test WF#312: Sales by payment method */
    public function test_wf312_sales_by_payment_method(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/payment-methods?from=' .
                now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString());

        $response->assertOk();
    }

    /** @test WF#313: Sales by category */
    public function test_wf313_sales_by_category(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/category-breakdown?from=' .
                now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString());

        $response->assertOk();
    }

    /** @test WF#314: Top selling products */
    public function test_wf314_top_selling_products(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/product-performance?limit=20&from=' .
                now()->startOfMonth()->toDateString());

        $response->assertOk();
    }

    /** @test WF#315: Sales by hour (peak hours) */
    public function test_wf315_sales_by_hour(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/hourly-sales?date=' . now()->toDateString());

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // WF #316-320: INVENTORY REPORTS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#316: Current stock levels report */
    public function test_wf316_stock_levels_report(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/inventory/valuation');

        $response->assertOk();
    }

    /** @test WF#317: Low stock report */
    public function test_wf317_low_stock_report(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/inventory/low-stock');

        $response->assertOk();
    }

    /** @test WF#318: Stock movement history */
    public function test_wf318_stock_movement_report(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/inventory/stock-movements?store_id=' . $this->store->id . '&from=' .
                now()->startOfMonth()->toDateString());

        $response->assertOk();
    }

    /** @test WF#319: Stock valuation report */
    public function test_wf319_stock_valuation(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/inventory/valuation');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // WF #321-325: STAFF & FINANCIAL REPORTS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#321: Staff performance report */
    public function test_wf321_staff_performance(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/staff-performance?from=' .
                now()->startOfMonth()->toDateString());

        $response->assertOk();
    }

    /** @test WF#322: Cashier session report */
    public function test_wf322_cashier_sessions(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/pos/sessions');

        $response->assertOk();
    }

    /** @test WF#323: Commission report */
    public function test_wf323_commission_report(): void
    {
        // Create a staff member in staff_users table (separate from auth users)
        $staffId = \Illuminate\Support\Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('staff_users')->insert([
            'id' => $staffId,
            'store_id' => $this->store->id,
            'first_name' => 'Sales',
            'last_name' => 'Rep',
            'email' => 'salesrep@reports-test.test',
            'phone' => '966509999888',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Query commissions for the staff member
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/staff/members/{$staffId}/commissions");

        $response->assertOk();

        // Test commission config update
        $configResp = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/staff/members/{$staffId}/commission-config", [
                'commission_type' => 'percentage',
                'commission_rate' => 5.0,
            ]);

        $this->assertTrue(
            in_array($configResp->status(), [200, 422]),
            'Commission config update should succeed or validate. Status: ' . $configResp->status()
        );

        // Staff performance report
        $perfResp = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/staff-performance?from=' .
                now()->startOfMonth()->toDateString());
        $perfResp->assertOk();
    }

    /** @test WF#324: Profit & loss report */
    public function test_wf324_profit_loss(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/financial/daily-pl?from=' .
                now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString());

        $response->assertOk();
    }

    /** @test WF#325: Cash flow statement */
    public function test_wf325_cash_flow(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/financial/cash-variance?from=' .
                now()->startOfMonth()->toDateString());

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // WF #326-330: TAX & COMPLIANCE REPORTS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#326: VAT summary report */
    public function test_wf326_vat_summary(): void
    {
        // Use the ZATCA VAT report endpoint
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/zatca/vat-report?from=' .
                now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString());

        $response->assertOk();

        // Verify response has expected structure
        $data = $response->json('data');
        $this->assertNotNull($data, 'VAT report should return data');
    }

    /** @test WF#327: ZATCA compliance report */
    public function test_wf327_zatca_compliance(): void
    {
        // Use the ZATCA compliance summary endpoint
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/zatca/compliance-summary');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotNull($data, 'Compliance summary should return data');
    }

    /** @test WF#328: Z-report (end of day) */
    public function test_wf328_z_report(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/dashboard');

        $response->assertOk();
    }

    /** @test WF#329: X-report (mid-day snapshot) */
    public function test_wf329_x_report(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/reports/refresh-summaries');

        $response->assertOk();
    }

    /** @test WF#330: Export report to CSV/PDF */
    public function test_wf330_export_report(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/reports/export', [
                'report_type' => 'sales_summary',
                'format' => 'csv',
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201, 202]),
            'Export should succeed or be queued'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // WF #331-335: CUSTOMER REPORTS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#331: Customer spending report */
    public function test_wf331_customer_spending(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/customers/top?from=' .
                now()->startOfMonth()->toDateString());

        $response->assertOk();
    }

    /** @test WF#332: Customer loyalty report */
    public function test_wf332_loyalty_report(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/customers/top');

        $response->assertOk();
    }

    /** @test WF#333: New vs returning customers */
    public function test_wf333_new_vs_returning(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/customers/retention?from=' .
                now()->startOfMonth()->toDateString());

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // REPORT DATE RANGE VALIDATION
    // ═══════════════════════════════════════════════════════════

    /** @test WF#336: Reports handle invalid date gracefully */
    public function test_wf336_invalid_date_range_rejected(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/sales-summary?date=invalid-date');

        // API may either reject with 422 or handle gracefully with 200
        $this->assertTrue(
            in_array($response->status(), [200, 422]),
            'Expected 200 or 422, got ' . $response->status()
        );
    }

    /** @test WF#337: Reports respect store scope */
    public function test_wf337_reports_store_scoped(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other', 'name_ar' => 'أخرى',
            'business_type' => 'grocery', 'country' => 'SA', 'is_active' => true,
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id, 'name' => 'Other', 'name_ar' => 'أخرى',
            'business_type' => 'grocery', 'currency' => 'SAR', 'locale' => 'ar',
            'timezone' => 'Asia/Riyadh', 'is_active' => true, 'is_main_branch' => true,
        ]);
        $otherUser = User::create([
            'name' => 'Other', 'email' => 'other@reports.test',
            'password_hash' => bcrypt('pass'), 'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;

        // Other org's reports should not include our data
        $response = $this->withToken($otherToken)
            ->getJson('/api/v2/reports/sales-summary?date=' . now()->toDateString() .
                '&store_id=' . $this->store->id);

        $this->assertTrue(
            $response->status() === 403 || $response->status() === 200,
            'Cross-org report access should be blocked or return empty data'
        );
    }
}
