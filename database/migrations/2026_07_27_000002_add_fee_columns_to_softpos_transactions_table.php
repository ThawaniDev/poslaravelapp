<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-transaction fee tracking columns to softpos_transactions.
 *
 *  platform_fee — amount charged to the merchant for this transaction
 *  gateway_fee  — amount paid to the payment gateway (EdfaPay)
 *  margin       — platform profit  = platform_fee − gateway_fee
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('softpos_transactions', function (Blueprint $table) {
            $table->decimal('platform_fee', 10, 3)
                ->default(0.000)
                ->after('amount')
                ->comment('Fee collected from the merchant for this transaction');

            $table->decimal('gateway_fee', 10, 3)
                ->default(0.000)
                ->after('platform_fee')
                ->comment('Fee paid to the payment gateway for this transaction');

            $table->decimal('margin', 10, 3)
                ->default(0.000)
                ->after('gateway_fee')
                ->comment('Platform net margin = platform_fee - gateway_fee');

            $table->string('fee_type', 20)
                ->default('percentage')
                ->after('margin')
                ->comment('Fee calculation model: percentage (Mada) or fixed (Visa/MC)');
        });
    }

    public function down(): void
    {
        Schema::table('softpos_transactions', function (Blueprint $table) {
            $table->dropColumn(['platform_fee', 'gateway_fee', 'margin', 'fee_type']);
        });
    }
};
