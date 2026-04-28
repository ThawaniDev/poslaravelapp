<?php

namespace Tests\Feature\Report;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\Report\Models\ScheduledReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportExtendedApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    private Organization $otherOrg;
    private Store $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Report Ext Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@report-ext.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function createCategoryAndProduct(string $name = 'Test Product', string $sku = 'TST-001'): array
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => $name,
            'sku' => $sku,
            'sell_price' => 100.00,
            'cost_price' => 50.00,
            'is_active' => true,
        ]);

        return [$category, $product];
    }

    // ─── Slow Movers ─────────────────────────────────────────

    public function test_slow_movers_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/products/slow-movers', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_slow_movers_returns_lowest_sellers(): void
    {
        [$cat, $prod1] = $this->createCategoryAndProduct('Fast Seller', 'FAST-001');
        $prod2 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat->id,
            'name' => 'Slow Seller',
            'sku' => 'SLOW-001',
            'sell_price' => 10.00,
            'cost_price' => 5.00,
            'is_active' => true,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $prod1->id,
            'date' => '2024-06-01',
            'quantity_sold' => 100,
            'revenue' => 10000.00,
        ]);
        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $prod2->id,
            'date' => '2024-06-01',
            'quantity_sold' => 2,
            'revenue' => 20.00,
        ]);

        $response = $this->getJson('/api/v2/reports/products/slow-movers', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Slow seller first (lowest quantity)
        $this->assertEquals('Slow Seller', $data[0]['product_name']);
        $this->assertEquals(2.0, $data[0]['total_quantity']);
    }

    // ─── Product Margin ──────────────────────────────────────

    public function test_product_margin_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/products/margin', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_product_margin_returns_margin_data(): void
    {
        [$cat, $product] = $this->createCategoryAndProduct('Widget', 'WDG-001');

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'date' => '2024-06-01',
            'quantity_sold' => 10,
            'revenue' => 1000.00,
            'cost' => 600.00,
        ]);

        $response = $this->getJson('/api/v2/reports/products/margin', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(1000.0, $data[0]['total_revenue']);
        $this->assertEquals(600.0, $data[0]['total_cost']);
        $this->assertEquals(400.0, $data[0]['profit']);
        $this->assertEquals(40.0, $data[0]['margin_percent']); // 400/1000 * 100
        $this->assertEquals(66.67, $data[0]['markup_percent']); // 400/600 * 100
    }

    // ─── Inventory Valuation ─────────────────────────────────

    public function test_inventory_valuation_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/inventory/valuation', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.total_stock_value', 0)
            ->assertJsonPath('data.product_count', 0);
    }

    public function test_inventory_valuation_returns_stock_value(): void
    {
        [$cat, $product] = $this->createCategoryAndProduct();

        \DB::table('stock_levels')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
            'reorder_point' => 10,
            'average_cost' => 25.00,
        ]);

        $response = $this->getJson('/api/v2/reports/inventory/valuation', $this->authHeader());
        $data = $response->json('data');
        $this->assertEquals(1250.0, $data['total_stock_value']); // 50 * 25
        $this->assertEquals(50.0, $data['total_items']);
        $this->assertEquals(1, $data['product_count']);
        $this->assertEquals('Test Product', $data['products'][0]['product_name']);
    }

    public function test_inventory_valuation_store_isolation(): void
    {
        [$cat, $product] = $this->createCategoryAndProduct();

        \DB::table('stock_levels')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'product_id' => $product->id, 'quantity' => 10, 'reserved_quantity' => 0, 'reorder_point' => 5, 'average_cost' => 10.00],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->otherStore->id, 'product_id' => $product->id, 'quantity' => 999, 'reserved_quantity' => 0, 'reorder_point' => 5, 'average_cost' => 100.00],
        ]);

        $response = $this->getJson('/api/v2/reports/inventory/valuation', $this->authHeader());
        $data = $response->json('data');
        $this->assertEquals(100.0, $data['total_stock_value']); // 10 * 10, NOT 999 * 100
    }

    // ─── Inventory Low Stock ─────────────────────────────────

    public function test_inventory_low_stock_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/inventory/low-stock', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_inventory_low_stock_returns_below_reorder(): void
    {
        [$cat, $prod1] = $this->createCategoryAndProduct('Low Item', 'LOW-001');
        $prod2 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat->id,
            'name' => 'OK Item',
            'sku' => 'OK-001',
            'sell_price' => 20.00,
            'cost_price' => 10.00,
            'is_active' => true,
        ]);

        \DB::table('stock_levels')->insert([
            // Below reorder point
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'product_id' => $prod1->id, 'quantity' => 3, 'reserved_quantity' => 0, 'reorder_point' => 10, 'max_stock_level' => 100, 'average_cost' => 5.00],
            // Above reorder point
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'product_id' => $prod2->id, 'quantity' => 50, 'reserved_quantity' => 0, 'reorder_point' => 10, 'max_stock_level' => 100, 'average_cost' => 5.00],
        ]);

        $response = $this->getJson('/api/v2/reports/inventory/low-stock', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Low Item', $data[0]['product_name']);
        $this->assertEquals(3.0, $data[0]['current_stock']);
        $this->assertEquals(10.0, $data[0]['reorder_point']);
        $this->assertEquals(7.0, $data[0]['deficit']);
    }

    // ─── Inventory Shrinkage ─────────────────────────────────

    public function test_inventory_shrinkage_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/inventory/shrinkage', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.by_reason', [])
            ->assertJsonPath('data.by_product', []);
    }

    public function test_inventory_shrinkage_returns_adjustments(): void
    {
        [$cat, $product] = $this->createCategoryAndProduct();

        \DB::table('stock_movements')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'product_id' => $product->id, 'type' => 'adjustment', 'quantity' => -5, 'unit_cost' => 10.00, 'reason' => 'damaged', 'created_at' => '2024-06-01 10:00:00'],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'product_id' => $product->id, 'type' => 'adjustment', 'quantity' => -3, 'unit_cost' => 10.00, 'reason' => 'expired', 'created_at' => '2024-06-02 10:00:00'],
            // Non-adjustment should be excluded
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'product_id' => $product->id, 'type' => 'sale', 'quantity' => -10, 'unit_cost' => 10.00, 'reason' => null, 'created_at' => '2024-06-01 10:00:00'],
        ]);

        $response = $this->getJson('/api/v2/reports/inventory/shrinkage', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(2, $data['by_reason']); // damaged + expired
        $this->assertCount(1, $data['by_product']);
    }

    // ─── Inventory Turnover ──────────────────────────────────

    public function test_inventory_turnover_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/inventory/turnover', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_inventory_turnover_returns_ratios(): void
    {
        [$cat, $product] = $this->createCategoryAndProduct();

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'date' => now()->subDays(5)->toDateString(),
            'quantity_sold' => 20,
            'revenue' => 2000.00,
            'cost' => 1000.00,
        ]);

        \DB::table('stock_levels')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
            'reorder_point' => 10,
            'average_cost' => 50.00,
        ]);

        $response = $this->getJson('/api/v2/reports/inventory/turnover', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(1000.0, $data[0]['cogs']);
        $this->assertEquals(50.0, $data[0]['current_stock']);
        // avg_inventory_value = 50 * 50 = 2500, turnover = 1000/2500 = 0.4
        $this->assertEquals(0.4, $data[0]['turnover_ratio']);
    }

    // ─── Financial: Daily P&L ────────────────────────────────

    public function test_financial_daily_pl_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/financial/daily-pl', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.daily', [])
            ->assertJsonPath('data.totals.total_net_profit', 0);
    }

    public function test_financial_daily_pl_returns_data(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-01',
            'total_transactions' => 10,
            'total_revenue' => 1000.00,
            'total_cost' => 600.00,
            'net_revenue' => 900.00,
        ]);

        \DB::table('expenses')->insert([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'amount' => 100.00,
            'category' => 'rent',
            'description' => 'Monthly rent',
            'expense_date' => '2024-06-01',
            'recorded_by' => $this->user->id,
        ]);

        $response = $this->getJson(
            '/api/v2/reports/financial/daily-pl?date_from=2024-06-01&date_to=2024-06-01',
            $this->authHeader(),
        );
        $data = $response->json('data');
        $this->assertCount(1, $data['daily']);
        $this->assertEquals(900.0, $data['daily'][0]['revenue']);
        $this->assertEquals(600.0, $data['daily'][0]['cost_of_goods']);
        $this->assertEquals(300.0, $data['daily'][0]['gross_profit']); // 900 - 600
        $this->assertEquals(100.0, $data['daily'][0]['expenses']);
        $this->assertEquals(200.0, $data['daily'][0]['net_profit']); // 300 - 100
    }

    // ─── Financial: Expenses ─────────────────────────────────

    public function test_financial_expenses_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/financial/expenses', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.total_expenses', 0)
            ->assertJsonPath('data.categories', []);
    }

    public function test_financial_expenses_returns_breakdown(): void
    {
        \DB::table('expenses')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'amount' => 500.00, 'category' => 'rent', 'description' => 'Rent', 'expense_date' => '2024-06-01', 'recorded_by' => $this->user->id],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'amount' => 200.00, 'category' => 'utilities', 'description' => 'Electric', 'expense_date' => '2024-06-01', 'recorded_by' => $this->user->id],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'amount' => 300.00, 'category' => 'rent', 'description' => 'Rent 2', 'expense_date' => '2024-06-15', 'recorded_by' => $this->user->id],
        ]);

        $response = $this->getJson('/api/v2/reports/financial/expenses', $this->authHeader());
        $data = $response->json('data');
        $this->assertEquals(1000.0, $data['total_expenses']);
        $this->assertCount(2, $data['categories']);
        // Rent first (higher total: 800)
        $this->assertEquals('rent', $data['categories'][0]['category']);
        $this->assertEquals(800.0, $data['categories'][0]['total_amount']);
        $this->assertEquals(2, $data['categories'][0]['expense_count']);
    }

    public function test_financial_expenses_date_filter(): void
    {
        \DB::table('expenses')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'amount' => 100.00, 'category' => 'rent', 'description' => 'June', 'expense_date' => '2024-06-01', 'recorded_by' => $this->user->id],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'amount' => 200.00, 'category' => 'rent', 'description' => 'July', 'expense_date' => '2024-07-01', 'recorded_by' => $this->user->id],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/financial/expenses?date_from=2024-07-01&date_to=2024-07-31',
            $this->authHeader(),
        );
        $data = $response->json('data');
        $this->assertEquals(200.0, $data['total_expenses']);
    }

    // ─── Financial: Cash Variance ────────────────────────────

    public function test_financial_cash_variance_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/financial/cash-variance', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.sessions_count', 0)
            ->assertJsonPath('data.total_variance', 0);
    }

    public function test_financial_cash_variance_returns_data(): void
    {
        \DB::table('cash_sessions')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'opened_by' => $this->user->id, 'opening_float' => 100.00, 'expected_cash' => 500.00, 'actual_cash' => 490.00, 'variance' => -10.00, 'status' => 'closed', 'opened_at' => '2024-06-01 08:00:00', 'closed_at' => '2024-06-01 18:00:00'],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'opened_by' => $this->user->id, 'opening_float' => 100.00, 'expected_cash' => 600.00, 'actual_cash' => 605.00, 'variance' => 5.00, 'status' => 'closed', 'opened_at' => '2024-06-02 08:00:00', 'closed_at' => '2024-06-02 18:00:00'],
            // Open session should be excluded
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'opened_by' => $this->user->id, 'opening_float' => 100.00, 'expected_cash' => 0, 'actual_cash' => 0, 'variance' => 0, 'status' => 'open', 'opened_at' => '2024-06-03 08:00:00', 'closed_at' => null],
        ]);

        $response = $this->getJson('/api/v2/reports/financial/cash-variance', $this->authHeader());
        $data = $response->json('data');
        $this->assertEquals(2, $data['sessions_count']);
        $this->assertEquals(-5.0, $data['total_variance']); // -10 + 5
        $this->assertEquals(1, $data['positive_variance_count']);
        $this->assertEquals(1, $data['negative_variance_count']);
    }

    // ─── Customer: Top Customers ─────────────────────────────

    public function test_top_customers_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/customers/top', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_top_customers_returns_ranked_by_spend(): void
    {
        \DB::table('customers')->insert([
            ['id' => Str::uuid()->toString(), 'organization_id' => $this->org->id, 'name' => 'Big Spender', 'total_spend' => 5000.00, 'visit_count' => 50, 'loyalty_points' => 500, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'organization_id' => $this->org->id, 'name' => 'Small Buyer', 'total_spend' => 100.00, 'visit_count' => 5, 'loyalty_points' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/v2/reports/customers/top', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('Big Spender', $data[0]['name']);
        $this->assertEquals(5000.0, $data[0]['total_spend']);
        $this->assertEquals(100.0, $data[0]['avg_spend_per_visit']); // 5000/50
    }

    // ─── Customer: Retention ─────────────────────────────────

    public function test_customer_retention_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/customers/retention', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.total_customers', 0)
            ->assertJsonPath('data.repeat_rate', 0);
    }

    public function test_customer_retention_returns_metrics(): void
    {
        \DB::table('customers')->insert([
            ['id' => Str::uuid()->toString(), 'organization_id' => $this->org->id, 'name' => 'Repeat 1', 'visit_count' => 5, 'total_spend' => 500.00, 'loyalty_points' => 100, 'last_visit_at' => now()->subDays(2), 'created_at' => now()->subDays(60), 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'organization_id' => $this->org->id, 'name' => 'Repeat 2', 'visit_count' => 3, 'total_spend' => 300.00, 'loyalty_points' => 50, 'last_visit_at' => now()->subDays(5), 'created_at' => now()->subDays(60), 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'organization_id' => $this->org->id, 'name' => 'One-Timer', 'visit_count' => 1, 'total_spend' => 50.00, 'loyalty_points' => 5, 'last_visit_at' => now()->subDays(45), 'created_at' => now()->subDays(60), 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'organization_id' => $this->org->id, 'name' => 'New Guy', 'visit_count' => 1, 'total_spend' => 20.00, 'loyalty_points' => 2, 'last_visit_at' => now()->subDays(1), 'created_at' => now()->subDays(10), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/v2/reports/customers/retention', $this->authHeader());
        $data = $response->json('data');
        $this->assertEquals(4, $data['total_customers']);
        $this->assertEquals(2, $data['repeat_customers']); // visit_count >= 2
        $this->assertEquals(50.0, $data['repeat_rate']); // 2/4 * 100
        $this->assertEquals(3, $data['active_customers_30d']); // 3 visited within 30d
        $this->assertArrayHasKey('total_loyalty_points', $data);
    }

    // ─── Sales Summary with Comparison ───────────────────────

    public function test_sales_summary_with_comparison(): void
    {
        // Previous period: June 1-10
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-05',
            'total_transactions' => 10,
            'total_revenue' => 1000.00,
            'net_revenue' => 800.00,
        ]);

        // Current period: June 11-20
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-15',
            'total_transactions' => 15,
            'total_revenue' => 1500.00,
            'net_revenue' => 1200.00,
        ]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-06-11&date_to=2024-06-20&compare=1',
            $this->authHeader(),
        );
        $response->assertOk();
        $data = $response->json('data');

        $this->assertArrayHasKey('previous_period', $data);
        $this->assertEquals('2024-06-01', $data['previous_period']['date_from']);
        $this->assertEquals('2024-06-10', $data['previous_period']['date_to']);
        $this->assertEquals(1000.0, $data['previous_period']['total_revenue']);
        $this->assertEquals(50.0, $data['previous_period']['revenue_change']); // (1500-1000)/1000*100
    }

    // ─── Export ───────────────────────────────────────────────

    public function test_export_sales_summary(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-01',
            'total_transactions' => 5,
            'total_revenue' => 500.00,
        ]);

        $response = $this->postJson('/api/v2/reports/export', [
            'report_type' => 'sales_summary',
            'format' => 'csv',
            'date_from' => '2024-06-01',
            'date_to' => '2024-06-30',
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('data.report_type', 'sales_summary')
            ->assertJsonPath('data.format', 'csv');

        $this->assertArrayHasKey('data', $response->json('data'));
    }

    public function test_export_validates_report_type(): void
    {
        $response = $this->postJson('/api/v2/reports/export', [
            'report_type' => 'nonexistent',
            'format' => 'csv',
        ], $this->authHeader());

        $response->assertStatus(422);
    }

    // ─── Scheduled Reports ───────────────────────────────────

    public function test_create_scheduled_report(): void
    {
        $response = $this->postJson('/api/v2/reports/schedules', [
            'report_type' => 'sales_summary',
            'name' => 'Daily Sales',
            'frequency' => 'daily',
            'recipients' => ['admin@test.com'],
            'format' => 'pdf',
        ], $this->authHeader());

        $response->assertCreated()
            ->assertJsonPath('data.report_type', 'sales_summary')
            ->assertJsonPath('data.frequency', 'daily')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('scheduled_reports', [
            'store_id' => $this->store->id,
            'report_type' => 'sales_summary',
        ]);
    }

    public function test_list_scheduled_reports(): void
    {
        ScheduledReport::create([
            'store_id' => $this->store->id,
            'report_type' => 'sales_summary',
            'name' => 'Daily Sales',
            'frequency' => 'daily',
            'recipients' => ['admin@test.com'],
            'format' => 'pdf',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/reports/schedules', $this->authHeader());
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_delete_scheduled_report(): void
    {
        $report = ScheduledReport::create([
            'store_id' => $this->store->id,
            'report_type' => 'sales_summary',
            'name' => 'To Delete',
            'frequency' => 'weekly',
            'recipients' => ['x@test.com'],
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v2/reports/schedules/{$report->id}", [], $this->authHeader());
        $response->assertOk();
        $this->assertDatabaseMissing('scheduled_reports', ['id' => $report->id]);
    }

    public function test_delete_nonexistent_scheduled_report(): void
    {
        $fakeId = Str::uuid()->toString();
        $response = $this->deleteJson("/api/v2/reports/schedules/{$fakeId}", [], $this->authHeader());
        $response->assertNotFound();
    }

    public function test_create_schedule_validates_recipients(): void
    {
        $response = $this->postJson('/api/v2/reports/schedules', [
            'report_type' => 'sales_summary',
            'name' => 'Bad Schedule',
            'frequency' => 'daily',
            'recipients' => ['not-an-email'],
        ], $this->authHeader());

        $response->assertStatus(422);
    }

    // ─── Refresh Summaries ───────────────────────────────────

    public function test_refresh_summaries_endpoint(): void
    {
        $response = $this->postJson('/api/v2/reports/refresh-summaries', [
            'date' => '2024-06-01',
        ], $this->authHeader());

        $response->assertOk();
    }

    // ─── Inventory Expiry ────────────────────────────────────

    public function test_inventory_expiry_returns_structure(): void
    {
        $response = $this->getJson(
            '/api/v2/reports/inventory/expiry?from=' . now()->subDays(30)->toDateString() . '&to=' . now()->toDateString(),
            $this->authHeader()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'totals' => ['expired_count', 'critical_count', 'warning_count'],
                    'expired',
                    'critical',
                    'warning',
                ],
            ]);
    }

    public function test_inventory_expiry_requires_auth(): void
    {
        $this->getJson('/api/v2/reports/inventory/expiry')->assertUnauthorized();
    }

    // ─── Delivery Commission ─────────────────────────────────

    public function test_financial_delivery_commission_returns_structure(): void
    {
        $response = $this->getJson(
            '/api/v2/reports/financial/delivery-commission?from=' . now()->subDays(30)->toDateString() . '&to=' . now()->toDateString(),
            $this->authHeader()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'totals' => ['total_orders', 'total_gross', 'total_commission', 'total_net'],
                    'platforms',
                ],
            ]);
    }

    public function test_financial_delivery_commission_requires_auth(): void
    {
        $this->getJson('/api/v2/reports/financial/delivery-commission')->assertUnauthorized();
    }

    // ─── Scheduled Reports ───────────────────────────────────

    public function test_scheduled_reports_list(): void
    {
        $response = $this->getJson('/api/v2/reports/schedules', $this->authHeader());

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    public function test_scheduled_report_create(): void
    {
        $response = $this->postJson('/api/v2/reports/schedules', [
            'name'       => 'Daily Revenue',
            'report_type' => 'sales_summary',
            'frequency'  => 'daily',
            'format'     => 'pdf',
            'recipients' => ['manager@test.com'],
        ], $this->authHeader());

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'frequency', 'format', 'is_active']]);
    }

    public function test_scheduled_report_create_validates_type(): void
    {
        $response = $this->postJson('/api/v2/reports/schedules', [
            'name'       => 'Bad Type',
            'report_type' => 'invalid_type',
            'frequency'  => 'daily',
            'format'     => 'pdf',
            'recipients' => ['a@b.com'],
        ], $this->authHeader());

        $response->assertUnprocessable();
    }

    public function test_scheduled_report_delete(): void
    {
        $schedule = \App\Domain\Report\Models\ScheduledReport::create([
            'store_id'    => $this->store->id,
            'name'        => 'To Delete',
            'report_type' => 'sales_summary',
            'frequency'   => 'weekly',
            'format'      => 'csv',
            'recipients'  => ['x@y.com'],
            'is_active'   => true,
            'next_run_at' => now()->addDay(),
        ]);

        $this->deleteJson("/api/v2/reports/schedules/{$schedule->id}", [], $this->authHeader())
             ->assertOk();

        $this->assertDatabaseMissing('scheduled_reports', ['id' => $schedule->id]);
    }

    // ─── Export ──────────────────────────────────────────────

    public function test_export_report_validates_type(): void
    {
        $response = $this->postJson('/api/v2/reports/export', [
            'report_type' => 'unknown_report',
            'format'      => 'pdf',
        ], $this->authHeader());

        $response->assertUnprocessable();
    }

    public function test_export_sales_summary_pdf(): void
    {
        $response = $this->postJson('/api/v2/reports/export', [
            'report_type' => 'sales_summary',
            'format'      => 'pdf',
            'from'        => now()->subDays(7)->toDateString(),
            'to'          => now()->toDateString(),
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonStructure(['data' => ['url']]);
    }
}
