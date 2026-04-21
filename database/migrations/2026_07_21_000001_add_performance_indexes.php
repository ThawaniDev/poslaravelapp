<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds performance indexes for hot-path queries.
 *
 * Defensive: each index is only created when its table AND every referenced
 * column exists, and when an index of the same name is not already present.
 */
return new class extends Migration
{
    /** @var array<int, array{table:string, columns:array<int,string>, name:string}> */
    private array $indexes = [
        ['table' => 'transactions', 'columns' => ['store_id'],                'name' => 'idx_transactions_store'],
        ['table' => 'transactions', 'columns' => ['customer_id'],             'name' => 'idx_transactions_customer'],
        ['table' => 'transactions', 'columns' => ['cashier_id'],              'name' => 'idx_transactions_cashier'],
        ['table' => 'transactions', 'columns' => ['status'],                  'name' => 'idx_transactions_status'],
        ['table' => 'transactions', 'columns' => ['created_at'],              'name' => 'idx_transactions_created_at'],
        ['table' => 'transactions', 'columns' => ['store_id', 'status'],      'name' => 'idx_transactions_store_status'],
        ['table' => 'transactions', 'columns' => ['store_id', 'created_at'],  'name' => 'idx_transactions_store_created'],

        ['table' => 'products', 'columns' => ['store_id'],                    'name' => 'idx_products_store'],
        ['table' => 'products', 'columns' => ['organization_id'],             'name' => 'idx_products_organization'],
        ['table' => 'products', 'columns' => ['barcode'],                     'name' => 'idx_products_barcode'],
        ['table' => 'products', 'columns' => ['sku'],                         'name' => 'idx_products_sku'],
        ['table' => 'products', 'columns' => ['category_id'],                 'name' => 'idx_products_category'],
        ['table' => 'products', 'columns' => ['store_id', 'is_active'],       'name' => 'idx_products_store_active'],
        ['table' => 'products', 'columns' => ['organization_id', 'is_active'],'name' => 'idx_products_org_active'],

        ['table' => 'pos_sessions', 'columns' => ['store_id'],                'name' => 'idx_pos_sessions_store'],
        ['table' => 'pos_sessions', 'columns' => ['cashier_id'],              'name' => 'idx_pos_sessions_cashier'],
        ['table' => 'pos_sessions', 'columns' => ['status'],                  'name' => 'idx_pos_sessions_status'],
        ['table' => 'pos_sessions', 'columns' => ['store_id', 'status'],      'name' => 'idx_pos_sessions_store_status'],

        ['table' => 'stock_levels', 'columns' => ['store_id'],                'name' => 'idx_stock_levels_store'],
        ['table' => 'stock_levels', 'columns' => ['branch_id'],               'name' => 'idx_stock_levels_branch'],
        ['table' => 'stock_levels', 'columns' => ['product_id'],              'name' => 'idx_stock_levels_product'],
        ['table' => 'stock_levels', 'columns' => ['store_id', 'product_id'],  'name' => 'idx_stock_levels_store_product'],

        ['table' => 'transaction_items', 'columns' => ['transaction_id'],     'name' => 'idx_transaction_items_transaction'],
        ['table' => 'transaction_items', 'columns' => ['product_id'],         'name' => 'idx_transaction_items_product'],

        ['table' => 'purchase_orders', 'columns' => ['store_id'],             'name' => 'idx_purchase_orders_store'],
        ['table' => 'purchase_orders', 'columns' => ['status'],               'name' => 'idx_purchase_orders_status'],
        ['table' => 'purchase_orders', 'columns' => ['supplier_id'],          'name' => 'idx_purchase_orders_supplier'],

        ['table' => 'stock_transfers', 'columns' => ['organization_id'],      'name' => 'idx_stock_transfers_organization'],
        ['table' => 'stock_transfers', 'columns' => ['status'],               'name' => 'idx_stock_transfers_status'],

        ['table' => 'goods_receipts', 'columns' => ['store_id'],              'name' => 'idx_goods_receipts_store'],
        ['table' => 'goods_receipts', 'columns' => ['status'],                'name' => 'idx_goods_receipts_status'],

        ['table' => 'customers', 'columns' => ['store_id'],                   'name' => 'idx_customers_store'],
        ['table' => 'customers', 'columns' => ['organization_id'],            'name' => 'idx_customers_organization'],
        ['table' => 'customers', 'columns' => ['phone'],                      'name' => 'idx_customers_phone'],
        ['table' => 'customers', 'columns' => ['loyalty_code'],               'name' => 'idx_customers_loyalty_code'],

        ['table' => 'recipes', 'columns' => ['organization_id'],              'name' => 'idx_recipes_organization'],
        ['table' => 'recipes', 'columns' => ['product_id'],                   'name' => 'idx_recipes_product'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $idx) {
            if (!Schema::hasTable($idx['table'])) {
                continue;
            }
            if (!Schema::hasColumns($idx['table'], $idx['columns'])) {
                continue;
            }
            if ($this->indexExists($idx['table'], $idx['name'])) {
                continue;
            }
            Schema::table($idx['table'], function (Blueprint $table) use ($idx) {
                $table->index($idx['columns'], $idx['name']);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as $idx) {
            if (!Schema::hasTable($idx['table'])) {
                continue;
            }
            if (!$this->indexExists($idx['table'], $idx['name'])) {
                continue;
            }
            Schema::table($idx['table'], function (Blueprint $table) use ($idx) {
                $table->dropIndex($idx['name']);
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = DB::connection()->getDriverName();
        try {
            return match ($driver) {
                'pgsql'  => DB::selectOne(
                    'select 1 from pg_indexes where schemaname = current_schema() and tablename = ? and indexname = ?',
                    [$table, $name]
                ) !== null,
                'mysql', 'mariadb' => DB::selectOne(
                    'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
                    [$table, $name]
                ) !== null,
                'sqlite' => DB::selectOne(
                    "select 1 from sqlite_master where type = 'index' and name = ? and tbl_name = ?",
                    [$name, $table]
                ) !== null,
                default  => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }
};
