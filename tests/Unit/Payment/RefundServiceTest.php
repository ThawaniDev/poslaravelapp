<?php

namespace Tests\Unit\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\Payment\Services\RefundService;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for RefundService.
 *
 * Covers: list per payment, list for store (filters), create (partial, full,
 * over-limit), status transitions on payment, find, cross-store scoping.
 */
class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $service;
    private User $user;
    private Store $store;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RefundService::class);

        $this->org = Organization::create([
            'name' => 'Refund Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Refund Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@refund.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    // ─── List Refunds for a Payment ───────────────────────────

    public function test_list_for_payment_returns_matching_refunds(): void
    {
        $payment = $this->createPayment(100.00);
        $this->seedRefund($payment->id, 30.00);
        $this->seedRefund($payment->id, 20.00);

        // Refund for another payment — should NOT appear
        $otherPayment = $this->createPayment(50.00);
        $this->seedRefund($otherPayment->id, 10.00);

        $result = $this->service->listForPayment($payment->id);

        $this->assertCount(2, $result->items());
        foreach ($result->items() as $refund) {
            $this->assertEquals($payment->id, $refund->payment_id);
        }
    }

    public function test_list_for_payment_returns_empty_when_no_refunds(): void
    {
        $payment = $this->createPayment(50.00);

        $result = $this->service->listForPayment($payment->id);

        $this->assertCount(0, $result->items());
    }

    public function test_list_for_payment_paginates(): void
    {
        $payment = $this->createPayment(300.00);
        for ($i = 0; $i < 5; $i++) {
            $this->seedRefund($payment->id, 10.00);
        }

        $result = $this->service->listForPayment($payment->id, perPage: 3);

        $this->assertCount(3, $result->items());
        $this->assertEquals(5, $result->total());
    }

    // ─── List Refunds for a Store ─────────────────────────────

    public function test_list_for_store_returns_store_refunds(): void
    {
        $payment = $this->createPayment(100.00);
        $this->seedRefund($payment->id, 25.00);

        $result = $this->service->listForStore($this->store->id);

        $this->assertCount(1, $result->items());
    }

    public function test_list_for_store_excludes_other_stores(): void
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
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@refund.test',
            'password_hash' => bcrypt('x'),
            'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $otherPayment = $this->createPaymentForStore($otherStore, $otherUser, $otherOrg, 200.00);
        $this->seedRefund($otherPayment->id, 50.00);

        $ownPayment = $this->createPayment(100.00);
        $this->seedRefund($ownPayment->id, 20.00);

        $result = $this->service->listForStore($this->store->id);

        $this->assertCount(1, $result->items());
    }

    public function test_list_for_store_filters_by_status(): void
    {
        $payment = $this->createPayment(100.00);
        $this->seedRefund($payment->id, 10.00, 'completed');
        $this->seedRefund($payment->id, 10.00, 'pending');

        $result = $this->service->listForStore($this->store->id, ['status' => 'completed']);

        $this->assertCount(1, $result->items());
        $this->assertEquals('completed', $result->items()[0]->status->value);
    }

    public function test_list_for_store_filters_by_method(): void
    {
        $payment = $this->createPayment(100.00);
        $this->seedRefund($payment->id, 10.00, 'completed', 'cash');
        $this->seedRefund($payment->id, 10.00, 'completed', 'card');

        $result = $this->service->listForStore($this->store->id, ['method' => 'cash']);

        $this->assertCount(1, $result->items());
        $this->assertEquals('cash', $result->items()[0]->method->value);
    }

    public function test_list_for_store_filters_by_date_range(): void
    {
        $payment = $this->createPayment(100.00);

        // Old refund
        $this->seedRefund($payment->id, 10.00, 'completed', 'cash', now()->subDays(10));
        // Today's refund
        $this->seedRefund($payment->id, 20.00, 'completed', 'cash', now());

        $result = $this->service->listForStore($this->store->id, [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->assertCount(1, $result->items());
        $this->assertEquals(20.00, (float) $result->items()[0]->amount);
    }

    // ─── Create Refund ────────────────────────────────────────

    public function test_create_partial_refund_updates_payment_to_partial_refund_status(): void
    {
        $payment = $this->createPayment(100.00);

        $refund = $this->service->create($payment, [
            'amount' => 40.00,
            'reason' => 'Customer not satisfied',
        ], $this->user);

        $this->assertInstanceOf(Refund::class, $refund);
        $this->assertEquals(40.00, (float) $refund->amount);

        $payment->refresh();
        $this->assertEquals('partial_refund', $payment->status);
    }

    public function test_create_full_refund_updates_payment_to_refunded_status(): void
    {
        $payment = $this->createPayment(100.00);

        $this->service->create($payment, ['amount' => 100.00], $this->user);

        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
    }

    public function test_two_partial_refunds_summing_to_full_marks_as_refunded(): void
    {
        $payment = $this->createPayment(100.00);

        $this->service->create($payment, ['amount' => 60.00], $this->user);
        $payment->refresh();
        $this->assertEquals('partial_refund', $payment->status);

        $this->service->create($payment, ['amount' => 40.00], $this->user);
        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
    }

    public function test_create_refund_throws_when_exceeding_refundable_amount(): void
    {
        $payment = $this->createPayment(50.00);

        $this->expectException(\RuntimeException::class);
        $this->service->create($payment, ['amount' => 51.00], $this->user);
    }

    public function test_create_refund_throws_after_full_refund(): void
    {
        $payment = $this->createPayment(50.00);
        $this->service->create($payment, ['amount' => 50.00], $this->user);

        $this->expectException(\RuntimeException::class);
        $this->service->create($payment, ['amount' => 1.00], $this->user);
    }

    public function test_create_refund_uses_payment_method_as_default(): void
    {
        $payment = $this->createPayment(100.00, 'card');

        $refund = $this->service->create($payment, ['amount' => 30.00], $this->user);

        $this->assertEquals('card', $refund->method instanceof \UnitEnum ? $refund->method->value : $refund->method);
    }

    public function test_create_refund_allows_override_method(): void
    {
        $payment = $this->createPayment(100.00, 'card');

        $refund = $this->service->create($payment, [
            'amount' => 30.00,
            'method' => 'cash',
        ], $this->user);

        $this->assertEquals('cash', $refund->method instanceof \UnitEnum ? $refund->method->value : $refund->method);
    }

    public function test_create_refund_records_processed_by(): void
    {
        $payment = $this->createPayment(50.00);

        $refund = $this->service->create($payment, ['amount' => 10.00], $this->user);

        $this->assertEquals($this->user->id, $refund->processed_by);
    }

    public function test_create_refund_saves_reference_number(): void
    {
        $payment = $this->createPayment(50.00);

        $refund = $this->service->create($payment, [
            'amount' => 10.00,
            'reference_number' => 'REF-001',
        ], $this->user);

        $this->assertEquals('REF-001', $refund->reference_number);
    }

    // ─── Find ─────────────────────────────────────────────────

    public function test_find_returns_refund_by_id(): void
    {
        $payment = $this->createPayment(50.00);
        $this->seedRefund($payment->id, 10.00);

        $refund = Refund::latest()->first();
        $found = $this->service->find($refund->id);

        $this->assertEquals($refund->id, $found->id);
    }

    public function test_find_throws_for_missing_id(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->find('00000000-0000-0000-0000-000000000099');
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createPayment(float $amount = 50.00, string $method = 'cash'): Payment
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100.00,
            'transaction_count' => 0,
            'opened_at' => now(),
        ]);

        $tx = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-' . uniqid(),
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
        ]);

        return Payment::create([
            'transaction_id' => $tx->id,
            'method' => $method,
            'amount' => $amount,
            'status' => 'completed',
        ]);
    }

    private function createPaymentForStore(Store $store, User $user, Organization $org, float $amount): Payment
    {
        $session = PosSession::create([
            'store_id' => $store->id,
            'cashier_id' => $user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100,
            'transaction_count' => 0,
            'opened_at' => now(),
        ]);

        $tx = Transaction::create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $user->id,
            'transaction_number' => 'TXN-' . uniqid(),
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
        ]);

        return Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => $amount,
            'status' => 'completed',
        ]);
    }

    private function seedRefund(
        string $paymentId,
        float $amount,
        string $status = 'completed',
        string $method = 'cash',
        ?\DateTimeInterface $createdAt = null,
    ): void {
        DB::table('refunds')->insert([
            'id' => Str::uuid()->toString(),
            'return_id' => Str::uuid()->toString(),
            'payment_id' => $paymentId,
            'method' => $method,
            'amount' => $amount,
            'status' => $status,
            'processed_by' => $this->user->id,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
