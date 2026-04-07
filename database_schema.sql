-- ==========================================================================
-- Wameed POS PLATFORM - COMPREHENSIVE DATABASE SCHEMA
-- Generated: 8 March 2026
-- Database: PostgreSQL 15+ (Laravel 11 backend)
-- Total tables: 255 unique tables across 47 feature files
-- ==========================================================================
-- 
-- USAGE: Convert to Laravel migrations using laravel_types_reference.md.
--        Tables are ordered by foreign key dependencies.
-- Extensions required: pgcrypto (for gen_random_uuid()), pg_trgm, btree_gin
-- ==========================================================================

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";  -- for full-text search indexes
CREATE EXTENSION IF NOT EXISTS "btree_gin"; -- for composite GIN indexes


-- ============================================================
-- CORE ENTITIES (referenced by all features)
-- ============================================================

CREATE TABLE organizations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    slug VARCHAR(100) NOT NULL UNIQUE,
    cr_number VARCHAR(50),
    vat_number VARCHAR(20),
    business_type VARCHAR(50),
    logo_url TEXT,
    country VARCHAR(5) DEFAULT 'SA',
    city VARCHAR(100),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE stores (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    slug VARCHAR(100) NOT NULL UNIQUE,
    branch_code VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    phone VARCHAR(20),
    email VARCHAR(255),
    timezone VARCHAR(50) DEFAULT 'Asia/Riyadh',
    currency VARCHAR(10) DEFAULT 'SAR',
    locale VARCHAR(10) DEFAULT 'ar',
    business_type VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    is_main_branch BOOLEAN DEFAULT FALSE,
    storage_used_mb INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_stores_organization ON stores (organization_id);
CREATE INDEX idx_stores_active ON stores (is_active);

CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id) ON DELETE CASCADE,
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    password_hash TEXT,
    pin_hash TEXT,
    role VARCHAR(50) DEFAULT 'cashier',
    locale VARCHAR(10) DEFAULT 'ar',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_users_store ON users (store_id);
CREATE INDEX idx_users_email ON users (email);

CREATE TABLE registers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    app_version VARCHAR(20),
    platform VARCHAR(20) DEFAULT 'windows',
    last_sync_at TIMESTAMP,
    is_online BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_registers_store ON registers (store_id);



-- ========================================================================
-- PLATFORM: Admin Users & Roles
-- ========================================================================

CREATE TABLE admin_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    avatar_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    two_factor_secret TEXT,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_confirmed_at TIMESTAMP,
    last_login_at TIMESTAMP,
    last_login_ip VARCHAR(45),
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    "group" VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_role_permissions (
    admin_role_id UUID NOT NULL REFERENCES admin_roles(id) ON DELETE CASCADE,
    admin_permission_id UUID NOT NULL REFERENCES admin_permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (admin_role_id, admin_permission_id)
);

CREATE TABLE admin_user_roles (
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    admin_role_id UUID NOT NULL REFERENCES admin_roles(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT NOW(),
    assigned_by UUID REFERENCES admin_users(id),
    PRIMARY KEY (admin_user_id, admin_role_id)
);

CREATE TABLE admin_activity_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    details JSONB,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- PLATFORM: System Configuration
-- ========================================================================

CREATE TABLE system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key VARCHAR(100) NOT NULL UNIQUE,
    value JSONB NOT NULL,
    "group" VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    updated_by UUID REFERENCES admin_users(id),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_system_settings_group ON system_settings ("group");

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

-- Seed defaults
INSERT INTO supported_locales (locale_code, language_name, language_name_native, direction, calendar_system, is_default) VALUES
('ar', 'Arabic', 'العربية', 'rtl', 'both', true),
('en', 'English', 'English', 'ltr', 'gregorian', false);

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

CREATE INDEX idx_master_translations_category ON master_translation_strings (category);

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

-- Seed default payment methods
INSERT INTO payment_methods (method_key, name, name_ar, category, requires_terminal, sort_order) VALUES
('cash', 'Cash', 'نقد', 'cash', false, 1),
('card_mada', 'Mada Card', 'بطاقة مدى', 'card', true, 2),
('card_visa', 'Visa', 'فيزا', 'card', true, 3),
('card_mastercard', 'Mastercard', 'ماستركارد', 'card', true, 4),
('store_credit', 'Store Credit', 'رصيد المتجر', 'credit', false, 5),
('gift_card', 'Gift Card', 'بطاقة هدية', 'credit', false, 6),
('mobile_payment', 'Mobile Payment', 'دفع بالجوال', 'digital', false, 7);

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

CREATE INDEX idx_certified_hardware_type ON certified_hardware (device_type, is_active);

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

CREATE INDEX idx_age_restricted_slug ON age_restricted_categories (category_slug);

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


-- ========================================================================
-- PLATFORM: Subscription Plans & Billing
-- ========================================================================

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
-- Single-row config table;


-- ========================================================================
-- PLATFORM: Content & Onboarding
-- ========================================================================

CREATE TABLE business_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE pos_layout_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id),
    layout_key VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    preview_image_url TEXT,
    config JSONB NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE platform_ui_defaults (
    key VARCHAR(50) PRIMARY KEY,
    value VARCHAR(100) NOT NULL
);
-- Seed: INSERT INTO platform_ui_defaults VALUES ('handedness','right'),('font_size','medium'),('theme','light_classic');

CREATE TABLE themes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    primary_color VARCHAR(7) NOT NULL,
    secondary_color VARCHAR(7) NOT NULL,
    background_color VARCHAR(7) NOT NULL,
    text_color VARCHAR(7) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE theme_package_visibility (
    theme_id UUID NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (theme_id, subscription_plan_id)
);

CREATE TABLE layout_package_visibility (
    pos_layout_template_id UUID NOT NULL REFERENCES pos_layout_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (pos_layout_template_id, subscription_plan_id)
);

CREATE TABLE receipt_layout_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    paper_width INT NOT NULL DEFAULT 80,
    header_config JSONB NOT NULL DEFAULT '{}',
    body_config JSONB NOT NULL DEFAULT '{}',
    footer_config JSONB NOT NULL DEFAULT '{}',
    zatca_qr_position VARCHAR(10) DEFAULT 'footer',
    show_bilingual BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE receipt_template_package_visibility (
    receipt_layout_template_id UUID NOT NULL REFERENCES receipt_layout_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (receipt_layout_template_id, subscription_plan_id)
);

CREATE TABLE cfd_themes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    background_color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    text_color VARCHAR(7) NOT NULL DEFAULT '#333333',
    accent_color VARCHAR(7) NOT NULL DEFAULT '#1A56A0',
    font_family VARCHAR(50) DEFAULT 'system',
    cart_layout VARCHAR(10) DEFAULT 'list',
    idle_layout VARCHAR(20) DEFAULT 'slideshow',
    animation_style VARCHAR(10) DEFAULT 'fade',
    transition_seconds INT DEFAULT 5,
    show_store_logo BOOLEAN DEFAULT TRUE,
    show_running_total BOOLEAN DEFAULT TRUE,
    thank_you_animation VARCHAR(15) DEFAULT 'check',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE cfd_theme_package_visibility (
    cfd_theme_id UUID NOT NULL REFERENCES cfd_themes(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (cfd_theme_id, subscription_plan_id)
);

CREATE TABLE signage_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    template_type VARCHAR(20) NOT NULL,
    layout_config JSONB NOT NULL DEFAULT '[]',
    placeholder_content JSONB DEFAULT '{}',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    text_color VARCHAR(7) DEFAULT '#333333',
    font_family VARCHAR(50) DEFAULT 'system',
    transition_style VARCHAR(10) DEFAULT 'fade',
    preview_image_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE signage_template_business_types (
    signage_template_id UUID NOT NULL REFERENCES signage_templates(id) ON DELETE CASCADE,
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    PRIMARY KEY (signage_template_id, business_type_id)
);

CREATE TABLE signage_template_package_visibility (
    signage_template_id UUID NOT NULL REFERENCES signage_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (signage_template_id, subscription_plan_id)
);

CREATE TABLE label_layout_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label_type VARCHAR(20) NOT NULL,
    label_width_mm INT NOT NULL,
    label_height_mm INT NOT NULL,
    barcode_type VARCHAR(15) DEFAULT 'CODE128',
    barcode_position JSONB,
    show_barcode_number BOOLEAN DEFAULT TRUE,
    field_layout JSONB NOT NULL DEFAULT '[]',
    font_family VARCHAR(50) DEFAULT 'system',
    default_font_size VARCHAR(10) DEFAULT 'small',
    show_border BOOLEAN DEFAULT FALSE,
    border_style VARCHAR(10) DEFAULT 'solid',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    preview_image_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE label_template_business_types (
    label_layout_template_id UUID NOT NULL REFERENCES label_layout_templates(id) ON DELETE CASCADE,
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    PRIMARY KEY (label_layout_template_id, business_type_id)
);

CREATE TABLE label_template_package_visibility (
    label_layout_template_id UUID NOT NULL REFERENCES label_layout_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (label_layout_template_id, subscription_plan_id)
);

CREATE TABLE business_type_category_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    category_name VARCHAR(100) NOT NULL,
    category_name_ar VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_category_templates_type ON business_type_category_templates (business_type_id, sort_order);

CREATE TABLE business_type_shift_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    days_of_week JSONB DEFAULT '[]',
    break_duration_minutes INT DEFAULT 30,
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_shift_templates_type ON business_type_shift_templates (business_type_id, sort_order);

CREATE TABLE business_type_receipt_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    paper_width INT DEFAULT 80,
    header_sections JSONB NOT NULL DEFAULT '["store_logo","store_name","store_address","store_phone","store_vat_number"]',
    body_sections JSONB NOT NULL DEFAULT '["items_table","subtotal","discount","vat","total","payment_method"]',
    footer_sections JSONB NOT NULL DEFAULT '["zatca_qr","receipt_number","cashier_name","thank_you_message"]',
    zatca_qr_position VARCHAR(10) DEFAULT 'footer',
    show_bilingual BOOLEAN DEFAULT TRUE,
    font_size VARCHAR(10) DEFAULT 'medium',
    custom_footer_text VARCHAR(200),
    custom_footer_text_ar VARCHAR(200)
);

