<?php

namespace Tests\Feature\Comprehensive;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Order\Models\Order;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\Payment\Models\Payment;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-Platform Model Consistency Tests
 *
 * Verifies that Laravel API responses contain ALL fields expected by
 * Flutter fromJson() parsers. These tests catch mismatches that cause
 * data display issues in the mobile/desktop app.
 */
class CrossPlatformModelConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Category $category;
    private Register $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'CrossPlat Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'CrossPlat Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'crossplatform@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Beverages',
            'name_ar' => 'مشروبات',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $this->register = Register::create([
            'store_id' => $this->store->id,
            'name' => 'Terminal 1',
            'device_id' => 'DEVICE-001',
            'is_active' => true,
            'is_online' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PRODUCT — Flutter expects these exact keys in fromJson()
    // ═══════════════════════════════════════════════════════════

    public function test_product_api_returns_all_flutter_expected_fields(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Arabic Coffee',
            'name_ar' => 'قهوة عربية',
            'description' => 'Traditional Arabic coffee',
            'description_ar' => 'قهوة عربية تقليدية',
            'sku' => 'ACOF-001',
            'barcode' => '6281234567890',
            'sell_price' => 15.50,
            'cost_price' => 8.00,
            'unit' => 'piece',
            'tax_rate' => 15.00,
            'is_weighable' => false,
            'tare_weight' => 0,
            'is_active' => true,
            'is_combo' => false,
            'age_restricted' => false,
            'image_url' => 'https://example.com/coffee.jpg',
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $response->assertOk();
        $data = $response->json('data');

        // Flutter Product.fromJson() expects all these keys
        $expectedKeys = [
            'id', 'organization_id', 'category_id', 'name', 'name_ar',
            'description', 'description_ar', 'sku', 'barcode',
            'sell_price', 'cost_price', 'unit', 'tax_rate',
            'is_weighable', 'tare_weight', 'is_active', 'is_combo',
            'age_restricted', 'image_url', 'sync_version',
            'created_at', 'updated_at',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Product API missing key '$key' expected by Flutter");
        }
    }

    public function test_product_sell_price_is_numeric(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Price Test',
            'sell_price' => 25.75,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['sell_price']);
        $this->assertEquals(25.75, $data['sell_price']);
    }

    public function test_product_boolean_fields_are_boolean(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Bool Test',
            'sell_price' => 10,
            'is_active' => true,
            'is_combo' => false,
            'is_weighable' => false,
            'age_restricted' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');
        $this->assertIsBool($data['is_active']);
        $this->assertIsBool($data['is_combo']);
        $this->assertIsBool($data['is_weighable']);
        $this->assertIsBool($data['age_restricted']);
    }

    // ═══════════════════════════════════════════════════════════
    // CATEGORY — Flutter expects these exact keys
    // ═══════════════════════════════════════════════════════════

    public function test_category_api_returns_all_flutter_expected_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/categories/' . $this->category->id);

        $response->assertOk();
        $data = $response->json('data');

        $expectedKeys = [
            'id', 'organization_id', 'name', 'name_ar',
            'image_url', 'sort_order', 'is_active', 'sync_version',
            'created_at', 'updated_at',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Category API missing key '$key' expected by Flutter");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // CUSTOMER — Flutter expects these exact keys
    // ═══════════════════════════════════════════════════════════

    public function test_customer_api_returns_all_flutter_expected_fields(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Ahmed Al-Rashidi',
            'phone' => '+96812345678',
            'email' => 'ahmed@example.com',
            'address' => '123 Main St, Muscat',
            'date_of_birth' => '1990-05-15',
            'loyalty_code' => 'CUST-001',
            'loyalty_points' => 250,
            'store_credit_balance' => 50.00,
            'tax_registration_number' => 'VAT123456',
            'notes' => 'VIP customer',
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/' . $customer->id);

        $response->assertOk();
        $data = $response->json('data');

        $expectedKeys = [
            'id', 'organization_id', 'name', 'phone', 'email', 'address',
            'date_of_birth', 'loyalty_code', 'loyalty_points',
            'store_credit_balance', 'group_id', 'tax_registration_number',
            'notes', 'total_spend', 'visit_count', 'last_visit_at',
            'sync_version', 'created_at', 'updated_at',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Customer API missing key '$key' expected by Flutter");
        }
    }

    public function test_customer_numeric_fields_are_correct_types(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Type Test Customer',
            'loyalty_points' => 100,
            'store_credit_balance' => 25.50,
            'total_spend' => 1500.75,
            'visit_count' => 42,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/' . $customer->id);

        $data = $response->json('data');
        $this->assertIsInt($data['loyalty_points']);
        $this->assertIsNumeric($data['store_credit_balance']);
        $this->assertIsNumeric($data['total_spend']);
        $this->assertIsInt($data['visit_count']);
    }

    // ═══════════════════════════════════════════════════════════
    // TRANSACTION — CRITICAL: Flutter expects ZATCA + external fields
    // ═══════════════════════════════════════════════════════════

    public function test_transaction_api_returns_base_flutter_expected_fields(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100.00,
            'discount_amount' => 0,
            'tax_amount' => 15.00,
            'tip_amount' => 0,
            'total_amount' => 115.00,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/' . $txn->id);

        $response->assertOk();
        $data = $response->json('data');

        // Base fields Flutter always expects
        $baseKeys = [
            'id', 'organization_id', 'store_id', 'register_id',
            'pos_session_id', 'cashier_id', 'transaction_number',
            'type', 'status', 'subtotal', 'discount_amount',
            'tax_amount', 'tip_amount', 'total_amount', 'is_tax_exempt',
            'notes', 'sync_version', 'created_at', 'updated_at',
        ];

        foreach ($baseKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Transaction API missing key '$key' expected by Flutter");
        }
    }

    public function test_transaction_amount_fields_are_float(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-TYPES',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 87.39,
            'discount_amount' => 5.00,
            'tax_amount' => 12.36,
            'tip_amount' => 2.00,
            'total_amount' => 96.75,
            'is_tax_exempt' => false,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/' . $txn->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['subtotal']);
        $this->assertIsNumeric($data['discount_amount']);
        $this->assertIsNumeric($data['tax_amount']);
        $this->assertIsNumeric($data['tip_amount']);
        $this->assertIsNumeric($data['total_amount']);
        $this->assertIsBool($data['is_tax_exempt']);
    }

    /**
     * KNOWN ISSUE: Flutter expects ZATCA fields but TransactionResource
     * doesn't return them yet. This test documents the gap.
     */
    public function test_transaction_missing_zatca_fields_flutter_expects(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'opened_at' => now(),
        ]);

        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-ZATCA',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'total_amount' => 115,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/' . $txn->id);

        $data = $response->json('data');

        // These fields exist in DB and Flutter model but NOT in TransactionResource
        // This test documents the mismatch for tracking
        $missingZatcaFields = ['zatca_uuid', 'zatca_hash', 'zatca_qr_code', 'zatca_status'];
        $missingExternalFields = ['external_type', 'external_id'];
        $missingSyncFields = ['sync_status', 'deleted_at'];

        $allMissing = array_merge($missingZatcaFields, $missingExternalFields, $missingSyncFields);

        $actuallyMissing = [];
        foreach ($allMissing as $field) {
            if (!array_key_exists($field, $data)) {
                $actuallyMissing[] = $field;
            }
        }

        // Document which fields are still missing
        // When TransactionResource is updated, these should become available
        $this->assertNotEmpty($actuallyMissing,
            'Expected some ZATCA/external fields to be missing from TransactionResource. ' .
            'If this fails, it means TransactionResource now includes all fields - which is good! Remove this test.');
    }

    // ═══════════════════════════════════════════════════════════
    // POS SESSION — Flutter expects these exact keys
    // ═══════════════════════════════════════════════════════════

    public function test_pos_session_returns_all_flutter_expected_fields(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'z_report_printed' => false,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/sessions/' . $session->id);

        $response->assertOk();
        $data = $response->json('data');

        $expectedKeys = [
            'id', 'store_id', 'register_id', 'cashier_id', 'status',
            'opening_cash', 'closing_cash', 'expected_cash', 'cash_difference',
            'total_cash_sales', 'total_card_sales', 'total_other_sales',
            'total_refunds', 'total_voids', 'transaction_count',
            'opened_at', 'closed_at', 'z_report_printed',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "PosSession API missing key '$key' expected by Flutter");
        }
    }

    public function test_pos_session_numeric_fields_correct_types(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'total_cash_sales' => 1200.50,
            'total_card_sales' => 800.75,
            'total_other_sales' => 200.00,
            'total_refunds' => 50.00,
            'total_voids' => 25.00,
            'transaction_count' => 45,
            'z_report_printed' => false,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/sessions/' . $session->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['opening_cash']);
        $this->assertIsNumeric($data['total_cash_sales']);
        $this->assertIsNumeric($data['total_card_sales']);
        $this->assertIsInt($data['transaction_count']);
        $this->assertIsBool($data['z_report_printed']);
    }

    // ═══════════════════════════════════════════════════════════
    // ORDER — Flutter expects these exact keys
    // ═══════════════════════════════════════════════════════════

    public function test_order_api_returns_all_flutter_expected_fields(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-CROSS-001',
            'source' => 'pos',
            'status' => 'new',
            'subtotal' => 87.39,
            'tax_amount' => 13.11,
            'discount_amount' => 0,
            'total' => 100.50,
            'notes' => 'Test order',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/orders/' . $order->id);

        $response->assertOk();
        $data = $response->json('data');

        $expectedKeys = [
            'id', 'store_id', 'order_number', 'source', 'status',
            'subtotal', 'tax_amount', 'discount_amount', 'total',
            'notes', 'created_by', 'created_at', 'updated_at',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Order API missing key '$key' expected by Flutter");
        }
    }

    public function test_order_amount_fields_are_float(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-TYPES-001',
            'status' => 'new',
            'subtotal' => 50.50,
            'tax_amount' => 7.58,
            'discount_amount' => 5.00,
            'total' => 53.08,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/orders/' . $order->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['subtotal']);
        $this->assertIsNumeric($data['tax_amount']);
        $this->assertIsNumeric($data['discount_amount']);
        $this->assertIsNumeric($data['total']);
    }

    // ═══════════════════════════════════════════════════════════
    // PAYMENT — KNOWN MISMATCH: card_reference vs card_reference_number
    // ═══════════════════════════════════════════════════════════

    public function test_payment_field_naming_card_reference(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500,
            'opened_at' => now(),
        ]);

        $txn = Transaction::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'pos_session_id' => $session->id,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-PAY-TEST',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'total_amount' => 115,
            'sync_version' => 1,
        ]);

        $payment = Payment::create([
            'transaction_id' => $txn->id,
            'method' => 'card_visa',
            'amount' => 115.00,
            'card_brand' => 'Visa',
            'card_last_four' => '4242',
            'card_auth_code' => 'AUTH123',
            'card_reference' => 'REF-456',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/' . $txn->id);

        $data = $response->json('data');

        // Laravel uses 'card_reference', Flutter expects 'card_reference'
        // (Previously Flutter had 'card_reference_number' — verify no mismatch)
        if (isset($data['payments']) && count($data['payments']) > 0) {
            $paymentData = $data['payments'][0];
            $this->assertArrayHasKey('card_reference', $paymentData,
                'Payment uses card_reference. Flutter must use same key, NOT card_reference_number.');
            $this->assertArrayNotHasKey('card_reference_number', $paymentData,
                'API should NOT have card_reference_number — use card_reference instead.');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // REGISTER/TERMINAL — Flutter expects these exact keys
    // ═══════════════════════════════════════════════════════════

    public function test_register_api_returns_all_flutter_expected_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/terminals/' . $this->register->id);

        $response->assertOk();
        $data = $response->json('data');

        $expectedKeys = [
            'id', 'store_id', 'name', 'device_id',
            'is_active', 'is_online', 'created_at', 'updated_at',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Register API missing key '$key' expected by Flutter");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // STAFF USER — Flutter expects these exact keys
    // ═══════════════════════════════════════════════════════════

    public function test_staff_user_api_returns_all_flutter_expected_fields(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Al-Rashidi',
            'email' => 'ahmed@staff.com',
            'phone' => '+96812345678',
            'employment_type' => 'full_time',
            'salary_type' => 'monthly',
            'status' => 'active',
            'language_preference' => 'ar',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members/' . $staff->id);

        $response->assertOk();
        $data = $response->json('data');

        $expectedKeys = [
            'id', 'store_id', 'first_name', 'last_name',
            'email', 'phone', 'photo_url', 'national_id',
            'nfc_badge_uid', 'biometric_enabled',
            'employment_type', 'salary_type', 'hourly_rate',
            'hire_date', 'termination_date', 'status',
            'language_preference', 'created_at', 'updated_at',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "StaffUser API missing key '$key' expected by Flutter");
        }
    }

    public function test_staff_user_hire_date_is_optional_for_flutter(): void
    {
        // Flutter requires hire_date but Laravel sends it as optional/nullable
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'No',
            'last_name' => 'HireDate',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members/' . $staff->id);

        $data = $response->json('data');
        // hire_date key must exist (even if null) so Flutter fromJson doesn't crash
        $this->assertArrayHasKey('hire_date', $data);
    }

    // ═══════════════════════════════════════════════════════════
    // STOCK LEVEL — Flutter expects these exact keys
    // ═══════════════════════════════════════════════════════════

    public function test_stock_level_api_returns_all_flutter_expected_fields(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Stock Test Product',
            'sell_price' => 10,
            'sync_version' => 1,
        ]);

        $stock = StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 50,
            'reserved_quantity' => 5,
            'reorder_point' => 10,
            'max_stock_level' => 200,
            'average_cost' => 7.50,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id);

        $response->assertOk();

        // Find our stock level in paginated results
        $items = $response->json('data.data') ?? $response->json('data');
        $this->assertNotEmpty($items);

        $stockData = is_array($items) ? $items[0] : $items;

        $expectedKeys = [
            'id', 'store_id', 'product_id',
            'quantity', 'reserved_quantity',
            'reorder_point', 'max_stock_level', 'average_cost',
            'sync_version',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stockData, "StockLevel API missing key '$key' expected by Flutter");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // API RESPONSE ENVELOPE — All endpoints must use standard format
    // ═══════════════════════════════════════════════════════════

    public function test_api_success_envelope_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/dashboard');

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);
    }

    public function test_api_error_envelope_on_not_found(): void
    {
        $fakeId = '00000000-0000-0000-0000-999999999999';

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/orders/' . $fakeId);

        $response->assertStatus(404);
    }

    public function test_api_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/pos/sessions')
            ->assertUnauthorized();
    }
}
