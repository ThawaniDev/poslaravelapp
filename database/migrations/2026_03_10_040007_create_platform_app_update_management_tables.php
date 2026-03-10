<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: App Update Management
 *
 * Tables: app_releases, app_update_stats
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE app_releases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version_number VARCHAR(20) NOT NULL,
    platform VARCHAR(10) NOT NULL,
    channel VARCHAR(10) NOT NULL DEFAULT 'stable',
    download_url TEXT NOT NULL,
    store_url TEXT,
    build_number VARCHAR(20),
    submission_status VARCHAR(20) DEFAULT 'not_applicable',
    release_notes TEXT,
    release_notes_ar TEXT,
    is_force_update BOOLEAN DEFAULT FALSE,
    min_supported_version VARCHAR(20),
    rollout_percentage INT NOT NULL DEFAULT 0 CHECK (rollout_percentage BETWEEN 0 AND 100),
    is_active BOOLEAN DEFAULT TRUE,
    released_at TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (platform, channel, version_number)
);

CREATE TABLE app_update_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    app_release_id UUID NOT NULL REFERENCES app_releases(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_app_releases_active ON app_releases (platform, channel, is_active);

CREATE INDEX idx_update_stats_release_status ON app_update_stats (app_release_id, status);

CREATE INDEX idx_update_stats_store ON app_update_stats (store_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_update_stats');
        Schema::dropIfExists('app_releases');
    }
};
