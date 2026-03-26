<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced POS Layout Builder & Template Marketplace
 *
 * New: layout_widgets, layout_widget_placements, widget_theme_overrides,
 *      marketplace_categories, template_marketplace_listings, template_purchases,
 *      template_reviews, template_versions, theme_variables
 * Altered: pos_layout_templates (canvas + marketplace columns), themes (design token columns)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // ── Alter pos_layout_templates ────────────────────────
        DB::unprepared(<<<'SQL'
ALTER TABLE pos_layout_templates
    ADD COLUMN IF NOT EXISTS canvas_columns INT NOT NULL DEFAULT 24,
    ADD COLUMN IF NOT EXISTS canvas_rows INT NOT NULL DEFAULT 16,
    ADD COLUMN IF NOT EXISTS canvas_gap_px INT NOT NULL DEFAULT 4,
    ADD COLUMN IF NOT EXISTS canvas_padding_px INT NOT NULL DEFAULT 8,
    ADD COLUMN IF NOT EXISTS breakpoints JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
    ADD COLUMN IF NOT EXISTS is_locked BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS clone_source_id UUID REFERENCES pos_layout_templates(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS published_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW();
SQL);

        // ── Alter themes ─────────────────────────────────────
        DB::unprepared(<<<'SQL'
ALTER TABLE themes
    ADD COLUMN IF NOT EXISTS typography_config JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS spacing_config JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS border_config JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS shadow_config JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS animation_config JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS css_variables JSONB NOT NULL DEFAULT '{}';
SQL);

        // ── New tables ───────────────────────────────────────
        DB::unprepared(<<<'SQL'

-- Widget catalog / registry
CREATE TABLE layout_widgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    description_ar TEXT,
    category VARCHAR(30) NOT NULL,
    icon VARCHAR(50),
    default_width INT NOT NULL DEFAULT 6,
    default_height INT NOT NULL DEFAULT 4,
    min_width INT NOT NULL DEFAULT 2,
    min_height INT NOT NULL DEFAULT 2,
    max_width INT NOT NULL DEFAULT 24,
    max_height INT NOT NULL DEFAULT 16,
    is_resizable BOOLEAN DEFAULT TRUE,
    is_required BOOLEAN DEFAULT FALSE,
    properties_schema JSONB NOT NULL DEFAULT '[]',
    default_properties JSONB NOT NULL DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Widget instances placed on a layout canvas
CREATE TABLE layout_widget_placements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pos_layout_template_id UUID NOT NULL REFERENCES pos_layout_templates(id) ON DELETE CASCADE,
    layout_widget_id UUID NOT NULL REFERENCES layout_widgets(id) ON DELETE RESTRICT,
    instance_key VARCHAR(50) NOT NULL,
    grid_x INT NOT NULL DEFAULT 0,
    grid_y INT NOT NULL DEFAULT 0,
    grid_w INT NOT NULL DEFAULT 6,
    grid_h INT NOT NULL DEFAULT 4,
    z_index INT NOT NULL DEFAULT 0,
    properties JSONB NOT NULL DEFAULT '{}',
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (pos_layout_template_id, instance_key)
);

-- Per-widget theme overrides within a placement
CREATE TABLE widget_theme_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    layout_widget_placement_id UUID NOT NULL REFERENCES layout_widget_placements(id) ON DELETE CASCADE,
    variable_key VARCHAR(100) NOT NULL,
    value VARCHAR(255) NOT NULL,
    UNIQUE (layout_widget_placement_id, variable_key)
);

-- Marketplace categories
CREATE TABLE marketplace_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(50),
    description TEXT,
    description_ar TEXT,
    parent_id UUID REFERENCES marketplace_categories(id) ON DELETE SET NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Marketplace listings (one per template)
