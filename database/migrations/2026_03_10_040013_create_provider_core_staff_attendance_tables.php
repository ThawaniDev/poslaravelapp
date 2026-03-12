<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROVIDER CORE: Staff & Attendance
 *
 * Tables: staff_users, staff_branch_assignments, attendance_records, break_records, shift_templates, shift_schedules, commission_rules, commission_earnings, staff_activity_log, training_sessions, staff_documents
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
CREATE TABLE staff_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    photo_url VARCHAR(500),
    national_id VARCHAR(50),
    pin_hash VARCHAR(255) NOT NULL,
    nfc_badge_uid VARCHAR(50) UNIQUE,
    biometric_enabled BOOLEAN DEFAULT FALSE,
    employment_type VARCHAR(20) NOT NULL DEFAULT 'full_time',
    salary_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
    hourly_rate DECIMAL(10,2),
    hire_date DATE NOT NULL DEFAULT CURRENT_DATE,
    termination_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    language_preference VARCHAR(5) DEFAULT 'ar',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE staff_branch_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    branch_id UUID NOT NULL REFERENCES stores(id),
    role_id BIGINT NOT NULL REFERENCES roles(id),
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(staff_user_id, branch_id)
);

CREATE TABLE attendance_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    clock_in_at TIMESTAMP NOT NULL,
    clock_out_at TIMESTAMP,
    break_minutes INTEGER DEFAULT 0,
    scheduled_shift_id UUID,
    overtime_minutes INTEGER DEFAULT 0,
    notes TEXT,
    auth_method VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE break_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    attendance_record_id UUID NOT NULL REFERENCES attendance_records(id) ON DELETE CASCADE,
    break_start TIMESTAMP NOT NULL,
    break_end TIMESTAMP
);

CREATE TABLE shift_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    color VARCHAR(7) DEFAULT '#4CAF50',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE shift_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    shift_template_id UUID NOT NULL REFERENCES shift_templates(id),
    date DATE NOT NULL,
    actual_start TIMESTAMP,
    actual_end TIMESTAMP,
    status VARCHAR(20) DEFAULT 'scheduled',
    swapped_with_id UUID REFERENCES staff_users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(staff_user_id, date, shift_template_id)
);

CREATE TABLE commission_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    staff_user_id UUID REFERENCES staff_users(id),
    type VARCHAR(20) NOT NULL DEFAULT 'flat_percentage',
    percentage DECIMAL(5,2),
    tiers_json JSONB,
    product_category_id UUID,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE commission_earnings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    order_id UUID NOT NULL,
    commission_rule_id UUID NOT NULL REFERENCES commission_rules(id),
    order_total DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE staff_activity_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    details JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE training_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    ended_at TIMESTAMP,
    transactions_count INTEGER DEFAULT 0,
    notes TEXT
);

CREATE TABLE staff_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    document_type VARCHAR(50) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    expiry_date DATE,
    uploaded_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('staff_activity_log');
        Schema::dropIfExists('commission_earnings');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('shift_schedules');
        Schema::dropIfExists('shift_templates');
        Schema::dropIfExists('break_records');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('staff_branch_assignments');
        Schema::dropIfExists('staff_users');
    }
};
