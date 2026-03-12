<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: Delivery Platform Registry
 *
 * Tables: delivery_platforms, delivery_platform_fields, delivery_platform_endpoints, delivery_platform_webhook_templates
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
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_webhook_templates');
        Schema::dropIfExists('delivery_platform_endpoints');
        Schema::dropIfExists('delivery_platform_fields');
        Schema::dropIfExists('delivery_platforms');
    }
};