CREATE TABLE business_type_industry_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    active_modules JSONB NOT NULL DEFAULT '[]',
    default_settings JSONB DEFAULT '{}',
    required_product_fields JSONB DEFAULT '[]'
);

CREATE TABLE business_type_promotion_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    promotion_type VARCHAR(20) NOT NULL,
    discount_value DECIMAL(10,2),
    applies_to VARCHAR(30) DEFAULT 'all_products',
    time_start TIME,
    time_end TIME,
    active_days JSONB,
    minimum_order DECIMAL(10,2) DEFAULT 0,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_promotion_templates_type ON business_type_promotion_templates (business_type_id);

CREATE TABLE business_type_commission_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    commission_type VARCHAR(30) NOT NULL,
    value DECIMAL(10,2),
    applies_to VARCHAR(30) DEFAULT 'all_sales',
    tier_thresholds JSONB,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_commission_templates_type ON business_type_commission_templates (business_type_id);

CREATE TABLE business_type_loyalty_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    program_type VARCHAR(20) NOT NULL DEFAULT 'points',
    earning_rate DECIMAL(8,4) DEFAULT 1.0,
    redemption_value DECIMAL(8,4) DEFAULT 0.01,
    min_redemption_points INT DEFAULT 100,
    stamps_card_size INT,
    cashback_percentage DECIMAL(5,2),
    points_expiry_days INT DEFAULT 0,
    enable_tiers BOOLEAN DEFAULT FALSE,
    tier_definitions JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE business_type_customer_group_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    credit_limit DECIMAL(12,2) DEFAULT 0,
    payment_terms_days INT DEFAULT 0,
    is_default_group BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_customer_group_templates_type ON business_type_customer_group_templates (business_type_id);

CREATE TABLE business_type_return_policies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    return_window_days INT NOT NULL DEFAULT 14,
    refund_methods JSONB DEFAULT '["original_payment"]',
    require_receipt BOOLEAN DEFAULT TRUE,
    restocking_fee_percentage DECIMAL(5,2) DEFAULT 0,
    void_grace_period_minutes INT DEFAULT 5,
    require_manager_approval BOOLEAN DEFAULT FALSE,
    max_return_without_approval DECIMAL(12,2) DEFAULT 0,
    return_reason_required BOOLEAN DEFAULT TRUE,
    partial_return_allowed BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE business_type_waste_reason_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    reason_code VARCHAR(30) NOT NULL,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    category VARCHAR(20) NOT NULL,
    description TEXT,
    requires_approval BOOLEAN DEFAULT FALSE,
    affects_cost_reporting BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    UNIQUE (business_type_id, reason_code)
);

CREATE INDEX idx_bt_waste_reason_templates_type ON business_type_waste_reason_templates (business_type_id);

CREATE TABLE business_type_appointment_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    default_slot_duration_minutes INT DEFAULT 30,
    min_advance_booking_hours INT DEFAULT 2,
    max_advance_booking_days INT DEFAULT 30,
    cancellation_window_hours INT DEFAULT 24,
    cancellation_fee_type VARCHAR(15) DEFAULT 'none',
    cancellation_fee_value DECIMAL(10,2) DEFAULT 0,
    allow_walkins BOOLEAN DEFAULT TRUE,
    overbooking_buffer_percentage DECIMAL(5,2) DEFAULT 0,
    require_deposit BOOLEAN DEFAULT FALSE,
    deposit_percentage DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE business_type_service_category_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    default_duration_minutes INT NOT NULL DEFAULT 30,
    default_price DECIMAL(10,2),
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_service_category_templates_type ON business_type_service_category_templates (business_type_id);

CREATE TABLE business_type_gift_registry_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10),
    default_expiry_days INT DEFAULT 90,
    allow_public_sharing BOOLEAN DEFAULT TRUE,
    allow_partial_fulfilment BOOLEAN DEFAULT TRUE,
    require_minimum_items BOOLEAN DEFAULT FALSE,
    minimum_items_count INT DEFAULT 0,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gift_registry_types_type ON business_type_gift_registry_types (business_type_id);

CREATE TABLE business_type_gamification_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    icon_url TEXT,
    trigger_type VARCHAR(30) NOT NULL,
    trigger_threshold INT NOT NULL DEFAULT 1,
    points_reward INT DEFAULT 0,
    description TEXT,
    description_ar TEXT,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gamification_badges_type ON business_type_gamification_badges (business_type_id);

CREATE TABLE business_type_gamification_challenges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    challenge_type VARCHAR(20) NOT NULL,
    target_value INT NOT NULL DEFAULT 1,
    reward_type VARCHAR(20) NOT NULL DEFAULT 'points',
    reward_value VARCHAR(50) NOT NULL DEFAULT '0',
    duration_days INT DEFAULT 30,
    is_recurring BOOLEAN DEFAULT FALSE,
    description TEXT,
    description_ar TEXT,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gamification_challenges_type ON business_type_gamification_challenges (business_type_id);

CREATE TABLE business_type_gamification_milestones (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    milestone_type VARCHAR(20) NOT NULL,
    threshold_value DECIMAL(12,2) NOT NULL,
    reward_type VARCHAR(20) NOT NULL DEFAULT 'points',
    reward_value VARCHAR(50) NOT NULL DEFAULT '0',
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gamification_milestones_type ON business_type_gamification_milestones (business_type_id);

CREATE TABLE onboarding_steps (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    step_number INT NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    title_ar VARCHAR(100) NOT NULL,
    description TEXT,
    description_ar TEXT,
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE knowledge_base_articles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(200) NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    delivery_platform_id UUID,
    is_published BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_kb_articles_published_category ON knowledge_base_articles (is_published, category);

CREATE TABLE pricing_page_content (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL UNIQUE REFERENCES subscription_plans(id),
    feature_bullet_list JSONB NOT NULL DEFAULT '[]',
    faq JSONB NOT NULL DEFAULT '[]',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- PLATFORM: Delivery Platform Registry
-- ========================================================================

CREATE TABLE delivery_platforms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    logo_url TEXT,
    auth_method VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE delivery_platform_fields (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id) ON DELETE CASCADE,
    field_label VARCHAR(100) NOT NULL,
    field_key VARCHAR(50) NOT NULL,
    field_type VARCHAR(20) NOT NULL DEFAULT 'text',
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    UNIQUE (delivery_platform_id, field_key)
);

CREATE TABLE delivery_platform_endpoints (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id) ON DELETE CASCADE,
    operation VARCHAR(50) NOT NULL,
    url_template TEXT NOT NULL,
    http_method VARCHAR(10) NOT NULL DEFAULT 'POST',
    request_mapping JSONB,
    UNIQUE (delivery_platform_id, operation)
);

CREATE TABLE delivery_platform_webhook_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id) ON DELETE CASCADE,
    path_template TEXT NOT NULL
);


-- ========================================================================
-- PLATFORM: Notification Templates
-- ========================================================================

CREATE TABLE notification_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    available_variables JSONB NOT NULL DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (event_key, channel)
);

CREATE TABLE notification_provider_status (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 1,
    is_healthy BOOLEAN DEFAULT TRUE,
    last_success_at TIMESTAMP,
    last_failure_at TIMESTAMP,
    failure_count_24h INT DEFAULT 0,
    success_count_24h INT DEFAULT 0,
    avg_latency_ms INT,
    disabled_reason TEXT,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (provider, channel)
);


