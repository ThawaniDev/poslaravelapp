<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Order\Models\Order;
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CompanionComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Companion Test Org',
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
            'name' => 'Owner',
            'email' => 'companion-comprehensive@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ═════════════════════════════════════════════════════════════════
    // QUICK STATS — Column names, data correctness, response structure
    // ═════════════════════════════════════════════════════════════════

    public function test_quick_stats_returns_all_expected_keys(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/quick-stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'store_name',
                    'today_revenue',
                    'today_transactions',
                    'today_orders',
                    'pending_orders',
                    'active_staff',
                    'low_stock_items',
                    'last_sync',
                    'currency',
                ],
            ]);
    }

    public function test_quick_stats_reflects_todays_orders(): void
    {
        // Create today's orders
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-001',
            'status' => 'completed',
            'total' => 100.50,
            'subtotal' => 87.39,
            'tax_amount' => 13.11,
        ]);
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-002',
            'status' => 'completed',
            'total' => 50.00,
            'subtotal' => 43.48,
            'tax_amount' => 6.52,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/quick-stats');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(150.50, $data['today_revenue']);
        $this->assertEquals(2, $data['today_transactions']);
        $this->assertEquals(2, $data['today_orders']);
    }

    public function test_quick_stats_counts_pending_orders(): void
    {
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-P1',
            'status' => 'new',
            'total' => 50,
            'subtotal' => 43.48,
            'tax_amount' => 6.52,
        ]);
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-P2',
            'status' => 'confirmed',
            'total' => 30,
            'subtotal' => 26.09,
            'tax_amount' => 3.91,
        ]);
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-C1',
            'status' => 'completed',
            'total' => 20,
            'subtotal' => 17.39,
            'tax_amount' => 2.61,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/quick-stats');

        $data = $response->json('data');
        $this->assertEquals(2, $data['pending_orders']);
    }

    public function test_quick_stats_counts_active_staff_via_clock_in_at(): void
    {
        // This tests the FIXED column name: clock_in_at (not clock_in)
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => null, // Still clocked in
        ]);

        // Clocked-out staff should not count
        $staff2 = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Sara',
            'last_name' => 'Test',
            'status' => 'active',
        ]);
        AttendanceRecord::create([
            'staff_user_id' => $staff2->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(5),
            'clock_out_at' => now()->subHours(1),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/quick-stats');

        $data = $response->json('data');
        $this->assertEquals(1, $data['active_staff']);
    }

    public function test_quick_stats_counts_low_stock_items(): void
    {
        $product1 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Low Stock Item',
            'sell_price' => 10,
            'sync_version' => 1,
        ]);
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product1->id,
            'quantity' => 3,
            'reorder_point' => 10,
        ]);

        $product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Well Stocked Item',
            'sell_price' => 20,
            'sync_version' => 1,
        ]);
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'quantity' => 100,
            'reorder_point' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/quick-stats');

        $data = $response->json('data');
        $this->assertEquals(1, $data['low_stock_items']);
    }

    public function test_quick_stats_returns_correct_currency(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/quick-stats');

        $this->assertEquals('SAR', $response->json('data.currency'));
    }

    public function test_quick_stats_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/quick-stats')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // DASHBOARD — Today vs yesterday comparison
    // ═══════════════════════════════════════════════════════════

    public function test_dashboard_returns_all_expected_keys(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'store' => ['id', 'name', 'currency', 'is_active'],
                    'today' => ['revenue', 'orders', 'average_order'],
                    'comparison' => ['yesterday_revenue', 'revenue_change_percent'],
                    'quick_stats',
                ],
            ]);
    }

    public function test_dashboard_reflects_store_is_active_status(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/dashboard');

        // Store is_active was set to true
        $this->assertTrue($response->json('data.store.is_active'));
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/dashboard')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // SALES SUMMARY — Period param, date range, data
    // ═══════════════════════════════════════════════════════════

    public function test_sales_summary_with_today_period(): void
    {
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-S1',
            'status' => 'completed',
            'total' => 200.00,
            'subtotal' => 173.91,
            'tax_amount' => 26.09,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/sales/summary?period=today');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['from', 'to'],
                    'summary' => [
                        'total_orders',
                        'total_revenue',
                        'total_tax',
                        'total_discount',
                        'average_order',
                    ],
                    'daily_breakdown',
                ],
            ]);

        $this->assertIsArray($response->json('data.period'));
        $this->assertArrayHasKey('from', $response->json('data.period'));
        $this->assertArrayHasKey('to', $response->json('data.period'));
    }

    public function test_sales_summary_with_week_period(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/sales/summary?period=week');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['period' => ['from', 'to'], 'summary']]);
    }

    public function test_sales_summary_with_month_period(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/sales/summary?period=month');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['period' => ['from', 'to'], 'summary']]);
    }

    public function test_sales_summary_with_from_to_dates(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/sales/summary?from=2025-01-01&to=2025-01-31');

        $response->assertOk();
    }

    public function test_sales_summary_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/sales/summary')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // ACTIVE ORDERS
    // ═══════════════════════════════════════════════════════════

    public function test_active_orders_returns_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/orders/active');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['orders', 'total'],
            ]);
    }

    public function test_active_orders_only_returns_pending_processing_preparing_ready(): void
    {
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-ACTIVE1',
            'status' => 'new',
            'source' => 'pos',
            'total' => 50,
            'subtotal' => 43.48,
            'tax_amount' => 6.52,
        ]);
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-ACTIVE2',
            'status' => 'confirmed',
            'source' => 'pos',
            'total' => 30,
            'subtotal' => 26.09,
            'tax_amount' => 3.91,
        ]);
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-DONE',
            'status' => 'completed',
            'source' => 'pos',
            'total' => 100,
            'subtotal' => 86.96,
            'tax_amount' => 13.04,
        ]);
        Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-CANCEL',
            'status' => 'cancelled',
            'source' => 'pos',
            'total' => 20,
            'subtotal' => 17.39,
            'tax_amount' => 2.61,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/orders/active');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(2, $data['total']);
    }

    public function test_active_orders_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/orders/active')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // INVENTORY ALERTS
    // ═══════════════════════════════════════════════════════════

    public function test_inventory_alerts_returns_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/inventory/alerts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['low_stock_items', 'total_low_stock', 'total_out_of_stock'],
            ]);
    }

    public function test_inventory_alerts_detects_low_stock(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Low Coffee',
            'sell_price' => 5,
            'sync_version' => 1,
        ]);
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'reorder_point' => 10,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/inventory/alerts');

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['total_low_stock']);
    }

    public function test_inventory_alerts_detects_out_of_stock(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Empty Product',
            'sell_price' => 15,
            'sync_version' => 1,
        ]);
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'reorder_point' => 5,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/inventory/alerts');

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['total_out_of_stock']);
    }

    public function test_inventory_alerts_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/inventory/alerts')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // ACTIVE STAFF — Uses clock_in_at column (NOT clock_in)
    // ═══════════════════════════════════════════════════════════

    public function test_active_staff_returns_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/staff/active');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['staff', 'total', 'clocked_in'],
            ]);
    }

    public function test_active_staff_returns_clocked_in_only(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Active',
            'last_name' => 'Worker',
            'status' => 'active',
        ]);
        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subMinutes(30),
            'clock_out_at' => null,
        ]);

        $staffOut = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Done',
            'last_name' => 'Worker',
            'status' => 'active',
        ]);
        AttendanceRecord::create([
            'staff_user_id' => $staffOut->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(8),
            'clock_out_at' => now()->subHours(1),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/staff/active');

        $data = $response->json('data');
        $this->assertEquals(1, $data['clocked_in']);
    }

    public function test_active_staff_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/staff/active')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // BRANCHES
    // ═══════════════════════════════════════════════════════════

    public function test_branches_returns_data(): void
    {
        // Create a second branch
        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch 2',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/branches');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_branches_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/branches')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // STORE AVAILABILITY TOGGLE
    // ═══════════════════════════════════════════════════════════

    public function test_toggle_store_availability(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/companion/store/availability', [
                'is_active' => false,
            ]);

        $response->assertOk();

        // Verify DB updated
        $this->store->refresh();
        $this->assertFalse($this->store->is_active);
    }

    public function test_toggle_store_availability_back_on(): void
    {
        $this->store->update(['is_active' => false]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/companion/store/availability', [
                'is_active' => true,
            ]);

        $response->assertOk();
        $this->store->refresh();
        $this->assertTrue($this->store->is_active);
    }

    // ═══════════════════════════════════════════════════════════
    // SESSIONS — Cache-based
    // ═══════════════════════════════════════════════════════════

    public function test_register_session(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/companion/sessions', [
                'device_name' => 'iPhone 15',
                'device_os' => 'iOS 18',
                'app_version' => '3.0.0',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotEmpty($data['session_id']);
        $this->assertEquals('iPhone 15', $data['device_name']);
    }

    public function test_register_session_validation_requires_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/companion/sessions', []);

        $response->assertStatus(422);
    }

    public function test_list_sessions_empty(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/sessions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('sessions', $data);
    }

    public function test_end_session(): void
    {
        // Register first
        $reg = $this->withToken($this->token)
            ->postJson('/api/v2/companion/sessions', [
                'device_name' => 'Test Device',
                'device_os' => 'Android 14',
                'app_version' => '2.0.0',
            ]);
        $sessionId = $reg->json('data.session_id');

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/companion/sessions/{$sessionId}/end");

        $response->assertOk();
        $this->assertNotNull($response->json('data.ended_at'));
    }

    // ═══════════════════════════════════════════════════════════
    // PREFERENCES — Cache-based
    // ═══════════════════════════════════════════════════════════

    public function test_get_default_preferences(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/preferences');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('theme', $data);
        $this->assertArrayHasKey('language', $data);
        $this->assertEquals('system', $data['theme']);
    }

    public function test_update_preferences(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/companion/preferences', [
                'theme' => 'dark',
                'language' => 'ar',
                'compact_mode' => true,
            ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('dark', $data['theme']);
        $this->assertEquals('ar', $data['language']);
        $this->assertTrue($data['compact_mode']);
    }

    public function test_preferences_persist_across_requests(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/v2/companion/preferences', [
                'theme' => 'dark',
                'language' => 'ar',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/preferences');

        $this->assertEquals('dark', $response->json('data.theme'));
        $this->assertEquals('ar', $response->json('data.language'));
    }

    // ═══════════════════════════════════════════════════════════
    // QUICK ACTIONS
    // ═══════════════════════════════════════════════════════════

    public function test_get_quick_actions_returns_defaults(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/quick-actions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('actions', $data);
        $this->assertNotEmpty($data['actions']);
    }

    public function test_update_quick_actions(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/companion/quick-actions', [
                'actions' => [
                    [
                        'id' => 'new_order',
                        'label' => 'New Order',
                        'icon' => 'add_shopping_cart',
                        'enabled' => true,
                        'order' => 1,
                    ],
                    [
                        'id' => 'inventory_check',
                        'label' => 'Inventory Check',
                        'icon' => 'inventory',
                        'enabled' => true,
                        'order' => 2,
                    ],
                ],
            ]);

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // MOBILE SUMMARY
    // ═══════════════════════════════════════════════════════════

    public function test_mobile_summary_returns_extended_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/summary');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('quick_stats', $data);
    }

    public function test_mobile_summary_requires_auth(): void
    {
        $this->getJson('/api/v2/companion/summary')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // LOG EVENTS
    // ═══════════════════════════════════════════════════════════

    public function test_log_event(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/companion/events', [
                'event_type' => 'screen_view',
                'event_data' => ['screen' => 'dashboard'],
            ]);

        $response->assertStatus(201);
    }

    public function test_log_event_validation(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/companion/events', []);

        $response->assertStatus(422);
    }
}
