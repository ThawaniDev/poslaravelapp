<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-terminal SoftPOS billing rate columns to the registers table.
 *
 * Two fee tiers are supported:
 *
 *   Mada (percentage-based)
 *     softpos_mada_merchant_rate — percentage rate charged to the merchant (e.g. 0.006 = 0.6 %)
 *     softpos_mada_gateway_rate  — percentage rate we pay EdfaPay         (e.g. 0.004 = 0.4 %)
 *     margin = (merchant_rate − gateway_rate) × amount
 *
 *   Visa / Mastercard (fixed per transaction)
 *     softpos_card_merchant_fee  — fixed SAR amount charged to the merchant  (e.g. 1.000 SAR)
 *     softpos_card_gateway_fee   — fixed SAR amount we pay to the gateway     (e.g. 0.500 SAR)
 *     margin = card_merchant_fee − card_gateway_fee
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            // Mada — percentage-based bilateral rates
            $table->decimal('softpos_mada_merchant_rate', 8, 6)
                ->default(0.006000)
                ->after('wameed_margin_percentage')
                ->comment('Rate charged to merchant per SAR on Mada transactions (e.g. 0.006 = 0.6%)');

            $table->decimal('softpos_mada_gateway_rate', 8, 6)
                ->default(0.004000)
                ->after('softpos_mada_merchant_rate')
                ->comment('Rate paid to EdfaPay per SAR on Mada transactions (e.g. 0.004 = 0.4%)');

            // Visa / Mastercard — fixed fee per transaction
            $table->decimal('softpos_card_merchant_fee', 10, 3)
                ->default(1.000)
                ->after('softpos_mada_gateway_rate')
                ->comment('Fixed SAR fee charged to merchant per Visa/MC transaction');

            $table->decimal('softpos_card_gateway_fee', 10, 3)
                ->default(0.500)
                ->after('softpos_card_merchant_fee')
                ->comment('Fixed SAR fee paid to gateway per Visa/MC transaction');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn([
                'softpos_mada_merchant_rate',
                'softpos_mada_gateway_rate',
                'softpos_card_merchant_fee',
                'softpos_card_gateway_fee',
            ]);
        });
    }
};