-- ========================================================================
-- PLATFORM: App Update Management
-- ========================================================================

CREATE TABLE app_releases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version_number VARCHAR(20) NOT NULL,
    platform VARCHAR(10) NOT NULL,
    channel VARCHAR(10) NOT NULL DEFAULT 'stable',
    download_url TEXT NOT NULL,
    store_url TEXT,
    build_number VARCHAR(20),
    submission_status VARCHAR(20) DEFAULT 'not_applicable',
    release_notes TEXT,
    release_notes_ar TEXT,
    is_force_update BOOLEAN DEFAULT FALSE,
    min_supported_version VARCHAR(20),
    rollout_percentage INT NOT NULL DEFAULT 0 CHECK (rollout_percentage BETWEEN 0 AND 100),
    is_active BOOLEAN DEFAULT TRUE,
    released_at TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (platform, channel, version_number)
);

CREATE INDEX idx_app_releases_active ON app_releases (platform, channel, is_active);

CREATE TABLE app_update_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    app_release_id UUID NOT NULL REFERENCES app_releases(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_update_stats_release_status ON app_update_stats (app_release_id, status);
CREATE INDEX idx_update_stats_store ON app_update_stats (store_id);


-- ========================================================================
-- PLATFORM: Security & Audit
-- ========================================================================

CREATE TABLE admin_ip_allowlist (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    label VARCHAR(100),
    added_by UUID NOT NULL REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_ip_blocklist (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    blocked_by UUID NOT NULL REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_trusted_devices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    device_fingerprint VARCHAR(64) NOT NULL,
    device_name VARCHAR(100),
    user_agent TEXT,
    trusted_at TIMESTAMP DEFAULT NOW(),
    last_used_at TIMESTAMP,
    UNIQUE (admin_user_id, device_fingerprint)
);

CREATE TABLE admin_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    session_token_hash VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    two_fa_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    last_activity_at TIMESTAMP DEFAULT NOW(),
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP
);

CREATE INDEX idx_admin_sessions_user_revoked ON admin_sessions (admin_user_id, revoked_at);

CREATE TABLE security_alerts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id),
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    details JSONB,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    resolved_at TIMESTAMP,
    resolved_by UUID REFERENCES admin_users(id),
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_security_alerts_type_status ON security_alerts (alert_type, status, created_at);


-- ========================================================================
-- PLATFORM: Infrastructure & Operations
-- ========================================================================

-- Laravel's async queue failure log
CREATE TABLE failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_failed_jobs_failed_at ON failed_jobs (failed_at);

CREATE TABLE database_backups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    backup_type VARCHAR(20) NOT NULL,
    file_path TEXT NOT NULL,
    file_size_bytes BIGINT,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    error_message TEXT,
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE INDEX idx_database_backups_type_started ON database_backups (backup_type, started_at);


-- ========================================================================
-- PROVIDER CORE: Organizations & Stores
-- ========================================================================


-- ========================================================================
-- PROVIDER CORE: Subscription & Billing
-- ========================================================================

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


-- ========================================================================
-- PROVIDER CORE: Provider Registration
-- ========================================================================

CREATE TABLE provider_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_name VARCHAR(255) NOT NULL,
    organization_name_ar VARCHAR(255),
    owner_name VARCHAR(255) NOT NULL,
    owner_email VARCHAR(255) NOT NULL,
    owner_phone VARCHAR(50) NOT NULL,
    cr_number VARCHAR(50),
    vat_number VARCHAR(50),
    business_type_id UUID REFERENCES business_types(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by UUID REFERENCES admin_users(id),
    reviewed_at TIMESTAMP,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE provider_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    admin_user_id UUID NOT NULL REFERENCES admin_users(id),
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE provider_limit_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    limit_key VARCHAR(50) NOT NULL,
    override_value INT NOT NULL,
    reason TEXT,
    set_by UUID NOT NULL REFERENCES admin_users(id),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, limit_key)
);

CREATE TABLE cancellation_reasons (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    reason_category VARCHAR(30) NOT NULL,
    reason_text TEXT,
    cancelled_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- PROVIDER CORE: Provider Roles & Permissions
-- ========================================================================

CREATE TABLE provider_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL UNIQUE,
    "group" VARCHAR(30) NOT NULL,
    description VARCHAR(255),
    description_ar VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_provider_permissions_group ON provider_permissions ("group");

CREATE TABLE default_role_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL,
    name_ar VARCHAR(50),
    slug VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255),
    description_ar VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE default_role_template_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    default_role_template_id UUID NOT NULL REFERENCES default_role_templates(id) ON DELETE CASCADE,
    provider_permission_id UUID NOT NULL REFERENCES provider_permissions(id) ON DELETE CASCADE,
    UNIQUE (default_role_template_id, provider_permission_id)
);

CREATE TABLE custom_role_package_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL UNIQUE REFERENCES subscription_plans(id),
    is_custom_roles_enabled BOOLEAN DEFAULT FALSE,
    max_custom_roles INT NOT NULL DEFAULT 0
);

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(125) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(125) NOT NULL DEFAULT 'staff',
    is_predefined BOOLEAN DEFAULT FALSE,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, name, guard_name)
);

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(125) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    module VARCHAR(50) NOT NULL,
    guard_name VARCHAR(125) NOT NULL DEFAULT 'staff',
    requires_pin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE role_has_permissions (
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);

CREATE TABLE model_has_roles (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    model_type VARCHAR(255) NOT NULL,
    model_id UUID NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type)
);

CREATE TABLE pin_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    requesting_user_id UUID NOT NULL REFERENCES users(id),
    authorizing_user_id UUID NOT NULL REFERENCES users(id),
    permission_code VARCHAR(125) NOT NULL,
    action_context JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE role_audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_id UUID NOT NULL REFERENCES users(id),
    action VARCHAR(50) NOT NULL,
    role_id BIGINT REFERENCES roles(id),
    details JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- PROVIDER CORE: Staff & Attendance
-- ========================================================================

CREATE TABLE staff_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    photo_url VARCHAR(500),
    national_id VARCHAR(50),
    pin_hash VARCHAR(255) NOT NULL,
    nfc_badge_uid VARCHAR(50) UNIQUE,
    biometric_enabled BOOLEAN DEFAULT FALSE,
    employment_type VARCHAR(20) NOT NULL DEFAULT 'full_time',
    salary_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
    hourly_rate DECIMAL(10,2),
    hire_date DATE NOT NULL DEFAULT CURRENT_DATE,
    termination_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    language_preference VARCHAR(5) DEFAULT 'ar',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE staff_branch_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    branch_id UUID NOT NULL REFERENCES stores(id),
    role_id BIGINT NOT NULL REFERENCES roles(id),
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(staff_user_id, branch_id)
);

CREATE TABLE attendance_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    clock_in_at TIMESTAMP NOT NULL,
    clock_out_at TIMESTAMP,
    break_minutes INTEGER DEFAULT 0,
    scheduled_shift_id UUID,
    overtime_minutes INTEGER DEFAULT 0,
    notes TEXT,
    auth_method VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE break_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    attendance_record_id UUID NOT NULL REFERENCES attendance_records(id) ON DELETE CASCADE,
    break_start TIMESTAMP NOT NULL,
    break_end TIMESTAMP
);

CREATE TABLE shift_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    color VARCHAR(7) DEFAULT '#4CAF50',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE shift_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    shift_template_id UUID NOT NULL REFERENCES shift_templates(id),
    date DATE NOT NULL,
    actual_start TIMESTAMP,
    actual_end TIMESTAMP,
    status VARCHAR(20) DEFAULT 'scheduled',
    swapped_with_id UUID REFERENCES staff_users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(staff_user_id, date, shift_template_id)
);

CREATE TABLE commission_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    staff_user_id UUID REFERENCES staff_users(id),
    type VARCHAR(20) NOT NULL DEFAULT 'flat_percentage',
    percentage DECIMAL(5,2),
    tiers_json JSONB,
    product_category_id UUID,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE commission_earnings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    order_id UUID NOT NULL,
    commission_rule_id UUID NOT NULL REFERENCES commission_rules(id),
    order_total DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE staff_activity_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    details JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE training_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    ended_at TIMESTAMP,
    transactions_count INTEGER DEFAULT 0,
    notes TEXT
);

CREATE TABLE staff_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    document_type VARCHAR(50) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    expiry_date DATE,
    uploaded_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- PROVIDER CORE: Security
-- ========================================================================

CREATE TABLE device_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    device_name VARCHAR(100) NOT NULL,
    hardware_id VARCHAR(200) NOT NULL,
    os_info VARCHAR(100),
    app_version VARCHAR(20),
    last_active_at TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    remote_wipe_requested BOOLEAN DEFAULT false,
    registered_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, hardware_id)
);

