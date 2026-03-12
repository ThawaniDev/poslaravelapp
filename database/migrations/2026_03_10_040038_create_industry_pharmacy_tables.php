<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INDUSTRY: Pharmacy
 *
 * Tables: prescriptions, drug_schedules
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
CREATE TABLE prescriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID REFERENCES orders(id),
    prescription_number VARCHAR(50) NOT NULL,
    patient_name VARCHAR(200) NOT NULL,
    patient_id VARCHAR(50),
    doctor_name VARCHAR(200),
    doctor_license VARCHAR(50),
    insurance_provider VARCHAR(100),
    insurance_claim_amount DECIMAL(12,3),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE drug_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL UNIQUE REFERENCES products(id),
    schedule_type VARCHAR(20) NOT NULL DEFAULT 'otc',
    active_ingredient VARCHAR(200),
    dosage_form VARCHAR(50),
    strength VARCHAR(50),
    manufacturer VARCHAR(200),
    requires_prescription BOOLEAN DEFAULT FALSE
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_schedules');
        Schema::dropIfExists('prescriptions');
    }
};
