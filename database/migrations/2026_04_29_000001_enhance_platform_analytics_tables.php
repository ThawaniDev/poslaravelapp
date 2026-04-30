<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REPORTS: Enhance Platform Analytics Tables
 *
 * Adds additional columns to improve analytics quality:
 * - platform_daily_stats: arr, avg_revenue_per_store, gross_gmv, refund_count
 * - platform_plan_stats: gross_mrr, net_mrr, avg_plan_age_days
 * - store_health_snapshots: last_sync_error, zatca_error_code, health_score
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite: add columns inline for test environment
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            // SQLite does not support multiple ADD COLUMN in one statement
            // Skip for tests — the test schema migration handles this separately
            return;
        }

        DB::unprepared(<<<'SQL'
-- Enhance platform_daily_stats
ALTER TABLE platform_daily_stats
    ADD COLUMN IF NOT EXISTS arr DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS avg_revenue_per_store DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS refund_count INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW();

-- Enhance platform_plan_stats
ALTER TABLE platform_plan_stats
    ADD COLUMN IF NOT EXISTS gross_mrr DECIMAL(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS net_mrr DECIMAL(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS avg_plan_age_days DECIMAL(8,2) NOT NULL DEFAULT 0;

-- Enhance store_health_snapshots
ALTER TABLE store_health_snapshots
    ADD COLUMN IF NOT EXISTS last_sync_error TEXT,
    ADD COLUMN IF NOT EXISTS zatca_error_code VARCHAR(50),
    ADD COLUMN IF NOT EXISTS health_score DECIMAL(5,2),
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT NOW();
SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE platform_daily_stats
    DROP COLUMN IF EXISTS arr,
    DROP COLUMN IF EXISTS avg_revenue_per_store,
    DROP COLUMN IF EXISTS refund_count,
    DROP COLUMN IF EXISTS updated_at;

ALTER TABLE platform_plan_stats
    DROP COLUMN IF EXISTS gross_mrr,
    DROP COLUMN IF EXISTS net_mrr,
    DROP COLUMN IF EXISTS avg_plan_age_days;

ALTER TABLE store_health_snapshots
    DROP COLUMN IF EXISTS last_sync_error,
    DROP COLUMN IF EXISTS zatca_error_code,
    DROP COLUMN IF EXISTS health_score,
    DROP COLUMN IF EXISTS created_at;
SQL);
    }
};