CREATE TABLE security_audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_id UUID,
    user_type VARCHAR(20),
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50),
    resource_id UUID,
    details JSONB DEFAULT '{}',
    severity VARCHAR(10) DEFAULT 'info',
    ip_address VARCHAR(45),
    device_id UUID REFERENCES device_registrations(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE login_attempts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_identifier VARCHAR(100) NOT NULL,
    attempt_type VARCHAR(20) NOT NULL,
    is_successful BOOLEAN NOT NULL,
    ip_address VARCHAR(45),
    device_id UUID,
    attempted_at TIMESTAMP DEFAULT NOW()
);

-- Server-side mirror of local POS security policy config (synced from device)
CREATE TABLE security_policies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE UNIQUE,
    pin_min_length INT DEFAULT 4,
    pin_max_length INT DEFAULT 6,
    auto_lock_seconds INT DEFAULT 120,
    max_failed_attempts INT DEFAULT 5,
    lockout_duration_minutes INT DEFAULT 15,
    require_2fa_owner BOOLEAN DEFAULT TRUE,
    session_max_hours INT DEFAULT 12,
    require_pin_override_void BOOLEAN DEFAULT TRUE,
    require_pin_override_return BOOLEAN DEFAULT TRUE,
    require_pin_override_discount BOOLEAN DEFAULT TRUE,
    discount_override_threshold DECIMAL(5,2) DEFAULT 20.0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- PROVIDER CORE: User Preferences & Localization
-- ========================================================================

CREATE TABLE user_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    pos_handedness VARCHAR(10),
    font_size VARCHAR(15),
    theme VARCHAR(50),
    pos_layout_id UUID REFERENCES pos_layout_templates(id)
);

CREATE TABLE translation_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    string_key VARCHAR(200) NOT NULL,
    locale VARCHAR(5) NOT NULL,
    custom_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, string_key, locale)
);


-- ========================================================================
-- CATALOG: Categories & Products
-- ========================================================================

CREATE TABLE categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    parent_id UUID REFERENCES categories(id),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    image_url TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    category_id UUID REFERENCES categories(id),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    description TEXT,
    description_ar TEXT,
    sku VARCHAR(100),
    barcode VARCHAR(50),
    sell_price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'piece',
    tax_rate DECIMAL(5,2) DEFAULT 15.00,
    is_weighable BOOLEAN DEFAULT FALSE,
    tare_weight DECIMAL(8,3) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_combo BOOLEAN DEFAULT FALSE,
    age_restricted BOOLEAN DEFAULT FALSE,
    image_url TEXT,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE TABLE product_barcodes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    barcode VARCHAR(50) NOT NULL UNIQUE,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE store_prices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    sell_price DECIMAL(12,2) NOT NULL,
    valid_from DATE,
    valid_to DATE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, product_id)
);

CREATE TABLE product_variant_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE product_variants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    variant_group_id UUID NOT NULL REFERENCES product_variant_groups(id),
    variant_value VARCHAR(100) NOT NULL,
    variant_value_ar VARCHAR(100),
    sku VARCHAR(100),
    barcode VARCHAR(50),
    price_adjustment DECIMAL(12,2) DEFAULT 0,
    image_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE product_images (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    image_url TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE combo_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    combo_price DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE combo_product_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    combo_product_id UUID NOT NULL REFERENCES combo_products(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
    is_optional BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE modifier_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    is_required BOOLEAN DEFAULT FALSE,
    min_select INT DEFAULT 0,
    max_select INT DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE modifier_options (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    modifier_group_id UUID NOT NULL REFERENCES modifier_groups(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    price_adjustment DECIMAL(12,2) DEFAULT 0,
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE suppliers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE product_suppliers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    supplier_id UUID NOT NULL REFERENCES suppliers(id) ON DELETE CASCADE,
    cost_price DECIMAL(12,2),
    lead_time_days INT,
    supplier_sku VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (product_id, supplier_id)
);

CREATE TABLE internal_barcode_sequence (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    last_sequence INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- CATALOG: Inventory
-- ========================================================================

CREATE TABLE stock_levels (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    reserved_quantity DECIMAL(12,3) DEFAULT 0,
    reorder_point DECIMAL(12,3),
    max_stock_level DECIMAL(12,3),
    average_cost DECIMAL(12,4) DEFAULT 0,
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, product_id)
);

CREATE TABLE stock_movements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    type VARCHAR(30) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4),
    reference_type VARCHAR(50),
    reference_id UUID,
    reason VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE goods_receipts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    supplier_id UUID REFERENCES suppliers(id),
    purchase_order_id UUID,
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'draft',
    total_cost DECIMAL(14,2) DEFAULT 0,
    notes TEXT,
    received_by UUID NOT NULL REFERENCES users(id),
    received_at TIMESTAMP DEFAULT NOW(),
    confirmed_at TIMESTAMP
);

CREATE TABLE goods_receipt_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    goods_receipt_id UUID NOT NULL REFERENCES goods_receipts(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    batch_number VARCHAR(100),
    expiry_date DATE
);

CREATE TABLE stock_adjustments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    type VARCHAR(20) NOT NULL,
    reason_code VARCHAR(50) NOT NULL,
    notes TEXT,
    adjusted_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE stock_adjustment_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    stock_adjustment_id UUID NOT NULL REFERENCES stock_adjustments(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4)
);

CREATE TABLE stock_transfers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    from_store_id UUID NOT NULL REFERENCES stores(id),
    to_store_id UUID NOT NULL REFERENCES stores(id),
    status VARCHAR(20) DEFAULT 'pending',
    reference_number VARCHAR(50) UNIQUE,
    notes TEXT,
    created_by UUID NOT NULL REFERENCES users(id),
    approved_by UUID REFERENCES users(id),
    received_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    approved_at TIMESTAMP,
    received_at TIMESTAMP
);

CREATE TABLE stock_transfer_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    stock_transfer_id UUID NOT NULL REFERENCES stock_transfers(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity_sent DECIMAL(12,3) NOT NULL,
    quantity_received DECIMAL(12,3)
);

CREATE TABLE purchase_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    supplier_id UUID NOT NULL REFERENCES suppliers(id),
    reference_number VARCHAR(50) UNIQUE,
    status VARCHAR(20) DEFAULT 'draft',
    expected_date DATE,
    total_cost DECIMAL(14,2) DEFAULT 0,
    notes TEXT,
    created_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE purchase_order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    purchase_order_id UUID NOT NULL REFERENCES purchase_orders(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity_ordered DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    quantity_received DECIMAL(12,3) DEFAULT 0
);

CREATE TABLE stock_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    batch_number VARCHAR(100),
    expiry_date DATE,
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4),
    goods_receipt_id UUID REFERENCES goods_receipts(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE recipes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    product_id UUID NOT NULL REFERENCES products(id),
    yield_quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE recipe_ingredients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    recipe_id UUID NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    ingredient_product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit VARCHAR(20) DEFAULT 'piece',
    waste_percent DECIMAL(5,2) DEFAULT 0
);


-- ========================================================================
-- CATALOG: Promotions & Coupons
-- ========================================================================

CREATE TABLE promotions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(30) NOT NULL,
    discount_value DECIMAL(12,2),
    buy_quantity INT,
    get_quantity INT,
    get_discount_percent DECIMAL(5,2),
    bundle_price DECIMAL(12,2),
    min_order_total DECIMAL(12,2),
    min_item_quantity INT,
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    active_days JSONB DEFAULT '[]',
    active_time_from TIME,
    active_time_to TIME,
    max_uses INT,
    max_uses_per_customer INT,
    is_stackable BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    is_coupon BOOLEAN DEFAULT FALSE,
    usage_count INT DEFAULT 0,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE promotion_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE (promotion_id, product_id)
);

CREATE TABLE promotion_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    category_id UUID NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE (promotion_id, category_id)
);

CREATE TABLE promotion_customer_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    customer_group_id UUID NOT NULL,
    UNIQUE (promotion_id, customer_group_id)
);

CREATE TABLE coupon_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    code VARCHAR(30) NOT NULL UNIQUE,
    max_uses INT DEFAULT 1,
    usage_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE promotion_usage_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id),
    coupon_code_id UUID REFERENCES coupon_codes(id),
    order_id UUID NOT NULL,
    customer_id UUID,
    discount_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE bundle_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity INT DEFAULT 1
);


-- ========================================================================
-- CUSTOMERS: Core
-- ========================================================================

