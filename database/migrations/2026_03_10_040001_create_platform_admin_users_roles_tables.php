<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: Admin Users & Roles
 *
 * Tables: admin_users, admin_roles, admin_permissions, admin_role_permissions, admin_user_roles, admin_activity_logs
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
CREATE TABLE admin_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    avatar_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    two_factor_secret TEXT,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_confirmed_at TIMESTAMP,
    last_login_at TIMESTAMP,
    last_login_ip VARCHAR(45),
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    "group" VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_role_permissions (
    admin_role_id UUID NOT NULL REFERENCES admin_roles(id) ON DELETE CASCADE,
    admin_permission_id UUID NOT NULL REFERENCES admin_permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (admin_role_id, admin_permission_id)
);

CREATE TABLE admin_user_roles (
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    admin_role_id UUID NOT NULL REFERENCES admin_roles(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT NOW(),
    assigned_by UUID REFERENCES admin_users(id),
    PRIMARY KEY (admin_user_id, admin_role_id)
);

CREATE TABLE admin_activity_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    details JSONB,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('admin_users');
    }
};
