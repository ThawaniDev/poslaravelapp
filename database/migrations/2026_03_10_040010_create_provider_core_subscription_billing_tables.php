<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROVIDER CORE: Subscription & Billing
 *
 * Tables: store_subscriptions, invoices, invoice_line_items, subscription_credits, store_add_ons, subscription_usage_snapshots, provider_backup_status
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
CREATE TABLE store_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id),
    status VARCHAR(20) NOT NULL DEFAULT 'trial',
    billing_cycle VARCHAR(10) DEFAULT 'monthly',
    current_period_start TIMESTAMP NOT NULL,
    current_period_end TIMESTAMP NOT NULL,
    trial_ends_at TIMESTAMP,
    payment_method VARCHAR(50),
    cancelled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    due_date DATE NOT NULL,
    paid_at TIMESTAMP,
    pdf_url TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE invoice_line_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL
);

CREATE TABLE subscription_credits (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    applied_by UUID NOT NULL REFERENCES admin_users(id),
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    applied_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE store_add_ons (
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    plan_add_on_id UUID NOT NULL REFERENCES plan_add_ons(id),
    activated_at TIMESTAMP DEFAULT NOW(),
    is_active BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (store_id, plan_add_on_id)
);

CREATE TABLE subscription_usage_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    resource_type VARCHAR(50) NOT NULL,
    current_count INTEGER NOT NULL,
    plan_limit INTEGER NOT NULL,
    snapshot_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, resource_type, snapshot_date)
);

CREATE TABLE provider_backup_status (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    last_successful_sync TIMESTAMP,
    last_cloud_backup TIMESTAMP,
    storage_used_bytes BIGINT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'unknown',
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, terminal_id)
);

CREATE INDEX idx_provider_backup_status_status ON provider_backup_status (status);

CREATE INDEX idx_provider_backup_status_store ON provider_backup_status (store_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_backup_status');
        Schema::dropIfExists('subscription_usage_snapshots');
        Schema::dropIfExists('store_add_ons');
        Schema::dropIfExists('subscription_credits');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('store_subscriptions');
    }
};
