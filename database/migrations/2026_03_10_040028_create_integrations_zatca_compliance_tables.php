<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INTEGRATIONS: ZATCA Compliance
 *
 * Tables: zatca_invoices, zatca_certificates
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE zatca_invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    invoice_number VARCHAR(50) NOT NULL,
    invoice_type VARCHAR(20) NOT NULL,
    invoice_xml TEXT NOT NULL,
    invoice_hash VARCHAR(64) NOT NULL,
    previous_invoice_hash VARCHAR(64) NOT NULL,
    digital_signature TEXT NOT NULL,
    qr_code_data TEXT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    vat_amount DECIMAL(12,2) NOT NULL,
    submission_status VARCHAR(20) DEFAULT 'pending',
    zatca_response_code VARCHAR(10),
    zatca_response_message TEXT,
    submitted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE zatca_certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    certificate_type VARCHAR(20) NOT NULL,
    certificate_pem TEXT NOT NULL,
    ccsid VARCHAR(100) NOT NULL,
    issued_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_certificates');
        Schema::dropIfExists('zatca_invoices');
    }
};
