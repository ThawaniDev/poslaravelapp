<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->uuid('provider_payment_id')->nullable()->after('invoice_id');
            $table->foreign('provider_payment_id')->references('id')->on('provider_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->dropForeign(['provider_payment_id']);
            $table->dropColumn('provider_payment_id');
        });
    }
};
