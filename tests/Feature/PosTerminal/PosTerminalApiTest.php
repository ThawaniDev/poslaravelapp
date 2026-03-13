<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\PosTerminal\Models\HeldCart;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\PosTerminal\Models\TransactionItem;
use App\Domain\Security\Enums\SessionStatus;
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
            'register_id' => 'REG-001',
            'cashier_id' => $this->user->id,
            'status' => SessionStatus::Open,
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
                'register_id' => 'REG-001',
                'opening_cash' => 100.00,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_sessions(): void
    {
        PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => SessionStatus::Open,
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
            'status' => SessionStatus::Open,
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
            'status' => SessionStatus::Open,
            'opening_cash' => 100,
            'total_cash_sales' => 500,
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
            'status' => SessionStatus::Closed,
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
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.status', 'completed');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Coffee', $response->json('data.items.0.product_name'));
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
            ->postJson("/api/v2/pos/transactions/{$txn->id}/void");

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
            ->postJson("/api/v2/pos/transactions/{$txn->id}/void");

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
}
