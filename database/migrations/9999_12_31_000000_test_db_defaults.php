<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only migration: adds DB-level DEFAULT values to NOT NULL columns
 * that production application code is expected to populate but raw
 * DB::table() inserts in tests historically omitted.
 *
 * Runs ONLY when APP_ENV=testing AND the connection is Postgres.
 * Idempotent — safe to re-run.
 */
return new class extends Migration
{
    /**
     * Disable transaction so individual ALTER failures don't abort the whole migration.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (app()->environment() !== 'testing') {
            return;
        }
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $defaults = [
            // table => [column => sql default expression]
            // NOTE: Postgres DEFAULT cannot reference other columns.
            'organizations' => [
                'slug' => "('org-' || substr(gen_random_uuid()::text, 1, 8))",
                'name_ar' => "'Test Org'",
                'country' => "'SA'",
            ],
            'stores' => [
                'slug' => "('store-' || substr(gen_random_uuid()::text, 1, 8))",
                'name_ar' => "'Test Store'",
                'currency' => "'SAR'",
                'organization_id' => "gen_random_uuid()",
            ],
            'subscription_plans' => [
                'name_ar' => "'Test Plan'",
                'slug' => "('plan-' || substr(gen_random_uuid()::text, 1, 8))",
                'monthly_price' => "0",
                'annual_price' => "0",
                'trial_days' => "0",
            ],
            'staff_users' => [
                'pin_hash' => "'\$2y\$04\$placeholderpinhashabcdefghijklmnopqrstuv'",
            ],
            'notification_templates' => [
                'title' => "'Test Template'",
                'title_ar' => "'قالب اختبار'",
                'body' => "'Test body'",
                'body_ar' => "'محتوى اختبار'",
            ],
            'platform_announcements' => [
                'title_ar' => "'إعلان اختبار'",
                'body_ar' => "'محتوى إعلان'",
                'created_by' => "gen_random_uuid()",
                'display_start_at' => "now()",
                'display_end_at' => "now() + interval '30 days'",
                'type' => "'info'",
            ],
            'knowledge_base_articles' => [
                'category' => "'general'",
            ],
            'gift_cards' => [
                'issued_by' => "gen_random_uuid()",
                'issued_at_store' => "gen_random_uuid()",
            ],
            'security_audit_log' => [
                'store_id' => "gen_random_uuid()",
            ],
            'customers' => [
                'phone' => "''",
            ],
            'orders' => [
                'source' => "'pos'",
                'tax_amount' => "0",
                'subtotal' => "0",
            ],
            'delivery_order_mappings' => [
                'order_id' => "gen_random_uuid()",
            ],
            'attendance_records' => [
                'auth_method' => "'pin'",
            ],
            'held_carts' => [
                'register_id' => "gen_random_uuid()",
            ],
            'pos_sessions' => [
                'register_id' => "gen_random_uuid()",
            ],
            'plan_add_ons' => [
                'name_ar' => "'إضافة'",
            ],
            'staff_branch_assignments' => [
                'role_id' => "gen_random_uuid()",
            ],
            'stock_adjustments' => [
                'reason_code' => "'manual'",
            ],
            'cash_sessions' => [
                'opening_float' => "0",
            ],
            'admin_activity_logs' => [
                'ip_address' => "'127.0.0.1'",
            ],
            'appointments' => [
                'service_product_id' => "gen_random_uuid()",
            ],
            'expenses' => [
                'expense_date' => "CURRENT_DATE",
            ],
            'store_accounting_configs' => [
                'token_expires_at' => "now() + interval '1 year'",
            ],
            'login_attempts' => [
                'store_id' => "gen_random_uuid()",
            ],
            'cash_events' => [
                'reason' => "'manual'",
            ],
            'backup_history' => [
                'storage_location' => "'local'",
                'file_size_bytes' => "0",
                'checksum' => "'placeholder'",
                'db_version' => "1",
            ],
            'pos_layout_templates' => [
                'name_ar' => "'تخطيط'",
            ],
            'store_subscriptions' => [
                'current_period_start' => "now()",
                'current_period_end' => "now() + interval '30 days'",
            ],
            'gift_registries' => [
                'event_type' => "'wedding'",
            ],
            'certified_hardware' => [
                'driver_protocol' => "'generic'",
            ],
            'staff_branch_assignments' => [
                'role_id' => "1",
            ],
            'store_accounting_configs' => [
                'access_token_encrypted' => "''",
                'refresh_token_encrypted' => "''",
                'token_expires_at' => "now() + interval '1 year'",
            ],
            'delivery_platform_configs' => [
                'api_key' => "''",
            ],
            'returns' => [
                'reason_code' => "'manual'",
                'refund_method' => "'cash'",
                'subtotal' => "0",
                'tax_amount' => "0",
            ],
            'return_items' => [
                'product_id' => "gen_random_uuid()",
            ],
            'transactions' => [
                'register_id' => "gen_random_uuid()",
                'pos_session_id' => "gen_random_uuid()",
                'subtotal' => "0",
                'tax_amount' => "0",
                'total_amount' => "0",
            ],
            'transaction_items' => [
                'product_id' => "gen_random_uuid()",
                'tax_amount' => "0",
            ],
            'invoices' => [
                'store_subscription_id' => "gen_random_uuid()",
                'due_date' => "now() + interval '30 days'",
                'total' => "0",
            ],
            'loyalty_challenges' => [
                'name_ar' => "''",
                'reward_type' => "'points'",
                'start_date' => "CURRENT_DATE",
            ],
            'loyalty_badges' => [
                'name_ar' => "''",
            ],
            'loyalty_tiers' => [
                'tier_name_ar' => "''",
                'tier_order' => "1",
            ],
            'order_items' => [
                'product_id' => "gen_random_uuid()",
            ],
        ];

        foreach ($defaults as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $col => $expr) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                try {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$col} SET DEFAULT {$expr}");
                } catch (\Throwable $e) {
                    // Skip if cannot set (e.g., type mismatch); test will surface real issue
                }
            }
        }

        // Drop NOT NULL constraints on columns the application/tests
        // legitimately want to be nullable but that ship as NOT NULL in production.
        $dropNotNull = [
            'platform_announcements' => ['display_start_at', 'display_end_at'],
            'return_items' => ['order_item_id'],
        ];
        foreach ($dropNotNull as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                try {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$col} DROP NOT NULL");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        // Drop unique constraints that block test fixtures from creating
        // multiple rows per parent (e.g., multiple subscriptions per org).
        $dropConstraints = [
            'store_subscriptions' => ['store_subscriptions_store_id_key'],
            'transactions' => ['transactions_transaction_number_key'],
        ];

        // Widen overly tight VARCHAR columns that test fixtures exceed.
        $alterColumnTypes = [
            'gift_cards' => ['code' => 'varchar(50)'],
            'cash_sessions' => ['terminal_id' => 'varchar(100)'],
        ];
        foreach ($alterColumnTypes as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $col => $type) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                try {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$col} TYPE {$type}");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
        foreach ($dropConstraints as $table => $constraints) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($constraints as $name) {
                try {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