CREATE TABLE customers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255),
    address TEXT,
    date_of_birth DATE,
    loyalty_code VARCHAR(20) UNIQUE,
    loyalty_points INT DEFAULT 0,
    store_credit_balance DECIMAL(12,2) DEFAULT 0,
    group_id UUID,
    tax_registration_number VARCHAR(50),
    notes TEXT,
    total_spend DECIMAL(14,2) DEFAULT 0,
    visit_count INT DEFAULT 0,
    last_visit_at TIMESTAMP,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE TABLE customer_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(100) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE loyalty_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    type VARCHAR(20) NOT NULL,
    points INT NOT NULL,
    balance_after INT NOT NULL,
    order_id UUID,
    notes VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE store_credit_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    order_id UUID,
    payment_id UUID,
    notes VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE loyalty_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL UNIQUE REFERENCES organizations(id),
    points_per_sar DECIMAL(5,2) DEFAULT 1,
    sar_per_point DECIMAL(8,4) DEFAULT 0.01,
    min_redemption_points INT DEFAULT 100,
    points_expiry_months INT DEFAULT 0,
    excluded_category_ids JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE digital_receipt_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL,
    customer_id UUID NOT NULL,
    channel VARCHAR(20) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'sent',
    sent_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- CUSTOMERS: Nice-to-Have Features
-- ========================================================================

CREATE TABLE cfd_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    is_enabled BOOLEAN DEFAULT false,
    target_monitor INTEGER DEFAULT 1,
    theme_config JSONB DEFAULT '{}',
    idle_content JSONB DEFAULT '[]',
    idle_rotation_seconds INTEGER DEFAULT 10,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE signage_playlists (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(100) NOT NULL,
    slides JSONB NOT NULL,
    schedule JSONB,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE appointments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    staff_id UUID REFERENCES staff_users(id),
    service_product_id UUID NOT NULL REFERENCES products(id),
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(20) DEFAULT 'scheduled',
    notes TEXT,
    reminder_sent BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_registries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    name VARCHAR(100) NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    event_date DATE,
    share_code VARCHAR(20) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_registry_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    registry_id UUID NOT NULL REFERENCES gift_registries(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity_desired INTEGER DEFAULT 1,
    quantity_purchased INTEGER DEFAULT 0,
    purchased_by_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE wishlists (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    product_id UUID NOT NULL REFERENCES products(id),
    added_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, product_id)
);

CREATE TABLE loyalty_challenges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    challenge_type VARCHAR(30) NOT NULL,
    target_value DECIMAL(12,2) NOT NULL,
    reward_type VARCHAR(20) NOT NULL,
    reward_value DECIMAL(12,2),
    reward_badge_id UUID,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE loyalty_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name_ar VARCHAR(50) NOT NULL,
    name_en VARCHAR(50) NOT NULL,
    icon_url VARCHAR(500),
    description_ar TEXT,
    description_en TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Add FK after both tables exist
ALTER TABLE loyalty_challenges
    ADD CONSTRAINT fk_challenge_badge
    FOREIGN KEY (reward_badge_id) REFERENCES loyalty_badges(id);

CREATE TABLE loyalty_tiers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    tier_name_ar VARCHAR(50) NOT NULL,
    tier_name_en VARCHAR(50) NOT NULL,
    tier_order INTEGER NOT NULL,
    min_points INTEGER NOT NULL,
    benefits JSONB DEFAULT '{}',
    icon_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, tier_order)
);

CREATE TABLE customer_challenge_progress (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    challenge_id UUID NOT NULL REFERENCES loyalty_challenges(id),
    current_value DECIMAL(12,2) DEFAULT 0,
    is_completed BOOLEAN DEFAULT false,
    completed_at TIMESTAMP,
    reward_claimed BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, challenge_id)
);

CREATE TABLE customer_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    badge_id UUID NOT NULL REFERENCES loyalty_badges(id),
    earned_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, badge_id)
);


-- ========================================================================
-- POS TERMINAL: Sessions & Transactions
-- ========================================================================

CREATE TABLE pos_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    opening_cash DECIMAL(12,2) NOT NULL,
    closing_cash DECIMAL(12,2),
    expected_cash DECIMAL(12,2),
    cash_difference DECIMAL(12,2),
    total_cash_sales DECIMAL(12,2) DEFAULT 0,
    total_card_sales DECIMAL(12,2) DEFAULT 0,
    total_other_sales DECIMAL(12,2) DEFAULT 0,
    total_refunds DECIMAL(12,2) DEFAULT 0,
    total_voids DECIMAL(12,2) DEFAULT 0,
    transaction_count INT DEFAULT 0,
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    z_report_printed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    pos_session_id UUID NOT NULL REFERENCES pos_sessions(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    customer_id UUID REFERENCES customers(id),
    transaction_number VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'sale',
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL,
    tip_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    is_tax_exempt BOOLEAN DEFAULT FALSE,
    return_transaction_id UUID REFERENCES transactions(id),
    external_type VARCHAR(30),
    external_id VARCHAR(100),
    notes TEXT,
    zatca_uuid UUID UNIQUE,
    zatca_hash TEXT,
    zatca_qr_code TEXT,
    zatca_status VARCHAR(20) DEFAULT 'pending',
    sync_status VARCHAR(20) DEFAULT 'pending',
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE TABLE transaction_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    barcode VARCHAR(50),
    product_name VARCHAR(255) NOT NULL,
    product_name_ar VARCHAR(255),
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2),
    discount_amount DECIMAL(12,2) DEFAULT 0,
    discount_type VARCHAR(20),
    discount_value DECIMAL(12,2),
    tax_rate DECIMAL(5,2) DEFAULT 15.00,
    tax_amount DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    serial_number VARCHAR(100),
    batch_number VARCHAR(100),
    expiry_date DATE,
    modifier_selections JSONB,
    notes TEXT,
    is_return_item BOOLEAN DEFAULT FALSE,
    age_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE held_carts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    customer_id UUID REFERENCES customers(id),
    cart_data JSONB NOT NULL,
    label VARCHAR(100),
    held_at TIMESTAMP DEFAULT NOW(),
    recalled_at TIMESTAMP,
    recalled_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE exchange_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_transaction_id UUID NOT NULL REFERENCES transactions(id),
    sale_transaction_id UUID NOT NULL REFERENCES transactions(id),
    net_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tax_exemptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(id),
    exemption_type VARCHAR(30) NOT NULL,
    customer_tax_id VARCHAR(50),
    certificate_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- POS TERMINAL: Customization
-- ========================================================================

CREATE TABLE pos_customization_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    theme VARCHAR(20) DEFAULT 'light',
    primary_color VARCHAR(7) DEFAULT '#1976D2',
    secondary_color VARCHAR(7),
    accent_color VARCHAR(7),
    font_scale DECIMAL(3,2) DEFAULT 1.00,
    handedness VARCHAR(10) DEFAULT 'right',
    grid_columns INT DEFAULT 4,
    show_product_images BOOLEAN DEFAULT TRUE,
    show_price_on_grid BOOLEAN DEFAULT TRUE,
    cart_display_mode VARCHAR(20) DEFAULT 'detailed',
    layout_direction VARCHAR(5) DEFAULT 'auto',
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE receipt_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    logo_url TEXT,
    header_line_1 VARCHAR(255),
    header_line_2 VARCHAR(255),
    footer_text TEXT,
    show_vat_number BOOLEAN DEFAULT TRUE,
    show_loyalty_points BOOLEAN DEFAULT TRUE,
    show_barcode BOOLEAN DEFAULT TRUE,
    paper_width_mm INT DEFAULT 80,
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE quick_access_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    grid_rows INT DEFAULT 4,
    grid_cols INT DEFAULT 5,
    buttons_json JSONB NOT NULL DEFAULT '[]',
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- ORDERS: Order Management
-- ========================================================================

CREATE TABLE orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    transaction_id UUID REFERENCES transactions(id),
    customer_id UUID REFERENCES customers(id),
    order_number VARCHAR(50) NOT NULL,
    source VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    notes TEXT,
    customer_notes TEXT,
    external_order_id VARCHAR(100),
    delivery_address TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, order_number)
);

CREATE TABLE order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    variant_id UUID REFERENCES product_variants(id),
    product_name VARCHAR(255) NOT NULL,
    product_name_ar VARCHAR(255),
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    notes TEXT
);

CREATE TABLE order_item_modifiers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_item_id UUID NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,
    modifier_option_id UUID REFERENCES modifier_options(id),
    modifier_name VARCHAR(255) NOT NULL,
    modifier_name_ar VARCHAR(255),
    price_adjustment DECIMAL(12,2) DEFAULT 0
);

CREATE TABLE order_status_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    from_status VARCHAR(30),
    to_status VARCHAR(30) NOT NULL,
    changed_by UUID REFERENCES users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE returns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    return_number VARCHAR(50) NOT NULL,
    type VARCHAR(20) NOT NULL,
    reason_code VARCHAR(50) NOT NULL,
    refund_method VARCHAR(30) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    total_refund DECIMAL(12,2) NOT NULL,
    notes TEXT,
    processed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, return_number)
);

