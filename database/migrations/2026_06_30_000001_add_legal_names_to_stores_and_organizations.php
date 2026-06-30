<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add legal_name_en and legal_name_ar to both stores and organizations.
 *
 * These are the official registered legal names (English and Arabic) as they
 * appear on government documents, e-invoices, and receipts. They are separate
 * from the trade/display name (`name` / `name_ar`) so the POS can print the
 * correct legal entity name on ZATCA-compliant invoices without polluting the
 * store's customer-facing display name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('legal_name_en', 255)->nullable()->after('name_ar');
            $table->string('legal_name_ar', 255)->nullable()->after('legal_name_en');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('legal_name_en', 255)->nullable()->after('name_ar');
            $table->string('legal_name_ar', 255)->nullable()->after('legal_name_en');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['legal_name_en', 'legal_name_ar']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['legal_name_en', 'legal_name_ar']);
        });
    }
};
