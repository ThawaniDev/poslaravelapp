<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPERATIONS: Backup & Sync
 *
 * Tables: backup_history, update_rollouts, sync_conflicts, sync_log
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
CREATE TABLE backup_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    backup_type VARCHAR(20) NOT NULL,
    storage_location VARCHAR(20) NOT NULL,
    local_path TEXT,
    cloud_key VARCHAR(500),
    file_size_bytes BIGINT NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    db_version INTEGER NOT NULL,
    records_count INTEGER,
    is_verified BOOLEAN DEFAULT FALSE,
    is_encrypted BOOLEAN DEFAULT TRUE,
    status VARCHAR(20) DEFAULT 'completed',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE update_rollouts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version VARCHAR(20) NOT NULL,
    rollout_percentage INTEGER NOT NULL DEFAULT 0,
    is_critical BOOLEAN DEFAULT FALSE,
    target_stores JSONB,
    pinned_stores JSONB,
    release_notes TEXT NOT NULL,
    released_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE sync_conflicts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_name VARCHAR(100) NOT NULL,
    record_id UUID NOT NULL,
    local_data JSONB NOT NULL,
    cloud_data JSONB NOT NULL,
    resolution VARCHAR(20),
    resolved_by UUID REFERENCES users(id),
    detected_at TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP
);

CREATE TABLE sync_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    direction VARCHAR(10) NOT NULL,
    records_count INTEGER NOT NULL DEFAULT 0,
    duration_ms INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    error_message TEXT,
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMP
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_log');
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('update_rollouts');
        Schema::dropIfExists('backup_history');
    }
};
