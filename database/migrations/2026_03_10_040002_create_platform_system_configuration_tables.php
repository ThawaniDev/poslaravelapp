<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: System Configuration
 *
 * Tables: system_settings, feature_flags, supported_locales, master_translation_strings, translation_versions, accounting_integration_configs, payment_methods, certified_hardware, tax_exemption_types, age_restricted_categories, thawani_marketplace_config
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key VARCHAR(100) NOT NULL UNIQUE,
    value JSONB NOT NULL,
    "group" VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    updated_by UUID REFERENCES admin_users(id),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE feature_flags (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    flag_key VARCHAR(100) NOT NULL UNIQUE,
    is_enabled BOOLEAN DEFAULT FALSE,
    rollout_percentage INT DEFAULT 0 CHECK (rollout_percentage BETWEEN 0 AND 100),
    target_plan_ids JSONB DEFAULT '[]',
    target_store_ids JSONB DEFAULT '[]',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE supported_locales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    locale_code VARCHAR(10) NOT NULL UNIQUE,
    language_name VARCHAR(50) NOT NULL,
    language_name_native VARCHAR(50) NOT NULL,
    direction VARCHAR(3) NOT NULL DEFAULT 'ltr',
    date_format VARCHAR(20) DEFAULT 'DD/MM/YYYY',
    number_format VARCHAR(20) DEFAULT 'western',
    calendar_system VARCHAR(20) DEFAULT 'gregorian',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE master_translation_strings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    string_key VARCHAR(200) NOT NULL UNIQUE,
    category VARCHAR(30) NOT NULL,
    value_en TEXT NOT NULL,
    value_ar TEXT NOT NULL,
    description VARCHAR(255),
    is_overridable BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE translation_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version_hash VARCHAR(64) NOT NULL,
    published_at TIMESTAMP DEFAULT NOW(),
    published_by UUID REFERENCES admin_users(id),
    notes VARCHAR(255)
);

CREATE TABLE accounting_integration_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_name VARCHAR(30) NOT NULL UNIQUE,
    client_id_encrypted TEXT NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    redirect_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE payment_methods (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    method_key VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    icon VARCHAR(255),
    category VARCHAR(20) NOT NULL,
    requires_terminal BOOLEAN DEFAULT FALSE,
    requires_customer_profile BOOLEAN DEFAULT FALSE,
    provider_config_schema JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE certified_hardware (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    device_type VARCHAR(30) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    driver_protocol VARCHAR(30) NOT NULL,
    connection_types JSONB DEFAULT '[]',
    firmware_version_min VARCHAR(20),
    paper_widths JSONB,
    setup_instructions TEXT,
    setup_instructions_ar TEXT,
    is_certified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(brand, model)
);

CREATE TABLE tax_exemption_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    required_documents TEXT,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE age_restricted_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category_slug VARCHAR(100) NOT NULL,
    min_age INT NOT NULL CHECK (min_age > 0),
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE thawani_marketplace_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id_encrypted TEXT NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    redirect_url VARCHAR(255) NOT NULL,
    api_base_url VARCHAR(255) NOT NULL,
    api_version VARCHAR(10) DEFAULT 'v2',
    webhook_url VARCHAR(255) NOT NULL,
    webhook_secret_encrypted TEXT NOT NULL,
    sync_interval_minutes INT DEFAULT 60,
    is_active BOOLEAN DEFAULT TRUE,
    last_connection_at TIMESTAMP,
    connection_status VARCHAR(20) DEFAULT 'unknown',
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_system_settings_group ON system_settings ("group");

CREATE INDEX idx_master_translations_category ON master_translation_strings (category);

CREATE INDEX idx_certified_hardware_type ON certified_hardware (device_type, is_active);

CREATE INDEX idx_age_restricted_slug ON age_restricted_categories (category_slug);

INSERT INTO supported_locales (locale_code, language_name, language_name_native, direction, calendar_system, is_default) VALUES
('ar', 'Arabic', 'العربية', 'rtl', 'both', true),
('en', 'English', 'English', 'ltr', 'gregorian', false);

INSERT INTO payment_methods (method_key, name, name_ar, category, requires_terminal, sort_order) VALUES
('cash', 'Cash', 'نقد', 'cash', false, 1),
('card_mada', 'Mada Card', 'بطاقة مدى', 'card', true, 2),
('card_visa', 'Visa', 'فيزا', 'card', true, 3),
('card_mastercard', 'Mastercard', 'ماستركارد', 'card', true, 4),
('store_credit', 'Store Credit', 'رصيد المتجر', 'credit', false, 5),
('gift_card', 'Gift Card', 'بطاقة هدية', 'credit', false, 6),
('mobile_payment', 'Mobile Payment', 'دفع بالجوال', 'digital', false, 7);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('thawani_marketplace_config');
        Schema::dropIfExists('age_restricted_categories');
        Schema::dropIfExists('tax_exemption_types');
        Schema::dropIfExists('certified_hardware');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('accounting_integration_configs');
        Schema::dropIfExists('translation_versions');
        Schema::dropIfExists('master_translation_strings');
        Schema::dropIfExists('supported_locales');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('system_settings');
    }
};
