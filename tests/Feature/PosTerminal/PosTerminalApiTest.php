<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\PosTerminal\Models\HeldCart;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\PosTerminal\Models\TransactionItem;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTerminalApiTest extends TestCase
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
            'name' => 'Test Org',
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
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Sessions ────────────────────────────────────────────

    public function test_can_open_session(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/sessions', [
                'opening_cash' => 100.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'open');

        $this->assertEquals(100, $response->json('data.opening_cash'));
    }

    public function test_cannot_open_duplicate_session_on_same_register(): void
    {
        PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => '00000000-0000-0000-0000-000000000003',
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 50,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/sessions', [
                'register_id' => '00000000-0000-0000-0000-000000000003',
                'opening_cash' => 100.00,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_sessions(): void
    {
        PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 50,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/sessions');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_show_session(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 75,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/pos/sessions/{$session->id}");

        $response->assertOk();
        $this->assertEquals(75, $response->json('data.opening_cash'));
    }

    public function test_can_close_session(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100,
            'total_cash_sales' => 450,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 50,
            'total_voids' => 0,
            'transaction_count' => 5,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/sessions/{$session->id}/close", [
                'closing_cash' => 545.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');
        // Expected = 100 + 500 - 50 = 550; closing = 545; diff = -5
        $this->assertEquals(-5.0, $response->json('data.cash_difference'));
    }

    public function test_cannot_close_already_closed_session(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Closed,
            'opening_cash' => 100,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'opened_at' => now(),
            'closed_at' => now(),
            'z_report_printed' => false,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/sessions/{$session->id}/close", [
                'closing_cash' => 100,
            ]);

        $response->assertStatus(422);
    }

    // ─── Transactions ────────────────────────────────────────

    public function test_can_create_transaction(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type' => 'sale',
                'subtotal' => 100.00,
                'tax_amount' => 15.00,
                'total_amount' => 115.00,
                'items' => [
                    [
                        'product_name' => 'Coffee',
                        'quantity' => 2,
                        'unit_price' => 50.00,
                        'line_total' => 100.00,
                    ],
                ],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 115.00,
                        'cash_tendered' => 120.00,
                        'change_given' => 5.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.status', 'completed');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Coffee', $response->json('data.items.0.product_name'));
        $this->assertCount(1, $response->json('data.payments'));
        $this->assertEquals('cash', $response->json('data.payments.0.method'));
        $this->assertEquals(115.00, $response->json('data.payments.0.amount'));
        $this->assertEquals(120.00, $response->json('data.payments.0.cash_tendered'));
        $this->assertEquals(5.00, $response->json('data.payments.0.change_given'));
    }

    public function test_create_transaction_requires_payments(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type' => 'sale',
                'subtotal' => 10,
                'total_amount' => 10,
                'items' => [
                    ['product_name' => 'Item', 'quantity' => 1, 'unit_price' => 10, 'line_total' => 10],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_create_transaction_requires_items(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type' => 'sale',
                'subtotal' => 10,
                'total_amount' => 10,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_transactions(): void
    {
        Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'tip_amount' => 0,
            'total_amount' => 115,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_show_transaction(): void
    {
        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-002',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 7.5,
            'tip_amount' => 0,
            'total_amount' => 57.5,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/pos/transactions/{$txn->id}");

        $response->assertOk()
            ->assertJsonPath('data.transaction_number', 'TXN-002');
    }

    public function test_can_void_transaction(): void
    {
        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-003',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 30,
            'discount_amount' => 0,
            'tax_amount' => 4.5,
            'tip_amount' => 0,
            'total_amount' => 34.5,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/pos/transactions/{$txn->id}/void", ['reason' => 'wrong customer charged']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'voided');
    }

    public function test_cannot_void_already_voided(): void
    {
        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-004',
            'type' => 'sale',
            'status' => 'voided',
            'subtotal' => 30,
            'discount_amount' => 0,
            'tax_amount' => 4.5,
            'tip_amount' => 0,
            'total_amount' => 34.5,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/pos/transactions/{$txn->id}/void", ['reason' => 'duplicate void test']);

        $response->assertStatus(422);
    }

    // ─── Held Carts ──────────────────────────────────────────

    public function test_can_hold_cart(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/held-carts', [
                'cart_data' => [
                    'items' => [['name' => 'Coffee', 'qty' => 1, 'price' => 5.00]],
                ],
                'label' => 'Table 3',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.label', 'Table 3');
    }

    public function test_can_list_held_carts(): void
    {
        HeldCart::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'cart_data' => ['items' => []],
            'label' => 'Cart A',
            'held_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/held-carts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_recall_held_cart(): void
    {
        $cart = HeldCart::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'cart_data' => ['items' => [['name' => 'Tea']]],
            'held_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/held-carts/{$cart->id}/recall");

        $response->assertOk();
        $this->assertNotNull($response->json('data.recalled_at'));
    }

    public function test_cannot_recall_already_recalled(): void
    {
        $cart = HeldCart::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'cart_data' => ['items' => []],
            'held_at' => now(),
            'recalled_at' => now(),
            'recalled_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/held-carts/{$cart->id}/recall");

        $response->assertStatus(422);
    }

    public function test_can_delete_held_cart(): void
    {
        $cart = HeldCart::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'cart_data' => ['items' => []],
            'held_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/pos/held-carts/{$cart->id}");

        $response->assertOk();
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v2/pos/sessions')->assertStatus(401);
        $this->getJson('/api/v2/pos/transactions')->assertStatus(401);
        $this->getJson('/api/v2/pos/held-carts')->assertStatus(401);
    }

    // ─── Return Transactions ──────────────────────────────────

    public function test_can_create_return_transaction(): void
    {
        $sale = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-RET-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'tip_amount' => 0,
            'total_amount' => 115,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions/return', [
                'return_transaction_id' => $sale->id,
                'subtotal' => 50.00,
                'tax_amount' => 7.50,
                'total_amount' => 57.50,
                'items' => [
                    [
                        'product_name' => 'Coffee',
                        'quantity' => 1,
                        'unit_price' => 50.00,
                        'line_total' => 50.00,
                        'is_return_item' => true,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 57.50],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'return')
            ->assertJsonPath('data.return_transaction_id', $sale->id);
    }

    public function test_cannot_return_voided_transaction(): void
    {
        $sale = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-RET-002',
            'type' => 'sale',
            'status' => 'voided',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'tip_amount' => 0,
            'total_amount' => 115,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions/return', [
                'return_transaction_id' => $sale->id,
                'subtotal' => 50,
                'total_amount' => 57.50,
                'items' => [
                    ['product_name' => 'Coffee', 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50],
                ],
                'payments' => [['method' => 'cash', 'amount' => 57.50]],
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_return_non_sale_transaction(): void
    {
        $returnTxn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-RET-003',
            'type' => 'return',
            'status' => 'completed',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 7.5,
            'tip_amount' => 0,
            'total_amount' => 57.5,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions/return', [
                'return_transaction_id' => $returnTxn->id,
                'subtotal' => 50,
                'total_amount' => 57.50,
                'items' => [
                    ['product_name' => 'Coffee', 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50],
                ],
                'payments' => [['method' => 'cash', 'amount' => 57.50]],
            ]);

        $response->assertStatus(422);
    }

    // ─── Transaction Lookup by Number ─────────────────────────

    public function test_can_find_transaction_by_number(): void
    {
        Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-20250101-0001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'tip_amount' => 0,
            'total_amount' => 115,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/by-number/TXN-20250101-0001');

        $response->assertOk()
            ->assertJsonPath('data.transaction_number', 'TXN-20250101-0001');
    }

    public function test_transaction_by_number_returns_404_when_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/by-number/DOESNOTEXIST');

        $response->assertStatus(404);
    }

    // ─── Split Payment ────────────────────────────────────────

    public function test_can_create_transaction_with_split_payment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type' => 'sale',
                'subtotal' => 200.00,
                'tax_amount' => 30.00,
                'total_amount' => 230.00,
                'items' => [
                    [
                        'product_name' => 'Laptop Stand',
                        'quantity' => 1,
                        'unit_price' => 200.00,
                        'line_total' => 200.00,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 100.00, 'cash_tendered' => 100.00, 'change_given' => 0],
                    ['method' => 'card_mada', 'amount' => 130.00, 'card_last_four' => '4321', 'card_brand' => 'mada'],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('data.payments'));
        $this->assertEquals('cash', $response->json('data.payments.0.method'));
        $this->assertEquals('card_mada', $response->json('data.payments.1.method'));
        $this->assertEquals('4321', $response->json('data.payments.1.card_last_four'));
    }

    public function test_can_create_transaction_with_card_payment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type' => 'sale',
                'subtotal' => 50.00,
                'tax_amount' => 7.50,
                'total_amount' => 57.50,
                'items' => [
                    ['product_name' => 'Headphones', 'quantity' => 1, 'unit_price' => 50.00, 'line_total' => 50.00],
                ],
                'payments' => [
                    [
                        'method' => 'card_visa',
                        'amount' => 57.50,
                        'card_brand' => 'visa',
                        'card_last_four' => '1234',
                        'card_auth_code' => 'AUTH123',
                        'card_reference' => 'REF-456',
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertEquals('card_visa', $response->json('data.payments.0.method'));
        $this->assertEquals('1234', $response->json('data.payments.0.card_last_four'));
        $this->assertEquals('AUTH123', $response->json('data.payments.0.card_auth_code'));
    }

    // ─── Session Counter Updates with Payments ────────────────

    public function test_session_counters_update_with_payment_type(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type' => 'sale',
                'pos_session_id' => $session->id,
                'subtotal' => 100,
                'tax_amount' => 15,
                'total_amount' => 115,
                'items' => [
                    ['product_name' => 'Item A', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 50],
                    ['method' => 'card_mada', 'amount' => 65],
                ],
            ]);

        $session->refresh();
        $this->assertEquals(1, $session->transaction_count);
        $this->assertEquals(50.0, (float) $session->total_cash_sales);
        $this->assertEquals(65.0, (float) $session->total_card_sales);
    }

    public function test_return_updates_session_refund_counter(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100,
            'total_cash_sales' => 500,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 5,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);

        $sale = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-SESS-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'tip_amount' => 0,
            'total_amount' => 115,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions/return', [
                'return_transaction_id' => $sale->id,
                'pos_session_id' => $session->id,
                'subtotal' => 50,
                'total_amount' => 57.50,
                'items' => [
                    ['product_name' => 'Coffee', 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50],
                ],
                'payments' => [['method' => 'cash', 'amount' => 57.50]],
            ]);

        $session->refresh();
        $this->assertEquals(6, $session->transaction_count);
        $this->assertEquals(57.5, (float) $session->total_refunds);
    }

    // ─── Products ─────────────────────────────────────────────

    public function test_can_list_products(): void
    {
        \App\Domain\Catalog\Models\Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Product',
            'name_ar' => 'منتج تجريبي',
            'sku' => 'TST-001',
            'barcode' => '1234567890',
            'sell_price' => 25.00,
            'cost_price' => 15.00,
            'tax_rate' => 15.00,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/products');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Test Product', $response->json('data.data.0.name'));
    }

    public function test_can_search_products_by_name(): void
    {
        \App\Domain\Catalog\Models\Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Premium Coffee',
            'sell_price' => 15,
            'is_active' => true,
        ]);

        \App\Domain\Catalog\Models\Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Green Tea',
            'sell_price' => 10,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/products?search=Coffee');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Premium Coffee', $response->json('data.data.0.name'));
    }

    public function test_can_search_products_by_barcode(): void
    {
        \App\Domain\Catalog\Models\Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Barcode Item',
            'barcode' => '9876543210',
            'sell_price' => 30,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/products?barcode=9876543210');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_products_excludes_inactive(): void
    {
        \App\Domain\Catalog\Models\Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Active Product',
            'sell_price' => 10,
            'is_active' => true,
        ]);

        \App\Domain\Catalog\Models\Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive Product',
            'sell_price' => 10,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/products');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Active Product', $response->json('data.data.0.name'));
    }

    // ─── Customers ────────────────────────────────────────────

    public function test_can_list_customers(): void
    {
        \App\Domain\Customer\Models\Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'John Doe',
            'phone' => '+96812345678',
            'email' => 'john@example.com',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/customers');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('John Doe', $response->json('data.data.0.name'));
    }

    public function test_can_search_customers_by_phone(): void
    {
        \App\Domain\Customer\Models\Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Ahmed',
            'phone' => '+96899887766',
        ]);

        \App\Domain\Customer\Models\Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Sara',
            'phone' => '+96811223344',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/customers?search=99887766');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Ahmed', $response->json('data.data.0.name'));
    }

    public function test_can_search_customers_by_name(): void
    {
        \App\Domain\Customer\Models\Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Mohammed Ali',
            'phone' => '+96800000001',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/customers?search=Mohammed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }
}
