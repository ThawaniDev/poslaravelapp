<?php

namespace Tests\Feature\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\Expense;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\Payment\Models\Payment;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BypassPermissionMiddleware;
use Tests\TestCase;

/**
 * Payment Permission Enforcement Tests
 *
 * Verifies that every payment endpoint enforces the correct permission:
 * - 401  unauthenticated
 * - 403  authenticated but missing required permission
 * - 200/201  authenticated with correct permission (or owner role)
 *
 * Permissions tested:
 *   payments.process   — list/create payments
 *   payments.refund    — list/create refunds
 *   cash.view_sessions — list/show cash sessions
 *   cash.manage        — open/close session, cash events, expenses
 *   finance.expenses   — create/update/delete expenses
 *   finance.gift_cards — issue/redeem/list/deactivate gift cards
 *   cash.view_daily_summary — daily summary
 *   cash.reconciliation     — reconciliation report
 */
class PaymentPermissionTest extends TestCase
{
    use RefreshDatabase;

    private static bool $classMigrated = false;

    private User $owner;
    private User $userNoPerms;
    private User $paymentsUser;
    private User $cashUser;
    private User $financeUser;
    private User $reportsUser;

    private Organization $org;
    private Store $store;

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

        // Re-enable real permission middleware
        $router = app('router');
        $router->aliasMiddleware('permission', CheckPermission::class);

        // Seed all permissions
        app(PermissionService::class)->seedAll();

        $this->org = Organization::create([
            'name' => 'Perm Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Perm Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = $this->makeUser('owner@perm-pay.test', 'owner');
        $this->userNoPerms = $this->makeUser('noperms@perm-pay.test', 'cashier');

        $this->paymentsUser = $this->makeUser('payments@perm-pay.test', 'cashier');
        $this->grantPermissions($this->paymentsUser, [
            'payments.process',
            'payments.refund',
        ]);

        $this->cashUser = $this->makeUser('cash@perm-pay.test', 'cashier');
        $this->grantPermissions($this->cashUser, [
            'cash.view_sessions',
            'cash.manage',
            'finance.expenses',
        ]);

        $this->financeUser = $this->makeUser('finance@perm-pay.test', 'cashier');
        $this->grantPermissions($this->financeUser, [
            'finance.gift_cards',
        ]);

        $this->reportsUser = $this->makeUser('reports@perm-pay.test', 'cashier');
        $this->grantPermissions($this->reportsUser, [
            'cash.view_daily_summary',
            'cash.reconciliation',
        ]);
    }

    protected function tearDown(): void
    {
        $router = app('router');
        $router->aliasMiddleware('permission', BypassPermissionMiddleware::class);
        parent::tearDown();
    }

    // ═══════════════════════════════════════════════════════════
    // Unauthenticated — 401 on ALL endpoints
    // ═══════════════════════════════════════════════════════════

    public function test_all_payment_endpoints_require_auth(): void
    {
        $this->getJson('/api/v2/payments')->assertUnauthorized();
        $this->postJson('/api/v2/payments', [])->assertUnauthorized();
        $this->getJson('/api/v2/payments/refunds')->assertUnauthorized();
        $this->getJson('/api/v2/cash-sessions')->assertUnauthorized();
        $this->postJson('/api/v2/cash-sessions', [])->assertUnauthorized();
        $this->getJson('/api/v2/expenses')->assertUnauthorized();
        $this->postJson('/api/v2/expenses', [])->assertUnauthorized();
        $this->getJson('/api/v2/gift-cards')->assertUnauthorized();
        $this->postJson('/api/v2/gift-cards', [])->assertUnauthorized();
        $this->getJson('/api/v2/finance/daily-summary')->assertUnauthorized();
        $this->getJson('/api/v2/finance/reconciliation')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // payments.process permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_payments_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/payments')
            ->assertForbidden();
    }

    public function test_list_payments_allowed_with_payments_process(): void
    {
        $this->withToken($this->token($this->paymentsUser))
            ->getJson('/api/v2/payments')
            ->assertOk();
    }

    public function test_owner_can_list_payments_without_explicit_permission(): void
    {
        $this->withToken($this->token($this->owner))
            ->getJson('/api/v2/payments')
            ->assertOk();
    }

    public function test_create_payment_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->postJson('/api/v2/payments', [])
            ->assertForbidden();
    }

