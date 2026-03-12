<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: Subscription Plans & Billing
 *
 * Tables: subscription_plans, plan_feature_toggles, plan_limits, plan_add_ons, subscription_discounts, payment_gateway_configs, payment_retry_rules
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
CREATE TABLE subscription_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    monthly_price DECIMAL(10,2) NOT NULL,
    annual_price DECIMAL(10,2) NOT NULL,
    trial_days INT DEFAULT 14,
    grace_period_days INT DEFAULT 7,
    is_active BOOLEAN DEFAULT TRUE,
    is_highlighted BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE plan_feature_toggles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    feature_key VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT FALSE,
    UNIQUE (subscription_plan_id, feature_key)
);

CREATE TABLE plan_limits (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    limit_key VARCHAR(50) NOT NULL,
    limit_value INT NOT NULL DEFAULT 0,
    price_per_extra_unit DECIMAL(10,2) DEFAULT 0,
    UNIQUE (subscription_plan_id, limit_key)
);

CREATE TABLE plan_add_ons (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    monthly_price DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE subscription_discounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    max_uses INT,
    times_used INT DEFAULT 0,
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    applicable_plan_ids JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE payment_gateway_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gateway_name VARCHAR(50) NOT NULL,
    credentials_encrypted JSONB NOT NULL,
    webhook_url TEXT,
    environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (gateway_name, environment)
);

CREATE TABLE payment_retry_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    max_retries INT NOT NULL DEFAULT 3,
    retry_interval_hours INT NOT NULL DEFAULT 24,
    grace_period_after_failure_days INT NOT NULL DEFAULT 7,
    updated_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_retry_rules');
        Schema::dropIfExists('payment_gateway_configs');
        Schema::dropIfExists('subscription_discounts');
        Schema::dropIfExists('plan_add_ons');
        Schema::dropIfExists('plan_limits');
        Schema::dropIfExists('plan_feature_toggles');
        Schema::dropIfExists('subscription_plans');
    }
};
