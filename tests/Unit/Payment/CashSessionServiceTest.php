<?php

namespace Tests\Unit\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Payment\Enums\CashEventType;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\Expense;
use App\Domain\Payment\Services\CashSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for CashSessionService.
 *
 * Covers: open session, duplicate prevention, cash events (in/out),
 * expected cash formula, close with variance, expense CRUD,
 * cannot add events to closed session.
 */
class CashSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CashSessionService $service;
    private User $user;
    private Store $store;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CashSessionService::class);

        $this->org = Organization::create([
            'name' => 'CashSession Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'CashSession Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'cs@unit.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    // ─── Open Session ─────────────────────────────────────────

    public function test_open_creates_session_with_open_status(): void
    {
        $session = $this->service->open(['opening_float' => 200.00], $this->user);

        $this->assertInstanceOf(CashSession::class, $session);
        $this->assertEquals(CashSessionStatus::Open, $session->status);
        $this->assertEquals(200.00, (float) $session->opening_float);
        $this->assertEquals($this->store->id, $session->store_id);
        $this->assertEquals($this->user->id, $session->opened_by);
    }

    public function test_open_sets_expected_cash_to_opening_float(): void
    {
        $session = $this->service->open(['opening_float' => 500.00], $this->user);

        $this->assertEquals(500.00, (float) $session->expected_cash);
    }

    public function test_open_with_terminal_id(): void
    {
        $session = $this->service->open([
            'opening_float' => 100.00,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
        ], $this->user);

        $this->assertEquals('00000000-0000-0000-0000-000000000001', $session->terminal_id);
    }

    public function test_open_throws_when_terminal_already_has_open_session(): void
    {
        $this->service->open([
            'opening_float' => 100.00,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
        ], $this->user);

        $this->expectException(\RuntimeException::class);
        $this->service->open([
            'opening_float' => 200.00,
            'terminal_id' => '00000000-0000-0000-0000-000000000001',
        ], $this->user);
    }

    public function test_open_allows_session_without_terminal_after_another_open(): void
    {
        // Sessions without terminal_id should not block each other
        $session1 = $this->service->open(['opening_float' => 100.00], $this->user);
        $session2 = $this->service->open(['opening_float' => 200.00], $this->user);

        $this->assertNotEquals($session1->id, $session2->id);
    }

    // ─── Cash Events ─────────────────────────────────────────

    public function test_cash_in_increases_expected_cash(): void
    {
        $session = $this->makeOpenSession(opening: 200.00);

        $this->service->addCashEvent($session, [
            'type' => 'cash_in',
            'amount' => 50.00,
            'reason' => 'Change replenishment',
        ], $this->user);

        $session->refresh();
        $this->assertEquals(250.00, (float) $session->expected_cash);
    }

    public function test_cash_out_decreases_expected_cash(): void
    {
        $session = $this->makeOpenSession(opening: 300.00);

        $this->service->addCashEvent($session, [
            'type' => 'cash_out',
            'amount' => 100.00,
            'reason' => 'Bank deposit',
        ], $this->user);

        $session->refresh();
        $this->assertEquals(200.00, (float) $session->expected_cash);
    }

    public function test_multiple_events_accumulate_correctly(): void
    {
        $session = $this->makeOpenSession(opening: 500.00);

        $this->service->addCashEvent($session, ['type' => 'cash_in', 'amount' => 100, 'reason' => 'tips'], $this->user);
        $this->service->addCashEvent($session, ['type' => 'cash_in', 'amount' => 50, 'reason' => 'change'], $this->user);
        $this->service->addCashEvent($session, ['type' => 'cash_out', 'amount' => 75, 'reason' => 'petty cash'], $this->user);

        $session->refresh();
        // 500 + 100 + 50 - 75 = 575
        $this->assertEquals(575.00, (float) $session->expected_cash);
    }

    public function test_add_cash_event_throws_for_closed_session(): void
    {
        $session = $this->makeClosedSession();

        $this->expectException(\RuntimeException::class);
        $this->service->addCashEvent($session, [
            'type' => 'cash_in',
            'amount' => 50,
            'reason' => 'test',
        ], $this->user);
    }

    public function test_cash_event_records_performed_by(): void
    {
        $session = $this->makeOpenSession();

        $event = $this->service->addCashEvent($session, [
            'type' => 'cash_in',
            'amount' => 25,
            'reason' => 'tips collected',
        ], $this->user);

        $this->assertEquals($this->user->id, $event->performed_by);
    }

    // ─── Close Session ────────────────────────────────────────

    public function test_close_session_updates_status_and_variance(): void
    {
        $session = $this->makeOpenSession(opening: 200.00);

        $closed = $this->service->close($session, ['actual_cash' => 195.00], $this->user);

        $this->assertEquals(CashSessionStatus::Closed, $closed->status);
        $this->assertEquals(195.00, (float) $closed->actual_cash);
        $this->assertEquals(-5.00, round((float) $closed->variance, 2));
        $this->assertNotNull($closed->closed_at);
        $this->assertEquals($this->user->id, $closed->closed_by);
    }

    public function test_close_session_positive_variance(): void
    {
        $session = $this->makeOpenSession(opening: 200.00);

        $closed = $this->service->close($session, ['actual_cash' => 210.00], $this->user);

        $this->assertEquals(10.00, round((float) $closed->variance, 2));
    }

    public function test_close_session_zero_variance(): void
    {
        $session = $this->makeOpenSession(opening: 300.00);

        $closed = $this->service->close($session, ['actual_cash' => 300.00], $this->user);

        $this->assertEquals(0.00, round((float) $closed->variance, 2));
    }

    public function test_close_session_stores_close_notes(): void
    {
        $session = $this->makeOpenSession(opening: 100.00);

        $closed = $this->service->close($session, [
            'actual_cash' => 95.00,
            'close_notes' => 'Short due to counting error',
        ], $this->user);

        $this->assertEquals('Short due to counting error', $closed->close_notes);
    }

    public function test_close_throws_for_already_closed_session(): void
    {
        $session = $this->makeClosedSession();

        $this->expectException(\RuntimeException::class);
        $this->service->close($session, ['actual_cash' => 100], $this->user);
    }

    // ─── Expected Cash Formula ───────────────────────────────
    // expected = opening_float + cash_events(in) - cash_events(out)

    public function test_expected_cash_formula_is_correct(): void
    {
        $session = $this->makeOpenSession(opening: 100.00);

        $this->service->addCashEvent($session, ['type' => 'cash_in', 'amount' => 200, 'reason' => 'tips'], $this->user);
        $this->service->addCashEvent($session, ['type' => 'cash_out', 'amount' => 50, 'reason' => 'petty'], $this->user);

        $session->refresh();
        // 100 + 200 - 50 = 250
        $this->assertEquals(250.00, (float) $session->expected_cash);
    }

    // ─── Expenses ─────────────────────────────────────────────

    public function test_add_expense_creates_record(): void
    {
        $expense = $this->service->addExpense([
            'amount' => 45.00,
            'category' => 'supplies',
            'description' => 'Cleaning supplies',
        ], $this->user);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertEquals(45.00, (float) $expense->amount);
        $this->assertEquals('supplies', $expense->category instanceof \BackedEnum ? $expense->category->value : $expense->category);
        $this->assertEquals($this->store->id, $expense->store_id);
        $this->assertEquals($this->user->id, $expense->recorded_by);
    }

    public function test_add_expense_links_to_cash_session(): void
    {
        $session = $this->makeOpenSession();

        $expense = $this->service->addExpense([
            'cash_session_id' => $session->id,
            'amount' => 20.00,
            'category' => 'food',
        ], $this->user);

        $this->assertEquals($session->id, $expense->cash_session_id);
    }

    public function test_add_expense_defaults_date_to_today(): void
    {
        $expense = $this->service->addExpense([
            'amount' => 10,
            'category' => 'transport',
        ], $this->user);

        $dateVal = $expense->expense_date instanceof \Carbon\Carbon ? $expense->expense_date->toDateString() : (string) $expense->expense_date;
        $this->assertEquals(now()->toDateString(), $dateVal);
    }

    public function test_list_expenses_scopes_by_store(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'business_type' => 'grocery', 'country' => 'SA']);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        // Other store expense
        Expense::create([
            'store_id' => $otherStore->id,
            'amount' => 999,
            'category' => 'other',
            'recorded_by' => $this->user->id,
            'expense_date' => now()->toDateString(),
        ]);

        // Our store expense
        $this->service->addExpense(['amount' => 10, 'category' => 'supplies'], $this->user);

        $result = $this->service->listExpenses($this->store->id);
        $this->assertCount(1, $result->items());
        $this->assertEquals($this->store->id, $result->items()[0]->store_id);
    }

    public function test_list_expenses_filters_by_date(): void
    {
        Expense::create([
            'store_id' => $this->store->id,
            'amount' => 100,
            'category' => 'supplies',
            'recorded_by' => $this->user->id,
            'expense_date' => now()->subDays(10)->toDateString(),
        ]);
        Expense::create([
            'store_id' => $this->store->id,
            'amount' => 50,
            'category' => 'food',
            'recorded_by' => $this->user->id,
            'expense_date' => now()->toDateString(),
        ]);

        $result = $this->service->listExpenses($this->store->id, [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->assertCount(1, $result->items());
        $catVal = $result->items()[0]->category;
        $this->assertEquals('food', $catVal instanceof \BackedEnum ? $catVal->value : $catVal);
    }

    public function test_list_expenses_filters_by_category(): void
    {
        Expense::create(['store_id' => $this->store->id, 'amount' => 10, 'category' => 'food', 'recorded_by' => $this->user->id, 'expense_date' => now()->toDateString()]);
        Expense::create(['store_id' => $this->store->id, 'amount' => 20, 'category' => 'supplies', 'recorded_by' => $this->user->id, 'expense_date' => now()->toDateString()]);

        $result = $this->service->listExpenses($this->store->id, ['category' => 'food']);
        $this->assertCount(1, $result->items());
    }

    public function test_update_expense_modifies_fields(): void
    {
        $expense = Expense::create([
            'store_id' => $this->store->id,
            'amount' => 30.00,
            'category' => 'supplies',
            'description' => 'Original',
            'recorded_by' => $this->user->id,
            'expense_date' => now()->toDateString(),
        ]);

        $updated = $this->service->updateExpense($expense, [
            'amount' => 55.00,
            'description' => 'Updated desc',
        ]);

        $this->assertEquals(55.00, (float) $updated->amount);
        $this->assertEquals('Updated desc', $updated->description);
    }

    public function test_delete_expense_removes_record(): void
    {
        $expense = Expense::create([
            'store_id' => $this->store->id,
            'amount' => 25,
            'category' => 'other',
            'recorded_by' => $this->user->id,
            'expense_date' => now()->toDateString(),
        ]);

        $this->service->deleteExpense($expense);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    // ─── Find ─────────────────────────────────────────────────

    public function test_find_returns_session_with_relations(): void
    {
        $session = $this->makeOpenSession();
        $this->service->addCashEvent($session, ['type' => 'cash_in', 'amount' => 10, 'reason' => 'test'], $this->user);

        $found = $this->service->find($session->id);

        $this->assertEquals($session->id, $found->id);
        $this->assertTrue($found->relationLoaded('cashEvents'));
        $this->assertTrue($found->relationLoaded('expenses'));
        $this->assertCount(1, $found->cashEvents);
    }

    public function test_find_throws_for_missing_session(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->find('00000000-0000-0000-0000-000000000099');
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function makeOpenSession(float $opening = 200.00): CashSession
    {
        return CashSession::create([
            'store_id' => $this->store->id,
            'opened_by' => $this->user->id,
            'opening_float' => $opening,
            'expected_cash' => $opening,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    private function makeClosedSession(float $opening = 200.00): CashSession
    {
        return CashSession::create([
            'store_id' => $this->store->id,
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
            'opening_float' => $opening,
            'expected_cash' => $opening,
            'actual_cash' => $opening,
            'variance' => 0,
            'status' => CashSessionStatus::Closed,
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
        ]);
    }
}
