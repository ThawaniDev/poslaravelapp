<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance Delivery Integration tables with new columns for:
 * - delivery_order_mappings: store_id, delivery_status, customer info, delivery_fee, rejection_reason, accepted/ready timestamps
 * - delivery_platform_configs: operating_hours_synced, last_order_received_at, daily_order_count, max_daily_orders
 * - delivery_menu_sync_logs: triggered_by, sync_type
 * - New table: delivery_status_push_logs for tracking status push attempts
 * - New table: delivery_webhook_logs for webhook audit trail
 * - New indexes for performance
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->upSqlite();
            return;
        }

        DB::unprepared(<<<'SQL'
-- ══════════════════════════════════════════════════════════════
-- Enhance delivery_order_mappings
-- ══════════════════════════════════════════════════════════════
ALTER TABLE delivery_order_mappings
    ADD COLUMN IF NOT EXISTS store_id UUID REFERENCES stores(id),
    ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(30) DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255),
    ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(30),
    ADD COLUMN IF NOT EXISTS delivery_address TEXT,
    ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS subtotal DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS total_amount DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS items_count INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT,
    ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS ready_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS dispatched_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS estimated_prep_minutes INT,
    ADD COLUMN IF NOT EXISTS notes TEXT;

CREATE INDEX IF NOT EXISTS idx_delivery_orders_store_status ON delivery_order_mappings (store_id, delivery_status);
CREATE INDEX IF NOT EXISTS idx_delivery_orders_platform_ext ON delivery_order_mappings (platform, external_order_id);

-- ══════════════════════════════════════════════════════════════
-- Enhance delivery_platform_configs
-- ══════════════════════════════════════════════════════════════
ALTER TABLE delivery_platform_configs
    ADD COLUMN IF NOT EXISTS operating_hours_synced BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS last_order_received_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS daily_order_count INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS max_daily_orders INT,
    ADD COLUMN IF NOT EXISTS sync_menu_on_product_change BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS menu_sync_interval_hours INT DEFAULT 6,
    ADD COLUMN IF NOT EXISTS webhook_url TEXT,
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'inactive';

-- ══════════════════════════════════════════════════════════════
-- Enhance delivery_menu_sync_logs
-- ══════════════════════════════════════════════════════════════
ALTER TABLE delivery_menu_sync_logs
    ADD COLUMN IF NOT EXISTS triggered_by VARCHAR(30) DEFAULT 'manual',
    ADD COLUMN IF NOT EXISTS sync_type VARCHAR(30) DEFAULT 'full',
    ADD COLUMN IF NOT EXISTS duration_seconds INT;

-- ══════════════════════════════════════════════════════════════
-- Enhance delivery_platforms (registry)
-- ══════════════════════════════════════════════════════════════
ALTER TABLE delivery_platforms
    ADD COLUMN IF NOT EXISTS name_ar VARCHAR(100),
    ADD COLUMN IF NOT EXISTS description TEXT,
    ADD COLUMN IF NOT EXISTS description_ar TEXT,
    ADD COLUMN IF NOT EXISTS api_type VARCHAR(20) DEFAULT 'rest',
    ADD COLUMN IF NOT EXISTS base_url TEXT,
    ADD COLUMN IF NOT EXISTS documentation_url TEXT,
    ADD COLUMN IF NOT EXISTS supported_countries JSONB DEFAULT '["SA"]',
    ADD COLUMN IF NOT EXISTS default_commission_percent DECIMAL(5,2) DEFAULT 0;

-- ══════════════════════════════════════════════════════════════
-- New table: delivery_status_push_logs
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS delivery_status_push_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    delivery_order_mapping_id UUID NOT NULL REFERENCES delivery_order_mappings(id) ON DELETE CASCADE,
    status_pushed VARCHAR(30) NOT NULL,
    platform VARCHAR(50) NOT NULL,
    http_status_code INT,
    request_payload JSONB,
    response_payload JSONB,
    success BOOLEAN DEFAULT FALSE,
    attempt_number INT DEFAULT 1,
    error_message TEXT,
    pushed_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_status_push_logs_mapping ON delivery_status_push_logs (delivery_order_mapping_id);

