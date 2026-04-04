<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Comprehensive seed data for ALL 255 tables.
 * Depends on TestDataSeeder having run first (org, store, admin, owner).
 *
 * Run:  php artisan db:seed --class=ComprehensiveTestDataSeeder
 */
class ComprehensiveTestDataSeeder extends Seeder
{
    // Shared references (populated in run())
    private string $orgId;
    private string $storeId;
    private string $ownerId;
    private string $adminId;
    private string $proPlanId;
    private string $terminalId;

    /**
     * Insert a row or return existing ID based on unique columns.
     */
    private function getOrInsert(string $table, array $uniqueKeys, array $data): string
    {
        $query = DB::table($table);
        foreach ($uniqueKeys as $k => $v) {
            $query->where($k, $v);
        }
        $existing = $query->value('id');
        if ($existing) {
            return $existing;
        }
        return DB::table($table)->insertGetId(array_merge($uniqueKeys, $data));
    }

    public function run(): void
    {
        // ── Lookup data from TestDataSeeder ─────────────────────
        $org = DB::table('organizations')->where('slug', 'ostora-supermarket')->first();
        $store = DB::table('stores')->where('slug', 'ostora-main')->first();
        $owner = DB::table('users')->where('email', 'owner@ostora.sa')->first();
        $admin = DB::table('admin_users')->where('email', 'admin@thawani.om')->first();
        $proPlan = DB::table('subscription_plans')->where('slug', 'professional')->first();

        if (!$org || !$store || !$owner || !$admin || !$proPlan) {
            $this->command->error('TestDataSeeder must run first. Run: php artisan db:seed --class=TestDataSeeder');
            return;
        }

        $this->orgId = $org->id;
        $this->storeId = $store->id;
        $this->ownerId = $owner->id;
        $this->adminId = $admin->id;
        $this->proPlanId = $proPlan->id;
        $this->terminalId = (string) Str::uuid();

        // ── Clean up any data from a previous run ───────────────
        $this->cleanupPreviousRun();

        // ── Seed in dependency order ────────────────────────────
        $this->seedSystemConfig();
        $this->seedContentOnboarding();
        $this->seedSubscriptionExtras();
        $this->seedSecondStore();
        $this->seedStaffAndRoles();
        $this->seedSecurityPolicies();
        $this->seedCatalog();
        $this->seedInventory();
        $this->seedCustomers();
        $this->seedPromotions();
        $this->seedPosAndTransactions();
        $this->seedOrdersAndFulfillment();
        $this->seedPaymentsAndFinancial();
        $this->seedNotifications();
        $this->seedDeliveryIntegrations();
        $this->seedAccountingIntegration();
        $this->seedThawaniIntegration();
        $this->seedZatcaCompliance();
        $this->seedPosCustomization();
        $this->seedLabels();
        $this->seedHardware();
        $this->seedBackupAndSync();
        $this->seedAnalytics();
        $this->seedSupport();
        $this->seedAnnouncements();
        $this->seedAppUpdates();
        $this->seedIndustryRestaurant();
        $this->seedIndustryPharmacy();
        $this->seedIndustryBakery();
        $this->seedIndustryElectronics();
        $this->seedIndustryFlorist();
        $this->seedIndustryJewelry();

        $this->command->info("\n✅ Comprehensive seed data created successfully!");
    }

    // ─────────────────────────────────────────────────────────────
    // CLEANUP PREVIOUS RUN (idempotent re-runs)
    // ─────────────────────────────────────────────────────────────
    private function cleanupPreviousRun(): void
    {
        $this->command->info('Cleaning up data from previous run...');

        $storeId = $this->storeId;
        $orgId = $this->orgId;
        $ownerId = $this->ownerId;

        // Get second store if exists
        $branch2Id = DB::table('stores')->where('slug', 'ostora-branch-2')->value('id');

        // Get IDs we'll need for cascading deletes
        $productIds = DB::table('products')->where('organization_id', $orgId)->pluck('id')->toArray();
        $customerIds = DB::table('customers')->where('organization_id', $orgId)->pluck('id')->toArray();
        $staffIds = DB::table('staff_users')->where('store_id', $storeId)->pluck('id')->toArray();
        $orderIds = DB::table('orders')->where('store_id', $storeId)->pluck('id')->toArray();
        $txnIds = DB::table('transactions')->where('store_id', $storeId)->pluck('id')->toArray();
        $roleIds = DB::table('roles')->where('store_id', $storeId)->pluck('id')->toArray();
        $promoIds = DB::table('promotions')->where('organization_id', $orgId)->pluck('id')->toArray();
        $returnIds = DB::table('returns')->where('store_id', $storeId)->pluck('id')->toArray();

        // ── Industry tables (leaf-level, no dependents) ─────────
        DB::table('buyback_transactions')->where('store_id', $storeId)->delete();
        DB::table('jewelry_product_details')->whereIn('product_id', $productIds)->delete();
        DB::table('daily_metal_rates')->where('store_id', $storeId)->delete();
        DB::table('flower_subscriptions')->where('store_id', $storeId)->delete();
        DB::table('flower_freshness_log')->where('store_id', $storeId)->delete();
        DB::table('flower_arrangements')->where('store_id', $storeId)->delete();
        DB::table('trade_in_records')->where('store_id', $storeId)->delete();
        DB::table('repair_jobs')->where('store_id', $storeId)->delete();
        DB::table('device_imei_records')->where('store_id', $storeId)->delete();
        DB::table('custom_cake_orders')->where('store_id', $storeId)->delete();
        DB::table('production_schedules')->where('store_id', $storeId)->delete();
        DB::table('bakery_recipes')->where('store_id', $storeId)->delete();
        DB::table('prescriptions')->where('store_id', $storeId)->delete();
        DB::table('drug_schedules')->whereIn('product_id', $productIds)->delete();
        DB::table('open_tabs')->where('store_id', $storeId)->delete();
        DB::table('table_reservations')->where('store_id', $storeId)->delete();
        DB::table('kitchen_tickets')->where('store_id', $storeId)->delete();
        DB::table('restaurant_tables')->where('store_id', $storeId)->delete();

        // ── App updates ─────────────────────────────────────────
        DB::table('app_update_stats')->where('store_id', $storeId)->delete();
        DB::table('app_releases')->where('version_number', '1.0.0')->where('platform', 'android')->delete();

        // ── Announcements ───────────────────────────────────────
        $subId = DB::table('store_subscriptions')->where('store_id', $storeId)->value('id');
        if ($subId) {
            DB::table('payment_reminders')->where('store_subscription_id', $subId)->delete();
        }
        DB::table('platform_announcements')->where('created_by', $this->adminId)->delete();

        // ── Support ─────────────────────────────────────────────
        $ticketIds = DB::table('support_tickets')->where('store_id', $storeId)->pluck('id')->toArray();
        DB::table('support_ticket_messages')->whereIn('support_ticket_id', $ticketIds)->delete();
        DB::table('support_tickets')->where('store_id', $storeId)->delete();
        DB::table('canned_responses')->where('created_by', $this->adminId)->delete();

        // ── Analytics ───────────────────────────────────────────
        DB::table('store_health_snapshots')->where('store_id', $storeId)->delete();
        DB::table('feature_adoption_stats')->where('feature_key', 'loyalty_program')->delete();
        DB::table('platform_plan_stats')->where('subscription_plan_id', $this->proPlanId)->delete();
        DB::table('platform_daily_stats')->where('date', now()->toDateString())->delete();
        DB::table('product_sales_summary')->where('store_id', $storeId)->delete();
        DB::table('daily_sales_summary')->where('store_id', $storeId)->delete();

        // ── Backup & Sync ───────────────────────────────────────
        DB::table('provider_backup_status')->where('store_id', $storeId)->delete();
        DB::table('update_rollouts')->where('version', '1.1.0')->delete();
        DB::table('sync_log')->where('store_id', $storeId)->delete();
        DB::table('database_backups')->where('backup_type', 'automated')->delete();
        DB::table('backup_history')->where('store_id', $storeId)->delete();

        // ── Hardware ────────────────────────────────────────────
        DB::table('implementation_fees')->where('store_id', $storeId)->delete();
        DB::table('hardware_sales')->where('store_id', $storeId)->delete();
        DB::table('hardware_event_log')->where('store_id', $storeId)->delete();
        DB::table('hardware_configurations')->where('store_id', $storeId)->delete();

        // ── Labels ──────────────────────────────────────────────
        DB::table('label_print_history')->where('store_id', $storeId)->delete();
        DB::table('label_templates')->where('organization_id', $orgId)->delete();

        // ── POS Customization ───────────────────────────────────
        DB::table('translation_overrides')->where('store_id', $storeId)->delete();
        DB::table('signage_playlists')->where('store_id', $storeId)->delete();
        DB::table('cfd_configurations')->where('store_id', $storeId)->delete();
        DB::table('user_preferences')->where('user_id', $ownerId)->delete();
        DB::table('quick_access_configs')->where('store_id', $storeId)->delete();
        DB::table('receipt_templates')->where('store_id', $storeId)->delete();
        DB::table('pos_customization_settings')->where('store_id', $storeId)->delete();

        // ── ZATCA ───────────────────────────────────────────────
        DB::table('zatca_invoices')->where('store_id', $storeId)->delete();
        DB::table('zatca_certificates')->where('store_id', $storeId)->delete();

        // ── Thawani ─────────────────────────────────────────────
        DB::table('thawani_settlements')->where('store_id', $storeId)->delete();
        DB::table('thawani_product_mappings')->where('store_id', $storeId)->delete();
        DB::table('thawani_store_config')->where('store_id', $storeId)->delete();
        DB::table('thawani_marketplace_config')->whereNotNull('api_version')->delete();

        // ── Accounting ──────────────────────────────────────────
        DB::table('auto_export_configs')->where('store_id', $storeId)->delete();
        DB::table('accounting_exports')->where('store_id', $storeId)->delete();
        DB::table('account_mappings')->where('store_id', $storeId)->delete();
        DB::table('store_accounting_configs')->where('store_id', $storeId)->delete();
        DB::table('accounting_integration_configs')->where('provider_name', 'quickbooks')->delete();

        // ── Delivery ────────────────────────────────────────────
        DB::table('delivery_order_mappings')->whereIn('order_id', $orderIds)->delete();
        DB::table('platform_delivery_integrations')->where('platform_slug', 'jahez')->delete();
        DB::table('delivery_platform_configs')->where('store_id', $storeId)->delete();
        DB::table('store_delivery_platforms')->where('store_id', $storeId)->delete();
        $platformIds = DB::table('delivery_platforms')->whereIn('slug', ['jahez', 'hungerstation', 'toyou'])->pluck('id')->toArray();
        DB::table('delivery_platform_fields')->whereIn('delivery_platform_id', $platformIds)->delete();
        DB::table('delivery_platforms')->whereIn('slug', ['jahez', 'hungerstation', 'toyou'])->delete();

        // ── Notifications ───────────────────────────────────────
        DB::table('fcm_tokens')->where('user_id', $ownerId)->delete();
        DB::table('notification_preferences')->where('user_id', $ownerId)->delete();
        DB::table('notifications')->where('notifiable_id', $ownerId)->delete();
        DB::table('notification_provider_status')->whereIn('provider', ['firebase', 'unifonic', 'sendgrid'])->delete();
        DB::table('notification_templates')->whereIn('event_key', ['order.new', 'stock.low', 'subscription.expiring'])->delete();

        // ── Payments & Financial ────────────────────────────────
        DB::table('refunds')->whereIn('return_id', $returnIds)->delete();
        $giftCardIds = DB::table('gift_cards')->where('organization_id', $orgId)->pluck('id')->toArray();
        DB::table('gift_card_transactions')->whereIn('gift_card_id', $giftCardIds)->delete();
        DB::table('gift_cards')->where('organization_id', $orgId)->delete();
        DB::table('payments')->whereIn('transaction_id', $txnIds)->delete();

        // ── Orders & Fulfillment ────────────────────────────────
        DB::table('pending_orders')->where('store_id', $storeId)->delete();
        DB::table('return_items')->whereIn('return_id', $returnIds)->delete();
        DB::table('returns')->where('store_id', $storeId)->delete();
        DB::table('order_delivery_info')->whereIn('order_id', $orderIds)->delete();
        DB::table('order_status_history')->whereIn('order_id', $orderIds)->delete();
        DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
        DB::table('orders')->where('store_id', $storeId)->delete();

        // ── POS & Transactions ──────────────────────────────────
        DB::table('expenses')->where('store_id', $storeId)->delete();
        $cashSessionIds = DB::table('cash_sessions')->where('store_id', $storeId)->pluck('id')->toArray();
        DB::table('cash_events')->whereIn('cash_session_id', $cashSessionIds)->delete();
        DB::table('cash_sessions')->where('store_id', $storeId)->delete();
        DB::table('held_carts')->where('store_id', $storeId)->delete();
        DB::table('transaction_items')->whereIn('transaction_id', $txnIds)->delete();
        DB::table('transactions')->where('store_id', $storeId)->delete();
        DB::table('pos_sessions')->where('store_id', $storeId)->delete();

        // ── Promotions ──────────────────────────────────────────
        DB::table('promotion_customer_groups')->whereIn('promotion_id', $promoIds)->delete();
        DB::table('promotion_categories')->whereIn('promotion_id', $promoIds)->delete();
        DB::table('coupon_codes')->whereIn('promotion_id', $promoIds)->delete();
        DB::table('promotions')->where('organization_id', $orgId)->delete();

        // ── Customers ───────────────────────────────────────────
        DB::table('gift_registries')->where('store_id', $storeId)->delete();
        DB::table('wishlists')->where('store_id', $storeId)->delete();
        DB::table('customer_challenge_progress')->whereIn('customer_id', $customerIds)->delete();
        DB::table('customer_badges')->whereIn('customer_id', $customerIds)->delete();
        DB::table('loyalty_tiers')->where('store_id', $storeId)->delete();
        DB::table('loyalty_challenges')->where('store_id', $storeId)->delete();
        DB::table('loyalty_badges')->where('store_id', $storeId)->delete();
        DB::table('store_credit_transactions')->whereIn('customer_id', $customerIds)->delete();
        DB::table('loyalty_transactions')->whereIn('customer_id', $customerIds)->delete();
        DB::table('loyalty_config')->where('organization_id', $orgId)->delete();
        DB::table('customers')->where('organization_id', $orgId)->delete();
        DB::table('customer_groups')->where('organization_id', $orgId)->delete();

        // ── Inventory ───────────────────────────────────────────
        DB::table('recipe_ingredients')->whereIn('recipe_id', DB::table('recipes')->where('organization_id', $orgId)->pluck('id'))->delete();
        DB::table('recipes')->where('organization_id', $orgId)->delete();
        if ($branch2Id) {
            $transferIds = DB::table('stock_transfers')->where('organization_id', $orgId)->pluck('id')->toArray();
            DB::table('stock_transfer_items')->whereIn('stock_transfer_id', $transferIds)->delete();
            DB::table('stock_transfers')->where('organization_id', $orgId)->delete();
        }
        $adjIds = DB::table('stock_adjustments')->where('store_id', $storeId)->pluck('id')->toArray();
        DB::table('stock_adjustment_items')->whereIn('stock_adjustment_id', $adjIds)->delete();
        DB::table('stock_adjustments')->where('store_id', $storeId)->delete();
        $poIds = DB::table('purchase_orders')->where('organization_id', $orgId)->pluck('id')->toArray();
        DB::table('purchase_order_items')->whereIn('purchase_order_id', $poIds)->delete();
        DB::table('purchase_orders')->where('organization_id', $orgId)->delete();
        DB::table('stock_batches')->where('store_id', $storeId)->delete();
        $grIds = DB::table('goods_receipts')->where('store_id', $storeId)->pluck('id')->toArray();
        DB::table('goods_receipt_items')->whereIn('goods_receipt_id', $grIds)->delete();
        DB::table('goods_receipts')->where('store_id', $storeId)->delete();
        DB::table('stock_movements')->where('store_id', $storeId)->delete();
        DB::table('stock_levels')->where('store_id', $storeId)->delete();

        // ── Catalog ─────────────────────────────────────────────
        DB::table('internal_barcode_sequence')->where('store_id', $storeId)->delete();
        DB::table('product_images')->whereIn('product_id', $productIds)->delete();
        DB::table('store_prices')->where('store_id', $storeId)->delete();
        $comboIds = DB::table('combo_products')->whereIn('product_id', $productIds)->pluck('id')->toArray();
        DB::table('combo_product_items')->whereIn('combo_product_id', $comboIds)->delete();
        DB::table('combo_products')->whereIn('product_id', $productIds)->delete();
        DB::table('product_suppliers')->whereIn('product_id', $productIds)->delete();
        DB::table('suppliers')->where('organization_id', $orgId)->delete();
        $modGroupIds = DB::table('modifier_groups')->whereIn('product_id', $productIds)->pluck('id')->toArray();
        DB::table('modifier_options')->whereIn('modifier_group_id', $modGroupIds)->delete();
        DB::table('modifier_groups')->whereIn('product_id', $productIds)->delete();
        DB::table('product_variants')->whereIn('product_id', $productIds)->delete();
        DB::table('product_barcodes')->whereIn('product_id', $productIds)->delete();
        DB::table('products')->where('organization_id', $orgId)->delete();
        DB::table('product_variant_groups')->where('organization_id', $orgId)->delete();
        DB::table('categories')->where('organization_id', $orgId)->delete();

        // ── Security ────────────────────────────────────────────
        DB::table('login_attempts')->where('store_id', $storeId)->delete();
        DB::table('security_audit_log')->where('store_id', $storeId)->delete();
        DB::table('device_registrations')->where('store_id', $storeId)->delete();
        DB::table('security_policies')->where('store_id', $storeId)->delete();

        // ── Staff & Roles ───────────────────────────────────────
        $templateIds = DB::table('default_role_templates')->where('slug', 'store-manager')->pluck('id')->toArray();
        DB::table('default_role_template_permissions')->whereIn('default_role_template_id', $templateIds)->delete();
        DB::table('default_role_templates')->where('slug', 'store-manager')->delete();
        DB::table('provider_permissions')->whereIn('name', ['manage_products', 'manage_orders', 'manage_staff', 'view_reports'])->delete();
        DB::table('staff_activity_log')->whereIn('staff_user_id', $staffIds)->delete();
        DB::table('staff_documents')->whereIn('staff_user_id', $staffIds)->delete();
        DB::table('training_sessions')->whereIn('staff_user_id', $staffIds)->delete();
        DB::table('commission_earnings')->whereIn('staff_user_id', $staffIds)->delete();
        DB::table('commission_rules')->where('store_id', $storeId)->delete();
        DB::table('appointments')->whereIn('staff_id', $staffIds)->delete();
        $attendanceIds = DB::table('attendance_records')->whereIn('staff_user_id', $staffIds)->pluck('id')->toArray();
        DB::table('break_records')->whereIn('attendance_record_id', $attendanceIds)->delete();
        DB::table('attendance_records')->whereIn('staff_user_id', $staffIds)->delete();
        DB::table('shift_schedules')->where('store_id', $storeId)->delete();
        DB::table('shift_templates')->where('store_id', $storeId)->delete();
        DB::table('staff_branch_assignments')->whereIn('staff_user_id', $staffIds)->delete();
        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('staff_users')->where('store_id', $storeId)->delete();
        DB::table('roles')->where('store_id', $storeId)->delete();

        $this->command->info('  ✓ Cleanup complete');
    }