CREATE TABLE template_marketplace_listings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pos_layout_template_id UUID NOT NULL UNIQUE REFERENCES pos_layout_templates(id) ON DELETE CASCADE,
    theme_id UUID REFERENCES themes(id) ON DELETE SET NULL,
    publisher_name VARCHAR(100) NOT NULL,
    publisher_avatar_url TEXT,
    title VARCHAR(150) NOT NULL,
    title_ar VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    short_description VARCHAR(300),
    short_description_ar VARCHAR(300),
    preview_images JSONB NOT NULL DEFAULT '[]',
    demo_video_url TEXT,
    pricing_type VARCHAR(20) NOT NULL DEFAULT 'free',
    price_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    subscription_interval VARCHAR(10),
    category_id UUID REFERENCES marketplace_categories(id) ON DELETE SET NULL,
    tags JSONB NOT NULL DEFAULT '[]',
    version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
    changelog TEXT,
    download_count INT NOT NULL DEFAULT 0,
    average_rating DECIMAL(2,1) NOT NULL DEFAULT 0.0,
    review_count INT NOT NULL DEFAULT 0,
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    rejection_reason TEXT,
    approved_by UUID,
    approved_at TIMESTAMP,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Template purchases / subscriptions
CREATE TABLE template_purchases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    marketplace_listing_id UUID NOT NULL REFERENCES template_marketplace_listings(id) ON DELETE RESTRICT,
    purchase_type VARCHAR(20) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    payment_reference VARCHAR(100),
    payment_gateway VARCHAR(30),
    subscription_starts_at TIMESTAMP,
    subscription_expires_at TIMESTAMP,
    auto_renew BOOLEAN NOT NULL DEFAULT TRUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    cancelled_at TIMESTAMP,
    refunded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Template reviews / ratings
CREATE TABLE template_reviews (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    marketplace_listing_id UUID NOT NULL REFERENCES template_marketplace_listings(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(200),
    body TEXT,
    is_verified_purchase BOOLEAN NOT NULL DEFAULT FALSE,
    is_published BOOLEAN NOT NULL DEFAULT TRUE,
    admin_response TEXT,
    admin_responded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (marketplace_listing_id, user_id)
);

-- Template version history
CREATE TABLE template_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pos_layout_template_id UUID NOT NULL REFERENCES pos_layout_templates(id) ON DELETE CASCADE,
    version_number VARCHAR(20) NOT NULL,
    changelog TEXT,
    canvas_snapshot JSONB NOT NULL,
    theme_snapshot JSONB,
    widget_placements_snapshot JSONB NOT NULL,
    published_by UUID,
    published_at TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Granular theme variables
CREATE TABLE theme_variables (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    theme_id UUID NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
    variable_key VARCHAR(100) NOT NULL,
    variable_value VARCHAR(255) NOT NULL,
    variable_type VARCHAR(20) NOT NULL,
    category VARCHAR(30) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (theme_id, variable_key)
);

SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS widget_theme_overrides CASCADE;
DROP TABLE IF EXISTS layout_widget_placements CASCADE;
DROP TABLE IF EXISTS layout_widgets CASCADE;
DROP TABLE IF EXISTS theme_variables CASCADE;
DROP TABLE IF EXISTS template_versions CASCADE;
DROP TABLE IF EXISTS template_reviews CASCADE;
DROP TABLE IF EXISTS template_purchases CASCADE;
DROP TABLE IF EXISTS template_marketplace_listings CASCADE;
DROP TABLE IF EXISTS marketplace_categories CASCADE;

ALTER TABLE pos_layout_templates
    DROP COLUMN IF EXISTS canvas_columns,
    DROP COLUMN IF EXISTS canvas_rows,
    DROP COLUMN IF EXISTS canvas_gap_px,
    DROP COLUMN IF EXISTS canvas_padding_px,
    DROP COLUMN IF EXISTS breakpoints,
    DROP COLUMN IF EXISTS version,
    DROP COLUMN IF EXISTS is_locked,
    DROP COLUMN IF EXISTS clone_source_id,
    DROP COLUMN IF EXISTS published_at,
    DROP COLUMN IF EXISTS updated_at;

ALTER TABLE themes
    DROP COLUMN IF EXISTS typography_config,
    DROP COLUMN IF EXISTS spacing_config,
    DROP COLUMN IF EXISTS border_config,
    DROP COLUMN IF EXISTS shadow_config,
    DROP COLUMN IF EXISTS animation_config,
    DROP COLUMN IF EXISTS css_variables;
SQL);
    }
};
