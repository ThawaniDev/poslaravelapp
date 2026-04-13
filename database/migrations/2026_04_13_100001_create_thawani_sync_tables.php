<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INTEGRATIONS: Thawani Marketplace Sync Enhancement
 *
 * Tables: thawani_category_mappings, thawani_sync_queue, thawani_sync_logs, thawani_column_mappings
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE thawani_category_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    category_id UUID NOT NULL REFERENCES categories(id),
    thawani_category_id BIGINT NOT NULL,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    sync_direction VARCHAR(20) NOT NULL DEFAULT 'outgoing',
    sync_error TEXT,
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, category_id)
);

CREATE TABLE thawani_sync_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    entity_type VARCHAR(50) NOT NULL,
    entity_id UUID NOT NULL,
    action VARCHAR(30) NOT NULL,
    payload JSONB,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 5,
    error_message TEXT,
    scheduled_at TIMESTAMP DEFAULT NOW(),
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_thawani_sync_queue_status ON thawani_sync_queue(status);
CREATE INDEX idx_thawani_sync_queue_scheduled ON thawani_sync_queue(scheduled_at);

CREATE TABLE thawani_sync_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(255),
    action VARCHAR(50) NOT NULL,
    direction VARCHAR(20) NOT NULL DEFAULT 'outgoing',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    request_data JSONB,
    response_data JSONB,
    error_message TEXT,
    http_status_code INTEGER,
    retry_count INTEGER NOT NULL DEFAULT 0,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_thawani_sync_logs_store ON thawani_sync_logs(store_id);
CREATE INDEX idx_thawani_sync_logs_status ON thawani_sync_logs(status);

CREATE TABLE thawani_column_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_type VARCHAR(50) NOT NULL,
    thawani_field VARCHAR(100) NOT NULL,
    wameed_field VARCHAR(100) NOT NULL,
    transform_type VARCHAR(30) NOT NULL DEFAULT 'direct',
    transform_config JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(entity_type, thawani_field, wameed_field)
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('thawani_column_mappings');
        Schema::dropIfExists('thawani_sync_logs');
        Schema::dropIfExists('thawani_sync_queue');
        Schema::dropIfExists('thawani_category_mappings');
    }
};