CREATE TABLE return_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_id UUID NOT NULL REFERENCES returns(id) ON DELETE CASCADE,
    order_item_id UUID NOT NULL REFERENCES order_items(id),
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    refund_amount DECIMAL(12,2) NOT NULL,
    restore_stock BOOLEAN DEFAULT TRUE
);

CREATE TABLE exchanges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    original_order_id UUID NOT NULL REFERENCES orders(id),
    return_id UUID NOT NULL REFERENCES returns(id),
    new_order_id UUID NOT NULL REFERENCES orders(id),
    net_amount DECIMAL(12,2) NOT NULL,
    processed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE order_delivery_info (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
    platform VARCHAR(50) NOT NULL,
    driver_name VARCHAR(255),
    driver_phone VARCHAR(50),
    estimated_delivery TIMESTAMP,
    actual_delivery TIMESTAMP,
    delivery_fee DECIMAL(12,2) DEFAULT 0,
    tracking_url TEXT
);

CREATE TABLE pending_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    items_json JSONB NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    notes TEXT,
    created_by UUID NOT NULL REFERENCES users(id),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- ORDERS: Payments & Finance
-- ========================================================================

CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    cash_tendered DECIMAL(12,2),
    change_given DECIMAL(12,2),
    tip_amount DECIMAL(12,2) DEFAULT 0,
    card_brand VARCHAR(30),
    card_last_four VARCHAR(4),
    card_auth_code VARCHAR(50),
    card_reference VARCHAR(100),
    gift_card_code VARCHAR(50),
    coupon_code VARCHAR(50),
    loyalty_points_used INT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE cash_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID,
    opened_by UUID NOT NULL REFERENCES users(id),
    closed_by UUID REFERENCES users(id),
    opening_float DECIMAL(12,2) NOT NULL,
    expected_cash DECIMAL(12,2),
    actual_cash DECIMAL(12,2),
    variance DECIMAL(12,2),
    status VARCHAR(20) DEFAULT 'open',
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    close_notes TEXT
);

CREATE TABLE cash_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cash_session_id UUID NOT NULL REFERENCES cash_sessions(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    notes TEXT,
    performed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    cash_session_id UUID REFERENCES cash_sessions(id),
    amount DECIMAL(12,2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    receipt_image_url TEXT,
    recorded_by UUID NOT NULL REFERENCES users(id),
    expense_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    code VARCHAR(20) NOT NULL UNIQUE,
    barcode VARCHAR(50),
    initial_amount DECIMAL(12,2) NOT NULL,
    balance DECIMAL(12,2) NOT NULL,
    recipient_name VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active',
    issued_by UUID NOT NULL REFERENCES users(id),
    issued_at_store UUID NOT NULL REFERENCES stores(id),
    expires_at DATE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_card_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gift_card_id UUID NOT NULL REFERENCES gift_cards(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    payment_id UUID REFERENCES payments(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    performed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE refunds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_id UUID NOT NULL REFERENCES returns(id),
    payment_id UUID REFERENCES payments(id),
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'completed',
    processed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- INTEGRATIONS: Delivery Platforms
-- ========================================================================

CREATE TABLE store_delivery_platforms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id),
    credentials JSONB NOT NULL DEFAULT '{}',
    inbound_api_key VARCHAR(48) UNIQUE,
    is_enabled BOOLEAN DEFAULT FALSE,
    sync_status VARCHAR(10) DEFAULT 'pending',
    last_sync_at TIMESTAMP,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, delivery_platform_id)
);

CREATE TABLE delivery_platform_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    platform VARCHAR(50) NOT NULL,
    api_key TEXT NOT NULL,
    merchant_id VARCHAR(100),
    webhook_secret TEXT,
    branch_id_on_platform VARCHAR(100),
    is_enabled BOOLEAN DEFAULT FALSE,
    auto_accept BOOLEAN DEFAULT TRUE,
    throttle_limit INT,
    last_menu_sync_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, platform)
);

CREATE TABLE delivery_order_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id),
    platform VARCHAR(50) NOT NULL,
    external_order_id VARCHAR(100) NOT NULL,
    external_status VARCHAR(50),
    commission_amount DECIMAL(12,2) DEFAULT 0,
    commission_percent DECIMAL(5,2),
    raw_payload JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE delivery_menu_sync_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    platform VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    items_synced INT DEFAULT 0,
    items_failed INT DEFAULT 0,
    error_details JSONB,
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

-- Platform-level delivery app registry (managed by Thawani admin)
CREATE TABLE platform_delivery_integrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    platform_slug VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    display_name_ar VARCHAR(100),
    api_base_url TEXT NOT NULL,
    client_id TEXT,
    client_secret_encrypted TEXT,
    webhook_secret_encrypted TEXT,
    default_commission_percent DECIMAL(5,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT FALSE,
    supported_countries JSONB DEFAULT '["SA"]',
    logo_url TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Per-store enrollment in a platform-managed delivery integration
CREATE TABLE store_delivery_platform_enrollments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    platform_slug VARCHAR(50) NOT NULL REFERENCES platform_delivery_integrations(platform_slug) ON DELETE CASCADE,
    merchant_id_on_platform VARCHAR(100),
    is_enabled BOOLEAN DEFAULT FALSE,
    auto_accept BOOLEAN DEFAULT TRUE,
    commission_override DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, platform_slug)
);
CREATE INDEX idx_store_dlv_enrollments_store ON store_delivery_platform_enrollments (store_id);


-- ========================================================================
-- INTEGRATIONS: Accounting
-- ========================================================================

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

CREATE INDEX idx_accounting_exports_store_date ON accounting_exports (store_id, created_at DESC);
CREATE INDEX idx_accounting_exports_status ON accounting_exports (status) WHERE status IN ('pending', 'processing');

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

CREATE INDEX idx_auto_export_next_run ON auto_export_configs (next_run_at) WHERE enabled = TRUE;


-- ========================================================================
-- INTEGRATIONS: Thawani Marketplace
-- ========================================================================

CREATE TABLE thawani_store_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    thawani_store_id VARCHAR(100) NOT NULL,
    is_connected BOOLEAN DEFAULT FALSE,
    auto_sync_products BOOLEAN DEFAULT TRUE,
    auto_sync_inventory BOOLEAN DEFAULT TRUE,
    auto_accept_orders BOOLEAN DEFAULT FALSE,
    operating_hours_json JSONB,
    commission_rate DECIMAL(5,2),
    connected_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE thawani_product_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    thawani_product_id VARCHAR(100) NOT NULL,
    is_published BOOLEAN DEFAULT TRUE,
    online_price DECIMAL(12,3),
    display_order INTEGER DEFAULT 0,
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, product_id)
);

CREATE TABLE thawani_order_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID REFERENCES orders(id),
    thawani_order_id VARCHAR(100) NOT NULL,
    thawani_order_number VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    delivery_type VARCHAR(20) NOT NULL DEFAULT 'delivery',
    customer_name VARCHAR(200),
    customer_phone VARCHAR(20),
    delivery_address TEXT,
    order_total DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3),
    rejection_reason TEXT,
    accepted_at TIMESTAMP,
    prepared_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE thawani_settlements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    settlement_date DATE NOT NULL,
    gross_amount DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3) NOT NULL,
    net_amount DECIMAL(12,3) NOT NULL,
    order_count INTEGER NOT NULL,
    thawani_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, settlement_date, thawani_reference)
);


-- ========================================================================
-- INTEGRATIONS: ZATCA Compliance
-- ========================================================================

CREATE TABLE zatca_invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    invoice_number VARCHAR(50) NOT NULL,
    invoice_type VARCHAR(20) NOT NULL,
    invoice_xml TEXT NOT NULL,
    invoice_hash VARCHAR(64) NOT NULL,
    previous_invoice_hash VARCHAR(64) NOT NULL,
    digital_signature TEXT NOT NULL,
    qr_code_data TEXT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    vat_amount DECIMAL(12,2) NOT NULL,
    submission_status VARCHAR(20) DEFAULT 'pending',
    zatca_response_code VARCHAR(10),
    zatca_response_message TEXT,
    submitted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE zatca_certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    certificate_type VARCHAR(20) NOT NULL,
    certificate_pem TEXT NOT NULL,
    ccsid VARCHAR(100) NOT NULL,
    issued_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- NOTIFICATIONS: Provider-Side
-- ========================================================================

CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id UUID NOT NULL,
    data JSONB NOT NULL,
    read_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX notifications_notifiable ON notifications (notifiable_type, notifiable_id);
CREATE INDEX notifications_read_at ON notifications (read_at);

CREATE TABLE notification_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    UNIQUE (user_id, event_key, channel)
);

