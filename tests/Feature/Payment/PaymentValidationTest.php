<?php

namespace Tests\Feature\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment Validation Tests
 *
 * Ensures every endpoint returns 422 Unprocessable Entity when:
 *   - Required fields are missing
 *   - Fields have invalid types/formats
 *   - Enum values are out of range
 *   - Business constraint violations that the request layer can catch
 *
 * Supplements PaymentApiTest (which tests happy paths) and
 * PaymentPermissionTest (which tests access control).
 */
class PaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private Organization $org;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Validation Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Validation Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'owner@validation.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/v2/payments
    // ═══════════════════════════════════════════════════════════

    public function test_create_payment_fails_without_transaction_id(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'method' => 'cash',
                'amount' => 50.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_create_payment_fails_with_invalid_transaction_uuid(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => 'not-a-uuid',
                'method' => 'cash',
                'amount' => 50.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_create_payment_fails_with_nonexistent_transaction(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => '00000000-0000-0000-0000-000000000000',
                'method' => 'cash',
                'amount' => 50.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_create_payment_fails_without_method(): void
    {
        $tx = $this->createTransaction();
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'amount' => 50.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);
    }

    public function test_create_payment_fails_with_invalid_method_enum(): void
    {
        $tx = $this->createTransaction();
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'method' => 'bitcoin',
                'amount' => 50.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);
    }

    public function test_create_payment_fails_without_amount(): void
    {
        $tx = $this->createTransaction();
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'method' => 'cash',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_fails_with_zero_amount(): void
    {
        $tx = $this->createTransaction();
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'method' => 'cash',
                'amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_fails_with_negative_amount(): void
    {
        $tx = $this->createTransaction();
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'method' => 'cash',
                'amount' => -10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_fails_with_card_last_four_wrong_length(): void
    {
        $tx = $this->createTransaction();
        $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'method' => 'card',
                'amount' => 100,
                'card_last_four' => '12345',  // 5 chars instead of 4
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['card_last_four']);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/v2/cash-sessions (open)
    // ═══════════════════════════════════════════════════════════

    public function test_open_session_fails_without_opening_float(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_float']);
    }

    public function test_open_session_fails_with_negative_opening_float(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', ['opening_float' => -50])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_float']);
    }

    public function test_open_session_fails_with_non_numeric_opening_float(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', ['opening_float' => 'not-a-number'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_float']);
    }

    // ═══════════════════════════════════════════════════════════
    // PUT /api/v2/cash-sessions/{id}/close
    // ═══════════════════════════════════════════════════════════

    public function test_close_session_fails_without_actual_cash(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$session->id}/close", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_cash']);
    }

    public function test_close_session_fails_with_negative_actual_cash(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$session->id}/close", ['actual_cash' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_cash']);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/v2/cash-events
    // ═══════════════════════════════════════════════════════════

    public function test_create_cash_event_fails_without_cash_session_id(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'type' => 'cash_in',
                'amount' => 50,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_session_id']);
    }

    public function test_create_cash_event_fails_with_nonexistent_session(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => '00000000-0000-0000-0000-000000000000',
                'type' => 'cash_in',
                'amount' => 50,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_session_id']);
    }

    public function test_create_cash_event_fails_without_type(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'amount' => 50,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_cash_event_fails_with_invalid_type(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'wire_transfer',
                'amount' => 50,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_cash_event_fails_without_amount(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'cash_in',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_cash_event_fails_with_zero_amount(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'cash_in',
                'amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/v2/expenses
    // ═══════════════════════════════════════════════════════════

    public function test_create_expense_fails_without_amount(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'category' => 'supplies',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_expense_fails_with_zero_amount(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'amount' => 0,
                'category' => 'supplies',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_expense_fails_without_category(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'amount' => 50,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_create_expense_fails_with_invalid_category(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'amount' => 50,
                'category' => 'yacht_fuel',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_create_expense_fails_with_invalid_date_format(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'amount' => 50,
                'category' => 'supplies',
                'expense_date' => '15/06/2025',  // d/m/Y not accepted
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expense_date']);
    }

    // ═══════════════════════════════════════════════════════════
    // PUT /api/v2/expenses/{id}
    // ═══════════════════════════════════════════════════════════

    public function test_update_expense_fails_with_invalid_category(): void
    {
        $expense = $this->makeExpense();
        $this->withToken($this->token)
            ->putJson("/api/v2/expenses/{$expense->id}", [
                'category' => 'secret_expenses',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_update_expense_fails_with_wrong_date_format(): void
    {
        $expense = $this->makeExpense();
        $this->withToken($this->token)
            ->putJson("/api/v2/expenses/{$expense->id}", [
                'expense_date' => '06-15-2025',  // m-d-Y not accepted
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expense_date']);
    }

    public function test_update_expense_fails_with_zero_amount(): void
    {
        $expense = $this->makeExpense();
        $this->withToken($this->token)
            ->putJson("/api/v2/expenses/{$expense->id}", [
                'amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/v2/gift-cards (issue)
    // ═══════════════════════════════════════════════════════════

    public function test_issue_gift_card_fails_without_amount(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_issue_gift_card_fails_with_zero_amount(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', ['amount' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_issue_gift_card_fails_with_negative_amount(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', ['amount' => -50])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_issue_gift_card_fails_with_duplicate_code(): void
    {
        GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => 'EXISTING-CODE-001',
            'barcode' => 'EXISTING-CODE-001',
            'initial_amount' => 100,
            'balance' => 100,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 100,
                'code' => 'EXISTING-CODE-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_issue_gift_card_fails_with_past_expires_at(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 100,
                'expires_at' => now()->subDays(1)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/v2/gift-cards/{code}/redeem
    // ═══════════════════════════════════════════════════════════

    public function test_redeem_gift_card_fails_without_amount(): void
    {
        $this->makeGiftCard('GC-VALID-001', 100);
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/GC-VALID-001/redeem', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_redeem_gift_card_fails_with_zero_amount(): void
    {
        $this->makeGiftCard('GC-VALID-002', 100);
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/GC-VALID-002/redeem', ['amount' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_redeem_gift_card_fails_with_non_numeric_amount(): void
    {
        $this->makeGiftCard('GC-VALID-003', 100);
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/GC-VALID-003/redeem', ['amount' => 'hundred'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /api/v2/payments/{id}/refund
    // ═══════════════════════════════════════════════════════════

    public function test_create_refund_fails_without_amount(): void
    {
        $payment = $this->createPayment(50.00);
        $this->withToken($this->token)
            ->postJson("/api/v2/payments/{$payment->id}/refund", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_refund_fails_with_zero_amount(): void
    {
        $payment = $this->createPayment(50.00);
        $this->withToken($this->token)
            ->postJson("/api/v2/payments/{$payment->id}/refund", ['amount' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_refund_fails_with_invalid_method_enum(): void
    {
        $payment = $this->createPayment(50.00);
        $this->withToken($this->token)
            ->postJson("/api/v2/payments/{$payment->id}/refund", [
                'amount' => 10,
                'method' => 'crypto',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);
    }

    // ═══════════════════════════════════════════════════════════
    // GET /api/v2/finance/daily-summary
    // ═══════════════════════════════════════════════════════════

    public function test_daily_summary_fails_with_invalid_date_format(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/finance/daily-summary?date=25-06-2025')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_daily_summary_fails_with_garbage_date(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/finance/daily-summary?date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    // ═══════════════════════════════════════════════════════════
    // GET /api/v2/finance/reconciliation
    // ═══════════════════════════════════════════════════════════

    public function test_reconciliation_fails_with_invalid_start_date(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/finance/reconciliation?start_date=2025/06/01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_reconciliation_fails_with_invalid_end_date(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/finance/reconciliation?end_date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function createTransaction(): Transaction
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
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
            'transaction_number' => 'TXN-VAL-' . uniqid(),
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50.00,
        ]);
    }

    private function createPayment(float $amount): \App\Domain\Payment\Models\Payment
    {
        $tx = $this->createTransaction();
        return \App\Domain\Payment\Models\Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => $amount,
            'status' => 'completed',

        ]);
    }

    private function makeOpenSession(): CashSession
    {
        return CashSession::create([
            'store_id' => $this->store->id,
            'opened_by' => $this->user->id,
            'opening_float' => 200,
            'expected_cash' => 200,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    private function makeExpense(): \App\Domain\Payment\Models\Expense
    {
        return \App\Domain\Payment\Models\Expense::create([
            'store_id' => $this->store->id,
            'amount' => 30,
            'category' => 'supplies',
            'recorded_by' => $this->user->id,
            'expense_date' => now()->toDateString(),
        ]);
    }

    private function makeGiftCard(string $code, float $balance = 100): GiftCard
    {
        return GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => $code,
            'barcode' => $code,
            'initial_amount' => $balance,
            'balance' => $balance,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
        ]);
    }
}
