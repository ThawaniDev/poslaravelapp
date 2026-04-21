<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('provider_payments')) {
            return;
        }
        Schema::table('provider_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_payments', 'original_currency')) {
                $table->string('original_currency', 3)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('provider_payments', 'original_amount')) {
                $table->decimal('original_amount', 12, 2)->nullable()->after('original_currency');
            }
            if (!Schema::hasColumn('provider_payments', 'exchange_rate_used')) {
                $table->decimal('exchange_rate_used', 10, 6)->nullable()->after('original_amount');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('provider_payments')) {
            return;
        }
        Schema::table('provider_payments', function (Blueprint $table) {
            $table->dropColumn(['original_currency', 'original_amount', 'exchange_rate_used']);
        });
    }
};
