<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ORDERS: Payments & Finance
 *
 * Tables: payments, cash_sessions, cash_events, expenses, gift_cards, gift_card_transactions, refunds
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    cash_tendered DECIMAL(12,2),
    change_given DECIMAL(12,2),
    tip_amount DECIMAL(12,2) DEFAULT 0,
    card_brand VARCHAR(30),
    card_last_four VARCHAR(4),
    card_auth_code VARCHAR(50),
    card_reference VARCHAR(100),
    gift_card_code VARCHAR(50),
    coupon_code VARCHAR(50),
    loyalty_points_used INT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE cash_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID,
    opened_by UUID NOT NULL REFERENCES users(id),
    closed_by UUID REFERENCES users(id),
    opening_float DECIMAL(12,2) NOT NULL,
    expected_cash DECIMAL(12,2),
    actual_cash DECIMAL(12,2),
    variance DECIMAL(12,2),
    status VARCHAR(20) DEFAULT 'open',
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    close_notes TEXT
);

CREATE TABLE cash_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cash_session_id UUID NOT NULL REFERENCES cash_sessions(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    notes TEXT,
    performed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    cash_session_id UUID REFERENCES cash_sessions(id),
    amount DECIMAL(12,2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    receipt_image_url TEXT,
    recorded_by UUID NOT NULL REFERENCES users(id),
    expense_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    code VARCHAR(20) NOT NULL UNIQUE,
    barcode VARCHAR(50),
    initial_amount DECIMAL(12,2) NOT NULL,
    balance DECIMAL(12,2) NOT NULL,
    recipient_name VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active',
    issued_by UUID NOT NULL REFERENCES users(id),
    issued_at_store UUID NOT NULL REFERENCES stores(id),
    expires_at DATE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE gift_card_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gift_card_id UUID NOT NULL REFERENCES gift_cards(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    payment_id UUID REFERENCES payments(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    performed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE refunds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_id UUID NOT NULL REFERENCES returns(id),
    payment_id UUID REFERENCES payments(id),
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'completed',
    processed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('cash_events');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('payments');
    }
};
