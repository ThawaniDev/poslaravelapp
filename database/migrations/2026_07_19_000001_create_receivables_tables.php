<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('receivables')) {
            Schema::create('receivables', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('store_id');
                $table->uuid('customer_id');
                $table->string('reference_number', 100)->nullable();
                $table->string('receivable_type', 50);
                $table->string('source', 50);
                $table->string('description', 255)->nullable();
                $table->string('description_ar', 255)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('status', 50)->default('pending');
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('created_by');
                $table->uuid('settled_by')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->integer('sync_version')->default(1);
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations');
                $table->foreign('store_id')->references('id')->on('stores');
                $table->foreign('customer_id')->references('id')->on('customers');
                $table->foreign('created_by')->references('id')->on('users');
                $table->foreign('settled_by')->references('id')->on('users');

                $table->index('organization_id');
                $table->index('customer_id');
                $table->index('status');
                $table->index('due_date');
                $table->index(['organization_id', 'customer_id']);
                $table->index(['organization_id', 'status']);
            });
        }

        if (! Schema::hasTable('receivable_payments')) {
            Schema::create('receivable_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('receivable_id');
                $table->uuid('order_id')->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->decimal('amount', 12, 2);
                $table->text('notes')->nullable();
                $table->uuid('settled_by');
                $table->timestamp('settled_at');

                $table->foreign('receivable_id')->references('id')->on('receivables')->cascadeOnDelete();
                $table->foreign('order_id')->references('id')->on('orders');
                $table->foreign('settled_by')->references('id')->on('users');

                $table->index('receivable_id');
                $table->index('order_id');
            });
        }

        if (! Schema::hasTable('receivable_logs')) {
            Schema::create('receivable_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('receivable_id');
                $table->string('event', 50);
                $table->string('from_value')->nullable();
                $table->string('to_value')->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->text('note')->nullable();
                $table->json('meta')->nullable();
                $table->uuid('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('receivable_id')->references('id')->on('receivables')->cascadeOnDelete();
                $table->foreign('actor_id')->references('id')->on('users');

                $table->index('receivable_id');
                $table->index('event');
                $table->index(['receivable_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_logs');
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('receivables');
    }
};
