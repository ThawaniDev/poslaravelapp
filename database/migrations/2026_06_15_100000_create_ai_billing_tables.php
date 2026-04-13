<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WAMEED AI BILLING — Comprehensive billing system for AI usage
 *
 * Tables: ai_billing_settings, ai_store_billing_configs, ai_billing_invoices,
 *         ai_billing_invoice_items, ai_billing_payments
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'

-- ─── AI Billing Settings (platform-level global config) ──────────────────
CREATE TABLE IF NOT EXISTS ai_billing_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key VARCHAR(100) NOT NULL UNIQUE,
    value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Seed default settings
INSERT INTO ai_billing_settings (key, value, description) VALUES
    ('margin_percentage', '20.000', 'Margin percentage added on top of raw AI cost (e.g. 20 = 20%)'),
    ('auto_disable_grace_days', '5', 'Days after month start to auto-disable stores with unpaid invoices'),
    ('global_monthly_limit_usd', '500.000', 'Maximum monthly AI spending limit in USD for all stores (0 = unlimited)'),
    ('billing_enabled', 'true', 'Whether the AI billing system is active'),
    ('invoice_generation_day', '1', 'Day of month to generate invoices (1 = first day)'),
    ('currency', 'USD', 'Currency for billing display'),
    ('min_billable_amount_usd', '0.010', 'Minimum amount to generate an invoice (below this = free)')
ON CONFLICT (key) DO NOTHING;

-- ─── AI Store Billing Configs (per-store billing configuration) ──────────
CREATE TABLE IF NOT EXISTS ai_store_billing_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    is_ai_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    monthly_limit_usd DECIMAL(12,3) NOT NULL DEFAULT 0,
    custom_margin_percentage DECIMAL(5,3),
    disabled_reason VARCHAR(100),
    disabled_at TIMESTAMP,
    enabled_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id)
);

CREATE INDEX idx_ai_store_billing_store ON ai_store_billing_configs (store_id);
CREATE INDEX idx_ai_store_billing_org ON ai_store_billing_configs (organization_id);
CREATE INDEX idx_ai_store_billing_enabled ON ai_store_billing_configs (is_ai_enabled);

-- ─── AI Billing Invoices (monthly invoices per store) ────────────────────
CREATE TABLE IF NOT EXISTS ai_billing_invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    year INT NOT NULL,
    month INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_requests INT NOT NULL DEFAULT 0,
    total_tokens BIGINT NOT NULL DEFAULT 0,
    raw_cost_usd DECIMAL(12,5) NOT NULL DEFAULT 0,
    margin_percentage DECIMAL(5,3) NOT NULL DEFAULT 20.000,
    margin_amount_usd DECIMAL(12,5) NOT NULL DEFAULT 0,
    billed_amount_usd DECIMAL(12,5) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    due_date DATE NOT NULL,
    paid_at TIMESTAMP,
    payment_reference VARCHAR(255),
    payment_notes TEXT,
    generated_at TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, year, month)
);

CREATE INDEX idx_ai_billing_invoices_store ON ai_billing_invoices (store_id);
CREATE INDEX idx_ai_billing_invoices_status ON ai_billing_invoices (status);
CREATE INDEX idx_ai_billing_invoices_due ON ai_billing_invoices (due_date);
CREATE INDEX idx_ai_billing_invoices_period ON ai_billing_invoices (year, month);

-- ─── AI Billing Invoice Items (per-feature breakdown) ────────────────────
CREATE TABLE IF NOT EXISTS ai_billing_invoice_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ai_billing_invoice_id UUID NOT NULL REFERENCES ai_billing_invoices(id) ON DELETE CASCADE,
    feature_slug VARCHAR(100) NOT NULL,
    feature_name VARCHAR(255),
    feature_name_ar VARCHAR(255),
    request_count INT NOT NULL DEFAULT 0,
    total_tokens BIGINT NOT NULL DEFAULT 0,
    raw_cost_usd DECIMAL(12,5) NOT NULL DEFAULT 0,
    billed_cost_usd DECIMAL(12,5) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_invoice_items_invoice ON ai_billing_invoice_items (ai_billing_invoice_id);
CREATE INDEX idx_ai_invoice_items_feature ON ai_billing_invoice_items (feature_slug);

-- ─── AI Billing Payments (payment records) ───────────────────────────────
CREATE TABLE IF NOT EXISTS ai_billing_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ai_billing_invoice_id UUID NOT NULL REFERENCES ai_billing_invoices(id) ON DELETE CASCADE,
    amount_usd DECIMAL(12,5) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'manual',
    reference VARCHAR(255),
    notes TEXT,
    recorded_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_billing_payments_invoice ON ai_billing_payments (ai_billing_invoice_id);

SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_billing_payments');
        Schema::dropIfExists('ai_billing_invoice_items');
        Schema::dropIfExists('ai_billing_invoices');
        Schema::dropIfExists('ai_store_billing_configs');
        Schema::dropIfExists('ai_billing_settings');
    }
};