-- ══════════════════════════════════════════════════════════════
-- New table: delivery_webhook_logs
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS delivery_webhook_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    platform VARCHAR(50) NOT NULL,
    store_id UUID REFERENCES stores(id),
    event_type VARCHAR(50) NOT NULL,
    external_order_id VARCHAR(100),
    payload JSONB NOT NULL,
    headers JSONB,
    signature_valid BOOLEAN,
    processed BOOLEAN DEFAULT FALSE,
    processing_result VARCHAR(30),
    error_message TEXT,
    ip_address VARCHAR(45),
    received_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_webhook_logs_platform ON delivery_webhook_logs (platform, received_at DESC);
CREATE INDEX IF NOT EXISTS idx_webhook_logs_store ON delivery_webhook_logs (store_id, received_at DESC);

SQL);
    }

    private function upSqlite(): void
    {
        // ── Create base tables for SQLite testing (originals skip SQLite) ──

        if (!Schema::hasTable('delivery_platforms')) {
            Schema::create('delivery_platforms', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name', 100);
                $table->string('slug', 50)->unique();
                $table->text('logo_url')->nullable();
                $table->string('auth_method', 20);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->string('name_ar', 100)->nullable();
                $table->text('description')->nullable();
                $table->text('description_ar')->nullable();
                $table->string('api_type', 20)->default('rest');
                $table->text('base_url')->nullable();
                $table->text('documentation_url')->nullable();
                $table->json('supported_countries')->nullable();
                $table->decimal('default_commission_percent', 5, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('delivery_platform_configs')) {
            Schema::create('delivery_platform_configs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('platform', 50);
                $table->text('api_key')->nullable();
                $table->string('merchant_id', 100)->nullable();
                $table->text('webhook_secret')->nullable();
                $table->string('branch_id_on_platform', 100)->nullable();
                $table->boolean('is_enabled')->default(false);
                $table->boolean('auto_accept')->default(true);
                $table->integer('throttle_limit')->nullable();
                $table->integer('max_daily_orders')->nullable();
                $table->timestamp('last_menu_sync_at')->nullable();
                $table->boolean('operating_hours_synced')->default(false);
                $table->timestamp('last_order_received_at')->nullable();
                $table->integer('daily_order_count')->default(0);
                $table->boolean('sync_menu_on_product_change')->default(true);
                $table->integer('menu_sync_interval_hours')->default(6);
                $table->text('webhook_url')->nullable();
                $table->string('status', 20)->default('inactive');
                $table->timestamps();
                $table->unique(['store_id', 'platform']);
            });
        }

        if (!Schema::hasTable('delivery_order_mappings')) {
            Schema::create('delivery_order_mappings', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->nullable();
                $table->uuid('order_id')->nullable();
                $table->string('platform', 50);
                $table->string('external_order_id', 100);
                $table->string('external_status', 50)->nullable();
                $table->string('delivery_status', 30)->default('pending');
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->decimal('commission_percent', 5, 2)->nullable();
                $table->json('raw_payload')->nullable();
                $table->string('customer_name', 255)->nullable();
                $table->string('customer_phone', 30)->nullable();
                $table->text('delivery_address')->nullable();
                $table->decimal('delivery_fee', 12, 2)->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->integer('items_count')->default(0);
                $table->text('rejection_reason')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->integer('estimated_prep_minutes')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('delivery_menu_sync_logs')) {
            Schema::create('delivery_menu_sync_logs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('platform', 50);
                $table->string('status', 20);
                $table->integer('items_synced')->default(0);
                $table->integer('items_failed')->default(0);
                $table->json('error_details')->nullable();
                $table->string('triggered_by', 30)->default('manual');
                $table->string('sync_type', 30)->default('full');
                $table->integer('duration_seconds')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
            });
        }

        if (!Schema::hasTable('platform_delivery_integrations')) {
            Schema::create('platform_delivery_integrations', function ($table) {
                $table->uuid('id')->primary();
                $table->string('platform_slug', 50)->unique();
                $table->string('display_name', 100);
                $table->string('display_name_ar', 100)->nullable();
                $table->text('api_base_url');
                $table->text('client_id')->nullable();
                $table->text('client_secret_encrypted')->nullable();
                $table->text('webhook_secret_encrypted')->nullable();
                $table->decimal('default_commission_percent', 5, 2)->default(0);
                $table->boolean('is_active')->default(false);
                $table->json('supported_countries')->nullable();
                $table->text('logo_url')->nullable();
                $table->timestamps();
            });
        }

        // ── New tables ──────────────────────────────────────────

        if (!Schema::hasTable('delivery_status_push_logs')) {
            Schema::create('delivery_status_push_logs', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('delivery_order_mapping_id');
                $table->string('status_pushed', 30);
                $table->string('platform', 50);
                $table->integer('http_status_code')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->boolean('success')->default(false);
                $table->integer('attempt_number')->default(1);
                $table->text('error_message')->nullable();
                $table->timestamp('pushed_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('delivery_webhook_logs')) {
            Schema::create('delivery_webhook_logs', function ($table) {
                $table->uuid('id')->primary();
                $table->string('platform', 50);
                $table->uuid('store_id')->nullable();
                $table->string('event_type', 50);
                $table->string('external_order_id', 100)->nullable();
                $table->json('payload');
                $table->json('headers')->nullable();
                $table->boolean('signature_valid')->nullable();
                $table->boolean('processed')->default(false);
                $table->string('processing_result', 30)->nullable();
                $table->text('error_message')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('received_at')->useCurrent();
            });
        }

        // ── Add columns to existing tables (only if they existed before this migration) ──

        if (Schema::hasTable('delivery_order_mappings') && !Schema::hasColumn('delivery_order_mappings', 'store_id')) {
            Schema::table('delivery_order_mappings', function ($table) {
                $table->uuid('store_id')->nullable()->after('id');
                $table->string('delivery_status', 30)->default('pending')->after('external_status');
                $table->string('customer_name', 255)->nullable();
                $table->string('customer_phone', 30)->nullable();
                $table->text('delivery_address')->nullable();
                $table->decimal('delivery_fee', 12, 2)->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->integer('items_count')->default(0);
                $table->text('rejection_reason')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->integer('estimated_prep_minutes')->nullable();
                $table->text('notes')->nullable();
            });
        }

        if (Schema::hasTable('delivery_platform_configs') && !Schema::hasColumn('delivery_platform_configs', 'operating_hours_synced')) {
            Schema::table('delivery_platform_configs', function ($table) {
                $table->boolean('operating_hours_synced')->default(false);
                $table->timestamp('last_order_received_at')->nullable();
                $table->integer('daily_order_count')->default(0);
                $table->integer('max_daily_orders')->nullable();
                $table->boolean('sync_menu_on_product_change')->default(true);
                $table->integer('menu_sync_interval_hours')->default(6);
                $table->text('webhook_url')->nullable();
                $table->string('status', 20)->default('inactive');
            });
        }

        if (Schema::hasTable('delivery_menu_sync_logs') && !Schema::hasColumn('delivery_menu_sync_logs', 'triggered_by')) {
            Schema::table('delivery_menu_sync_logs', function ($table) {
                $table->string('triggered_by', 30)->default('manual');
                $table->string('sync_type', 30)->default('full');
                $table->integer('duration_seconds')->nullable();
            });
        }

        if (Schema::hasTable('delivery_platforms')) {
            Schema::table('delivery_platforms', function ($table) {
                if (!Schema::hasColumn('delivery_platforms', 'name_ar')) {
                    $table->string('name_ar', 100)->nullable();
                }
                if (!Schema::hasColumn('delivery_platforms', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('delivery_platforms', 'description_ar')) {
                    $table->text('description_ar')->nullable();
                }
                if (!Schema::hasColumn('delivery_platforms', 'api_type')) {
                    $table->string('api_type', 20)->default('rest');
                }
                if (!Schema::hasColumn('delivery_platforms', 'base_url')) {
                    $table->text('base_url')->nullable();
                }
                if (!Schema::hasColumn('delivery_platforms', 'documentation_url')) {
                    $table->text('documentation_url')->nullable();
                }
                if (!Schema::hasColumn('delivery_platforms', 'supported_countries')) {
                    $table->json('supported_countries')->nullable();
                }
                if (!Schema::hasColumn('delivery_platforms', 'default_commission_percent')) {
                    $table->decimal('default_commission_percent', 5, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_webhook_logs');
        Schema::dropIfExists('delivery_status_push_logs');
    }
};
