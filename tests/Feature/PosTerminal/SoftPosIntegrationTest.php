<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Payment\Models\Payment;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end tests for the EdfaPay SoftPOS integration.
 *
 * Covers:
 *  1. Terminal CRUD — edfapay_token stored, returned, updateable, clearable
 *  2. SoftPOS sale — approval_code/rrn/card_scheme/masked_card normalised
 *  3. Split payment (cash + soft_pos)
 *  4. Session card-sales counter incremented for soft_pos
 *  5. SoftPosTransaction recorded and subscription threshold tracking
 *  6. Refund of a soft_pos sale decrements session counter
 *  7. Invalid/missing payment payload validation
 *  8. Security — edfapay_token not leaked to other stores
 */
class SoftPosIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Organization $org;
    private Store $store;
    private Register $register;
    private string $token;

    // ─── Fixture data ─────────────────────────────────────────────

    private const EDFAPAY_TOKEN  = 'D08BE4C0FE041A155F0028C0FCD042087771DA505D54087EFC3A0FC1183213D6';
    private const APPROVAL_CODE  = '963680';
    private const RRN            = '181418400212';
    private const CARD_SCHEME    = 'mada';
    private const MASKED_CARD    = '5069 68** **** 0286';
    private const TXN_ID         = '8d174e11-2a5a-48c6-84c1-d1cb8871ce7c';

    // ─── setUp ────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'          => 'SoftPOS Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        StoreSettings::create([
            'store_id'                       => $this->store->id,
            'tax_rate'                       => 15,
            'enable_refunds'                 => true,
            'enable_exchanges'               => true,
            'return_without_receipt_policy'  => 'deny',
            'held_cart_expiry_hours'         => 24,
        ]);

        $this->register = Register::create([
            'store_id'        => $this->store->id,
            'name'            => 'SoftPOS Counter',
            'device_id'       => 'sp-dev-001',
            'platform'        => 'android',
            'app_version'     => '1.0.0',
            'is_active'       => true,
            'softpos_enabled' => true,
            'softpos_status'  => 'active',
            'nfc_capable'     => true,
        ]);

        $this->cashier = User::create([
            'name'          => 'Cashier',
            'email'         => 'cashier-sp@test.com',
            'password_hash' => bcrypt('password'),
            'store_id'      => $this->store->id,
            'organization_id' => $this->org->id,
            'role'          => 'cashier',
            'is_active'     => true,
        ]);

        $this->token = $this->cashier->createToken('test', ['*'])->plainTextToken;
    }




    /** @test */
    public function test_provider_can_set_edfapay_token_on_terminal_update(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/terminals/{$this->register->id}", [
                'name'          => 'SoftPOS Counter',
                'edfapay_token' => self::EDFAPAY_TOKEN,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.edfapay_token', self::EDFAPAY_TOKEN);

        // edfapay_token is encrypted at rest — read through the model to decrypt
        $this->assertSame(self::EDFAPAY_TOKEN, $this->register->fresh()->edfapay_token);
    }

    /** @test */
    public function test_provider_terminal_show_returns_edfapay_token(): void
    {
        $this->register->update(['edfapay_token' => self::EDFAPAY_TOKEN]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/pos/terminals/{$this->register->id}");

        $response->assertOk()
            ->assertJsonPath('data.edfapay_token', self::EDFAPAY_TOKEN);
    }

    /** @test */
    public function test_edfapay_token_can_be_cleared_by_passing_null(): void
    {
        $this->register->update(['edfapay_token' => self::EDFAPAY_TOKEN]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/terminals/{$this->register->id}", [
                'edfapay_token' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.edfapay_token', null);

        $this->assertDatabaseHas('registers', [
            'id'            => $this->register->id,
            'edfapay_token' => null,
        ]);
    }

    /** @test */
    public function test_edfapay_token_max_length_validation(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/terminals/{$this->register->id}", [
                'edfapay_token' => str_repeat('X', 501),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edfapay_token']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  2. SoftPOS Sale — EdfaPay field normalisation
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_soft_pos_sale_stores_edfapay_fields_normalised(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'register_id'  => $this->register->id,
                'type'         => 'sale',
                'subtotal'     => 100.00,
                'tax_amount'   => 15.00,
                'total_amount' => 115.00,
                'items'        => [[
                    'product_name' => 'Coffee Bag',
                    'quantity'     => 1,
                    'unit_price'   => 100.00,
                    'line_total'   => 115.00,
                ]],
                'payments'     => [[
                    'method'             => 'soft_pos',
                    'amount'             => 115.00,
                    'approval_code'      => self::APPROVAL_CODE,
                    'rrn'                => self::RRN,
                    'card_scheme'        => self::CARD_SCHEME,
                    'masked_card'        => self::MASKED_CARD,
                    'card_transaction_id' => self::TXN_ID,
                ]],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'completed');

        $transactionId = $response->json('data.id');

        // Verify Payment record has normalised columns
        $payment = Payment::where('transaction_id', $transactionId)->first();

        $this->assertNotNull($payment, 'Payment record was not created');
        $this->assertEquals('soft_pos',           $payment->method->value);
        $this->assertEquals(115.00,               (float) $payment->amount);
        $this->assertEquals(self::APPROVAL_CODE,  $payment->card_auth_code);
        $this->assertEquals(self::RRN,            $payment->card_reference);
        $this->assertEquals(self::CARD_SCHEME,    $payment->card_brand);
        // card_last_four stores the last 4 digits extracted from the masked card '5069 68** **** 0286'
        $this->assertEquals('0286',                $payment->card_last_four);
    }

    /** @test */
    public function test_soft_pos_sale_also_accepts_card_field_names_directly(): void
    {
        // Flutter may send the already-normalised names — must work too
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 50.00,
                'total_amount' => 50.00,
                'items'        => [[
                    'product_name' => 'Item',
                    'quantity'     => 1,
                    'unit_price'   => 50.00,
                    'line_total'   => 50.00,
                ]],
                'payments' => [[
                    'method'        => 'soft_pos',
                    'amount'        => 50.00,
                    'card_auth_code' => 'ABC123',
                    'card_reference' => 'RRN999',
                    'card_brand'    => 'visa',
                    'card_last_four' => '4242',
                ]],
            ]);

        $response->assertStatus(201);

        $payment = Payment::where('transaction_id', $response->json('data.id'))->first();

        $this->assertEquals('ABC123', $payment->card_auth_code);
        $this->assertEquals('RRN999', $payment->card_reference);
        $this->assertEquals('visa',   $payment->card_brand);
        $this->assertEquals('4242',   $payment->card_last_four);
    }

    /** @test */
    public function test_soft_pos_approval_code_takes_priority_over_card_auth_code(): void
    {
        // When both are sent, approval_code (EdfaPay native name) wins
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 20.00,
                'total_amount' => 20.00,
                'items'        => [[
                    'product_name' => 'Widget',
                    'quantity'     => 1,
                    'unit_price'   => 20.00,
                    'line_total'   => 20.00,
                ]],
                'payments' => [[
                    'method'        => 'soft_pos',
                    'amount'        => 20.00,
                    'approval_code' => 'PRIORITY_CODE',
                    'card_auth_code' => 'OLD_CODE',
                    'rrn'           => 'RRN123',
                ]],
            ]);

        $response->assertStatus(201);

        $payment = Payment::where('transaction_id', $response->json('data.id'))->first();
        $this->assertEquals('PRIORITY_CODE', $payment->card_auth_code);
    }

    // ═══════════════════════════════════════════════════════════════
    //  3. Split payment — cash + soft_pos
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_split_payment_cash_and_soft_pos(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 200.00,
                'tax_amount'   => 30.00,
                'total_amount' => 230.00,
                'items'        => [[
                    'product_name' => 'Order',
                    'quantity'     => 1,
                    'unit_price'   => 200.00,
                    'line_total'   => 230.00,
                ]],
                'payments' => [
                    [
                        'method'        => 'cash',
                        'amount'        => 100.00,
                        'cash_tendered' => 100.00,
                        'change_given'  => 0,
                    ],
                    [
                        'method'        => 'soft_pos',
                        'amount'        => 130.00,
                        'approval_code' => self::APPROVAL_CODE,
                        'rrn'           => self::RRN,
                        'card_scheme'   => 'visa',
                        'masked_card'   => '4111 11** **** 1111',
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $transactionId = $response->json('data.id');

        $payments = Payment::where('transaction_id', $transactionId)->get();
        $this->assertCount(2, $payments);

        $cashPayment    = $payments->where('method', 'cash')->first();
        $softPosPayment = $payments->where('method', 'soft_pos')->first();

        $this->assertNotNull($cashPayment);
        $this->assertNotNull($softPosPayment);
        $this->assertEquals(100.00, (float) $cashPayment->amount);
        $this->assertEquals(130.00, (float) $softPosPayment->amount);
        $this->assertEquals(self::APPROVAL_CODE, $softPosPayment->card_auth_code);
        $this->assertEquals(self::RRN,           $softPosPayment->card_reference);
    }

    // ═══════════════════════════════════════════════════════════════
    //  4. Session sales counters — soft_pos counts as card_sales
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_soft_pos_payment_increments_session_total_card_sales(): void
    {
        // Create an open session
        $session = \App\Domain\PosTerminal\Models\PosSession::create([
            'store_id'           => $this->store->id,
            'register_id'        => $this->register->id,
            'cashier_id'         => $this->cashier->id,
            'status'             => 'open',
            'opening_cash'       => 0,
            'total_cash_sales'   => 0,
            'total_card_sales'   => 0,
            'total_other_sales'  => 0,
            'total_refunds'      => 0,
            'total_voids'        => 0,
            'transaction_count'  => 0,
            'z_report_printed'   => false,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'register_id'    => $this->register->id,
                'pos_session_id' => $session->id,
                'type'           => 'sale',
                'subtotal'       => 80.00,
                'total_amount'   => 80.00,
                'items'          => [[
                    'product_name' => 'Tea',
                    'quantity'     => 1,
                    'unit_price'   => 80.00,
                    'line_total'   => 80.00,
                ]],
                'payments' => [[
                    'method'  => 'soft_pos',
                    'amount'  => 80.00,
                    'rrn'     => 'RRN-SESSION-TEST',
                ]],
            ]);

        $response->assertStatus(201);

        $session->refresh();

        $this->assertEquals(80.00, (float) $session->total_card_sales,
            'soft_pos payment must be counted in total_card_sales');
        $this->assertEquals(0.00,  (float) $session->total_cash_sales);
        $this->assertEquals(0.00,  (float) $session->total_other_sales);
        $this->assertEquals(1,     $session->transaction_count);
    }

    /** @test */
    public function test_soft_pos_payment_does_not_increment_cash_or_other_sales(): void
    {
        $session = \App\Domain\PosTerminal\Models\PosSession::create([
            'store_id'          => $this->store->id,
            'register_id'       => $this->register->id,
            'cashier_id'        => $this->cashier->id,
            'status'            => 'open',
            'opening_cash'      => 500,
            'total_cash_sales'  => 200,
            'total_card_sales'  => 50,
            'total_other_sales' => 30,
            'total_refunds'     => 0,
            'total_voids'       => 0,
            'transaction_count' => 3,
            'z_report_printed'  => false,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'register_id'    => $this->register->id,
                'pos_session_id' => $session->id,
                'type'           => 'sale',
                'subtotal'       => 40.00,
                'total_amount'   => 40.00,
                'items'          => [[
                    'product_name' => 'Juice',
                    'quantity'     => 1,
                    'unit_price'   => 40.00,
                    'line_total'   => 40.00,
                ]],
                'payments' => [[
                    'method' => 'soft_pos',
                    'amount' => 40.00,
                ]],
            ])->assertStatus(201);

        $session->refresh();

        // Only card_sales should grow
        $this->assertEquals(200.00, (float) $session->total_cash_sales,  'cash unchanged');
        $this->assertEquals(90.00,  (float) $session->total_card_sales,  'soft_pos added to card');
        $this->assertEquals(30.00,  (float) $session->total_other_sales, 'other unchanged');
    }

    // ═══════════════════════════════════════════════════════════════
    //  5. SoftPosTransaction recorded for subscription threshold
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_soft_pos_sale_creates_softpos_transaction_record(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 60.00,
                'total_amount' => 60.00,
                'items'        => [[
                    'product_name' => 'Snack',
                    'quantity'     => 1,
                    'unit_price'   => 60.00,
                    'line_total'   => 60.00,
                ]],
                'payments' => [[
                    'method'        => 'soft_pos',
                    'amount'        => 60.00,
                    'approval_code' => 'APPR-001',
                    'rrn'           => 'RRN-001',
                ]],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('softpos_transactions', [
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'status'          => 'completed',
        ]);

        $record = SoftPosTransaction::where('organization_id', $this->org->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals(60.00, (float) $record->amount);
        $this->assertEquals('RRN-001', $record->transaction_ref);
    }

    /** @test */
    public function test_soft_pos_sale_increments_subscription_transaction_count(): void
    {
        $plan = SubscriptionPlan::create([
            'name'                          => 'Pro',
            'slug'                          => 'pro-test',
            'monthly_price'                 => 100.00,
            'annual_price'                  => 1000.00,
            'softpos_free_eligible'         => true,
            'softpos_free_threshold'        => 5,
            'softpos_free_threshold_period' => 'monthly',
        ]);

        $subscription = StoreSubscription::create([
            'organization_id'           => $this->org->id,
            'subscription_plan_id'      => $plan->id,
            'status'                    => 'active',
            'billing_cycle'             => 'monthly',
            'current_period_start'      => now()->startOfMonth(),
            'current_period_end'        => now()->endOfMonth(),
            'softpos_transaction_count' => 0,
            'is_softpos_free'           => false,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 50.00,
                'total_amount' => 50.00,
                'items'        => [[
                    'product_name' => 'Widget',
                    'quantity'     => 1,
                    'unit_price'   => 50.00,
                    'line_total'   => 50.00,
                ]],
                'payments' => [[
                    'method' => 'soft_pos',
                    'amount' => 50.00,
                    'rrn'    => 'RRN-COUNT-001',
                ]],
            ])->assertStatus(201);

        $subscription->refresh();
        $this->assertEquals(1, $subscription->softpos_transaction_count);
        $this->assertEquals(50.0, (float) $subscription->softpos_sales_total,
            'softpos_sales_total should accumulate the sale amount');
    }

    /** @test */
    public function test_soft_pos_threshold_activates_free_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name'                          => 'Pro-Free',
            'slug'                          => 'pro-free-test',
            'monthly_price'                 => 200.00,
            'annual_price'                  => 2000.00,
            'softpos_free_eligible'         => true,
            'softpos_free_threshold'        => 2,   // very low so test reaches it
            'softpos_free_threshold_period' => 'monthly',
        ]);

        $subscription = StoreSubscription::create([
            'organization_id'           => $this->org->id,
            'subscription_plan_id'      => $plan->id,
            'status'                    => 'active',
            'billing_cycle'             => 'monthly',
            'current_period_start'      => now()->startOfMonth(),
            'current_period_end'        => now()->endOfMonth(),
            'softpos_transaction_count' => 1,   // one away from threshold
            'is_softpos_free'           => false,
        ]);

        // One more SoftPOS sale should trigger the threshold
        $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 30.00,
                'total_amount' => 30.00,
                'items'        => [[
                    'product_name' => 'Drink',
                    'quantity'     => 1,
                    'unit_price'   => 30.00,
                    'line_total'   => 30.00,
                ]],
                'payments' => [[
                    'method' => 'soft_pos',
                    'amount' => 30.00,
                    'rrn'    => 'RRN-THRESHOLD',
                ]],
            ])->assertStatus(201);

        $subscription->refresh();

        $this->assertEquals(2,    $subscription->softpos_transaction_count);
        $this->assertEquals(30.0, (float) $subscription->softpos_sales_total,
            'softpos_sales_total should accumulate the triggering sale amount');
        $this->assertTrue($subscription->is_softpos_free,
            'Subscription should be marked free after reaching threshold');
        $this->assertEquals(200.00, (float) $subscription->original_amount,
            'original monthly price should be stored');
        $this->assertEquals('softpos_threshold_reached', $subscription->discount_reason);
    }

    /** @test */
    public function test_cash_sale_does_not_create_softpos_transaction_record(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 25.00,
                'total_amount' => 25.00,
                'items'        => [[
                    'product_name' => 'Item',
                    'quantity'     => 1,
                    'unit_price'   => 25.00,
                    'line_total'   => 25.00,
                ]],
                'payments' => [[
                    'method'        => 'cash',
                    'amount'        => 25.00,
                    'cash_tendered' => 30.00,
                    'change_given'  => 5.00,
                ]],
            ])->assertStatus(201);

        $this->assertDatabaseMissing('softpos_transactions', [
            'organization_id' => $this->org->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  6. Refund of a soft_pos sale
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_soft_pos_refund_decrements_session_card_sales(): void
    {
        $session = \App\Domain\PosTerminal\Models\PosSession::create([
            'store_id'          => $this->store->id,
            'register_id'       => $this->register->id,
            'cashier_id'        => $this->cashier->id,
            'status'            => 'open',
            'opening_cash'      => 0,
            'total_cash_sales'  => 0,
            'total_card_sales'  => 100,   // already has 100 from a previous sale
            'total_other_sales' => 0,
            'total_refunds'     => 0,
            'total_voids'       => 0,
            'transaction_count' => 1,
            'z_report_printed'  => false,
        ]);

        // Post a soft_pos refund
        $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'register_id'    => $this->register->id,
                'pos_session_id' => $session->id,
                'type'           => 'return',
                'subtotal'       => 40.00,
                'total_amount'   => 40.00,
                'items'          => [[
                    'product_name'  => 'Returned Item',
                    'quantity'      => 1,
                    'unit_price'    => 40.00,
                    'line_total'    => 40.00,
                    'is_return_item' => true,
                ]],
                'payments' => [[
                    'method' => 'soft_pos',
                    'amount' => 40.00,
                    'rrn'    => 'REFUND-RRN',
                ]],
            ])->assertStatus(201);

        $session->refresh();

        // 100 − 40 = 60
        $this->assertEquals(60.00, (float) $session->total_card_sales,
            'Soft_pos refund must decrement total_card_sales');
        $this->assertGreaterThan(0, (float) $session->total_refunds);
    }

    // ═══════════════════════════════════════════════════════════════
    //  7. Validation
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_transaction_with_no_payments_fails_validation(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 50.00,
                'total_amount' => 50.00,
                'items'        => [[
                    'product_name' => 'Item',
                    'quantity'     => 1,
                    'unit_price'   => 50.00,
                    'line_total'   => 50.00,
                ]],
                // no payments key
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payments']);
    }

    /** @test */
    public function test_transaction_with_no_items_fails_validation(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 50.00,
                'total_amount' => 50.00,
                // no items key
                'payments' => [[
                    'method' => 'soft_pos',
                    'amount' => 50.00,
                ]],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  8. Security — tenant isolation
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_cashier_cannot_read_terminal_from_another_store(): void
    {
        // Create a second org + store with its own terminal containing a token
        $org2   = Organization::create(['name' => 'Other Org', 'business_type' => 'restaurant', 'country' => 'SA']);
        $store2 = Store::create([
            'organization_id' => $org2->id,
            'name'            => 'Other Store',
            'business_type'   => 'restaurant',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $register2 = Register::create([
            'store_id'      => $store2->id,
            'name'          => 'Other Terminal',
            'device_id'     => 'other-dev-001',
            'platform'      => 'android',
            'is_active'     => true,
            'edfapay_token' => 'SECRET_TOKEN_OTHER_ORG',
        ]);

        // Cashier of store 1 tries to read terminal from store 2
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/pos/terminals/{$register2->id}");

        // Must be 404 (store isolation), NOT 200 with leaked token
        $response->assertNotFound();
    }

    /** @test */
    public function test_cashier_cannot_update_terminal_from_another_store(): void
    {
        $org2   = Organization::create(['name' => 'Org B', 'business_type' => 'grocery', 'country' => 'SA']);
        $store2 = Store::create([
            'organization_id' => $org2->id,
            'name'            => 'Store B',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $register2 = Register::create([
            'store_id'  => $store2->id,
            'name'      => 'Terminal B',
            'device_id' => 'dev-b-001',
            'platform'  => 'android',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/pos/terminals/{$register2->id}", [
                'edfapay_token' => 'INJECTED_TOKEN',
            ]);

        $response->assertNotFound();

        // Ensure the token was not written
        $this->assertDatabaseMissing('registers', [
            'id'            => $register2->id,
            'edfapay_token' => 'INJECTED_TOKEN',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  9. listActive endpoint includes softpos_enabled flag
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_list_active_registers_endpoint_accessible(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/registers');

        $response->assertOk();

        // Our setUp register is active — must appear
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->register->id, $ids->all());
    }

    // ═══════════════════════════════════════════════════════════════
    //  10. SoftPOS transaction does not fail if no subscription exists
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_soft_pos_sale_succeeds_even_when_no_subscription_exists(): void
    {
        // No StoreSubscription in DB — SoftPosService.incrementAndCheckThreshold is a no-op
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', [
                'type'         => 'sale',
                'subtotal'     => 10.00,
                'total_amount' => 10.00,
                'items'        => [[
                    'product_name' => 'Pen',
                    'quantity'     => 1,
                    'unit_price'   => 10.00,
                    'line_total'   => 10.00,
                ]],
                'payments' => [[
                    'method' => 'soft_pos',
                    'amount' => 10.00,
                    'rrn'    => 'RRN-NOSUB',
                ]],
            ]);

        // Transaction must succeed — subscription issues are non-blocking
        $response->assertStatus(201);
    }
}
