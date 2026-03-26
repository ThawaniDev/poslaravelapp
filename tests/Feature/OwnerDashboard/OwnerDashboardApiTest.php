<?php

namespace Tests\Feature\OwnerDashboard;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OwnerDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    private Organization $otherOrg;
    private Store $otherStore;
    private User $otherUser;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Dashboard Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'name_ar' => 'المتجر الرئيسي',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'owner@dashboard.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Another org for data isolation test
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
        $this->otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@dashboard.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->otherStore->id,
            'organization_id' => $this->otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function authHeader(?string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ─── Authentication ──────────────────────────────────────

    public function test_unauthenticated_cannot_access_dashboard(): void
    {
        $this->getJson('/api/v2/owner-dashboard/stats')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_sales_trend(): void
    {
        $this->getJson('/api/v2/owner-dashboard/sales-trend')
            ->assertUnauthorized();
    }

    // ─── Stats Endpoint ──────────────────────────────────────

    public function test_stats_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/stats', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.today_sales.value', 0)
            ->assertJsonPath('data.transactions.value', 0)
            ->assertJsonPath('data.avg_basket.value', 0)
            ->assertJsonPath('data.net_profit.value', 0);
    }

    public function test_stats_returns_today_data_with_change(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => Carbon::today()->toDateString(),
            'total_transactions' => 50,
            'total_revenue' => 5000.00,
            'total_cost' => 3000.00,
            'total_discount' => 200.00,
            'total_tax' => 750.00,
            'total_refunds' => 100.00,
            'net_revenue' => 1950.00,
            'cash_revenue' => 3000.00,
            'card_revenue' => 2000.00,
            'other_revenue' => 0.00,
            'avg_basket_size' => 100.00,
            'unique_customers' => 35,
        ]);

        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => Carbon::yesterday()->toDateString(),
            'total_transactions' => 40,
            'total_revenue' => 4000.00,
            'total_cost' => 2400.00,
            'total_discount' => 100.00,
            'total_tax' => 600.00,
            'total_refunds' => 50.00,
            'net_revenue' => 1550.00,
            'cash_revenue' => 2500.00,
            'card_revenue' => 1500.00,
            'other_revenue' => 0.00,
            'avg_basket_size' => 100.00,
            'unique_customers' => 30,
        ]);

        $response = $this->getJson('/api/v2/owner-dashboard/stats', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(5000.0, (float) $data['today_sales']['value']);
        $this->assertEquals(50, $data['transactions']['value']);
        $this->assertEquals(1950.0, (float) $data['net_profit']['value']);
        $this->assertEquals(35, $data['unique_customers']);
        $this->assertEquals(100.0, (float) $data['total_refunds']);
        $this->assertEquals(25.0, (float) $data['today_sales']['change']);
        $this->assertEquals(25.0, (float) $data['transactions']['change']);
    }

    public function test_stats_data_isolation(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->otherStore->id,
            'date' => Carbon::today()->toDateString(),
            'total_transactions' => 100,
            'total_revenue' => 9999.00,
            'total_cost' => 5000.00,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_refunds' => 0,
            'net_revenue' => 4999.00,
            'cash_revenue' => 9999.00,
            'card_revenue' => 0,
            'other_revenue' => 0,
            'avg_basket_size' => 99.99,
            'unique_customers' => 80,
        ]);

        $response = $this->getJson('/api/v2/owner-dashboard/stats', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data.today_sales.value', 0)
            ->assertJsonPath('data.transactions.value', 0);
    }

    // ─── Sales Trend Endpoint ────────────────────────────────

    public function test_sales_trend_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/sales-trend', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current', [])
            ->assertJsonPath('data.previous', [])
            ->assertJsonPath('data.summary.current_total', 0);
    }

    public function test_sales_trend_returns_7_day_data(): void
    {
        for ($i = 0; $i < 7; $i++) {
            DailySalesSummary::create([
                'store_id' => $this->store->id,
                'date' => Carbon::today()->subDays($i)->toDateString(),
                'total_transactions' => 10 + $i,
                'total_revenue' => 1000.00 + ($i * 100),
                'total_cost' => 600.00,
                'total_discount' => 0,
                'total_tax' => 150.00,
                'total_refunds' => 0,
                'net_revenue' => 400.00 + ($i * 100),
                'cash_revenue' => 500.00,
                'card_revenue' => 500.00 + ($i * 100),
                'other_revenue' => 0,
                'avg_basket_size' => 100.00,
                'unique_customers' => 8,
            ]);
        }

        $response = $this->getJson('/api/v2/owner-dashboard/sales-trend?days=7', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(7, 'data.current');
    }

    public function test_sales_trend_custom_days(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/sales-trend?days=30', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period' => ['from', 'to'],
                    'current',
                    'previous',
                    'summary' => ['current_total', 'previous_total', 'change'],
                ],
            ]);
    }

    // ─── Top Products Endpoint ───────────────────────────────

    public function test_top_products_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/top-products', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_top_products_returns_by_revenue(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Coffee',
            'name_ar' => 'قهوة',
            'sku' => 'COF-001',
            'sell_price' => 5.00,
            'cost_price' => 2.00,
            'is_active' => true,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'date' => Carbon::today()->toDateString(),
            'quantity_sold' => 100,
            'revenue' => 500.00,
            'cost' => 200.00,
            'discount_amount' => 0,
            'tax_amount' => 75.00,
            'return_quantity' => 0,
            'return_amount' => 0,
        ]);

        $response = $this->getJson('/api/v2/owner-dashboard/top-products?limit=5&days=30', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Coffee');
        $this->assertEquals(500.0, (float) $response->json('data.0.total_revenue'));
    }

    // ─── Low Stock Endpoint ──────────────────────────────────

    public function test_low_stock_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/low-stock', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_low_stock_returns_items_near_reorder_point(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Paper Cups',
            'name_ar' => 'أكواب ورقية',
            'sku' => 'CUP-001',
            'sell_price' => 0.10,
            'cost_price' => 0.05,
            'is_active' => true,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'reorder_point' => 10,
            'max_stock_level' => 100,
            'average_cost' => 0.05,
        ]);

        $response = $this->getJson('/api/v2/owner-dashboard/low-stock', $this->authHeader());
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Paper Cups');
        $this->assertEquals(5.0, (float) $response->json('data.0.current_stock'));
        $this->assertEquals(10.0, (float) $response->json('data.0.reorder_point'));
    }

    public function test_low_stock_excludes_zero_quantity(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Out of Stock Item',
            'name_ar' => 'منتج نفد',
            'sku' => 'OOS-001',
            'sell_price' => 1.00,
            'cost_price' => 0.50,
            'is_active' => true,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'reorder_point' => 10,
            'max_stock_level' => 100,
            'average_cost' => 0.50,
        ]);

        $response = $this->getJson('/api/v2/owner-dashboard/low-stock', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    // ─── Recent Orders Endpoint ──────────────────────────────

    public function test_recent_orders_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/recent-orders', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    // ─── Financial Summary Endpoint ──────────────────────────

    public function test_financial_summary_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/financial-summary', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revenue.total', 0);
    }

    public function test_financial_summary_returns_aggregated(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => Carbon::today()->toDateString(),
            'total_transactions' => 20,
            'total_revenue' => 2000.00,
            'total_cost' => 1200.00,
            'total_discount' => 100.00,
            'total_tax' => 300.00,
            'total_refunds' => 50.00,
            'net_revenue' => 650.00,
            'cash_revenue' => 1000.00,
            'card_revenue' => 1000.00,
            'other_revenue' => 0.00,
            'avg_basket_size' => 100.00,
            'unique_customers' => 15,
        ]);

        $response = $this->getJson('/api/v2/owner-dashboard/financial-summary', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(2000.0, (float) $data['revenue']['total']);
        $this->assertEquals(650.0, (float) $data['revenue']['net']);
        $this->assertEquals(300.0, (float) $data['revenue']['tax']);
        $this->assertEquals(100.0, (float) $data['revenue']['discounts']);
        $this->assertEquals(50.0, (float) $data['revenue']['refunds']);
    }

    public function test_financial_summary_date_filter(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-01',
            'total_transactions' => 10,
            'total_revenue' => 1000.00,
            'total_cost' => 600.00,
            'total_discount' => 0,
            'total_tax' => 150.00,
            'total_refunds' => 0,
            'net_revenue' => 400.00,
            'cash_revenue' => 500.00,
            'card_revenue' => 500.00,
            'other_revenue' => 0,
            'avg_basket_size' => 100.00,
            'unique_customers' => 8,
        ]);

        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-07-01',
            'total_transactions' => 20,
            'total_revenue' => 2000.00,
            'total_cost' => 1200.00,
            'total_discount' => 0,
            'total_tax' => 300.00,
            'total_refunds' => 0,
            'net_revenue' => 800.00,
            'cash_revenue' => 1000.00,
            'card_revenue' => 1000.00,
            'other_revenue' => 0,
            'avg_basket_size' => 100.00,
            'unique_customers' => 15,
        ]);

        $response = $this->getJson(
            '/api/v2/owner-dashboard/financial-summary?date_from=2024-06-01&date_to=2024-06-30',
            $this->authHeader()
        );
        $response->assertOk();
        $this->assertEquals(1000.0, (float) $response->json('data.revenue.total'));
    }

    // ─── Hourly Sales Endpoint ───────────────────────────────

    public function test_hourly_sales_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/hourly-sales', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    // ─── Branches Endpoint ───────────────────────────────────

    public function test_branches_returns_overview(): void
    {
        $branchStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch 2',
            'name_ar' => 'الفرع ٢',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => Carbon::today()->toDateString(),
            'total_transactions' => 50,
            'total_revenue' => 5000.00,
            'total_cost' => 3000.00,
            'total_discount' => 0,
            'total_tax' => 750.00,
            'total_refunds' => 0,
            'net_revenue' => 2000.00,
            'cash_revenue' => 3000.00,
            'card_revenue' => 2000.00,
            'other_revenue' => 0,
            'avg_basket_size' => 100.00,
            'unique_customers' => 35,
        ]);

        DailySalesSummary::create([
            'store_id' => $branchStore->id,
            'date' => Carbon::today()->toDateString(),
            'total_transactions' => 30,
            'total_revenue' => 3000.00,
            'total_cost' => 1800.00,
            'total_discount' => 0,
            'total_tax' => 450.00,
            'total_refunds' => 0,
            'net_revenue' => 1200.00,
            'cash_revenue' => 2000.00,
            'card_revenue' => 1000.00,
            'other_revenue' => 0,
            'avg_basket_size' => 100.00,
            'unique_customers' => 20,
        ]);

        $response = $this->getJson('/api/v2/owner-dashboard/branches', $this->authHeader());
        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Ordered by revenue desc
        $this->assertEquals('Main Store', $response->json('data.0.store_name'));
    }

    // ─── Staff Performance Endpoint ──────────────────────────

    public function test_staff_performance_empty(): void
    {
        $response = $this->getJson('/api/v2/owner-dashboard/staff-performance', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    // ─── Validation ──────────────────────────────────────────

    public function test_sales_trend_validates_days_max(): void
    {
        $response = $this->getJson(
            '/api/v2/owner-dashboard/sales-trend?days=500',
            $this->authHeader()
        );
        $response->assertStatus(422);
    }

    public function test_financial_summary_validates_date_format(): void
    {
        $response = $this->getJson(
            '/api/v2/owner-dashboard/financial-summary?date_from=invalid',
            $this->authHeader()
        );
        $response->assertStatus(422);
    }

    public function test_top_products_validates_metric(): void
    {
        $response = $this->getJson(
            '/api/v2/owner-dashboard/top-products?metric=invalid',
            $this->authHeader()
        );
        $response->assertStatus(422);
    }
}