CREATE TABLE fcm_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    token TEXT NOT NULL,
    device_type VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE notification_events_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    notification_id UUID NOT NULL REFERENCES notifications(id),
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT NOW()
);

-- Full audit log of every notification delivery attempt with provider & fallback tracking
CREATE TABLE notification_delivery_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    notification_id UUID REFERENCES notifications(id) ON DELETE SET NULL,
    channel VARCHAR(20) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    provider_message_id VARCHAR(100),
    error_message TEXT,
    latency_ms INT,
    is_fallback BOOLEAN DEFAULT FALSE,
    attempted_providers JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_notif_delivery_logs_channel ON notification_delivery_logs (channel, status);
CREATE INDEX idx_notif_delivery_logs_provider ON notification_delivery_logs (provider, created_at);


-- ========================================================================
-- PLATFORM: Announcements
-- ========================================================================

CREATE TABLE platform_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type VARCHAR(20) NOT NULL,
    title VARCHAR(200) NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    target_filter JSONB NOT NULL DEFAULT '{"scope":"all"}',
    display_start_at TIMESTAMP NOT NULL,
    display_end_at TIMESTAMP NOT NULL,
    is_banner BOOLEAN DEFAULT FALSE,
    send_push BOOLEAN DEFAULT FALSE,
    send_email BOOLEAN DEFAULT FALSE,
    created_by UUID NOT NULL REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_announcements_display ON platform_announcements (display_start_at, display_end_at);
CREATE INDEX idx_announcements_type ON platform_announcements (type);

CREATE TABLE platform_announcement_dismissals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    announcement_id UUID NOT NULL REFERENCES platform_announcements(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id),
    dismissed_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (announcement_id, store_id)
);

CREATE TABLE payment_reminders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    reminder_type VARCHAR(20) NOT NULL,
    channel VARCHAR(10) NOT NULL,
    sent_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_payment_reminders_sub_type ON payment_reminders (store_subscription_id, reminder_type);
CREATE INDEX idx_payment_reminders_sent ON payment_reminders (sent_at);


-- ========================================================================
-- REPORTS: Provider Analytics
-- ========================================================================

CREATE TABLE product_sales_summary (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    date DATE NOT NULL,
    quantity_sold DECIMAL(12,3) DEFAULT 0,
    revenue DECIMAL(14,2) DEFAULT 0,
    cost DECIMAL(14,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    return_quantity DECIMAL(12,3) DEFAULT 0,
    return_amount DECIMAL(12,2) DEFAULT 0,
    UNIQUE (store_id, product_id, date)
);

CREATE TABLE daily_sales_summary (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    date DATE NOT NULL,
    total_transactions INT DEFAULT 0,
    total_revenue DECIMAL(14,2) DEFAULT 0,
    total_cost DECIMAL(14,2) DEFAULT 0,
    total_discount DECIMAL(12,2) DEFAULT 0,
    total_tax DECIMAL(12,2) DEFAULT 0,
    total_refunds DECIMAL(12,2) DEFAULT 0,
    net_revenue DECIMAL(14,2) DEFAULT 0,
    cash_revenue DECIMAL(14,2) DEFAULT 0,
    card_revenue DECIMAL(14,2) DEFAULT 0,
    other_revenue DECIMAL(14,2) DEFAULT 0,
    avg_basket_size DECIMAL(12,2) DEFAULT 0,
    unique_customers INT DEFAULT 0,
    UNIQUE (store_id, date)
);


-- ========================================================================
-- REPORTS: Platform Analytics
-- ========================================================================

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


-- ========================================================================
-- HARDWARE: Configuration
-- ========================================================================

CREATE TABLE hardware_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    device_type VARCHAR(30) NOT NULL,
    connection_type VARCHAR(20) NOT NULL,
    device_name VARCHAR(100),
    config_json JSONB NOT NULL DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, terminal_id, device_type)
);

CREATE TABLE hardware_event_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    device_type VARCHAR(30) NOT NULL,
    event VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE hardware_sales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    sold_by UUID NOT NULL REFERENCES admin_users(id),
    item_type VARCHAR(50) NOT NULL,
    item_description VARCHAR(255),
    serial_number VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    notes TEXT,
    sold_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE implementation_fees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    fee_type VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'invoiced',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- OPERATIONS: Backup & Sync
-- ========================================================================

CREATE TABLE backup_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    backup_type VARCHAR(20) NOT NULL,
    storage_location VARCHAR(20) NOT NULL,
    local_path TEXT,
    cloud_key VARCHAR(500),
    file_size_bytes BIGINT NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    db_version INTEGER NOT NULL,
    records_count INTEGER,
    is_verified BOOLEAN DEFAULT FALSE,
    is_encrypted BOOLEAN DEFAULT TRUE,
    status VARCHAR(20) DEFAULT 'completed',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE update_rollouts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version VARCHAR(20) NOT NULL,
    rollout_percentage INTEGER NOT NULL DEFAULT 0,
    is_critical BOOLEAN DEFAULT FALSE,
    target_stores JSONB,
    pinned_stores JSONB,
    release_notes TEXT NOT NULL,
    released_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE sync_conflicts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_name VARCHAR(100) NOT NULL,
    record_id UUID NOT NULL,
    local_data JSONB NOT NULL,
    cloud_data JSONB NOT NULL,
    resolution VARCHAR(20),
    resolved_by UUID REFERENCES users(id),
    detected_at TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP
);

CREATE TABLE sync_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    direction VARCHAR(10) NOT NULL,
    records_count INTEGER NOT NULL DEFAULT 0,
    duration_ms INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    error_message TEXT,
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMP
);


-- ========================================================================
-- SUPPORT: Tickets & Help
-- ========================================================================

CREATE TABLE support_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    organization_id UUID NOT NULL REFERENCES organizations(id),
    store_id UUID REFERENCES stores(id),
    user_id UUID REFERENCES users(id),
    assigned_to UUID REFERENCES admin_users(id),
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(10) NOT NULL DEFAULT 'medium',
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    sla_deadline_at TIMESTAMP,
    first_response_at TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE support_ticket_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    support_ticket_id UUID NOT NULL REFERENCES support_tickets(id) ON DELETE CASCADE,
    sender_type VARCHAR(10) NOT NULL,
    sender_id UUID NOT NULL,
    message_text TEXT NOT NULL,
    attachments JSONB,
    is_internal_note BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE canned_responses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    shortcut VARCHAR(50) UNIQUE,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_by UUID REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- LABEL PRINTING
-- ========================================================================

CREATE TABLE label_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    label_width_mm DECIMAL(6,2) NOT NULL,
    label_height_mm DECIMAL(6,2) NOT NULL,
    layout_json JSONB NOT NULL,
    is_preset BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    created_by UUID REFERENCES users(id),
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE label_print_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    template_id UUID REFERENCES label_templates(id),
    printed_by UUID NOT NULL REFERENCES users(id),
    product_count INT NOT NULL,
    total_labels INT NOT NULL,
    printer_name VARCHAR(255),
    printed_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- BUSINESS ONBOARDING
-- ========================================================================

CREATE TABLE business_type_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(50) NOT NULL UNIQUE,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    icon VARCHAR(50) NOT NULL,
    template_json JSONB NOT NULL DEFAULT '{}',
    sample_products_json JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE onboarding_progress (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    current_step VARCHAR(50) DEFAULT 'welcome',
    completed_steps JSONB DEFAULT '[]',
    checklist_items JSONB DEFAULT '{}',
    is_wizard_completed BOOLEAN DEFAULT FALSE,
    is_checklist_dismissed BOOLEAN DEFAULT FALSE,
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);


-- ========================================================================
-- INDUSTRY: Pharmacy
-- ========================================================================

CREATE TABLE prescriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID REFERENCES orders(id),
    prescription_number VARCHAR(50) NOT NULL,
    patient_name VARCHAR(200) NOT NULL,
    patient_id VARCHAR(50),
    doctor_name VARCHAR(200),
    doctor_license VARCHAR(50),
    insurance_provider VARCHAR(100),
    insurance_claim_amount DECIMAL(12,3),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE drug_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL UNIQUE REFERENCES products(id),
    schedule_type VARCHAR(20) NOT NULL DEFAULT 'otc',
    active_ingredient VARCHAR(200),
    dosage_form VARCHAR(50),
    strength VARCHAR(50),
    manufacturer VARCHAR(200),
    requires_prescription BOOLEAN DEFAULT FALSE
);


-- ========================================================================
-- INDUSTRY: Jewelry
-- ========================================================================

CREATE TABLE daily_metal_rates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10),
    rate_per_gram DECIMAL(12,3) NOT NULL,
    buyback_rate_per_gram DECIMAL(12,3),
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, metal_type, karat, effective_date)
);

