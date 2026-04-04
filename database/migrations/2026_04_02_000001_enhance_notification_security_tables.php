<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENHANCEMENT: Notification & Security Feature Completion
 *
 * New tables:
 *   - notification_schedules          — scheduled / recurring notifications
 *   - notification_batches            — batch notification tracking
 *   - notification_sound_configs      — per-store sound alert configuration
 *   - notification_read_receipts      — track when/how notifications were read
 *   - security_sessions               — provider-side staff sessions (POS sessions)
 *   - security_incidents              — provider-side security incidents / alerts
 *
 * Altered tables:
 *   - notifications_custom            — add priority, expires_at, metadata columns
 *   - notification_preferences        — add per_category_channels JSON, sound_enabled
 *   - notification_delivery_logs      — add retry_count, next_retry_at
 *   - device_registrations            — add ip_address, screen_resolution, last_known_location
 *   - security_audit_log              — add request_method, request_url, response_code, duration_ms
 *   - login_attempts                  — add user_agent, failure_reason, geo_location
 *   - security_policies               — add biometric_enabled, pin_expiry_days, require_unique_pins,
 *                                        max_devices, audit_retention_days, force_logout_on_role_change
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // ─── New Tables ─────────────────────────────────────

        DB::unprepared(<<<'SQL'
-- Scheduled notifications for delayed / recurring delivery
CREATE TABLE IF NOT EXISTS notification_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    recipient_user_id UUID,
    recipient_group VARCHAR(50),
    variables JSONB DEFAULT '{}',
    schedule_type VARCHAR(20) NOT NULL DEFAULT 'once',
    scheduled_at TIMESTAMP NOT NULL,
    cron_expression VARCHAR(100),
    timezone VARCHAR(50) DEFAULT 'Asia/Riyadh',
    is_active BOOLEAN DEFAULT TRUE,
    last_sent_at TIMESTAMP,
    next_run_at TIMESTAMP,
    created_by UUID,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_notification_schedules_next_run ON notification_schedules (next_run_at) WHERE is_active = TRUE;

-- Batch notification tracking
CREATE TABLE IF NOT EXISTS notification_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id) ON DELETE CASCADE,
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    total_recipients INT NOT NULL DEFAULT 0,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    metadata JSONB DEFAULT '{}',
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Per-store sound configuration for POS alerts
CREATE TABLE IF NOT EXISTS notification_sound_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    event_key VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    sound_file VARCHAR(255) DEFAULT 'default',
    volume DECIMAL(3,2) DEFAULT 0.80,
    repeat_count INT DEFAULT 1,
    repeat_interval_seconds INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, event_key)
);

-- Track when/how notifications were acknowledged
CREATE TABLE IF NOT EXISTS notification_read_receipts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    notification_id UUID NOT NULL,
    user_id UUID NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT NOW(),
    read_via VARCHAR(30) DEFAULT 'click',
    device_type VARCHAR(20)
);

CREATE INDEX idx_notification_read_receipts_notification ON notification_read_receipts (notification_id);
CREATE INDEX idx_notification_read_receipts_user ON notification_read_receipts (user_id, read_at DESC);

-- Provider-side staff sessions (POS terminal sessions)
CREATE TABLE IF NOT EXISTS security_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    user_id UUID NOT NULL,
    device_id UUID REFERENCES device_registrations(id),
    session_type VARCHAR(20) NOT NULL DEFAULT 'shift',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    ip_address VARCHAR(45),
    user_agent TEXT,
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    last_activity_at TIMESTAMP DEFAULT NOW(),
    ended_at TIMESTAMP,
    end_reason VARCHAR(50),
    metadata JSONB DEFAULT '{}'
);

CREATE INDEX idx_security_sessions_store_user ON security_sessions (store_id, user_id, status);
CREATE INDEX idx_security_sessions_active ON security_sessions (status) WHERE status = 'active';

