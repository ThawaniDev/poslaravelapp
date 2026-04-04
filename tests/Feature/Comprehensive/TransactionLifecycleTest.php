<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Order\Models\Order;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transaction Lifecycle Test
 *
 * Tests the full flow: open session → create transaction → verify DB →
 * verify API response → void → return → close session.
 * Ensures data from Flutter cashier reflects correctly in DB and pages.
 */
class TransactionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Category $category;
    private Register $register;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Lifecycle Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Lifecycle Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'lifecycle@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Food',
            'name_ar' => 'طعام',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $this->register = Register::create([
            'store_id' => $this->store->id,
            'name' => 'POS-1',
            'device_id' => 'LIFECYCLE-DEVICE-001',
            'is_active' => true,
            'is_online' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Arabic Coffee',
            'name_ar' => 'قهوة عربية',
            'sell_price' => 15.00,
            'cost_price' => 7.00,
            'tax_rate' => 15.00,
            'barcode' => '6281111111111',
            'is_active' => true,
            'sync_version' => 1,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FULL POS SESSION LIFECYCLE
    // ═══════════════════════════════════════════════════════════

    public function test_open_pos_session(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/sessions', [
                'register_id' => $this->register->id,
                'opening_cash' => 500.00,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals('open', $data['status']);
        $this->assertEquals(500.00, $data['opening_cash']);
        $this->assertEquals($this->register->id, $data['register_id']);

        // Verify in DB
        $this->assertDatabaseHas('pos_sessions', [
            'id' => $data['id'],
            'status' => 'open',
            'register_id' => $this->register->id,
        ]);
    }

    public function test_create_transaction_within_session(): void
    {
        // Open session first
        $sessionResp = $this->withToken($this->token)
            ->postJson('/api/v2/pos/sessions', [
                'register_id' => $this->register->id,
                'opening_cash' => 500.00,
            ]);
        $sessionId = $sessionResp->json('data.id');

        // Create transaction
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'pos_session_id' => $sessionId,
                'type' => 'sale',
                'subtotal' => 30.00,
                'total_amount' => 34.50,
                'tax_amount' => 4.50,
                'discount_amount' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Arabic Coffee',
                        'quantity' => 2,
                        'unit_price' => 15.00,
                        'line_total' => 30.00,
                    ],
                ],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 34.50,
                        'cash_tendered' => 50.00,
                        'change_given' => 15.50,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals('sale', $data['type']);
        $this->assertEquals('completed', $data['status']);
        $this->assertNotEmpty($data['transaction_number']);

        // Verify DB
        $this->assertDatabaseHas('transactions', [
            'id' => $data['id'],
            'type' => 'sale',
            'pos_session_id' => $sessionId,
        ]);
    }

    public function test_list_transactions_within_session(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-LIST-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'sync_version' => 1,
        ]);
        Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-LIST-002',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50,
            'tax_amount' => 7.5,
            'total_amount' => 57.5,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions?session_id=' . $session->id);

        $response->assertOk();
    }

    public function test_show_transaction_by_number(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-LOOKUP-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/by-number/TXN-LOOKUP-001');

        $response->assertOk()
            ->assertJsonPath('data.transaction_number', 'TXN-LOOKUP-001');
    }

    public function test_void_transaction(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-VOID-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions/' . $txn->id . '/void', [
                'reason' => 'Customer changed mind',
            ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('voided', $data['status']);

        // Verify DB
        $this->assertDatabaseHas('transactions', [
            'id' => $txn->id,
            'status' => 'voided',
        ]);
    }

    public function test_close_pos_session(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v2/pos/sessions/' . $session->id . '/close', [
                'closing_cash' => 1500.00,
            ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('closed', $data['status']);

        // Verify DB
        $this->assertDatabaseHas('pos_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ORDER LIFECYCLE
    // ═══════════════════════════════════════════════════════════

    public function test_create_order_via_api(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/orders', [
                'source' => 'pos',
                'subtotal' => 45.00,
                'total' => 51.75,
                'tax_amount' => 6.75,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Arabic Coffee',
                        'quantity' => 3,
                        'unit_price' => 15.00,
                        'total' => 45.00,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertNotEmpty($data['order_number']);
        $this->assertEquals('pos', $data['source']);

        // Verify DB
        $this->assertDatabaseHas('orders', [
            'id' => $data['id'],
            'source' => 'pos',
        ]);
    }

    public function test_order_status_flow_pending_to_completed(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-FLOW-001',
            'source' => 'pos',
            'status' => 'new',
            'subtotal' => 45.00,
            'tax_amount' => 6.75,
            'total' => 51.75,
        ]);

        // New → Preparing
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/orders/' . $order->id . '/status', [
                'status' => 'preparing',
            ]);
        $response->assertOk();
        $this->assertEquals('preparing', $response->json('data.status'));

        // Preparing → Ready
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/orders/' . $order->id . '/status', [
                'status' => 'ready',
            ]);
        $response->assertOk();
        $this->assertEquals('ready', $response->json('data.status'));

        // Ready → Completed
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/orders/' . $order->id . '/status', [
                'status' => 'completed',
            ]);
        $response->assertOk();
        $this->assertEquals('completed', $response->json('data.status'));

        // Verify final DB state
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    public function test_order_void(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-VOID-001',
            'source' => 'pos',
            'status' => 'new',
            'subtotal' => 20.00,
            'tax_amount' => 3.00,
            'total' => 23.00,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/orders/' . $order->id . '/void', [
                'reason' => 'Duplicate order',
            ]);

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // REGISTER / TERMINAL LIFECYCLE
    // ═══════════════════════════════════════════════════════════

    public function test_create_register(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/terminals', [
                'name' => 'New Terminal',
                'device_id' => 'NEW-DEVICE-XYZ',
                'platform' => 'macos',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('New Terminal', $data['name']);

        $this->assertDatabaseHas('registers', [
            'name' => 'New Terminal',
            'device_id' => 'NEW-DEVICE-XYZ',
        ]);
    }

    public function test_list_registers(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/terminals');

        $response->assertOk();
    }

    public function test_toggle_register_status(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/terminals/' . $this->register->id . '/toggle-status');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // HELD CARTS
    // ═══════════════════════════════════════════════════════════

    public function test_hold_cart(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/held-carts', [
                'register_id' => $this->register->id,
                'cart_data' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Arabic Coffee',
                        'quantity' => 1,
                        'unit_price' => 15.00,
                    ],
                ],
                'label' => 'Walk-in customer',
            ]);

        $response->assertStatus(201);
    }

    public function test_list_held_carts(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/held-carts');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // POS PRODUCTS & CUSTOMERS SEARCH
    // ═══════════════════════════════════════════════════════════

    public function test_pos_product_search(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/products?search=Coffee');

        $response->assertOk();
    }

    public function test_pos_product_barcode_search(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/products?barcode=6281111111111');

        $response->assertOk();
    }

    public function test_pos_customer_search(): void
    {
        Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Walk-in Ahmed',
            'phone' => '+96898765432',
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/customers?search=Ahmed');

        $response->assertOk();
    }
}
