<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDUSTRY: Bakery
 *
 * Tables: bakery_recipes, production_schedules, custom_cake_orders
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE bakery_recipes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    name VARCHAR(200) NOT NULL,
    expected_yield INTEGER NOT NULL DEFAULT 1,
    prep_time_minutes INTEGER,
    bake_time_minutes INTEGER,
    bake_temperature_c INTEGER,
    instructions TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE production_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    recipe_id UUID NOT NULL REFERENCES bakery_recipes(id),
    schedule_date DATE NOT NULL,
    planned_batches INTEGER NOT NULL DEFAULT 1,
    actual_batches INTEGER,
    planned_yield INTEGER NOT NULL,
    actual_yield INTEGER,
    status VARCHAR(20) DEFAULT 'planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE custom_cake_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    order_id UUID REFERENCES orders(id),
    description TEXT NOT NULL,
    size VARCHAR(50),
    flavor VARCHAR(100),
    decoration_notes TEXT,
    delivery_date DATE NOT NULL,
    delivery_time TIME,
    price DECIMAL(12,3) NOT NULL,
    deposit_paid DECIMAL(12,3) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'ordered',
    reference_image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_cake_orders');
        Schema::dropIfExists('production_schedules');
        Schema::dropIfExists('bakery_recipes');
    }
};
