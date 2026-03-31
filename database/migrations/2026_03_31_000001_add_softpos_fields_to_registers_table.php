<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('registers', 'softpos_enabled')) {
            return; // columns already present (e.g. SQLite test schema)
        }

        Schema::table('registers', function (Blueprint $table) {
            // ── SoftPOS Core ─────────────────────────────────────────
            $table->boolean('softpos_enabled')->default(false)->after('is_active');
            $table->string('nearpay_tid', 50)->nullable()->after('softpos_enabled')
                  ->comment('Terminal ID issued by acquirer (HALA or bank)');
            $table->string('nearpay_mid', 50)->nullable()->after('nearpay_tid')
                  ->comment('Merchant ID at acquirer');
            $table->string('nearpay_auth_key', 255)->nullable()->after('nearpay_mid')
                  ->comment('NearPay SDK auth/JWT key');

            // ── Acquirer Info ────────────────────────────────────────
            $table->string('acquirer_source', 30)->nullable()->after('nearpay_auth_key')
                  ->comment('hala, bank_rajhi, bank_snb, geidea, other');
            $table->string('acquirer_name', 100)->nullable()->after('acquirer_source');
            $table->string('acquirer_reference', 100)->nullable()->after('acquirer_name')
                  ->comment('External ref from acquirer for this terminal');

            // ── Device Hardware ──────────────────────────────────────
            $table->string('device_model', 100)->nullable()->after('acquirer_reference');
            $table->string('os_version', 30)->nullable()->after('device_model');
            $table->boolean('nfc_capable')->default(false)->after('os_version');
            $table->string('serial_number', 100)->nullable()->after('nfc_capable');

            // ── Transaction Fee Configuration ────────────────────────
            $table->string('fee_profile', 30)->default('standard')->after('serial_number')
                  ->comment('standard, custom, promotional');
            $table->decimal('fee_mada_percentage', 5, 4)->default(0.0150)->after('fee_profile')
                  ->comment('e.g. 0.0150 = 1.50%');
            $table->decimal('fee_visa_mc_percentage', 5, 4)->default(0.0200)->after('fee_mada_percentage')
                  ->comment('e.g. 0.0200 = 2.00%');
            $table->decimal('fee_flat_per_txn', 8, 2)->default(0.00)->after('fee_visa_mc_percentage')
                  ->comment('Flat fee per transaction in SAR');
            $table->decimal('thawani_margin_percentage', 5, 4)->default(0.0040)->after('fee_flat_per_txn')
                  ->comment('Thawani markup e.g. 0.0040 = 0.40%');

            // ── Settlement ───────────────────────────────────────────
            $table->string('settlement_cycle', 10)->default('T+1')->after('thawani_margin_percentage')
                  ->comment('T+1, T+2, weekly, etc.');
            $table->string('settlement_bank_name', 100)->nullable()->after('settlement_cycle');
            $table->string('settlement_iban', 34)->nullable()->after('settlement_bank_name');

            // ── Activation & Status ──────────────────────────────────
            $table->string('softpos_status', 20)->default('pending')->after('settlement_iban')
                  ->comment('pending, active, suspended, deactivated');
            $table->timestamp('softpos_activated_at')->nullable()->after('softpos_status');
            $table->timestamp('last_transaction_at')->nullable()->after('softpos_activated_at');
            $table->text('admin_notes')->nullable()->after('last_transaction_at');

            // ── Indexes ──────────────────────────────────────────────
            $table->index('nearpay_tid');
            $table->index('acquirer_source');
            $table->index('softpos_status');
            $table->index('softpos_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropIndex(['nearpay_tid']);
            $table->dropIndex(['acquirer_source']);
            $table->dropIndex(['softpos_status']);
            $table->dropIndex(['softpos_enabled']);

            $table->dropColumn([
                'softpos_enabled',
                'nearpay_tid',
                'nearpay_mid',
                'nearpay_auth_key',
                'acquirer_source',
                'acquirer_name',
                'acquirer_reference',
                'device_model',
                'os_version',
                'nfc_capable',
                'serial_number',
                'fee_profile',
                'fee_mada_percentage',
                'fee_visa_mc_percentage',
                'fee_flat_per_txn',
                'thawani_margin_percentage',
                'settlement_cycle',
                'settlement_bank_name',
                'settlement_iban',
                'softpos_status',
                'softpos_activated_at',
                'last_transaction_at',
                'admin_notes',
            ]);
        });
    }
};
