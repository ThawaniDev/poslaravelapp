<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\Payment\Models\Payment;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Payment Workflow End-to-End Tests
 *
 * These tests simulate complete, multi-step user journeys that span multiple
 * API calls. They validate that the system state is consistent throughout and
 * that the final state matches business expectations.
 *
 * Scenarios:
 *   1. Full cash session lifecycle (open → events → expenses → close → verify)
 *   2. Full gift card lifecycle (issue → balance → partial → full redeem → deactivate blocks)
 *   3. Partial and full refund workflow (partial_refund → refunded status transitions)
 *   4. Daily summary accuracy (payments, refunds, expenses reflected correctly)
 *   5. Reconciliation variance (open session → events → close → reconciliation report)
 */
class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // Run migrate:fresh once per class to avoid PostgreSQL deadlocks
    private static bool $classMigrated = false;

    private User $owner;
    private Store $store;
    private Organization $org;
    private string $token;

    protected function refreshTestDatabase(): void
    {
        if (!static::$classMigrated) {
            $this->migrateDatabases();
            $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
            static::$classMigrated = true;
        }
        $this->beginDatabaseTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Workflow Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Workflow Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════
    // Scenario 1: Full Cash Session Lifecycle
    // ═══════════════════════════════════════════════════════════

    /**
     * Journey:
     *   1. Open session with £200 float
     *   2. Add cash_in £50 (tips collected)
     *   3. Add cash_out £30 (petty cash)
     *   4. Add expense £15 (cleaning supplies)
     *   5. Close session with actual_cash £205
     *   6. Verify: expected=220, actual=205, variance=-15
     *   7. Verify daily summary shows the session
     */
    public function test_full_cash_session_lifecycle(): void
    {
        // 1. Open session
        $openResponse = $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', ['opening_float' => 200.00])
            ->assertCreated();

        $sessionId = $openResponse->json('data.id');

        // 2. Cash in
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_in',
                'amount' => 50.00,
                'reason' => 'Tips collected',
            ])
            ->assertCreated();

        // 3. Cash out
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_out',
                'amount' => 30.00,
                'reason' => 'Petty cash for supplies',
            ])
            ->assertCreated();

        // 4. Add expense
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'cash_session_id' => $sessionId,
                'amount' => 15.00,
                'category' => 'supplies',
                'description' => 'Cleaning supplies',
            ])
            ->assertCreated();

        // Verify expected_cash before close: 200 + 50 - 30 = 220
        $session = CashSession::find($sessionId);
        $this->assertEquals(220.00, (float) $session->expected_cash);

        // 5. Close session
        $closeResponse = $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$sessionId}/close", [
                'actual_cash' => 205.00,
                'close_notes' => 'End of shift',
            ])
            ->assertOk();

        // 6. Verify variance
        $this->assertEquals(205.00, $closeResponse->json('data.actual_cash'));
        $this->assertEquals(-15.00, round($closeResponse->json('data.variance'), 2));
        $this->assertEquals('closed', $closeResponse->json('data.status'));

        // 7. Verify session shows in list with correct status
        $this->withToken($this->token)
            ->getJson('/api/v2/cash-sessions')
            ->assertOk()
            ->assertJsonFragment(['id' => $sessionId])
            ->assertJsonFragment(['status' => 'closed']);
    }

    /**
     * Verifies cannot add events after session is closed.
     */
    public function test_cash_session_rejects_events_after_close(): void
    {
        $openResp = $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', ['opening_float' => 100])
            ->assertCreated();

        $sessionId = $openResp->json('data.id');

        $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$sessionId}/close", ['actual_cash' => 100])
            ->assertOk();

        // Try to add event to closed session
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_in',
                'amount' => 50,
            ])
            ->assertStatus(422); // Request validation passes but business rule fails → 422 or 400
    }

    // ═══════════════════════════════════════════════════════════
    // Scenario 2: Full Gift Card Lifecycle
    // ═══════════════════════════════════════════════════════════

    /**
     * Journey:
     *   1. Issue gift card for £100
     *   2. Check balance → 100.00, status=active
     *   3. Partial redeem £40 → balance=60
     *   4. Partial redeem £60 → balance=0, status=redeemed
     *   5. Attempt deactivate → fails (already redeemed)
     *   6. Attempt another redeem → fails (already redeemed)
     */
    public function test_full_gift_card_lifecycle(): void
    {
        // 1. Issue gift card
        $issueResp = $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', [
                'amount' => 100.00,
                'code' => 'GC-LIFECYCLE-001',
            ])
            ->assertCreated();

        $code = $issueResp->json('data.code');
        $this->assertEquals('GC-LIFECYCLE-001', $code);

        // 2. Check balance
        $balanceResp = $this->withToken($this->token)
            ->getJson("/api/v2/gift-cards/{$code}/balance")
            ->assertOk();

        $this->assertEquals(100.00, $balanceResp->json('data.balance'));
        $this->assertEquals('active', $balanceResp->json('data.status'));

        // 3. First partial redeem £40
        $redeemResp1 = $this->withToken($this->token)
            ->postJson("/api/v2/gift-cards/{$code}/redeem", ['amount' => 40.00])
            ->assertOk();

        $this->assertEquals(60.00, $redeemResp1->json('data.balance'));
        $this->assertEquals('active', $redeemResp1->json('data.status'));

        // 4. Second redeem £60 — fully exhausts card
        $redeemResp2 = $this->withToken($this->token)
            ->postJson("/api/v2/gift-cards/{$code}/redeem", ['amount' => 60.00])
            ->assertOk();

        $this->assertEquals(0.00, $redeemResp2->json('data.balance'));
        $this->assertEquals('redeemed', $redeemResp2->json('data.status'));

        // 5. Deactivate attempt must fail
        $this->withToken($this->token)
            ->putJson("/api/v2/gift-cards/{$code}/deactivate")
            ->assertStatus(422);  // business rule: cannot deactivate redeemed

        // 6. Another redeem must fail
        $this->withToken($this->token)
            ->postJson("/api/v2/gift-cards/{$code}/redeem", ['amount' => 1])
            ->assertStatus(422);
    }

    /**
     * Deactivating an active card should mark it inactive (not redeemed).
     */
    public function test_gift_card_deactivation_prevents_future_use(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards', ['amount' => 50, 'code' => 'GC-DEACT-001'])
            ->assertCreated();

        $this->withToken($this->token)
            ->putJson('/api/v2/gift-cards/GC-DEACT-001/deactivate')
            ->assertOk();

        // Redeem after deactivation must fail
        $this->withToken($this->token)
            ->postJson('/api/v2/gift-cards/GC-DEACT-001/redeem', ['amount' => 10])
            ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // Scenario 3: Refund Workflow — Partial → Full
    // ═══════════════════════════════════════════════════════════

    /**
     * Journey:
     *   1. Create a £100 payment
     *   2. Partial refund £40 → payment.status = partial_refund
     *   3. Partial refund £30 → still partial_refund
     *   4. Remaining refund £30 → payment.status = refunded
     *   5. Over-refund attempt → 422
     */
    public function test_refund_workflow_partial_then_full(): void
    {
        $tx = $this->createTransaction(100.00);

        // 1. Create payment
        $payResp = $this->withToken($this->token)
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'method' => 'cash',
                'amount' => 100.00,
            ])
            ->assertCreated();

        $paymentId = $payResp->json('data.id');

        // 2. Partial refund £40
        $this->withToken($this->token)
            ->postJson("/api/v2/payments/{$paymentId}/refund", [
                'amount' => 40.00,
                'reason' => 'Customer returned item A',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'partial_refund',
        ]);

        // 3. Partial refund £30
        $this->withToken($this->token)
            ->postJson("/api/v2/payments/{$paymentId}/refund", [
                'amount' => 30.00,
                'reason' => 'Customer returned item B',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'partial_refund',
        ]);

        // 4. Final refund £30 (fully refunded)
        $this->withToken($this->token)
            ->postJson("/api/v2/payments/{$paymentId}/refund", ['amount' => 30.00])
            ->assertCreated();

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'refunded',
        ]);

        // 5. Over-refund attempt
        $this->withToken($this->token)
            ->postJson("/api/v2/payments/{$paymentId}/refund", ['amount' => 1.00])
            ->assertStatus(422);

        // Verify 3 refunds exist for this payment
        $this->withToken($this->token)
            ->getJson("/api/v2/payments/{$paymentId}/refunds")
            ->assertOk()
            ->assertJsonCount(3, 'data.data');
    }

    // ═══════════════════════════════════════════════════════════
    // Scenario 4: Daily Summary Accuracy
    // ═══════════════════════════════════════════════════════════

    /**
     * Creates payments, a refund, and an expense for today,
     * then verifies daily summary returns correct totals.
     */
    public function test_daily_summary_reflects_payments_refunds_and_expenses(): void
    {
        $today = now()->toDateString();

        // Create 2 cash payments £50, £70
        $tx1 = $this->createTransaction(50.00);
        $pay1 = Payment::create([
            'transaction_id' => $tx1->id,
            'method' => 'cash',
            'amount' => 50.00,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        $tx2 = $this->createTransaction(70.00);
        $pay2 = Payment::create([
            'transaction_id' => $tx2->id,
            'method' => 'card',
            'amount' => 70.00,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        // Refund £20 from pay1
        DB::table('refunds')->insert([
            'id' => Str::uuid()->toString(),
            'return_id' => Str::uuid()->toString(),
            'payment_id' => $pay1->id,
            'method' => 'cash',
            'amount' => 20.00,
            'status' => 'completed',
            'processed_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        // Add £15 expense
        $this->withToken($this->token)
            ->postJson('/api/v2/expenses', [
                'amount' => 15.00,
                'category' => 'supplies',
                'expense_date' => $today,
            ])
            ->assertCreated();

        // Get daily summary
        $summaryResp = $this->withToken($this->token)
            ->getJson("/api/v2/finance/daily-summary?date={$today}")
            ->assertOk();

        $summary = $summaryResp->json('data');

        // Gross = 50 + 70 = 120
        $this->assertEquals(120.00, (float) $summary['revenue']['gross']);

        // Refunds = 20
        $this->assertEquals(20.00, (float) $summary['revenue']['refunds']);

        // Net = 120 - 20 - 15 = 85  (gross - refunds - expenses)
        $this->assertEquals(85.00, (float) $summary['revenue']['net']);

        // Expenses = 15
        $this->assertEquals(15.00, (float) $summary['expenses']['total']);

        // Payment breakdown contains cash and card
        $breakdown = collect($summary['payment_breakdown']);
        $cashBreakdown = $breakdown->firstWhere('method', 'cash');
        $cardBreakdown = $breakdown->firstWhere('method', 'card');

        $this->assertNotNull($cashBreakdown);
        $this->assertNotNull($cardBreakdown);
        $this->assertEquals(50.00, (float) $cashBreakdown['total']);
        $this->assertEquals(70.00, (float) $cardBreakdown['total']);
    }

    /**
     * Hourly activity array must always have 24 slots.
     */
    public function test_daily_summary_hourly_activity_has_24_slots(): void
    {
        $today = now()->toDateString();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/finance/daily-summary?date={$today}")
            ->assertOk();

        $hourly = $response->json('data.hourly_activity');
        $this->assertCount(24, $hourly);
    }

    // ═══════════════════════════════════════════════════════════
    // Scenario 5: Reconciliation Variance
    // ═══════════════════════════════════════════════════════════

    /**
     * Opens a session, adds events, closes with known variance,
     * then verifies reconciliation shows correct data.
     */
    public function test_reconciliation_shows_correct_variance(): void
    {
        $today = now()->toDateString();

        // 1. Open session with £300 float
        $openResp = $this->withToken($this->token)
            ->postJson('/api/v2/cash-sessions', ['opening_float' => 300.00])
            ->assertCreated();

        $sessionId = $openResp->json('data.id');

        // 2. Cash in £100
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_in',
                'amount' => 100.00,
                'reason' => 'cash sales',
            ]);

        // 3. Cash out £50
        $this->withToken($this->token)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $sessionId,
                'type' => 'cash_out',
                'amount' => 50.00,
                'reason' => 'petty cash',
            ]);

        // Expected = 300 + 100 - 50 = 350
        // Actual = 345 → variance = -5

        // 4. Close session
        $this->withToken($this->token)
            ->putJson("/api/v2/cash-sessions/{$sessionId}/close", [
                'actual_cash' => 345.00,
            ])
            ->assertOk();

        // 5. Check reconciliation
        $recoResp = $this->withToken($this->token)
            ->getJson("/api/v2/finance/reconciliation?start_date={$today}&end_date={$today}")
            ->assertOk();

        $data = $recoResp->json('data');

        // At least 1 session found
        $this->assertGreaterThanOrEqual(1, $data['summary']['session_count']);

        // Sum of variance should include -5
        $totalVariance = (float) $data['summary']['total_variance'];
        $this->assertEquals(-5.00, round($totalVariance, 2));
    }

    // ═══════════════════════════════════════════════════════════
    // Scenario 6: Cross-Store Isolation
    // ═══════════════════════════════════════════════════════════

    /**
     * Data from a different store must not appear in this store's reports.
     */
    public function test_store_isolation_in_daily_summary(): void
    {
        $today = now()->toDateString();

        // Create another org/store and user
        $otherOrg = Organization::create(['name' => 'Other Org', 'business_type' => 'grocery', 'country' => 'SA']);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other@isolation.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $otherToken = $otherOwner->createToken('other')->plainTextToken;

        // Other store creates a £500 payment
        $session = PosSession::create([
            'store_id' => $otherStore->id,
            'cashier_id' => $otherOwner->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100,
            'transaction_count' => 0,
            'opened_at' => now(),
        ]);
        $tx = Transaction::create([
            'organization_id' => $otherOrg->id,
            'store_id' => $otherStore->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $otherOwner->id,
            'transaction_number' => 'TXN-ISO-' . uniqid(),
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 500,
        ]);
        Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => 500.00,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        // This store's summary should NOT include the £500
        $ownSummary = $this->withToken($this->token)
            ->getJson("/api/v2/finance/daily-summary?date={$today}")
            ->assertOk()
            ->json('data');

        $this->assertEquals(0.00, (float) $ownSummary['revenue']['gross']);

        // Other store's summary should show £500
        // Use actingAs to avoid Sanctum token lookup issues within nested transactions
        $otherSummary = $this->actingAs($otherOwner, 'sanctum')
            ->getJson("/api/v2/finance/daily-summary?date={$today}")
            ->assertOk()
            ->json('data');

        $this->assertEquals(500.00, (float) $otherSummary['revenue']['gross']);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function createTransaction(float $amount = 50.00): Transaction
    {
        $posSession = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->owner->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100.00,
            'transaction_count' => 0,
            'opened_at' => now(),
        ]);

        return Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'pos_session_id' => $posSession->id,
            'cashier_id' => $this->owner->id,
            'transaction_number' => 'TXN-WF-' . uniqid(),
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
        ]);
    }
}
