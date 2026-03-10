<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CUSTOMERS: Core
 *
 * Tables: customers, customer_groups, loyalty_transactions, store_credit_transactions, loyalty_config, digital_receipt_log
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE customers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255),
    address TEXT,
    date_of_birth DATE,
    loyalty_code VARCHAR(20) UNIQUE,
    loyalty_points INT DEFAULT 0,
    store_credit_balance DECIMAL(12,2) DEFAULT 0,
    group_id UUID,
    tax_registration_number VARCHAR(50),
    notes TEXT,
    total_spend DECIMAL(14,2) DEFAULT 0,
    visit_count INT DEFAULT 0,
    last_visit_at TIMESTAMP,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE TABLE customer_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(100) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE loyalty_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    type VARCHAR(20) NOT NULL,
    points INT NOT NULL,
    balance_after INT NOT NULL,
    order_id UUID,
    notes VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE store_credit_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    order_id UUID,
    payment_id UUID,
    notes VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE loyalty_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL UNIQUE REFERENCES organizations(id),
    points_per_sar DECIMAL(5,2) DEFAULT 1,
    sar_per_point DECIMAL(8,4) DEFAULT 0.01,
    min_redemption_points INT DEFAULT 100,
    points_expiry_months INT DEFAULT 0,
    excluded_category_ids JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE digital_receipt_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL,
    customer_id UUID NOT NULL,
    channel VARCHAR(20) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'sent',
    sent_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_receipt_log');
        Schema::dropIfExists('loyalty_config');
        Schema::dropIfExists('store_credit_transactions');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('customers');
    }
};
