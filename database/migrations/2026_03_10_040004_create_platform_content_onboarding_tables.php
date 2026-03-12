<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: Content & Onboarding
 *
 * Tables: business_types, pos_layout_templates, platform_ui_defaults, themes, theme_package_visibility, layout_package_visibility, receipt_layout_templates, receipt_template_package_visibility, cfd_themes, cfd_theme_package_visibility, signage_templates, signage_template_business_types, signage_template_package_visibility, label_layout_templates, label_template_business_types, label_template_package_visibility, business_type_category_templates, business_type_shift_templates, business_type_receipt_templates, business_type_industry_configs, business_type_promotion_templates, business_type_commission_templates, business_type_loyalty_configs, business_type_customer_group_templates, business_type_return_policies, business_type_waste_reason_templates, business_type_appointment_configs, business_type_service_category_templates, business_type_gift_registry_types, business_type_gamification_badges, business_type_gamification_challenges, business_type_gamification_milestones, onboarding_steps, knowledge_base_articles, pricing_page_content
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

CREATE TABLE pricing_page_content (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL UNIQUE REFERENCES subscription_plans(id),
    feature_bullet_list JSONB NOT NULL DEFAULT '[]',
    faq JSONB NOT NULL DEFAULT '[]',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_bt_category_templates_type ON business_type_category_templates (business_type_id, sort_order);

CREATE INDEX idx_bt_shift_templates_type ON business_type_shift_templates (business_type_id, sort_order);

CREATE INDEX idx_bt_promotion_templates_type ON business_type_promotion_templates (business_type_id);

CREATE INDEX idx_bt_commission_templates_type ON business_type_commission_templates (business_type_id);

CREATE INDEX idx_bt_customer_group_templates_type ON business_type_customer_group_templates (business_type_id);

CREATE INDEX idx_bt_waste_reason_templates_type ON business_type_waste_reason_templates (business_type_id);

CREATE INDEX idx_bt_service_category_templates_type ON business_type_service_category_templates (business_type_id);

CREATE INDEX idx_bt_gift_registry_types_type ON business_type_gift_registry_types (business_type_id);

CREATE INDEX idx_bt_gamification_badges_type ON business_type_gamification_badges (business_type_id);

CREATE INDEX idx_bt_gamification_challenges_type ON business_type_gamification_challenges (business_type_id);

CREATE INDEX idx_bt_gamification_milestones_type ON business_type_gamification_milestones (business_type_id);

CREATE INDEX idx_kb_articles_published_category ON knowledge_base_articles (is_published, category);

INSERT INTO platform_ui_defaults VALUES ('handedness','right'),('font_size','medium'),('theme','light_classic');
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_page_content');
        Schema::dropIfExists('knowledge_base_articles');
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('business_type_gamification_milestones');
        Schema::dropIfExists('business_type_gamification_challenges');
        Schema::dropIfExists('business_type_gamification_badges');
        Schema::dropIfExists('business_type_gift_registry_types');
        Schema::dropIfExists('business_type_service_category_templates');
        Schema::dropIfExists('business_type_appointment_configs');
        Schema::dropIfExists('business_type_waste_reason_templates');
        Schema::dropIfExists('business_type_return_policies');
        Schema::dropIfExists('business_type_customer_group_templates');
        Schema::dropIfExists('business_type_loyalty_configs');
        Schema::dropIfExists('business_type_commission_templates');
        Schema::dropIfExists('business_type_promotion_templates');
        Schema::dropIfExists('business_type_industry_configs');
        Schema::dropIfExists('business_type_receipt_templates');
        Schema::dropIfExists('business_type_shift_templates');
        Schema::dropIfExists('business_type_category_templates');
        Schema::dropIfExists('label_template_package_visibility');
        Schema::dropIfExists('label_template_business_types');
        Schema::dropIfExists('label_layout_templates');
        Schema::dropIfExists('signage_template_package_visibility');
        Schema::dropIfExists('signage_template_business_types');
        Schema::dropIfExists('signage_templates');
        Schema::dropIfExists('cfd_theme_package_visibility');
        Schema::dropIfExists('cfd_themes');
        Schema::dropIfExists('receipt_template_package_visibility');
        Schema::dropIfExists('receipt_layout_templates');
        Schema::dropIfExists('layout_package_visibility');
        Schema::dropIfExists('theme_package_visibility');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('platform_ui_defaults');
        Schema::dropIfExists('pos_layout_templates');
        Schema::dropIfExists('business_types');
    }
};
