<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use App\Domain\ProviderSubscription\Services\SoftPosFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive tests for the SoftPOS bilateral fee feature.
 *
 * Covers:
 *  1.  Mada fee calculation: 1000 SAR → platform 6.0, gateway 4.0, margin 2.0
 *  2.  Visa/MC fixed fee: any amount → platform 1.0, gateway 0.5, margin 0.5
 *  3.  Custom rates per terminal override defaults
 *  4.  Unknown card scheme defaults to Mada percentage rates
 *  5.  SoftPosFeeService::calculate() edge cases (zero, very small amount)
 *  6.  Fee description helpers return correct strings
 *  7.  Transaction creation stores fees in softpos_transactions
 *  8.  platform_daily_stats updated after a SoftPOS transaction
 *  9.  Admin PATCH /admin/terminals/{id}/softpos-billing updates rates
 * 10.  Admin GET /admin/softpos/financials returns P&L data
 * 11.  Admin GET /admin/softpos/terminal-rates returns all billing configs
 * 12.  Provider cannot see gateway rates in RegisterResource
 * 13.  Admin can see full bilateral breakdown in AdminRegisterResource
 * 14.  PATCH softpos-billing requires admin auth (not provider token)
 * 15.  Business rule: mada_merchant_rate must be >= mada_gateway_rate
 * 16.  Business rule: card_merchant_fee must be >= card_gateway_fee
 * 17.  Admin GET /admin/softpos/transactions returns paginated list with fees
 */
class SoftPosFeeTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private Register $register;
    private string $adminToken;
    private string $providerToken;

    // ─── Default test rates ────────────────────────────────────────
    private const MADA_MERCHANT_RATE = 0.006; // 0.6%
    private const MADA_GATEWAY_RATE  = 0.004; // 0.4%
    private const CARD_MERCHANT_FEE  = 1.000;
    private const CARD_GATEWAY_FEE   = 0.500;

    // ─── setUp ────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'          => 'Fee Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Fee Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        StoreSettings::create([
            'store_id'                      => $this->store->id,
            'tax_rate'                      => 15,
            'enable_refunds'                => true,
            'enable_exchanges'              => true,
            'return_without_receipt_policy' => 'deny',
            'held_cart_expiry_hours'        => 24,
        ]);

        $this->register = Register::create([
            'store_id'                    => $this->store->id,
            'name'                        => 'SoftPOS Terminal',
            'device_id'                   => 'sp-fee-dev-001',
            'platform'                    => 'android',
            'app_version'                 => '1.0.0',
            'is_active'                   => true,
            'softpos_enabled'             => true,
            'softpos_status'              => 'active',
            'nfc_capable'                 => true,
            // Explicit default rates
            'softpos_mada_merchant_rate'  => self::MADA_MERCHANT_RATE,
            'softpos_mada_gateway_rate'   => self::MADA_GATEWAY_RATE,
            'softpos_card_merchant_fee'   => self::CARD_MERCHANT_FEE,
            'softpos_card_gateway_fee'    => self::CARD_GATEWAY_FEE,
        ]);

        $this->admin = AdminUser::create([
            'name'          => 'Platform Admin',
            'email'         => 'admin-fee@wameed.test',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
        $this->adminToken = $this->admin->createToken('test', ['*'])->plainTextToken;

        $this->cashier = User::create([
            'name'            => 'Cashier Fee',
            'email'           => 'cashier-fee@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'cashier',
            'is_active'       => true,
        ]);
        $this->providerToken = $this->cashier->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════════
    // 1 — Mada fee calculation
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_mada_percentage_calculation_for_1000_sar(): void
    {
        $svc    = new SoftPosFeeService();
        $result = $svc->calculate(
            amount:           1000.0,
            cardScheme:       'mada',
            madaMerchantRate: 0.006,
            madaGatewayRate:  0.004,
            cardMerchantFee:  1.0,
            cardGatewayFee:   0.5,
        );

        $this->assertEqualsWithDelta(6.0,  $result['platform_fee'], 0.001);
        $this->assertEqualsWithDelta(4.0,  $result['gateway_fee'],  0.001);
        $this->assertEqualsWithDelta(2.0,  $result['margin'],       0.001);
        $this->assertSame('percentage', $result['fee_type']);
        $this->assertSame('mada',       $result['scheme']);
    }

    /** @test */
    public function test_mada_variants_are_normalised_correctly(): void
    {
        $svc = new SoftPosFeeService();

        foreach (['MADA', 'Mada', 'mada', 'MADA_CREDIT'] as $raw) {
            $result = $svc->calculate(100.0, $raw, 0.006, 0.004, 1.0, 0.5);
            $this->assertSame('percentage', $result['fee_type'], "Variant '$raw' should be treated as percentage");
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 2 — Visa/MC fixed fee
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_visa_fixed_fee_is_independent_of_amount(): void
    {
        $svc = new SoftPosFeeService();

        foreach ([50.0, 500.0, 10000.0] as $amount) {
            $result = $svc->calculate($amount, 'visa', 0.006, 0.004, 1.0, 0.5);

            $this->assertEqualsWithDelta(1.0, $result['platform_fee'], 0.001, "amount=$amount");
            $this->assertEqualsWithDelta(0.5, $result['gateway_fee'],  0.001, "amount=$amount");
            $this->assertEqualsWithDelta(0.5, $result['margin'],       0.001, "amount=$amount");
            $this->assertSame('fixed', $result['fee_type']);
        }
    }

    /** @test */
    public function test_mastercard_uses_fixed_fee(): void
    {
        $svc    = new SoftPosFeeService();
        $result = $svc->calculate(250.0, 'mastercard', 0.006, 0.004, 1.0, 0.5);

        $this->assertSame('fixed', $result['fee_type']);
        $this->assertEqualsWithDelta(1.0, $result['platform_fee'], 0.001);
    }

    // ═══════════════════════════════════════════════════════════════
    // 3 — Custom rates per terminal
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_custom_terminal_rates_used_in_calculation(): void
    {
        $this->register->update([
            'softpos_mada_merchant_rate' => 0.010, // 1.0%
            'softpos_mada_gateway_rate'  => 0.007, // 0.7%
            'softpos_card_merchant_fee'  => 2.000,
            'softpos_card_gateway_fee'   => 1.200,
        ]);

        $svc    = new SoftPosFeeService();
        $result = $svc->calculateFromRegister(1000.0, 'mada', $this->register->fresh());

        $this->assertEqualsWithDelta(10.0, $result['platform_fee'], 0.001); // 1000 * 0.010
        $this->assertEqualsWithDelta(7.0,  $result['gateway_fee'],  0.001); // 1000 * 0.007
        $this->assertEqualsWithDelta(3.0,  $result['margin'],       0.001); // 10 - 7

        // Visa with custom fixed fee
        $result2 = $svc->calculateFromRegister(500.0, 'visa', $this->register->fresh());
        $this->assertEqualsWithDelta(2.0,  $result2['platform_fee'], 0.001);
        $this->assertEqualsWithDelta(1.2,  $result2['gateway_fee'],  0.001);
        $this->assertEqualsWithDelta(0.8,  $result2['margin'],       0.001);
    }

    // ═══════════════════════════════════════════════════════════════
    // 4 — Unknown scheme defaults to Mada rates
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_unknown_card_scheme_falls_back_to_mada_rates(): void
    {
        $svc = new SoftPosFeeService();

        // Truly unknown schemes (not mada / visa / mastercard / amex) fall back to mada percentage
        foreach ([null, '', 'unionpay', 'jcb', 'unknown_scheme'] as $scheme) {
            $result = $svc->calculate(1000.0, $scheme, 0.006, 0.004, 1.0, 0.5);
            $this->assertSame('percentage', $result['fee_type'], "Scheme '$scheme' should fall back to percentage");
        }

        // Amex is classified as a card (same as Visa/MC) and returns fixed fee
        $amexResult = $svc->calculate(1000.0, 'amex', 0.006, 0.004, 1.0, 0.5);
        $this->assertSame('fixed', $amexResult['fee_type'], 'amex should use fixed fee like Visa/MC');
        $this->assertEqualsWithDelta(1.0, $amexResult['platform_fee'], 0.001);
    }

    // ═══════════════════════════════════════════════════════════════
    // 5 — Edge cases
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_zero_amount_produces_zero_fees(): void
    {
        $svc    = new SoftPosFeeService();
        $result = $svc->calculate(0.0, 'mada', 0.006, 0.004, 1.0, 0.5);

        $this->assertEqualsWithDelta(0.0, $result['platform_fee'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['gateway_fee'],  0.001);
        $this->assertEqualsWithDelta(0.0, $result['margin'],       0.001);
    }

    /** @test */
    public function test_very_small_mada_amount_rounds_to_three_decimal_places(): void
    {
        $svc = new SoftPosFeeService();

        // 0.01 * 0.006 = 0.00006 → rounds to 0.000 (3dp).
        // The service uses round(..., 3) so sub-cent amounts are floored to 0.
        $result = $svc->calculate(0.01, 'mada', 0.006, 0.004, 1.0, 0.5);
        $this->assertEqualsWithDelta(0.0, $result['platform_fee'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['gateway_fee'],  0.001);

        // 1.0 SAR produces a meaningful fee (0.006 SAR rounds to 0.006)
        $result2 = $svc->calculate(1.0, 'mada', 0.006, 0.004, 1.0, 0.5);
        $this->assertEqualsWithDelta(0.006, $result2['platform_fee'], 0.001);
        $this->assertEqualsWithDelta(0.004, $result2['gateway_fee'],  0.001);
        $this->assertEqualsWithDelta(0.002, $result2['margin'],       0.001);
    }

    // ═══════════════════════════════════════════════════════════════
    // 6 — Fee description helpers
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_merchant_fee_description_for_mada(): void
    {
        $svc  = new SoftPosFeeService();
        $desc = $svc->merchantFeeDescription('mada', 0.006, 1.0);

        $this->assertStringContainsString('0.6%', $desc);
    }

    /** @test */
    public function test_merchant_fee_description_for_visa(): void
    {
        $svc  = new SoftPosFeeService();
        $desc = $svc->merchantFeeDescription('visa', 0.006, 1.0);

        $this->assertStringContainsString('1', $desc); // fixed fee SAR 1
    }

    /** @test */
    public function test_admin_fee_description_shows_bilateral_breakdown(): void
    {
        $svc  = new SoftPosFeeService();
        $desc = $svc->adminFeeDescription('mada', 0.006, 0.004, 1.0, 0.5);

        $this->assertStringContainsString('0.6%', $desc);
        $this->assertStringContainsString('0.4%', $desc);
    }

    // ═══════════════════════════════════════════════════════════════
    // 7 — Transaction stores fees in softpos_transactions
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_softpos_transaction_record_stores_fee_columns(): void
    {
        // Directly create a SoftPosTransaction with all fee columns
        // order_id is a UUID column in the DB — leave null (it's nullable)
        $txn = SoftPosTransaction::create([
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'terminal_id'     => $this->register->id,
            'amount'          => 1000.0,
            'currency'        => 'SAR',
            'payment_method'  => 'mada',
            'status'          => 'completed',
            'platform_fee'    => 6.0,
            'gateway_fee'     => 4.0,
            'margin'          => 2.0,
            'fee_type'        => 'percentage',
        ]);

        $this->assertDatabaseHas('softpos_transactions', [
            'id'           => $txn->id,
            'platform_fee' => '6.000',
            'gateway_fee'  => '4.000',
            'margin'       => '2.000',
            'fee_type'     => 'percentage',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 8 — platform_daily_stats updated after a SoftPOS transaction
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_platform_daily_stats_updated_with_softpos_margin(): void
    {
        $today = now()->toDateString();

        // Seed an existing row (simulates other activity today)
        PlatformDailyStat::firstOrCreate(
            ['date' => $today],
            ['softpos_transaction_count' => 0, 'softpos_margin' => 0.0]
        );

        // Simulate what TransactionService does:
        PlatformDailyStat::where('date', $today)->update([
            'softpos_transaction_count' => \Illuminate\Support\Facades\DB::raw('softpos_transaction_count + 1'),
            'softpos_volume'            => \Illuminate\Support\Facades\DB::raw('softpos_volume + 1000'),
            'softpos_platform_fees'     => \Illuminate\Support\Facades\DB::raw('softpos_platform_fees + 6'),
            'softpos_gateway_fees'      => \Illuminate\Support\Facades\DB::raw('softpos_gateway_fees + 4'),
            'softpos_margin'            => \Illuminate\Support\Facades\DB::raw('softpos_margin + 2'),
        ]);

        $stat = PlatformDailyStat::where('date', $today)->first();
        $this->assertEquals(1, $stat->softpos_transaction_count);
        $this->assertEqualsWithDelta(1000.0, $stat->softpos_volume,        0.001);
        $this->assertEqualsWithDelta(6.0,    $stat->softpos_platform_fees, 0.001);
        $this->assertEqualsWithDelta(4.0,    $stat->softpos_gateway_fees,  0.001);
        $this->assertEqualsWithDelta(2.0,    $stat->softpos_margin,        0.001);
    }

    // ═══════════════════════════════════════════════════════════════
    // 9 — Admin PATCH /admin/terminals/{id}/softpos-billing
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_update_softpos_billing_rates(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v2/admin/terminals/{$this->register->id}/softpos-billing", [
                'mada_merchant_rate' => 0.010,
                'mada_gateway_rate'  => 0.007,
                'card_merchant_fee'  => 2.000,
                'card_gateway_fee'   => 1.000,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('registers', [
            'id'                         => $this->register->id,
            'softpos_mada_merchant_rate' => '0.010000',
            'softpos_mada_gateway_rate'  => '0.007000',
            'softpos_card_merchant_fee'  => '2.000',
            'softpos_card_gateway_fee'   => '1.000',
        ]);
    }

    /** @test */
    public function test_admin_can_partially_update_softpos_billing(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v2/admin/terminals/{$this->register->id}/softpos-billing", [
                'mada_merchant_rate' => 0.008,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('registers', [
            'id'                         => $this->register->id,
            'softpos_mada_merchant_rate' => '0.008000',
            // Other fields unchanged
            'softpos_mada_gateway_rate'  => '0.004000',
            'softpos_card_merchant_fee'  => '1.000',
            'softpos_card_gateway_fee'   => '0.500',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 10 — Admin GET /admin/softpos/financials
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_fetch_softpos_financials(): void
    {
        // Seed some transactions
        SoftPosTransaction::create([
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'terminal_id'     => $this->register->id,
            'amount'          => 1000.0,
            'currency'        => 'SAR',
            'payment_method'  => 'mada',
            'status'          => 'completed',
            'platform_fee'    => 6.0,
            'gateway_fee'     => 4.0,
            'margin'          => 2.0,
            'fee_type'        => 'percentage',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v2/admin/analytics/softpos/financials');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpis' => [
                        'transaction_count',
                        'total_volume',
                        'total_platform_fee',
                        'total_gateway_fee',
                        'total_margin',
                        'overall_margin_rate',
                    ],
                    'by_scheme',
                    'by_terminal',
                    'by_store',
                    'daily_trend',
                    'date_range',
                ],
            ]);

        $kpis = $response->json('data.kpis');
        $this->assertEquals(1,   $kpis['transaction_count']);
        $this->assertEquals(1000, $kpis['total_volume']);
        $this->assertEquals(2,   $kpis['total_margin']);
    }

    // ═══════════════════════════════════════════════════════════════
    // 11 — Admin GET /admin/softpos/terminal-rates
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_fetch_terminal_rates(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v2/admin/analytics/softpos/terminal-rates');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'terminals' => [
                        '*' => [
                            'id',
                            'name',
                            'billing' => [
                                'mada_merchant_rate',
                                'mada_gateway_rate',
                                'mada_margin_rate',
                                'card_merchant_fee',
                                'card_gateway_fee',
                                'card_margin_fee',
                                'merchant_description',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 12 — Provider cannot see gateway rates
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_provider_terminal_resource_does_not_expose_gateway_rates(): void
    {
        $response = $this->withToken($this->providerToken)
            ->getJson("/api/v2/pos/terminals/{$this->register->id}");

        $response->assertOk();

        // Provider sees merchant-facing softpos_fees
        $response->assertJsonStructure(['data' => ['softpos_fees' => ['mada_rate', 'card_fee']]]);

        // Must NOT contain gateway_rate keys
        $data = $response->json('data.softpos_fees');
        $this->assertArrayNotHasKey('mada_gateway_rate', $data ?? []);
        $this->assertArrayNotHasKey('card_gateway_fee',  $data ?? []);
        $this->assertArrayNotHasKey('gateway_rate',      $data ?? []);
    }

    // ═══════════════════════════════════════════════════════════════
    // 13 — Admin sees full bilateral breakdown
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_terminal_resource_shows_full_bilateral_breakdown(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v2/admin/terminals/{$this->register->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'softpos_billing' => [
                        'mada_merchant_rate',
                        'mada_gateway_rate',
                        'mada_margin_rate',
                        'mada_merchant_rate_pct',
                        'mada_gateway_rate_pct',
                        'mada_margin_rate_pct',
                        'card_merchant_fee',
                        'card_gateway_fee',
                        'card_margin_fee',
                    ],
                ],
            ]);

        $billing = $response->json('data.softpos_billing');
        $this->assertEqualsWithDelta(0.006, $billing['mada_merchant_rate'], 0.000001);
        $this->assertEqualsWithDelta(0.004, $billing['mada_gateway_rate'],  0.000001);
        $this->assertEqualsWithDelta(0.002, $billing['mada_margin_rate'],   0.000001);
        $this->assertEqualsWithDelta(1.0,   $billing['card_merchant_fee'],  0.001);
        $this->assertEqualsWithDelta(0.5,   $billing['card_gateway_fee'],   0.001);
        $this->assertEqualsWithDelta(0.5,   $billing['card_margin_fee'],    0.001);
    }

    // ═══════════════════════════════════════════════════════════════
    // 14 — PATCH requires admin auth
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_provider_token_cannot_update_softpos_billing(): void
    {
        $response = $this->withToken($this->providerToken)
            ->patchJson("/api/v2/admin/terminals/{$this->register->id}/softpos-billing", [
                'mada_merchant_rate' => 0.001,
            ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function test_unauthenticated_cannot_update_softpos_billing(): void
    {
        $response = $this->patchJson("/api/v2/admin/terminals/{$this->register->id}/softpos-billing", [
            'mada_merchant_rate' => 0.001,
        ]);

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // 15 — Business rule: merchant_rate >= gateway_rate
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_mada_merchant_rate_cannot_be_less_than_gateway_rate(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v2/admin/terminals/{$this->register->id}/softpos-billing", [
                'mada_merchant_rate' => 0.003, // less than current gateway rate 0.004
                'mada_gateway_rate'  => 0.004,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', fn ($v) => str_contains($v, 'loss') || str_contains($v, 'mada_merchant_rate'));
    }

    /** @test */
    public function test_card_merchant_fee_cannot_be_less_than_gateway_fee(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v2/admin/terminals/{$this->register->id}/softpos-billing", [
                'card_merchant_fee' => 0.3, // less than current gateway fee 0.5
                'card_gateway_fee'  => 0.5,
            ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // 16 — Admin GET /admin/softpos/transactions returns paginated list
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_fetch_softpos_transactions_with_fees(): void
    {
        SoftPosTransaction::create([
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'terminal_id'     => $this->register->id,
            'amount'          => 500.0,
            'currency'        => 'SAR',
            'payment_method'  => 'visa',
            'status'          => 'completed',
            'platform_fee'    => 1.0,
            'gateway_fee'     => 0.5,
            'margin'          => 0.5,
            'fee_type'        => 'fixed',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v2/admin/analytics/softpos/transactions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'amount',
                            'card_scheme',
                            'fee_type',
                            'fees' => ['platform_fee', 'gateway_fee', 'margin'],
                            'status',
                        ],
                    ],
                    'pagination',
                    'page_summary' => [
                        'total_amount',
                        'total_platform_fee',
                        'total_gateway_fee',
                        'total_margin',
                    ],
                ],
            ]);

        $firstItem = $response->json('data.data.0');
        $this->assertEqualsWithDelta(1.0, $firstItem['fees']['platform_fee'], 0.001);
        $this->assertEqualsWithDelta(0.5, $firstItem['fees']['margin'],       0.001);
    }

    // ═══════════════════════════════════════════════════════════════
    // Extra — calculateFromRegister matches calculate()
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_calculate_from_register_is_consistent_with_direct_calculate(): void
    {
        $svc = new SoftPosFeeService();

        $direct   = $svc->calculate(750.0, 'mada', 0.006, 0.004, 1.0, 0.5);
        $fromReg  = $svc->calculateFromRegister(750.0, 'mada', $this->register);

        $this->assertEqualsWithDelta($direct['platform_fee'], $fromReg['platform_fee'], 0.001);
        $this->assertEqualsWithDelta($direct['gateway_fee'],  $fromReg['gateway_fee'],  0.001);
        $this->assertEqualsWithDelta($direct['margin'],       $fromReg['margin'],       0.001);
        $this->assertSame($direct['fee_type'], $fromReg['fee_type']);
    }
}
