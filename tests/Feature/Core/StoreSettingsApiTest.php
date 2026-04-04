<?php

namespace Tests\Feature\Core;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsApiTest extends TestCase
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
            'name' => 'Settings Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Settings Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Manager',
            'email' => 'manager@settings-test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ];
    }

    // ─── GET Settings ────────────────────────────────────────────

    public function test_can_get_store_settings(): void
    {
        // Ensure settings exist
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->getJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            $this->authHeaders()
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'store_id',
                    'tax_label',
                    'tax_rate',
                    'prices_include_tax',
                    'receipt_header',
                    'receipt_show_logo',
                    'receipt_show_tax_breakdown',
                    'receipt_show_address',
                    'receipt_show_phone',
                    'receipt_show_date',
                    'receipt_show_cashier',
                    'receipt_show_barcode',
                    'receipt_paper_size',
                    'receipt_font_size',
                    'receipt_language',
                    'currency_code',
                    'currency_symbol',
                    'default_sale_type',
                    'require_customer_for_sale',
                    'auto_print_receipt',
                    'enable_tips',
                    'enable_hold_orders',
                    'enable_refunds',
                    'enable_exchanges',
                    'require_manager_for_refund',
                    'require_manager_for_discount',
                    'enable_open_price_items',
                    'enable_quick_add_products',
                    'max_discount_percent',
                    'session_timeout_minutes',
                    'enable_loyalty_points',
                    'enable_customer_display',
                    'track_inventory',
                    'low_stock_alert',
                ],
            ]);
    }

    // ─── UPDATE Settings ─────────────────────────────────────────

    public function test_can_update_tax_settings(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            [
                'tax_label' => 'GST',
                'tax_rate' => 5.0,
                'prices_include_tax' => false,
                'tax_number' => 'TAX-12345',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $settings = StoreSettings::where('store_id', $this->store->id)->first();
        $this->assertEquals('GST', $settings->tax_label);
        $this->assertEquals(5.0, (float) $settings->tax_rate);
        $this->assertFalse($settings->prices_include_tax);
        $this->assertEquals('TAX-12345', $settings->tax_number);
    }

    public function test_can_update_receipt_settings(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            [
                'receipt_header' => 'My Store Header',
                'receipt_footer' => 'Thank you!',
                'receipt_show_logo' => false,
                'receipt_show_tax_breakdown' => true,
                'receipt_show_address' => false,
                'receipt_show_barcode' => true,
                'receipt_paper_size' => '58mm',
                'receipt_font_size' => 'large',
                'receipt_language' => 'en',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $settings = StoreSettings::where('store_id', $this->store->id)->first();
        $this->assertEquals('My Store Header', $settings->receipt_header);
        $this->assertEquals('Thank you!', $settings->receipt_footer);
        $this->assertFalse($settings->receipt_show_logo);
        $this->assertEquals('58mm', $settings->receipt_paper_size);
        $this->assertEquals('large', $settings->receipt_font_size);
        $this->assertEquals('en', $settings->receipt_language);
    }

    public function test_can_update_pos_behavior_settings(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            [
                'default_sale_type' => 'takeaway',
                'require_customer_for_sale' => true,
                'auto_print_receipt' => false,
                'enable_tips' => true,
                'enable_hold_orders' => false,
                'enable_refunds' => true,
                'enable_exchanges' => false,
                'require_manager_for_refund' => true,
                'require_manager_for_discount' => true,
                'max_discount_percent' => 50,
                'session_timeout_minutes' => 15,
                'barcode_scan_sound' => false,
                'enable_open_price_items' => true,
                'enable_quick_add_products' => false,
                'enable_kitchen_display' => true,
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $settings = StoreSettings::where('store_id', $this->store->id)->first();
        $this->assertEquals('takeaway', $settings->default_sale_type);
        $this->assertTrue($settings->require_customer_for_sale);
        $this->assertFalse($settings->auto_print_receipt);
        $this->assertTrue($settings->enable_tips);
        $this->assertFalse($settings->enable_hold_orders);
        $this->assertTrue($settings->require_manager_for_refund);
        $this->assertEquals(50, $settings->max_discount_percent);
        $this->assertEquals(15, $settings->session_timeout_minutes);
    }

    public function test_can_update_loyalty_settings(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            [
                'enable_loyalty_points' => true,
                'loyalty_points_per_currency' => 2.5,
                'loyalty_redemption_value' => 0.05,
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $settings = StoreSettings::where('store_id', $this->store->id)->first();
        $this->assertTrue($settings->enable_loyalty_points);
        $this->assertEquals(2.5, (float) $settings->loyalty_points_per_currency);
        $this->assertEquals(0.05, (float) $settings->loyalty_redemption_value);
    }

    public function test_can_update_inventory_settings(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            [
                'track_inventory' => true,
                'allow_negative_stock' => true,
                'enable_batch_tracking' => true,
                'enable_expiry_tracking' => true,
                'auto_deduct_ingredients' => true,
                'low_stock_alert' => true,
                'low_stock_threshold' => 5,
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $settings = StoreSettings::where('store_id', $this->store->id)->first();
        $this->assertTrue($settings->track_inventory);
        $this->assertTrue($settings->enable_batch_tracking);
        $this->assertTrue($settings->enable_expiry_tracking);
        $this->assertTrue($settings->auto_deduct_ingredients);
        $this->assertEquals(5, $settings->low_stock_threshold);
    }

    public function test_can_update_customer_display_settings(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            [
                'enable_customer_display' => true,
                'customer_display_message' => 'Welcome to our store!',
                'theme_mode' => 'dark',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $settings = StoreSettings::where('store_id', $this->store->id)->first();
        $this->assertTrue($settings->enable_customer_display);
        $this->assertEquals('Welcome to our store!', $settings->customer_display_message);
        $this->assertEquals('dark', $settings->theme_mode);
    }

    // ─── Validation Tests ────────────────────────────────────────

    public function test_rejects_invalid_tax_rate(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            ['tax_rate' => 150], // > 100
            $this->authHeaders()
        );

        $response->assertStatus(422);
    }

    public function test_rejects_invalid_receipt_paper_size(): void
    {
        StoreSettings::firstOrCreate(
            ['store_id' => $this->store->id],
            ['tax_label' => 'VAT', 'tax_rate' => 15.00]
        );

        $response = $this->putJson(
            "/api/v2/core/stores/{$this->store->id}/settings",
            ['receipt_paper_size' => 'A4'], // not 58mm or 80mm
            $this->authHeaders()
        );

        $response->assertStatus(422);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(
            "/api/v2/core/stores/{$this->store->id}/settings"
        );

        $response->assertStatus(401);
    }
}
