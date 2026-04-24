<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Rule #10 — B2B customers (with tax_registration_number) must have
 * their VAT number embedded on the ZATCA tax invoice. We store the buyer
 * VAT number on the invoice row so it is fixed at the time of submission
 * and survives later edits to the customer profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // SQLite test schema is built up by the test bootstrap migration; this
            // migration is a no-op in that environment, mirroring the original
            // ZATCA migration's pattern.
            return;
        }

        if (! Schema::hasColumn('zatca_invoices', 'buyer_tax_number')) {
            DB::statement('ALTER TABLE zatca_invoices ADD COLUMN buyer_tax_number VARCHAR(50) NULL');
        }
        if (! Schema::hasColumn('zatca_invoices', 'buyer_name')) {
            DB::statement('ALTER TABLE zatca_invoices ADD COLUMN buyer_name VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if (Schema::hasColumn('zatca_invoices', 'buyer_tax_number')) {
            DB::statement('ALTER TABLE zatca_invoices DROP COLUMN buyer_tax_number');
        }
        if (Schema::hasColumn('zatca_invoices', 'buyer_name')) {
            DB::statement('ALTER TABLE zatca_invoices DROP COLUMN buyer_name');
        }
    }
};
