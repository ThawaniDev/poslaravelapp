<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDUSTRY: Florist
 *
 * Tables: flower_arrangements, flower_freshness_log, flower_subscriptions
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE flower_arrangements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(200) NOT NULL,
    occasion VARCHAR(50),
    items_json JSONB NOT NULL,
    total_price DECIMAL(12,3) NOT NULL,
    is_template BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE flower_freshness_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    received_date DATE NOT NULL,
    expected_vase_life_days INTEGER NOT NULL,
    markdown_date DATE,
    dispose_date DATE,
    quantity INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'fresh'
);

CREATE TABLE flower_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    arrangement_template_id UUID REFERENCES flower_arrangements(id),
    frequency VARCHAR(20) NOT NULL,
    delivery_day VARCHAR(10),
    delivery_address TEXT NOT NULL,
    price_per_delivery DECIMAL(12,3) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    next_delivery_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_subscriptions');
        Schema::dropIfExists('flower_freshness_log');
        Schema::dropIfExists('flower_arrangements');
    }
};
