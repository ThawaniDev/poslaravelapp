<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Enhance suppliers table with additional columns ───
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('website', 255)->nullable()->after('email');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('country', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('country');
            $table->string('bank_name', 255)->nullable()->after('payment_terms');
            $table->string('bank_account', 100)->nullable()->after('bank_name');
            $table->string('iban', 50)->nullable()->after('bank_account');
            $table->decimal('credit_limit', 12, 2)->nullable()->after('iban');
            $table->decimal('outstanding_balance', 12, 2)->default(0)->after('credit_limit');
            $table->tinyInteger('rating')->nullable()->after('outstanding_balance');
            $table->string('category', 100)->nullable()->after('rating');
        });

        // ─── Create supplier_returns table ───
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('store_id');
            $table->uuid('supplier_id');
            $table->string('reference_number', 50)->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('reason', 255)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        // ─── Create supplier_return_items table ───
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_return_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('reason', 255)->nullable();
            $table->string('batch_number', 100)->nullable();

            $table->foreign('supplier_return_id')->references('id')->on('supplier_returns')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'website', 'city', 'country', 'postal_code',
                'bank_name', 'bank_account', 'iban',
                'credit_limit', 'outstanding_balance',
                'rating', 'category',
            ]);
        });
    }
};