    // ─────────────────────────────────────────────────────────────
    // SYSTEM CONFIG (Tier 1)
    // ─────────────────────────────────────────────────────────────
    private function seedSystemConfig(): void
    {
        $this->command->info('Seeding system config...');

        // Supported Locales
        DB::table('supported_locales')->insertOrIgnore([
            ['locale_code' => 'ar', 'language_name' => 'Arabic', 'language_name_native' => 'العربية', 'direction' => 'rtl', 'calendar_system' => 'hijri', 'is_default' => true],
            ['locale_code' => 'en', 'language_name' => 'English', 'language_name_native' => 'English', 'direction' => 'ltr', 'calendar_system' => 'gregorian', 'is_default' => false],
        ]);

        // Payment Methods
        $methods = [
            'cash' => 'نقدي', 'card' => 'بطاقة', 'mada' => 'مدى', 'apple_pay' => 'أبل باي',
            'stc_pay' => 'اس تي سي باي', 'gift_card' => 'بطاقة هدية', 'store_credit' => 'رصيد المتجر',
            'loyalty_points' => 'نقاط الولاء', 'bank_transfer' => 'تحويل بنكي',
        ];
        $i = 0;
        foreach ($methods as $key => $nameAr) {
            DB::table('payment_methods')->insertOrIgnore([
                'method_key' => $key,
                'name' => ucwords(str_replace('_', ' ', $key)),
                'name_ar' => $nameAr,
                'icon' => $key . '.svg',
                'category' => in_array($key, ['cash', 'bank_transfer']) ? 'cash' : 'electronic',
                'requires_terminal' => in_array($key, ['card', 'mada', 'apple_pay', 'stc_pay']),
                'is_active' => true,
                'sort_order' => ++$i,
            ]);
        }

        // System Settings
        $settings = [
            ['key' => 'platform_name', 'value' => json_encode('Wameed POS'), 'group' => 'general', 'description' => 'Platform display name'],
            ['key' => 'default_currency', 'value' => json_encode('SAR'), 'group' => 'general', 'description' => 'Default currency'],
            ['key' => 'default_locale', 'value' => json_encode('ar'), 'group' => 'general', 'description' => 'Default locale'],
            ['key' => 'maintenance_mode', 'value' => json_encode(false), 'group' => 'system', 'description' => 'Global maintenance mode'],
            ['key' => 'smtp_host', 'value' => json_encode('smtp.sendgrid.net'), 'group' => 'email', 'description' => 'SMTP host'],
            ['key' => 'sms_provider', 'value' => json_encode('unifonic'), 'group' => 'sms', 'description' => 'SMS provider'],
        ];
        foreach ($settings as $s) {
            DB::table('system_settings')->insertOrIgnore(array_merge($s, ['updated_by' => $this->adminId, 'updated_at' => now()]));
        }

        // Feature Flags
        $flags = [
            ['flag_key' => 'enable_zatca', 'is_enabled' => true, 'rollout_percentage' => 100],
            ['flag_key' => 'enable_loyalty_v2', 'is_enabled' => true, 'rollout_percentage' => 50],
            ['flag_key' => 'enable_ai_insights', 'is_enabled' => false, 'rollout_percentage' => 0],
            ['flag_key' => 'enable_delivery_integrations', 'is_enabled' => true, 'rollout_percentage' => 100],
        ];
        foreach ($flags as $f) {
            DB::table('feature_flags')->insertOrIgnore(array_merge($f, ['updated_at' => now()]));
        }

        // Certified Hardware
        $hardware = [
            ['device_type' => 'receipt_printer', 'brand' => 'Epson', 'model' => 'TM-T88VI', 'driver_protocol' => 'esc_pos', 'is_certified' => true],
            ['device_type' => 'receipt_printer', 'brand' => 'Star', 'model' => 'TSP143IV', 'driver_protocol' => 'star_prnt', 'is_certified' => true],
            ['device_type' => 'barcode_scanner', 'brand' => 'Zebra', 'model' => 'DS2208', 'driver_protocol' => 'hid', 'is_certified' => true],
            ['device_type' => 'cash_drawer', 'brand' => 'APG', 'model' => 'VB320-BL1616', 'driver_protocol' => 'rj12_kick', 'is_certified' => true],
            ['device_type' => 'card_terminal', 'brand' => 'Ingenico', 'model' => 'Move5000', 'driver_protocol' => 'nexo', 'is_certified' => true],
            ['device_type' => 'label_printer', 'brand' => 'Zebra', 'model' => 'ZD421', 'driver_protocol' => 'zpl', 'is_certified' => true],
            ['device_type' => 'weighing_scale', 'brand' => 'CAS', 'model' => 'SW-1S', 'driver_protocol' => 'serial_cas', 'is_certified' => true],
        ];
        foreach ($hardware as $h) {
            DB::table('certified_hardware')->insertOrIgnore(array_merge($h, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Tax Exemption Types
        DB::table('tax_exemption_types')->insertOrIgnore([
            ['name' => 'Diplomatic', 'name_ar' => 'دبلوماسي', 'code' => 'DIPLOMATIC', 'required_documents' => 'diplomatic_id', 'is_active' => true],
            ['name' => 'Government', 'name_ar' => 'حكومي', 'code' => 'GOVERNMENT', 'required_documents' => 'gov_letter', 'is_active' => true],
            ['name' => 'Healthcare', 'name_ar' => 'صحي', 'code' => 'HEALTHCARE', 'required_documents' => null, 'is_active' => true],
        ]);

        // Age Restricted Categories
        DB::table('age_restricted_categories')->insertOrIgnore([
            ['category_slug' => 'tobacco', 'min_age' => 18, 'is_active' => true],
            ['category_slug' => 'energy-drinks', 'min_age' => 16, 'is_active' => true],
        ]);

        // Master Translation Strings
        $strings = [
            ['string_key' => 'common.save', 'category' => 'common', 'value_en' => 'Save', 'value_ar' => 'حفظ', 'is_overridable' => true],
            ['string_key' => 'common.cancel', 'category' => 'common', 'value_en' => 'Cancel', 'value_ar' => 'إلغاء', 'is_overridable' => true],
            ['string_key' => 'pos.checkout', 'category' => 'pos', 'value_en' => 'Checkout', 'value_ar' => 'الدفع', 'is_overridable' => true],
            ['string_key' => 'pos.add_to_cart', 'category' => 'pos', 'value_en' => 'Add to Cart', 'value_ar' => 'أضف للسلة', 'is_overridable' => true],
            ['string_key' => 'receipt.thank_you', 'category' => 'receipt', 'value_en' => 'Thank you!', 'value_ar' => 'شكراً!', 'is_overridable' => true],
        ];
        foreach ($strings as $s) {
            DB::table('master_translation_strings')->insertOrIgnore($s);
        }

        // Platform UI Defaults
        $defaults = [
            ['key' => 'primary_color', 'value' => '#1B4D3E'],
            ['key' => 'secondary_color', 'value' => '#F5A623'],
            ['key' => 'font_family', 'value' => 'IBM Plex Sans Arabic'],
            ['key' => 'default_grid_columns', 'value' => '4'],
        ];
        foreach ($defaults as $d) {
            DB::table('platform_ui_defaults')->insertOrIgnore($d);
        }

        $this->command->info('  ✓ System config: locales, payment methods, settings, flags, hardware');
    }

    // ─────────────────────────────────────────────────────────────
    // CONTENT & ONBOARDING (Tier 2)
    // ─────────────────────────────────────────────────────────────
    private function seedContentOnboarding(): void
    {
        $this->command->info('Seeding content & onboarding...');

        // Business Types
        $types = [
            ['name' => 'Grocery', 'slug' => 'grocery', 'name_ar' => 'بقالة', 'icon' => 'grocery', 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Restaurant', 'slug' => 'restaurant', 'name_ar' => 'مطعم', 'icon' => 'restaurant', 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Pharmacy', 'slug' => 'pharmacy', 'name_ar' => 'صيدلية', 'icon' => 'pharmacy', 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Bakery', 'slug' => 'bakery', 'name_ar' => 'مخبز', 'icon' => 'bakery', 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Electronics', 'slug' => 'electronics', 'name_ar' => 'إلكترونيات', 'icon' => 'electronic', 'is_active' => true, 'sort_order' => 5],
            ['name' => 'Florist', 'slug' => 'florist', 'name_ar' => 'زهور', 'icon' => 'florist', 'is_active' => true, 'sort_order' => 6],
            ['name' => 'Jewelry', 'slug' => 'jewelry', 'name_ar' => 'مجوهرات', 'icon' => 'jewelry', 'is_active' => true, 'sort_order' => 7],
            ['name' => 'Fashion', 'slug' => 'fashion', 'name_ar' => 'أزياء', 'icon' => 'fashion', 'is_active' => true, 'sort_order' => 8],
        ];
        foreach ($types as $t) {
            DB::table('business_types')->insertOrIgnore(array_merge($t, ['created_at' => now(), 'updated_at' => now()]));
        }

        $groceryTypeId = DB::table('business_types')->where('slug', 'grocery')->value('id');
        $restaurantTypeId = DB::table('business_types')->where('slug', 'restaurant')->value('id');
        $pharmacyTypeId = DB::table('business_types')->where('slug', 'pharmacy')->value('id');

        // Business Type Category Templates (grocery example)
        if ($groceryTypeId) {
            $cats = ['Fruits & Vegetables|فواكه وخضروات', 'Dairy & Eggs|ألبان وبيض', 'Meat & Poultry|لحوم ودواجن',
                     'Bakery|مخبوزات', 'Beverages|مشروبات', 'Snacks|وجبات خفيفة', 'Canned Goods|معلبات',
                     'Cleaning|منظفات', 'Personal Care|عناية شخصية', 'Frozen Food|مجمدات'];
            foreach ($cats as $i => $c) {
                [$en, $ar] = explode('|', $c);
                DB::table('business_type_category_templates')->insertOrIgnore([
                    'business_type_id' => $groceryTypeId, 'category_name' => $en,
                    'category_name_ar' => $ar, 'sort_order' => $i + 1,
                ]);
            }
        }

        // Themes
        $themes = [
            ['name' => 'Thawani Default', 'slug' => 'thawani-default', 'primary_color' => '#1B4D3E', 'secondary_color' => '#F5A623', 'background_color' => '#FFFFFF', 'text_color' => '#333333', 'is_active' => true, 'is_system' => true],
            ['name' => 'Dark Mode', 'slug' => 'dark-mode', 'primary_color' => '#1E1E2E', 'secondary_color' => '#89B4FA', 'background_color' => '#1E1E2E', 'text_color' => '#FFFFFF', 'is_active' => true, 'is_system' => false],
            ['name' => 'Ocean Blue', 'slug' => 'ocean-blue', 'primary_color' => '#0077B6', 'secondary_color' => '#00B4D8', 'background_color' => '#F0F8FF', 'text_color' => '#333333', 'is_active' => true, 'is_system' => false],
        ];
        foreach ($themes as $t) {
            DB::table('themes')->insertOrIgnore(array_merge($t, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Onboarding Steps
        $steps = [
            ['step_number' => 1, 'title' => 'Welcome', 'title_ar' => 'مرحباً', 'description' => 'Set up your store', 'description_ar' => 'أعد متجرك', 'is_required' => true, 'sort_order' => 1],
            ['step_number' => 2, 'title' => 'Store Info', 'title_ar' => 'بيانات المتجر', 'description' => 'Add store details', 'description_ar' => 'أضف بيانات المتجر', 'is_required' => true, 'sort_order' => 2],
            ['step_number' => 3, 'title' => 'Add Products', 'title_ar' => 'أضف منتجات', 'description' => 'Import or create products', 'description_ar' => 'استورد أو أنشئ منتجات', 'is_required' => true, 'sort_order' => 3],
            ['step_number' => 4, 'title' => 'Payment Setup', 'title_ar' => 'إعداد الدفع', 'description' => 'Configure payment methods', 'description_ar' => 'أعد طرق الدفع', 'is_required' => true, 'sort_order' => 4],
            ['step_number' => 5, 'title' => 'First Sale', 'title_ar' => 'أول عملية بيع', 'description' => 'Make your first sale', 'description_ar' => 'أجرِ أول عملية بيع', 'is_required' => false, 'sort_order' => 5],
        ];
        foreach ($steps as $s) {
            DB::table('onboarding_steps')->insertOrIgnore(array_merge($s, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Receipt Layout Templates
        DB::table('receipt_layout_templates')->insertOrIgnore([
            ['name' => 'Standard 80mm', 'name_ar' => 'قياسي 80مم', 'slug' => 'standard-80', 'paper_width' => 80,
             'header_config' => json_encode(['show_logo' => true, 'show_store_name' => true]),
             'body_config' => json_encode(['show_barcode' => true, 'show_tax_breakdown' => true]),
             'footer_config' => json_encode(['show_qr' => true, 'custom_text' => true]),
             'zatca_qr_position' => 'bottom', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Compact 58mm', 'name_ar' => 'مدمج 58مم', 'slug' => 'compact-58', 'paper_width' => 58,
             'header_config' => json_encode(['show_logo' => false, 'show_store_name' => true]),
             'body_config' => json_encode(['show_barcode' => false, 'show_tax_breakdown' => false]),
             'footer_config' => json_encode(['show_qr' => true]),
             'zatca_qr_position' => 'bottom', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // CFD Themes
        DB::table('cfd_themes')->insertOrIgnore([
            ['name' => 'Classic Cart', 'slug' => 'classic-cart', 'background_color' => '#FFFFFF',
             'text_color' => '#333333', 'accent_color' => '#1B4D3E',
             'cart_layout' => 'list', 'idle_layout' => 'slideshow', 'animation_style' => 'fade',
             'thank_you_animation' => 'confetti', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Label Layout Templates
        DB::table('label_layout_templates')->insertOrIgnore([
            ['name' => 'Standard Shelf', 'name_ar' => 'ملصق رف قياسي', 'slug' => 'standard-shelf', 'label_type' => 'shelf',
             'label_width_mm' => 60, 'label_height_mm' => 30, 'barcode_type' => 'ean13',
             'field_layout' => json_encode(['fields' => ['product_name', 'barcode', 'price']]),
             'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Price Tag', 'name_ar' => 'بطاقة سعر', 'slug' => 'price-tag', 'label_type' => 'price_tag',
             'label_width_mm' => 40, 'label_height_mm' => 25, 'barcode_type' => 'code128',
             'field_layout' => json_encode(['fields' => ['product_name', 'price']]),
             'created_at' => now(), 'updated_at' => now()],
        ]);

        // Signage Templates
        DB::table('signage_templates')->insertOrIgnore([
            ['name' => 'Welcome Board', 'name_ar' => 'لوحة ترحيب', 'slug' => 'welcome-board', 'template_type' => 'welcome',
             'layout_config' => json_encode(['sections' => ['logo', 'message', 'offers']]),
             'created_at' => now(), 'updated_at' => now()],
        ]);

        // Knowledge Base Articles
        DB::table('knowledge_base_articles')->insertOrIgnore([
            ['title' => 'Getting Started with POS', 'title_ar' => 'البدء مع نقطة البيع', 'slug' => 'getting-started-pos',
             'body' => 'Welcome to Wameed POS! This guide will help you set up your first sale...',
             'body_ar' => 'مرحباً بكم في ثواني! سيساعدك هذا الدليل على إعداد أول عملية بيع...',
             'category' => 'getting_started', 'is_published' => true, 'sort_order' => 1,
             'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Managing Inventory', 'title_ar' => 'إدارة المخزون', 'slug' => 'managing-inventory',
             'body' => 'Learn how to track stock levels, set reorder points, and manage suppliers...',
             'body_ar' => 'تعلم كيف تتبع مستويات المخزون وتحدد نقاط إعادة الطلب وتدير الموردين...',
             'category' => 'inventory', 'is_published' => true, 'sort_order' => 2,
             'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->command->info('  ✓ Content: business types, themes, onboarding, templates');
    }

    // ─────────────────────────────────────────────────────────────
    // SUBSCRIPTION EXTRAS (Tier 3)
    // ─────────────────────────────────────────────────────────────
    private function seedSubscriptionExtras(): void
    {
        $this->command->info('Seeding subscription extras...');

        $starterPlanId = DB::table('subscription_plans')->where('slug', 'starter')->value('id');
        $enterprisePlanId = DB::table('subscription_plans')->where('slug', 'enterprise')->value('id');

        // Plan Feature Toggles
        $features = [
            'multi_branch', 'custom_roles', 'api_access', 'white_label', 'loyalty_program',
            'delivery_integration', 'accounting_integration', 'zatca_compliance', 'advanced_reports', 'cfd_display',
        ];
        foreach ([$starterPlanId, $this->proPlanId, $enterprisePlanId] as $planId) {
            if (!$planId) continue;
            foreach ($features as $f) {
                $enabled = ($planId === $starterPlanId) ? in_array($f, ['zatca_compliance']) : true;
                DB::table('plan_feature_toggles')->insertOrIgnore([
                    'subscription_plan_id' => $planId, 'feature_key' => $f, 'is_enabled' => $enabled,
                ]);
            }
        }

        // Plan Limits
        $limits = ['max_products' => [100, 5000, -1], 'max_staff' => [3, 20, -1], 'max_branches' => [1, 5, -1], 'max_registers' => [1, 10, -1]];
        foreach ($limits as $key => [$starter, $pro, $enterprise]) {
            foreach ([[$starterPlanId, $starter], [$this->proPlanId, $pro], [$enterprisePlanId, $enterprise]] as [$planId, $val]) {
                if (!$planId) continue;
                DB::table('plan_limits')->insertOrIgnore([
                    'subscription_plan_id' => $planId, 'limit_key' => $key, 'limit_value' => $val,
                ]);
            }
        }

        // Plan Add-Ons
        $addOns = [
            ['name' => 'Extra Branch', 'name_ar' => 'فرع إضافي', 'slug' => 'extra-branch', 'monthly_price' => 49.00, 'is_active' => true],
            ['name' => 'White Label', 'name_ar' => 'علامة تجارية خاصة', 'slug' => 'white-label', 'monthly_price' => 99.00, 'is_active' => true],
            ['name' => 'Priority Support', 'name_ar' => 'دعم أولوية', 'slug' => 'priority-support', 'monthly_price' => 29.00, 'is_active' => true],
        ];
        foreach ($addOns as $a) {
            DB::table('plan_add_ons')->insertOrIgnore(array_merge($a, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Subscription Discount
        DB::table('subscription_discounts')->insertOrIgnore([
            'code' => 'WELCOME20', 'type' => 'percentage', 'value' => 20.00,
            'max_uses' => 500, 'times_used' => 15, 'valid_from' => now(),
            'valid_to' => now()->addMonths(6),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Pricing Page Content
        if ($this->proPlanId) {
            DB::table('pricing_page_content')->insertOrIgnore([
                'id'                     => \Illuminate\Support\Str::uuid(),
                'subscription_plan_id'   => $this->proPlanId,
                'hero_title'             => 'Pro Plan — Built for Serious Retailers',
                'hero_title_ar'          => 'خطة Pro — مصمّمة للتجار المحترفين',
                'hero_subtitle'          => 'Everything you need to run multiple branches, manage large inventories, and serve your customers fast.',
                'hero_subtitle_ar'       => 'كل ما تحتاجه لإدارة فروع متعددة، مخزون ضخم، وخدمة عملاء سريعة.',
                'highlight_badge'        => 'Most Popular',
                'highlight_badge_ar'     => 'الأكثر شيوعاً',
                'highlight_color'        => 'primary',
                'is_highlighted'         => true,
                'cta_label'              => 'Start Free Trial',
                'cta_label_ar'           => 'ابدأ تجربتك المجانية',
                'cta_secondary_label'    => 'Compare Plans',
                'cta_secondary_label_ar' => 'قارن بين الخطط',
                'cta_url'                => null,
                'price_prefix'           => 'Starting at',
                'price_prefix_ar'        => 'يبدأ من',
                'price_suffix'           => '/ month',
                'price_suffix_ar'        => '/ شهرياً',
                'annual_discount_label'    => 'Save 20% with annual billing',
                'annual_discount_label_ar' => 'وفّر 20% بالدفع السنوي',
                'trial_label'              => '14-day free trial, no credit card needed',
                'trial_label_ar'           => 'تجربة مجانية 14 يوماً، بدون بطاقة ائتمان',
                'money_back_days'          => 30,
                'feature_bullet_list' => json_encode([
                    ['text_en' => 'Up to 5,000 products', 'text_ar' => 'حتى 5,000 منتج', 'icon' => 'heroicon-o-cube', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => 'Includes variants and bundles', 'tooltip_ar' => 'يشمل المتغيرات والباقات'],
                    ['text_en' => '20 staff accounts', 'text_ar' => '20 حساب موظف', 'icon' => 'heroicon-o-users', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                    ['text_en' => '5 branches', 'text_ar' => '5 فروع', 'icon' => 'heroicon-o-building-storefront', 'is_included' => true, 'is_highlighted' => true, 'tooltip_en' => 'Full inter-branch inventory transfer', 'tooltip_ar' => 'تحويل مخزون كامل بين الفروع'],
                    ['text_en' => 'Full API access', 'text_ar' => 'وصول كامل لـ API', 'icon' => 'heroicon-o-code-bracket', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                    ['text_en' => 'Priority support (SLA 4h)', 'text_ar' => 'دعم أولوية (SLA 4 ساعات)', 'icon' => 'heroicon-o-lifebuoy', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                    ['text_en' => 'ZATCA Phase 2 compliance', 'text_ar' => 'امتثال ZATCA المرحلة الثانية', 'icon' => 'heroicon-o-shield-check', 'is_included' => true, 'is_highlighted' => true, 'tooltip_en' => 'e-invoicing & QR code generation included', 'tooltip_ar' => 'الفاتورة الإلكترونية ورمز QR مدمجان'],
                    ['text_en' => 'Advanced analytics dashboard', 'text_ar' => 'لوحة تحليلات متقدمة', 'icon' => 'heroicon-o-chart-bar', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                    ['text_en' => 'Loyalty & promotions engine', 'text_ar' => 'نظام الولاء والعروض', 'icon' => 'heroicon-o-gift', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                ]),
                'feature_categories' => json_encode([
                    [
                        'category_en' => 'Point of Sale',
                        'category_ar' => 'نقطة البيع',
                        'features' => [
                            ['text_en' => 'Barcode scanning', 'text_ar' => 'مسح الباركود', 'limit' => 'Unlimited', 'icon' => 'heroicon-o-qr-code', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                            ['text_en' => 'Split payments', 'text_ar' => 'الدفع المجزأ', 'limit' => null, 'icon' => 'heroicon-o-credit-card', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                            ['text_en' => 'Offline mode', 'text_ar' => 'وضع بلا إنترنت', 'limit' => null, 'icon' => 'heroicon-o-wifi', 'is_included' => true, 'is_highlighted' => true, 'tooltip_en' => 'Sync resumes when connection is restored', 'tooltip_ar' => 'المزامنة تستأنف عند استعادة الاتصال'],
                        ],
                    ],
                    [
                        'category_en' => 'Inventory & Catalog',
                        'category_ar' => 'المخزون والكتالوج',
                        'features' => [
                            ['text_en' => 'Products', 'text_ar' => 'المنتجات', 'limit' => '5,000', 'icon' => 'heroicon-o-cube', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                            ['text_en' => 'Bulk import / export', 'text_ar' => 'استيراد / تصدير بالجملة', 'limit' => null, 'icon' => 'heroicon-o-arrow-up-tray', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                            ['text_en' => 'Inter-branch transfers', 'text_ar' => 'التحويل بين الفروع', 'limit' => null, 'icon' => 'heroicon-o-arrows-right-left', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                            ['text_en' => 'Physical inventory count', 'text_ar' => 'الجرد الفعلي', 'limit' => null, 'icon' => 'heroicon-o-clipboard-document-list', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                        ],
                    ],
                    [
                        'category_en' => 'Integrations & Compliance',
                        'category_ar' => 'التكاملات والامتثال',
                        'features' => [
                            ['text_en' => 'ZATCA e-invoicing', 'text_ar' => 'فاتورة ZATCA الإلكترونية', 'limit' => null, 'icon' => 'heroicon-o-document-check', 'is_included' => true, 'is_highlighted' => true, 'tooltip_en' => null, 'tooltip_ar' => null],
                            ['text_en' => 'Thawani integration', 'text_ar' => 'تكامل ثواني', 'limit' => null, 'icon' => 'heroicon-o-banknotes', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                            ['text_en' => 'REST API (full access)', 'text_ar' => 'REST API (وصول كامل)', 'limit' => null, 'icon' => 'heroicon-o-code-bracket', 'is_included' => true, 'is_highlighted' => false, 'tooltip_en' => null, 'tooltip_ar' => null],
                        ],
                    ],
                ]),
                'faq' => json_encode([
                    ['question_en' => 'Can I upgrade or downgrade at any time?', 'question_ar' => 'هل يمكنني الترقية أو التخفيض في أي وقت؟', 'answer_en' => 'Yes. Plan changes take effect immediately or at the end of your current billing cycle, depending on the direction of the change.', 'answer_ar' => 'نعم. تسري تغييرات الخطة فوراً أو في نهاية دورة الفوترة الحالية حسب اتجاه التغيير.'],
                    ['question_en' => 'What happens when my trial ends?', 'question_ar' => 'ماذا يحدث عند انتهاء فترة التجربة؟', 'answer_en' => 'You have a 7-day grace period before any features are restricted. No data is lost.', 'answer_ar' => 'لديك فترة سماح 7 أيام قبل أي تقييد للميزات. لن تُفقد أي بيانات.'],
                    ['question_en' => 'Is there a setup fee?', 'question_ar' => 'هل توجد رسوم تأسيس؟', 'answer_en' => 'No setup fees. You only pay the monthly or annual subscription price.', 'answer_ar' => 'لا توجد رسوم تأسيس. تدفع فقط سعر الاشتراك الشهري أو السنوي.'],
                    ['question_en' => 'Do you offer a money-back guarantee?', 'question_ar' => 'هل تقدمون ضمان استعادة المال؟', 'answer_en' => 'Yes. We offer a 30-day money-back guarantee on all paid plans, no questions asked.', 'answer_ar' => 'نعم. نقدم ضمان استعادة المال خلال 30 يوماً على جميع الخطط المدفوعة، دون أسئلة.'],
                    ['question_en' => 'Can I use Wameed POS across multiple devices?', 'question_ar' => 'هل يمكنني استخدام ثواني POS على أجهزة متعددة؟', 'answer_en' => 'Yes. The Pro plan supports unlimited device sessions per branch.', 'answer_ar' => 'نعم. تدعم خطة Pro جلسات أجهزة غير محدودة لكل فرع.'],
                ]),
                'testimonials' => json_encode([
                    ['name' => 'Ahmed Al-Rashidi', 'company' => 'Al Rashidi Supermarkets', 'role_en' => 'Owner', 'role_ar' => 'مالك', 'text_en' => 'Switching to Thawani Pro transformed how we manage our 3 branches. ZATCA compliance is seamless.', 'text_ar' => 'الانتقال إلى ثواني Pro غيّر طريقة إدارتنا لفروعنا الثلاث. الامتثال لـ ZATCA سلس جداً.', 'rating' => 5, 'avatar_url' => null],
                    ['name' => 'Sara Al-Shehri', 'company' => 'Bloom Boutique', 'role_en' => 'Operations Manager', 'role_ar' => 'مديرة العمليات', 'text_en' => 'The inventory management and bulk import saved us countless hours every month.', 'text_ar' => 'إدارة المخزون والاستيراد بالجملة وفّرا علينا ساعات لا تُحصى كل شهر.', 'rating' => 5, 'avatar_url' => null],
                    ['name' => 'Khalid Bin Saad', 'company' => 'SpeedMart', 'role_en' => 'CEO', 'role_ar' => 'رئيس تنفيذي', 'text_en' => 'The API integration with our ERP was straightforward. Support team responded within 2 hours.', 'text_ar' => 'التكامل مع ERP عبر API كان سهلاً. فريق الدعم استجاب خلال ساعتين.', 'rating' => 4, 'avatar_url' => null],
                ]),
                'comparison_highlights' => json_encode([
                    ['feature_en' => 'Products', 'feature_ar' => 'المنتجات', 'value' => '5,000', 'note_en' => 'Starter: 500 / Enterprise: Unlimited', 'note_ar' => 'Starter: 500 / Enterprise: غير محدود'],
                    ['feature_en' => 'Staff Accounts', 'feature_ar' => 'حسابات الموظفين', 'value' => '20', 'note_en' => 'Starter: 5 / Enterprise: Unlimited', 'note_ar' => 'Starter: 5 / Enterprise: غير محدود'],
                    ['feature_en' => 'Branches', 'feature_ar' => 'الفروع', 'value' => '5', 'note_en' => 'Starter: 1 / Enterprise: Unlimited', 'note_ar' => 'Starter: 1 / Enterprise: غير محدود'],
                    ['feature_en' => 'ZATCA Compliance', 'feature_ar' => 'امتثال ZATCA', 'value' => '✓ Phase 2', 'note_en' => null, 'note_ar' => null],
                    ['feature_en' => 'API Access', 'feature_ar' => 'وصول API', 'value' => 'Full', 'note_en' => 'Starter: Read-only', 'note_ar' => 'Starter: قراءة فقط'],
                ]),
                'meta_title'          => 'Pro Plan Pricing — Wameed POS',
                'meta_title_ar'       => 'أسعار خطة Pro — ثواني POS',
                'meta_description'    => 'Everything your growing retail business needs — 5,000 products, 5 branches, full API, and ZATCA Phase 2 compliance.',
                'meta_description_ar' => 'كل ما تحتاجه لتنمية تجارتك — 5,000 منتج، 5 فروع، API كامل، وامتثال ZATCA المرحلة الثانية.',
                'color_theme'        => 'primary',
                'card_icon'          => 'heroicon-o-rocket-launch',
                'card_image_url'     => null,
                'is_published'       => true,
                'sort_order'         => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        $this->command->info('  ✓ Subscription: features, limits, add-ons, discounts');
    }

    // ─────────────────────────────────────────────────────────────
    // SECOND STORE (for multi-branch testing)
    // ─────────────────────────────────────────────────────────────
    private function seedSecondStore(): void
    {
        $this->command->info('Seeding second store...');

        DB::table('stores')->insertOrIgnore([
            'organization_id' => $this->orgId,
            'name' => 'Ostora Branch 2',
            'name_ar' => 'أستورا - الفرع الثاني',
            'slug' => 'ostora-branch-2',
            'branch_code' => 'OST-002',
            'address' => 'Tahlia Street, Al Sulaimaniyah, Riyadh',
            'city' => 'Riyadh',
            'latitude' => 24.6907,
            'longitude' => 46.6850,
            'phone' => '+966501234568',
            'email' => 'branch2@ostora.sa',
            'timezone' => 'Asia/Riyadh',
            'currency' => 'SAR',
            'locale' => 'ar',
            'business_type' => 'grocery',
            'is_active' => true,
            'is_main_branch' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Register for main store
        DB::table('registers')->insertOrIgnore([
            'store_id' => $this->storeId,
            'name' => 'Register 1',
            'device_id' => 'REG-001-' . Str::random(8),
            'app_version' => '1.0.0',
            'platform' => 'android',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('registers')->insertOrIgnore([
            'store_id' => $this->storeId,
            'name' => 'Register 2',
            'device_id' => 'REG-002-' . Str::random(8),
            'app_version' => '1.0.0',
            'platform' => 'ios',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('  ✓ Second store + 2 registers');
    }

    // ─────────────────────────────────────────────────────────────
    // STAFF & ROLES (Tier 4)
    // ─────────────────────────────────────────────────────────────
    private function seedStaffAndRoles(): void
    {
        $this->command->info('Seeding staff & roles...');

        // Store-level Permissions
        $perms = [
            ['name' => 'pos.sell', 'display_name' => 'POS Sell', 'module' => 'pos', 'guard_name' => 'web', 'requires_pin' => false],
            ['name' => 'pos.refund', 'display_name' => 'POS Refund', 'module' => 'pos', 'guard_name' => 'web', 'requires_pin' => true],
            ['name' => 'pos.discount', 'display_name' => 'POS Discount', 'module' => 'pos', 'guard_name' => 'web', 'requires_pin' => true],
            ['name' => 'pos.void', 'display_name' => 'POS Void', 'module' => 'pos', 'guard_name' => 'web', 'requires_pin' => true],
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'module' => 'inventory', 'guard_name' => 'web', 'requires_pin' => false],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Inventory', 'module' => 'inventory', 'guard_name' => 'web', 'requires_pin' => false],
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'module' => 'reports', 'guard_name' => 'web', 'requires_pin' => false],
            ['name' => 'staff.manage', 'display_name' => 'Manage Staff', 'module' => 'staff', 'guard_name' => 'web', 'requires_pin' => false],
            ['name' => 'settings.manage', 'display_name' => 'Manage Settings', 'module' => 'settings', 'guard_name' => 'web', 'requires_pin' => true],
            ['name' => 'customers.manage', 'display_name' => 'Manage Customers', 'module' => 'customers', 'guard_name' => 'web', 'requires_pin' => false],
            ['name' => 'delivery.view', 'display_name' => 'View Delivery', 'module' => 'delivery', 'guard_name' => 'web', 'requires_pin' => false],
            ['name' => 'delivery.manage', 'display_name' => 'Manage Delivery', 'module' => 'delivery', 'guard_name' => 'web', 'requires_pin' => false],
        ];
        foreach ($perms as $p) {
            DB::table('permissions')->insertOrIgnore(array_merge($p, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Store-level Roles
        $cashierRole = DB::table('roles')->where('store_id', $this->storeId)->where('name', 'Cashier')->where('guard_name', 'web')->value('id');
        if (!$cashierRole) {
            $cashierRole = DB::table('roles')->insertGetId([
                'store_id' => $this->storeId, 'name' => 'Cashier', 'display_name' => 'Cashier',
                'guard_name' => 'web', 'is_predefined' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $managerRole = DB::table('roles')->where('store_id', $this->storeId)->where('name', 'Manager')->where('guard_name', 'web')->value('id');
        if (!$managerRole) {
            $managerRole = DB::table('roles')->insertGetId([
                'store_id' => $this->storeId, 'name' => 'Manager', 'display_name' => 'Manager',
                'guard_name' => 'web', 'is_predefined' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Assign permissions to roles
        $allPermIds = DB::table('permissions')->pluck('id')->toArray();
        $cashierPerms = DB::table('permissions')->whereIn('name', ['pos.sell', 'inventory.view', 'customers.manage'])->pluck('id')->toArray();

        foreach ($allPermIds as $pid) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $managerRole, 'permission_id' => $pid]);
        }
        foreach ($cashierPerms as $pid) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $cashierRole, 'permission_id' => $pid]);
        }

        // Staff Users
        $staffCashier = DB::table('staff_users')->insertGetId([
            'store_id' => $this->storeId,
            'first_name' => 'Ahmed',
            'last_name' => 'Cashier',
            'email' => 'ahmed@ostora.sa',
            'phone' => '+966501111111',
            'pin_hash' => Hash::make('5678'),
            'hire_date' => now()->subMonths(6)->toDateString(),
            'employment_type' => 'full_time',
            'salary_type' => 'monthly',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffManager = DB::table('staff_users')->insertGetId([
            'store_id' => $this->storeId,
            'first_name' => 'Sara',
            'last_name' => 'Manager',
            'email' => 'sara@ostora.sa',
            'phone' => '+966502222222',
            'pin_hash' => Hash::make('9012'),
            'hire_date' => now()->subYear()->toDateString(),
            'employment_type' => 'full_time',
            'salary_type' => 'monthly',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Staff Branch Assignments
        DB::table('staff_branch_assignments')->insertOrIgnore([
            ['staff_user_id' => $staffCashier, 'branch_id' => $this->storeId, 'role_id' => $cashierRole, 'is_primary' => true, 'created_at' => now()],
            ['staff_user_id' => $staffManager, 'branch_id' => $this->storeId, 'role_id' => $managerRole, 'is_primary' => true, 'created_at' => now()],
        ]);

        // Shift Templates
        $morningShift = DB::table('shift_templates')->insertGetId([
            'store_id' => $this->storeId, 'name' => 'Morning Shift',
            'start_time' => '08:00', 'end_time' => '16:00', 'color' => '#4CAF50',
            'created_at' => now(),
        ]);
        $eveningShift = DB::table('shift_templates')->insertGetId([
            'store_id' => $this->storeId, 'name' => 'Evening Shift',
            'start_time' => '16:00', 'end_time' => '23:00', 'color' => '#FF9800',
            'created_at' => now(),
        ]);

        // Shift Schedules (for this week)
        $today = now()->startOfWeek();
        for ($i = 0; $i < 5; $i++) {
            $date = $today->copy()->addDays($i)->toDateString();
            DB::table('shift_schedules')->insertOrIgnore([
                'store_id' => $this->storeId, 'staff_user_id' => $staffCashier,
                'shift_template_id' => $morningShift, 'date' => $date, 'status' => 'scheduled',
            ]);
            DB::table('shift_schedules')->insertOrIgnore([
                'store_id' => $this->storeId, 'staff_user_id' => $staffManager,
                'shift_template_id' => $eveningShift, 'date' => $date, 'status' => 'scheduled',
            ]);
        }

        // Attendance Records
        $attendanceId = DB::table('attendance_records')->insertGetId([
            'staff_user_id' => $staffCashier, 'store_id' => $this->storeId,
            'clock_in_at' => now()->subHours(6), 'clock_out_at' => now()->subHours(1),
            'auth_method' => 'pin', 'created_at' => now(),
        ]);

        DB::table('break_records')->insertOrIgnore([
            'attendance_record_id' => $attendanceId,
            'break_start' => now()->subHours(3), 'break_end' => now()->subHours(2)->subMinutes(30),
        ]);

        // Commission Rules
        $ruleId = DB::table('commission_rules')->insertGetId([
            'store_id' => $this->storeId, 'staff_user_id' => $staffCashier,
            'type' => 'percentage', 'percentage' => 2.00,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Training Session
        DB::table('training_sessions')->insertOrIgnore([
            'staff_user_id' => $staffCashier, 'store_id' => $this->storeId,
            'started_at' => now()->subDays(5),
            'ended_at' => now()->subDays(5)->addMinutes(45),
            'transactions_count' => 10, 'notes' => 'POS basics training - passed',
        ]);

        // Staff Documents
        DB::table('staff_documents')->insertOrIgnore([
            'staff_user_id' => $staffCashier,
            'document_type' => 'national_id',
            'expiry_date' => now()->addYears(5)->toDateString(), 'file_url' => 'documents/national_id_ahmed.pdf',
            'uploaded_at' => now(),
        ]);

        // Staff Activity Log
        DB::table('staff_activity_log')->insert([
            'staff_user_id' => $staffCashier, 'store_id' => $this->storeId,
            'action' => 'clock_in', 'entity_type' => 'attendance',
            'details' => json_encode(['method' => 'pin']),
            'created_at' => now()->subHours(6),
        ]);

        // Default Role Templates (provider permissions)
        DB::table('provider_permissions')->insertOrIgnore([
            ['name' => 'manage_products', 'group' => 'catalog', 'description' => 'Create/edit products', 'is_active' => true],
            ['name' => 'manage_orders', 'group' => 'orders', 'description' => 'View/manage orders', 'is_active' => true],
            ['name' => 'manage_staff', 'group' => 'staff', 'description' => 'Manage staff members', 'is_active' => true],
            ['name' => 'view_reports', 'group' => 'reports', 'description' => 'Access reports', 'is_active' => true],
        ]);

        $templateId = DB::table('default_role_templates')->insertGetId([
            'name' => 'Store Manager', 'slug' => 'store-manager',
            'description' => 'Default role for store managers',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $ppIds = DB::table('provider_permissions')->pluck('id');
        foreach ($ppIds as $ppId) {
            DB::table('default_role_template_permissions')->insertOrIgnore([
                'default_role_template_id' => $templateId, 'provider_permission_id' => $ppId,
            ]);
        }

        $this->command->info('  ✓ Staff: 2 users, roles, shifts, attendance, commissions');
    }

    // ─────────────────────────────────────────────────────────────
    // SECURITY POLICIES (Tier 5)
    // ─────────────────────────────────────────────────────────────
    private function seedSecurityPolicies(): void
    {
        $this->command->info('Seeding security policies...');

        DB::table('security_policies')->insertOrIgnore([
            'store_id' => $this->storeId,
            'pin_min_length' => 4,
            'auto_lock_seconds' => 300,
            'max_failed_attempts' => 5,
            'lockout_duration_minutes' => 15,
        ]);

        // Device Registration
        $deviceId = DB::table('device_registrations')->insertGetId([
            'store_id' => $this->storeId,
            'device_name' => 'Main POS Terminal',
            'hardware_id' => 'HW-' . Str::random(16),
            'os_info' => 'Android 14',
            'app_version' => '1.0.0',
            'is_active' => true,
            'last_active_at' => now(),
            'registered_at' => now(),
        ]);

        // Security Audit Log
        DB::table('security_audit_log')->insert([
            'store_id' => $this->storeId,
            'device_id' => $deviceId,
            'user_id' => $this->ownerId,
            'user_type' => 'owner',
            'action' => 'login',
            'severity' => 'info',
            'ip_address' => '192.168.1.100',
            'details' => json_encode(['method' => 'password']),
            'created_at' => now(),
        ]);

        // Login Attempts
        DB::table('login_attempts')->insert([
            'store_id' => $this->storeId,
            'user_identifier' => 'owner@ostora.sa',
            'attempt_type' => 'password',
            'is_successful' => true,
            'ip_address' => '192.168.1.100',
            'attempted_at' => now(),
        ]);

        $this->command->info('  ✓ Security: policies, devices, audit log');
    }

    // ─────────────────────────────────────────────────────────────
    // CATALOG (Tier 6)
    // ─────────────────────────────────────────────────────────────
    private function seedCatalog(): void
    {
        $this->command->info('Seeding catalog...');

        // Categories
        $catFruits = DB::table('categories')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Fruits & Vegetables',
            'name_ar' => 'فواكه وخضروات', 'sort_order' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $catDairy = DB::table('categories')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Dairy & Eggs',
            'name_ar' => 'ألبان وبيض', 'sort_order' => 2, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $catBeverages = DB::table('categories')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Beverages',
            'name_ar' => 'مشروبات', 'sort_order' => 3, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $catSnacks = DB::table('categories')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Snacks',
            'name_ar' => 'وجبات خفيفة', 'sort_order' => 4, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $catBakery = DB::table('categories')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Bakery',
            'name_ar' => 'مخبوزات', 'sort_order' => 5, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Products
        $products = [
            ['category_id' => $catFruits, 'name' => 'Banana', 'name_ar' => 'موز', 'sku' => 'FRU-001', 'barcode' => '6281000000101', 'sell_price' => 5.00, 'cost_price' => 2.50, 'tax_rate' => 15.00, 'unit' => 'kg', 'is_weighable' => true, 'is_active' => true],
            ['category_id' => $catFruits, 'name' => 'Apple Red', 'name_ar' => 'تفاح أحمر', 'sku' => 'FRU-002', 'barcode' => '6281000000102', 'sell_price' => 12.00, 'cost_price' => 7.00, 'tax_rate' => 15.00, 'unit' => 'kg', 'is_weighable' => true, 'is_active' => true],
            ['category_id' => $catDairy, 'name' => 'Fresh Milk 1L', 'name_ar' => 'حليب طازج 1 لتر', 'sku' => 'DAI-001', 'barcode' => '6281000000201', 'sell_price' => 6.50, 'cost_price' => 4.00, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
            ['category_id' => $catDairy, 'name' => 'Eggs Pack 30', 'name_ar' => 'بيض 30 حبة', 'sku' => 'DAI-002', 'barcode' => '6281000000202', 'sell_price' => 18.00, 'cost_price' => 12.00, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
            ['category_id' => $catBeverages, 'name' => 'Water 500ml', 'name_ar' => 'ماء 500 مل', 'sku' => 'BEV-001', 'barcode' => '6281000000301', 'sell_price' => 1.00, 'cost_price' => 0.30, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
            ['category_id' => $catBeverages, 'name' => 'Cola 330ml', 'name_ar' => 'كولا 330 مل', 'sku' => 'BEV-002', 'barcode' => '6281000000302', 'sell_price' => 2.50, 'cost_price' => 1.00, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
            ['category_id' => $catSnacks, 'name' => 'Chips Original', 'name_ar' => 'شيبس أصلي', 'sku' => 'SNK-001', 'barcode' => '6281000000401', 'sell_price' => 4.00, 'cost_price' => 2.00, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
            ['category_id' => $catSnacks, 'name' => 'Chocolate Bar', 'name_ar' => 'لوح شوكولاتة', 'sku' => 'SNK-002', 'barcode' => '6281000000402', 'sell_price' => 3.50, 'cost_price' => 1.50, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
            ['category_id' => $catBakery, 'name' => 'Arabic Bread', 'name_ar' => 'خبز عربي', 'sku' => 'BAK-001', 'barcode' => '6281000000501', 'sell_price' => 2.00, 'cost_price' => 0.80, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
            ['category_id' => $catBakery, 'name' => 'Croissant', 'name_ar' => 'كرواسون', 'sku' => 'BAK-002', 'barcode' => '6281000000502', 'sell_price' => 5.00, 'cost_price' => 2.00, 'tax_rate' => 15.00, 'unit' => 'piece', 'is_weighable' => false, 'is_active' => true],
        ];

        $productIds = [];
        foreach ($products as $p) {
            $productIds[] = DB::table('products')->insertGetId(array_merge($p, [
                'organization_id' => $this->orgId, 'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // Product Barcodes
        foreach ($productIds as $i => $pid) {
            DB::table('product_barcodes')->insertOrIgnore([
                'product_id' => $pid, 'barcode' => $products[$i]['barcode'], 'is_primary' => true,
            ]);
        }

        // Product Variant Group (for beverages — size variants)
        $varGroupId = DB::table('product_variant_groups')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Size', 'name_ar' => 'الحجم',
        ]);

        // Product Variants for Cola
        $colaId = $productIds[5]; // Cola 330ml
        DB::table('product_variants')->insert([
            ['product_id' => $colaId, 'variant_group_id' => $varGroupId, 'variant_value' => '500ml', 'sku' => 'BEV-002-500', 'barcode' => '6281000000303', 'price_adjustment' => 1.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $colaId, 'variant_group_id' => $varGroupId, 'variant_value' => '1L', 'sku' => 'BEV-002-1L', 'barcode' => '6281000000304', 'price_adjustment' => 2.50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Modifier Group (for Croissant — filling)
        $croissantId = $productIds[9];
        $modGroupId = DB::table('modifier_groups')->insertGetId([
            'product_id' => $croissantId, 'name' => 'Filling', 'name_ar' => 'الحشوة',
            'is_required' => false, 'min_select' => 0, 'max_select' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('modifier_options')->insert([
            ['modifier_group_id' => $modGroupId, 'name' => 'Chocolate', 'name_ar' => 'شوكولاتة', 'price_adjustment' => 2.00, 'is_default' => false, 'sort_order' => 1],
            ['modifier_group_id' => $modGroupId, 'name' => 'Cheese', 'name_ar' => 'جبنة', 'price_adjustment' => 1.50, 'is_default' => false, 'sort_order' => 2],
            ['modifier_group_id' => $modGroupId, 'name' => 'Zaatar', 'name_ar' => 'زعتر', 'price_adjustment' => 1.00, 'is_default' => true, 'sort_order' => 3],
        ]);

        // Suppliers
        $supplierId = DB::table('suppliers')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Al Marai Distribution',
            'phone' => '+966509999999',
            'email' => 'orders@almarai.sa', 'address' => 'Riyadh Industrial City',
            'notes' => 'Contact: Khalid', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Product Supplier links
        DB::table('product_suppliers')->insertOrIgnore([
            ['product_id' => $productIds[2], 'supplier_id' => $supplierId, 'cost_price' => 3.80, 'lead_time_days' => 2, 'supplier_sku' => 'AM-MILK-1L'],
            ['product_id' => $productIds[3], 'supplier_id' => $supplierId, 'cost_price' => 11.50, 'lead_time_days' => 2, 'supplier_sku' => 'AM-EGG-30'],
        ]);

        // Combo Product
        $comboId = DB::table('combo_products')->insertGetId([
            'product_id' => $productIds[8], 'name' => 'Breakfast Combo',
            'combo_price' => 8.00, 'created_at' => now(),
        ]);
        DB::table('combo_product_items')->insert([
            ['combo_product_id' => $comboId, 'product_id' => $productIds[8], 'quantity' => 1, 'is_optional' => false],
            ['combo_product_id' => $comboId, 'product_id' => $productIds[2], 'quantity' => 1, 'is_optional' => false],
        ]);

        // Store-specific Price
        DB::table('store_prices')->insertOrIgnore([
            'store_id' => $this->storeId, 'product_id' => $productIds[0],
            'sell_price' => 4.50, 'valid_from' => now(), 'valid_to' => now()->addMonth(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Product Images
        DB::table('product_images')->insertOrIgnore([
            ['product_id' => $productIds[0], 'image_url' => 'products/banana.jpg', 'sort_order' => 1],
            ['product_id' => $productIds[2], 'image_url' => 'products/milk.jpg', 'sort_order' => 1],
        ]);

        // Internal Barcode Sequence
        DB::table('internal_barcode_sequence')->insertOrIgnore([
            'store_id' => $this->storeId, 'last_sequence' => 1000,
        ]);

        $this->command->info('  ✓ Catalog: 10 products, 5 categories, variants, modifiers, supplier');
    }

    // ─────────────────────────────────────────────────────────────
    // INVENTORY (Tier 7)
    // ─────────────────────────────────────────────────────────────
    private function seedInventory(): void
    {
        $this->command->info('Seeding inventory...');

        $productIds = DB::table('products')->where('organization_id', $this->orgId)->pluck('id')->toArray();
        $supplierId = DB::table('suppliers')->where('organization_id', $this->orgId)->first()?->id;

        // Stock Levels for each product
        foreach ($productIds as $pid) {
            DB::table('stock_levels')->insertOrIgnore([
                'store_id' => $this->storeId, 'product_id' => $pid,
                'quantity' => rand(20, 200), 'reserved_quantity' => 0,
                'reorder_point' => 10, 'max_stock_level' => 500,
            ]);
        }

        // Stock Movement
        DB::table('stock_movements')->insert([
            'store_id' => $this->storeId, 'product_id' => $productIds[0],
            'type' => 'receipt', 'quantity' => 100, 'unit_cost' => 2.50,
            'reference_type' => 'goods_receipt', 'performed_by' => $this->ownerId,
            'reason' => 'Initial stock', 'created_at' => now(),
        ]);

        // Goods Receipt
        if ($supplierId) {
            $grId = DB::table('goods_receipts')->insertGetId([
                'store_id' => $this->storeId, 'supplier_id' => $supplierId,
                'reference_number' => 'GR-2025-001', 'status' => 'confirmed',
                'total_cost' => 350.00, 'received_by' => $this->ownerId,
                'received_at' => now()->subDays(3),
            ]);

            DB::table('goods_receipt_items')->insert([
                ['goods_receipt_id' => $grId, 'product_id' => $productIds[2], 'quantity' => 50, 'unit_cost' => 3.80, 'batch_number' => 'B2025-001', 'expiry_date' => now()->addDays(14)->toDateString()],
                ['goods_receipt_id' => $grId, 'product_id' => $productIds[3], 'quantity' => 28, 'unit_cost' => 11.50, 'batch_number' => 'B2025-002', 'expiry_date' => now()->addDays(21)->toDateString()],
            ]);

            // Stock Batch
            DB::table('stock_batches')->insert([
                'store_id' => $this->storeId, 'product_id' => $productIds[2],
                'batch_number' => 'B2025-001', 'expiry_date' => now()->addDays(14)->toDateString(),
                'quantity' => 50, 'goods_receipt_id' => $grId, 'created_at' => now(),
            ]);

            // Purchase Order
            $poId = DB::table('purchase_orders')->insertGetId([
                'organization_id' => $this->orgId, 'store_id' => $this->storeId,
                'supplier_id' => $supplierId, 'reference_number' => 'PO-2025-001',
                'status' => 'sent', 'expected_date' => now()->addDays(5)->toDateString(),
                'total_cost' => 500.00, 'created_by' => $this->ownerId,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('purchase_order_items')->insert([
                'purchase_order_id' => $poId, 'product_id' => $productIds[4],
                'quantity_ordered' => 200, 'unit_cost' => 0.30,
            ]);
        }

        // Stock Adjustment
        $adjId = DB::table('stock_adjustments')->insertGetId([
            'store_id' => $this->storeId, 'type' => 'damage',
            'reason_code' => 'expired', 'notes' => 'Expired dairy products',
            'adjusted_by' => $this->ownerId, 'created_at' => now(),
        ]);
        DB::table('stock_adjustment_items')->insert([
            'stock_adjustment_id' => $adjId, 'product_id' => $productIds[2],
            'quantity' => -5,
        ]);

        // Stock Transfer (between stores)
        $branch2Id = DB::table('stores')->where('slug', 'ostora-branch-2')->value('id');
        if ($branch2Id) {
            $transferId = DB::table('stock_transfers')->insertGetId([
                'organization_id' => $this->orgId, 'from_store_id' => $this->storeId,
                'to_store_id' => $branch2Id, 'reference_number' => 'TRF-2025-001',
                'status' => 'completed', 'created_by' => $this->ownerId,
                'approved_by' => $this->ownerId, 'received_by' => $this->ownerId,
                'created_at' => now(),
            ]);
            DB::table('stock_transfer_items')->insert([
                'stock_transfer_id' => $transferId, 'product_id' => $productIds[4],
                'quantity_sent' => 50, 'quantity_received' => 50,
            ]);
        }

        // Recipe
        $recipeId = DB::table('recipes')->insertGetId([
            'organization_id' => $this->orgId, 'product_id' => $productIds[9],
            'yield_quantity' => 12,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('recipe_ingredients')->insert([
            ['recipe_id' => $recipeId, 'ingredient_product_id' => $productIds[2], 'quantity' => 0.5, 'unit' => 'liter', 'waste_percent' => 2],
        ]);

        $this->command->info('  ✓ Inventory: stock levels, movements, receipts, PO, adjustments, transfers');
    }

    // ─────────────────────────────────────────────────────────────
    // CUSTOMERS (Tier 8)
    // ─────────────────────────────────────────────────────────────
    private function seedCustomers(): void
    {
        $this->command->info('Seeding customers...');

        // Customer Groups
        $vipGroupId = DB::table('customer_groups')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'VIP',
            'discount_percent' => 10.00,
        ]);
        $regularGroupId = DB::table('customer_groups')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Regular',
            'discount_percent' => 0.00,
        ]);
        $wholesaleGroupId = DB::table('customer_groups')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Wholesale',
            'discount_percent' => 15.00,
        ]);

        // Customers
        $customer1 = DB::table('customers')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Fatima Ahmed',
            'phone' => '+966551234567', 'email' => 'fatima@email.com',
            'loyalty_code' => 'LYL-0001', 'loyalty_points' => 2500,
            'store_credit_balance' => 50.00, 'group_id' => $vipGroupId,
            'total_spend' => 15000.00, 'visit_count' => 120,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $customer2 = DB::table('customers')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Khalid Sultan',
            'phone' => '+966559876543', 'email' => 'khalid@email.com',
            'loyalty_code' => 'LYL-0002', 'loyalty_points' => 800,
            'store_credit_balance' => 0.00, 'group_id' => $regularGroupId,
            'total_spend' => 3500.00, 'visit_count' => 45,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $customer3 = DB::table('customers')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Nora Mohammed',
            'phone' => '+966555555555', 'email' => 'nora@email.com',
            'loyalty_code' => 'LYL-0003', 'loyalty_points' => 150,
            'store_credit_balance' => 25.00, 'group_id' => $regularGroupId,
            'total_spend' => 1200.00, 'visit_count' => 18,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Loyalty Config
        DB::table('loyalty_config')->insertOrIgnore([
            'organization_id' => $this->orgId,
            'points_per_sar' => 1, 'sar_per_point' => 0.10,
            'min_redemption_points' => 100, 'points_expiry_months' => 12,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Loyalty Transactions
        DB::table('loyalty_transactions')->insert([
            ['customer_id' => $customer1, 'type' => 'earn', 'points' => 150, 'balance_after' => 2500, 'notes' => 'Purchase #1001', 'performed_by' => $this->ownerId, 'created_at' => now()->subDays(5)],
            ['customer_id' => $customer1, 'type' => 'redeem', 'points' => -100, 'balance_after' => 2400, 'notes' => 'Redeemed for discount', 'performed_by' => $this->ownerId, 'created_at' => now()->subDays(2)],
            ['customer_id' => $customer2, 'type' => 'earn', 'points' => 35, 'balance_after' => 835, 'notes' => 'Purchase #1002', 'performed_by' => $this->ownerId, 'created_at' => now()->subDay()],
        ]);

        // Store Credit Transactions
        DB::table('store_credit_transactions')->insert([
            'customer_id' => $customer1, 'type' => 'credit', 'amount' => 50.00,
            'balance_after' => 50.00, 'notes' => 'Return refund as store credit', 'performed_by' => $this->ownerId,
            'created_at' => now()->subDays(3),
        ]);

        // Loyalty Badges
        $badgeId = DB::table('loyalty_badges')->insertGetId([
            'store_id' => $this->storeId, 'name_ar' => 'عميل مميز', 'name_en' => 'Star Customer',
            'icon_url' => 'badges/star.svg', 'description_en' => 'Awarded for 100+ visits',
        ]);

        // Loyalty Challenges
        $challengeId = DB::table('loyalty_challenges')->insertGetId([
            'store_id' => $this->storeId, 'name_ar' => 'تحدي المشتريات', 'name_en' => 'Spending Challenge',
            'challenge_type' => 'spending', 'target_value' => 500.00,
            'reward_type' => 'points', 'reward_value' => 100,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'is_active' => true,
        ]);

        // Loyalty Tiers
        DB::table('loyalty_tiers')->insertOrIgnore([
            'store_id' => $this->storeId,
            'tier_name_ar' => 'برونزي', 'tier_name_en' => 'Bronze',
            'tier_order' => 1, 'min_points' => 0,
            'benefits' => json_encode(['discount' => 0]),
        ]);

        // Customer Badge
        DB::table('customer_badges')->insertOrIgnore([
            'customer_id' => $customer1, 'badge_id' => $badgeId, 'earned_at' => now()->subDays(10),
        ]);

        // Customer Challenge Progress
        DB::table('customer_challenge_progress')->insertOrIgnore([
            'customer_id' => $customer1, 'challenge_id' => $challengeId,
            'current_value' => 350.00, 'is_completed' => false, 'reward_claimed' => false,
        ]);

        // Wishlists
        $firstProduct = DB::table('products')->where('organization_id', $this->orgId)->first();
        if ($firstProduct) {
            DB::table('wishlists')->insertOrIgnore([
                'store_id' => $this->storeId, 'customer_id' => $customer1,
                'product_id' => $firstProduct->id, 'added_at' => now(),
            ]);
        }

        // Gift Registry
        $registryId = DB::table('gift_registries')->insertGetId([
            'store_id' => $this->storeId, 'customer_id' => $customer3,
            'name' => 'Baby Shower', 'event_type' => 'baby_shower',
            'event_date' => now()->addMonth()->toDateString(),
            'share_code' => 'GIFT-' . strtoupper(Str::random(6)), 'is_active' => true,
        ]);

        $this->command->info('  ✓ Customers: 3 customers, groups, loyalty, badges, challenges');
    }

    // ─────────────────────────────────────────────────────────────
    // PROMOTIONS (Tier 9)
    // ─────────────────────────────────────────────────────────────
    private function seedPromotions(): void
    {
        $this->command->info('Seeding promotions...');

        // Promotions
        $promo1 = DB::table('promotions')->insertGetId([
            'organization_id' => $this->orgId, 'name' => '20% Off Dairy',
            'type' => 'percentage',
            'discount_value' => 20.00,
            'min_order_total' => 0, 'valid_from' => now()->subDays(7),
            'valid_to' => now()->addDays(30), 'is_stackable' => false,
            'is_coupon' => false, 'is_active' => true, 'usage_count' => 45,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $promo2 = DB::table('promotions')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Ramadan Special',
            'type' => 'fixed_amount',
            'discount_value' => 10.00,
            'min_order_total' => 50.00, 'valid_from' => now(),
            'valid_to' => now()->addDays(30), 'is_stackable' => false,
            'is_coupon' => true, 'is_active' => true, 'usage_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Coupon codes for promo2
        DB::table('coupon_codes')->insert([
            ['promotion_id' => $promo2, 'code' => 'RAMADAN10', 'max_uses' => 100, 'usage_count' => 0, 'is_active' => true],
            ['promotion_id' => $promo2, 'code' => 'EID25', 'max_uses' => 50, 'usage_count' => 0, 'is_active' => true],
        ]);

        // Promotion -> Category links
        $dairyCatId = DB::table('categories')->where('organization_id', $this->orgId)->where('name', 'Dairy & Eggs')->value('id');
        if ($dairyCatId) {
            DB::table('promotion_categories')->insertOrIgnore([
                'promotion_id' => $promo1, 'category_id' => $dairyCatId,
            ]);
        }

        // Promotion -> Customer Group
        $vipGroupId = DB::table('customer_groups')->where('organization_id', $this->orgId)->where('name', 'VIP')->value('id');
        if ($vipGroupId) {
            DB::table('promotion_customer_groups')->insertOrIgnore([
                'promotion_id' => $promo2, 'customer_group_id' => $vipGroupId,
            ]);
        }

        $this->command->info('  ✓ Promotions: 2 promotions, coupons, category/group links');
    }

    // ─────────────────────────────────────────────────────────────
    // POS & TRANSACTIONS (Tier 10)
    // ─────────────────────────────────────────────────────────────
    private function seedPosAndTransactions(): void
    {
        $this->command->info('Seeding POS & transactions...');

        $registerId = DB::table('registers')->where('store_id', $this->storeId)->value('id');
        $customer1 = DB::table('customers')->where('organization_id', $this->orgId)->first();
        $productIds = DB::table('products')->where('organization_id', $this->orgId)->pluck('id', 'sku')->toArray();

        // POS Session
        $sessionId = DB::table('pos_sessions')->insertGetId([
            'store_id' => $this->storeId, 'register_id' => $registerId,
            'cashier_id' => $this->ownerId, 'status' => 'open',
            'opening_cash' => 500.00, 'opened_at' => now()->subHours(4),
            'transaction_count' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Transaction 1 (sale)
        $txn1 = DB::table('transactions')->insertGetId([
            'organization_id' => $this->orgId, 'store_id' => $this->storeId,
            'register_id' => $registerId, 'pos_session_id' => $sessionId,
            'cashier_id' => $this->ownerId, 'customer_id' => $customer1?->id,
            'transaction_number' => 'TXN-2025-0001', 'type' => 'sale',
            'status' => 'completed', 'subtotal' => 25.50, 'discount_amount' => 0.00,
            'tax_amount' => 3.83, 'tip_amount' => 0.00, 'total_amount' => 29.33,
            'sync_status' => 'synced', 'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        // Transaction Items
        $banana = $productIds['FRU-001'] ?? null;
        $milk = $productIds['DAI-001'] ?? null;
        $water = $productIds['BEV-001'] ?? null;

        if ($banana) DB::table('transaction_items')->insert(['transaction_id' => $txn1, 'product_id' => $banana, 'product_name' => 'Banana', 'barcode' => '6281000000101', 'quantity' => 2.5, 'unit_price' => 5.00, 'cost_price' => 2.50, 'discount_amount' => 0, 'tax_amount' => 1.88, 'line_total' => 14.38]);
        if ($milk) DB::table('transaction_items')->insert(['transaction_id' => $txn1, 'product_id' => $milk, 'product_name' => 'Fresh Milk 1L', 'barcode' => '6281000000201', 'quantity' => 1, 'unit_price' => 6.50, 'cost_price' => 4.00, 'discount_amount' => 0, 'tax_amount' => 0.98, 'line_total' => 7.48]);
        if ($water) DB::table('transaction_items')->insert(['transaction_id' => $txn1, 'product_id' => $water, 'product_name' => 'Water 500ml', 'barcode' => '6281000000301', 'quantity' => 3, 'unit_price' => 1.00, 'cost_price' => 0.30, 'discount_amount' => 0, 'tax_amount' => 0.45, 'line_total' => 3.45]);

        // Transaction 2 (smaller sale)
        $txn2 = DB::table('transactions')->insertGetId([
            'organization_id' => $this->orgId, 'store_id' => $this->storeId,
            'register_id' => $registerId, 'pos_session_id' => $sessionId,
            'cashier_id' => $this->ownerId,
            'transaction_number' => 'TXN-2025-0002', 'type' => 'sale',
            'status' => 'completed', 'subtotal' => 4.00, 'discount_amount' => 0.00,
            'tax_amount' => 0.60, 'tip_amount' => 0.00, 'total_amount' => 4.60,
            'sync_status' => 'synced', 'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $chips = $productIds['SNK-001'] ?? null;
        if ($chips) DB::table('transaction_items')->insert(['transaction_id' => $txn2, 'product_id' => $chips, 'product_name' => 'Chips Original', 'barcode' => '6281000000401', 'quantity' => 1, 'unit_price' => 4.00, 'cost_price' => 2.00, 'discount_amount' => 0, 'tax_amount' => 0.60, 'line_total' => 4.60]);

        // Held Cart
        DB::table('held_carts')->insert([
            'store_id' => $this->storeId, 'register_id' => $registerId,
            'cashier_id' => $this->ownerId, 'customer_id' => $customer1?->id,
            'cart_data' => json_encode([
                ['product_id' => $banana, 'name' => 'Banana', 'qty' => 1, 'price' => 5.00],
                ['product_id' => $water, 'name' => 'Water', 'qty' => 6, 'price' => 1.00],
            ]),
            'label' => 'Fatima cart', 'held_at' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
        ]);

        // Cash Session
        $cashSessionId = DB::table('cash_sessions')->insertGetId([
            'store_id' => $this->storeId, 'opened_by' => $this->ownerId,
            'opening_float' => 500.00, 'status' => 'open',
            'opened_at' => now()->subHours(4),
        ]);

        // Cash Events
        DB::table('cash_events')->insert([
            ['cash_session_id' => $cashSessionId, 'type' => 'cash_in', 'amount' => 500.00, 'reason' => 'Opening float', 'performed_by' => $this->ownerId, 'created_at' => now()->subHours(4)],
            ['cash_session_id' => $cashSessionId, 'type' => 'cash_out', 'amount' => 100.00, 'reason' => 'Bank deposit', 'performed_by' => $this->ownerId, 'created_at' => now()->subHour()],
        ]);

        // Expenses
        DB::table('expenses')->insert([
            'store_id' => $this->storeId, 'cash_session_id' => $cashSessionId,
            'amount' => 50.00, 'category' => 'cleaning', 'description' => 'Cleaning supplies',
            'recorded_by' => $this->ownerId, 'expense_date' => now()->toDateString(),
            'created_at' => now(),
        ]);

        $this->command->info('  ✓ POS: sessions, 2 transactions, held cart, cash session');
    }

    // ─────────────────────────────────────────────────────────────
    // ORDERS & FULFILLMENT (Tier 11)
    // ─────────────────────────────────────────────────────────────
    private function seedOrdersAndFulfillment(): void
    {
        $this->command->info('Seeding orders & fulfillment...');

        $txn1 = DB::table('transactions')->where('transaction_number', 'TXN-2025-0001')->value('id');
        $customer1 = DB::table('customers')->where('organization_id', $this->orgId)->first();
        $productIds = DB::table('products')->where('organization_id', $this->orgId)->pluck('id', 'sku')->toArray();

        // Order 1 (completed)
        $order1 = DB::table('orders')->insertGetId([
            'store_id' => $this->storeId, 'transaction_id' => $txn1,
            'customer_id' => $customer1?->id, 'order_number' => 'ORD-2025-0001',
            'source' => 'pos', 'status' => 'completed',
            'subtotal' => 25.50, 'tax_amount' => 3.83, 'discount_amount' => 0.00, 'total' => 29.33,
            'created_by' => $this->ownerId,
            'created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3),
        ]);

        // Order Items
        if ($banana = ($productIds['FRU-001'] ?? null)) {
            DB::table('order_items')->insert(['order_id' => $order1, 'product_id' => $banana, 'product_name' => 'Banana', 'quantity' => 2.5, 'unit_price' => 5.00, 'discount_amount' => 0, 'tax_amount' => 1.88, 'total' => 14.38]);
        }
        if ($milk = ($productIds['DAI-001'] ?? null)) {
            DB::table('order_items')->insert(['order_id' => $order1, 'product_id' => $milk, 'product_name' => 'Fresh Milk 1L', 'quantity' => 1, 'unit_price' => 6.50, 'discount_amount' => 0, 'tax_amount' => 0.98, 'total' => 7.48]);
        }

        // Order Status History
        DB::table('order_status_history')->insert([
            ['order_id' => $order1, 'from_status' => null, 'to_status' => 'new', 'changed_by' => $this->ownerId, 'created_at' => now()->subHours(3)],
            ['order_id' => $order1, 'from_status' => 'new', 'to_status' => 'completed', 'changed_by' => $this->ownerId, 'created_at' => now()->subHours(3)],
        ]);

        // Order 2 (pending delivery)
        $order2 = DB::table('orders')->insertGetId([
            'store_id' => $this->storeId,
            'customer_id' => $customer1?->id, 'order_number' => 'ORD-2025-0002',
            'source' => 'delivery', 'status' => 'confirmed',
            'subtotal' => 45.00, 'tax_amount' => 6.75, 'discount_amount' => 5.00, 'total' => 46.75,
            'external_order_id' => 'EXT-12345',
            'created_by' => $this->ownerId,
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);

        // Order Delivery Info
        DB::table('order_delivery_info')->insertOrIgnore([
            'order_id' => $order2, 'platform' => 'jahez',
            'driver_name' => 'Ali Driver', 'driver_phone' => '+966507777777',
            'estimated_delivery' => now()->addMinutes(30),
            'delivery_fee' => 10.00,
        ]);

        // Return
        $returnId = DB::table('returns')->insertGetId([
            'store_id' => $this->storeId, 'order_id' => $order1,
            'return_number' => 'RTN-2025-0001', 'type' => 'partial',
            'reason_code' => 'defective',
            'refund_method' => 'store_credit', 'subtotal' => 6.50, 'tax_amount' => 0.98, 'total_refund' => 7.48,
            'processed_by' => $this->ownerId, 'created_at' => now()->subHour(),
        ]);

        $milkOrderItem = DB::table('order_items')->where('order_id', $order1)->where('product_id', $milk ?? 0)->value('id');
        if ($milkOrderItem && $milk) {
            DB::table('return_items')->insert([
                'return_id' => $returnId, 'order_item_id' => $milkOrderItem,
                'product_id' => $milk, 'quantity' => 1, 'unit_price' => 6.50, 'refund_amount' => 7.48,
                'restore_stock' => false,
            ]);
        }

        // Pending Order
        DB::table('pending_orders')->insert([
            'store_id' => $this->storeId, 'customer_id' => $customer1?->id,
            'items_json' => json_encode([
                ['product_id' => $banana, 'name' => 'Banana', 'qty' => 3, 'price' => 5.00],
            ]),
            'total' => 17.25, 'created_by' => $this->ownerId,
            'expires_at' => now()->addHours(24), 'created_at' => now(),
        ]);

        $this->command->info('  ✓ Orders: 2 orders, items, delivery, return, pending');
    }

    // ─────────────────────────────────────────────────────────────
    // PAYMENTS & FINANCIAL (Tier 12)
    // ─────────────────────────────────────────────────────────────
    private function seedPaymentsAndFinancial(): void
    {
        $this->command->info('Seeding payments & financial...');

        $txn1 = DB::table('transactions')->where('transaction_number', 'TXN-2025-0001')->value('id');
        $txn2 = DB::table('transactions')->where('transaction_number', 'TXN-2025-0002')->value('id');

        // Payments
        $payment1 = DB::table('payments')->insertGetId([
            'transaction_id' => $txn1, 'method' => 'cash',
            'amount' => 29.33, 'cash_tendered' => 50.00, 'change_given' => 20.67,
            'tip_amount' => 0.00, 'created_at' => now()->subHours(3),
        ]);

        $payment2 = DB::table('payments')->insertGetId([
            'transaction_id' => $txn2, 'method' => 'mada',
            'amount' => 4.60, 'card_brand' => 'mada', 'card_last_four' => '1234',
            'card_auth_code' => 'AUTH-98765', 'card_reference' => 'REF-12345',
            'created_at' => now()->subHours(2),
        ]);

        // Gift Card
        $giftCardId = DB::table('gift_cards')->insertGetId([
            'organization_id' => $this->orgId, 'code' => 'GC-2025-001',
            'barcode' => '9900000000001', 'initial_amount' => 200.00,
            'balance' => 150.00, 'recipient_name' => 'Ali Family',
            'status' => 'active', 'issued_by' => $this->ownerId,
            'issued_at_store' => $this->storeId,
            'expires_at' => now()->addYear(), 'created_at' => now(),
        ]);

        DB::table('gift_card_transactions')->insert([
            'gift_card_id' => $giftCardId, 'type' => 'redeem', 'amount' => 50.00,
            'balance_after' => 150.00, 'store_id' => $this->storeId, 'performed_by' => $this->ownerId,
            'created_at' => now()->subDays(2),
        ]);

        // Refund (linked to return)
        $returnId = DB::table('returns')->where('return_number', 'RTN-2025-0001')->value('id');
        if ($returnId) {
            DB::table('refunds')->insert([
                'return_id' => $returnId, 'payment_id' => $payment1,
                'method' => 'store_credit', 'amount' => 7.48,
                'status' => 'completed', 'processed_by' => $this->ownerId,
                'created_at' => now()->subHour(),
            ]);
        }

        $this->command->info('  ✓ Payments: cash + mada, gift card, refund');
    }

    // ─────────────────────────────────────────────────────────────
    // NOTIFICATIONS (Tier 13)
    // ─────────────────────────────────────────────────────────────
    private function seedNotifications(): void
    {
        $this->command->info('Seeding notifications...');

        // Notification Templates
        $templates = [
            ['event_key' => 'order.new', 'channel' => 'push', 'title' => 'New Order', 'title_ar' => 'طلب جديد', 'body' => 'Order #{order_number} received', 'body_ar' => 'تم استلام الطلب #{order_number}', 'available_variables' => json_encode(['order_number', 'total']), 'is_active' => true],
            ['event_key' => 'order.new', 'channel' => 'sms', 'title' => 'New Order', 'title_ar' => 'طلب جديد', 'body' => 'New order #{order_number} totaling {total} SAR', 'body_ar' => 'طلب جديد #{order_number} بقيمة {total} ريال', 'available_variables' => json_encode(['order_number', 'total']), 'is_active' => true],
            ['event_key' => 'stock.low', 'channel' => 'push', 'title' => 'Low Stock Alert', 'title_ar' => 'تنبيه نقص المخزون', 'body' => '{product_name} is below reorder point', 'body_ar' => '{product_name} أقل من نقطة إعادة الطلب', 'available_variables' => json_encode(['product_name', 'current_qty', 'reorder_point']), 'is_active' => true],
            ['event_key' => 'subscription.expiring', 'channel' => 'email', 'title' => 'Subscription Expiring', 'title_ar' => 'الاشتراك ينتهي قريباً', 'body' => 'Your subscription expires on {expiry_date}', 'body_ar' => 'ينتهي اشتراكك في {expiry_date}', 'available_variables' => json_encode(['expiry_date', 'plan_name']), 'is_active' => true],
        ];
        foreach ($templates as $t) {
            DB::table('notification_templates')->insertOrIgnore(array_merge($t, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Notification Provider Status
        DB::table('notification_provider_status')->insertOrIgnore([
            ['provider' => 'firebase', 'channel' => 'push', 'is_enabled' => true, 'priority' => 1, 'is_healthy' => true, 'failure_count_24h' => 0],
            ['provider' => 'unifonic', 'channel' => 'sms', 'is_enabled' => true, 'priority' => 1, 'is_healthy' => true, 'failure_count_24h' => 2],
            ['provider' => 'sendgrid', 'channel' => 'email', 'is_enabled' => true, 'priority' => 1, 'is_healthy' => true, 'failure_count_24h' => 0],
        ]);

        // Notifications
        $notifId = DB::table('notifications')->insertGetId([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\LowStockAlert',
            'notifiable_type' => 'App\\Domain\\Auth\\Models\\User',
            'notifiable_id' => $this->ownerId,
            'data' => json_encode(['product_name' => 'Fresh Milk 1L', 'current_qty' => 5, 'reorder_point' => 10]),
            'created_at' => now(),
        ]);

        // Notification Preferences
        DB::table('notification_preferences')->insertOrIgnore([
            ['user_id' => $this->ownerId, 'event_key' => 'order.new', 'channel' => 'push', 'is_enabled' => true],
            ['user_id' => $this->ownerId, 'event_key' => 'stock.low', 'channel' => 'push', 'is_enabled' => true],
            ['user_id' => $this->ownerId, 'event_key' => 'subscription.expiring', 'channel' => 'email', 'is_enabled' => true],
        ]);

        // FCM Tokens
        DB::table('fcm_tokens')->insertOrIgnore([
            'user_id' => $this->ownerId, 'token' => 'fcm_test_token_' . Str::random(32),
            'device_type' => 'android', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->command->info('  ✓ Notifications: templates, providers, preferences, FCM token');
    }

    // ─────────────────────────────────────────────────────────────
    // DELIVERY INTEGRATIONS (Tier 14)
    // ─────────────────────────────────────────────────────────────
    private function seedDeliveryIntegrations(): void
    {
        $this->command->info('Seeding delivery integrations...');

        // Delivery Platforms
        $platforms = [
            ['name' => 'Jahez', 'slug' => 'jahez', 'logo_url' => 'delivery/jahez.png', 'auth_method' => 'api_key', 'is_active' => true, 'sort_order' => 1, 'name_ar' => 'جاهز'],
            ['name' => 'HungerStation', 'slug' => 'hungerstation', 'logo_url' => 'delivery/hunger.png', 'auth_method' => 'oauth2', 'is_active' => true, 'sort_order' => 2, 'name_ar' => 'هنقرستيشن'],
            ['name' => 'ToYou', 'slug' => 'toyou', 'logo_url' => 'delivery/toyou.png', 'auth_method' => 'api_key', 'is_active' => true, 'sort_order' => 3, 'name_ar' => 'تويو'],
            ['name' => 'Marsool', 'slug' => 'marsool', 'logo_url' => 'delivery/marsool.png', 'auth_method' => 'api_key', 'is_active' => true, 'sort_order' => 4, 'name_ar' => 'مرسول'],
            ['name' => 'Keeta', 'slug' => 'keeta', 'logo_url' => 'delivery/keeta.png', 'auth_method' => 'api_key', 'is_active' => true, 'sort_order' => 5, 'name_ar' => 'كيتا'],
            ['name' => 'Noon Food', 'slug' => 'noon_food', 'logo_url' => 'delivery/noon_food.png', 'auth_method' => 'oauth2', 'is_active' => true, 'sort_order' => 6, 'name_ar' => 'نون فود'],
            ['name' => 'Ninja', 'slug' => 'ninja', 'logo_url' => 'delivery/ninja.png', 'auth_method' => 'api_key', 'is_active' => true, 'sort_order' => 7, 'name_ar' => 'نينجا'],
            ['name' => 'The Chefz', 'slug' => 'the_chefz', 'logo_url' => 'delivery/the_chefz.png', 'auth_method' => 'api_key', 'is_active' => true, 'sort_order' => 8, 'name_ar' => 'ذا شفز'],
            ['name' => 'Talabat', 'slug' => 'talabat', 'logo_url' => 'delivery/talabat.png', 'auth_method' => 'oauth2', 'is_active' => true, 'sort_order' => 9, 'name_ar' => 'طلبات'],
            ['name' => 'Carriage', 'slug' => 'carriage', 'logo_url' => 'delivery/carriage.png', 'auth_method' => 'api_key', 'is_active' => true, 'sort_order' => 10, 'name_ar' => 'كاريدج'],
        ];
        foreach ($platforms as $p) {
            DB::table('delivery_platforms')->insertOrIgnore(array_merge($p, ['created_at' => now(), 'updated_at' => now()]));
        }

        $jahezId = DB::table('delivery_platforms')->where('slug', 'jahez')->value('id');

        if ($jahezId) {
            // Delivery Platform Fields
            DB::table('delivery_platform_fields')->insertOrIgnore([
                ['delivery_platform_id' => $jahezId, 'field_label' => 'API Key', 'field_key' => 'jahez_api_key', 'field_type' => 'password', 'is_required' => true],
                ['delivery_platform_id' => $jahezId, 'field_label' => 'Merchant ID', 'field_key' => 'jahez_merchant_id', 'field_type' => 'text', 'is_required' => true],
            ]);

            // Store Delivery Platform
            DB::table('store_delivery_platforms')->insertOrIgnore([
                'store_id' => $this->storeId, 'delivery_platform_id' => $jahezId,
                'credentials' => json_encode(['api_key' => 'test_key_xxx', 'merchant_id' => 'MER-001']),
                'inbound_api_key' => 'IBK-' . Str::random(24),
                'is_enabled' => true, 'sync_status' => 'synced', 'last_sync_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Delivery Platform Config
            DB::table('delivery_platform_configs')->insertOrIgnore([
                'store_id' => $this->storeId, 'platform' => 'jahez',
                'api_key' => 'test_api_key_jahez',
                'merchant_id' => 'MER-001', 'is_enabled' => true,
                'auto_accept' => true, 'last_menu_sync_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Platform Delivery Integration (managed centrally)
        DB::table('platform_delivery_integrations')->insertOrIgnore([
            'platform_slug' => 'jahez', 'display_name' => 'Jahez',
            'api_base_url' => 'https://api.jahez.net/v2',
            'default_commission_percent' => 15.00, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Delivery Order Mapping
        $order2 = DB::table('orders')->where('order_number', 'ORD-2025-0002')->value('id');
        if ($order2) {
            DB::table('delivery_order_mappings')->insertOrIgnore([
                'order_id' => $order2, 'platform' => 'jahez',
                'external_order_id' => 'JHZ-99001', 'external_status' => 'assigned',
                'commission_amount' => 7.01,
                'raw_payload' => json_encode(['driver_name' => 'Ali', 'eta' => 25]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->command->info('  ✓ Delivery: 3 platforms, config, order mapping');
    }

    // ─────────────────────────────────────────────────────────────
    // ACCOUNTING INTEGRATION (Tier 15)
    // ─────────────────────────────────────────────────────────────
    private function seedAccountingIntegration(): void
    {
        $this->command->info('Seeding accounting integration...');

        DB::table('accounting_integration_configs')->insertOrIgnore([
            'provider_name' => 'quickbooks',
            'client_id_encrypted' => encrypt('test_client_id'),
            'client_secret_encrypted' => encrypt('test_client_secret'),
            'redirect_url' => 'https://app.thawani.om/callback/qbo',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('store_accounting_configs')->insertOrIgnore([
            'store_id' => $this->storeId, 'provider' => 'quickbooks',
            'access_token_encrypted' => encrypt('test_access_token'),
            'refresh_token_encrypted' => encrypt('test_refresh_token'),
            'token_expires_at' => now()->addHour(),
            'realm_id' => 'QBO-123456', 'connected_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('account_mappings')->insert([
            ['store_id' => $this->storeId, 'pos_account_key' => 'sales_revenue', 'provider_account_id' => '4000', 'provider_account_name' => 'Sales Revenue'],
            ['store_id' => $this->storeId, 'pos_account_key' => 'cost_of_goods', 'provider_account_id' => '5000', 'provider_account_name' => 'Cost of Goods Sold'],
            ['store_id' => $this->storeId, 'pos_account_key' => 'tax_payable', 'provider_account_id' => '2100', 'provider_account_name' => 'VAT Payable'],
        ]);

        DB::table('accounting_exports')->insert([
            'store_id' => $this->storeId, 'provider' => 'quickbooks',
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'export_types' => json_encode(['sales', 'expenses']),
            'status' => 'completed', 'entries_count' => 156,
            'triggered_by' => 'admin', 'created_at' => now(),
        ]);

        DB::table('auto_export_configs')->insertOrIgnore([
            'store_id' => $this->storeId, 'enabled' => true,
            'frequency' => 'daily', 'time' => '02:00',
            'export_types' => json_encode(['sales', 'expenses', 'inventory']),
            'next_run_at' => now()->addDay()->setTime(2, 0), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->command->info('  ✓ Accounting: QBO config, mappings, export');
    }

    // ─────────────────────────────────────────────────────────────
    // THAWANI INTEGRATION (Tier 16)
    // ─────────────────────────────────────────────────────────────
    private function seedThawaniIntegration(): void
    {
        $this->command->info('Seeding Thawani integration...');

        DB::table('thawani_marketplace_config')->insertOrIgnore([
            'client_id_encrypted' => encrypt('test_thawani_client_id'),
            'client_secret_encrypted' => encrypt('test_thawani_client_secret'),
            'redirect_url' => 'https://pos.thawani.om/callback/marketplace',
            'api_base_url' => 'https://api.thawani.om/v1',
            'api_version' => 'v1', 'sync_interval_minutes' => 15,
            'webhook_url' => 'https://pos.thawani.om/webhooks/marketplace',
            'webhook_secret_encrypted' => encrypt('test_webhook_secret'),
            'updated_at' => now(),
        ]);

        DB::table('thawani_store_config')->insertOrIgnore([
            'store_id' => $this->storeId, 'thawani_store_id' => 'TH-STORE-001',
            'is_connected' => true, 'auto_sync_products' => true,
            'auto_sync_inventory' => true, 'auto_accept_orders' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $firstProduct = DB::table('products')->where('organization_id', $this->orgId)->first();
        if ($firstProduct) {
            DB::table('thawani_product_mappings')->insertOrIgnore([
                'store_id' => $this->storeId, 'product_id' => $firstProduct->id,
                'thawani_product_id' => 'TH-PRD-001', 'is_published' => true,
                'online_price' => $firstProduct->sell_price, 'last_synced_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('thawani_settlements')->insert([
            'store_id' => $this->storeId, 'settlement_date' => now()->subDays(7)->toDateString(),
            'gross_amount' => 5000.00, 'commission_amount' => 250.00,
            'net_amount' => 4750.00, 'order_count' => 85,
            'thawani_reference' => 'STL-' . Str::random(12), 'created_at' => now(),
        ]);

        $this->command->info('  ✓ Thawani: marketplace config, store config, settlement');
    }

    // ─────────────────────────────────────────────────────────────
    // ZATCA COMPLIANCE (Tier 17)
    // ─────────────────────────────────────────────────────────────
    private function seedZatcaCompliance(): void
    {
        $this->command->info('Seeding ZATCA compliance...');

        DB::table('zatca_certificates')->insert([
            'store_id' => $this->storeId, 'certificate_type' => 'compliance',
            'certificate_pem' => '-----BEGIN CERTIFICATE-----\nTEST_CERTIFICATE_DATA\n-----END CERTIFICATE-----',
            'ccsid' => 'CCSID-TEST-' . Str::random(16),
            'issued_at' => now()->subMonths(6), 'expires_at' => now()->addMonths(6),
            'status' => 'active', 'created_at' => now(),
        ]);

        $order1 = DB::table('orders')->where('order_number', 'ORD-2025-0001')->value('id');
        if ($order1) {
            DB::table('zatca_invoices')->insert([
                'store_id' => $this->storeId, 'order_id' => $order1,
                'invoice_number' => 'INV-2025-0001', 'invoice_type' => 'standard',
                'invoice_xml' => '<Invoice>TEST</Invoice>',
                'invoice_hash' => hash('sha256', 'INV-2025-0001'),
                'previous_invoice_hash' => hash('sha256', 'GENESIS'),
                'digital_signature' => base64_encode('test_signature'),
                'qr_code_data' => base64_encode('ZATCA QR test data'),
                'total_amount' => 150.00, 'vat_amount' => 22.50,
                'submission_status' => 'reported', 'created_at' => now(),
            ]);
        }

        $this->command->info('  ✓ ZATCA: certificate, invoice');
    }

    // ─────────────────────────────────────────────────────────────
    // POS CUSTOMIZATION (Tier 18)
    // ─────────────────────────────────────────────────────────────
    private function seedPosCustomization(): void
    {
        $this->command->info('Seeding POS customization...');

        DB::table('pos_customization_settings')->insertOrIgnore([
            'store_id' => $this->storeId, 'theme' => 'light',
            'primary_color' => '#1B4D3E', 'secondary_color' => '#F5A623',
            'font_scale' => 1.0, 'handedness' => 'right',
            'grid_columns' => 4, 'show_product_images' => true,
            'cart_display_mode' => 'list', 'updated_at' => now(),
        ]);

        DB::table('receipt_templates')->insertOrIgnore([
            'store_id' => $this->storeId, 'logo_url' => 'receipts/ostora-logo.png',
            'header_line_1' => 'Ostora Supermarket', 'header_line_2' => 'أستورا سوبرماركت',
            'footer_text' => 'شكراً لتسوقكم — Thank you!',
            'show_vat_number' => true, 'show_loyalty_points' => true,
            'paper_width_mm' => 80, 'updated_at' => now(),
        ]);

        DB::table('quick_access_configs')->insertOrIgnore([
            'store_id' => $this->storeId, 'grid_rows' => 3, 'grid_cols' => 4,
            'buttons_json' => json_encode([
                ['type' => 'product', 'product_id' => DB::table('products')->where('sku', 'BEV-001')->value('id'), 'label' => 'Water', 'color' => '#2196F3'],
                ['type' => 'product', 'product_id' => DB::table('products')->where('sku', 'BAK-001')->value('id'), 'label' => 'Bread', 'color' => '#FF9800'],
                ['type' => 'category', 'category_id' => DB::table('categories')->where('organization_id', $this->orgId)->first()?->id, 'label' => 'Fruits', 'color' => '#4CAF50'],
            ]),
            'updated_at' => now(),
        ]);

        // User Preferences
        DB::table('user_preferences')->insertOrIgnore([
            'user_id' => $this->ownerId, 'pos_handedness' => 'right',
            'font_size' => 'medium', 'theme' => 'default',
        ]);

        // CFD Configuration
        DB::table('cfd_configurations')->insertOrIgnore([
            'store_id' => $this->storeId, 'is_enabled' => true,
            'target_monitor' => 2,
            'theme_config' => json_encode(['background' => '#FFFFFF', 'cart_layout' => 'list']),
            'idle_rotation_seconds' => 10,
        ]);

        // Signage Playlist
        DB::table('signage_playlists')->insertOrIgnore([
            'store_id' => $this->storeId, 'name' => 'Main Display',
            'slides' => json_encode([
                ['type' => 'image', 'url' => 'signage/offer1.jpg', 'duration' => 8],
                ['type' => 'image', 'url' => 'signage/offer2.jpg', 'duration' => 8],
            ]),
            'schedule' => json_encode(['start' => '08:00', 'end' => '23:00']),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Translation Overrides
        DB::table('translation_overrides')->insertOrIgnore([
            'store_id' => $this->storeId, 'string_key' => 'receipt.thank_you',
            'locale' => 'ar', 'custom_value' => 'شكراً لزيارتكم أستورا!',
        ]);

        $this->command->info('  ✓ POS Customization: theme, receipt, quick access, CFD');
    }

    // ─────────────────────────────────────────────────────────────
    // LABELS (Tier 19)
    // ─────────────────────────────────────────────────────────────
    private function seedLabels(): void
    {
        $this->command->info('Seeding labels...');

        $templateId = DB::table('label_templates')->insertGetId([
            'organization_id' => $this->orgId, 'name' => 'Shelf Label',
            'label_width_mm' => 60, 'label_height_mm' => 30,
            'layout_json' => json_encode([
                'fields' => ['product_name', 'barcode', 'price'],
                'barcode_type' => 'ean13', 'font_size' => 10,
            ]),
            'is_preset' => true, 'is_default' => true,
            'created_by' => $this->ownerId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('label_print_history')->insert([
            'store_id' => $this->storeId, 'template_id' => $templateId,
            'printed_by' => $this->ownerId, 'product_count' => 10,
            'total_labels' => 10, 'printer_name' => 'Zebra ZD421',
            'printed_at' => now(),
        ]);

        $this->command->info('  ✓ Labels: template, print history');
    }

    // ─────────────────────────────────────────────────────────────
    // HARDWARE (Tier 20)
    // ─────────────────────────────────────────────────────────────
    private function seedHardware(): void
    {
        $this->command->info('Seeding hardware...');

        DB::table('hardware_configurations')->insertOrIgnore([
            'store_id' => $this->storeId, 'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer', 'connection_type' => 'usb',
            'config_json' => json_encode(['driver' => 'esc_pos', 'model' => 'Epson TM-T88VI', 'auto_cut' => true]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('hardware_event_log')->insert([
            'store_id' => $this->storeId, 'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer', 'event' => 'connected',
            'details' => json_encode(['model' => 'Epson TM-T88VI']),
            'created_at' => now(),
        ]);

        DB::table('hardware_sales')->insert([
            'store_id' => $this->storeId, 'sold_by' => $this->adminId,
            'item_type' => 'receipt_printer', 'item_description' => 'Epson TM-T88VI',
            'serial_number' => 'EP-' . Str::random(12), 'amount' => 1200.00,
            'sold_at' => now(),
        ]);

        DB::table('implementation_fees')->insert([
            'store_id' => $this->storeId, 'fee_type' => 'setup',
            'notes' => 'Initial POS setup and training',
            'amount' => 500.00, 'status' => 'paid', 'created_at' => now(),
        ]);

        $this->command->info('  ✓ Hardware: config, event log, sales, fees');
    }

    // ─────────────────────────────────────────────────────────────
    // BACKUP & SYNC (Tier 21)
    // ─────────────────────────────────────────────────────────────
    private function seedBackupAndSync(): void
    {
        $this->command->info('Seeding backup & sync...');

        DB::table('backup_history')->insert([
            'store_id' => $this->storeId, 'terminal_id' => $this->terminalId,
            'backup_type' => 'full', 'storage_location' => 'cloud',
            'cloud_key' => 'backups/ostora-main/' . now()->format('Y-m-d') . '.sql.gz',
            'file_size_bytes' => 15728640, 'checksum' => hash('sha256', 'backup-test'),
            'db_version' => 1, 'status' => 'completed',
            'created_at' => now(),
        ]);

        DB::table('database_backups')->insert([
            'backup_type' => 'automated', 'file_path' => '/backups/daily/' . now()->format('Y-m-d') . '.sql.gz',
            'file_size_bytes' => 52428800, 'status' => 'completed',
            'started_at' => now()->subHours(2), 'completed_at' => now()->subHours(2)->addMinutes(5),
        ]);

        DB::table('sync_log')->insert([
            'store_id' => $this->storeId, 'terminal_id' => $this->terminalId,
            'direction' => 'upload', 'records_count' => 142,
            'duration_ms' => 3200, 'status' => 'success',
            'started_at' => now()->subMinutes(5),
        ]);

        DB::table('update_rollouts')->insert([
            'version' => '1.1.0', 'rollout_percentage' => 50,
            'is_critical' => false, 'release_notes' => 'Bug fixes and performance improvements',
        ]);

        DB::table('provider_backup_status')->insertOrIgnore([
            'store_id' => $this->storeId, 'terminal_id' => $this->terminalId,
            'last_successful_sync' => now()->subMinutes(30),
            'last_cloud_backup' => now()->subHour(),
            'storage_used_bytes' => 15728640,
        ]);

        $this->command->info('  ✓ Backup/Sync: history, database backup, sync log, rollout');
    }

    // ─────────────────────────────────────────────────────────────
    // ANALYTICS (Tier 22)
    // ─────────────────────────────────────────────────────────────
    private function seedAnalytics(): void
    {
        $this->command->info('Seeding analytics...');

        // Daily Sales Summary (last 7 days)
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            DB::table('daily_sales_summary')->insertOrIgnore([
                'store_id' => $this->storeId, 'date' => $date,
                'total_transactions' => rand(30, 80),
                'total_revenue' => rand(3000, 8000) + rand(0, 99) / 100,
                'total_tax' => rand(450, 1200) + rand(0, 99) / 100,
                'total_discount' => rand(50, 200) + rand(0, 99) / 100,
                'total_refunds' => rand(0, 300) + rand(0, 99) / 100,
                'unique_customers' => rand(15, 45),
            ]);
        }

        // Product Sales Summary
        $productIds = DB::table('products')->where('organization_id', $this->orgId)->limit(5)->pluck('id');
        foreach ($productIds as $pid) {
            DB::table('product_sales_summary')->insertOrIgnore([
                'store_id' => $this->storeId, 'product_id' => $pid,
                'date' => now()->toDateString(),
                'quantity_sold' => rand(5, 50),
                'revenue' => rand(50, 500) + rand(0, 99) / 100,
                'cost' => rand(20, 200) + rand(0, 99) / 100,
                'discount_amount' => rand(0, 50) + rand(0, 99) / 100,
            ]);
        }

        // Platform Daily Stats
        DB::table('platform_daily_stats')->insertOrIgnore([
            'date' => now()->toDateString(),
            'total_active_stores' => 150,
            'new_registrations' => 3,
            'total_orders' => 12500,
            'total_gmv' => 875000.00,
            'total_mrr' => 45000.00,
            'churn_count' => 1,
        ]);

        // Platform Plan Stats
        DB::table('platform_plan_stats')->insertOrIgnore([
            'subscription_plan_id' => $this->proPlanId,
            'date' => now()->toDateString(),
            'active_count' => 85, 'trial_count' => 12,
            'churned_count' => 1, 'mrr' => 16915.00,
        ]);

        // Feature Adoption Stats
        DB::table('feature_adoption_stats')->insertOrIgnore([
            'feature_key' => 'loyalty_program', 'date' => now()->toDateString(),
            'stores_using_count' => 67, 'total_events' => 4500,
        ]);

        // Store Health Snapshot
        DB::table('store_health_snapshots')->insertOrIgnore([
            'store_id' => $this->storeId, 'date' => now()->toDateString(),
            'sync_status' => 'healthy', 'zatca_compliance' => true,
            'error_count' => 0,
        ]);

        $this->command->info('  ✓ Analytics: 7 days sales, product summaries, platform stats');
    }

    // ─────────────────────────────────────────────────────────────
    // SUPPORT (Tier 23)
    // ─────────────────────────────────────────────────────────────
    private function seedSupport(): void
    {
        $this->command->info('Seeding support...');

        $ticketId = DB::table('support_tickets')->insertGetId([
            'ticket_number' => 'TKT-2025-0001',
            'organization_id' => $this->orgId, 'store_id' => $this->storeId,
            'user_id' => $this->ownerId, 'assigned_to' => $this->adminId,
            'subject' => 'Receipt printer not connecting',
            'description' => 'My Epson printer stopped connecting after the last update.',
            'category' => 'hardware', 'priority' => 'medium',
            'status' => 'in_progress',
            'sla_deadline_at' => now()->addHours(24),
            'created_at' => now()->subHours(6), 'updated_at' => now()->subHours(2),
        ]);

        DB::table('support_ticket_messages')->insert([
            ['support_ticket_id' => $ticketId, 'sender_type' => 'provider', 'sender_id' => $this->ownerId,
             'message_text' => 'My Epson printer stopped connecting after the last update. It was working fine before.',
             'is_internal_note' => false, 'sent_at' => now()->subHours(6)],
            ['support_ticket_id' => $ticketId, 'sender_type' => 'admin', 'sender_id' => $this->adminId,
             'message_text' => 'We are looking into this. Can you try restarting the printer and clearing the Bluetooth cache?',
             'is_internal_note' => false, 'sent_at' => now()->subHours(4)],
            ['support_ticket_id' => $ticketId, 'sender_type' => 'admin', 'sender_id' => $this->adminId,
             'message_text' => 'Likely USB driver issue after firmware update. Escalating to hardware team.',
             'is_internal_note' => true, 'sent_at' => now()->subHours(3)],
        ]);

        DB::table('canned_responses')->insertOrIgnore([
            ['title' => 'Receipt Printer Troubleshooting', 'shortcut' => '/printer',
             'body' => 'Please try: 1) Restart printer 2) Clear Bluetooth cache 3) Reconnect USB',
             'body_ar' => 'يرجى المحاولة: 1) أعد تشغيل الطابعة 2) امسح ذاكرة البلوتوث 3) أعد توصيل USB',
             'category' => 'hardware', 'is_active' => true, 'created_by' => $this->adminId],
            ['title' => 'Welcome Response', 'shortcut' => '/welcome',
             'body' => 'Thank you for contacting Thawani Support! How can we help you today?',
             'body_ar' => 'شكراً لتواصلكم مع دعم ثواني! كيف يمكننا مساعدتكم؟',
             'category' => 'general', 'is_active' => true, 'created_by' => $this->adminId],
        ]);

        $this->command->info('  ✓ Support: ticket with messages, canned responses');
    }

    // ─────────────────────────────────────────────────────────────
    // ANNOUNCEMENTS (Tier 24)
    // ─────────────────────────────────────────────────────────────
    private function seedAnnouncements(): void
    {
        $this->command->info('Seeding announcements...');

        $announcementId = DB::table('platform_announcements')->insertGetId([
            'type' => 'feature', 'title' => 'New Loyalty Program Features!',
            'title_ar' => 'مزايا جديدة لبرنامج الولاء!',
            'body' => 'We have added loyalty badges and challenges. Check them out in your dashboard.',
            'body_ar' => 'أضفنا شارات وتحديات الولاء. تحقق منها في لوحة القيادة.',
            'target_filter' => json_encode(['plans' => ['professional', 'enterprise']]),
            'display_start_at' => now()->subDays(3),
            'display_end_at' => now()->addDays(14),
            'created_by' => $this->adminId,
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);

        // Payment Reminders
        $subId = DB::table('store_subscriptions')->where('store_id', $this->storeId)->value('id');
        if ($subId) {
            DB::table('payment_reminders')->insert([
                'store_subscription_id' => $subId, 'reminder_type' => 'trial_ending',
                'channel' => 'push', 'sent_at' => now()->subDays(2),
            ]);
        }

        $this->command->info('  ✓ Announcements: feature announcement, payment reminder');
    }

    // ─────────────────────────────────────────────────────────────
    // APP UPDATES (Tier 25)
    // ─────────────────────────────────────────────────────────────
    private function seedAppUpdates(): void
    {
        $this->command->info('Seeding app updates...');

        $releaseId = DB::table('app_releases')->insertGetId([
            'version_number' => '1.0.0', 'platform' => 'android',
            'channel' => 'stable', 'download_url' => 'https://releases.thawani.om/android/1.0.0.apk',
            'release_notes' => 'Initial release', 'submission_status' => 'approved',
            'rollout_percentage' => 100, 'is_force_update' => false,
            'created_at' => now()->subMonth(), 'updated_at' => now()->subMonth(),
        ]);

        DB::table('app_update_stats')->insert([
            'store_id' => $this->storeId, 'app_release_id' => $releaseId,
            'status' => 'installed',
            'updated_at' => now()->subDays(20),
        ]);

        $this->command->info('  ✓ App updates: release, install stat');
    }

    // ─────────────────────────────────────────────────────────────
    // INDUSTRY: RESTAURANT (Tier 26)
    // ─────────────────────────────────────────────────────────────
    private function seedIndustryRestaurant(): void
    {
        $this->command->info('Seeding industry: restaurant...');

        $order1 = DB::table('orders')->where('order_number', 'ORD-2025-0001')->value('id');

        // Restaurant Tables
        $tables = [];
        for ($i = 1; $i <= 8; $i++) {
            $tables[$i] = DB::table('restaurant_tables')->insertGetId([
                'store_id' => $this->storeId, 'table_number' => (string) $i,
                'display_name' => "Table $i", 'seats' => ($i <= 4) ? 4 : 6,
                'zone' => ($i <= 4) ? 'indoor' : 'outdoor',
                'position_x' => ($i - 1) % 4 * 100, 'position_y' => (int)(($i - 1) / 4) * 100,
                'status' => ($i === 1) ? 'occupied' : 'available',
                'current_order_id' => ($i === 1) ? $order1 : null,
                'created_at' => now(),
            ]);
        }

        // Kitchen Ticket
        if ($order1) {
            DB::table('kitchen_tickets')->insert([
                'store_id' => $this->storeId, 'order_id' => $order1,
                'table_id' => $tables[1], 'ticket_number' => 1,
                'items_json' => json_encode([
                    ['name' => 'Arabic Bread', 'qty' => 2, 'notes' => 'Extra warm'],
                    ['name' => 'Croissant', 'qty' => 1, 'modifiers' => ['Chocolate filling']],
                ]),
                'station' => 'main', 'status' => 'in_progress',
                'course_number' => 1, 'created_at' => now()->subMinutes(15),
            ]);
        }

        // Table Reservation
        DB::table('table_reservations')->insert([
            'store_id' => $this->storeId, 'table_id' => $tables[3],
            'customer_name' => 'Abdullah', 'customer_phone' => '+966553333333',
            'party_size' => 4,
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '19:30', 'duration_minutes' => 90,
            'status' => 'confirmed', 'created_at' => now(),
        ]);

        // Open Tab
        if ($order1) {
            DB::table('open_tabs')->insert([
                'store_id' => $this->storeId, 'order_id' => $order1,
                'customer_name' => 'Fatima', 'table_id' => $tables[1],
                'opened_at' => now()->subMinutes(45), 'status' => 'open',
            ]);
        }

        $this->command->info('  ✓ Restaurant: 8 tables, kitchen ticket, reservation, open tab');
    }

    // ─────────────────────────────────────────────────────────────
    // INDUSTRY: PHARMACY (Tier 27)
    // ─────────────────────────────────────────────────────────────
    private function seedIndustryPharmacy(): void
    {
        $this->command->info('Seeding industry: pharmacy...');

        $firstProduct = DB::table('products')->where('organization_id', $this->orgId)->first();
        $order1 = DB::table('orders')->where('order_number', 'ORD-2025-0001')->value('id');

        if ($firstProduct) {
            DB::table('drug_schedules')->insertOrIgnore([
                'product_id' => $firstProduct->id, 'schedule_type' => 'otc',
                'active_ingredient' => 'Paracetamol', 'dosage_form' => 'tablet',
                'strength' => '500mg', 'requires_prescription' => false,
            ]);
        }

        if ($order1) {
            DB::table('prescriptions')->insert([
                'store_id' => $this->storeId, 'order_id' => $order1,
                'prescription_number' => 'RX-2025-0001',
                'patient_name' => 'Ahmed Patient', 'patient_id' => '1234567890',
                'doctor_name' => 'Dr. Khalid', 'doctor_license' => 'LIC-DOC-001',
                'insurance_provider' => 'Bupa Arabia',
                'created_at' => now(),
            ]);
        }

        $this->command->info('  ✓ Pharmacy: drug schedule, prescription');
    }

    // ─────────────────────────────────────────────────────────────
    // INDUSTRY: BAKERY (Tier 28)
    // ─────────────────────────────────────────────────────────────
    private function seedIndustryBakery(): void
    {
        $this->command->info('Seeding industry: bakery...');

        $croissant = DB::table('products')->where('sku', 'BAK-002')->first();
        $customer1 = DB::table('customers')->where('organization_id', $this->orgId)->first();
        $order1 = DB::table('orders')->where('order_number', 'ORD-2025-0001')->value('id');

        $recipeId = null;
        if ($croissant) {
            $recipeId = DB::table('bakery_recipes')->insertGetId([
                'store_id' => $this->storeId, 'product_id' => $croissant->id,
                'name' => 'Butter Croissant', 'expected_yield' => 24,
                'prep_time_minutes' => 120, 'bake_time_minutes' => 18,
                'bake_temperature_c' => 200,
                'instructions' => 'Mix dough, laminate with butter, proof 2h, bake at 200°C for 18min',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if ($recipeId) {
            DB::table('production_schedules')->insert([
                'store_id' => $this->storeId, 'recipe_id' => $recipeId,
                'schedule_date' => now()->addDay()->toDateString(),
                'planned_batches' => 3, 'planned_yield' => 72,
                'status' => 'scheduled', 'created_at' => now(),
            ]);
        }

        if ($customer1 && $order1) {
            DB::table('custom_cake_orders')->insert([
                'store_id' => $this->storeId, 'customer_id' => $customer1->id,
                'order_id' => $order1, 'description' => 'Birthday cake, chocolate with roses',
                'size' => '10 inch', 'flavor' => 'chocolate',
                'decoration_notes' => 'Happy Birthday! - 2 layers',
                'delivery_date' => now()->addDays(3)->toDateString(),
                'price' => 250.00, 'deposit_paid' => 125.00,
                'status' => 'in_progress', 'created_at' => now(),
            ]);
        }

        $this->command->info('  ✓ Bakery: recipe, production schedule, custom cake order');
    }

    // ─────────────────────────────────────────────────────────────
    // INDUSTRY: ELECTRONICS (Tier 29)
    // ─────────────────────────────────────────────────────────────
    private function seedIndustryElectronics(): void
    {
        $this->command->info('Seeding industry: electronics...');

        $firstProduct = DB::table('products')->where('organization_id', $this->orgId)->first();
        $customer2 = DB::table('customers')->where('organization_id', $this->orgId)->skip(1)->first();
        $staffCashier = DB::table('staff_users')->where('store_id', $this->storeId)->first();

        if ($firstProduct) {
            DB::table('device_imei_records')->insert([
                'product_id' => $firstProduct->id, 'store_id' => $this->storeId,
                'imei' => '35' . str_pad(rand(0, 9999999999999), 13, '0', STR_PAD_LEFT),
                'serial_number' => 'SN-' . Str::random(12),
                'condition_grade' => 'new', 'purchase_price' => 2500.00,
                'warranty_end_date' => now()->addYear()->toDateString(),
                'status' => 'in_stock', 'created_at' => now(),
            ]);
        }

        if ($customer2 && $staffCashier) {
            DB::table('repair_jobs')->insert([
                'store_id' => $this->storeId, 'customer_id' => $customer2->id,
                'device_description' => 'iPhone 15 Pro Max', 'imei' => '35' . str_pad(rand(0, 9999999999999), 13, '0', STR_PAD_LEFT),
                'issue_description' => 'Cracked screen', 'status' => 'in_progress',
                'diagnosis_notes' => 'Screen replacement needed. OEM part ordered.',
                'estimated_cost' => 800.00,
                'staff_user_id' => $staffCashier->id,
                'received_at' => now()->subDays(2),
            ]);

            DB::table('trade_in_records')->insert([
                'store_id' => $this->storeId, 'customer_id' => $customer2->id,
                'device_description' => 'Samsung Galaxy S23', 'imei' => '35' . str_pad(rand(0, 9999999999999), 13, '0', STR_PAD_LEFT),
                'condition_grade' => 'good', 'assessed_value' => 1500.00,
                'staff_user_id' => $staffCashier->id,
                'created_at' => now(),
            ]);
        }

        $this->command->info('  ✓ Electronics: IMEI record, repair job, trade-in');
    }

    // ─────────────────────────────────────────────────────────────
    // INDUSTRY: FLORIST (Tier 30)
    // ─────────────────────────────────────────────────────────────
    private function seedIndustryFlorist(): void
    {
        $this->command->info('Seeding industry: florist...');

        $firstProduct = DB::table('products')->where('organization_id', $this->orgId)->first();
        $customer3 = DB::table('customers')->where('organization_id', $this->orgId)->skip(2)->first();

        $arrangementId = DB::table('flower_arrangements')->insertGetId([
            'store_id' => $this->storeId, 'name' => 'Rose Bouquet',
            'occasion' => 'anniversary',
            'items_json' => json_encode([
                ['flower' => 'Red Rose', 'qty' => 24],
                ['flower' => 'Baby Breath', 'qty' => 10],
                ['flower' => 'Eucalyptus', 'qty' => 5],
            ]),
            'total_price' => 180.00, 'is_template' => true,
            'created_at' => now(),
        ]);

        if ($firstProduct) {
            DB::table('flower_freshness_log')->insert([
                'product_id' => $firstProduct->id, 'store_id' => $this->storeId,
                'received_date' => now()->subDays(3)->toDateString(),
                'expected_vase_life_days' => 7,
                'markdown_date' => now()->addDay()->toDateString(),
                'dispose_date' => now()->addDays(4)->toDateString(),
                'quantity' => 50, 'status' => 'fresh',
            ]);
        }

        if ($customer3) {
            DB::table('flower_subscriptions')->insert([
                'store_id' => $this->storeId, 'customer_id' => $customer3->id,
                'arrangement_template_id' => $arrangementId,
                'frequency' => 'weekly', 'delivery_day' => 'sunday',
                'delivery_address' => 'Olaya, Riyadh', 'price_per_delivery' => 150.00,
                'next_delivery_date' => now()->next('sunday')->toDateString(),
                'is_active' => true, 'created_at' => now(),
            ]);
        }

        $this->command->info('  ✓ Florist: arrangement, freshness log, subscription');
    }

    // ─────────────────────────────────────────────────────────────
    // INDUSTRY: JEWELRY (Tier 31)
    // ─────────────────────────────────────────────────────────────
    private function seedIndustryJewelry(): void
    {
        $this->command->info('Seeding industry: jewelry...');

        $firstProduct = DB::table('products')->where('organization_id', $this->orgId)->first();
        $customer1 = DB::table('customers')->where('organization_id', $this->orgId)->first();
        $staffCashier = DB::table('staff_users')->where('store_id', $this->storeId)->first();

        // Daily Metal Rates
        DB::table('daily_metal_rates')->insert([
            ['store_id' => $this->storeId, 'metal_type' => 'gold', 'karat' => '24', 'rate_per_gram' => 280.00, 'buyback_rate_per_gram' => 270.00, 'effective_date' => now()->toDateString(), 'created_at' => now()],
            ['store_id' => $this->storeId, 'metal_type' => 'gold', 'karat' => '21', 'rate_per_gram' => 245.00, 'buyback_rate_per_gram' => 235.00, 'effective_date' => now()->toDateString(), 'created_at' => now()],
            ['store_id' => $this->storeId, 'metal_type' => 'gold', 'karat' => '18', 'rate_per_gram' => 210.00, 'buyback_rate_per_gram' => 200.00, 'effective_date' => now()->toDateString(), 'created_at' => now()],
            ['store_id' => $this->storeId, 'metal_type' => 'silver', 'karat' => null, 'rate_per_gram' => 3.50, 'buyback_rate_per_gram' => 3.00, 'effective_date' => now()->toDateString(), 'created_at' => now()],
        ]);

        if ($firstProduct) {
            DB::table('jewelry_product_details')->insertOrIgnore([
                'product_id' => $firstProduct->id, 'metal_type' => 'gold',
                'karat' => '21', 'gross_weight_g' => 15.50, 'net_weight_g' => 14.80,
                'stone_weight_carat' => 0.70, 'making_charges_type' => 'per_gram',
                'making_charges_value' => 25.00, 'stone_type' => 'diamond',
                'stone_count' => 3,
            ]);
        }

        if ($customer1 && $staffCashier) {
            DB::table('buyback_transactions')->insert([
                'store_id' => $this->storeId, 'customer_id' => $customer1->id,
                'metal_type' => 'gold', 'karat' => '21', 'weight_g' => 10.00,
                'rate_per_gram' => 235.00, 'total_amount' => 2350.00,
                'payment_method' => 'cash',
                'staff_user_id' => $staffCashier->id,
                'created_at' => now(),
            ]);
        }

        $this->command->info('  ✓ Jewelry: metal rates, product details, buyback');
    }
}
