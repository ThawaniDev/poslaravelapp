<?php

namespace Tests\Feature\OwnerDashboard;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /**
     * Seed a completed sale transaction (and optional line items) into the live
     * `transactions` / `transaction_items` tables. Returns the new transaction id.
     *
     * @param  array<int, array{product_id?: ?string, quantity?: float, unit_price?: float, cost_price?: float, line_total?: float, tax_amount?: float}>  $items
     */
    private function seedTransaction(
        ?string $storeId = null,
        ?string $userId = null,
        ?Carbon $createdAt = null,
        float $totalAmount = 0,
        float $taxAmount = 0,
        float $discountAmount = 0,
        string $type = 'sale',
        string $status = 'completed',
        ?string $customerId = null,
        array $items = [],
    ): string {
        $resolvedStoreId = $storeId ?? $this->store->id;
        $resolvedUserId  = $userId ?? $this->user->id;
        $registerId      = $this->ensureRegister($resolvedStoreId);
        $sessionId       = $this->ensureSession($resolvedStoreId, $registerId, $resolvedUserId);

        $id = (string) Str::uuid();
        $when = $createdAt ?? Carbon::now();
        DB::table('transactions')->insert([
            'id' => $id,
            'organization_id' => $this->org->id,
            'store_id' => $resolvedStoreId,
            'register_id' => $registerId,
            'pos_session_id' => $sessionId,
            'cashier_id' => $resolvedUserId,
            'customer_id' => $customerId,
            'transaction_number' => 'TXN-' . substr($id, 0, 8),
            'type' => $type,
            'status' => $status,
            'subtotal' => max(0, $totalAmount - $taxAmount),
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'tip_amount' => 0,
            'total_amount' => $totalAmount,
            'created_at' => $when,
            'updated_at' => $when,
        ]);

        foreach ($items as $line) {
            DB::table('transaction_items')->insert([
                'id' => (string) Str::uuid(),
                'transaction_id' => $id,
                'product_id' => $line['product_id'] ?? $this->ensureDefaultProduct(),
                'product_name' => $line['product_name'] ?? 'Item',
                'quantity' => $line['quantity'] ?? 1,
                'unit_price' => $line['unit_price'] ?? 0,
                'cost_price' => $line['cost_price'] ?? 0,
                'discount_amount' => 0,
                'tax_rate' => 0,
                'tax_amount' => $line['tax_amount'] ?? 0,
                'line_total' => $line['line_total'] ?? (($line['unit_price'] ?? 0) * ($line['quantity'] ?? 1)),
                'is_return_item' => false,
            ]);
        }

        return $id;
    }

    private ?string $defaultProductId = null;

    private function ensureDefaultProduct(): string
    {
        if ($this->defaultProductId !== null) {
            return $this->defaultProductId;
        }
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Item',
            'name_ar' => 'صنف',
            'sku' => 'TEST-DEFAULT-' . substr((string) Str::uuid(), 0, 6),
            'sell_price' => 1.00,
            'cost_price' => 0.50,
            'is_active' => true,
        ]);

        return $this->defaultProductId = $product->id;
    }

    /** @var array<string,string> store_id => register_id */
    private array $registersByStore = [];

    private function ensureRegister(string $storeId): string
    {
        if (isset($this->registersByStore[$storeId])) {
            return $this->registersByStore[$storeId];
        }
        $id = (string) Str::uuid();
        DB::table('registers')->insert([
            'id' => $id,
            'store_id' => $storeId,
            'name' => 'Test Register',
            'device_id' => 'dev-' . substr($id, 0, 8),
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $this->registersByStore[$storeId] = $id;
    }

    /** @var array<string,string> register_id => pos_session_id */
    private array $sessionsByRegister = [];

    private function ensureSession(string $storeId, string $registerId, string $userId): string
    {
        if (isset($this->sessionsByRegister[$registerId])) {
            return $this->sessionsByRegister[$registerId];
        }
        $id = (string) Str::uuid();
        DB::table('pos_sessions')->insert([
            'id' => $id,
            'store_id' => $storeId,
            'register_id' => $registerId,
            'cashier_id' => $userId,
            'status' => 'open',
            'opening_cash' => 0,
            'opened_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $this->sessionsByRegister[$registerId] = $id;
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
        // Today: 50 sales totalling 5000 revenue, 750 tax, 3000 cost (net = 5000 - 750 - 3000 = 1250).
        // Spread 35 unique customers and one refund of 100.
        $customerIds = [];
        for ($i = 0; $i < 35; $i++) {
            $customerIds[] = (string) Str::uuid();
        }
        for ($i = 0; $i < 50; $i++) {
            $cust = $customerIds[$i % 35];
            $this->seedTransaction(
                createdAt: Carbon::today()->addHours(10),
                totalAmount: 100,
                taxAmount: 15,
                customerId: $cust,
                items: [[ 'unit_price' => 85, 'cost_price' => 60, 'line_total' => 85, 'tax_amount' => 15 ]],
            );
        }
        $this->seedTransaction(
            createdAt: Carbon::today()->addHours(11),
            totalAmount: 100,
            type: 'return',
        );

        // Yesterday: 40 sales totalling 4000 revenue, 600 tax, 2400 cost (net = 4000 - 600 - 2400 = 1000).
        for ($i = 0; $i < 40; $i++) {
            $this->seedTransaction(
                createdAt: Carbon::yesterday()->addHours(10),
                totalAmount: 100,
                taxAmount: 15,
                items: [[ 'unit_price' => 85, 'cost_price' => 60, 'line_total' => 85, 'tax_amount' => 15 ]],
            );
        }

        $response = $this->getJson('/api/v2/owner-dashboard/stats', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(5000.0, (float) $data['today_sales']['value']);
        $this->assertEquals(50, $data['transactions']['value']);
        $this->assertEquals(1250.0, (float) $data['net_profit']['value']);
        $this->assertEquals(35, $data['unique_customers']);
        $this->assertEquals(100.0, (float) $data['total_refunds']);
        // 5000 vs 4000 -> +25%, 50 vs 40 -> +25%
        $this->assertEquals(25.0, (float) $data['today_sales']['change']);
        $this->assertEquals(25.0, (float) $data['transactions']['change']);
    }

    public function test_stats_data_isolation(): void
    {
        // Live transaction in OTHER org/store; primary store sees zeros.
        $otherRegisterId = (string) Str::uuid();
        DB::table('registers')->insert([
            'id' => $otherRegisterId,
            'store_id' => $this->otherStore->id,
            'name' => 'Other Register',
            'device_id' => 'other-' . substr($otherRegisterId, 0, 8),
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $otherSessionId = (string) Str::uuid();
        DB::table('pos_sessions')->insert([
            'id' => $otherSessionId,
            'store_id' => $this->otherStore->id,
            'register_id' => $otherRegisterId,
            'cashier_id' => $this->otherUser->id,
            'status' => 'open',
            'opening_cash' => 0,
            'opened_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('transactions')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->otherOrg->id,
            'store_id' => $this->otherStore->id,
            'register_id' => $otherRegisterId,
            'pos_session_id' => $otherSessionId,
            'cashier_id' => $this->otherUser->id,
            'transaction_number' => 'OTHER-1',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 9999,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 9999,
            'created_at' => Carbon::today()->addHours(10),
            'updated_at' => Carbon::today()->addHours(10),
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
        // One sale per day for the last 7 days.
        for ($i = 0; $i < 7; $i++) {
            $this->seedTransaction(
                createdAt: Carbon::today()->subDays($i)->setTime(12, 0),
                totalAmount: 1000 + ($i * 100),
                taxAmount: 150,
                items: [[ 'unit_price' => 850 + ($i * 100), 'cost_price' => 600, 'line_total' => 850 + ($i * 100), 'tax_amount' => 150 ]],
            );
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

        // 100 units sold today @ 5 each = 500 revenue.
        $this->seedTransaction(
            createdAt: Carbon::today()->setTime(10, 0),
            totalAmount: 575,
            taxAmount: 75,
            items: [[
                'product_id' => $product->id,
                'product_name' => 'Coffee',
                'quantity' => 100,
                'unit_price' => 5.00,
                'cost_price' => 2.00,
                'line_total' => 500,
                'tax_amount' => 75,
            ]],
        );

        $response = $this->getJson('/api/v2/owner-dashboard/top-products?limit=5&days=30', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Coffee');
        $this->assertEquals(500.0, (float) $response->json('data.0.total_revenue'));
        $this->assertEquals(100.0, (float) $response->json('data.0.total_qty'));
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

    public function test_low_stock_includes_zero_quantity(): void
    {
        // The dashboard 'low stock alerts' panel surfaces both low-and out-of-stock items
        // so owners notice gaps in stock immediately after sales empty the shelf.
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
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Out of Stock Item');
        $this->assertEquals(0.0, (float) $response->json('data.0.current_stock'));
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
        // 20 sales today: 100 each, 15 tax, 60 cost. revenue=2000, tax=300, cost=1200, net=500.
        // Plus a refund of 50.
        for ($i = 0; $i < 20; $i++) {
            $this->seedTransaction(
                createdAt: Carbon::today()->addHours(10),
                totalAmount: 100,
                taxAmount: 15,
                discountAmount: 5,
                items: [[ 'unit_price' => 85, 'cost_price' => 60, 'line_total' => 85, 'tax_amount' => 15 ]],
            );
        }
        $this->seedTransaction(
            createdAt: Carbon::today()->addHours(11),
            totalAmount: 50,
            type: 'return',
        );

        $response = $this->getJson('/api/v2/owner-dashboard/financial-summary', $this->authHeader());
        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(2000.0, (float) $data['revenue']['total']);
        $this->assertEquals(500.0, (float) $data['revenue']['net']);
        $this->assertEquals(300.0, (float) $data['revenue']['tax']);
        $this->assertEquals(100.0, (float) $data['revenue']['discounts']);
        $this->assertEquals(50.0, (float) $data['revenue']['refunds']);
    }

    public function test_financial_summary_date_filter(): void
    {
        // June day: 1 sale of 1000
        $this->seedTransaction(
            createdAt: Carbon::parse('2024-06-15 10:00:00'),
            totalAmount: 1000,
            taxAmount: 150,
        );
        // July day: 1 sale of 2000 (must be excluded)
        $this->seedTransaction(
            createdAt: Carbon::parse('2024-07-15 10:00:00'),
            totalAmount: 2000,
            taxAmount: 300,
        );

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

        // Main store: 50 sales totalling 5000 today.
        for ($i = 0; $i < 50; $i++) {
            $this->seedTransaction(
                storeId: $this->store->id,
                createdAt: Carbon::today()->addHours(10),
                totalAmount: 100,
                taxAmount: 15,
            );
        }
        // Branch 2: 30 sales totalling 3000 today.
        for ($i = 0; $i < 30; $i++) {
            $this->seedTransaction(
                storeId: $branchStore->id,
                createdAt: Carbon::today()->addHours(10),
                totalAmount: 100,
                taxAmount: 15,
            );
        }

        $response = $this->getJson('/api/v2/owner-dashboard/branches', $this->authHeader());
        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Ordered by revenue desc
        $this->assertEquals('Main Store', $response->json('data.0.store_name'));
        $this->assertEquals(5000.0, (float) $response->json('data.0.total_revenue'));
        $this->assertEquals(3000.0, (float) $response->json('data.1.total_revenue'));
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
