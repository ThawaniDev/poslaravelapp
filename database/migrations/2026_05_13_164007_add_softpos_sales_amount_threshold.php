<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Amount threshold on the plan: how much total SoftPOS sales volume
        // the organisation must process in the period to earn a free subscription.
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('softpos_free_threshold_amount', 12, 3)
                ->nullable()
                ->after('softpos_free_threshold')
                ->comment('Total SoftPOS sales amount (in plan currency) required to qualify for free subscription');
        });

        // Running total of SoftPOS sales on the subscription for the current period.
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->decimal('softpos_sales_total', 12, 3)
                ->default(0)
                ->after('softpos_transaction_count')
                ->comment('Accumulated SoftPOS sales amount in the current threshold period');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('softpos_free_threshold_amount');
        });

        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->dropColumn('softpos_sales_total');
        });
    }
};
