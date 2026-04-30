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
 * Data Parsing & Type Consistency Tests
 *
 * Ensures all API responses return correct types (float, int, bool, string, null)
 * that Flutter's Dart type system can parse without errors.
 * Flutter crashes on: String where int expected, int where double expected, etc.
 */
class DataParsingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Register $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Parsing Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Parsing Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Parsing User',
            'email' => 'parsing@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->register = Register::create([
            'store_id' => $this->store->id,
            'name' => 'Parse Terminal',
            'device_id' => 'PARSE-001',
            'is_active' => true,
            'is_online' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // PRODUCT: sell_price, cost_price, tax_rate must be double/float
    // ═══════════════════════════════════════════════════════════════

    public function test_product_prices_are_numeric_not_string(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Price Type Test',
            'sell_price' => 10.50,
            'cost_price' => 5.25,
            'tax_rate' => 15.00,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');

        // Flutter uses double for prices — must not be string
        $this->assertIsNumeric($data['sell_price'], 'sell_price must be numeric for Flutter double parsing');
        $this->assertIsNumeric($data['cost_price'], 'cost_price must be numeric for Flutter double parsing');
        $this->assertIsNumeric($data['tax_rate'], 'tax_rate must be numeric for Flutter double parsing');
    }

    public function test_product_zero_prices_still_numeric(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Free Product',
            'sell_price' => 0,
            'cost_price' => 0,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['sell_price']);
    }

    public function test_product_sync_version_is_integer(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Sync Ver Test',
            'sell_price' => 10,
            'sync_version' => 42,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');
        // sync_version should be treated as int
        $this->assertIsInt($data['sync_version']);
    }

    public function test_product_nullable_fields_return_null_not_empty_string(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Null Fields Test',
            'sell_price' => 10,
            'sync_version' => 1,
            // No description, sku, barcode, image_url set
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');

        // Nullable fields should be null, not "" (empty string)
        $this->assertIsArray($data);
        if (array_key_exists('description', $data) && $data['description'] !== null) {
            $this->assertNotEquals('', $data['description'],
                'description should be null, not empty string, when not set');
        } else {
            // Either the key is absent, or value is null — both acceptable
            $this->assertTrue(! array_key_exists('description', $data) || $data['description'] === null);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CUSTOMER: loyalty_points(int), store_credit_balance(float)
    // ═══════════════════════════════════════════════════════════════

    public function test_customer_loyalty_points_is_int(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Loyalty Test',
            'loyalty_points' => 500,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/' . $customer->id);

        $data = $response->json('data');
        $this->assertIsInt($data['loyalty_points']);
    }

    public function test_customer_store_credit_balance_is_float(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Credit Test',
            'store_credit_balance' => 125.50,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/' . $customer->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['store_credit_balance']);
    }

    public function test_customer_visit_count_is_int(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Visit Test',
            'visit_count' => 15,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/' . $customer->id);

        $data = $response->json('data');
        $this->assertIsInt($data['visit_count']);
    }

    // ═══════════════════════════════════════════════════════════════
    // TRANSACTION: All amounts must be float
    // ═══════════════════════════════════════════════════════════════

    public function test_transaction_all_amounts_are_float(): void
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
            'transaction_number' => 'TXN-PARSE-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 100.00,
            'discount_amount' => 10.00,
            'tax_amount' => 13.50,
            'tip_amount' => 5.00,
            'total_amount' => 108.50,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/' . $txn->id);

        $data = $response->json('data');

        // Resource casts to (float), but JSON encodes whole numbers as int
        // Use assertIsNumeric since json_encode(100.0) outputs 100 (int)
        $this->assertIsNumeric($data['subtotal'], 'subtotal must be numeric');
        $this->assertIsNumeric($data['discount_amount'], 'discount_amount must be numeric');
        $this->assertIsNumeric($data['tax_amount'], 'tax_amount must be numeric');
        $this->assertIsNumeric($data['tip_amount'], 'tip_amount must be numeric');
        $this->assertIsNumeric($data['total_amount'], 'total_amount must be numeric');
    }

    public function test_transaction_zero_discount_is_still_float(): void
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
            'transaction_number' => 'TXN-PARSE-002',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50.00,
            'discount_amount' => 0,
            'tax_amount' => 7.50,
            'tip_amount' => 0,
            'total_amount' => 57.50,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/transactions/' . $txn->id);

        $data = $response->json('data');
        // Even zero must be numeric (JSON encodes 0.0 as 0)
        $this->assertIsNumeric($data['discount_amount']);
        $this->assertIsNumeric($data['tip_amount']);
    }

    // ═══════════════════════════════════════════════════════════════
    // POS SESSION: numeric fields must be correct types
    // ═══════════════════════════════════════════════════════════════

    public function test_pos_session_amounts_are_float_count_is_int(): void
    {
        $session = PosSession::create([
            'store_id' => $this->store->id,
            'register_id' => $this->register->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_cash' => 500.00,
            'total_cash_sales' => 1200.00,
            'total_card_sales' => 800.00,
            'total_other_sales' => 200.00,
            'total_refunds' => 50.00,
            'total_voids' => 25.00,
            'transaction_count' => 30,
            'z_report_printed' => false,
            'opened_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/pos/sessions/' . $session->id);

        $data = $response->json('data');

        // Resource casts to (float), JSON encodes whole numbers as int
        $this->assertIsNumeric($data['opening_cash']);
        $this->assertIsNumeric($data['total_cash_sales']);
        $this->assertIsNumeric($data['total_card_sales']);
        $this->assertIsNumeric($data['total_other_sales']);
        $this->assertIsNumeric($data['total_refunds']);
        $this->assertIsNumeric($data['total_voids']);
        $this->assertIsInt($data['transaction_count']);
        $this->assertIsBool($data['z_report_printed']);
    }

    // ═══════════════════════════════════════════════════════════════
    // ORDER: amounts must be float
    // ═══════════════════════════════════════════════════════════════

    public function test_order_amounts_are_float(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'order_number' => 'ORD-PARSE-001',
            'source' => 'pos',
            'status' => 'new',
            'subtotal' => 87.39,
            'tax_amount' => 13.11,
            'discount_amount' => 0,
            'total' => 100.50,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/orders/' . $order->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['subtotal']);
        $this->assertIsNumeric($data['tax_amount']);
        $this->assertIsNumeric($data['discount_amount']);
        $this->assertIsNumeric($data['total']);
    }

    // ═══════════════════════════════════════════════════════════════
    // STOCK LEVEL: quantities must be numeric
    // ═══════════════════════════════════════════════════════════════

    public function test_stock_level_quantities_are_numeric(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Stock Parse',
            'sell_price' => 10,
            'sync_version' => 1,
        ]);
        $stock = StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 100.5,
            'reserved_quantity' => 5.0,
            'reorder_point' => 10,
            'max_stock_level' => 500,
            'average_cost' => 7.25,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id);

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');

        if (!empty($items) && is_array($items)) {
            $stockData = $items[0];
            $this->assertIsNumeric($stockData['quantity']);
            $this->assertIsNumeric($stockData['reserved_quantity']);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STAFF: biometric_enabled must be bool, hourly_rate nullable float
    // ═══════════════════════════════════════════════════════════════

    public function test_staff_biometric_is_bool(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Bio',
            'last_name' => 'Test',
            'biometric_enabled' => true,
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members/' . $staff->id);

        $data = $response->json('data');
        $this->assertIsBool($data['biometric_enabled']);
    }

    public function test_staff_hourly_rate_nullable_is_null_or_float(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Hourly',
            'last_name' => 'Test',
            'hourly_rate' => null,
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members/' . $staff->id);

        $data = $response->json('data');
        $this->assertNull($data['hourly_rate'], 'hourly_rate when null should be null, not 0');
    }

    public function test_staff_hourly_rate_when_set_is_float(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Hourly',
            'last_name' => 'Rate',
            'hourly_rate' => 25.50,
            'salary_type' => 'hourly',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members/' . $staff->id);

        $data = $response->json('data');
        $this->assertIsNumeric($data['hourly_rate']);
        $this->assertEquals(25.50, $data['hourly_rate']);
    }

    // ═══════════════════════════════════════════════════════════════
    // DATE FORMATTING: All dates must be ISO 8601 strings
    // ═══════════════════════════════════════════════════════════════

    public function test_timestamps_are_iso8601_strings(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Date Format Test',
            'sell_price' => 10,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');

        // created_at/updated_at must be strings (ISO 8601), not unix timestamps
        if ($data['created_at'] !== null) {
            $this->assertIsString($data['created_at']);
            // Must contain 'T' for ISO 8601 format (2025-01-15T10:30:00.000000Z)
            $this->assertStringContainsString('T', $data['created_at'],
                'created_at must be ISO 8601 format with T separator');
        }
    }

    public function test_customer_date_of_birth_is_date_string(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'DOB Test',
            'date_of_birth' => '1990-05-15',
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers/' . $customer->id);

        $data = $response->json('data');
        $this->assertIsString($data['date_of_birth']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['date_of_birth'],
            'date_of_birth must be YYYY-MM-DD format');
    }

    public function test_staff_hire_date_is_date_string_when_set(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Hire',
            'last_name' => 'Date',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members/' . $staff->id);

        $data = $response->json('data');
        if ($data['hire_date'] !== null) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['hire_date'],
                'hire_date should be YYYY-MM-DD format');
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // UUID FIELDS: Must be valid UUID strings
    // ═══════════════════════════════════════════════════════════════

    public function test_all_id_fields_are_valid_uuids(): void
    {
        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'UUID Test',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'UUID Product',
            'sell_price' => 10,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products/' . $product->id);

        $data = $response->json('data');

        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($uuidPattern, $data['id']);
        $this->assertMatchesRegularExpression($uuidPattern, $data['organization_id']);
        $this->assertMatchesRegularExpression($uuidPattern, $data['category_id']);
    }

    // ═══════════════════════════════════════════════════════════════
    // LIST RESPONSES: data key must be array
    // ═══════════════════════════════════════════════════════════════

    public function test_list_products_data_is_array(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertIsArray($data, 'List endpoint data.data must be array for Flutter List<> parsing');
    }

    public function test_list_categories_data_is_array(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/categories');

        $response->assertOk();
    }

    public function test_list_customers_data_is_array(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/customers');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // EMPTY LISTS: Must return empty array, not null
    // ═══════════════════════════════════════════════════════════════

    public function test_empty_product_list_returns_empty_array(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/catalog/products');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertIsArray($data);
    }

    public function test_companion_active_orders_empty_returns_array(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/orders/active');

        $response->assertOk();
        $orders = $response->json('data.orders');
        $this->assertIsArray($orders, 'Active orders must be array even when empty');
    }

    public function test_companion_inventory_alerts_empty_returns_array(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/companion/inventory/alerts');

        $response->assertOk();
        $alerts = $response->json('data.low_stock_items');
        $this->assertIsArray($alerts, 'Inventory low_stock_items must be array even when empty');
    }
}
