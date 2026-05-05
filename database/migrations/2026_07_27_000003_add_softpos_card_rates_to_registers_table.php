<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add percentage-rate columns for Visa / Mastercard / Amex SoftPOS transactions.
 *
 * Visa/MC fee model (bilateral, mixed):
 *   platform_fee = (amount × card_merchant_rate) + card_merchant_fee
 *   gateway_fee  = (amount × card_gateway_rate)  + card_gateway_fee
 *   margin       = platform_fee − gateway_fee
 *
 * When card_merchant_rate = 0 the model degrades to the previous
 * fixed-only behaviour (backward compatible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->decimal('softpos_card_merchant_rate', 8, 6)
                ->default(0.000000)
                ->after('softpos_mada_gateway_rate')
                ->comment('Percentage rate charged to merchant per Visa/MC/Amex txn (e.g. 0.025 = 2.5%)');

            $table->decimal('softpos_card_gateway_rate', 8, 6)
                ->default(0.000000)
                ->after('softpos_card_merchant_rate')
                ->comment('Percentage rate paid to gateway per Visa/MC/Amex txn (e.g. 0.020 = 2.0%)');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn(['softpos_card_merchant_rate', 'softpos_card_gateway_rate']);
        });
    }
};
