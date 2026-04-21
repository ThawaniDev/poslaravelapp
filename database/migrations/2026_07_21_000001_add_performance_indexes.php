<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Transactions ─────────────────────────────────────
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('store_id', 'idx_transactions_store');
            $table->index('customer_id', 'idx_transactions_customer');
            $table->index('cashier_id', 'idx_transactions_cashier');
            $table->index('status', 'idx_transactions_status');
            $table->index('created_at', 'idx_transactions_created_at');
            $table->index(['store_id', 'status'], 'idx_transactions_store_status');
            $table->index(['store_id', 'created_at'], 'idx_transactions_store_created');
        });

        // ── Products ─────────────────────────────────────────
        Schema::table('products', function (Blueprint $table) {
            $table->index('store_id', 'idx_products_store');
            $table->index('organization_id', 'idx_products_organization');
            $table->index('barcode', 'idx_products_barcode');
            $table->index('sku', 'idx_products_sku');
            $table->index('category_id', 'idx_products_category');
            $table->index(['store_id', 'is_active'], 'idx_products_store_active');
        });

        // ── POS Sessions ────────────────────────────────────
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->index('store_id', 'idx_pos_sessions_store');
            $table->index('cashier_id', 'idx_pos_sessions_cashier');
            $table->index('status', 'idx_pos_sessions_status');
            $table->index(['store_id', 'status'], 'idx_pos_sessions_store_status');
        });

        // ── Stock Levels ─────────────────────────────────────
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->index('store_id', 'idx_stock_levels_store');
            $table->index('product_id', 'idx_stock_levels_product');
            $table->index(['store_id', 'product_id'], 'idx_stock_levels_store_product');
        });

        // ── Transaction Items ────────────────────────────────
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->index('transaction_id', 'idx_transaction_items_transaction');
            $table->index('product_id', 'idx_transaction_items_product');
        });

        // ── Purchase Orders ──────────────────────────────────
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('store_id', 'idx_purchase_orders_store');
            $table->index('status', 'idx_purchase_orders_status');
            $table->index('supplier_id', 'idx_purchase_orders_supplier');
        });

        // ── Stock Transfers ──────────────────────────────────
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->index('organization_id', 'idx_stock_transfers_organization');
            $table->index('status', 'idx_stock_transfers_status');
        });

        // ── Goods Receipts ───────────────────────────────────
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->index('store_id', 'idx_goods_receipts_store');
            $table->index('status', 'idx_goods_receipts_status');
        });

        // ── Customers ────────────────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            $table->index('store_id', 'idx_customers_store');
            $table->index('phone', 'idx_customers_phone');
            $table->index('loyalty_code', 'idx_customers_loyalty_code');
        });

        // ── Recipes ──────────────────────────────────────────
        Schema::table('recipes', function (Blueprint $table) {
            $table->index('organization_id', 'idx_recipes_organization');
            $table->index('product_id', 'idx_recipes_product');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_store');
            $table->dropIndex('idx_transactions_customer');
            $table->dropIndex('idx_transactions_cashier');
            $table->dropIndex('idx_transactions_status');
            $table->dropIndex('idx_transactions_created_at');
            $table->dropIndex('idx_transactions_store_status');
            $table->dropIndex('idx_transactions_store_created');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_store');
            $table->dropIndex('idx_products_organization');
            $table->dropIndex('idx_products_barcode');
            $table->dropIndex('idx_products_sku');
            $table->dropIndex('idx_products_category');
            $table->dropIndex('idx_products_store_active');
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_pos_sessions_store');
            $table->dropIndex('idx_pos_sessions_cashier');
            $table->dropIndex('idx_pos_sessions_status');
            $table->dropIndex('idx_pos_sessions_store_status');
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropIndex('idx_stock_levels_store');
            $table->dropIndex('idx_stock_levels_product');
            $table->dropIndex('idx_stock_levels_store_product');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropIndex('idx_transaction_items_transaction');
            $table->dropIndex('idx_transaction_items_product');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_purchase_orders_store');
            $table->dropIndex('idx_purchase_orders_status');
            $table->dropIndex('idx_purchase_orders_supplier');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex('idx_stock_transfers_organization');
            $table->dropIndex('idx_stock_transfers_status');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropIndex('idx_goods_receipts_store');
            $table->dropIndex('idx_goods_receipts_status');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_store');
            $table->dropIndex('idx_customers_phone');
            $table->dropIndex('idx_customers_loyalty_code');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex('idx_recipes_organization');
            $table->dropIndex('idx_recipes_product');
        });
    }
};
