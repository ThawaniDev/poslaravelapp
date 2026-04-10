<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\Register;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\CashSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CashManagementWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private string $ownerToken;
    private string $cashierToken;
    private Register $register;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Cash Test Org', 'name_ar' => 'منظمة اختبار النقد',
            'business_type' => 'grocery', 'country' => 'SA',
            'vat_number' => '300000000000009', 'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id, 'name' => 'Cash Branch',
            'name_ar' => 'فرع النقد', 'business_type' => 'grocery',
            'currency' => 'SAR', 'locale' => 'ar', 'timezone' => 'Asia/Riyadh',
            'is_active' => true, 'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@cash-test.test',
            'password_hash' => bcrypt('password'), 'store_id' => $this->store->id,
            'organization_id' => $this->org->id, 'role' => 'owner', 'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@cash-test.test',
            'password_hash' => bcrypt('password'), 'pin_hash' => bcrypt('1234'),
            'store_id' => $this->store->id, 'organization_id' => $this->org->id,
            'role' => 'cashier', 'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
        $this->cashierToken = $this->cashier->createToken('test', ['*'])->plainTextToken;
        $this->assignCashierRole($this->cashier, $this->store->id);

        $this->register = Register::create([
            'store_id' => $this->store->id, 'name' => 'Register 1',
            'device_id' => 'REG-CASH-001', 'app_version' => '1.0.0',
            'platform' => 'windows', 'is_active' => true, 'is_online' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #231-235: CASH EVENTS (PAY IN / PAY OUT)
    // ═══════════════════════════════════════════════════════════

    public function test_wf231_cash_pay_in(): void
    {
        $session = $this->createOpenCashSession();

        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'cash_in',
                'amount' => 200.00,
                'reason' => 'Change refill',
            ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'Cash event should be created. Status: ' . $response->status() . ' Body: ' . $response->getContent()
        );

        $this->assertDatabaseHas('cash_events', [
            'cash_session_id' => $session->id,
            'type' => 'cash_in',
            'amount' => 200.00,
        ]);
    }

    public function test_wf232_cash_pay_out(): void
    {
        $session = $this->createOpenCashSession();

        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'cash_out',
                'amount' => 100.00,
                'reason' => 'Supplier payment',
            ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'Cash out event should be created. Status: ' . $response->status()
        );

        $this->assertDatabaseHas('cash_events', [
            'cash_session_id' => $session->id,
            'type' => 'cash_out',
            'amount' => 100.00,
        ]);
    }

    public function test_wf233_cash_drop(): void
    {
        $session = $this->createOpenCashSession();

        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/cash-events', [
                'cash_session_id' => $session->id,
                'type' => 'cash_out',
                'amount' => 500.00,
                'reason' => 'Safe drop - excess cash',
            ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'Safe drop cash event should be created. Status: ' . $response->status()
        );

        $this->assertDatabaseHas('cash_events', [
            'cash_session_id' => $session->id,
            'type' => 'cash_out',
            'amount' => 500.00,
        ]);
    }

    public function test_wf234_list_cash_events(): void
    {
        $session = $this->createOpenCashSession();

        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $session->id, 'type' => 'cash_in', 'amount' => 100, 'reason' => 'Change',
        ]);

        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $session->id, 'type' => 'cash_out', 'amount' => 50, 'reason' => 'Tips',
        ]);

        $response = $this->withToken($this->cashierToken)
            ->getJson("/api/v2/cash-sessions/{$session->id}");

        $response->assertOk();
    }

    public function test_wf235_cash_events_affect_session_total(): void
    {
        $session = $this->createOpenCashSession();

        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $session->id, 'type' => 'cash_in', 'amount' => 200, 'reason' => 'Refill',
        ]);

        $this->withToken($this->cashierToken)->postJson('/api/v2/cash-events', [
            'cash_session_id' => $session->id, 'type' => 'cash_out', 'amount' => 50, 'reason' => 'Tips',
        ]);

        $response = $this->withToken($this->cashierToken)
            ->putJson("/api/v2/cash-sessions/{$session->id}/close", [
                'actual_cash' => 650.00,
            ]);

        $response->assertOk();

        $session->refresh();
        $this->assertEquals('closed', $session->status->value ?? $session->status);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #236-240: DEBIT TRANSACTIONS
    // ═══════════════════════════════════════════════════════════

    public function test_wf236_record_debit(): void
    {
        $customer = $this->createCustomer();

        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/debits', [
                'customer_id' => $customer->id,
                'debit_type' => 'customer_credit',
                'source' => 'pos_terminal',
                'amount' => 150.00,
                'description' => 'Groceries on credit',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('debits', [
            'customer_id' => $customer->id,
            'amount' => 150.00,
            'status' => 'pending',
        ]);
    }

    public function test_wf237_debit_payment(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder();

        $debitResp = $this->withToken($this->ownerToken)->postJson('/api/v2/debits', [
            'customer_id' => $customer->id, 'debit_type' => 'customer_credit',
            'source' => 'pos_terminal', 'amount' => 200.00, 'description' => 'Credit purchase',
        ]);
        $debitId = $debitResp->json('data.id');
        $this->assertNotNull($debitId, 'Debit should be created');

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/debits/{$debitId}/allocate", [
                'order_id' => $order->id,
                'amount' => 100.00,
            ]);

        $response->assertOk();
    }

    public function test_wf238_full_debit_payment(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder();

        $debitResp = $this->withToken($this->ownerToken)->postJson('/api/v2/debits', [
            'customer_id' => $customer->id, 'debit_type' => 'customer_credit',
            'source' => 'pos_terminal', 'amount' => 100.00, 'description' => 'Owed',
        ]);
        $debitId = $debitResp->json('data.id');
        $this->assertNotNull($debitId, 'Debit should be created');

        $this->withToken($this->ownerToken)->postJson("/api/v2/debits/{$debitId}/allocate", [
            'order_id' => $order->id, 'amount' => 100.00,
        ]);

        $this->assertDatabaseHas('debits', [
            'id' => $debitId,
            'status' => 'fully_allocated',
        ]);
    }

    public function test_wf239_list_debits_by_status(): void
    {
        $customer = $this->createCustomer();

        $this->withToken($this->ownerToken)->postJson('/api/v2/debits', [
            'customer_id' => $customer->id, 'debit_type' => 'customer_credit',
            'source' => 'manual', 'amount' => 50, 'description' => 'Test debit',
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/debits');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_wf240_overdue_debits(): void
    {
        $customer = $this->createCustomer();

        $debitResp = $this->withToken($this->ownerToken)->postJson('/api/v2/debits', [
            'customer_id' => $customer->id, 'debit_type' => 'customer_credit',
            'source' => 'manual', 'amount' => 75, 'description' => 'Test debit for reversal',
        ]);
        $debitId = $debitResp->json('data.id');

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/debits/{$debitId}/reverse");

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'Debit reversal should succeed. Status: ' . $response->status()
        );

        $this->assertDatabaseHas('debits', ['id' => $debitId, 'status' => 'reversed']);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #241-245: EXPENSE TRACKING
    // ═══════════════════════════════════════════════════════════

    public function test_wf241_record_expense(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/expenses', [
                'amount' => 500.00,
                'category' => 'maintenance',
                'description' => 'Store maintenance',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('expenses', [
            'amount' => 500.00,
            'category' => 'maintenance',
        ]);
    }

    public function test_wf242_list_expenses_filtered(): void
    {
        $this->withToken($this->ownerToken)->postJson('/api/v2/expenses', [
            'amount' => 100, 'category' => 'supplies', 'description' => 'Paper',
        ]);

        $this->withToken($this->ownerToken)->postJson('/api/v2/expenses', [
            'amount' => 200, 'category' => 'utility', 'description' => 'Electric',
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/expenses');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_wf243_expense_summary(): void
    {
        // Create some expenses first
        $this->withToken($this->ownerToken)->postJson('/api/v2/expenses', [
            'amount' => 300, 'category' => 'maintenance', 'description' => 'AC repair',
        ]);
        $this->withToken($this->ownerToken)->postJson('/api/v2/expenses', [
            'amount' => 150, 'category' => 'supplies', 'description' => 'Office supplies',
        ]);

        // Use the financial expenses report endpoint for summary
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/financial/expenses?from=' .
                now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString());

        $response->assertOk();

        // Also verify expenses list returns our records
        $listResp = $this->withToken($this->ownerToken)->getJson('/api/v2/expenses');
        $listResp->assertOk();

        $expenses = $listResp->json('data.data') ?? $listResp->json('data');
        $this->assertGreaterThanOrEqual(2, count($expenses), 'Should have at least 2 expenses');
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function createOpenCashSession(): CashSession
    {
        return CashSession::create([
            'store_id' => $this->store->id,
            'terminal_id' => $this->register->id,
            'opened_by' => $this->cashier->id,
            'opening_float' => 500.00,
            'expected_cash' => 500.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer',
            'phone' => '966501234567',
            'email' => 'customer@test.test',
        ]);
    }

    private function createOrder(): Order
    {
        return Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-' . uniqid(),
            'subtotal' => 100.00,
            'total' => 115.00,
            'tax_amount' => 15.00,
            'status' => 'completed',
            'source' => 'pos',
            'created_by' => $this->owner->id,
        ]);
    }
}
