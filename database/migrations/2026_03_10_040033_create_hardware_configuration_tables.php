<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HARDWARE: Configuration
 *
 * Tables: hardware_configurations, hardware_event_log, hardware_sales, implementation_fees
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
CREATE TABLE hardware_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    device_type VARCHAR(30) NOT NULL,
    connection_type VARCHAR(20) NOT NULL,
    device_name VARCHAR(100),
    config_json JSONB NOT NULL DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, terminal_id, device_type)
);

CREATE TABLE hardware_event_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    device_type VARCHAR(30) NOT NULL,
    event VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE hardware_sales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    sold_by UUID NOT NULL REFERENCES admin_users(id),
    item_type VARCHAR(50) NOT NULL,
    item_description VARCHAR(255),
    serial_number VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    notes TEXT,
    sold_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE implementation_fees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    fee_type VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'invoiced',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_fees');
        Schema::dropIfExists('hardware_sales');
        Schema::dropIfExists('hardware_event_log');
        Schema::dropIfExists('hardware_configurations');
    }
};
