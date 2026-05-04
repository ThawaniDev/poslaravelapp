<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'idempotency_key')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 64)
                  ->nullable()
                  ->after('transaction_number')
                  ->comment('Client-provided idempotency key for safe offline replay');

            $table->unique(['store_id', 'idempotency_key'], 'transactions_store_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_store_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
