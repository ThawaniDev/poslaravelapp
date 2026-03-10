<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INTEGRATIONS: Accounting
 *
 * Tables: store_accounting_configs, account_mappings, accounting_exports, auto_export_configs
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE store_accounting_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id) ON DELETE CASCADE,
    provider VARCHAR(20) NOT NULL,
    access_token_encrypted TEXT NOT NULL,
    refresh_token_encrypted TEXT NOT NULL,
    token_expires_at TIMESTAMP NOT NULL,
    realm_id VARCHAR(50),
    tenant_id VARCHAR(50),
    company_name VARCHAR(255),
    connected_at TIMESTAMP DEFAULT NOW(),
    last_sync_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE account_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    pos_account_key VARCHAR(50) NOT NULL,
    provider_account_id VARCHAR(100) NOT NULL,
    provider_account_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, pos_account_key)
);

CREATE TABLE accounting_exports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    provider VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    export_types JSONB NOT NULL DEFAULT '[]',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    entries_count INT DEFAULT 0,
    error_message TEXT,
    journal_entry_ids JSONB DEFAULT '[]',
    csv_url TEXT,
    triggered_by VARCHAR(20) NOT NULL DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE TABLE auto_export_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id) ON DELETE CASCADE,
    enabled BOOLEAN DEFAULT FALSE,
    frequency VARCHAR(20) NOT NULL DEFAULT 'daily',
    day_of_week INT,
    day_of_month INT,
    "time" TIME DEFAULT '23:00',
    export_types JSONB NOT NULL DEFAULT '["daily_summary"]',
    notify_email VARCHAR(255),
    retry_on_failure BOOLEAN DEFAULT TRUE,
    last_run_at TIMESTAMP,
    next_run_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_accounting_exports_store_date ON accounting_exports (store_id, created_at DESC);

CREATE INDEX idx_accounting_exports_status ON accounting_exports (status) WHERE status IN ('pending', 'processing');

CREATE INDEX idx_auto_export_next_run ON auto_export_configs (next_run_at) WHERE enabled = TRUE;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_export_configs');
        Schema::dropIfExists('accounting_exports');
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('store_accounting_configs');
    }
};