    public function test_create_payment_allowed_with_payments_process(): void
    {
        $tx = $this->createTransaction();
        $this->withToken($this->token($this->paymentsUser))
            ->postJson('/api/v2/payments', [
                'transaction_id' => $tx->id,
                'method' => 'cash',
                'amount' => 50.00,
            ])
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // payments.refund permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_refunds_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/payments/refunds')
            ->assertForbidden();
    }

    public function test_list_refunds_allowed_with_payments_refund(): void
    {
        $this->withToken($this->token($this->paymentsUser))
            ->getJson('/api/v2/payments/refunds')
            ->assertOk();
    }

    public function test_create_refund_forbidden_without_permission(): void
    {
        $payment = $this->makePayment();

        $this->withToken($this->token($this->userNoPerms))
            ->postJson("/api/v2/payments/{$payment->id}/refund", ['amount' => 10, 'reason' => 'test'])
            ->assertForbidden();
    }

    public function test_create_refund_allowed_with_payments_refund(): void
    {
        $payment = $this->makePayment();

        $this->withToken($this->token($this->paymentsUser))
            ->postJson("/api/v2/payments/{$payment->id}/refund", ['amount' => 10, 'reason' => 'customer request'])
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // cash.view_sessions permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_cash_sessions_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/cash-sessions')
            ->assertForbidden();
    }

    public function test_list_cash_sessions_allowed_with_cash_view_sessions(): void
    {
        $this->withToken($this->token($this->cashUser))
            ->getJson('/api/v2/cash-sessions')
            ->assertOk();
    }

