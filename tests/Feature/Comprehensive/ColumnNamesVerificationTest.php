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
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Column Names Verification Tests
 *
 * Verifies that code references use correct database column names.
 * Catches bugs like clock_in vs clock_in_at, incorrect table references, etc.
 */
class ColumnNamesVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ═══════════════════════════════════════════════════════════════
    // ATTENDANCE RECORDS — clock_in_at / clock_out_at (NOT clock_in)
    // ═══════════════════════════════════════════════════════════════

    public function test_attendance_records_table_has_clock_in_at_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('attendance_records', 'clock_in_at'),
            'attendance_records must have clock_in_at column (not clock_in)'
        );
    }

    public function test_attendance_records_table_has_clock_out_at_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('attendance_records', 'clock_out_at'),
            'attendance_records must have clock_out_at column (not clock_out)'
        );
    }

    public function test_attendance_records_does_not_have_clock_in_without_at(): void
    {
        $this->assertFalse(
            Schema::hasColumn('attendance_records', 'clock_in'),
            'attendance_records should NOT have clock_in — use clock_in_at instead'
        );
    }

    public function test_attendance_records_does_not_have_clock_out_without_at(): void
    {
        $this->assertFalse(
            Schema::hasColumn('attendance_records', 'clock_out'),
            'attendance_records should NOT have clock_out — use clock_out_at instead'
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // ORGANIZATIONS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_organizations_table_has_expected_columns(): void
    {
        $expected = ['id', 'name', 'business_type', 'country'];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('organizations', $col),
                "organizations table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STORES TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_stores_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'organization_id', 'name', 'business_type',
            'currency', 'is_active', 'is_main_branch',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('stores', $col),
                "stores table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // USERS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_users_table_has_password_hash_not_password(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'password_hash'),
            'users table uses password_hash (not password)'
        );
    }

    public function test_users_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'store_id', 'organization_id', 'name', 'email',
            'password_hash', 'role', 'is_active',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('users', $col),
                "users table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // REGISTERS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_registers_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'store_id', 'name', 'device_id',
            'is_active', 'is_online',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('registers', $col),
                "registers table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // PRODUCTS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_products_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'organization_id', 'category_id', 'name', 'name_ar',
            'description', 'description_ar', 'sku', 'barcode',
            'sell_price', 'cost_price', 'unit', 'tax_rate',
            'is_weighable', 'tare_weight', 'is_active', 'is_combo',
            'age_restricted', 'image_url', 'sync_version',
            'created_at', 'updated_at', 'deleted_at',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('products', $col),
                "products table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CATEGORIES TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_categories_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'organization_id', 'parent_id', 'name', 'name_ar',
            'image_url', 'sort_order', 'is_active', 'sync_version',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('categories', $col),
                "categories table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // TRANSACTIONS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_transactions_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'organization_id', 'store_id', 'register_id',
            'pos_session_id', 'cashier_id', 'customer_id',
            'transaction_number', 'type', 'status',
            'subtotal', 'discount_amount', 'tax_amount', 'tip_amount', 'total_amount',
            'is_tax_exempt', 'return_transaction_id', 'notes',
            'sync_version', 'created_at', 'updated_at',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('transactions', $col),
                "transactions table missing column: $col"
            );
        }
    }

    public function test_transactions_table_has_zatca_columns(): void
    {
        // ZATCA compliance columns must exist for Saudi tax reporting
        $zatcaCols = ['zatca_uuid', 'zatca_hash', 'zatca_qr_code', 'zatca_status'];
        foreach ($zatcaCols as $col) {
            $this->assertTrue(
                Schema::hasColumn('transactions', $col),
                "transactions table missing ZATCA column: $col"
            );
        }
    }

    public function test_transactions_table_has_external_fields(): void
    {
        $externalCols = ['external_type', 'external_id'];
        foreach ($externalCols as $col) {
            $this->assertTrue(
                Schema::hasColumn('transactions', $col),
                "transactions table missing external column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // PAYMENTS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_payments_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'transaction_id', 'method', 'amount',
            'cash_tendered', 'change_given', 'tip_amount',
            'card_brand', 'card_last_four', 'card_auth_code',
            'card_reference', 'gift_card_code', 'coupon_code',
            'loyalty_points_used', 'created_at',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('payments', $col),
                "payments table missing column: $col"
            );
        }
    }

    public function test_payments_uses_card_reference_not_card_reference_number(): void
    {
        $this->assertTrue(
            Schema::hasColumn('payments', 'card_reference'),
            'payments uses card_reference'
        );
        $this->assertFalse(
            Schema::hasColumn('payments', 'card_reference_number'),
            'payments should NOT have card_reference_number — use card_reference'
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // POS SESSIONS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_pos_sessions_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'store_id', 'register_id', 'cashier_id', 'status',
            'opening_cash', 'closing_cash', 'expected_cash', 'cash_difference',
            'total_cash_sales', 'total_card_sales', 'total_other_sales',
            'total_refunds', 'total_voids', 'transaction_count',
            'opened_at', 'closed_at', 'z_report_printed',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('pos_sessions', $col),
                "pos_sessions table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ORDERS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_orders_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'store_id', 'order_number', 'source', 'status',
            'subtotal', 'tax_amount', 'discount_amount', 'total',
            'notes', 'customer_notes', 'external_order_id',
            'delivery_address', 'created_by',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('orders', $col),
                "orders table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CUSTOMERS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_customers_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'organization_id', 'name', 'phone', 'email', 'address',
            'date_of_birth', 'loyalty_code', 'loyalty_points',
            'store_credit_balance', 'group_id', 'tax_registration_number',
            'notes', 'total_spend', 'visit_count', 'last_visit_at',
            'sync_version',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('customers', $col),
                "customers table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STAFF USERS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_staff_users_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'store_id', 'first_name', 'last_name',
            'email', 'phone', 'photo_url', 'national_id',
            'nfc_badge_uid', 'biometric_enabled',
            'employment_type', 'salary_type', 'hourly_rate',
            'hire_date', 'termination_date', 'status',
            'language_preference',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('staff_users', $col),
                "staff_users table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STOCK LEVELS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_stock_levels_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'store_id', 'product_id',
            'quantity', 'reserved_quantity',
            'reorder_point', 'max_stock_level', 'average_cost',
            'sync_version',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('stock_levels', $col),
                "stock_levels table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SUBSCRIPTION & BILLING TABLES
    // ═══════════════════════════════════════════════════════════════

    public function test_subscription_plans_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'name', 'name_ar', 'slug',
            'monthly_price', 'annual_price',
            'trial_days', 'is_active',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('subscription_plans', $col),
                "subscription_plans table missing column: $col"
            );
        }
    }

    public function test_store_subscriptions_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'organization_id', 'subscription_plan_id',
            'status', 'billing_cycle',
            'current_period_start', 'current_period_end',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('store_subscriptions', $col),
                "store_subscriptions table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SYNC LOGS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_sync_log_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'store_id', 'terminal_id', 'direction',
            'records_count', 'duration_ms', 'status',
        ];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('sync_log', $col),
                "sync_log table missing column: $col"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STORE SETTINGS TABLE
    // ═══════════════════════════════════════════════════════════════

    public function test_store_settings_table_exists_and_has_core_columns(): void
    {
        if (!Schema::hasTable('store_settings')) {
            $this->markTestSkipped('store_settings table not in SQLite test schema');
        }

        $expected = ['id', 'store_id'];
        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('store_settings', $col),
                "store_settings table missing column: $col"
            );
        }
    }
}
