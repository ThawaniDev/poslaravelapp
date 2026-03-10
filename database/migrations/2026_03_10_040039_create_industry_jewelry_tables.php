<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDUSTRY: Jewelry
 *
 * Tables: daily_metal_rates, jewelry_product_details, buyback_transactions
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE daily_metal_rates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10),
    rate_per_gram DECIMAL(12,3) NOT NULL,
    buyback_rate_per_gram DECIMAL(12,3),
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, metal_type, karat, effective_date)
);

CREATE TABLE jewelry_product_details (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL UNIQUE REFERENCES products(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10),
    gross_weight_g DECIMAL(10,3) NOT NULL,
    net_weight_g DECIMAL(10,3) NOT NULL,
    making_charges_type VARCHAR(20) DEFAULT 'percentage',
    making_charges_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    stone_type VARCHAR(50),
    stone_weight_carat DECIMAL(10,3),
    stone_count INTEGER,
    certificate_number VARCHAR(100),
    certificate_url VARCHAR(500)
);

CREATE TABLE buyback_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10) NOT NULL,
    weight_g DECIMAL(10,3) NOT NULL,
    rate_per_gram DECIMAL(12,3) NOT NULL,
    total_amount DECIMAL(12,3) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('buyback_transactions');
        Schema::dropIfExists('jewelry_product_details');
        Schema::dropIfExists('daily_metal_rates');
    }
};
