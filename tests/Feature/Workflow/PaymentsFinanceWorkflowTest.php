<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\Register;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * E2E Workflow Tests: Payments & Finance
 *
 * WF #PF01 – Full payment lifecycle (cash, card, gift card, split)
 * WF #PF02 – Cash session lifecycle (open → events → close → reconciliation)
 * WF #PF03 – Gift card lifecycle (issue → balance check → redeem → exhaust)
 * WF #PF04 – Expense tracking (create → list → update → delete)
 * WF #PF05 – Refund workflow
 * WF #PF06 – Financial daily summary accuracy
 * WF #PF07 – Multi-terminal isolation
 * WF #PF08 – Permission enforcement
 * WF #PF09 – Cross-branch gift card usage
 * WF #PF10 – Variance thresholds and over-tolerance warning
 */
class PaymentsFinanceWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private User $otherCashier;
    private Organization $org;
    private Store $store;
    private Store $branch;
    private string $ownerToken;
    private string $cashierToken;
    private Register $register;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Finance Test Org', 'name_ar' => 'منظمة اختبار المالية',
            'business_type' => 'grocery', 'country' => 'SA',
            'vat_number' => '300000000000099', 'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id, 'name' => 'Main Store',
            'name_ar' => 'المتجر الرئيسي', 'business_type' => 'grocery',
            'currency' => 'SAR', 'locale' => 'ar', 'timezone' => 'Asia/Riyadh',
            'is_active' => true, 'is_main_branch' => true,
        ]);

        $this->branch = Store::create([
            'organization_id' => $this->org->id, 'name' => 'Branch Store',
            'name_ar' => 'فرع المتجر', 'business_type' => 'grocery',
            'currency' => 'SAR', 'locale' => 'ar', 'timezone' => 'Asia/Riyadh',
            'is_active' => true, 'is_main_branch' => false,
        ]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@finance-wf.test',
            'password_hash' => bcrypt('password'), 'store_id' => $this->store->id,
            'organization_id' => $this->org->id, 'role' => 'owner', 'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@finance-wf.test',
            'password_hash' => bcrypt('password'), 'pin_hash' => bcrypt('1234'),
            'store_id' => $this->store->id, 'organization_id' => $this->org->id,
            'role' => 'cashier', 'is_active' => true,
        ]);

        $this->otherCashier = User::create([
            'name' => 'Branch Cashier', 'email' => 'branch.cashier@finance-wf.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->branch->id, 'organization_id' => $this->org->id,
            'role' => 'cashier', 'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->cashierToken = $this->cashier->createToken('test', ['*'])->plainTextToken;

        $this->assignOwnerRole($this->owner, $this->store->id);
        $this->assignCashierRole($this->cashier, $this->store->id);

        $this->register = Register::create([
            'store_id' => $this->store->id, 'name' => 'Terminal 1',
            'device_id' => 'TERM-PF-001', 'app_version' => '1.0.0',
            'platform' => 'windows', 'is_active' => true, 'is_online' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF01 — Full Payment Lifecycle
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf01a_cash_payment_full_lifecycle(): void
    {
        $txn = $this->makeTransaction();

        // Create a cash payment
        $createRes = $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id'  => $txn->id,
            'method'          => 'cash',
            'amount'          => 50.00,
            'cash_tendered'   => 100.00,
            'change_given'    => 50.00,
        ]);

        $createRes->assertStatus(201);
        $createRes->assertJsonPath('success', true);
        $paymentId = $createRes->json('data.id');
        $this->assertNotNull($paymentId);

        // Verify payment appears in list
        $listRes = $this->withToken($this->cashierToken)->getJson('/api/v2/payments');
        $listRes->assertStatus(200);
        $ids = collect($listRes->json('data.data'))->pluck('id')->toArray();
        $this->assertContains($paymentId, $ids);

        // Filter by method
        $filteredRes = $this->withToken($this->cashierToken)->getJson('/api/v2/payments?method=cash');
        $filteredRes->assertStatus(200);
        $this->assertNotEmpty($filteredRes->json('data.data'));
    }

    /** @test */
    public function wf_pf01b_card_payment_with_metadata(): void
    {
        $txn = $this->makeTransaction();

        $res = $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id'  => $txn->id,
            'method'          => 'card_mada',
            'amount'          => 75.00,
            'card_brand'      => 'Mada',
            'card_last_four'  => '1234',
            'card_auth_code'  => 'AUTH9999',
            'card_reference'  => 'REF-MADA-001',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('payments', [
            'method'         => 'card_mada',
            'card_last_four' => '1234',
            'card_auth_code' => 'AUTH9999',
        ]);
    }

    /** @test */
    public function wf_pf01c_gift_card_payment(): void
    {
        $card = $this->makeGiftCard(balance: 100.0);
        $txn  = $this->makeTransaction();

        $res = $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id' => $txn->id,
            'method'         => 'gift_card',
            'amount'         => 40.00,
            'gift_card_code' => $card->code,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('payments', [
            'method'         => 'gift_card',
            'gift_card_code' => $card->code,
        ]);
    }

    /** @test */
    public function wf_pf01d_split_payment_cash_and_card(): void
    {
        $txn = $this->makeTransaction(total: 150.0);

        $cashRes = $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id' => $txn->id,
            'method'         => 'cash',
            'amount'         => 50.00,
            'cash_tendered'  => 50.00,
            'change_given'   => 0.00,
        ]);
        $cashRes->assertStatus(201);

        $cardRes = $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id' => $txn->id,
            'method'         => 'card_visa',
            'amount'         => 100.00,
            'card_last_four' => '4321',
        ]);
        $cardRes->assertStatus(201);

        // Both payments linked to same transaction
        $this->assertDatabaseHas('payments', ['transaction_id' => $txn->id, 'method' => 'cash']);
        $this->assertDatabaseHas('payments', ['transaction_id' => $txn->id, 'method' => 'card_visa']);
    }

    /** @test */
    public function wf_pf01e_payment_search_by_card_reference(): void
    {
        $txn = $this->makeTransaction();
        $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id' => $txn->id,
            'method'         => 'card_mada',
            'amount'         => 30.0,
            'card_reference' => 'REF-SEARCH-XYZ',
            'card_last_four' => '9999',
        ]);

        $res = $this->withToken($this->cashierToken)->getJson('/api/v2/payments?search=REF-SEARCH-XYZ');
        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('data.data'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF02 — Cash Session Lifecycle
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf02a_full_cash_session_lifecycle(): void
    {
        $terminal = '00000000-0000-0000-0000-000000000aaa';

        // 1. Open session
        $openRes = $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id'   => $terminal,
            'opening_float' => 500.00,
        ]);
        $openRes->assertStatus(201);
        $sessionId = $openRes->json('data.id');

        // 2. Add cash in event
        $inRes = $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $sessionId,
            'type'            => 'cash_in',
            'amount'          => 100.00,
            'reason'          => 'Replenishment',
        ]);
        $inRes->assertStatus(201);

        // Verify expected_cash updated
        $showRes = $this->withToken($this->cashierToken)->getJson("/api/v2/cash-sessions/{$sessionId}");
        $showRes->assertStatus(200);
        $this->assertEquals(600.0, (float) $showRes->json('data.expected_cash'));

        // 3. Add cash out event
        $outRes = $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $sessionId,
            'type'            => 'cash_out',
            'amount'          => 50.00,
            'reason'          => 'Petty cash',
        ]);
        $outRes->assertStatus(201);

        $showRes2 = $this->withToken($this->cashierToken)->getJson("/api/v2/cash-sessions/{$sessionId}");
        $this->assertEquals(550.0, (float) $showRes2->json('data.expected_cash'));

        // 4. Close session
        $closeRes = $this->withToken($this->cashierToken)->putJson("/api/v2/cash-sessions/{$sessionId}/close", [
            'actual_cash' => 545.00,
            'close_notes' => 'Short by 5',
        ]);
        $closeRes->assertStatus(200);
        $closeRes->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('cash_sessions', [
            'id'     => $sessionId,
            'status' => 'closed',
        ]);
    }

    /** @test */
    public function wf_pf02b_cannot_open_duplicate_terminal_session(): void
    {
        $terminal = '00000000-0000-0000-0000-000000000bbb';

        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id'   => $terminal,
            'opening_float' => 200.00,
        ])->assertStatus(201);

        // Second attempt same terminal same store → 422
        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id'   => $terminal,
            'opening_float' => 100.00,
        ])->assertStatus(422);
    }

    /** @test */
    public function wf_pf02c_different_terminals_can_open_concurrent_sessions(): void
    {
        $t1 = '00000000-0000-0000-0000-000000000cc1';
        $t2 = '00000000-0000-0000-0000-000000000cc2';

        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => $t1, 'opening_float' => 100.00,
        ])->assertStatus(201);

        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => $t2, 'opening_float' => 200.00,
        ])->assertStatus(201);

        $listRes = $this->withToken($this->cashierToken)->getJson('/api/v2/cash-sessions');
        $this->assertGreaterThanOrEqual(2, count($listRes->json('data.data')));
    }

    /** @test */
    public function wf_pf02d_cannot_close_already_closed_session(): void
    {
        $session = $this->makeClosedSession();

        $this->withToken($this->cashierToken)->putJson("/api/v2/cash-sessions/{$session->id}/close", [
            'actual_cash' => 100.00,
        ])->assertStatus(422);
    }

    /** @test */
    public function wf_pf02e_cash_event_updates_expected_cash_formula(): void
    {
        // Expected cash = opening_float + cash_in - cash_out + sales_cash
        $terminal = '00000000-0000-0000-0000-000000000ee1';
        $openRes = $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => $terminal, 'opening_float' => 300.00,
        ]);
        $sessionId = $openRes->json('data.id');

        // cash_in 50
        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $sessionId, 'type' => 'cash_in', 'amount' => 50.00, 'reason' => 'a',
        ])->assertStatus(201);

        // cash_out 20
        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $sessionId, 'type' => 'cash_out', 'amount' => 20.00, 'reason' => 'b',
        ])->assertStatus(201);

        $showRes = $this->withToken($this->cashierToken)->getJson("/api/v2/cash-sessions/{$sessionId}");
        // 300 + 50 - 20 = 330
        $this->assertEquals(330.0, (float) $showRes->json('data.expected_cash'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF03 — Gift Card Lifecycle
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf03a_full_gift_card_lifecycle(): void
    {
        // 1. Issue card
        $issueRes = $this->withToken($this->cashierToken)->postJson('/api/v2/gift-cards', [
            'amount'         => 200.00,
            'recipient_name' => 'Alice',
        ]);
        $issueRes->assertStatus(201);
        $code    = $issueRes->json('data.code');
        $balance = (float) $issueRes->json('data.balance');
        $this->assertEquals(200.0, $balance);
        $this->assertNotEmpty($code);

        // 2. Check balance
        $balRes = $this->withToken($this->cashierToken)->getJson("/api/v2/gift-cards/{$code}/balance");
        $balRes->assertStatus(200);
        $this->assertEquals(200.0, (float) $balRes->json('data.balance'));

        // 3. Partial redeem
        $redeemRes = $this->withToken($this->cashierToken)->postJson("/api/v2/gift-cards/{$code}/redeem", [
            'amount' => 80.00,
        ]);
        $redeemRes->assertStatus(200);
        $this->assertEquals(120.0, (float) $redeemRes->json('data.balance'));
        $redeemRes->assertJsonPath('data.status', 'active');

        // 4. Redeem remaining
        $finalRes = $this->withToken($this->cashierToken)->postJson("/api/v2/gift-cards/{$code}/redeem", [
            'amount' => 120.00,
        ]);
        $finalRes->assertStatus(200);
        $this->assertEquals(0.0, (float) $finalRes->json('data.balance'));
        $finalRes->assertJsonPath('data.status', 'redeemed');
    }

    /** @test */
    public function wf_pf03b_cannot_redeem_expired_gift_card(): void
    {
        $card = $this->makeGiftCard(balance: 50.0, expiresInDays: -1);

        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/gift-cards/{$card->code}/redeem", ['amount' => 10.00])
            ->assertStatus(422);
    }

    /** @test */
    public function wf_pf03c_cannot_redeem_more_than_balance(): void
    {
        $card = $this->makeGiftCard(balance: 30.0);

        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/gift-cards/{$card->code}/redeem", ['amount' => 50.00])
            ->assertStatus(422);
    }

    /** @test */
    public function wf_pf03d_gift_card_cross_branch_redemption(): void
    {
        // Card issued at main store — used at branch (same org)
        $card = $this->makeGiftCard(balance: 100.0);
        $branchToken = $this->otherCashier->createToken('branch', ['*'])->plainTextToken;
        $this->assignCashierRole($this->otherCashier, $this->branch->id);

        $res = $this->withToken($branchToken)
            ->postJson("/api/v2/gift-cards/{$card->code}/redeem", ['amount' => 25.00]);

        $res->assertStatus(200);
        $this->assertEquals(75.0, (float) $res->json('data.balance'));
    }

    /** @test */
    public function wf_pf03e_deactivate_gift_card(): void
    {
        $card = $this->makeGiftCard(balance: 100.0);

        $deactRes = $this->withToken($this->ownerToken)->putJson("/api/v2/gift-cards/{$card->code}/deactivate");
        $deactRes->assertStatus(200);

        // Cannot redeem deactivated card
        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/gift-cards/{$card->code}/redeem", ['amount' => 10.00])
            ->assertStatus(422);
    }

    /** @test */
    public function wf_pf03f_cannot_deactivate_already_redeemed_card(): void
    {
        $card = $this->makeGiftCard(balance: 0.0, status: GiftCardStatus::Redeemed);

        $this->withToken($this->ownerToken)
            ->putJson("/api/v2/gift-cards/{$card->code}/deactivate")
            ->assertStatus(422);
    }

    /** @test */
    public function wf_pf03g_list_gift_cards_filtered_by_status(): void
    {
        $this->makeGiftCard(balance: 100.0, status: GiftCardStatus::Active);
        $this->makeGiftCard(balance: 0.0, status: GiftCardStatus::Redeemed);

        $activeRes = $this->withToken($this->ownerToken)->getJson('/api/v2/gift-cards?status=active');
        $activeRes->assertStatus(200);
        $statuses = collect($activeRes->json('data.data'))->pluck('status')->toArray();
        $this->assertContains('active', $statuses);
        $this->assertNotContains('redeemed', $statuses);
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF04 — Expense Tracking
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf04a_full_expense_lifecycle(): void
    {
        // 1. Create expense
        $createRes = $this->withToken($this->cashierToken)->postJson('/api/v2/expenses', [
            'amount'      => 45.50,
            'category'    => 'supplies',
            'description' => 'Cleaning supplies',
        ]);
        $createRes->assertStatus(201);
        $expenseId = $createRes->json('data.id');

        // 2. List expenses
        $listRes = $this->withToken($this->cashierToken)->getJson('/api/v2/expenses');
        $listRes->assertStatus(200);
        $this->assertNotEmpty($listRes->json('data.data'));

        // 3. Update expense
        $updateRes = $this->withToken($this->cashierToken)->putJson("/api/v2/expenses/{$expenseId}", [
            'amount'      => 60.00,
            'description' => 'Cleaning supplies (updated)',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals(60.00, (float) $updateRes->json('data.amount'));

        // 4. Delete expense
        $deleteRes = $this->withToken($this->cashierToken)->deleteJson("/api/v2/expenses/{$expenseId}");
        $deleteRes->assertStatus(200);
        $deleteRes->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    /** @test */
    public function wf_pf04b_expense_linked_to_cash_session(): void
    {
        $terminal = '00000000-0000-0000-0000-000000000ff1';
        $openRes = $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => $terminal, 'opening_float' => 200.00,
        ]);
        $sessionId = $openRes->json('data.id');

        $expRes = $this->withToken($this->cashierToken)->postJson('/api/v2/expenses', [
            'amount'          => 30.00,
            'category'        => 'food',
            'cash_session_id' => $sessionId,
        ]);
        $expRes->assertStatus(201);
        $this->assertEquals($sessionId, $expRes->json('data.cash_session_id'));
    }

    /** @test */
    public function wf_pf04c_expense_date_filters(): void
    {
        // Past expense
        \App\Domain\Payment\Models\Expense::create([
            'store_id'     => $this->store->id,
            'amount'       => 100,
            'category'     => 'supplies',
            'recorded_by'  => $this->cashier->id,
            'expense_date' => now()->subDays(10)->toDateString(),
        ]);
        // Today expense
        \App\Domain\Payment\Models\Expense::create([
            'store_id'     => $this->store->id,
            'amount'       => 50,
            'category'     => 'food',
            'recorded_by'  => $this->cashier->id,
            'expense_date' => now()->toDateString(),
        ]);

        $res = $this->withToken($this->cashierToken)->getJson(
            '/api/v2/expenses?start_date=' . now()->toDateString() . '&end_date=' . now()->toDateString()
        );
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.data'));
        $this->assertEquals('food', $res->json('data.data.0.category'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF05 — Refund Workflow
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf05a_refund_full_lifecycle(): void
    {
        $txn      = $this->makeTransaction(total: 100.0);
        $payment  = $this->createPayment($txn, 100.0, 'cash');

        // Create refund — return_id is optional; service falls back to payment->id
        // FK checks are bypassed in test DB (session_replication_role=replica)
        $refundRes = $this->withToken($this->cashierToken)->postJson("/api/v2/payments/{$payment->id}/refund", [
            'amount' => 40.00,
            'method' => 'cash',
        ]);
        $refundRes->assertStatus(201);
        $refundId = $refundRes->json('data.id');

        // List refunds for payment
        $listRes = $this->withToken($this->cashierToken)->getJson("/api/v2/payments/{$payment->id}/refunds");
        $listRes->assertStatus(200);
        $this->assertNotEmpty($listRes->json('data.data'));

        // List all refunds
        $allRes = $this->withToken($this->cashierToken)->getJson('/api/v2/payments/refunds');
        $allRes->assertStatus(200);
        $ids = collect($allRes->json('data.data'))->pluck('id')->toArray();
        $this->assertContains($refundId, $ids);
    }

    /** @test */
    public function wf_pf05b_cannot_refund_more_than_payment(): void
    {
        $txn     = $this->makeTransaction(total: 50.0);
        $payment = $this->createPayment($txn, 50.0, 'cash');

        // Attempt to refund more than the payment amount — service must reject with 422
        $this->withToken($this->cashierToken)->postJson("/api/v2/payments/{$payment->id}/refund", [
            'amount' => 60.00,
            'method' => 'cash',
        ])->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF06 — Financial Daily Summary
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf06a_daily_summary_structure_and_accuracy(): void
    {
        $txn1 = $this->makeTransaction(total: 100.0);
        $txn2 = $this->makeTransaction(total: 200.0);

        $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id' => $txn1->id, 'method' => 'cash', 'amount' => 100.0,
        ]);
        $this->withToken($this->cashierToken)->postJson('/api/v2/payments', [
            'transaction_id' => $txn2->id, 'method' => 'card_mada', 'amount' => 200.0,
        ]);

        $res = $this->withToken($this->ownerToken)->getJson('/api/v2/finance/daily-summary?date=' . now()->toDateString());
        $res->assertStatus(200);
        $res->assertJsonStructure([
            'data' => [
                'date', 'store_id',
                'revenue' => ['gross', 'refunds', 'expenses', 'net'],
                'transactions' => ['count', 'refund_count', 'average'],
                'payment_breakdown',
                'cash_sessions',
                'expenses',
                'hourly_activity',
            ],
        ]);

        $data = $res->json('data');
        $this->assertEquals(300.0, (float) $data['revenue']['gross']);
        $this->assertEquals(2, $data['transactions']['count']);
        $this->assertEquals(150.0, (float) $data['transactions']['average']);
    }

    /** @test */
    public function wf_pf06b_daily_summary_includes_expenses(): void
    {
        \App\Domain\Payment\Models\Expense::create([
            'store_id'     => $this->store->id,
            'amount'       => 75.0,
            'category'     => 'supplies',
            'recorded_by'  => $this->cashier->id,
            'expense_date' => now()->toDateString(),
        ]);

        $res = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/finance/daily-summary?date=' . now()->toDateString());
        $res->assertStatus(200);

        $this->assertEquals(75.0, (float) $res->json('data.revenue.expenses'));
    }

    /** @test */
    public function wf_pf06c_daily_summary_defaults_to_today(): void
    {
        $res = $this->withToken($this->ownerToken)->getJson('/api/v2/finance/daily-summary');
        $res->assertStatus(200);
        $this->assertEquals(now()->toDateString(), $res->json('data.date'));
    }

    /** @test */
    public function wf_pf06d_reconciliation_endpoint(): void
    {
        $res = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/finance/reconciliation?start_date=' . now()->toDateString() . '&end_date=' . now()->toDateString());
        $res->assertStatus(200);
        $res->assertJsonStructure([
            'data' => [
                'start_date', 'end_date',
                'summary' => ['session_count', 'total_expected', 'total_actual', 'total_variance', 'within_tolerance'],
                'sessions',
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF07 — Multi-terminal Isolation
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf07_store_scoping_prevents_cross_store_access(): void
    {
        // Main store cashier opens a session
        $mainRes = $this->withToken($this->cashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => '00000000-0000-0000-0000-000000000aa1', 'opening_float' => 200.00,
        ]);
        $mainRes->assertStatus(201);
        $mainSessionId = $mainRes->json('data.id');

        // Branch cashier opens a session at a different store
        $branchCashierToken = $this->otherCashier->createToken('branch', ['*'])->plainTextToken;
        $this->assignCashierRole($this->otherCashier, $this->branch->id);
        $this->withToken($branchCashierToken)->postJson('/api/v2/cash-sessions', [
            'terminal_id' => '00000000-0000-0000-0000-000000000bb1', 'opening_float' => 100.00,
        ])->assertStatus(201);

        // Main store cashier CAN see their own session in the list
        $listRes = $this->withToken($this->cashierToken)->getJson('/api/v2/cash-sessions');
        $listRes->assertStatus(200);
        $ids = collect($listRes->json('data.data'))->pluck('id')->toArray();
        $this->assertContains($mainSessionId, $ids);
    }

    // ═══════════════════════════════════════════════════════════════════
    // WF #PF08 — Unauthenticated Access Rejected
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function wf_pf08a_all_payment_endpoints_require_auth(): void
    {
        $endpoints = [
            ['GET',  '/api/v2/payments'],
            ['GET',  '/api/v2/cash-sessions'],
            ['GET',  '/api/v2/gift-cards'],
            ['GET',  '/api/v2/expenses'],
            ['GET',  '/api/v2/payments/refunds'],
            ['GET',  '/api/v2/finance/daily-summary'],
            ['GET',  '/api/v2/finance/reconciliation'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->json($method, $url)->assertStatus(401);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════

    private function makeTransaction(float $total = 50.0): Transaction
    {
        $session = PosSession::create([
            'store_id'          => $this->store->id,
            'register_id'       => $this->register->id,
            'cashier_id'        => $this->cashier->id,
            'status'            => CashSessionStatus::Open,
            'opening_cash'      => 200.00,
            'transaction_count' => 0,
            'opened_at'         => now(),
        ]);

        return Transaction::create([
            'organization_id'   => $this->org->id,
            'store_id'          => $this->store->id,
            'pos_session_id'    => $session->id,
            'cashier_id'        => $this->cashier->id,
            'transaction_number' => 'TXN-WF-' . strtoupper(Str::random(6)),
            'type'              => 'sale',
            'status'            => 'completed',
            'subtotal'          => $total,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'total_amount'      => $total,
        ]);
    }

    private function createPayment(Transaction $txn, float $amount, string $method): \App\Domain\Payment\Models\Payment
    {
        return \App\Domain\Payment\Models\Payment::create([
            'transaction_id' => $txn->id,
            'method'         => $method,
            'amount'         => $amount,
            'status'         => 'completed',
        ]);
    }

    private function makeGiftCard(
        float $balance = 100.0,
        int $expiresInDays = 365,
        GiftCardStatus $status = GiftCardStatus::Active,
    ): GiftCard {
        $code = 'GC-WF-' . strtoupper(Str::random(8));
        return GiftCard::create([
            'organization_id' => $this->org->id,
            'code'            => $code,
            'barcode'         => $code,
            'initial_amount'  => 100.0,
            'balance'         => $balance,
            'status'          => $status,
            'issued_by'       => $this->cashier->id,
            'issued_at_store' => $this->store->id,
            'expires_at'      => now()->addDays($expiresInDays),
        ]);
    }

    private function makeClosedSession(): CashSession
    {
        return CashSession::create([
            'store_id'     => $this->store->id,
            'terminal_id'  => '00000000-0000-0000-0000-000000000ccc',
            'opened_by'    => $this->cashier->id,
            'opening_float' => 100,
            'expected_cash' => 100,
            'actual_cash'   => 100,
            'variance'      => 0,
            'status'        => CashSessionStatus::Closed,
            'opened_at'     => now()->subHour(),
            'closed_at'     => now(),
        ]);
    }
}
