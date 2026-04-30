<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CUSTOMERS: Nice-to-Have Features
 *
 * Tables: cfd_configurations, signage_playlists, appointments, gift_registries, gift_registry_items, wishlists, loyalty_challenges, loyalty_badges, loyalty_tiers, customer_challenge_progress, customer_badges
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
CREATE TABLE cfd_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    is_enabled BOOLEAN DEFAULT false,
    target_monitor VARCHAR(50) DEFAULT 'secondary',
    theme_config JSONB DEFAULT '{}',
    idle_content JSONB DEFAULT '[]',
    idle_rotation_seconds INTEGER DEFAULT 10,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE signage_playlists (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(100) NOT NULL,
    slides JSONB NOT NULL,
    schedule JSONB,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE appointments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    staff_id UUID REFERENCES staff_users(id),
    service_product_id UUID NOT NULL REFERENCES products(id),
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(20) DEFAULT 'scheduled',
    notes TEXT,
    reminder_sent BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_registries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    name VARCHAR(100) NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    event_date DATE,
    share_code VARCHAR(20) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_registry_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    registry_id UUID NOT NULL REFERENCES gift_registries(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity_desired INTEGER DEFAULT 1,
    quantity_purchased INTEGER DEFAULT 0,
    purchased_by_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE wishlists (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    product_id UUID NOT NULL REFERENCES products(id),
    added_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, product_id)
);

CREATE TABLE loyalty_challenges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    challenge_type VARCHAR(30) NOT NULL,
    target_value DECIMAL(12,2) NOT NULL,
    reward_type VARCHAR(20) NOT NULL,
    reward_value DECIMAL(12,2),
    reward_badge_id UUID,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE loyalty_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name_ar VARCHAR(50) NOT NULL,
    name_en VARCHAR(50) NOT NULL,
    icon_url VARCHAR(500),
    description_ar TEXT,
    description_en TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE loyalty_tiers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    tier_name_ar VARCHAR(50) NOT NULL,
    tier_name_en VARCHAR(50) NOT NULL,
    tier_order INTEGER NOT NULL,
    min_points INTEGER NOT NULL,
    benefits JSONB DEFAULT '{}',
    icon_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, tier_order)
);

CREATE TABLE customer_challenge_progress (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    challenge_id UUID NOT NULL REFERENCES loyalty_challenges(id),
    current_value DECIMAL(12,2) DEFAULT 0,
    is_completed BOOLEAN DEFAULT false,
    completed_at TIMESTAMP,
    reward_claimed BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, challenge_id)
);

CREATE TABLE customer_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    badge_id UUID NOT NULL REFERENCES loyalty_badges(id),
    earned_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, badge_id)
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_badges');
        Schema::dropIfExists('customer_challenge_progress');
        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('loyalty_badges');
        Schema::dropIfExists('loyalty_challenges');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('gift_registry_items');
        Schema::dropIfExists('gift_registries');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('signage_playlists');
        Schema::dropIfExists('cfd_configurations');
    }
};