-- Provider-side security incidents
CREATE TABLE IF NOT EXISTS security_incidents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    incident_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    source_ip VARCHAR(45),
    user_id UUID,
    device_id UUID REFERENCES device_registrations(id),
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    resolved_by UUID,
    resolved_at TIMESTAMP,
    resolution_notes TEXT,
    auto_detected BOOLEAN DEFAULT FALSE,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_security_incidents_store_status ON security_incidents (store_id, status, created_at DESC);
SQL);

        // ─── Create notifications_custom if missing ─────────
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS notifications_custom (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    store_id UUID REFERENCES stores(id) ON DELETE CASCADE,
    category VARCHAR(30) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500),
    reference_type VARCHAR(50),
    reference_id UUID,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);

        // ─── Alter Existing Tables ──────────────────────────

        // notifications_custom enhancements
        DB::statement("ALTER TABLE notifications_custom ADD COLUMN IF NOT EXISTS priority VARCHAR(10) DEFAULT 'normal'");
        DB::statement("ALTER TABLE notifications_custom ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP");
        DB::statement("ALTER TABLE notifications_custom ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}'");
        DB::statement("ALTER TABLE notifications_custom ADD COLUMN IF NOT EXISTS channel VARCHAR(20) DEFAULT 'in_app'");
        DB::statement("ALTER TABLE notifications_custom ADD COLUMN IF NOT EXISTS read_at TIMESTAMP");

        // notification_preferences enhancements
        DB::statement("ALTER TABLE notification_preferences ADD COLUMN IF NOT EXISTS per_category_channels JSONB DEFAULT '{}'");
        DB::statement("ALTER TABLE notification_preferences ADD COLUMN IF NOT EXISTS sound_enabled BOOLEAN DEFAULT TRUE");
        DB::statement("ALTER TABLE notification_preferences ADD COLUMN IF NOT EXISTS email_digest VARCHAR(20) DEFAULT 'none'");

        // notification_delivery_logs enhancements
        DB::statement("ALTER TABLE notification_delivery_logs ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0");
        DB::statement("ALTER TABLE notification_delivery_logs ADD COLUMN IF NOT EXISTS next_retry_at TIMESTAMP");
        DB::statement("ALTER TABLE notification_delivery_logs ADD COLUMN IF NOT EXISTS request_payload JSONB");
        DB::statement("ALTER TABLE notification_delivery_logs ADD COLUMN IF NOT EXISTS response_payload JSONB");

        // device_registrations enhancements
        DB::statement("ALTER TABLE device_registrations ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45)");
        DB::statement("ALTER TABLE device_registrations ADD COLUMN IF NOT EXISTS screen_resolution VARCHAR(20)");
        DB::statement("ALTER TABLE device_registrations ADD COLUMN IF NOT EXISTS last_known_location VARCHAR(100)");
        DB::statement("ALTER TABLE device_registrations ADD COLUMN IF NOT EXISTS device_type VARCHAR(30) DEFAULT 'desktop'");

        // security_audit_log enhancements
        DB::statement("ALTER TABLE security_audit_log ADD COLUMN IF NOT EXISTS request_method VARCHAR(10)");
        DB::statement("ALTER TABLE security_audit_log ADD COLUMN IF NOT EXISTS request_url TEXT");
        DB::statement("ALTER TABLE security_audit_log ADD COLUMN IF NOT EXISTS response_code INT");
        DB::statement("ALTER TABLE security_audit_log ADD COLUMN IF NOT EXISTS duration_ms INT");
        DB::statement("ALTER TABLE security_audit_log ADD COLUMN IF NOT EXISTS user_agent TEXT");

        // login_attempts enhancements
        DB::statement("ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS user_agent TEXT");
        DB::statement("ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS failure_reason VARCHAR(100)");
        DB::statement("ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS geo_location VARCHAR(100)");
        DB::statement("ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS device_name VARCHAR(100)");

        // security_policies enhancements
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS biometric_enabled BOOLEAN DEFAULT TRUE");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS pin_expiry_days INT DEFAULT 0");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS require_unique_pins BOOLEAN DEFAULT TRUE");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS max_devices INT DEFAULT 10");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS audit_retention_days INT DEFAULT 90");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS force_logout_on_role_change BOOLEAN DEFAULT TRUE");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS password_expiry_days INT DEFAULT 0");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS require_strong_password BOOLEAN DEFAULT FALSE");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS ip_restriction_enabled BOOLEAN DEFAULT FALSE");
        DB::statement("ALTER TABLE security_policies ADD COLUMN IF NOT EXISTS allowed_ip_ranges JSONB DEFAULT '[]'");

        // Indexes for performance
        DB::statement("CREATE INDEX IF NOT EXISTS idx_notif_custom_user_read ON notifications_custom (user_id, is_read, created_at DESC)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_notif_custom_store_category ON notifications_custom (store_id, category, created_at DESC)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_notif_custom_expires ON notifications_custom (expires_at) WHERE expires_at IS NOT NULL");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_login_attempts_brute ON login_attempts (store_id, user_identifier, is_successful, attempted_at)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_security_audit_store_action ON security_audit_log (store_id, action, created_at DESC)");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // Drop new tables
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('security_sessions');
        Schema::dropIfExists('notification_read_receipts');
        Schema::dropIfExists('notification_sound_configs');
        Schema::dropIfExists('notification_batches');
        Schema::dropIfExists('notification_schedules');

        // Revert column additions (reverse order)
        $drops = [
            ['security_policies', 'allowed_ip_ranges'],
            ['security_policies', 'ip_restriction_enabled'],
            ['security_policies', 'require_strong_password'],
            ['security_policies', 'password_expiry_days'],
            ['security_policies', 'force_logout_on_role_change'],
            ['security_policies', 'audit_retention_days'],
            ['security_policies', 'max_devices'],
            ['security_policies', 'require_unique_pins'],
            ['security_policies', 'pin_expiry_days'],
            ['security_policies', 'biometric_enabled'],
            ['login_attempts', 'device_name'],
            ['login_attempts', 'geo_location'],
            ['login_attempts', 'failure_reason'],
            ['login_attempts', 'user_agent'],
            ['security_audit_log', 'user_agent'],
            ['security_audit_log', 'duration_ms'],
            ['security_audit_log', 'response_code'],
            ['security_audit_log', 'request_url'],
            ['security_audit_log', 'request_method'],
            ['device_registrations', 'device_type'],
            ['device_registrations', 'last_known_location'],
            ['device_registrations', 'screen_resolution'],
            ['device_registrations', 'ip_address'],
            ['notification_delivery_logs', 'response_payload'],
            ['notification_delivery_logs', 'request_payload'],
            ['notification_delivery_logs', 'next_retry_at'],
            ['notification_delivery_logs', 'retry_count'],
            ['notification_preferences', 'email_digest'],
            ['notification_preferences', 'sound_enabled'],
            ['notification_preferences', 'per_category_channels'],
            ['notifications_custom', 'read_at'],
            ['notifications_custom', 'channel'],
            ['notifications_custom', 'metadata'],
            ['notifications_custom', 'expires_at'],
            ['notifications_custom', 'priority'],
        ];

        foreach ($drops as [$table, $column]) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS {$column}");
        }

        // Drop indexes
        DB::statement("DROP INDEX IF EXISTS idx_notif_custom_user_read");
        DB::statement("DROP INDEX IF EXISTS idx_notif_custom_store_category");
        DB::statement("DROP INDEX IF EXISTS idx_notif_custom_expires");
        DB::statement("DROP INDEX IF EXISTS idx_login_attempts_brute");
        DB::statement("DROP INDEX IF EXISTS idx_security_audit_store_action");

        // Drop notifications_custom (created by this migration if it didn't exist)
        Schema::dropIfExists('notifications_custom');
    }
};
