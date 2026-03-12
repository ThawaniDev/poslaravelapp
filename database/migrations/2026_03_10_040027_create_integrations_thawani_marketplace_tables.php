<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INTEGRATIONS: Thawani Marketplace
 *
 * Tables: thawani_store_config, thawani_product_mappings, thawani_order_mappings, thawani_settlements
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
CREATE TABLE thawani_store_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    thawani_store_id VARCHAR(100) NOT NULL,
    is_connected BOOLEAN DEFAULT FALSE,
    auto_sync_products BOOLEAN DEFAULT TRUE,
    auto_sync_inventory BOOLEAN DEFAULT TRUE,
    auto_accept_orders BOOLEAN DEFAULT FALSE,
    operating_hours_json JSONB,
    commission_rate DECIMAL(5,2),
    connected_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE thawani_product_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    thawani_product_id VARCHAR(100) NOT NULL,
    is_published BOOLEAN DEFAULT TRUE,
    online_price DECIMAL(12,3),
    display_order INTEGER DEFAULT 0,
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, product_id)
);

CREATE TABLE thawani_order_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID REFERENCES orders(id),
    thawani_order_id VARCHAR(100) NOT NULL,
    thawani_order_number VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    delivery_type VARCHAR(20) NOT NULL DEFAULT 'delivery',
    customer_name VARCHAR(200),
    customer_phone VARCHAR(20),
    delivery_address TEXT,
    order_total DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3),
    rejection_reason TEXT,
    accepted_at TIMESTAMP,
    prepared_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE thawani_settlements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    settlement_date DATE NOT NULL,
    gross_amount DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3) NOT NULL,
    net_amount DECIMAL(12,3) NOT NULL,
    order_count INTEGER NOT NULL,
    thawani_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, settlement_date, thawani_reference)
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('thawani_settlements');
        Schema::dropIfExists('thawani_order_mappings');
        Schema::dropIfExists('thawani_product_mappings');
        Schema::dropIfExists('thawani_store_config');
    }
};
