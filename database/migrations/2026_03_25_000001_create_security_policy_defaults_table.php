<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: Security Policy Defaults
 *
 * Table: security_policy_defaults — singleton-style table (max 1 row)
 * Stores platform-wide security minimums that all provider POS apps inherit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE security_policy_defaults (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    session_timeout_minutes INT NOT NULL DEFAULT 30 CHECK (session_timeout_minutes BETWEEN 5 AND 480),
    require_reauth_on_wake BOOLEAN DEFAULT TRUE,
    pin_min_length INT NOT NULL DEFAULT 4 CHECK (pin_min_length BETWEEN 4 AND 8),
    pin_complexity VARCHAR(30) NOT NULL DEFAULT 'numeric_only',
    require_unique_pins BOOLEAN DEFAULT TRUE,
    pin_expiry_days INT NOT NULL DEFAULT 0,
    biometric_enabled_default BOOLEAN DEFAULT TRUE,
    biometric_can_replace_pin BOOLEAN DEFAULT FALSE,
    max_failed_login_attempts INT NOT NULL DEFAULT 5,
    lockout_duration_minutes INT NOT NULL DEFAULT 15,
    failed_attempt_alert_to_owner BOOLEAN DEFAULT TRUE,
    device_registration_policy VARCHAR(30) NOT NULL DEFAULT 'open',
    max_devices_per_store INT NOT NULL DEFAULT 10,
    updated_by UUID REFERENCES admin_users(id),
    updated_at TIMESTAMP DEFAULT NOW()
);

INSERT INTO security_policy_defaults (
    session_timeout_minutes, require_reauth_on_wake,
    pin_min_length, pin_complexity, require_unique_pins, pin_expiry_days,
    biometric_enabled_default, biometric_can_replace_pin,
    max_failed_login_attempts, lockout_duration_minutes, failed_attempt_alert_to_owner,
    device_registration_policy, max_devices_per_store
) VALUES (
    30, true,
    4, 'numeric_only', true, 0,
    true, false,
    5, 15, true,
    'open', 10
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_policy_defaults');
    }
};
