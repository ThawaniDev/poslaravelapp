<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INTEGRATIONS: Delivery Platforms
 *
 * Tables: store_delivery_platforms, delivery_platform_configs, delivery_order_mappings, delivery_menu_sync_logs, platform_delivery_integrations, store_delivery_platform_enrollments
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_delivery_platform_enrollments');
        Schema::dropIfExists('platform_delivery_integrations');
        Schema::dropIfExists('delivery_menu_sync_logs');
        Schema::dropIfExists('delivery_order_mappings');
        Schema::dropIfExists('delivery_platform_configs');
        Schema::dropIfExists('store_delivery_platforms');
    }
};
