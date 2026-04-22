<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->uuid('actor_id')->nullable();
            $table->string('action', 64);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->index(['transaction_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_audit_logs');
    }
};
