<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only create materialized views for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('
                CREATE MATERIALIZED VIEW IF NOT EXISTS mv_daily_sales_summary AS
                SELECT
                    store_id,
                    DATE(created_at) AS sale_date,
                    COUNT(*) AS order_count,
                    SUM(total) AS total_revenue,
                    SUM(tax_amount) AS total_vat,
                    ROUND(AVG(total), 2) AS avg_order_value,
                    SUM(discount_amount) AS total_discount,
                    COUNT(DISTINCT customer_id) AS unique_customers
                FROM orders
                WHERE status NOT IN (\'cancelled\', \'voided\')
                GROUP BY store_id, DATE(created_at)
            ');

            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS mv_daily_sales_store_date
                ON mv_daily_sales_summary (store_id, sale_date)
            ');

            DB::statement('
                CREATE MATERIALIZED VIEW IF NOT EXISTS mv_product_performance AS
                SELECT
                    p.store_id,
                    p.id AS product_id,
                    p.name_ar,
                    p.name AS name_en,
                    p.sku,
                    SUM(oi.quantity) AS total_qty_sold,
                    SUM(oi.line_total) AS total_revenue,
                    COUNT(DISTINCT oi.order_id) AS order_count
                FROM products p
                JOIN order_items oi ON oi.product_id = p.id
                JOIN orders o ON o.id = oi.order_id
                WHERE o.status NOT IN (\'cancelled\', \'voided\')
                GROUP BY p.store_id, p.id, p.name_ar, p.name, p.sku
            ');

            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS mv_product_perf_store_product
                ON mv_product_performance (store_id, product_id)
            ');
        }

        // Performance indexes for dashboard queries (safe for both SQLite and PostgreSQL)
        if (Schema::hasTable('orders') && ! $this->indexExists('orders', 'idx_orders_store_status_date')) {
            Schema::table('orders', function ($table) {
                $table->index(['store_id', 'status', 'created_at'], 'idx_orders_store_status_date');
            });
        }

        if (Schema::hasTable('order_items') && ! $this->indexExists('order_items', 'idx_order_items_product_id')) {
            Schema::table('order_items', function ($table) {
                $table->index('product_id', 'idx_order_items_product_id');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_product_performance');
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_daily_sales_summary');
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function ($table) {
                $table->dropIndex('idx_orders_store_status_date');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function ($table) {
                $table->dropIndex('idx_order_items_product_id');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return DB::selectOne("SELECT 1 FROM pg_indexes WHERE indexname = ?", [$indexName]) !== null;
        }

        // SQLite
        $indexes = DB::select("PRAGMA index_list('$table')");
        foreach ($indexes as $index) {
            if ($index->name === $indexName) {
                return true;
            }
        }

        return false;
    }
};
