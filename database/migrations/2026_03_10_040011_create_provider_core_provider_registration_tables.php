<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROVIDER CORE: Provider Registration
 *
 * Tables: provider_registrations, provider_notes, provider_limit_overrides, cancellation_reasons
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
CREATE TABLE provider_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_name VARCHAR(255) NOT NULL,
    organization_name_ar VARCHAR(255),
    owner_name VARCHAR(255) NOT NULL,
    owner_email VARCHAR(255) NOT NULL,
    owner_phone VARCHAR(50) NOT NULL,
    cr_number VARCHAR(50),
    vat_number VARCHAR(50),
    business_type_id UUID REFERENCES business_types(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by UUID REFERENCES admin_users(id),
    reviewed_at TIMESTAMP,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE provider_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    admin_user_id UUID NOT NULL REFERENCES admin_users(id),
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE provider_limit_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    limit_key VARCHAR(50) NOT NULL,
    override_value INT NOT NULL,
    reason TEXT,
    set_by UUID NOT NULL REFERENCES admin_users(id),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, limit_key)
);

CREATE TABLE cancellation_reasons (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    reason_category VARCHAR(30) NOT NULL,
    reason_text TEXT,
    cancelled_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_reasons');
        Schema::dropIfExists('provider_limit_overrides');
        Schema::dropIfExists('provider_notes');
        Schema::dropIfExists('provider_registrations');
    }
};
