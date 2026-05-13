<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add EdfaPay card / transaction data columns to softpos_transactions.
 *
 * These fields are extracted from the raw SDK response and stored as dedicated
 * columns so they are queryable, filterable, and exportable without parsing
 * the JSON metadata blob.
 *
 *  approval_code          — bank authorisation code (auth_code in SDK)
 *  masked_card            — PAN like "5069 68** **** 0286" (credit_number)
 *  cardholder_name        — name on card (cardholder_name)
 *  card_expiry            — YYMM expiry from SDK (card_expiration_date)
 *  stan                   — System Trace Audit Number (used for reconciliation)
 *  acquirer_bank          — acquiring bank short code e.g. "RAJB"
 *  application_id         — EMV Application ID (AID) e.g. "A0000002281010"
 *  edfapay_transaction_id — EdfaPay's own UUID for the transaction (transaction_number)
 *  sdk_raw_response       — full transaction object from EdfaPay SDK (for audit/disputes)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('softpos_transactions', function (Blueprint $table) {
            $table->string('approval_code', 30)->nullable()
                ->after('transaction_ref')
                ->comment('Bank authorisation / approval code (auth_code from EdfaPay)');

            $table->string('masked_card', 30)->nullable()
                ->after('approval_code')
                ->comment('Masked PAN e.g. "5069 68** **** 0286" (credit_number from EdfaPay)');

            $table->string('cardholder_name', 100)->nullable()
                ->after('masked_card')
                ->comment('Cardholder name as returned by the SDK');

            $table->string('card_expiry', 10)->nullable()
                ->after('cardholder_name')
                ->comment('YYMM card expiry date from SDK (card_expiration_date)');

            $table->string('stan', 20)->nullable()
                ->after('card_expiry')
                ->comment('System Trace Audit Number — used for settlement reconciliation');

            $table->string('acquirer_bank', 50)->nullable()
                ->after('stan')
                ->comment('Acquiring bank short code e.g. "RAJB"');

            $table->string('application_id', 50)->nullable()
                ->after('acquirer_bank')
                ->comment('EMV Application ID (AID) e.g. "A0000002281010"');

            $table->string('edfapay_transaction_id', 100)->nullable()
                ->after('application_id')
                ->comment('EdfaPay internal transaction UUID (transaction_number from SDK)');

            $table->json('sdk_raw_response')->nullable()
                ->after('edfapay_transaction_id')
                ->comment('Full transaction object from the EdfaPay SDK response for audit / disputes');
        });
    }

    public function down(): void
    {
        Schema::table('softpos_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'approval_code',
                'masked_card',
                'cardholder_name',
                'card_expiry',
                'stan',
                'acquirer_bank',
                'application_id',
                'edfapay_transaction_id',
                'sdk_raw_response',
            ]);
        });
    }
};
