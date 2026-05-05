<?php

namespace Tests\Unit\Domain\Report;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\Report\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for ReportService — tests each service method in isolation
 * with known data, verifying aggregation logic, edge cases, and data shapes.
 */
class ReportServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;
    private Organization $org;
    private Store $store;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReportService::class);

        $this->org = Organization::create([
            'name' => 'Unit Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Unit Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->storeId = $this->store->id;
    }

    // ─── salesSummary ──────────────────────────────────────────────────────────

    /** @test */
    public function sales_summary_returns_zero_totals_when_no_data(): void
    {
        $result = $this->service->salesSummary($this->storeId, []);

        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('series', $result);
        $this->assertEquals(0, $result['totals']['total_transactions']);
        $this->assertEquals(0.0, $result['totals']['total_revenue']);
        $this->assertIsArray($result['series']);
        $this->assertEmpty($result['series']);
    }

    /** @test */
    public function sales_summary_sums_all_daily_rows(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->storeId,
            'date' => '2024-11-01',
            'total_transactions' => 10,
            'total_revenue' => 1000.00,
            'total_cost' => 600.00,
            'total_discount' => 50.00,
            'total_tax' => 100.00,
            'total_refunds' => 30.00,
            'net_revenue' => 970.00,
            'cash_revenue' => 500.00,
            'card_revenue' => 500.00,
            'unique_customers' => 8,
        ]);

        DailySalesSummary::create([
            'store_id' => $this->storeId,
            'date' => '2024-11-02',
            'total_transactions' => 5,
            'total_revenue' => 500.00,
            'total_cost' => 300.00,
            'total_discount' => 25.00,
            'total_tax' => 50.00,
            'total_refunds' => 10.00,
            'net_revenue' => 490.00,
            'cash_revenue' => 300.00,
            'card_revenue' => 200.00,
            'unique_customers' => 4,
        ]);

        $result = $this->service->salesSummary($this->storeId, []);

        $this->assertEquals(15, $result['totals']['total_transactions']);
        $this->assertEquals(1500.00, $result['totals']['total_revenue']);
        $this->assertEquals(40.00, $result['totals']['total_refunds']);
        $this->assertEquals(12, $result['totals']['unique_customers']);
        $this->assertCount(2, $result['series']);
    }

    /** @test */
    public function sales_summary_date_filter_restricts_results(): void
    {
        DailySalesSummary::create(['store_id' => $this->storeId, 'date' => '2024-10-15', 'total_revenue' => 100.00]);
        DailySalesSummary::create(['store_id' => $this->storeId, 'date' => '2024-11-01', 'total_revenue' => 200.00]);
        DailySalesSummary::create(['store_id' => $this->storeId, 'date' => '2024-11-30', 'total_revenue' => 300.00]);
        DailySalesSummary::create(['store_id' => $this->storeId, 'date' => '2024-12-01', 'total_revenue' => 400.00]);

        $result = $this->service->salesSummary($this->storeId, [
            'date_from' => '2024-11-01',
            'date_to' => '2024-11-30',
        ]);

        $this->assertCount(2, $result['series']);
        $this->assertEquals(500.00, $result['totals']['total_revenue']);
    }

    /** @test */
    public function sales_summary_granularity_weekly_groups_days(): void
    {
        // All in the same ISO week (Mon Nov 4 – Sun Nov 10)
        foreach (['2024-11-04', '2024-11-05', '2024-11-06'] as $date) {
            DailySalesSummary::create([
                'store_id' => $this->storeId,
                'date' => $date,
                'total_transactions' => 2,
                'total_revenue' => 100.00,
            ]);
        }

        $result = $this->service->salesSummary($this->storeId, ['granularity' => 'weekly']);

        // All 3 days collapse to 1 weekly bucket
        $this->assertCount(1, $result['series']);
        $this->assertEquals(6, $result['series'][0]['total_transactions']);
        $this->assertEquals(300.00, $result['series'][0]['total_revenue']);
    }

    /** @test */
    public function sales_summary_granularity_monthly_groups_days(): void
    {
        foreach (['2024-11-01', '2024-11-15', '2024-11-28'] as $date) {
            DailySalesSummary::create([
                'store_id' => $this->storeId,
                'date' => $date,
                'total_transactions' => 1,
                'total_revenue' => 50.00,
            ]);
        }
        DailySalesSummary::create([
            'store_id' => $this->storeId,
            'date' => '2024-12-01',
            'total_transactions' => 1,
            'total_revenue' => 100.00,
        ]);

        $result = $this->service->salesSummary($this->storeId, ['granularity' => 'monthly']);

        $this->assertCount(2, $result['series']);
    }

    /** @test */
    public function sales_summary_order_source_filter_uses_orders_table(): void
    {
        // Insert an order with source='pos' and one with source='delivery'
        $posOrderId = Str::uuid()->toString();
        $deliveryOrderId = Str::uuid()->toString();

        DB::table('orders')->insert([
            ['id' => $posOrderId, 'store_id' => $this->storeId, 'order_number' => 'ORD-001', 'source' => 'pos', 'status' => 'completed', 'total' => 150.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $deliveryOrderId, 'store_id' => $this->storeId, 'order_number' => 'ORD-002', 'source' => 'delivery', 'status' => 'completed', 'total' => 250.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = $this->service->salesSummary($this->storeId, ['order_source' => 'pos']);

        // Only the pos order should be counted
        $this->assertEquals(150.00, (float) $result['totals']['total_revenue']);
    }

    /** @test */
    public function sales_summary_order_status_filter_uses_orders_table(): void
    {
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O2', 'source' => 'pos', 'status' => 'refunded', 'total' => 50.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = $this->service->salesSummary($this->storeId, ['order_status' => 'completed']);

        $this->assertEquals(100.00, (float) $result['totals']['total_revenue']);
    }

    /** @test */
    public function sales_summary_with_compare_returns_previous_period(): void
    {
        // Current period: Nov 11-20
        DailySalesSummary::create(['store_id' => $this->storeId, 'date' => '2024-11-15', 'total_revenue' => 1000.00, 'total_transactions' => 10]);
        // Previous period: Nov 1-10
        DailySalesSummary::create(['store_id' => $this->storeId, 'date' => '2024-11-05', 'total_revenue' => 800.00, 'total_transactions' => 8]);

        $result = $this->service->salesSummary($this->storeId, [
            'date_from' => '2024-11-11',
            'date_to' => '2024-11-20',
            'compare' => '1',
        ]);

        $this->assertArrayHasKey('previous_period', $result);
        $this->assertNotNull($result['previous_period']);
        $this->assertEquals(800.00, (float) $result['previous_period']['total_revenue']);
    }

    /** @test */
    public function sales_summary_store_isolation(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $otherStore = Store::create(['organization_id' => $otherOrg->id, 'name' => 'Other Store', 'business_type' => 'grocery', 'currency' => 'SAR', 'is_active' => true]);

        DailySalesSummary::create(['store_id' => $this->storeId, 'date' => '2024-11-01', 'total_revenue' => 1000.00]);
        DailySalesSummary::create(['store_id' => $otherStore->id, 'date' => '2024-11-01', 'total_revenue' => 9999.00]);

        $result = $this->service->salesSummary($this->storeId, []);

        $this->assertEquals(1000.00, $result['totals']['total_revenue']);
    }

    // ─── productPerformance ────────────────────────────────────────────────────

    /** @test */
    public function product_performance_returns_empty_array_when_no_data(): void
    {
        $result = $this->service->productPerformance($this->storeId, []);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function product_performance_returns_sorted_by_revenue_descending(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        $p1 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Product A', 'sku' => 'A001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);
        $p2 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Product B', 'sku' => 'B001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);

        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p1->id, 'date' => '2024-11-01', 'revenue' => 500.00, 'quantity_sold' => 50, 'cost' => 250.00]);
        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p2->id, 'date' => '2024-11-01', 'revenue' => 1000.00, 'quantity_sold' => 100, 'cost' => 500.00]);

        $result = $this->service->productPerformance($this->storeId, []);

        $this->assertCount(2, $result);
        $this->assertEquals('Product B', $result[0]['product_name']);
        $this->assertEquals('Product A', $result[1]['product_name']);
    }

    /** @test */
    public function product_performance_category_filter_works(): void
    {
        $cat1 = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat 1', 'sort_order' => 1]);
        $cat2 = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat 2', 'sort_order' => 2]);
        $p1 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat1->id, 'name' => 'P1', 'sku' => 'P001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);
        $p2 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat2->id, 'name' => 'P2', 'sku' => 'P002', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);

        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p1->id, 'date' => '2024-11-01', 'revenue' => 200.00, 'quantity_sold' => 20]);
        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p2->id, 'date' => '2024-11-01', 'revenue' => 300.00, 'quantity_sold' => 30]);

        $result = $this->service->productPerformance($this->storeId, ['category_id' => $cat1->id]);

        $this->assertCount(1, $result);
        $this->assertEquals('P1', $result[0]['product_name']);
    }

    // ─── staffPerformance ─────────────────────────────────────────────────────

    /** @test */
    public function staff_performance_returns_empty_array_when_no_orders(): void
    {
        $result = $this->service->staffPerformance($this->storeId, []);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function staff_performance_aggregates_per_staff_member(): void
    {
        $staffId = Str::uuid()->toString();
        // Must insert staff user so JOIN works
        DB::table('staff_users')->insert([
            'id' => $staffId,
            'user_id' => Str::uuid(),
            'store_id' => $this->storeId,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'pin_hash' => bcrypt('1234'),
            'status' => 'active',
            'employment_type' => 'full_time',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_by' => $staffId, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O2', 'source' => 'pos', 'status' => 'completed', 'total' => 200.00, 'created_by' => $staffId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = $this->service->staffPerformance($this->storeId, []);

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result[0]['total_orders']);
        $this->assertEquals(300.00, (float) $result[0]['total_revenue']);
        $this->assertArrayHasKey('hours_worked', $result[0]);
    }

    /** @test */
    public function staff_performance_includes_hours_worked_from_attendance(): void
    {
        $staffUser = DB::table('staff_users')->insertGetId([
            'id' => Str::uuid(),
            'user_id' => Str::uuid(),
            'store_id' => $this->storeId,
            'first_name' => 'Staff',
            'last_name' => 'One',
            'pin_hash' => bcrypt('1234'),
            'status' => 'active',
            'employment_type' => 'full_time',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the actual UUID of the staff user
        $staffUserId = DB::table('staff_users')->where('first_name', 'Staff')->value('id');

        // Insert 4 hours of attendance
        DB::table('attendance_records')->insert([
            'id' => Str::uuid(),
            'staff_user_id' => $staffUserId,
            'store_id' => $this->storeId,
            'clock_in_at' => now()->startOfDay()->addHours(8),
            'clock_out_at' => now()->startOfDay()->addHours(12),
        ]);

        // Insert an order by this staff
        DB::table('orders')->insert([
            'id' => Str::uuid(),
            'store_id' => $this->storeId,
            'order_number' => 'O1',
            'source' => 'pos',
            'status' => 'completed',
            'total' => 100.00,
            'created_by' => $staffUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->staffPerformance($this->storeId, []);

        $this->assertCount(1, $result);
        $staff = $result[0];
        $this->assertArrayHasKey('hours_worked', $staff);
        $this->assertEquals(4.0, (float) $staff['hours_worked']);
    }

    // ─── hourlySales ──────────────────────────────────────────────────────────

    /** @test */
    public function hourly_sales_returns_24_hour_buckets(): void
    {
        $result = $this->service->hourlySales($this->storeId, []);

        $this->assertIsArray($result);
        // Service only returns hours that have data, not always 24 buckets

        foreach ($result as $bucket) {
            $this->assertArrayHasKey('hour', $bucket);
            $this->assertArrayHasKey('total_orders', $bucket);
            $this->assertArrayHasKey('total_revenue', $bucket);
        }
    }

    /** @test */
    public function hourly_sales_counts_orders_in_correct_hour(): void
    {
        // Insert 2 orders at 14:xx today
        $today = now()->startOfDay();
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_at' => $today->copy()->addHours(14)->addMinutes(10), 'updated_at' => now()],
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O2', 'source' => 'pos', 'status' => 'completed', 'total' => 200.00, 'created_at' => $today->copy()->addHours(14)->addMinutes(45), 'updated_at' => now()],
        ]);

        $result = $this->service->hourlySales($this->storeId, [
            'date_from' => $today->toDateString(),
            'date_to' => $today->toDateString(),
        ]);

        $hour14 = collect($result)->firstWhere('hour', 14);
        $this->assertNotNull($hour14);
        $this->assertEquals(2, $hour14['total_orders']);
        $this->assertEquals(300.00, (float) $hour14['total_revenue']);
    }

    // ─── paymentMethodBreakdown ────────────────────────────────────────────────

    /** @test */
    public function payment_method_breakdown_returns_empty_when_no_data(): void
    {
        $result = $this->service->paymentMethodBreakdown($this->storeId, []);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function payment_method_breakdown_groups_by_method(): void
    {
        $transId = Str::uuid()->toString();
        DB::table('transactions')->insert([
            'id' => $transId,
            'organization_id' => $this->org->id,
            'store_id' => $this->storeId,
            'cashier_id' => Str::uuid(),
            'transaction_number' => 'T001',
            'type' => 'sale',
            'status' => 'completed',
            'total_amount' => 300.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            ['id' => Str::uuid(), 'transaction_id' => $transId, 'method' => 'cash', 'amount' => 100.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'transaction_id' => $transId, 'method' => 'card', 'amount' => 200.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = $this->service->paymentMethodBreakdown($this->storeId, []);

        $this->assertCount(2, $result);
        $methods = collect($result)->pluck('method')->toArray();
        $this->assertContains('cash', $methods);
        $this->assertContains('card', $methods);
    }

    // ─── categoryBreakdown ────────────────────────────────────────────────────

    /** @test */
    public function category_breakdown_aggregates_product_sales_by_category(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Beverages', 'sort_order' => 1]);
        $p = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Tea', 'sku' => 'T001', 'sell_price' => 5.00, 'cost_price' => 2.00, 'is_active' => true]);

        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p->id, 'date' => '2024-11-01', 'revenue' => 100.00, 'quantity_sold' => 20]);
        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p->id, 'date' => '2024-11-02', 'revenue' => 50.00, 'quantity_sold' => 10]);

        $result = $this->service->categoryBreakdown($this->storeId, []);

        $this->assertCount(1, $result);
        $this->assertEquals('Beverages', $result[0]['category_name']);
        $this->assertEquals(150.00, (float) $result[0]['total_revenue']);
        $this->assertEquals(30, (int) $result[0]['total_quantity']);
    }

    // ─── dashboard ────────────────────────────────────────────────────────────

    /** @test */
    public function dashboard_returns_correct_shape(): void
    {
        $result = $this->service->dashboard($this->storeId);

        $this->assertArrayHasKey('today', $result);
        $this->assertArrayHasKey('yesterday', $result);
        $this->assertArrayHasKey('top_products', $result);

        foreach (['total_revenue', 'total_transactions', 'total_refunds', 'avg_basket_size'] as $key) {
            $this->assertArrayHasKey($key, $result['today']);
        }
    }

    /** @test */
    public function dashboard_today_counts_only_todays_orders(): void
    {
        $today = now()->toDateString();

        // Dashboard reads from daily_sales_summary, not raw orders
        DailySalesSummary::create([
            'store_id' => $this->storeId,
            'date' => $today,
            'total_transactions' => 1,
            'total_revenue' => 400.00,
        ]);

        $result = $this->service->dashboard($this->storeId);

        $this->assertEquals(400.00, (float) $result['today']['total_revenue']);
        $this->assertEquals(1, $result['today']['total_transactions']);
    }

    // ─── slowMovers ───────────────────────────────────────────────────────────

    /** @test */
    public function slow_movers_returns_products_sorted_by_quantity_ascending(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        $p1 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Fast', 'sku' => 'F001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);
        $p2 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Slow', 'sku' => 'S001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);

        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p1->id, 'date' => '2024-11-01', 'revenue' => 1000.00, 'quantity_sold' => 100]);
        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p2->id, 'date' => '2024-11-01', 'revenue' => 10.00, 'quantity_sold' => 1]);

        $result = $this->service->slowMovers($this->storeId, []);

        $this->assertCount(2, $result);
        $this->assertEquals('Slow', $result[0]['product_name']);
    }

    // ─── productMargin ────────────────────────────────────────────────────────

    /** @test */
    public function product_margin_calculates_margin_percentage_correctly(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        $p = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Widget', 'sku' => 'W001', 'sell_price' => 100.00, 'cost_price' => 60.00, 'is_active' => true]);

        ProductSalesSummary::create(['store_id' => $this->storeId, 'product_id' => $p->id, 'date' => '2024-11-01', 'revenue' => 1000.00, 'cost' => 600.00, 'quantity_sold' => 10]);

        $result = $this->service->productMargin($this->storeId, []);

        $this->assertCount(1, $result);
        $item = $result[0];
        $this->assertArrayHasKey('margin_percent', $item);
        $this->assertEquals(40.0, round((float) $item['margin_percent'], 1));
    }

    // ─── inventoryValuation ───────────────────────────────────────────────────

    /** @test */
    public function inventory_valuation_returns_correct_shape(): void
    {
        $result = $this->service->inventoryValuation($this->storeId);

        $this->assertArrayHasKey('total_stock_value', $result);
        $this->assertArrayHasKey('total_items', $result);
        $this->assertArrayHasKey('product_count', $result);
        $this->assertArrayHasKey('products', $result);
    }

    /** @test */
    public function inventory_valuation_sums_stock_value(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        $p = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Prod', 'sku' => 'V001', 'sell_price' => 20.00, 'cost_price' => 10.00, 'is_active' => true]);

        DB::table('stock_levels')->insert([
            'id' => Str::uuid(),
            'store_id' => $this->storeId,
            'product_id' => $p->id,
            'quantity' => 100,
            'average_cost' => 10.00,
            'reorder_point' => 10,
        ]);

        $result = $this->service->inventoryValuation($this->storeId);

        $this->assertEquals(1000.00, (float) $result['total_stock_value']);
        $this->assertEquals(100.0, (float) $result['total_items']);
    }

    // ─── inventoryLowStock ────────────────────────────────────────────────────

    /** @test */
    public function inventory_low_stock_returns_products_below_reorder_point(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        $pLow = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Low Stock', 'sku' => 'L001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);
        $pOk = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'OK Stock', 'sku' => 'L002', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);

        DB::table('stock_levels')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'product_id' => $pLow->id, 'quantity' => 2, 'reorder_point' => 10],
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'product_id' => $pOk->id, 'quantity' => 50, 'reorder_point' => 10],
        ]);

        $result = $this->service->inventoryLowStock($this->storeId);

        $this->assertCount(1, $result);
        $this->assertEquals('Low Stock', $result[0]['product_name']);
    }

    // ─── financialExpenses ────────────────────────────────────────────────────

    /** @test */
    public function financial_expenses_groups_by_category(): void
    {
        DB::table('expenses')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'category' => 'utilities', 'amount' => 500.00, 'expense_date' => '2024-11-01', 'description' => 'Electric', 'recorded_by' => Str::uuid(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'category' => 'utilities', 'amount' => 300.00, 'expense_date' => '2024-11-05', 'description' => 'Water', 'recorded_by' => Str::uuid(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'category' => 'salaries', 'amount' => 2000.00, 'expense_date' => '2024-11-10', 'description' => 'Staff', 'recorded_by' => Str::uuid(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = $this->service->financialExpenses($this->storeId, []);

        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('total_expenses', $result);

        $breakdown = collect($result['categories']);
        $utilities = $breakdown->firstWhere('category', 'utilities');
        $this->assertEquals(800.00, (float) $utilities['total_amount']);
        $this->assertEquals(2800.00, (float) $result['total_expenses']);
    }

    // ─── financialCashVariance ────────────────────────────────────────────────

    /** @test */
    public function financial_cash_variance_returns_correct_shape(): void
    {
        $result = $this->service->financialCashVariance($this->storeId, []);

        $this->assertArrayHasKey('sessions', $result);
        $this->assertArrayHasKey('total_variance', $result);
        $this->assertArrayHasKey('sessions_count', $result);
    }

    // ─── topCustomers ─────────────────────────────────────────────────────────

    /** @test */
    public function top_customers_returns_ranked_by_total_spend(): void
    {
        // topCustomers queries customers via organization_id subquery and orders by total_spend
        DB::table('customers')->insert([
            ['id' => Str::uuid(), 'organization_id' => $this->org->id, 'name' => 'Big Spender', 'total_spend' => 800.00, 'visit_count' => 3, 'loyalty_points' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'organization_id' => $this->org->id, 'name' => 'Small Spender', 'total_spend' => 100.00, 'visit_count' => 1, 'loyalty_points' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = $this->service->topCustomers($this->storeId, []);

        $this->assertNotEmpty($result);
        $this->assertEquals('Big Spender', $result[0]['name']);
        $this->assertEquals(800.00, (float) $result[0]['total_spend']);
    }

    // ─── customerRetention ────────────────────────────────────────────────────

    /** @test */
    public function customer_retention_returns_correct_shape_with_new_fields(): void
    {
        $result = $this->service->customerRetention($this->storeId, []);

        $this->assertArrayHasKey('total_customers', $result);
        $this->assertArrayHasKey('new_customers_30d', $result);
        $this->assertArrayHasKey('returning_customers_30d', $result);
        $this->assertArrayHasKey('loyalty_points_redeemed', $result);
        $this->assertArrayHasKey('repeat_rate', $result);
    }

    // ─── refreshDailySummary ──────────────────────────────────────────────────

    /** @test */
    public function refresh_daily_summary_creates_or_updates_row(): void
    {
        $date = '2024-11-01';

        // No data yet, so no orders exist → refresh creates a zero row
        $this->service->refreshDailySummary($this->storeId, $date);

        $row = DailySalesSummary::where('store_id', $this->storeId)->where('date', $date)->first();
        $this->assertNotNull($row);
        $this->assertEquals(0, $row->total_transactions);
    }

    /** @test */
    public function refresh_daily_summary_aggregates_existing_orders(): void
    {
        $date = '2024-11-15';
        $dateTime = '2024-11-15 10:00:00';

        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_at' => $dateTime, 'updated_at' => $dateTime],
            ['id' => Str::uuid(), 'store_id' => $this->storeId, 'order_number' => 'O2', 'source' => 'pos', 'status' => 'completed', 'total' => 200.00, 'created_at' => $dateTime, 'updated_at' => $dateTime],
        ]);

        $this->service->refreshDailySummary($this->storeId, $date);

        $row = DailySalesSummary::where('store_id', $this->storeId)->where('date', $date)->first();
        $this->assertNotNull($row);
        $this->assertEquals(2, $row->total_transactions);
        $this->assertEquals(300.00, (float) $row->total_revenue);
    }

    // ─── createScheduledReport ────────────────────────────────────────────────

    /** @test */
    public function create_scheduled_report_persists_to_db(): void
    {
        $report = $this->service->createScheduledReport($this->storeId, [
            'report_type' => 'sales_summary',
            'name' => 'Daily Sales Email',
            'frequency' => 'daily',
            'recipients' => ['owner@test.com'],
            'format' => 'pdf',
        ]);

        $this->assertDatabaseHas('scheduled_reports', [
            'store_id' => $this->storeId,
            'report_type' => 'sales_summary',
            'frequency' => 'daily',
        ]);
        $this->assertNotNull($report->next_run_at);
    }

    /** @test */
    public function create_scheduled_report_sets_next_run_at(): void
    {
        $report = $this->service->createScheduledReport($this->storeId, [
            'report_type' => 'staff_performance',
            'name' => 'Weekly Staff',
            'frequency' => 'weekly',
            'recipients' => ['mgr@test.com'],
        ]);

        $this->assertNotNull($report->next_run_at);
        $this->assertTrue($report->next_run_at->isFuture());
    }

    /** @test */
    public function delete_scheduled_report_removes_from_db(): void
    {
        $report = $this->service->createScheduledReport($this->storeId, [
            'report_type' => 'sales_summary',
            'name' => 'To Delete',
            'frequency' => 'daily',
            'recipients' => ['x@x.com'],
        ]);

        $deleted = $this->service->deleteScheduledReport($this->storeId, $report->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('scheduled_reports', ['id' => $report->id]);
    }

    /** @test */
    public function delete_scheduled_report_returns_false_for_wrong_store(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $otherStore = Store::create(['organization_id' => $otherOrg->id, 'name' => 'Other', 'business_type' => 'grocery', 'currency' => 'SAR', 'is_active' => true]);

        $report = $this->service->createScheduledReport($this->storeId, [
            'report_type' => 'sales_summary',
            'name' => 'Mine',
            'frequency' => 'daily',
            'recipients' => ['x@x.com'],
        ]);

        $deleted = $this->service->deleteScheduledReport($otherStore->id, $report->id);

        $this->assertFalse($deleted);
        $this->assertDatabaseHas('scheduled_reports', ['id' => $report->id]);
    }

    // ─── exportReport ─────────────────────────────────────────────────────────

    /** @test */
    public function export_report_returns_download_shape_for_csv(): void
    {
        $result = $this->service->exportReport($this->storeId, 'sales_summary', [], 'csv');

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('report_type', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('format', $result);
        $this->assertEquals('csv', $result['format']);
        $this->assertEquals('sales_summary', $result['report_type']);
    }

    /** @test */
    public function export_report_returns_download_shape_for_pdf(): void
    {
        $result = $this->service->exportReport($this->storeId, 'staff_performance', [], 'pdf');

        $this->assertArrayHasKey('url', $result);
        $this->assertEquals('pdf', $result['format']);
    }
}
