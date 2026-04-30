<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: provider_limit_overrides was originally created with store_id FK
 * but the service layer uses organization_id. This migration adds organization_id,
 * backfills it from stores table, then drops store_id constraint and renames.
 *
 * Also adds: impersonation_sessions table for audit trail.
 * Also adds: provider_registrations.notes column for internal admin notes during review.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') return;

        DB::unprepared(<<<'SQL'

-- ─── Fix provider_limit_overrides: add organization_id, migrate data, drop store_id ──────────

-- 1. Add organization_id column (nullable initially so existing rows don't fail)
ALTER TABLE provider_limit_overrides
    ADD COLUMN IF NOT EXISTS organization_id UUID;

-- 2. Backfill organization_id from stores (only if store_id column still exists)
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'provider_limit_overrides' AND column_name = 'store_id'
    ) THEN
        UPDATE provider_limit_overrides plo
        SET organization_id = s.organization_id
        FROM stores s
        WHERE s.id = plo.store_id
          AND plo.organization_id IS NULL;
    END IF;
END $$;

-- 3. Make organization_id NOT NULL (skip if already NOT NULL)
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'provider_limit_overrides'
          AND column_name = 'organization_id'
          AND is_nullable = 'YES'
    ) THEN
        ALTER TABLE provider_limit_overrides ALTER COLUMN organization_id SET NOT NULL;
    END IF;
END $$;

-- 4. Drop the old unique constraint on (store_id, limit_key)
ALTER TABLE provider_limit_overrides
    DROP CONSTRAINT IF EXISTS provider_limit_overrides_store_id_limit_key_key;

-- 5. Add FK to organizations
ALTER TABLE provider_limit_overrides
    ADD CONSTRAINT fk_plo_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

-- 6. Add new unique constraint on (organization_id, limit_key)
ALTER TABLE provider_limit_overrides
    ADD CONSTRAINT provider_limit_overrides_organization_id_limit_key_key
    UNIQUE (organization_id, limit_key);

-- 7. Drop old FK on store_id (it references stores)
ALTER TABLE provider_limit_overrides
    DROP CONSTRAINT IF EXISTS provider_limit_overrides_store_id_fkey;

-- 8. Drop store_id column
ALTER TABLE provider_limit_overrides
    DROP COLUMN IF EXISTS store_id;

-- ─── Indexes ────────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_plo_organization_id ON provider_limit_overrides(organization_id);
CREATE INDEX IF NOT EXISTS idx_plo_expires_at ON provider_limit_overrides(expires_at) WHERE expires_at IS NOT NULL;

-- ─── impersonation_sessions table ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS impersonation_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES admin_users(id),
    target_user_id UUID NOT NULL REFERENCES users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    token VARCHAR(128) NOT NULL UNIQUE,
    ip_address INET,
    user_agent TEXT,
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    ended_at TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_impersonation_admin ON impersonation_sessions(admin_user_id);
CREATE INDEX IF NOT EXISTS idx_impersonation_token ON impersonation_sessions(token);
CREATE INDEX IF NOT EXISTS idx_impersonation_expires ON impersonation_sessions(expires_at);

-- ─── Enhance provider_registrations ─────────────────────────────────────────
ALTER TABLE provider_registrations
    ADD COLUMN IF NOT EXISTS internal_notes TEXT,
    ADD COLUMN IF NOT EXISTS source VARCHAR(30) DEFAULT 'website',
    ADD COLUMN IF NOT EXISTS plan_id UUID REFERENCES subscription_plans(id);

-- ─── Enhance store_subscriptions for plan info ───────────────────────────────
-- cancellation_reasons already has store_subscription_id FK. Add admin_notes.
ALTER TABLE cancellation_reasons
    ADD COLUMN IF NOT EXISTS recorded_by UUID REFERENCES admin_users(id);

SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') return;

        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS impersonation_sessions;

ALTER TABLE provider_limit_overrides
    DROP CONSTRAINT IF EXISTS provider_limit_overrides_organization_id_limit_key_key,
    DROP CONSTRAINT IF EXISTS fk_plo_organization,
    ADD COLUMN IF NOT EXISTS store_id UUID;

ALTER TABLE provider_registrations
    DROP COLUMN IF EXISTS internal_notes,
    DROP COLUMN IF EXISTS source,
    DROP COLUMN IF EXISTS plan_id;

ALTER TABLE cancellation_reasons
    DROP COLUMN IF EXISTS recorded_by;
SQL);
    }
};
