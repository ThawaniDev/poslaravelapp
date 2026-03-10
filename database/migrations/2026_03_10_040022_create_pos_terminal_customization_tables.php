<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS TERMINAL: Customization
 *
 * Tables: pos_customization_settings, receipt_templates, quick_access_configs
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_access_configs');
        Schema::dropIfExists('receipt_templates');
        Schema::dropIfExists('pos_customization_settings');
    }
};
