<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WAMEED AI — Core AI Feature Tables
 *
 * Tables: ai_provider_configs, ai_feature_definitions, ai_store_feature_configs,
 *         ai_usage_logs, ai_daily_usage_summaries, ai_monthly_usage_summaries,
 *         ai_platform_daily_summaries, ai_prompts, ai_suggestions, ai_feedback, ai_cache
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'

-- ─── AI Provider Configs (platform-level) ────────────────────────────────
CREATE TABLE ai_provider_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider VARCHAR(50) NOT NULL DEFAULT 'openai',
    api_key_encrypted TEXT NOT NULL,
    default_model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    max_tokens_per_request INT NOT NULL DEFAULT 4096,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- ─── AI Feature Definitions (master list of 33 features) ─────────────────
CREATE TABLE ai_feature_definitions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    description TEXT,
    description_ar TEXT,
    category VARCHAR(50) NOT NULL,
    icon VARCHAR(100),
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    is_premium BOOLEAN NOT NULL DEFAULT FALSE,
    default_model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    default_max_tokens INT NOT NULL DEFAULT 2048,
    cost_per_request_estimate DECIMAL(10,6) NOT NULL DEFAULT 0.001,
    daily_limit INT NOT NULL DEFAULT 50,
    monthly_limit INT NOT NULL DEFAULT 500,
    requires_subscription_plan JSONB,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_feature_definitions_category ON ai_feature_definitions (category);
CREATE INDEX idx_ai_feature_definitions_slug ON ai_feature_definitions (slug);

-- ─── AI Store Feature Configs (per-store toggles) ────────────────────────
CREATE TABLE ai_store_feature_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    ai_feature_definition_id UUID NOT NULL REFERENCES ai_feature_definitions(id) ON DELETE CASCADE,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    daily_limit INT NOT NULL DEFAULT 100,
    monthly_limit INT NOT NULL DEFAULT 3000,
    custom_prompt_override TEXT,
    settings_json JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, ai_feature_definition_id)
);

CREATE INDEX idx_ai_store_feature_configs_store ON ai_store_feature_configs (store_id);

-- ─── AI Usage Logs (per-request granular tracking) ───────────────────────
CREATE TABLE ai_usage_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    ai_feature_definition_id UUID NOT NULL REFERENCES ai_feature_definitions(id) ON DELETE CASCADE,
    feature_slug VARCHAR(100) NOT NULL,
    model_used VARCHAR(100) NOT NULL,
    input_tokens INT NOT NULL DEFAULT 0,
    output_tokens INT NOT NULL DEFAULT 0,
    total_tokens INT NOT NULL DEFAULT 0,
    estimated_cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
    request_payload_hash VARCHAR(64),
    response_cached BOOLEAN NOT NULL DEFAULT FALSE,
    latency_ms INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'success',
    error_message TEXT,
    metadata_json JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_usage_logs_store_created ON ai_usage_logs (store_id, created_at);
CREATE INDEX idx_ai_usage_logs_feature_created ON ai_usage_logs (feature_slug, created_at);
CREATE INDEX idx_ai_usage_logs_org_created ON ai_usage_logs (organization_id, created_at);
CREATE INDEX idx_ai_usage_logs_status ON ai_usage_logs (status);

-- ─── AI Daily Usage Summaries (pre-aggregated per store) ─────────────────
CREATE TABLE ai_daily_usage_summaries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    total_requests INT NOT NULL DEFAULT 0,
    cached_requests INT NOT NULL DEFAULT 0,
    failed_requests INT NOT NULL DEFAULT 0,
    total_input_tokens BIGINT NOT NULL DEFAULT 0,
    total_output_tokens BIGINT NOT NULL DEFAULT 0,
    total_estimated_cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
    feature_breakdown_json JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, date)
);

CREATE INDEX idx_ai_daily_usage_store ON ai_daily_usage_summaries (store_id, date);

-- ─── AI Monthly Usage Summaries ──────────────────────────────────────────
CREATE TABLE ai_monthly_usage_summaries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    month DATE NOT NULL,
    total_requests INT NOT NULL DEFAULT 0,
    cached_requests INT NOT NULL DEFAULT 0,
    failed_requests INT NOT NULL DEFAULT 0,
    total_input_tokens BIGINT NOT NULL DEFAULT 0,
    total_output_tokens BIGINT NOT NULL DEFAULT 0,
    total_estimated_cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
    feature_breakdown_json JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, month)
);

-- ─── AI Platform Daily Summaries (platform-wide analytics) ───────────────
CREATE TABLE ai_platform_daily_summaries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    date DATE NOT NULL UNIQUE,
    total_stores_active INT NOT NULL DEFAULT 0,
    total_requests INT NOT NULL DEFAULT 0,
    total_tokens BIGINT NOT NULL DEFAULT 0,
    total_estimated_cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
    feature_breakdown_json JSONB,
    top_stores_json JSONB,
    error_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    avg_latency_ms INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ─── AI Prompts (server-side, versioned) ─────────────────────────────────
CREATE TABLE ai_prompts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    feature_slug VARCHAR(100) NOT NULL,
    version INT NOT NULL DEFAULT 1,
    system_prompt TEXT NOT NULL,
    user_prompt_template TEXT NOT NULL,
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    max_tokens INT NOT NULL DEFAULT 2048,
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.7,
    response_format VARCHAR(20) NOT NULL DEFAULT 'json_object',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by UUID,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(feature_slug, version)
);

CREATE INDEX idx_ai_prompts_slug_active ON ai_prompts (feature_slug, is_active);

-- ─── AI Suggestions (stored async suggestions) ──────────────────────────
CREATE TABLE ai_suggestions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    feature_slug VARCHAR(100) NOT NULL,
    suggestion_type VARCHAR(100) NOT NULL,
    title VARCHAR(500) NOT NULL,
    title_ar VARCHAR(500),
    content_json JSONB NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    accepted_at TIMESTAMP,
    dismissed_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_suggestions_store_status ON ai_suggestions (store_id, status, created_at);
CREATE INDEX idx_ai_suggestions_feature ON ai_suggestions (feature_slug);

-- ─── AI Feedback (user ratings on AI responses) ─────────────────────────
CREATE TABLE ai_feedback (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ai_usage_log_id UUID NOT NULL REFERENCES ai_usage_logs(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    feedback_text TEXT,
    is_helpful BOOLEAN,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_feedback_store ON ai_feedback (store_id);
CREATE INDEX idx_ai_feedback_log ON ai_feedback (ai_usage_log_id);

-- ─── AI Cache (long-term response cache) ────────────────────────────────
CREATE TABLE ai_cache (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cache_key VARCHAR(255) NOT NULL UNIQUE,
    feature_slug VARCHAR(100) NOT NULL,
    store_id UUID REFERENCES stores(id) ON DELETE CASCADE,
    response_text TEXT NOT NULL,
    tokens_used INT NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_cache_key ON ai_cache (cache_key);
CREATE INDEX idx_ai_cache_expires ON ai_cache (expires_at);
CREATE INDEX idx_ai_cache_feature ON ai_cache (feature_slug);

SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cache');
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_suggestions');
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_platform_daily_summaries');
        Schema::dropIfExists('ai_monthly_usage_summaries');
        Schema::dropIfExists('ai_daily_usage_summaries');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_store_feature_configs');
        Schema::dropIfExists('ai_feature_definitions');
        Schema::dropIfExists('ai_provider_configs');
    }
};
