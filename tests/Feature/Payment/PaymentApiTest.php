<?php

namespace Tests\Feature\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\Payment\Enums\CashSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
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

    // ─── Payments ────────────────────────────────────────────

    public function test_can_create_payment(): void
    {
        $transaction = $this->createTransaction();

        $response = $this->withToken($this->token)->postJson('/api/v2/payments', [
            'transaction_id' => $transaction->id,
            'method' => 'cash',
            'amount' => 50.00,
            'cash_tendered' => 100.00,
            'change_given' => 50.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('payments', [
            'transaction_id' => $transaction->id,
            'method' => 'cash',
        ]);
    }

    public function test_can_list_payments(): void
    {
        $transaction = $this->createTransaction();
        \App\Domain\Payment\Models\Payment::create([
            'transaction_id' => $transaction->id,
            'method' => 'cash',
            'amount' => 50.00,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/payments');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.data'));
    }

    // ─── Cash Sessions ──────────────────────────────────────

    public function test_can_open_cash_session(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => 'TERM-001',
            'opening_float' => 200.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('cash_sessions', [
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'status' => 'open',
        ]);
    }

    public function test_cannot_open_duplicate_session_on_same_terminal(): void
    {
        CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'opened_by' => $this->user->id,
            'opening_float' => 100,
            'expected_cash' => 100,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => 'TERM-001',
            'opening_float' => 200.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_can_list_cash_sessions(): void
    {
        CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'opened_by' => $this->user->id,
            'opening_float' => 100,
            'expected_cash' => 100,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/cash-sessions');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.data'));
    }

    public function test_can_show_cash_session(): void
    {
        $session = CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'opened_by' => $this->user->id,
            'opening_float' => 100,
            'expected_cash' => 100,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson("/api/v2/cash-sessions/{$session->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $session->id);
    }

    public function test_can_close_cash_session(): void
    {
        $session = CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'opened_by' => $this->user->id,
            'opening_float' => 100,
            'expected_cash' => 100,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)->putJson("/api/v2/cash-sessions/{$session->id}/close", [
            'actual_cash' => 95.00,
            'close_notes' => 'Short by 5',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('cash_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_cannot_close_already_closed_session(): void
    {
        $session = CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'opened_by' => $this->user->id,
            'opening_float' => 100,
            'expected_cash' => 100,
            'actual_cash' => 100,
            'variance' => 0,
            'status' => CashSessionStatus::Closed,
            'opened_at' => now(),
            'closed_at' => now(),
        ]);

        $response = $this->withToken($this->token)->putJson("/api/v2/cash-sessions/{$session->id}/close", [
            'actual_cash' => 100.00,
        ]);

        $response->assertStatus(422);
    }

    // ─── Cash Events ────────────────────────────────────────

    public function test_can_create_cash_event(): void
    {
        $session = CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'opened_by' => $this->user->id,
            'opening_float' => 100,
            'expected_cash' => 100,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $session->id,
            'type' => 'cash_in',
            'amount' => 50.00,
            'reason' => 'Change replenishment',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $session->refresh();
        $this->assertEquals(150.0, (float) $session->expected_cash);
    }

    public function test_cash_out_decreases_expected(): void
    {
        $session = CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => 'TERM-001',
            'opened_by' => $this->user->id,
            'opening_float' => 200,
            'expected_cash' => 200,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $session->id,
            'type' => 'cash_out',
            'amount' => 30.00,
            'reason' => 'Cash pickup',
        ]);

        $response->assertStatus(201);

        $session->refresh();
        $this->assertEquals(170.0, (float) $session->expected_cash);
    }

    // ─── Expenses ───────────────────────────────────────────

    public function test_can_create_expense(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/expenses', [
            'amount' => 25.50,
            'category' => 'supplies',
            'description' => 'Cleaning supplies',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('expenses', [
            'store_id' => $this->store->id,
            'category' => 'supplies',
        ]);
    }

    public function test_can_list_expenses(): void
    {
        \App\Domain\Payment\Models\Expense::create([
            'store_id' => $this->store->id,
            'amount' => 10,
            'category' => 'food',
            'recorded_by' => $this->user->id,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/expenses');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.data'));
    }

    // ─── Gift Cards ─────────────────────────────────────────

    public function test_can_issue_gift_card(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v2/gift-cards', [
            'amount' => 100.00,
            'recipient_name' => 'John Doe',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('gift_cards', [
            'organization_id' => $this->org->id,
            'recipient_name' => 'John Doe',
            'status' => 'active',
        ]);
    }

    public function test_can_check_gift_card_balance(): void
    {
        $card = GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'GC-TEST001',
            'barcode' => 'GC-TEST001',
            'initial_amount' => 100,
            'balance' => 75,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
            'expires_at' => now()->addMonths(12),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/gift-cards/GC-TEST001/balance');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertEquals(75, (float) $response->json('data.balance'));
    }

    public function test_can_redeem_gift_card(): void
    {
        GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'GC-REDEEM01',
            'barcode' => 'GC-REDEEM01',
            'initial_amount' => 100,
            'balance' => 100,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
            'expires_at' => now()->addMonths(12),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/gift-cards/GC-REDEEM01/redeem', [
            'amount' => 40.00,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertEquals(60, (float) $response->json('data.balance'));
    }

    public function test_cannot_redeem_expired_gift_card(): void
    {
        GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'GC-EXPIRED',
            'barcode' => 'GC-EXPIRED',
            'initial_amount' => 100,
            'balance' => 100,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/gift-cards/GC-EXPIRED/redeem', [
            'amount' => 10.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_redeem_more_than_balance(): void
    {
        GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'GC-LOW',
            'barcode' => 'GC-LOW',
            'initial_amount' => 50,
            'balance' => 20,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
            'expires_at' => now()->addMonths(12),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/gift-cards/GC-LOW/redeem', [
            'amount' => 30.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_full_redeem_marks_card_as_redeemed(): void
    {
        GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'GC-FULL',
            'barcode' => 'GC-FULL',
            'initial_amount' => 50,
            'balance' => 50,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
            'expires_at' => now()->addMonths(12),
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v2/gift-cards/GC-FULL/redeem', [
            'amount' => 50.00,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'redeemed');
    }

    // ─── Auth ───────────────────────────────────────────────

    public function test_unauthenticated_request_rejected(): void
    {
        $response = $this->getJson('/api/v2/cash-sessions');
        $response->assertStatus(401);
    }

    // ─── Financial Reports ──────────────────────────────────

    public function test_daily_summary_returns_expected_structure(): void
    {
        $transaction = $this->createTransaction();

        // Create a payment for today
        $this->withToken($this->token)->postJson('/api/v2/payments', [
            'transaction_id' => $transaction->id,
            'method' => 'cash',
            'amount' => 75.00,
            'cash_tendered' => 100.00,
            'change_given' => 25.00,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/finance/daily-summary?date=' . now()->toDateString());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'date',
                'store_id',
                'revenue' => ['gross', 'refunds', 'expenses', 'net'],
                'transactions' => ['count', 'refund_count', 'average'],
                'payment_breakdown',
                'cash_sessions',
                'expenses',
                'hourly_activity',
            ],
        ]);
    }

    public function test_daily_summary_defaults_to_today(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/finance/daily-summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.date', now()->toDateString());
    }

    public function test_daily_summary_returns_correct_payment_totals(): void
    {
        $t1 = $this->createTransaction();
        $t2 = $this->createTransaction();

        $this->withToken($this->token)->postJson('/api/v2/payments', [
            'transaction_id' => $t1->id,
            'method' => 'cash',
            'amount' => 100.00,
        ]);

        $this->withToken($this->token)->postJson('/api/v2/payments', [
            'transaction_id' => $t2->id,
            'method' => 'card_mada',
            'amount' => 200.00,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/finance/daily-summary?date=' . now()->toDateString());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(300.00, $data['revenue']['gross']);
        $this->assertEquals(2, $data['transactions']['count']);
        $this->assertEquals(150.00, $data['transactions']['average']);

        // Check payment breakdown has both methods
        $methods = collect($data['payment_breakdown'])->pluck('method')->toArray();
        $this->assertContains('cash', $methods);
        $this->assertContains('card_mada', $methods);
    }

    public function test_daily_summary_includes_expenses(): void
    {
        // Open a session first
        $this->withToken($this->token)->postJson('/api/v2/cash-sessions', [
            'opening_float' => 500.00,
        ]);

        // Create an expense
        $this->withToken($this->token)->postJson('/api/v2/expenses', [
            'amount' => 45.00,
            'category' => 'supplies',
            'description' => 'Cleaning supplies',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/finance/daily-summary?date=' . now()->toDateString());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(45.00, $data['expenses']['total']);
    }

    public function test_daily_summary_includes_hourly_activity(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/finance/daily-summary?date=' . now()->toDateString());

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should have 24 hours of activity data
        $this->assertCount(24, $data['hourly_activity']);
    }

    public function test_reconciliation_returns_expected_structure(): void
    {
        // Open and close a session
        $openRes = $this->withToken($this->token)->postJson('/api/v2/cash-sessions', [
            'opening_float' => 500.00,
        ]);
        $sessionId = $openRes->json('data.id');

        $this->withToken($this->token)->putJson("/api/v2/cash-sessions/{$sessionId}/close", [
            'actual_cash' => 520.00,
            'close_notes' => 'End of shift',
        ]);

        $today = now()->toDateString();
        $response = $this->withToken($this->token)->getJson("/api/v2/finance/reconciliation?start_date={$today}&end_date={$today}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'summary' => [
                    'session_count',
                    'total_expected',
                    'total_actual',
                    'total_variance',
                    'within_tolerance',
                ],
                'sessions',
            ],
        ]);
    }

    public function test_reconciliation_defaults_to_today(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v2/finance/reconciliation');

        $response->assertStatus(200);
    }

    public function test_reconciliation_calculates_variance_correctly(): void
    {
        // Open and close two sessions
        $open1 = $this->withToken($this->token)->postJson('/api/v2/cash-sessions', [
            'opening_float' => 500.00,
        ]);
        $sid1 = $open1->json('data.id');

        $this->withToken($this->token)->putJson("/api/v2/cash-sessions/{$sid1}/close", [
            'actual_cash' => 510.00,
        ]);

        $open2 = $this->withToken($this->token)->postJson('/api/v2/cash-sessions', [
            'opening_float' => 300.00,
        ]);
        $sid2 = $open2->json('data.id');

        $this->withToken($this->token)->putJson("/api/v2/cash-sessions/{$sid2}/close", [
            'actual_cash' => 295.00,
        ]);

        $today = now()->toDateString();
        $response = $this->withToken($this->token)->getJson("/api/v2/finance/reconciliation?start_date={$today}&end_date={$today}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['session_count']);
    }

    public function test_daily_summary_unauthenticated(): void
    {
        $response = $this->getJson('/api/v2/finance/daily-summary');
        $response->assertStatus(401);
    }

    public function test_reconciliation_unauthenticated(): void
    {
        $response = $this->getJson('/api/v2/finance/reconciliation');
        $response->assertStatus(401);
    }

    // ─── Helpers ────────────────────────────────────────────

    private function createTransaction(): Transaction
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => 'REG-001',
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100.00,
            'transaction_count' => 0,
            'opened_at' => now(),
        ]);

        return Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-TEST-0001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50.00,
        ]);
    }
}
