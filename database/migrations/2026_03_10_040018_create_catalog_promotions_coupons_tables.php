<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CATALOG: Promotions & Coupons
 *
 * Tables: promotions, promotion_products, promotion_categories, promotion_customer_groups, coupon_codes, promotion_usage_log, bundle_products
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE promotions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(30) NOT NULL,
    discount_value DECIMAL(12,2),
    buy_quantity INT,
    get_quantity INT,
    get_discount_percent DECIMAL(5,2),
    bundle_price DECIMAL(12,2),
    min_order_total DECIMAL(12,2),
    min_item_quantity INT,
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    active_days JSONB DEFAULT '[]',
    active_time_from TIME,
    active_time_to TIME,
    max_uses INT,
    max_uses_per_customer INT,
    is_stackable BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    is_coupon BOOLEAN DEFAULT FALSE,
    usage_count INT DEFAULT 0,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE promotion_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE (promotion_id, product_id)
);

CREATE TABLE promotion_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    category_id UUID NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE (promotion_id, category_id)
);

CREATE TABLE promotion_customer_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    customer_group_id UUID NOT NULL,
    UNIQUE (promotion_id, customer_group_id)
);

CREATE TABLE coupon_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    code VARCHAR(30) NOT NULL UNIQUE,
    max_uses INT DEFAULT 1,
    usage_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE promotion_usage_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id),
    coupon_code_id UUID REFERENCES coupon_codes(id),
    order_id UUID NOT NULL,
    customer_id UUID,
    discount_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE bundle_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity INT DEFAULT 1
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_products');
        Schema::dropIfExists('promotion_usage_log');
        Schema::dropIfExists('coupon_codes');
        Schema::dropIfExists('promotion_customer_groups');
        Schema::dropIfExists('promotion_categories');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotions');
    }
};
