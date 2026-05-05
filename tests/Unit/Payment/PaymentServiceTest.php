<?php

namespace Tests\Unit\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\Payment\Enums\CashSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for PaymentService.
 *
 * Covers: list filters, create, find, search, pagination.
 */
class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;
    private User $user;
    private Store $store;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentService::class);

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@unit.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    // ─── List ─────────────────────────────────────────────────

    public function test_list_returns_paginator(): void
    {
        $tx = $this->createTransaction();
        Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => 100,
            'status' => 'completed',
        ]);

        $result = $this->service->list($this->store->id);

        $this->assertCount(1, $result->items());
        $this->assertEquals(1, $result->total());
    }

    public function test_list_scopes_by_store(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $otherUser = User::create([
            'name' => 'Other Cashier',
            'email' => 'other.cashier@unit.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Create payment in other store
        $otherTx = $this->createTransaction($otherStore, $otherUser);
        Payment::create([
            'transaction_id' => $otherTx->id,
            'method' => 'cash',
            'amount' => 999,
            'status' => 'completed',
        ]);

        // Our store has no payments
        $result = $this->service->list($this->store->id);
        $this->assertCount(0, $result->items());
    }

    public function test_list_filters_by_method(): void
    {
        $tx1 = $this->createTransaction();
        $tx2 = $this->createTransaction();

        Payment::create(['transaction_id' => $tx1->id, 'method' => 'cash', 'amount' => 100, 'status' => 'completed']);
        Payment::create(['transaction_id' => $tx2->id, 'method' => 'card_mada', 'amount' => 200, 'status' => 'completed']);

        $result = $this->service->list($this->store->id, ['method' => 'cash']);
        $this->assertCount(1, $result->items());
        $this->assertEquals('cash', $result->items()[0]->method->value ?? $result->items()[0]->method);
    }

    public function test_list_filters_by_status(): void
    {
        $tx = $this->createTransaction();
        Payment::create(['transaction_id' => $tx->id, 'method' => 'cash', 'amount' => 50, 'status' => 'refunded']);
        $tx2 = $this->createTransaction();
        Payment::create(['transaction_id' => $tx2->id, 'method' => 'cash', 'amount' => 75, 'status' => 'completed']);

        $result = $this->service->list($this->store->id, ['status' => 'refunded']);
        $this->assertCount(1, $result->items());
    }

    public function test_list_filters_by_date_range(): void
    {
        $tx = $this->createTransaction();
        Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => 100,
            'status' => 'completed',
            'created_at' => now()->subDays(5)->toDateTimeString(),
        ]);

        // Filter for today only — should return 0
        $result = $this->service->list($this->store->id, [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        $this->assertCount(0, $result->items());

        // Filter for past 7 days — should return 1
        $result2 = $this->service->list($this->store->id, [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        $this->assertCount(1, $result2->items());
    }

    public function test_list_searches_by_card_reference(): void
    {
        $tx = $this->createTransaction();
        Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'card_visa',
            'amount' => 120,
            'card_reference' => 'AUTH-UNIQUE-XYZ',
            'status' => 'completed',
        ]);

        $result = $this->service->list($this->store->id, ['search' => 'AUTH-UNIQUE-XYZ']);
        $this->assertCount(1, $result->items());
    }

    public function test_list_searches_by_card_last_four(): void
    {
        $tx = $this->createTransaction();
        Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'card_mada',
            'amount' => 80,
            'card_last_four' => '9876',
            'status' => 'completed',
        ]);

        $result = $this->service->list($this->store->id, ['search' => '9876']);
        $this->assertCount(1, $result->items());
    }

    public function test_list_searches_by_gift_card_code(): void
    {
        $tx = $this->createTransaction();
        Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'gift_card',
            'amount' => 50,
            'gift_card_code' => 'GC-SEARCH-CODE',
            'status' => 'completed',
        ]);

        $result = $this->service->list($this->store->id, ['search' => 'GC-SEARCH-CODE']);
        $this->assertCount(1, $result->items());
    }

    public function test_list_searches_by_nearpay_transaction_id(): void
    {
        $tx = $this->createTransaction();
        Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'card_mada',
            'amount' => 150,
            'nearpay_transaction_id' => 'NEAR-TXN-UNIQ',
            'status' => 'completed',
        ]);

        $result = $this->service->list($this->store->id, ['search' => 'NEAR-TXN-UNIQ']);
        $this->assertCount(1, $result->items());
    }

    public function test_list_returns_empty_for_no_match(): void
    {
        $result = $this->service->list($this->store->id, ['search' => 'NO-MATCH-EVER']);
        $this->assertCount(0, $result->items());
    }

    // ─── Pagination ───────────────────────────────────────────

    public function test_list_respects_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $tx = $this->createTransaction();
            Payment::create([
                'transaction_id' => $tx->id,
                'method' => 'cash',
                'amount' => 10 * ($i + 1),
                'status' => 'completed',
            ]);
        }

        $result = $this->service->list($this->store->id, [], 2);
        $this->assertCount(2, $result->items());
        $this->assertEquals(5, $result->total());
        $this->assertEquals(3, $result->lastPage());
    }

    // ─── Create ───────────────────────────────────────────────

    public function test_create_payment_returns_payment_model(): void
    {
        $tx = $this->createTransaction();
        $payment = $this->service->create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => 100.00,
            'cash_tendered' => 200.00,
            'change_given' => 100.00,
            'tip_amount' => 5.00,
        ], $this->user);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(100.00, (float) $payment->amount);
        $this->assertEquals(200.00, (float) $payment->cash_tendered);
        $this->assertEquals(100.00, (float) $payment->change_given);
        $this->assertEquals(5.00, (float) $payment->tip_amount);
    }

    public function test_create_card_payment_stores_card_details(): void
    {
        $tx = $this->createTransaction();
        $payment = $this->service->create([
            'transaction_id' => $tx->id,
            'method' => 'card_visa',
            'amount' => 250.00,
            'card_brand' => 'visa',
            'card_last_four' => '4242',
            'card_auth_code' => 'VISA-AUTH-001',
            'card_reference' => 'REF-001',
        ], $this->user);

        $this->assertEquals('visa', $payment->card_brand);
        $this->assertEquals('4242', $payment->card_last_four);
        $this->assertEquals('VISA-AUTH-001', $payment->card_auth_code);
        $this->assertEquals('REF-001', $payment->card_reference);
    }

    public function test_create_gift_card_payment_stores_code(): void
    {
        $tx = $this->createTransaction();
        $payment = $this->service->create([
            'transaction_id' => $tx->id,
            'method' => 'gift_card',
            'amount' => 75.00,
            'gift_card_code' => 'GC-STORE-001',
        ], $this->user);

        $this->assertEquals('GC-STORE-001', $payment->gift_card_code);
    }

    public function test_create_defaults_tip_to_zero(): void
    {
        $tx = $this->createTransaction();
        $payment = $this->service->create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => 50.00,
        ], $this->user);

        $this->assertEquals(0, (float) $payment->tip_amount);
    }

    // ─── Find ─────────────────────────────────────────────────

    public function test_find_returns_payment_by_id(): void
    {
        $tx = $this->createTransaction();
        $payment = Payment::create([
            'transaction_id' => $tx->id,
            'method' => 'cash',
            'amount' => 100,
            'status' => 'completed',
        ]);

        $found = $this->service->find($payment->id);
        $this->assertEquals($payment->id, $found->id);
    }

    public function test_find_throws_for_missing_payment(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->find('00000000-0000-0000-0000-000000000099');
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function createTransaction(?Store $store = null, ?User $user = null): Transaction
    {
        $store ??= $this->store;
        $user ??= $this->user;

        $session = PosSession::create([
            'store_id' => $store->id,
            'cashier_id' => $user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100.00,
            'transaction_count' => 0,
            'opened_at' => now(),
        ]);

        return Transaction::create([
            'organization_id' => $store->organization_id,
            'store_id' => $store->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $user->id,
            'transaction_number' => 'TXN-UNIT-' . uniqid(),
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50.00,
        ]);
    }
}
