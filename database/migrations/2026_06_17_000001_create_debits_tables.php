<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('debits')) {
            Schema::create('debits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('store_id');
                $table->uuid('customer_id');
                $table->string('reference_number', 100)->nullable();
                $table->string('debit_type', 50);
                $table->string('source', 50);
                $table->string('description', 255)->nullable();
                $table->string('description_ar', 255)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('status', 50)->default('pending');
                $table->text('notes')->nullable();
                $table->uuid('created_by');
                $table->uuid('allocated_by')->nullable();
                $table->timestamp('allocated_at')->nullable();
                $table->integer('sync_version')->default(1);
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations');
                $table->foreign('store_id')->references('id')->on('stores');
                $table->foreign('customer_id')->references('id')->on('customers');
                $table->foreign('created_by')->references('id')->on('users');
                $table->foreign('allocated_by')->references('id')->on('users');

                $table->index('organization_id');
                $table->index('customer_id');
                $table->index('status');
                $table->index(['organization_id', 'customer_id']);
                $table->index(['organization_id', 'status']);
            });
        }

        if (! Schema::hasTable('debit_allocations')) {
            Schema::create('debit_allocations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('debit_id');
                $table->uuid('order_id');
                $table->decimal('amount', 12, 2);
                $table->text('notes')->nullable();
                $table->uuid('allocated_by');
                $table->timestamp('allocated_at');

                $table->foreign('debit_id')->references('id')->on('debits')->cascadeOnDelete();
                $table->foreign('order_id')->references('id')->on('orders');
                $table->foreign('allocated_by')->references('id')->on('users');

                $table->index('debit_id');
                $table->index('order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('debit_allocations');
        Schema::dropIfExists('debits');
    }
};
