<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ZATCA Phase 2 — production-grade engine extensions.
 *
 *  - zatca_devices: EGS units (one per POS terminal). Tracks activation PIN,
 *    hardware serial, environment, tamper flag, current ICV and PIH.
 *  - zatca_invoices extensions: UUIDv4, ICV, device_id, customer_id, B2B flag,
 *    reference_invoice_uuid (credit/debit notes), adjustment_reason,
 *    submission attempts/timing, cleared XML + signed hash + base64 QR,
 *    rejection details.
 *  - zatca_certificates extensions: CSR + private key PEM + handshake state.
 *
 * No-op on sqlite (test schema bootstraps the same columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('zatca_devices')) {
            Schema::create('zatca_devices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('device_uuid', 64)->unique();
                $table->string('hardware_serial', 128)->nullable();
                $table->string('activation_code', 32)->nullable()->index();
                $table->timestamp('activated_at')->nullable();
                $table->string('environment', 20)->default('sandbox');
                $table->string('status', 20)->default('pending');
                $table->boolean('is_tampered')->default(false);
                $table->text('tamper_reason')->nullable();
                $table->unsignedBigInteger('current_icv')->default(0);
                $table->string('current_pih', 128)->nullable();
                $table->uuid('certificate_id')->nullable();
                $table->timestamps();
                $table->index(['store_id', 'status']);
            });
        }

        $invoiceCols = [
            'uuid' => "ALTER TABLE zatca_invoices ADD COLUMN uuid VARCHAR(36) NULL",
            'icv' => "ALTER TABLE zatca_invoices ADD COLUMN icv BIGINT NULL",
            'device_id' => "ALTER TABLE zatca_invoices ADD COLUMN device_id UUID NULL",
            'customer_id' => "ALTER TABLE zatca_invoices ADD COLUMN customer_id UUID NULL",
            'is_b2b' => "ALTER TABLE zatca_invoices ADD COLUMN is_b2b BOOLEAN NOT NULL DEFAULT FALSE",
            'reference_invoice_uuid' => "ALTER TABLE zatca_invoices ADD COLUMN reference_invoice_uuid VARCHAR(36) NULL",
            'adjustment_reason' => "ALTER TABLE zatca_invoices ADD COLUMN adjustment_reason VARCHAR(255) NULL",
            'cleared_xml' => "ALTER TABLE zatca_invoices ADD COLUMN cleared_xml TEXT NULL",
            'cleared_hash' => "ALTER TABLE zatca_invoices ADD COLUMN cleared_hash VARCHAR(128) NULL",
            'tlv_qr_base64' => "ALTER TABLE zatca_invoices ADD COLUMN tlv_qr_base64 TEXT NULL",
            'submission_attempts' => "ALTER TABLE zatca_invoices ADD COLUMN submission_attempts INTEGER NOT NULL DEFAULT 0",
            'last_attempt_at' => "ALTER TABLE zatca_invoices ADD COLUMN last_attempt_at TIMESTAMP NULL",
            'next_attempt_at' => "ALTER TABLE zatca_invoices ADD COLUMN next_attempt_at TIMESTAMP NULL",
            'rejection_errors' => "ALTER TABLE zatca_invoices ADD COLUMN rejection_errors JSON NULL",
            'flow' => "ALTER TABLE zatca_invoices ADD COLUMN flow VARCHAR(20) NOT NULL DEFAULT 'reporting'",
        ];
        foreach ($invoiceCols as $col => $sql) {
            if (! Schema::hasColumn('zatca_invoices', $col)) {
                DB::statement($sql);
            }
        }

        $certCols = [
            'csr_pem' => "ALTER TABLE zatca_certificates ADD COLUMN csr_pem TEXT NULL",
            'private_key_pem' => "ALTER TABLE zatca_certificates ADD COLUMN private_key_pem TEXT NULL",
            'compliance_request_id' => "ALTER TABLE zatca_certificates ADD COLUMN compliance_request_id VARCHAR(128) NULL",
            'pcsid' => "ALTER TABLE zatca_certificates ADD COLUMN pcsid VARCHAR(100) NULL",
            'public_key_pem' => "ALTER TABLE zatca_certificates ADD COLUMN public_key_pem TEXT NULL",
            'updated_at' => "ALTER TABLE zatca_certificates ADD COLUMN updated_at TIMESTAMP NULL",
        ];
        foreach ($certCols as $col => $sql) {
            if (! Schema::hasColumn('zatca_certificates', $col)) {
                DB::statement($sql);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_devices');
    }
};
