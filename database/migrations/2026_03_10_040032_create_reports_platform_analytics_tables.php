<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REPORTS: Platform Analytics
 *
 * Tables: platform_daily_stats, platform_plan_stats, feature_adoption_stats, store_health_snapshots
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
CREATE TABLE platform_daily_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    date DATE NOT NULL UNIQUE,
    total_active_stores INT NOT NULL DEFAULT 0,
    new_registrations INT NOT NULL DEFAULT 0,
    total_orders INT NOT NULL DEFAULT 0,
    total_gmv DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_mrr DECIMAL(12,2) NOT NULL DEFAULT 0,
    churn_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE platform_plan_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id),
    date DATE NOT NULL,
    active_count INT NOT NULL DEFAULT 0,
    trial_count INT NOT NULL DEFAULT 0,
    churned_count INT NOT NULL DEFAULT 0,
    mrr DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE (subscription_plan_id, date)
);

CREATE TABLE feature_adoption_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    feature_key VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    stores_using_count INT NOT NULL DEFAULT 0,
    total_events INT NOT NULL DEFAULT 0,
    UNIQUE (feature_key, date)
);

CREATE TABLE store_health_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    date DATE NOT NULL,
    sync_status VARCHAR(10),
    zatca_compliance BOOLEAN,
    error_count INT DEFAULT 0,
    last_activity_at TIMESTAMP,
    UNIQUE (store_id, date)
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_health_snapshots');
        Schema::dropIfExists('feature_adoption_stats');
        Schema::dropIfExists('platform_plan_stats');
        Schema::dropIfExists('platform_daily_stats');
    }
};
