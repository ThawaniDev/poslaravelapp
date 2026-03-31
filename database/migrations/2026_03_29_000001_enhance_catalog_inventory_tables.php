<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance Catalog & Inventory tables with missing columns and new tables.
 *
 * New columns on products: offer_price, offer_start, offer_end, min_order_qty, max_order_qty
 * New columns on categories: description, description_ar
 * New columns on suppliers: contact_person, tax_number, payment_terms
 * New tables: stocktakes, stocktake_items, waste_records
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
-- ─── Enhance products ─────────────────────────────────────
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS offer_price DECIMAL(12,2),
    ADD COLUMN IF NOT EXISTS offer_start DATE,
    ADD COLUMN IF NOT EXISTS offer_end DATE,
    ADD COLUMN IF NOT EXISTS min_order_qty DECIMAL(12,3) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS max_order_qty DECIMAL(12,3);

-- ─── Enhance categories ───────────────────────────────────
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS description TEXT,
    ADD COLUMN IF NOT EXISTS description_ar TEXT;

-- ─── Enhance suppliers ────────────────────────────────────
ALTER TABLE suppliers
    ADD COLUMN IF NOT EXISTS contact_person VARCHAR(255),
    ADD COLUMN IF NOT EXISTS tax_number VARCHAR(50),
    ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(100);

-- ─── Stocktake workflow ───────────────────────────────────
CREATE TABLE IF NOT EXISTS stocktakes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    reference_number VARCHAR(50),
    type VARCHAR(20) DEFAULT 'full',
    status VARCHAR(20) DEFAULT 'in_progress',
    category_id UUID REFERENCES categories(id),
    notes TEXT,
    started_by UUID NOT NULL REFERENCES users(id),
    completed_by UUID REFERENCES users(id),
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS stocktake_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    stocktake_id UUID NOT NULL REFERENCES stocktakes(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    expected_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
    counted_qty DECIMAL(12,3),
    variance DECIMAL(12,3),
    cost_impact DECIMAL(14,2),
    notes TEXT,
    counted_at TIMESTAMP
);

-- ─── Waste tracking ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS waste_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4),
    reason VARCHAR(50) NOT NULL,
    batch_number VARCHAR(100),
    notes TEXT,
    recorded_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_records');
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('stocktakes');

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::unprepared(<<<'SQL'
ALTER TABLE products
    DROP COLUMN IF EXISTS offer_price,
    DROP COLUMN IF EXISTS offer_start,
    DROP COLUMN IF EXISTS offer_end,
    DROP COLUMN IF EXISTS min_order_qty,
    DROP COLUMN IF EXISTS max_order_qty;
ALTER TABLE categories
    DROP COLUMN IF EXISTS description,
    DROP COLUMN IF EXISTS description_ar;
ALTER TABLE suppliers
    DROP COLUMN IF EXISTS contact_person,
    DROP COLUMN IF EXISTS tax_number,
    DROP COLUMN IF EXISTS payment_terms;
SQL);
        }
    }
};
