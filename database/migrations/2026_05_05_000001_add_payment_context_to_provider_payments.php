<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_payments') || Schema::hasColumn('provider_payments', 'payment_context')) {
            return;
        }

        Schema::table('provider_payments', function (Blueprint $table) {
            $table->json('payment_context')->nullable()->after('gateway_response');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('provider_payments') || ! Schema::hasColumn('provider_payments', 'payment_context')) {
            return;
        }

        Schema::table('provider_payments', function (Blueprint $table) {
            $table->dropColumn('payment_context');
        });
    }
};