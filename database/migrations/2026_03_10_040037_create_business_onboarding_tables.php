<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BUSINESS ONBOARDING
 *
 * Tables: business_type_templates, onboarding_progress
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
        Schema::dropIfExists('business_type_templates');
    }
};
