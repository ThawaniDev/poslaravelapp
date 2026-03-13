<?php

namespace Tests\Feature\Report;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportApiTest extends TestCase
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
            'name' => 'Report Org',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@report.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Other org for isolation
        $this->otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'retail',
            'country' => 'OM',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@report.com',
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

    public function test_unauthenticated_cannot_access_reports(): void
    {
        $this->getJson('/api/v2/reports/sales-summary')
            ->assertUnauthorized();
    }

    // ─── Sales Summary ───────────────────────────────────────

    public function test_sales_summary_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/sales-summary', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.total_transactions', 0)
            ->assertJsonPath('data.daily', []);
    }

    public function test_sales_summary_returns_aggregated_data(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-01',
            'total_transactions' => 10,
            'total_revenue' => 1000.00,
            'total_cost' => 600.00,
            'total_discount' => 50.00,
            'total_tax' => 100.00,
            'total_refunds' => 25.00,
            'net_revenue' => 825.00,
            'cash_revenue' => 500.00,
            'card_revenue' => 400.00,
            'other_revenue' => 100.00,
            'avg_basket_size' => 100.00,
            'unique_customers' => 8,
        ]);

        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-02',
            'total_transactions' => 15,
            'total_revenue' => 1500.00,
            'total_cost' => 900.00,
            'total_discount' => 75.00,
            'total_tax' => 150.00,
            'total_refunds' => 30.00,
            'net_revenue' => 1245.00,
            'cash_revenue' => 800.00,
            'card_revenue' => 600.00,
            'other_revenue' => 100.00,
            'avg_basket_size' => 100.00,
            'unique_customers' => 12,
        ]);

        $response = $this->getJson('/api/v2/reports/sales-summary', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(25, $data['totals']['total_transactions']);
        $this->assertEquals(2500.0, $data['totals']['total_revenue']);
        $this->assertEquals(2070.0, $data['totals']['net_revenue']);
        $this->assertEquals(20, $data['totals']['unique_customers']);
        $this->assertCount(2, $data['daily']);
    }

    public function test_sales_summary_date_filter(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-01',
            'total_transactions' => 10,
            'total_revenue' => 1000.00,
            'total_cost' => 600.00,
            'net_revenue' => 400.00,
        ]);
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-15',
            'total_transactions' => 20,
            'total_revenue' => 2000.00,
            'total_cost' => 1200.00,
            'net_revenue' => 800.00,
        ]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-06-10&date_to=2024-06-20',
            $this->authHeader(),
        );
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data['daily']);
        $this->assertEquals(20, $data['totals']['total_transactions']);
    }

    public function test_sales_summary_store_isolation(): void
    {
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => '2024-06-01',
            'total_transactions' => 10,
            'total_revenue' => 1000.00,
        ]);
        DailySalesSummary::create([
            'store_id' => $this->otherStore->id,
            'date' => '2024-06-01',
            'total_transactions' => 99,
            'total_revenue' => 9999.00,
        ]);

        $response = $this->getJson('/api/v2/reports/sales-summary', $this->authHeader());
        $data = $response->json('data');
        $this->assertEquals(10, $data['totals']['total_transactions']);
        $this->assertEquals(1000.0, $data['totals']['total_revenue']);
    }

    // ─── Product Performance ────────────────────────────────

    public function test_product_performance_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/product-performance', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_product_performance_returns_ranked_products(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Electronics',
            'name_ar' => 'إلكترونيات',
            'is_active' => true,
        ]);

        $product1 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'Laptop',
            'name_ar' => 'لابتوب',
            'sku' => 'LAP-001',
            'sell_price' => 500.00,
            'cost_price' => 300.00,
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'Mouse',
            'name_ar' => 'ماوس',
            'sku' => 'MOU-001',
            'sell_price' => 20.00,
            'cost_price' => 8.00,
            'is_active' => true,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $product1->id,
            'date' => '2024-06-01',
            'quantity_sold' => 5,
            'revenue' => 2500.00,
            'cost' => 1500.00,
            'discount_amount' => 100.00,
            'return_quantity' => 1,
            'return_amount' => 500.00,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'date' => '2024-06-01',
            'quantity_sold' => 50,
            'revenue' => 1000.00,
            'cost' => 400.00,
            'discount_amount' => 0,
            'return_quantity' => 0,
            'return_amount' => 0,
        ]);

        $response = $this->getJson('/api/v2/reports/product-performance', $this->authHeader());
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Sorted by revenue desc, so Laptop first
        $this->assertEquals($product1->id, $data[0]['product_id']);
        $this->assertEquals('Laptop', $data[0]['product_name']);
        $this->assertEquals(2500.0, $data[0]['total_revenue']);
        $this->assertEquals(1000.0, $data[0]['profit']);
        $this->assertEquals(1, $data[0]['total_returns']);

        // Mouse second
        $this->assertEquals($product2->id, $data[1]['product_id']);
        $this->assertEquals(600.0, $data[1]['profit']);
    }

    public function test_product_performance_category_filter(): void
    {
        $cat1 = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Electronics',
            'is_active' => true,
        ]);
        $cat2 = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Food',
            'is_active' => true,
        ]);

        $prod1 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat1->id,
            'name' => 'Phone',
            'sku' => 'PHN-001',
            'sell_price' => 100.00,
            'cost_price' => 50.00,
            'is_active' => true,
        ]);
        $prod2 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat2->id,
            'name' => 'Pizza',
            'sku' => 'PIZ-001',
            'sell_price' => 10.00,
            'cost_price' => 5.00,
            'is_active' => true,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $prod1->id,
            'date' => '2024-06-01',
            'quantity_sold' => 10,
            'revenue' => 1000.00,
            'cost' => 500.00,
        ]);
        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $prod2->id,
            'date' => '2024-06-01',
            'quantity_sold' => 100,
            'revenue' => 1000.00,
            'cost' => 500.00,
        ]);

        $response = $this->getJson(
            '/api/v2/reports/product-performance?category_id=' . $cat1->id,
            $this->authHeader(),
        );
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Phone', $data[0]['product_name']);
    }

    public function test_product_performance_store_isolation(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Cat',
            'is_active' => true,
        ]);
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'Item',
            'sku' => 'ITM-001',
            'sell_price' => 10.00,
            'cost_price' => 5.00,
            'is_active' => true,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'date' => '2024-06-01',
            'quantity_sold' => 10,
            'revenue' => 100.00,
            'cost' => 50.00,
        ]);
        ProductSalesSummary::create([
            'store_id' => $this->otherStore->id,
            'product_id' => $product->id,
            'date' => '2024-06-01',
            'quantity_sold' => 999,
            'revenue' => 9999.00,
            'cost' => 5000.00,
        ]);

        $response = $this->getJson('/api/v2/reports/product-performance', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(100.0, $data[0]['total_revenue']);
    }

    // ─── Category Breakdown ──────────────────────────────────

    public function test_category_breakdown_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/category-breakdown', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_category_breakdown_returns_grouped_data(): void
    {
        $cat1 = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Electronics',
            'name_ar' => 'إلكترونيات',
            'is_active' => true,
        ]);
        $cat2 = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Groceries',
            'name_ar' => 'بقالة',
            'is_active' => true,
        ]);

        $prod1 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat1->id,
            'name' => 'TV',
            'sku' => 'TV-001',
            'sell_price' => 800.00,
            'cost_price' => 500.00,
            'is_active' => true,
        ]);
        $prod2 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat1->id,
            'name' => 'Radio',
            'sku' => 'RAD-001',
            'sell_price' => 50.00,
            'cost_price' => 20.00,
            'is_active' => true,
        ]);
        $prod3 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat2->id,
            'name' => 'Rice',
            'sku' => 'RICE-001',
            'sell_price' => 3.00,
            'cost_price' => 1.50,
            'is_active' => true,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $prod1->id,
            'date' => '2024-06-01',
            'quantity_sold' => 2,
            'revenue' => 1600.00,
            'cost' => 1000.00,
        ]);
        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $prod2->id,
            'date' => '2024-06-01',
            'quantity_sold' => 10,
            'revenue' => 500.00,
            'cost' => 200.00,
        ]);
        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $prod3->id,
            'date' => '2024-06-01',
            'quantity_sold' => 200,
            'revenue' => 600.00,
            'cost' => 300.00,
        ]);

        $response = $this->getJson('/api/v2/reports/category-breakdown', $this->authHeader());
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Electronics first (higher revenue)
        $this->assertEquals('Electronics', $data[0]['category_name']);
        $this->assertEquals('إلكترونيات', $data[0]['category_name_ar']);
        $this->assertEquals(2100.0, $data[0]['total_revenue']);
        $this->assertEquals(900.0, $data[0]['profit']);
        $this->assertEquals(2, $data[0]['product_count']);

        // Groceries second
        $this->assertEquals('Groceries', $data[1]['category_name']);
        $this->assertEquals(300.0, $data[1]['profit']);
        $this->assertEquals(1, $data[1]['product_count']);
    }

    // ─── Staff Performance ───────────────────────────────────

    public function test_staff_performance_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/staff-performance', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_staff_performance_returns_data(): void
    {
        $staff1 = StaffUser::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
            'employment_type' => 'full_time',
            'salary_type' => 'monthly',
            'hire_date' => '2024-01-01',
        ]);
        $staff2 = StaffUser::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'first_name' => 'Sara',
            'last_name' => 'Hassan',
            'employment_type' => 'full_time',
            'salary_type' => 'monthly',
            'hire_date' => '2024-01-01',
        ]);

        // Create orders attributed to staff
        \DB::table('orders')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'ORD-001', 'status' => 'completed', 'total' => 200.00, 'discount_amount' => 10.00, 'created_by' => $staff1->id, 'created_at' => '2024-06-01 10:00:00', 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'ORD-002', 'status' => 'completed', 'total' => 300.00, 'discount_amount' => 0, 'created_by' => $staff1->id, 'created_at' => '2024-06-01 11:00:00', 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'ORD-003', 'status' => 'completed', 'total' => 150.00, 'discount_amount' => 5.00, 'created_by' => $staff2->id, 'created_at' => '2024-06-01 12:00:00', 'updated_at' => now()],
            // Cancelled order should be excluded
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'ORD-004', 'status' => 'cancelled', 'total' => 999.00, 'discount_amount' => 0, 'created_by' => $staff1->id, 'created_at' => '2024-06-01 13:00:00', 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/v2/reports/staff-performance', $this->authHeader());
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Ahmed first (higher revenue)
        $this->assertEquals('Ahmed Ali', $data[0]['staff_name']);
        $this->assertEquals(2, $data[0]['total_orders']);
        $this->assertEquals(500.0, $data[0]['total_revenue']);
        $this->assertEquals(10.0, $data[0]['total_discounts_given']);

        // Sara second
        $this->assertEquals('Sara Hassan', $data[1]['staff_name']);
        $this->assertEquals(1, $data[1]['total_orders']);
    }

    public function test_staff_performance_date_filter(): void
    {
        $staff = StaffUser::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $this->store->id,
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'employment_type' => 'full_time',
            'salary_type' => 'monthly',
            'hire_date' => '2024-01-01',
        ]);

        \DB::table('orders')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'ORD-A', 'status' => 'completed', 'total' => 100.00, 'discount_amount' => 0, 'created_by' => $staff->id, 'created_at' => '2024-05-01 10:00:00', 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'ORD-B', 'status' => 'completed', 'total' => 200.00, 'discount_amount' => 0, 'created_by' => $staff->id, 'created_at' => '2024-06-15 10:00:00', 'updated_at' => now()],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/staff-performance?date_from=2024-06-01&date_to=2024-06-30',
            $this->authHeader(),
        );
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(200.0, $data[0]['total_revenue']);
    }

    // ─── Hourly Sales ────────────────────────────────────────

    public function test_hourly_sales_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/hourly-sales', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_hourly_sales_returns_pattern(): void
    {
        \DB::table('orders')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'H-001', 'status' => 'completed', 'total' => 100.00, 'created_at' => '2024-06-01 09:15:00', 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'H-002', 'status' => 'completed', 'total' => 200.00, 'created_at' => '2024-06-01 09:45:00', 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'H-003', 'status' => 'completed', 'total' => 150.00, 'created_at' => '2024-06-01 14:30:00', 'updated_at' => now()],
            // Voided excluded
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'H-004', 'status' => 'voided', 'total' => 999.00, 'created_at' => '2024-06-01 09:50:00', 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/v2/reports/hourly-sales', $this->authHeader());
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data); // hour 9 and hour 14
        // Hour 9
        $this->assertEquals(9, $data[0]['hour']);
        $this->assertEquals(2, $data[0]['total_orders']);
        $this->assertEquals(300.0, $data[0]['total_revenue']);
        // Hour 14
        $this->assertEquals(14, $data[1]['hour']);
        $this->assertEquals(1, $data[1]['total_orders']);
    }

    public function test_hourly_sales_store_isolation(): void
    {
        \DB::table('orders')->insert([
            ['id' => Str::uuid()->toString(), 'store_id' => $this->store->id, 'order_number' => 'ISO-1', 'status' => 'completed', 'total' => 100.00, 'created_at' => '2024-06-01 10:00:00', 'updated_at' => now()],
            ['id' => Str::uuid()->toString(), 'store_id' => $this->otherStore->id, 'order_number' => 'ISO-2', 'status' => 'completed', 'total' => 999.00, 'created_at' => '2024-06-01 10:00:00', 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/v2/reports/hourly-sales', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(100.0, $data[0]['total_revenue']);
    }

    // ─── Payment Methods ─────────────────────────────────────

    public function test_payment_methods_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/payment-methods', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_payment_methods_returns_breakdown(): void
    {
        $txnId = Str::uuid()->toString();
        \DB::table('transactions')->insert([
            'id' => $txnId,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-001',
            'type' => 'sale',
            'status' => 'completed',
            'total_amount' => 300.00,
            'created_at' => '2024-06-01 10:00:00',
            'updated_at' => now(),
        ]);

        \DB::table('payments')->insert([
            ['id' => Str::uuid()->toString(), 'transaction_id' => $txnId, 'method' => 'cash', 'amount' => 100.00, 'created_at' => '2024-06-01 10:00:00'],
            ['id' => Str::uuid()->toString(), 'transaction_id' => $txnId, 'method' => 'card', 'amount' => 150.00, 'created_at' => '2024-06-01 10:00:00'],
            ['id' => Str::uuid()->toString(), 'transaction_id' => $txnId, 'method' => 'cash', 'amount' => 50.00, 'created_at' => '2024-06-01 10:00:00'],
        ]);

        $response = $this->getJson('/api/v2/reports/payment-methods', $this->authHeader());
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Cash first (higher total: 150)
        $this->assertEquals('cash', $data[0]['method']);
        $this->assertEquals(2, $data[0]['transaction_count']);
        $this->assertEquals(150.0, $data[0]['total_amount']);
        // Card second
        $this->assertEquals('card', $data[1]['method']);
        $this->assertEquals(150.0, $data[1]['total_amount']);
    }

    public function test_payment_methods_store_isolation(): void
    {
        $txn1Id = Str::uuid()->toString();
        $txn2Id = Str::uuid()->toString();
        \DB::table('transactions')->insert([
            ['id' => $txn1Id, 'organization_id' => $this->org->id, 'store_id' => $this->store->id, 'cashier_id' => $this->user->id, 'transaction_number' => 'TXN-A', 'type' => 'sale', 'status' => 'completed', 'total_amount' => 100.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $txn2Id, 'organization_id' => $this->otherOrg->id, 'store_id' => $this->otherStore->id, 'cashier_id' => $this->otherUser->id, 'transaction_number' => 'TXN-B', 'type' => 'sale', 'status' => 'completed', 'total_amount' => 999.00, 'created_at' => now(), 'updated_at' => now()],
        ]);
        \DB::table('payments')->insert([
            ['id' => Str::uuid()->toString(), 'transaction_id' => $txn1Id, 'method' => 'cash', 'amount' => 100.00, 'created_at' => now()],
            ['id' => Str::uuid()->toString(), 'transaction_id' => $txn2Id, 'method' => 'cash', 'amount' => 999.00, 'created_at' => now()],
        ]);

        $response = $this->getJson('/api/v2/reports/payment-methods', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(100.0, $data[0]['total_amount']);
    }

    // ─── Dashboard ───────────────────────────────────────────

    public function test_dashboard_empty(): void
    {
        $response = $this->getJson('/api/v2/reports/dashboard', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.today.total_transactions', 0)
            ->assertJsonPath('data.yesterday.total_transactions', 0)
            ->assertJsonPath('data.top_products', []);
    }

    public function test_dashboard_returns_today_and_yesterday(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => $today,
            'total_transactions' => 25,
            'total_revenue' => 2500.00,
            'net_revenue' => 2000.00,
            'total_refunds' => 100.00,
            'avg_basket_size' => 100.00,
            'unique_customers' => 20,
        ]);
        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => $yesterday,
            'total_transactions' => 20,
            'total_revenue' => 2000.00,
            'net_revenue' => 1600.00,
        ]);

        $response = $this->getJson('/api/v2/reports/dashboard', $this->authHeader());
        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(25, $data['today']['total_transactions']);
        $this->assertEquals(2500.0, $data['today']['total_revenue']);
        $this->assertEquals(20, $data['yesterday']['total_transactions']);
    }

    public function test_dashboard_top_products(): void
    {
        $today = now()->toDateString();

        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Cat',
            'is_active' => true,
        ]);
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'Top Seller',
            'sku' => 'TOP-001',
            'sell_price' => 50.00,
            'cost_price' => 25.00,
            'is_active' => true,
        ]);

        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => $today,
            'total_transactions' => 1,
            'total_revenue' => 500.00,
        ]);

        ProductSalesSummary::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'date' => $today,
            'quantity_sold' => 10,
            'revenue' => 500.00,
            'cost' => 250.00,
        ]);

        $response = $this->getJson('/api/v2/reports/dashboard', $this->authHeader());
        $data = $response->json('data');
        $this->assertCount(1, $data['top_products']);
        $this->assertEquals('Top Seller', $data['top_products'][0]['product_name']);
        $this->assertEquals(500.0, $data['top_products'][0]['revenue']);
    }

    public function test_dashboard_store_isolation(): void
    {
        $today = now()->toDateString();

        DailySalesSummary::create([
            'store_id' => $this->store->id,
            'date' => $today,
            'total_transactions' => 5,
            'total_revenue' => 500.00,
        ]);
        DailySalesSummary::create([
            'store_id' => $this->otherStore->id,
            'date' => $today,
            'total_transactions' => 99,
            'total_revenue' => 9999.00,
        ]);

        $response = $this->getJson('/api/v2/reports/dashboard', $this->authHeader());
        $data = $response->json('data');
        $this->assertEquals(5, $data['today']['total_transactions']);
    }

    // ─── Validation ──────────────────────────────────────────

    public function test_invalid_date_format_rejected(): void
    {
        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=01-06-2024',
            $this->authHeader(),
        );
        $response->assertStatus(422);
    }

    public function test_date_to_before_date_from_rejected(): void
    {
        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-06-10&date_to=2024-06-01',
            $this->authHeader(),
        );
        $response->assertStatus(422);
    }
}
