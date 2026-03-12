<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REPORTS: Provider Analytics
 *
 * Tables: product_sales_summary, daily_sales_summary
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (\Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE product_sales_summary (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    date DATE NOT NULL,
    quantity_sold DECIMAL(12,3) DEFAULT 0,
    revenue DECIMAL(14,2) DEFAULT 0,
    cost DECIMAL(14,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    return_quantity DECIMAL(12,3) DEFAULT 0,
    return_amount DECIMAL(12,2) DEFAULT 0,
    UNIQUE (store_id, product_id, date)
);

CREATE TABLE daily_sales_summary (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    date DATE NOT NULL,
    total_transactions INT DEFAULT 0,
    total_revenue DECIMAL(14,2) DEFAULT 0,
    total_cost DECIMAL(14,2) DEFAULT 0,
    total_discount DECIMAL(12,2) DEFAULT 0,
    total_tax DECIMAL(12,2) DEFAULT 0,
    total_refunds DECIMAL(12,2) DEFAULT 0,
    net_revenue DECIMAL(14,2) DEFAULT 0,
    cash_revenue DECIMAL(14,2) DEFAULT 0,
    card_revenue DECIMAL(14,2) DEFAULT 0,
    other_revenue DECIMAL(14,2) DEFAULT 0,
    avg_basket_size DECIMAL(12,2) DEFAULT 0,
    unique_customers INT DEFAULT 0,
    UNIQUE (store_id, date)
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summary');
        Schema::dropIfExists('product_sales_summary');
    }
};
