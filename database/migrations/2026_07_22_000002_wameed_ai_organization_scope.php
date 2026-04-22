<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wameed AI: Organization-level support.
 *
 * Allows AI features to be used by organization-level users (no store assignment).
 * - Makes store_id nullable on AI tables that previously required it.
 * - Adds organization_id (denormalized) to ai_suggestions, ai_feedback, ai_cache,
 *   ai_store_feature_configs, ai_chat_messages.
 * - Backfills organization_id from store joins.
 * - Replaces unique constraints with partial uniques that distinguish
 *   org-level rows (store_id NULL) from per-store rows (store_id NOT NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return; // sqlite test schema is rebuilt fresh
        }

        DB::unprepared(<<<'SQL'

-- ─── Make store_id nullable on tables where org-level usage is needed ────
ALTER TABLE ai_chats                       ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_usage_logs                  ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_daily_usage_summaries       ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_monthly_usage_summaries     ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_suggestions                 ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_feedback                    ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_store_feature_configs       ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_store_billing_configs       ALTER COLUMN store_id DROP NOT NULL;
ALTER TABLE ai_billing_invoices            ALTER COLUMN store_id DROP NOT NULL;

-- ─── Add organization_id where missing (denormalized for filter speed) ───
ALTER TABLE ai_suggestions
    ADD COLUMN IF NOT EXISTS organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE;
ALTER TABLE ai_feedback
    ADD COLUMN IF NOT EXISTS organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE;
ALTER TABLE ai_cache
    ADD COLUMN IF NOT EXISTS organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE;
ALTER TABLE ai_store_feature_configs
    ADD COLUMN IF NOT EXISTS organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE;
ALTER TABLE ai_chat_messages
    ADD COLUMN IF NOT EXISTS organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    ADD COLUMN IF NOT EXISTS store_id UUID REFERENCES stores(id) ON DELETE SET NULL;

-- ─── Backfill organization_id from store joins ──────────────────────────
UPDATE ai_suggestions s
   SET organization_id = st.organization_id
  FROM stores st
 WHERE s.store_id = st.id AND s.organization_id IS NULL;

UPDATE ai_feedback f
   SET organization_id = st.organization_id
  FROM stores st
 WHERE f.store_id = st.id AND f.organization_id IS NULL;

UPDATE ai_cache c
   SET organization_id = st.organization_id
  FROM stores st
 WHERE c.store_id = st.id AND c.organization_id IS NULL;

UPDATE ai_store_feature_configs sfc
   SET organization_id = st.organization_id
  FROM stores st
 WHERE sfc.store_id = st.id AND sfc.organization_id IS NULL;

UPDATE ai_chat_messages m
   SET organization_id = c.organization_id,
       store_id        = c.store_id
  FROM ai_chats c
 WHERE m.chat_id = c.id AND m.organization_id IS NULL;

-- ─── Indexes for the new organization_id columns ────────────────────────
CREATE INDEX IF NOT EXISTS idx_ai_suggestions_org_status
    ON ai_suggestions (organization_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_ai_feedback_org
    ON ai_feedback (organization_id);
CREATE INDEX IF NOT EXISTS idx_ai_cache_org
    ON ai_cache (organization_id);
CREATE INDEX IF NOT EXISTS idx_ai_store_feature_configs_org
    ON ai_store_feature_configs (organization_id);
CREATE INDEX IF NOT EXISTS idx_ai_chat_messages_org
    ON ai_chat_messages (organization_id);

-- ─── Replace existing UNIQUE(store_id,...) with partial uniques ─────────
-- Goal: keep per-store uniqueness but ALSO enforce org-level uniqueness
--       for rows that have store_id IS NULL (org-only).

-- ai_daily_usage_summaries: was UNIQUE(store_id, date)
ALTER TABLE ai_daily_usage_summaries
    DROP CONSTRAINT IF EXISTS ai_daily_usage_summaries_store_id_date_key;
DROP INDEX IF EXISTS ai_daily_usage_summaries_store_id_date_key;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_daily_usage_per_store
    ON ai_daily_usage_summaries (store_id, date) WHERE store_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_daily_usage_per_org
    ON ai_daily_usage_summaries (organization_id, date) WHERE store_id IS NULL;

-- ai_monthly_usage_summaries: was UNIQUE(store_id, month)
ALTER TABLE ai_monthly_usage_summaries
    DROP CONSTRAINT IF EXISTS ai_monthly_usage_summaries_store_id_month_key;
DROP INDEX IF EXISTS ai_monthly_usage_summaries_store_id_month_key;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_monthly_usage_per_store
    ON ai_monthly_usage_summaries (store_id, month) WHERE store_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_monthly_usage_per_org
    ON ai_monthly_usage_summaries (organization_id, month) WHERE store_id IS NULL;

-- ai_store_feature_configs: was UNIQUE(store_id, ai_feature_definition_id)
ALTER TABLE ai_store_feature_configs
    DROP CONSTRAINT IF EXISTS ai_store_feature_configs_store_id_ai_feature_definition_id_key;
DROP INDEX IF EXISTS ai_store_feature_configs_store_id_ai_feature_definition_id_key;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_store_feature_per_store
    ON ai_store_feature_configs (store_id, ai_feature_definition_id) WHERE store_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_store_feature_per_org
    ON ai_store_feature_configs (organization_id, ai_feature_definition_id) WHERE store_id IS NULL;

-- ai_store_billing_configs: was UNIQUE(store_id)
ALTER TABLE ai_store_billing_configs
    DROP CONSTRAINT IF EXISTS ai_store_billing_configs_store_id_key;
DROP INDEX IF EXISTS ai_store_billing_configs_store_id_key;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_store_billing_per_store
    ON ai_store_billing_configs (store_id) WHERE store_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_store_billing_per_org
    ON ai_store_billing_configs (organization_id) WHERE store_id IS NULL;

-- ai_billing_invoices: was UNIQUE(store_id, year, month)
ALTER TABLE ai_billing_invoices
    DROP CONSTRAINT IF EXISTS ai_billing_invoices_store_id_year_month_key;
DROP INDEX IF EXISTS ai_billing_invoices_store_id_year_month_key;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_billing_invoices_per_store
    ON ai_billing_invoices (store_id, year, month) WHERE store_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uniq_ai_billing_invoices_per_org
    ON ai_billing_invoices (organization_id, year, month) WHERE store_id IS NULL;

SQL);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
-- Drop the partial uniques (best-effort restore of original uniques skipped to avoid data conflicts).
DROP INDEX IF EXISTS uniq_ai_billing_invoices_per_store;
DROP INDEX IF EXISTS uniq_ai_billing_invoices_per_org;
DROP INDEX IF EXISTS uniq_ai_store_billing_per_store;
DROP INDEX IF EXISTS uniq_ai_store_billing_per_org;
DROP INDEX IF EXISTS uniq_ai_store_feature_per_store;
DROP INDEX IF EXISTS uniq_ai_store_feature_per_org;
DROP INDEX IF EXISTS uniq_ai_monthly_usage_per_store;
DROP INDEX IF EXISTS uniq_ai_monthly_usage_per_org;
DROP INDEX IF EXISTS uniq_ai_daily_usage_per_store;
DROP INDEX IF EXISTS uniq_ai_daily_usage_per_org;

DROP INDEX IF EXISTS idx_ai_chat_messages_org;
DROP INDEX IF EXISTS idx_ai_store_feature_configs_org;
DROP INDEX IF EXISTS idx_ai_cache_org;
DROP INDEX IF EXISTS idx_ai_feedback_org;
DROP INDEX IF EXISTS idx_ai_suggestions_org_status;

ALTER TABLE ai_chat_messages              DROP COLUMN IF EXISTS store_id, DROP COLUMN IF EXISTS organization_id;
ALTER TABLE ai_store_feature_configs      DROP COLUMN IF EXISTS organization_id;
ALTER TABLE ai_cache                      DROP COLUMN IF EXISTS organization_id;
ALTER TABLE ai_feedback                   DROP COLUMN IF EXISTS organization_id;
ALTER TABLE ai_suggestions                DROP COLUMN IF EXISTS organization_id;
SQL);
    }
};
