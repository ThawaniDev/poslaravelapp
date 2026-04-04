<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance store_settings with comprehensive POS configuration columns.
 *
 * Groups:
 *  - Receipt enhancements (paper size, font, language, extra toggles)
 *  - POS behaviour (hold orders, refunds, exchanges, barcode, quick-add)
 *  - Discount & loyalty
 *  - Inventory tracking
 *  - Display & appearance
 *  - Customer display
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            // ─── Receipt enhancements ────────────────────────────
            $table->boolean('receipt_show_address')->default(true)->after('receipt_show_tax_breakdown');
            $table->boolean('receipt_show_phone')->default(true)->after('receipt_show_address');
            $table->boolean('receipt_show_date')->default(true)->after('receipt_show_phone');
            $table->boolean('receipt_show_cashier')->default(true)->after('receipt_show_date');
            $table->boolean('receipt_show_barcode')->default(true)->after('receipt_show_cashier');
            $table->string('receipt_paper_size', 10)->default('80mm')->after('receipt_show_barcode');
            $table->string('receipt_font_size', 10)->default('normal')->after('receipt_paper_size');
            $table->string('receipt_language', 10)->default('ar')->after('receipt_font_size');

            // ─── POS behaviour enhancements ──────────────────────
            $table->boolean('barcode_scan_sound')->default(true)->after('enable_kitchen_display');
            $table->string('default_sale_type', 20)->default('dine_in')->after('barcode_scan_sound');
            $table->boolean('enable_hold_orders')->default(true)->after('default_sale_type');
            $table->boolean('enable_refunds')->default(true)->after('enable_hold_orders');
            $table->boolean('enable_exchanges')->default(true)->after('enable_refunds');
            $table->boolean('require_manager_for_refund')->default(false)->after('enable_exchanges');
            $table->boolean('require_manager_for_discount')->default(false)->after('require_manager_for_refund');
            $table->boolean('enable_open_price_items')->default(false)->after('require_manager_for_discount');
            $table->boolean('enable_quick_add_products')->default(true)->after('enable_open_price_items');

            // ─── Loyalty ─────────────────────────────────────────
            $table->boolean('enable_loyalty_points')->default(false)->after('enable_quick_add_products');
            $table->decimal('loyalty_points_per_currency', 8, 2)->default(1.00)->after('enable_loyalty_points');
            $table->decimal('loyalty_redemption_value', 8, 2)->default(0.01)->after('loyalty_points_per_currency');

            // ─── Inventory tracking ──────────────────────────────
            $table->boolean('track_inventory')->default(true)->after('low_stock_threshold');
            $table->boolean('enable_batch_tracking')->default(false)->after('track_inventory');
            $table->boolean('enable_expiry_tracking')->default(false)->after('enable_batch_tracking');
            $table->boolean('auto_deduct_ingredients')->default(false)->after('enable_expiry_tracking');

            // ─── Display & appearance ────────────────────────────
            $table->string('theme_mode', 10)->default('system')->after('auto_deduct_ingredients');
            $table->string('display_language', 10)->default('ar')->after('theme_mode');

            // ─── Customer-facing display ─────────────────────────
            $table->boolean('enable_customer_display')->default(false)->after('display_language');
            $table->string('customer_display_message', 255)->nullable()->after('enable_customer_display');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                // Receipt
                'receipt_show_address',
                'receipt_show_phone',
                'receipt_show_date',
                'receipt_show_cashier',
                'receipt_show_barcode',
                'receipt_paper_size',
                'receipt_font_size',
                'receipt_language',
                // POS
                'barcode_scan_sound',
                'default_sale_type',
                'enable_hold_orders',
                'enable_refunds',
                'enable_exchanges',
                'require_manager_for_refund',
                'require_manager_for_discount',
                'enable_open_price_items',
                'enable_quick_add_products',
                // Loyalty
                'enable_loyalty_points',
                'loyalty_points_per_currency',
                'loyalty_redemption_value',
                // Inventory
                'track_inventory',
                'enable_batch_tracking',
                'enable_expiry_tracking',
                'auto_deduct_ingredients',
                // Display
                'theme_mode',
                'display_language',
                // Customer display
                'enable_customer_display',
                'customer_display_message',
            ]);
        });
    }
};
