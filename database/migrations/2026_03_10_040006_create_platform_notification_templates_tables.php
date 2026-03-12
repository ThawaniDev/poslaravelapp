<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: Notification Templates
 *
 * Tables: notification_templates, notification_provider_status
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
CREATE TABLE notification_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    available_variables JSONB NOT NULL DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (event_key, channel)
);

CREATE TABLE notification_provider_status (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 1,
    is_healthy BOOLEAN DEFAULT TRUE,
    last_success_at TIMESTAMP,
    last_failure_at TIMESTAMP,
    failure_count_24h INT DEFAULT 0,
    success_count_24h INT DEFAULT 0,
    avg_latency_ms INT,
    disabled_reason TEXT,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (provider, channel)
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_provider_status');
        Schema::dropIfExists('notification_templates');
    }
};
