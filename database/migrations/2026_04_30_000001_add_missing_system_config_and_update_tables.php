<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gap-fill migration for System Configuration & App Update Management.
 *
 * Adds:
 *   - security_policy_defaults (singleton)
 *   - translation_overrides (store-level string overrides)
 *   - payment_methods additional columns (description, fees, min/max, currencies)
 *   - app_releases additional columns (file_checksum, file_size_bytes)
 *   - app_update_stats created_at column for proper auditing
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'

-- ─────────────────────────────────────────────────────────
-- 1. security_policy_defaults (singleton table)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS security_policy_defaults (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    session_timeout_minutes INT NOT NULL DEFAULT 30
        CHECK (session_timeout_minutes BETWEEN 5 AND 480),
    require_reauth_on_wake BOOLEAN NOT NULL DEFAULT TRUE,
    pin_min_length INT NOT NULL DEFAULT 4 CHECK (pin_min_length BETWEEN 4 AND 8),
    pin_complexity VARCHAR(30) NOT NULL DEFAULT 'numeric_only',
    require_unique_pins BOOLEAN NOT NULL DEFAULT TRUE,
    pin_expiry_days INT NOT NULL DEFAULT 0 CHECK (pin_expiry_days >= 0),
    biometric_enabled_default BOOLEAN NOT NULL DEFAULT TRUE,
    biometric_can_replace_pin BOOLEAN NOT NULL DEFAULT FALSE,
    max_failed_login_attempts INT NOT NULL DEFAULT 5 CHECK (max_failed_login_attempts BETWEEN 1 AND 20),
    lockout_duration_minutes INT NOT NULL DEFAULT 15 CHECK (lockout_duration_minutes >= 0),
    failed_attempt_alert_to_owner BOOLEAN NOT NULL DEFAULT TRUE,
    device_registration_policy VARCHAR(20) NOT NULL DEFAULT 'open',
    max_devices_per_store INT NOT NULL DEFAULT 10 CHECK (max_devices_per_store BETWEEN 1 AND 100),
    updated_by UUID REFERENCES admin_users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Seed default row so the settings page always has data
INSERT INTO security_policy_defaults (
    session_timeout_minutes, require_reauth_on_wake, pin_min_length, pin_complexity,
    require_unique_pins, pin_expiry_days, biometric_enabled_default, biometric_can_replace_pin,
    max_failed_login_attempts, lockout_duration_minutes, failed_attempt_alert_to_owner,
    device_registration_policy, max_devices_per_store
) SELECT 30, TRUE, 4, 'numeric_only', TRUE, 0, TRUE, FALSE, 5, 15, TRUE, 'open', 10
WHERE NOT EXISTS (SELECT 1 FROM security_policy_defaults);


-- ─────────────────────────────────────────────────────────
-- 2. translation_overrides (per-store localization overrides)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS translation_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    string_key VARCHAR(200) NOT NULL,
    locale VARCHAR(10) NOT NULL DEFAULT 'ar',
    custom_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, string_key, locale)
);

CREATE INDEX IF NOT EXISTS idx_translation_overrides_store_locale
    ON translation_overrides (store_id, locale);

CREATE INDEX IF NOT EXISTS idx_translation_overrides_key
    ON translation_overrides (string_key);


-- ─────────────────────────────────────────────────────────
-- 3. payment_methods — add missing business columns
-- ─────────────────────────────────────────────────────────
ALTER TABLE payment_methods
    ADD COLUMN IF NOT EXISTS description TEXT,
    ADD COLUMN IF NOT EXISTS description_ar TEXT,
    ADD COLUMN IF NOT EXISTS supported_currencies JSONB DEFAULT '["SAR"]',
    ADD COLUMN IF NOT EXISTS min_amount NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS max_amount NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS processing_fee_percent NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS processing_fee_fixed NUMERIC(12,3) DEFAULT 0;


-- ─────────────────────────────────────────────────────────
-- 4. app_releases — add file integrity columns
-- ─────────────────────────────────────────────────────────
ALTER TABLE app_releases
    ADD COLUMN IF NOT EXISTS file_checksum VARCHAR(64),
    ADD COLUMN IF NOT EXISTS file_size_bytes BIGINT;


-- ─────────────────────────────────────────────────────────
-- 5. app_update_stats — add created_at for audit trail
-- ─────────────────────────────────────────────────────────
ALTER TABLE app_update_stats
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT NOW();

SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::dropIfExists('translation_overrides');
        Schema::dropIfExists('security_policy_defaults');

        DB::unprepared(<<<'SQL'
ALTER TABLE payment_methods
    DROP COLUMN IF EXISTS description,
    DROP COLUMN IF EXISTS description_ar,
    DROP COLUMN IF EXISTS supported_currencies,
    DROP COLUMN IF EXISTS min_amount,
    DROP COLUMN IF EXISTS max_amount,
    DROP COLUMN IF EXISTS processing_fee_percent,
    DROP COLUMN IF EXISTS processing_fee_fixed;

ALTER TABLE app_releases
    DROP COLUMN IF EXISTS file_checksum,
    DROP COLUMN IF EXISTS file_size_bytes;

ALTER TABLE app_update_stats
    DROP COLUMN IF EXISTS created_at;
SQL);
    }
};
