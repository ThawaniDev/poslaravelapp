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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Filter tests for Report API endpoints.
 *
 * Middleware is bypassed (default TestCase behaviour) so every request
 * with a valid Sanctum token succeeds at the auth/plan layer, and only
 * the filter behaviour is exercised.
 */
class ReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Filter Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Filter Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Filter User',
            'email' => 'filter@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    private function apiHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── date_from / date_to filter ───────────────────────────────────────────

    /** @test */
    public function sales_summary_date_from_is_inclusive(): void
    {
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-01', 'total_revenue' => 100.00, 'total_transactions' => 1]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-10-31', 'total_revenue' => 999.00, 'total_transactions' => 1]);

        $this->getJson('/api/v2/reports/sales-summary?date_from=2024-11-01', $this->apiHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.totals.total_revenue', fn ($v) => (float) $v === 100.0);
    }

    /** @test */
    public function sales_summary_date_to_is_inclusive(): void
    {
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-30', 'total_revenue' => 200.00, 'total_transactions' => 1]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-12-01', 'total_revenue' => 999.00, 'total_transactions' => 1]);

        $this->getJson('/api/v2/reports/sales-summary?date_to=2024-11-30', $this->apiHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.totals.total_revenue', fn ($v) => (float) $v === 200.0);
    }

    /** @test */
    public function invalid_date_format_returns_422(): void
    {
        $this->getJson('/api/v2/reports/sales-summary?date_from=not-a-date', $this->apiHeaders())
            ->assertStatus(422);
    }

    /** @test */
    public function date_to_before_date_from_returns_422(): void
    {
        $this->getJson('/api/v2/reports/sales-summary?date_from=2024-11-30&date_to=2024-11-01', $this->apiHeaders())
            ->assertStatus(422);
    }

    // ─── granularity ─────────────────────────────────────────────────────────

    /** @test */
    public function granularity_daily_returns_one_bucket_per_day(): void
    {
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-01', 'total_revenue' => 100.00]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-02', 'total_revenue' => 200.00]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-11-01&date_to=2024-11-02&granularity=daily',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertCount(2, $response->json('data.series'));
    }

    /** @test */
    public function granularity_weekly_collapses_days_in_same_week(): void
    {
        // Mon–Wed of the same ISO week
        foreach (['2024-11-04', '2024-11-05', '2024-11-06'] as $date) {
            DailySalesSummary::create(['store_id' => $this->store->id, 'date' => $date, 'total_revenue' => 100.00, 'total_transactions' => 1]);
        }

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-11-04&date_to=2024-11-06&granularity=weekly',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertCount(1, $response->json('data.series'));
        $this->assertEquals(300.00, (float) $response->json('data.series.0.total_revenue'));
    }

    /** @test */
    public function granularity_monthly_collapses_same_month(): void
    {
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-01', 'total_revenue' => 400.00]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-15', 'total_revenue' => 600.00]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-12-01', 'total_revenue' => 200.00]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?granularity=monthly',
            $this->apiHeaders()
        )->assertStatus(200);

        // Nov and Dec → 2 buckets
        $this->assertCount(2, $response->json('data.series'));
    }

    // ─── order_source filter ─────────────────────────────────────────────────

    /** @test */
    public function order_source_pos_only_returns_pos_orders(): void
    {
        $now = now()->toDateTimeString();
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'P1', 'source' => 'pos', 'status' => 'completed', 'total' => 300.00, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'D1', 'source' => 'delivery', 'status' => 'completed', 'total' => 700.00, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?order_source=pos',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(300.00, (float) $response->json('data.totals.total_revenue'));
    }

    /** @test */
    public function order_source_delivery_returns_only_delivery_orders(): void
    {
        $now = now()->toDateTimeString();
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'P1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'D1', 'source' => 'delivery', 'status' => 'completed', 'total' => 500.00, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?order_source=delivery',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(500.00, (float) $response->json('data.totals.total_revenue'));
    }

    /** @test */
    public function order_source_online_filters_correctly(): void
    {
        $now = now()->toDateTimeString();
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'O1', 'source' => 'online', 'status' => 'completed', 'total' => 450.00, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'P1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?order_source=online',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(450.00, (float) $response->json('data.totals.total_revenue'));
    }

    /** @test */
    public function order_source_phone_filters_correctly(): void
    {
        $now = now()->toDateTimeString();
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'PH1', 'source' => 'phone', 'status' => 'completed', 'total' => 220.00, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?order_source=phone',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(220.00, (float) $response->json('data.totals.total_revenue'));
    }

    /** @test */
    public function invalid_order_source_returns_422(): void
    {
        $this->getJson(
            '/api/v2/reports/sales-summary?order_source=unknown_channel',
            $this->apiHeaders()
        )->assertStatus(422);
    }

    // ─── order_status filter ─────────────────────────────────────────────────

    /** @test */
    public function order_status_completed_excludes_refunded_orders(): void
    {
        $now = now()->toDateTimeString();
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'C1', 'source' => 'pos', 'status' => 'completed', 'total' => 300.00, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'R1', 'source' => 'pos', 'status' => 'refunded', 'total' => 100.00, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?order_status=completed',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(300.00, (float) $response->json('data.totals.total_revenue'));
    }

    // ─── payment_method filter ────────────────────────────────────────────────

    /** @test */
    public function payment_method_filter_on_payment_methods_endpoint(): void
    {
        // Insert 2 payments, one cash one card, for today
        $orderId = Str::uuid()->toString();
        $now = now()->toDateTimeString();

        DB::table('orders')->insert(['id' => $orderId, 'store_id' => $this->store->id, 'order_number' => 'O1', 'source' => 'pos', 'status' => 'completed', 'total' => 200.00, 'created_at' => $now, 'updated_at' => $now]);

        $transId = Str::uuid()->toString();
        DB::table('transactions')->insert([
            'id' => $transId,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'T001',
            'type' => 'sale',
            'status' => 'completed',
            'total_amount' => 200.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('payments')->insert([
            ['id' => Str::uuid(), 'transaction_id' => $transId, 'method' => 'cash', 'amount' => 100.00, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'transaction_id' => $transId, 'method' => 'card', 'amount' => 100.00, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/payment-methods?payment_method=cash',
            $this->apiHeaders()
        )->assertStatus(200);

        // Should return only cash entries
        $methods = collect($response->json('data'))->pluck('method')->toArray();
        foreach ($methods as $m) {
            $this->assertEquals('cash', $m);
        }
    }

    // ─── staff_id filter ─────────────────────────────────────────────────────

    /** @test */
    public function staff_id_filter_returns_only_that_staff_orders(): void
    {
        $s1 = Str::uuid()->toString();
        $s2 = Str::uuid()->toString();
        $now = now()->toDateTimeString();

        // Insert staff users so the JOIN in staffPerformance works
        DB::table('staff_users')->insert([
            ['id' => $s1, 'user_id' => Str::uuid(), 'store_id' => $this->store->id, 'first_name' => 'Alice', 'last_name' => 'A', 'pin_hash' => bcrypt('1234'), 'status' => 'active', 'employment_type' => 'full_time', 'created_at' => $now, 'updated_at' => $now],
            ['id' => $s2, 'user_id' => Str::uuid(), 'store_id' => $this->store->id, 'first_name' => 'Bob', 'last_name' => 'B', 'pin_hash' => bcrypt('1234'), 'status' => 'active', 'employment_type' => 'full_time', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'S1O1', 'source' => 'pos', 'status' => 'completed', 'total' => 200.00, 'created_by' => $s1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'S2O1', 'source' => 'pos', 'status' => 'completed', 'total' => 500.00, 'created_by' => $s2, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->getJson(
            "/api/v2/reports/staff-performance?staff_id={$s1}",
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(200.00, (float) $response->json('data.0.total_revenue'));
    }

    // ─── category_id filter on product performance ────────────────────────────

    /** @test */
    public function category_id_filter_restricts_product_performance(): void
    {
        $cat1 = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat A', 'sort_order' => 1]);
        $cat2 = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat B', 'sort_order' => 2]);

        $p1 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat1->id, 'name' => 'P Cat A', 'sku' => 'CA001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);
        $p2 = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat2->id, 'name' => 'P Cat B', 'sku' => 'CB001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);

        ProductSalesSummary::create(['store_id' => $this->store->id, 'product_id' => $p1->id, 'date' => '2024-11-01', 'revenue' => 300.00, 'quantity_sold' => 30]);
        ProductSalesSummary::create(['store_id' => $this->store->id, 'product_id' => $p2->id, 'date' => '2024-11-01', 'revenue' => 600.00, 'quantity_sold' => 60]);

        $response = $this->getJson(
            "/api/v2/reports/product-performance?category_id={$cat1->id}",
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('P Cat A', $response->json('data.0.product_name'));
    }

    // ─── limit filter ─────────────────────────────────────────────────────────

    /** @test */
    public function limit_filter_restricts_number_of_returned_rows(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        foreach (range(1, 10) as $i) {
            $p = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => "P{$i}", 'sku' => "SKU{$i}", 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);
            ProductSalesSummary::create(['store_id' => $this->store->id, 'product_id' => $p->id, 'date' => '2024-11-01', 'revenue' => ($i * 100.0), 'quantity_sold' => $i]);
        }

        $response = $this->getJson(
            '/api/v2/reports/product-performance?limit=5',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertCount(5, $response->json('data'));
    }

    // ─── compare filter ──────────────────────────────────────────────────────

    /** @test */
    public function compare_filter_returns_previous_period_data(): void
    {
        // Current period Nov 11-20: 1000 revenue
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-15', 'total_revenue' => 1000.00, 'total_transactions' => 10]);
        // Previous period Nov 1-10: 800 revenue
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-05', 'total_revenue' => 800.00, 'total_transactions' => 8]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-11-11&date_to=2024-11-20&compare=1',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertArrayHasKey('previous_period', $response->json('data'));
        $previous = $response->json('data.previous_period');
        $this->assertNotNull($previous);
        $this->assertEquals(800.00, (float) $previous['total_revenue']);
    }

    /** @test */
    public function compare_filter_zero_returns_no_previous_period(): void
    {
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-15', 'total_revenue' => 1000.00]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-11-11&date_to=2024-11-20&compare=0',
            $this->apiHeaders()
        )->assertStatus(200);

        $previous = $response->json('data.previous_period');
        $this->assertNull($previous);
    }

    // ─── branch_id filter (multi-branch) ──────────────────────────────────────

    /** @test */
    public function branch_id_same_org_returns_branch_data(): void
    {
        // Second branch in same org
        $branch = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch 2',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-01', 'total_revenue' => 1000.00]);
        DailySalesSummary::create(['store_id' => $branch->id, 'date' => '2024-11-01', 'total_revenue' => 5000.00]);

        $response = $this->getJson(
            "/api/v2/reports/sales-summary?branch_id={$branch->id}",
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(5000.00, (float) $response->json('data.totals.total_revenue'));
    }

    /** @test */
    public function branch_id_from_different_org_falls_back_to_own_store(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $foreignBranch = Store::create(['organization_id' => $otherOrg->id, 'name' => 'Foreign', 'business_type' => 'grocery', 'currency' => 'SAR', 'is_active' => true]);

        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-01', 'total_revenue' => 1000.00]);
        DailySalesSummary::create(['store_id' => $foreignBranch->id, 'date' => '2024-11-01', 'total_revenue' => 9999.00]);

        $response = $this->getJson(
            "/api/v2/reports/sales-summary?branch_id={$foreignBranch->id}",
            $this->apiHeaders()
        )->assertStatus(200);

        // Should return own store data, not the foreign branch
        $this->assertEquals(1000.00, (float) $response->json('data.totals.total_revenue'));
    }

    // ─── Hourly sales date filter ─────────────────────────────────────────────

    /** @test */
    public function hourly_sales_date_filter_restricts_to_specified_day(): void
    {
        $day1 = '2024-11-01 10:00:00';
        $day2 = '2024-11-02 10:00:00';

        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'O1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_at' => $day1, 'updated_at' => $day1],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'O2', 'source' => 'pos', 'status' => 'completed', 'total' => 999.00, 'created_at' => $day2, 'updated_at' => $day2],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/hourly-sales?date_from=2024-11-01&date_to=2024-11-01',
            $this->apiHeaders()
        )->assertStatus(200);

        $total = collect($response->json('data'))->sum('total_revenue');
        $this->assertEquals(100.00, (float) $total);
    }

    // ─── inventory filters ────────────────────────────────────────────────────

    /** @test */
    public function inventory_low_stock_endpoint_returns_200_with_correct_shape(): void
    {
        $response = $this->getJson(
            '/api/v2/reports/inventory/low-stock',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertIsArray($response->json('data'));
    }

    /** @test */
    public function inventory_expiry_endpoint_returns_200_with_correct_shape(): void
    {
        $response = $this->getJson(
            '/api/v2/reports/inventory/expiry',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertIsArray($response->json('data'));
    }

    // ─── sort_by / sort_dir on product performance ────────────────────────────

    /** @test */
    public function sort_by_quantity_sorts_correctly(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        $pA = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'A', 'sku' => 'SA001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);
        $pB = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'B', 'sku' => 'SB001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);

        ProductSalesSummary::create(['store_id' => $this->store->id, 'product_id' => $pA->id, 'date' => '2024-11-01', 'revenue' => 100.00, 'quantity_sold' => 5]);
        ProductSalesSummary::create(['store_id' => $this->store->id, 'product_id' => $pB->id, 'date' => '2024-11-01', 'revenue' => 50.00, 'quantity_sold' => 20]);

        $response = $this->getJson(
            '/api/v2/reports/product-performance?sort_by=quantity&sort_dir=desc',
            $this->apiHeaders()
        )->assertStatus(200);

        $names = collect($response->json('data'))->pluck('product_name')->toArray();
        $this->assertEquals('B', $names[0]);
    }

    // ─── expense date filter ──────────────────────────────────────────────────

    /** @test */
    public function expenses_date_filter_restricts_results(): void
    {
        DB::table('expenses')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'category' => 'utilities', 'amount' => 500.00, 'expense_date' => '2024-11-01', 'description' => 'Nov', 'recorded_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'category' => 'utilities', 'amount' => 300.00, 'expense_date' => '2024-10-15', 'description' => 'Oct', 'recorded_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/financial/expenses?date_from=2024-11-01&date_to=2024-11-30',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(500.00, (float) $response->json('data.total_expenses'));
    }

    // ─── min_amount / max_amount filter on top customers ─────────────────────

    /** @test */
    public function top_customers_sorted_by_spend(): void
    {
        $orgId = $this->org->id;

        DB::table('customers')->insert([
            ['id' => Str::uuid(), 'organization_id' => $orgId, 'name' => 'Big Spender', 'total_spend' => 1000.00, 'visit_count' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'organization_id' => $orgId, 'name' => 'Small Spender', 'total_spend' => 50.00, 'visit_count' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson(
            '/api/v2/reports/customers/top',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals('Big Spender', $response->json('data.0.name'));
        $this->assertEquals(1000.00, (float) $response->json('data.0.total_spend'));
    }
}
