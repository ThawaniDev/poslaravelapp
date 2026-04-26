<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPERATIONS: Store Backup Settings
 *
 * Stores per-store backup schedule configuration (auto-backup frequency,
 * retention policy, encryption preference, cloud backup toggle).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite handled in test schema migration
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS store_backup_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    auto_backup_enabled BOOLEAN DEFAULT TRUE,
    frequency VARCHAR(20) DEFAULT 'daily',
    retention_days INTEGER DEFAULT 30,
    encrypt_backups BOOLEAN DEFAULT FALSE,
    local_backup_enabled BOOLEAN DEFAULT TRUE,
    cloud_backup_enabled BOOLEAN DEFAULT TRUE,
    backup_hour INTEGER DEFAULT 2,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id)
);

CREATE INDEX IF NOT EXISTS idx_store_backup_settings_store ON store_backup_settings(store_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_backup_settings');
    }
};
