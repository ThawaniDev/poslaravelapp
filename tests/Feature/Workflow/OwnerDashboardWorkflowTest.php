<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * OWNER DASHBOARD WORKFLOW TESTS
 *
 * Verifies real-time dashboard stats, sales trends, top products,
 * low stock, active cashiers, financial summary, branches overview.
 *
 * Cross-references: Workflows #681-695
 */
class OwnerDashboardWorkflowTest extends WorkflowTestCase
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
            'name' => 'Dashboard Org',
            'name_ar' => 'منظمة لوحة',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Dashboard Store',
            'name_ar' => 'متجر لوحة',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Dashboard Owner',
            'email' => 'dashboard-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);

        // Seed some products and stock for dashboard queries
        $cat = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Drinks', 'name_ar' => 'مشروبات',
            'is_active' => true, 'sync_version' => 1,
        ]);

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat->id,
            'name' => 'Dashboard Coffee', 'name_ar' => 'قهوة',
            'sku' => 'DASH-001', 'barcode' => '6281001250001',
            'sell_price' => 15.00, 'cost_price' => 5.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'reorder_point' => 10,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);

        // Seed daily summary for trend data
        DB::table('daily_sales_summary')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'date' => now()->toDateString(),
            'total_transactions' => 25,
            'total_revenue' => 1500.00,
            'total_cost' => 800.00,
            'total_discount' => 0,
            'total_tax' => 225.00,
            'total_refunds' => 0,
            'net_revenue' => 1500.00,
            'cash_revenue' => 900.00,
            'card_revenue' => 600.00,
            'other_revenue' => 0,
            'avg_basket_size' => 60.00,
            'unique_customers' => 20,
        ]);
    }

    // ══════════════════════════════════════════════
    //  DASHBOARD STATS — WF #681-685
    // ══════════════════════════════════════════════

    /** @test */
    public function wf681_dashboard_stats(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf682_sales_trend(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/sales-trend');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf683_top_products(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/top-products');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf684_low_stock_dashboard(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/low-stock');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf685_active_cashiers(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/active-cashiers');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  FINANCIAL & DETAILED — WF #686-690
    // ══════════════════════════════════════════════

    /** @test */
    public function wf686_recent_orders(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/recent-orders');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf687_financial_summary(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/financial-summary');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf688_hourly_sales(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/hourly-sales');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf689_branches_overview(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/branches');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf690_staff_performance_dashboard(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/owner-dashboard/staff-performance');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }
}
