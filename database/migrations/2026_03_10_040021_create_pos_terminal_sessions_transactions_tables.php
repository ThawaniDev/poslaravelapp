<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS TERMINAL: Sessions & Transactions
 *
 * Tables: pos_sessions, transactions, transaction_items, held_carts, exchange_transactions, tax_exemptions
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
CREATE TABLE pos_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    opening_cash DECIMAL(12,2) NOT NULL,
    closing_cash DECIMAL(12,2),
    expected_cash DECIMAL(12,2),
    cash_difference DECIMAL(12,2),
    total_cash_sales DECIMAL(12,2) DEFAULT 0,
    total_card_sales DECIMAL(12,2) DEFAULT 0,
    total_other_sales DECIMAL(12,2) DEFAULT 0,
    total_refunds DECIMAL(12,2) DEFAULT 0,
    total_voids DECIMAL(12,2) DEFAULT 0,
    transaction_count INT DEFAULT 0,
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    z_report_printed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    pos_session_id UUID NOT NULL REFERENCES pos_sessions(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    customer_id UUID REFERENCES customers(id),
    transaction_number VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'sale',
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL,
    tip_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    is_tax_exempt BOOLEAN DEFAULT FALSE,
    return_transaction_id UUID REFERENCES transactions(id),
    external_type VARCHAR(30),
    external_id VARCHAR(100),
    notes TEXT,
    zatca_uuid UUID UNIQUE,
    zatca_hash TEXT,
    zatca_qr_code TEXT,
    zatca_status VARCHAR(20) DEFAULT 'pending',
    sync_status VARCHAR(20) DEFAULT 'pending',
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE TABLE transaction_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    barcode VARCHAR(50),
    product_name VARCHAR(255) NOT NULL,
    product_name_ar VARCHAR(255),
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2),
    discount_amount DECIMAL(12,2) DEFAULT 0,
    discount_type VARCHAR(20),
    discount_value DECIMAL(12,2),
    tax_rate DECIMAL(5,2) DEFAULT 15.00,
    tax_amount DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    serial_number VARCHAR(100),
    batch_number VARCHAR(100),
    expiry_date DATE,
    modifier_selections JSONB,
    notes TEXT,
    is_return_item BOOLEAN DEFAULT FALSE,
    age_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE held_carts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    customer_id UUID REFERENCES customers(id),
    cart_data JSONB NOT NULL,
    label VARCHAR(100),
    held_at TIMESTAMP DEFAULT NOW(),
    recalled_at TIMESTAMP,
    recalled_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE exchange_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_transaction_id UUID NOT NULL REFERENCES transactions(id),
    sale_transaction_id UUID NOT NULL REFERENCES transactions(id),
    net_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tax_exemptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(id),
    exemption_type VARCHAR(30) NOT NULL,
    customer_tax_id VARCHAR(50),
    certificate_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_exemptions');
        Schema::dropIfExists('exchange_transactions');
        Schema::dropIfExists('held_carts');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('pos_sessions');
    }
};
