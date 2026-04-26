<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance sync_log and sync_conflicts tables:
 * - sync_log: add sync_token, metadata columns
 * - sync_conflicts: add conflict_type, auto_resolved columns
 * - Add indexes for performance
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite enhancements
            if (Schema::hasTable('sync_log') && !Schema::hasColumn('sync_log', 'sync_token')) {
                Schema::table('sync_log', function (Blueprint $table) {
                    $table->string('sync_token', 255)->nullable()->after('status');
                    $table->string('client_version', 20)->nullable()->after('sync_token');
                    $table->integer('conflicts_count')->default(0)->after('records_count');
                });
            }

            if (Schema::hasTable('sync_conflicts') && !Schema::hasColumn('sync_conflicts', 'conflict_type')) {
                Schema::table('sync_conflicts', function (Blueprint $table) {
                    $table->string('conflict_type', 20)->default('update_update')->after('record_id');
                    $table->boolean('auto_resolved')->default(false)->after('resolution');
                    $table->text('resolution_notes')->nullable()->after('auto_resolved');
                });
            }

            return;
        }

        // PostgreSQL enhancements
        DB::unprepared(<<<'SQL'
-- Enhance sync_log
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'sync_log' AND column_name = 'sync_token'
    ) THEN
        ALTER TABLE sync_log ADD COLUMN sync_token VARCHAR(255);
        ALTER TABLE sync_log ADD COLUMN client_version VARCHAR(20);
        ALTER TABLE sync_log ADD COLUMN conflicts_count INTEGER NOT NULL DEFAULT 0;

        CREATE INDEX IF NOT EXISTS idx_sync_log_store_direction ON sync_log (store_id, direction);
        CREATE INDEX IF NOT EXISTS idx_sync_log_store_status ON sync_log (store_id, status);
        CREATE INDEX IF NOT EXISTS idx_sync_log_terminal ON sync_log (terminal_id, started_at DESC);
        CREATE INDEX IF NOT EXISTS idx_sync_log_started_at ON sync_log (started_at DESC);
    END IF;
END $$;

-- Enhance sync_conflicts
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'sync_conflicts' AND column_name = 'conflict_type'
    ) THEN
        ALTER TABLE sync_conflicts ADD COLUMN conflict_type VARCHAR(20) NOT NULL DEFAULT 'update_update';
        ALTER TABLE sync_conflicts ADD COLUMN auto_resolved BOOLEAN NOT NULL DEFAULT FALSE;
        ALTER TABLE sync_conflicts ADD COLUMN resolution_notes TEXT;

        CREATE INDEX IF NOT EXISTS idx_sync_conflicts_unresolved ON sync_conflicts (store_id, detected_at DESC)
            WHERE resolution IS NULL;
        CREATE INDEX IF NOT EXISTS idx_sync_conflicts_table ON sync_conflicts (store_id, table_name);
    END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support DROP COLUMN in older versions — skip
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE sync_log
    DROP COLUMN IF EXISTS sync_token,
    DROP COLUMN IF EXISTS client_version,
    DROP COLUMN IF EXISTS conflicts_count;

ALTER TABLE sync_conflicts
    DROP COLUMN IF EXISTS conflict_type,
    DROP COLUMN IF EXISTS auto_resolved,
    DROP COLUMN IF EXISTS resolution_notes;
SQL);
    }
};
