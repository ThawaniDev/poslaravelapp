<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\IndustryRestaurant\Models\OpenTab;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\PosTerminal\Services\ManagerPinService;
use App\Domain\Payment\Enums\CashSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the production-hardening additions to the POS terminal:
 *  • Manager-PIN gates on discount, tax-exempt, and void
 *  • Modifier persistence on transaction items
 *  • Open-tab auto-settlement when a sale references the tab
 *  • Refund-method suggestion endpoint
 *  • Customer-Facing Display state endpoint
 */
class PosTerminalHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    /**
     * Mint a manager-PIN approval token directly into the cache. The
     * production flow goes through `ManagerPinService::verify()` which
     * requires a real PIN + permission check; for these gate tests we only
     * care that the token is honoured by downstream consumers.
     */
    private function issueApprovalToken(string $action): string
    {
        $token = Str::random(48);
        Cache::put('pos:mgr_pin:' . $token, [
            'approver_id' => $this->user->id,
            'action' => $action,
            'organization_id' => $this->org->id,
        ], 300);
        return $token;
    }

    private function configureStoreSettings(array $overrides = []): StoreSettings
    {
        return StoreSettings::create(array_merge([
            'store_id' => $this->store->id,
            'currency_code' => 'SAR',
            'currency_symbol' => 'ر.س',
            'decimal_places' => 3,
        ], $overrides));
    }

    private function basicSalePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'sale',
            'subtotal' => 100.00,
            'tax_amount' => 0,
            'total_amount' => 100.00,
            'items' => [
                [
                    'product_name' => 'Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'line_total' => 100,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 100],
            ],
        ], $overrides);
    }

    // ─── Manager-PIN gates ───────────────────────────────────

    public function test_discount_above_threshold_requires_manager_pin(): void
    {
        $this->configureStoreSettings([
            'require_manager_for_discount' => true,
            'discount_pin_threshold_percent' => 10,
        ]);

        // 20% discount on 100 → exceeds threshold, no token sent.
        $payload = $this->basicSalePayload([
            'subtotal' => 100,
            'discount_amount' => 20,
            'total_amount' => 80,
            'payments' => [['method' => 'cash', 'amount' => 80]],
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString('manager', strtolower($response->json('message') ?? ''));
    }

    public function test_discount_below_threshold_does_not_require_pin(): void
    {
        $this->configureStoreSettings([
            'require_manager_for_discount' => true,
            'discount_pin_threshold_percent' => 10,
        ]);

        $payload = $this->basicSalePayload([
            'subtotal' => 100,
            'discount_amount' => 5,
            'total_amount' => 95,
            'payments' => [['method' => 'cash', 'amount' => 95]],
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $payload);

        $response->assertStatus(201);
    }

    public function test_discount_with_valid_pin_token_succeeds(): void
    {
        $this->configureStoreSettings([
            'require_manager_for_discount' => true,
            'discount_pin_threshold_percent' => 10,
        ]);

        $token = $this->issueApprovalToken('discount');

        $payload = $this->basicSalePayload([
            'subtotal' => 100,
            'discount_amount' => 25,
            'total_amount' => 75,
            'payments' => [['method' => 'cash', 'amount' => 75]],
            'approval_token' => $token,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $payload);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.approver_id'));
    }

    public function test_void_requires_manager_pin_when_configured(): void
    {
        $this->configureStoreSettings([
            'require_manager_for_refund' => true,
        ]);

        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-PIN-1',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 50,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/pos/transactions/{$txn->id}/void", [
                'reason' => 'Customer changed mind',
            ]);

        $response->assertStatus(422);

        // Now retry with a valid void-bound token.
        $token = $this->issueApprovalToken('void');

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/pos/transactions/{$txn->id}/void", [
                'reason' => 'Customer changed mind',
                'approval_token' => $token,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'voided');
        $this->assertNotNull($response->json('data.approver_id'));
    }

    // ─── Modifier persistence ────────────────────────────────

    public function test_transaction_persists_modifiers_and_computes_total(): void
    {
        $payload = $this->basicSalePayload([
            'subtotal' => 30,
            'total_amount' => 30,
            'items' => [[
                'product_name' => 'Latte',
                'quantity' => 2,
                'unit_price' => 15,
                'line_total' => 30,
                'item_notes' => 'No sugar',
                'modifier_selections' => [
                    [
                        'modifier_option_id' => '11111111-1111-1111-1111-111111111111',
                        'modifier_group_id' => '22222222-2222-2222-2222-222222222222',
                        'name' => 'Extra shot',
                        'price_adjustment' => 2.5,
                        'quantity' => 1,
                    ],
                    [
                        'modifier_option_id' => '33333333-3333-3333-3333-333333333333',
                        'name' => 'Oat milk',
                        'price_adjustment' => 1,
                        'quantity' => 1,
                    ],
                ],
            ]],
            'payments' => [['method' => 'cash', 'amount' => 30]],
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $payload);

        $response->assertStatus(201);

        $item = $response->json('data.items.0');
        // (2.5 + 1) per unit × 2 units = 7.0
        $this->assertEquals(7.0, (float) $item['modifier_total']);
        $this->assertEquals('No sugar', $item['item_notes']);
        $this->assertCount(2, $item['modifier_selections']);
    }

    // ─── Open-tab auto-settlement ────────────────────────────

    public function test_sale_with_tab_id_closes_open_tab(): void
    {
        $tab = OpenTab::create([
            'store_id' => $this->store->id,
            'customer_name' => 'Walk-in',
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $payload = $this->basicSalePayload(['tab_id' => $tab->id]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $payload);

        $response->assertStatus(201);

        $tab->refresh();
        $this->assertEquals('closed', $tab->status);
        $this->assertEquals($response->json('data.id'), $tab->transaction_id);
        $this->assertNotNull($tab->closed_at);
    }

    // ─── Refund-method suggestion endpoint ───────────────────

    public function test_refund_methods_suggests_split_proportional_to_original(): void
    {
        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-RM-1',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 100,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $txn->payments()->createMany([
            ['method' => 'cash', 'amount' => 60, 'cash_tendered' => 60, 'change_given' => 0],
            ['method' => 'card', 'amount' => 40, 'card_last_four' => '4242'],
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/pos/transactions/{$txn->id}/refund-methods?amount=50");

        $response->assertOk();
        $this->assertEquals(50.0, (float) $response->json('data.refund_amount'));

        $suggested = $response->json('data.suggested');
        $this->assertCount(2, $suggested);

        $byMethod = collect($suggested)->keyBy('method');
        // 60% × 50 = 30 cash, remainder 20 card
        $this->assertEquals(30.0, round((float) $byMethod['cash']['amount'], 2));
        $this->assertEquals(20.0, round((float) $byMethod['card']['amount'], 2));
        $this->assertEquals('4242', $byMethod['card']['card_last_four']);
    }

    // ─── CFD endpoint ────────────────────────────────────────

    public function test_cfd_display_returns_config_and_session_state(): void
    {
        $this->configureStoreSettings([
            'cfd_enabled' => true,
            'cfd_idle_layout' => 'promotions',
            'cfd_cart_layout' => 'list',
            'cfd_welcome_message' => 'Welcome',
            'cfd_welcome_message_ar' => 'مرحبا',
            'cfd_show_promotions' => false,
        ]);

        $session = PosSession::create([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => 100,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/pos/sessions/{$session->id}/cfd-display");

        $response->assertOk();
        $this->assertTrue($response->json('data.config.enabled'));
        $this->assertEquals('promotions', $response->json('data.config.idle_layout'));
        $this->assertEquals('Welcome', $response->json('data.config.welcome_message'));
        $this->assertEquals('SAR', $response->json('data.config.currency_code'));
        $this->assertEquals($session->id, $response->json('data.session_id'));
        $this->assertNull($response->json('data.cart'));
        $this->assertEquals([], $response->json('data.promotions'));
    }
}
