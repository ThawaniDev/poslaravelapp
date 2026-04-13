<?php

namespace Tests\Feature\Settings;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\PosTerminal\Models\PosSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * Store Settings Enforcement Test
 *
 * Verifies that StoreSettings (require_customer_for_sale, max_discount_percent,
 * allow_negative_stock, enable_refunds, track_inventory, tax_rate) are actually
 * enforced in TransactionService business logic — not just stored.
 */
class StoreSettingsEnforcementTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private StoreSettings $storeSettings;
    private string $token;
    private Category $category;
    private Register $register;
    private Product $product;
    private PosSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Settings Enforcement Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Settings Store',
            'business_type' => 'grocery',
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Cashier',
            'email' => 'settings-enforcement@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->assignOwnerRole($this->user, $this->store->id);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->storeSettings = StoreSettings::create([
            'store_id' => $this->store->id,
            'tax_rate' => 5.00,
            'allow_negative_stock' => false,
            'require_customer_for_sale' => false,
            'max_discount_percent' => 20,
            'enable_refunds' => true,
            'track_inventory' => true,
        ]);

        $this->category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Drinks',
            'name_ar' => 'مشروبات',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $this->register = Register::create([
            'store_id' => $this->store->id,
            'name' => 'POS-1',
            'device_id' => 'SETTINGS-DEVICE-001',
            'is_active' => true,
            'is_online' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Omani Coffee',
            'name_ar' => 'قهوة عمانية',
            'sell_price' => 10.00,
            'cost_price' => 4.00,
            'tax_rate' => 5.00,
            'barcode' => '6281000000001',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'sync_version' => 1,
        ]);

        $this->session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 100.00,
        ]);
    }

    private function salePayload(array $overrides = []): array
    {
        return array_merge([
            'pos_session_id' => $this->session->id,
            'type' => 'sale',
            'subtotal' => 20.00,
            'total_amount' => 21.00,
            'tax_amount' => 1.00,
            'discount_amount' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => 'Omani Coffee',
                    'quantity' => 2,
                    'unit_price' => 10.00,
                    'line_total' => 20.00,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 21.00,
                    'cash_tendered' => 25.00,
                    'change_given' => 4.00,
                ],
            ],
        ], $overrides);
    }

    // ═══════════════════════════════════════════════════════════
    // require_customer_for_sale
    // ═══════════════════════════════════════════════════════════

    public function test_sale_without_customer_succeeds_when_not_required(): void
    {
        $this->storeSettings->update(['require_customer_for_sale' => false]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(201);
    }

    public function test_sale_without_customer_rejected_when_required(): void
    {
        $this->storeSettings->update(['require_customer_for_sale' => true]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => __('pos.customer_required_for_sale')]);
    }

    public function test_sale_with_customer_succeeds_when_required(): void
    {
        $this->storeSettings->update(['require_customer_for_sale' => true]);

        $customer = \App\Domain\Customer\Models\Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Ali Al-Busaidi',
            'phone' => '+96890000001',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'customer_id' => $customer->id,
            ]));

        $response->assertStatus(201);
    }

    // ═══════════════════════════════════════════════════════════
    // max_discount_percent
    // ═══════════════════════════════════════════════════════════

    public function test_discount_within_limit_succeeds(): void
    {
        $this->storeSettings->update(['max_discount_percent' => 20]);

        // 10% discount on subtotal of 20.00 = 2.00 discount
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'discount_amount' => 2.00,
                'total_amount' => 19.00,
            ]));

        $response->assertStatus(201);
    }

    public function test_discount_exceeding_limit_rejected(): void
    {
        $this->storeSettings->update(['max_discount_percent' => 20]);

        // 50% discount on subtotal of 20.00 = 10.00 discount
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'discount_amount' => 10.00,
                'total_amount' => 11.00,
            ]));

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => __('pos.discount_exceeds_maximum', ['max' => 20])]);
    }

    public function test_item_level_discount_exceeding_limit_rejected(): void
    {
        $this->storeSettings->update(['max_discount_percent' => 15]);

        // Item-level 30% discount
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'discount_amount' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Omani Coffee',
                        'quantity' => 2,
                        'unit_price' => 10.00,
                        'discount_amount' => 6.00, // 30% of 20 item total
                        'line_total' => 14.00,
                    ],
                ],
            ]));

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // allow_negative_stock
    // ═══════════════════════════════════════════════════════════

    public function test_sale_within_stock_succeeds_when_negative_stock_disallowed(): void
    {
        $this->storeSettings->update(['allow_negative_stock' => false]);

        // Stock is 5, selling 2 → should succeed
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(201);

        // Verify stock decreased
        $this->assertEquals(3, StockLevel::where('product_id', $this->product->id)->first()->quantity);
    }

    public function test_sale_exceeding_stock_rejected_when_negative_stock_disallowed(): void
    {
        $this->storeSettings->update(['allow_negative_stock' => false]);

        // Stock is 5, selling 10 → should fail
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'subtotal' => 100.00,
                'total_amount' => 105.00,
                'tax_amount' => 5.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Omani Coffee',
                        'quantity' => 10,
                        'unit_price' => 10.00,
                        'line_total' => 100.00,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 105.00],
                ],
            ]));

        $response->assertStatus(422);

        // Stock should remain at 5 (transaction rolled back)
        $this->assertEquals(5, StockLevel::where('product_id', $this->product->id)->first()->quantity);
    }

    public function test_sale_exceeding_stock_allowed_when_negative_stock_enabled(): void
    {
        $this->storeSettings->update(['allow_negative_stock' => true]);

        // Stock is 5, selling 10 → should succeed with negative stock
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'subtotal' => 100.00,
                'total_amount' => 105.00,
                'tax_amount' => 5.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Omani Coffee',
                        'quantity' => 10,
                        'unit_price' => 10.00,
                        'line_total' => 100.00,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 105.00],
                ],
            ]));

        $response->assertStatus(201);

        // Stock should be -5 now
        $this->assertEquals(-5, StockLevel::where('product_id', $this->product->id)->first()->quantity);
    }

    // ═══════════════════════════════════════════════════════════
    // enable_refunds
    // ═══════════════════════════════════════════════════════════

    public function test_return_succeeds_when_refunds_enabled(): void
    {
        $this->storeSettings->update(['enable_refunds' => true]);

        // First create a sale
        $saleResp = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());
        $saleResp->assertStatus(201);
        $saleId = $saleResp->json('data.id');

        // Now create a return
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions/return', [
                'return_transaction_id' => $saleId,
                'pos_session_id' => $this->session->id,
                'subtotal' => 10.00,
                'total_amount' => 10.50,
                'tax_amount' => 0.50,
                'discount_amount' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Omani Coffee',
                        'quantity' => 1,
                        'unit_price' => 10.00,
                        'line_total' => 10.00,
                        'is_return_item' => true,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 10.50],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_return_rejected_when_refunds_disabled(): void
    {
        $this->storeSettings->update(['enable_refunds' => false]);

        // First create a sale (refunds doesn't affect normal sales)
        $saleResp = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());
        $saleResp->assertStatus(201);
        $saleId = $saleResp->json('data.id');

        // Attempt return — should be rejected
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions/return', [
                'return_transaction_id' => $saleId,
                'pos_session_id' => $this->session->id,
                'subtotal' => 10.00,
                'total_amount' => 10.50,
                'tax_amount' => 0.50,
                'discount_amount' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Omani Coffee',
                        'quantity' => 1,
                        'unit_price' => 10.00,
                        'line_total' => 10.00,
                        'is_return_item' => true,
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 10.50],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => __('pos.refunds_disabled')]);
    }

    // ═══════════════════════════════════════════════════════════
    // tax_rate default from settings
    // ═══════════════════════════════════════════════════════════

    public function test_tax_rate_defaults_to_store_setting_when_not_provided(): void
    {
        $this->storeSettings->update(['tax_rate' => 5.00]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Omani Coffee',
                        'quantity' => 1,
                        'unit_price' => 10.00,
                        'line_total' => 10.00,
                        // tax_rate intentionally omitted — should default to store's 5%
                    ],
                ],
                'subtotal' => 10.00,
                'total_amount' => 10.50,
                'tax_amount' => 0.50,
                'payments' => [
                    ['method' => 'cash', 'amount' => 10.50],
                ],
            ]));

        $response->assertStatus(201);

        // Verify the transaction item got the store tax rate
        $txId = $response->json('data.id');
        $item = \App\Domain\PosTerminal\Models\TransactionItem::where('transaction_id', $txId)->first();
        $this->assertEquals(5.00, (float) $item->tax_rate);
    }

    public function test_explicit_tax_rate_overrides_store_default(): void
    {
        $this->storeSettings->update(['tax_rate' => 5.00]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload([
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_name' => 'Omani Coffee',
                        'quantity' => 1,
                        'unit_price' => 10.00,
                        'tax_rate' => 15.00, // explicitly set — should override
                        'line_total' => 10.00,
                    ],
                ],
                'subtotal' => 10.00,
                'total_amount' => 11.50,
                'tax_amount' => 1.50,
                'payments' => [
                    ['method' => 'cash', 'amount' => 11.50],
                ],
            ]));

        $response->assertStatus(201);

        $txId = $response->json('data.id');
        $item = \App\Domain\PosTerminal\Models\TransactionItem::where('transaction_id', $txId)->first();
        $this->assertEquals(15.00, (float) $item->tax_rate);
    }

    // ═══════════════════════════════════════════════════════════
    // track_inventory
    // ═══════════════════════════════════════════════════════════

    public function test_stock_not_deducted_when_inventory_tracking_disabled(): void
    {
        $this->storeSettings->update(['track_inventory' => false]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(201);

        // Stock should remain unchanged at 5
        $this->assertEquals(5, StockLevel::where('product_id', $this->product->id)->first()->quantity);
    }

    public function test_stock_deducted_when_inventory_tracking_enabled(): void
    {
        $this->storeSettings->update(['track_inventory' => true]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/pos/transactions', $this->salePayload());

        $response->assertStatus(201);

        // Stock should decrease from 5 to 3 (sold 2)
        $this->assertEquals(3, StockLevel::where('product_id', $this->product->id)->first()->quantity);
    }
}