    public function test_show_cash_session_forbidden_without_permission(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token($this->userNoPerms))
            ->getJson("/api/v2/cash-sessions/{$session->id}")
            ->assertForbidden();
    }

    public function test_show_cash_session_allowed_with_cash_view_sessions(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token($this->cashUser))
            ->getJson("/api/v2/cash-sessions/{$session->id}")
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // cash.manage permission
    // ═══════════════════════════════════════════════════════════

    public function test_open_cash_session_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->postJson('/api/v2/cash-sessions', ['opening_float' => 100])
            ->assertForbidden();
    }

    public function test_open_cash_session_allowed_with_cash_manage(): void
    {
        $this->withToken($this->token($this->cashUser))
            ->postJson('/api/v2/cash-sessions', ['opening_float' => 200])
            ->assertCreated();
    }

    public function test_close_cash_session_forbidden_without_permission(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token($this->userNoPerms))
            ->putJson("/api/v2/cash-sessions/{$session->id}/close", ['actual_cash' => 200])
            ->assertForbidden();
    }

    public function test_close_cash_session_allowed_with_cash_manage(): void
    {
        $session = $this->makeOpenSessionFor($this->cashUser);
        $this->withToken($this->token($this->cashUser))
            ->putJson("/api/v2/cash-sessions/{$session->id}/close", ['actual_cash' => 200])
            ->assertOk();
    }

    public function test_create_cash_event_forbidden_without_permission(): void
    {
        $session = $this->makeOpenSession();
        $this->withToken($this->token($this->userNoPerms))
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'cash_in',
                'amount' => 50,
                'reason' => 'tips',
            ])
            ->assertForbidden();
    }

    public function test_create_cash_event_allowed_with_cash_manage(): void
    {
        $session = $this->makeOpenSessionFor($this->cashUser);
        $this->withToken($this->token($this->cashUser))
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'cash_in',
                'amount' => 50,
                'reason' => 'tips',
            ])
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // finance.expenses permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_expenses_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/expenses')
            ->assertForbidden();
    }

    public function test_list_expenses_allowed_with_finance_expenses(): void
    {
        $this->withToken($this->token($this->cashUser))
            ->getJson('/api/v2/expenses')
            ->assertOk();
    }

    public function test_create_expense_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->postJson('/api/v2/expenses', [])
            ->assertForbidden();
    }

    public function test_create_expense_allowed_with_finance_expenses(): void
    {
        $this->withToken($this->token($this->cashUser))
            ->postJson('/api/v2/expenses', [
                'amount' => 25,
                'category' => 'supplies',
                'description' => 'Test',
            ])
            ->assertCreated();
    }

    public function test_update_expense_forbidden_without_permission(): void
    {
        $expense = $this->makeExpense();
        $this->withToken($this->token($this->userNoPerms))
            ->putJson("/api/v2/expenses/{$expense->id}", ['amount' => 30])
            ->assertForbidden();
    }

    public function test_update_expense_allowed_with_finance_expenses(): void
    {
        $expense = $this->makeExpense();
        $this->withToken($this->token($this->cashUser))
            ->putJson("/api/v2/expenses/{$expense->id}", ['amount' => 30])
            ->assertOk();
    }

    public function test_delete_expense_forbidden_without_permission(): void
    {
        $expense = $this->makeExpense();
        $this->withToken($this->token($this->userNoPerms))
            ->deleteJson("/api/v2/expenses/{$expense->id}")
            ->assertForbidden();
    }

    public function test_delete_expense_allowed_with_finance_expenses(): void
    {
        $expense = $this->makeExpense();
        $this->withToken($this->token($this->cashUser))
            ->deleteJson("/api/v2/expenses/{$expense->id}")
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // finance.gift_cards permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_gift_cards_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/gift-cards')
            ->assertForbidden();
    }

    public function test_list_gift_cards_allowed_with_finance_gift_cards(): void
    {
        $this->withToken($this->token($this->financeUser))
            ->getJson('/api/v2/gift-cards')
            ->assertOk();
    }

    public function test_issue_gift_card_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->postJson('/api/v2/gift-cards', ['amount' => 100])
            ->assertForbidden();
    }

    public function test_issue_gift_card_allowed_with_finance_gift_cards(): void
    {
        $this->withToken($this->token($this->financeUser))
            ->postJson('/api/v2/gift-cards', ['amount' => 100])
            ->assertCreated();
    }

    public function test_check_gift_card_balance_forbidden_without_permission(): void
    {
        $this->makeGiftCard('GC-PERM-BAL');
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/gift-cards/GC-PERM-BAL/balance')
            ->assertForbidden();
    }

    public function test_check_gift_card_balance_allowed_with_finance_gift_cards(): void
    {
        $this->makeGiftCard('GC-PERM-BAL2');
        $this->withToken($this->token($this->financeUser))
            ->getJson('/api/v2/gift-cards/GC-PERM-BAL2/balance')
            ->assertOk();
    }

    public function test_redeem_gift_card_forbidden_without_permission(): void
    {
        $this->makeGiftCard('GC-PERM-REDEEM');
        $this->withToken($this->token($this->userNoPerms))
            ->postJson('/api/v2/gift-cards/GC-PERM-REDEEM/redeem', ['amount' => 10])
            ->assertForbidden();
    }

    public function test_redeem_gift_card_allowed_with_finance_gift_cards(): void
    {
        $this->makeGiftCard('GC-PERM-REDEEM2');
        $this->withToken($this->token($this->financeUser))
            ->postJson('/api/v2/gift-cards/GC-PERM-REDEEM2/redeem', ['amount' => 10])
            ->assertOk();
    }

    public function test_deactivate_gift_card_forbidden_without_permission(): void
    {
        $this->makeGiftCard('GC-PERM-DEACT');
        $this->withToken($this->token($this->userNoPerms))
            ->putJson('/api/v2/gift-cards/GC-PERM-DEACT/deactivate')
            ->assertForbidden();
    }

    public function test_deactivate_gift_card_allowed_with_finance_gift_cards(): void
    {
        $this->makeGiftCard('GC-PERM-DEACT2');
        $this->withToken($this->token($this->financeUser))
            ->putJson('/api/v2/gift-cards/GC-PERM-DEACT2/deactivate')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // cash.view_daily_summary & cash.reconciliation
    // ═══════════════════════════════════════════════════════════

    public function test_daily_summary_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/finance/daily-summary')
            ->assertForbidden();
    }

    public function test_daily_summary_allowed_with_view_daily_summary(): void
    {
        $this->withToken($this->token($this->reportsUser))
            ->getJson('/api/v2/finance/daily-summary')
            ->assertOk();
    }

    public function test_reconciliation_forbidden_without_permission(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/finance/reconciliation')
            ->assertForbidden();
    }

    public function test_reconciliation_allowed_with_cash_reconciliation(): void
    {
        $this->withToken($this->token($this->reportsUser))
            ->getJson('/api/v2/finance/reconciliation')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // Owner bypasses all checks
    // ═══════════════════════════════════════════════════════════

    public function test_owner_can_access_all_payment_endpoints(): void
    {
        $token = $this->token($this->owner);

        $this->withToken($token)->getJson('/api/v2/payments')->assertOk();
        $this->withToken($token)->getJson('/api/v2/payments/refunds')->assertOk();
        $this->withToken($token)->getJson('/api/v2/cash-sessions')->assertOk();
        $this->withToken($token)->getJson('/api/v2/expenses')->assertOk();
        $this->withToken($token)->getJson('/api/v2/gift-cards')->assertOk();
        $this->withToken($token)->getJson('/api/v2/finance/daily-summary')->assertOk();
        $this->withToken($token)->getJson('/api/v2/finance/reconciliation')->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function token(User $user): string
    {
        return $user->createToken('perm-test')->plainTextToken;
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function grantPermissions(User $user, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        $roleName = 'perm_pay_' . substr(md5(implode(',', $permissionNames)), 0, 8);
        $role = Role::firstOrCreate(
            ['name' => $roleName, 'store_id' => $this->store->id],
            ['display_name' => 'Auto Role', 'guard_name' => 'staff', 'is_predefined' => false],
        );
        $role->permissions()->syncWithoutDetaching($permissionIds);

        DB::table('model_has_roles')->updateOrInsert([
            'role_id' => $role->id,
            'model_id' => $user->id,
            'model_type' => get_class($user),
        ]);
    }

    private function makeOpenSession(): CashSession
    {
        return CashSession::create([
            'store_id' => $this->store->id,
            'opened_by' => $this->owner->id,
            'opening_float' => 200,
            'expected_cash' => 200,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    private function makeOpenSessionFor(User $user): CashSession
    {
        return CashSession::create([
            'store_id' => $this->store->id,
            'opened_by' => $user->id,
            'opening_float' => 200,
            'expected_cash' => 200,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    private function makeGiftCard(string $code): GiftCard
    {
        return GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => $code,
            'barcode' => $code,
            'initial_amount' => 100,
            'balance' => 100,
            'status' => GiftCardStatus::Active,
            'issued_by' => $this->owner->id,
            'issued_at_store' => $this->store->id,
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    }

    private function createExpenseAsOwner(): mixed
    {
        return $this->makeExpense()->id;
    }

    private function makeExpense(): Expense
    {
        return Expense::create([
            'store_id' => $this->store->id,
            'amount' => 20.00,
            'category' => 'supplies',
            'description' => 'Perm test expense',
            'recorded_by' => $this->owner->id,
            'expense_date' => now()->toDateString(),
        ]);
    }

    private function makePayment(): Payment
    {
        $tx = $this->createTransaction();
        return Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => 50.00,
            'status' => 'completed',
            'created_at' => now(),
        ]);
    }

    private function createTransaction(): Transaction
    {
        $session = PosSession::create([
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
            'pos_session_id' => $session->id,
            'cashier_id' => $this->owner->id,
            'transaction_number' => 'TXN-PERM-' . uniqid(),
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50.00,
        ]);
    }
}
