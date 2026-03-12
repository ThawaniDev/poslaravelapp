<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDUSTRY: Restaurant
 *
 * Tables: restaurant_tables, kitchen_tickets, table_reservations, open_tabs
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
CREATE TABLE restaurant_tables (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_number VARCHAR(20) NOT NULL,
    display_name VARCHAR(50),
    seats INTEGER NOT NULL DEFAULT 4,
    zone VARCHAR(50),
    position_x INTEGER DEFAULT 0,
    position_y INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'available',
    current_order_id UUID REFERENCES orders(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, table_number)
);

CREATE TABLE kitchen_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    table_id UUID REFERENCES restaurant_tables(id),
    ticket_number INTEGER NOT NULL,
    items_json JSONB NOT NULL,
    station VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    course_number INTEGER DEFAULT 1,
    fire_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE TABLE table_reservations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_id UUID REFERENCES restaurant_tables(id),
    customer_name VARCHAR(200) NOT NULL,
    customer_phone VARCHAR(20),
    party_size INTEGER NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    duration_minutes INTEGER DEFAULT 90,
    status VARCHAR(20) DEFAULT 'confirmed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE open_tabs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    customer_name VARCHAR(200) NOT NULL,
    table_id UUID REFERENCES restaurant_tables(id),
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'open'
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('open_tabs');
        Schema::dropIfExists('table_reservations');
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('restaurant_tables');
    }
};
