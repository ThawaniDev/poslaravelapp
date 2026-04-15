<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_payments', function (Blueprint $table) {
            $table->string('original_currency', 3)->nullable()->after('currency');
            $table->decimal('original_amount', 12, 2)->nullable()->after('original_currency');
            $table->decimal('exchange_rate_used', 10, 6)->nullable()->after('original_amount');
        });
    }

    public function down(): void
    {
        Schema::table('provider_payments', function (Blueprint $table) {
            $table->dropColumn(['original_currency', 'original_amount', 'exchange_rate_used']);
        });
    }
};
