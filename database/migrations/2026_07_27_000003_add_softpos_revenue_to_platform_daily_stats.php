<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add SoftPOS-specific revenue metrics to platform_daily_stats.
 *
 *  softpos_transaction_count — number of SoftPOS txns recorded on that day
 *  softpos_volume            — total transaction amount on that day
 *  softpos_platform_fees     — total fees collected from merchants
 *  softpos_gateway_fees      — total fees paid to EdfaPay/gateway
 *  softpos_margin            — net daily SoftPOS margin (platform_fees − gateway_fees)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_daily_stats', function (Blueprint $table) {
            $table->unsignedInteger('softpos_transaction_count')
                ->default(0)
                ->after('refund_count');

            $table->decimal('softpos_volume', 15, 3)
                ->default(0)
                ->after('softpos_transaction_count');

            $table->decimal('softpos_platform_fees', 15, 3)
                ->default(0)
                ->after('softpos_volume');

            $table->decimal('softpos_gateway_fees', 15, 3)
                ->default(0)
                ->after('softpos_platform_fees');

            $table->decimal('softpos_margin', 15, 3)
                ->default(0)
                ->after('softpos_gateway_fees');
        });
    }

    public function down(): void
    {
        Schema::table('platform_daily_stats', function (Blueprint $table) {
            $table->dropColumn([
                'softpos_transaction_count',
                'softpos_volume',
                'softpos_platform_fees',
                'softpos_gateway_fees',
                'softpos_margin',
            ]);
        });
    }
};
