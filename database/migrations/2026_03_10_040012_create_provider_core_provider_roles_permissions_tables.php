<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROVIDER CORE: Provider Roles & Permissions
 *
 * Tables: provider_permissions, default_role_templates, default_role_template_permissions, custom_role_package_config, pin_overrides, role_audit_log
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
CREATE TABLE provider_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL UNIQUE,
    "group" VARCHAR(30) NOT NULL,
    description VARCHAR(255),
    description_ar VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE default_role_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL,
    name_ar VARCHAR(50),
    slug VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255),
    description_ar VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE default_role_template_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    default_role_template_id UUID NOT NULL REFERENCES default_role_templates(id) ON DELETE CASCADE,
    provider_permission_id UUID NOT NULL REFERENCES provider_permissions(id) ON DELETE CASCADE,
    UNIQUE (default_role_template_id, provider_permission_id)
);

CREATE TABLE custom_role_package_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL UNIQUE REFERENCES subscription_plans(id),
    is_custom_roles_enabled BOOLEAN DEFAULT FALSE,
    max_custom_roles INT NOT NULL DEFAULT 0
);

CREATE TABLE pin_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    requesting_user_id UUID NOT NULL REFERENCES users(id),
    authorizing_user_id UUID NOT NULL REFERENCES users(id),
    permission_code VARCHAR(125) NOT NULL,
    action_context JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE role_audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_id UUID NOT NULL REFERENCES users(id),
    action VARCHAR(50) NOT NULL,
    role_id BIGINT REFERENCES roles(id),
    details JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_provider_permissions_group ON provider_permissions ("group");
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_audit_log');
        Schema::dropIfExists('pin_overrides');
        Schema::dropIfExists('custom_role_package_config');
        Schema::dropIfExists('default_role_template_permissions');
        Schema::dropIfExists('default_role_templates');
        Schema::dropIfExists('provider_permissions');
    }
};
