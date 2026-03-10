<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROVIDER CORE: Security
 *
 * Tables: device_registrations, security_audit_log, login_attempts, security_policies
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE device_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    device_name VARCHAR(100) NOT NULL,
    hardware_id VARCHAR(200) NOT NULL,
    os_info VARCHAR(100),
    app_version VARCHAR(20),
    last_active_at TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    remote_wipe_requested BOOLEAN DEFAULT false,
    registered_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, hardware_id)
);

CREATE TABLE security_audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_id UUID,
    user_type VARCHAR(20),
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50),
    resource_id UUID,
    details JSONB DEFAULT '{}',
    severity VARCHAR(10) DEFAULT 'info',
    ip_address VARCHAR(45),
    device_id UUID REFERENCES device_registrations(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE login_attempts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_identifier VARCHAR(100) NOT NULL,
    attempt_type VARCHAR(20) NOT NULL,
    is_successful BOOLEAN NOT NULL,
    ip_address VARCHAR(45),
    device_id UUID,
    attempted_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE security_policies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE UNIQUE,
    pin_min_length INT DEFAULT 4,
    pin_max_length INT DEFAULT 6,
    auto_lock_seconds INT DEFAULT 120,
    max_failed_attempts INT DEFAULT 5,
    lockout_duration_minutes INT DEFAULT 15,
    require_2fa_owner BOOLEAN DEFAULT TRUE,
    session_max_hours INT DEFAULT 12,
    require_pin_override_void BOOLEAN DEFAULT TRUE,
    require_pin_override_return BOOLEAN DEFAULT TRUE,
    require_pin_override_discount BOOLEAN DEFAULT TRUE,
    discount_override_threshold DECIMAL(5,2) DEFAULT 20.0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_policies');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('security_audit_log');
        Schema::dropIfExists('device_registrations');
    }
};
