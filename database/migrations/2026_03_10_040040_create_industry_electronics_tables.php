<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDUSTRY: Electronics
 *
 * Tables: device_imei_records, repair_jobs, trade_in_records
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE device_imei_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    imei VARCHAR(15) NOT NULL,
    imei2 VARCHAR(15),
    serial_number VARCHAR(50),
    condition_grade VARCHAR(5),
    purchase_price DECIMAL(12,3),
    status VARCHAR(20) DEFAULT 'in_stock',
    warranty_end_date DATE,
    store_warranty_end_date DATE,
    sold_order_id UUID REFERENCES orders(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE repair_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    device_description VARCHAR(200) NOT NULL,
    imei VARCHAR(15),
    issue_description TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'received',
    diagnosis_notes TEXT,
    repair_notes TEXT,
    estimated_cost DECIMAL(12,3),
    final_cost DECIMAL(12,3),
    parts_used JSONB,
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    received_at TIMESTAMP DEFAULT NOW(),
    estimated_ready_at TIMESTAMP,
    completed_at TIMESTAMP,
    collected_at TIMESTAMP
);

CREATE TABLE trade_in_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    device_description VARCHAR(200) NOT NULL,
    imei VARCHAR(15),
    condition_grade VARCHAR(5) NOT NULL,
    assessed_value DECIMAL(12,3) NOT NULL,
    applied_to_order_id UUID REFERENCES orders(id),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_in_records');
        Schema::dropIfExists('repair_jobs');
        Schema::dropIfExists('device_imei_records');
    }
};
