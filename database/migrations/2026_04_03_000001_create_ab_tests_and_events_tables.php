<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: A/B Tests & Conversion Events
 *
 * Tables: ab_tests, ab_test_variants, ab_test_events
 *
 * Previously only existed in SQLite test schema. This migration creates
 * them for PostgreSQL and adds the ab_test_events table for tracking
 * real impressions and conversions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return; // SQLite test schema handles these separately
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS ab_tests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    description TEXT,
    feature_flag_id UUID REFERENCES feature_flags(id) ON DELETE SET NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','running','completed','cancelled')),
    start_date DATE,
    end_date DATE,
    metric_key VARCHAR(100),
    traffic_percentage INT DEFAULT 100 CHECK (traffic_percentage BETWEEN 0 AND 100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ab_test_variants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ab_test_id UUID NOT NULL REFERENCES ab_tests(id) ON DELETE CASCADE,
    variant_key VARCHAR(50) NOT NULL,
    variant_label VARCHAR(150),
    weight INT DEFAULT 50,
    is_control BOOLEAN DEFAULT FALSE,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ab_test_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ab_test_id UUID NOT NULL REFERENCES ab_tests(id) ON DELETE CASCADE,
    variant_id UUID NOT NULL REFERENCES ab_test_variants(id) ON DELETE CASCADE,
    event_type VARCHAR(20) NOT NULL CHECK (event_type IN ('impression','conversion')),
    store_id UUID,
    user_id UUID,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_ab_test_events_test_variant ON ab_test_events(ab_test_id, variant_id);
CREATE INDEX IF NOT EXISTS idx_ab_test_events_type ON ab_test_events(ab_test_id, event_type);
CREATE INDEX IF NOT EXISTS idx_ab_test_events_created ON ab_test_events(created_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_test_events');
        Schema::dropIfExists('ab_test_variants');
        Schema::dropIfExists('ab_tests');
    }
};
