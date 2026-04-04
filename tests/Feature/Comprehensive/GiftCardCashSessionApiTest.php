<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\GiftCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GiftCardCashSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        auth()->forgetGuards();

        $this->org = Organization::create([
            'name' => 'Payment Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Payment Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'gc-cashier@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════
    // ─── GIFT CARDS ──────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_issue_gift_card(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 100.00,
                'recipient_name' => 'Mohammed Ali',
                'expires_at' => now()->addYear()->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('gift_cards', [
            'organization_id' => $this->org->id,
            'initial_amount' => 100.00,
            'status' => 'active',
        ]);
    }

    public function test_issue_gift_card_requires_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'recipient_name' => 'Test',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_issue_gift_card_rejects_zero_amount(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 0,
            ]);

        $response->assertUnprocessable();
    }

    public function test_issue_gift_card_with_custom_code(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 50.00,
                'code' => 'GIFT-2024-VIP',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('gift_cards', [
            'code' => 'GIFT-2024-VIP',
        ]);
    }

    public function test_issue_gift_card_rejects_duplicate_code(): void
    {
        // Issue first
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 50.00,
                'code' => 'UNIQUE-CODE',
            ]);

        // Duplicate
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 50.00,
                'code' => 'UNIQUE-CODE',
            ]);

        $response->assertUnprocessable();
    }

    public function test_can_check_gift_card_balance(): void
    {
        $card = GiftCard::forceCreate([
            'id' => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'code' => 'BAL-CHECK-001',
            'initial_amount' => 100.00,
            'balance' => 75.00,
            'status' => 'active',
            'issued_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/gift-cards/BAL-CHECK-001/balance');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_check_balance_returns_404_for_unknown_code(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/gift-cards/NONEXISTENT/balance');

        $response->assertNotFound();
    }

    public function test_can_redeem_gift_card(): void
    {
        GiftCard::forceCreate([
            'id' => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'code' => 'REDEEM-001',
            'initial_amount' => 100.00,
            'balance' => 100.00,
            'status' => 'active',
            'issued_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/REDEEM-001/redeem', [
                'amount' => 30.00,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('gift_cards', [
            'code' => 'REDEEM-001',
            'balance' => 70.00,
        ]);
    }

    public function test_redeem_rejects_amount_exceeding_balance(): void
    {
        GiftCard::forceCreate([
            'id' => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'code' => 'LOW-BAL-001',
            'initial_amount' => 50.00,
            'balance' => 10.00,
            'status' => 'active',
            'issued_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/LOW-BAL-001/redeem', [
                'amount' => 25.00,
            ]);

        $response->assertStatus(422);
    }

    public function test_redeem_requires_amount(): void
    {
        GiftCard::forceCreate([
            'id' => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'code' => 'REDEEM-REQ',
            'initial_amount' => 100.00,
            'balance' => 100.00,
            'status' => 'active',
            'issued_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/REDEEM-REQ/redeem', []);

        $response->assertUnprocessable();
    }

    public function test_redeem_returns_404_for_unknown_card(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/NONEXISTENT/redeem', [
                'amount' => 10.00,
            ]);

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════
    // ─── CASH SESSIONS ───────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_open_cash_session(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', [
                'opening_float' => 500.00,
                'terminal_id' => 'REG-01',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('cash_sessions', [
            'store_id' => $this->store->id,
            'opening_float' => 500.00,
            'status' => 'open',
        ]);
    }

    public function test_open_session_requires_opening_float(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['opening_float']);
    }

    public function test_can_list_cash_sessions(): void
    {
        $this->createCashSession();

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/cash-sessions');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_can_show_cash_session(): void
    {
        $sessionId = $this->createCashSession();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/cash-sessions/{$sessionId}");

        $response->assertOk();
    }

    public function test_can_close_cash_session(): void
    {
        $sessionId = $this->createCashSession();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$sessionId}/close", [
                'actual_cash' => 520.00,
                'close_notes' => 'End of shift',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('cash_sessions', [
            'id' => $sessionId,
            'status' => 'closed',
        ]);
    }

    public function test_close_session_requires_actual_cash(): void
    {
        $sessionId = $this->createCashSession();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$sessionId}/close", []);

        $response->assertUnprocessable();
    }

    public function test_close_returns_404_for_missing_session(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$fakeId}/close", [
                'actual_cash' => 100.00,
            ]);

        $response->assertNotFound();
    }

    // ─── Cash Events ─────────────────────────────────────────

    public function test_can_create_cash_in_event(): void
    {
        $sessionId = $this->createCashSession();

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_in',
                'amount' => 200.00,
                'reason' => 'Change from bank',
                'notes' => 'Additional float',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('cash_events', [
            'cash_session_id' => $sessionId,
            'type' => 'cash_in',
            'amount' => 200.00,
        ]);
    }

    public function test_can_create_cash_out_event(): void
    {
        $sessionId = $this->createCashSession();

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_out',
                'amount' => 50.00,
                'reason' => 'Petty cash for supplies',
            ]);

        $response->assertCreated();
    }

    public function test_cash_event_requires_session_and_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'amount' => 100.00,
            ]);

        $response->assertUnprocessable();
    }

    // ─── Expenses ────────────────────────────────────────────

    public function test_can_create_expense(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'amount' => 45.00,
                'category' => 'supplies',
                'description' => 'Receipt paper rolls',
                'expense_date' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('expenses', [
            'store_id' => $this->store->id,
            'category' => 'supplies',
        ]);
    }

    public function test_expense_requires_amount_and_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/expenses', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount', 'category']);
    }

    public function test_can_create_expense_with_all_categories(): void
    {
        $categories = ['supplies', 'food', 'transport', 'maintenance', 'utility', 'other'];

        foreach ($categories as $cat) {
            $response = $this->withToken($this->token)
                ->postJson('/api/v2/expenses', [
                    'amount' => 10.00,
                    'category' => $cat,
                ]);

            $response->assertCreated();
        }
    }

    public function test_can_list_expenses(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'amount' => 30.00,
                'category' => 'other',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/expenses');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_expense_can_link_to_cash_session(): void
    {
        $sessionId = $this->createCashSession();

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'cash_session_id' => $sessionId,
                'amount' => 75.00,
                'category' => 'maintenance',
                'description' => 'Register repair',
            ]);

        $response->assertCreated();
    }

    // ─── Full Gift Card Lifecycle ────────────────────────────

    public function test_gift_card_full_lifecycle(): void
    {
        // Issue
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 200.00,
                'recipient_name' => 'VIP Customer',
            ]);
        $response->assertCreated();
        $code = $response->json('data.code');

        // Check balance
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/gift-cards/{$code}/balance");
        $response->assertOk();

        // Redeem partial
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/gift-cards/{$code}/redeem", [
                'amount' => 80.00,
            ]);
        $response->assertOk();

        // Check updated balance
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/gift-cards/{$code}/balance");
        $response->assertOk();
    }

    // ─── Full Cash Session Lifecycle ─────────────────────────

    public function test_cash_session_full_lifecycle(): void
    {
        // Open
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', [
                'opening_float' => 300.00,
            ]);
        $response->assertCreated();
        $sessionId = $response->json('data.id');

        // Cash in
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_in',
                'amount' => 100.00,
                'reason' => 'Extra float',
            ]);
        $response->assertCreated();

        // Record expense
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'cash_session_id' => $sessionId,
                'amount' => 25.00,
                'category' => 'supplies',
            ]);
        $response->assertCreated();

        // Cash out
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_out',
                'amount' => 50.00,
                'reason' => 'Bank deposit',
            ]);
        $response->assertCreated();

        // Close
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$sessionId}/close", [
                'actual_cash' => 325.00,
                'close_notes' => 'End of day',
            ]);
        $response->assertOk();
    }

    // ─── Auth ────────────────────────────────────────────────

    public function test_payment_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/cash-sessions');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/v2/gift-cards', []);
        $response->assertUnauthorized();

        $response = $this->getJson('/api/v2/expenses');
        $response->assertUnauthorized();
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createCashSession(): string
    {
        $id = Str::uuid()->toString();
        DB::table('cash_sessions')->insert([
            'id' => $id,
            'store_id' => $this->store->id,
            'opened_by' => $this->user->id,
            'opening_float' => 500.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        return $id;
    }
}