CREATE TABLE jewelry_product_details (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL UNIQUE REFERENCES products(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10),
    gross_weight_g DECIMAL(10,3) NOT NULL,
    net_weight_g DECIMAL(10,3) NOT NULL,
    making_charges_type VARCHAR(20) DEFAULT 'percentage',
    making_charges_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    stone_type VARCHAR(50),
    stone_weight_carat DECIMAL(10,3),
    stone_count INTEGER,
    certificate_number VARCHAR(100),
    certificate_url VARCHAR(500)
);

CREATE TABLE buyback_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10) NOT NULL,
    weight_g DECIMAL(10,3) NOT NULL,
    rate_per_gram DECIMAL(12,3) NOT NULL,
    total_amount DECIMAL(12,3) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- INDUSTRY: Electronics
-- ========================================================================

CREATE TABLE device_imei_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    imei VARCHAR(15) NOT NULL,
    imei2 VARCHAR(15),
    serial_number VARCHAR(50),
    condition_grade VARCHAR(5),
    purchase_price DECIMAL(12,3),
    status VARCHAR(20) DEFAULT 'in_stock',
    warranty_end_date DATE,
    store_warranty_end_date DATE,
    sold_order_id UUID REFERENCES orders(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE repair_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    device_description VARCHAR(200) NOT NULL,
    imei VARCHAR(15),
    issue_description TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'received',
    diagnosis_notes TEXT,
    repair_notes TEXT,
    estimated_cost DECIMAL(12,3),
    final_cost DECIMAL(12,3),
    parts_used JSONB,
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    received_at TIMESTAMP DEFAULT NOW(),
    estimated_ready_at TIMESTAMP,
    completed_at TIMESTAMP,
    collected_at TIMESTAMP
);

CREATE TABLE trade_in_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    device_description VARCHAR(200) NOT NULL,
    imei VARCHAR(15),
    condition_grade VARCHAR(5) NOT NULL,
    assessed_value DECIMAL(12,3) NOT NULL,
    applied_to_order_id UUID REFERENCES orders(id),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- INDUSTRY: Florist
-- ========================================================================

CREATE TABLE flower_arrangements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(200) NOT NULL,
    occasion VARCHAR(50),
    items_json JSONB NOT NULL,
    total_price DECIMAL(12,3) NOT NULL,
    is_template BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE flower_freshness_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    received_date DATE NOT NULL,
    expected_vase_life_days INTEGER NOT NULL,
    markdown_date DATE,
    dispose_date DATE,
    quantity INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'fresh'
);

CREATE TABLE flower_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    arrangement_template_id UUID REFERENCES flower_arrangements(id),
    frequency VARCHAR(20) NOT NULL,
    delivery_day VARCHAR(10),
    delivery_address TEXT NOT NULL,
    price_per_delivery DECIMAL(12,3) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    next_delivery_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- INDUSTRY: Bakery
-- ========================================================================

CREATE TABLE bakery_recipes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    name VARCHAR(200) NOT NULL,
    expected_yield INTEGER NOT NULL DEFAULT 1,
    prep_time_minutes INTEGER,
    bake_time_minutes INTEGER,
    bake_temperature_c INTEGER,
    instructions TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE production_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    recipe_id UUID NOT NULL REFERENCES bakery_recipes(id),
    schedule_date DATE NOT NULL,
    planned_batches INTEGER NOT NULL DEFAULT 1,
    actual_batches INTEGER,
    planned_yield INTEGER NOT NULL,
    actual_yield INTEGER,
    status VARCHAR(20) DEFAULT 'planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE custom_cake_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    order_id UUID REFERENCES orders(id),
    description TEXT NOT NULL,
    size VARCHAR(50),
    flavor VARCHAR(100),
    decoration_notes TEXT,
    delivery_date DATE NOT NULL,
    delivery_time TIME,
    price DECIMAL(12,3) NOT NULL,
    deposit_paid DECIMAL(12,3) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'ordered',
    reference_image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW()
);


-- ========================================================================
-- INDUSTRY: Restaurant
-- ========================================================================

CREATE TABLE restaurant_tables (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_number VARCHAR(20) NOT NULL,
    display_name VARCHAR(50),
    seats INTEGER NOT NULL DEFAULT 4,
    zone VARCHAR(50),
    position_x INTEGER DEFAULT 0,
    position_y INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'available',
    current_order_id UUID REFERENCES orders(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, table_number)
);

CREATE TABLE kitchen_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    table_id UUID REFERENCES restaurant_tables(id),
    ticket_number INTEGER NOT NULL,
    items_json JSONB NOT NULL,
    station VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    course_number INTEGER DEFAULT 1,
    fire_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE TABLE table_reservations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_id UUID REFERENCES restaurant_tables(id),
    customer_name VARCHAR(200) NOT NULL,
    customer_phone VARCHAR(20),
    party_size INTEGER NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    duration_minutes INTEGER DEFAULT 90,
    status VARCHAR(20) DEFAULT 'confirmed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE open_tabs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    customer_name VARCHAR(200) NOT NULL,
    table_id UUID REFERENCES restaurant_tables(id),
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'open'
);


-- ========================================================================
-- MATERIALIZED VIEWS (Performance optimization)
-- ========================================================================

-- Source: store_owner_web_dashboard_feature.md
-- Pre-aggregated daily sales for fast dashboard queries
CREATE MATERIALIZED VIEW mv_daily_sales_summary AS
SELECT
    store_id,
    DATE(created_at) AS sale_date,
    COUNT(*) AS order_count,
    SUM(total) AS total_revenue,
    SUM(tax_amount) AS total_vat,
    AVG(total) AS avg_order_value
FROM orders
WHERE status = 'completed'
GROUP BY store_id, DATE(created_at);

CREATE UNIQUE INDEX mv_daily_sales_store_date
    ON mv_daily_sales_summary (store_id, sale_date);

-- Source: store_owner_web_dashboard_feature.md
-- Pre-aggregated product performance metrics
CREATE MATERIALIZED VIEW mv_product_performance AS
SELECT
    o.store_id,
    p.id AS product_id,
    p.name_ar,
    p.name AS name_en,
    SUM(oi.quantity) AS total_qty_sold,
    SUM(oi.total) AS total_revenue,
    COUNT(DISTINCT oi.order_id) AS order_count
FROM products p
JOIN order_items oi ON oi.product_id = p.id
JOIN orders o ON o.id = oi.order_id
WHERE o.status = 'completed'
GROUP BY o.store_id, p.id, p.name_ar, p.name;

CREATE UNIQUE INDEX mv_product_perf_store_product
    ON mv_product_performance (store_id, product_id);


-- ========================================================================
-- DEFERRED FOREIGN KEY CONSTRAINTS
-- (Tables that reference tables defined later in the schema)
-- ========================================================================

ALTER TABLE knowledge_base_articles
    ADD CONSTRAINT fk_kb_articles_delivery_platform
    FOREIGN KEY (delivery_platform_id) REFERENCES delivery_platforms(id);

ALTER TABLE commission_earnings
    ADD CONSTRAINT fk_commission_earnings_order
    FOREIGN KEY (order_id) REFERENCES orders(id);

ALTER TABLE goods_receipts
    ADD CONSTRAINT fk_goods_receipts_purchase_order
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id);

ALTER TABLE promotion_customer_groups
    ADD CONSTRAINT fk_promotion_customer_groups_group
    FOREIGN KEY (customer_group_id) REFERENCES customer_groups(id) ON DELETE CASCADE;

ALTER TABLE promotion_usage_log
    ADD CONSTRAINT fk_promotion_usage_log_order
    FOREIGN KEY (order_id) REFERENCES orders(id);

ALTER TABLE promotion_usage_log
    ADD CONSTRAINT fk_promotion_usage_log_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id);

ALTER TABLE customers
    ADD CONSTRAINT fk_customers_group
    FOREIGN KEY (group_id) REFERENCES customer_groups(id);

ALTER TABLE loyalty_transactions
    ADD CONSTRAINT fk_loyalty_transactions_order
    FOREIGN KEY (order_id) REFERENCES orders(id);

ALTER TABLE store_credit_transactions
    ADD CONSTRAINT fk_store_credit_transactions_order
    FOREIGN KEY (order_id) REFERENCES orders(id);

ALTER TABLE store_credit_transactions
    ADD CONSTRAINT fk_store_credit_transactions_payment
    FOREIGN KEY (payment_id) REFERENCES payments(id);

ALTER TABLE digital_receipt_log
    ADD CONSTRAINT fk_digital_receipt_log_order
    FOREIGN KEY (order_id) REFERENCES orders(id);

ALTER TABLE digital_receipt_log
    ADD CONSTRAINT fk_digital_receipt_log_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id);


-- END OF SCHEMA
-- Total tables: 255 | Materialized views: 2 | Source files: 47
