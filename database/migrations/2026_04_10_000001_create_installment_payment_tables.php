<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INSTALLMENT PAYMENTS: BNPL Provider Integration
 *
 * Tables:
 *   installment_providers       — Platform-level provider configuration (Tabby, Tamara, MisPay, Madfu)
 *   store_installment_configs   — Per-store credentials & preferences for each provider
 *   installment_payments        — Individual installment payment lifecycle tracking
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'

-- ─── Platform-level installment provider registry ───────────────────────
-- Managed by platform admins. Controls which BNPL providers are available.
CREATE TABLE installment_providers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    logo_url TEXT,
    description TEXT,
    description_ar TEXT,
    supported_currencies JSONB NOT NULL DEFAULT '["SAR"]',
    min_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(12,2) NOT NULL DEFAULT 99999.99,
    supported_installment_counts JSONB NOT NULL DEFAULT '[3,4,6]',
    environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    is_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    is_under_maintenance BOOLEAN NOT NULL DEFAULT FALSE,
    maintenance_message TEXT,
    maintenance_message_ar TEXT,
    platform_config JSONB NOT NULL DEFAULT '{}',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- ─── Per-store installment provider configuration ───────────────────────
-- Each store has its own credentials per BNPL provider.
CREATE TABLE store_installment_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    provider VARCHAR(30) NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    -- Encrypted credentials (API keys, secrets, merchant codes)
    credentials JSONB NOT NULL DEFAULT '{}',
    -- Provider-specific merchant information
    merchant_code VARCHAR(255),
    -- Webhook URLs for callbacks
    webhook_url TEXT,
    -- Success/failure/cancel redirect URLs
    success_url TEXT,
    failure_url TEXT,
    cancel_url TEXT,
    -- Additional config (min/max overrides, custom settings)
    config JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, provider)
);

-- ─── Installment payment records ────────────────────────────────────────
-- Tracks each installment payment attempt and its lifecycle.
CREATE TABLE installment_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    transaction_id UUID REFERENCES transactions(id),
    payment_id UUID REFERENCES payments(id),
    provider VARCHAR(30) NOT NULL,
    -- Provider-side identifiers
    provider_order_id VARCHAR(255),
    provider_checkout_id VARCHAR(255),
    provider_payment_id VARCHAR(255),
    -- Payment details
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'SAR',
    installment_count INT,
    -- Status tracking
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    -- Checkout URLs
    checkout_url TEXT,
    -- Customer info snapshot
    customer_name VARCHAR(255),
    customer_phone VARCHAR(30),
    customer_email VARCHAR(255),
    -- Provider response data
    provider_response JSONB DEFAULT '{}',
    -- Error tracking
    error_code VARCHAR(100),
    error_message TEXT,
    -- Timestamps
    initiated_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    cancelled_at TIMESTAMP,
    expired_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Indexes for performance
CREATE INDEX idx_store_installment_configs_store ON store_installment_configs(store_id);
CREATE INDEX idx_store_installment_configs_provider ON store_installment_configs(provider);
CREATE INDEX idx_installment_payments_store ON installment_payments(store_id);
CREATE INDEX idx_installment_payments_transaction ON installment_payments(transaction_id);
CREATE INDEX idx_installment_payments_status ON installment_payments(status);
CREATE INDEX idx_installment_payments_provider ON installment_payments(provider);

-- Seed default provider configurations
INSERT INTO installment_providers (provider, name, name_ar, description, description_ar, supported_currencies, min_amount, max_amount, supported_installment_counts, sort_order) VALUES
    ('tabby', 'Tabby', 'تابي', 'Split your purchase into 4 interest-free payments', 'قسّم مشترياتك على 4 دفعات بدون فوائد', '["SAR", "AED", "KWD", "BHD"]', 1.00, 5000.00, '[4]', 1),
    ('tamara', 'Tamara', 'تمارا', 'Buy now and pay later in easy installments', 'اشتر الآن وادفع لاحقاً بأقساط سهلة', '["SAR", "AED", "KWD", "BHD"]', 1.00, 10000.00, '[2,3,4,6]', 2),
    ('mispay', 'MisPay', 'مس باي', 'Flexible installment payments made easy', 'مدفوعات أقساط مرنة وسهلة', '["SAR"]', 100.00, 10000.00, '[3,4,6]', 3),
    ('madfu', 'Madfu', 'مدفوع', 'Pay in installments with Madfu', 'ادفع بالأقساط مع مدفوع', '["SAR"]', 100.00, 5000.00, '[3,4,6,12]', 4);

SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('store_installment_configs');
        Schema::dropIfExists('installment_providers');
    }
};
