<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::create('marketplace_purchase_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_purchase_id');
            $table->foreign('template_purchase_id')
                ->references('id')
                ->on('template_purchases')
                ->cascadeOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->string('status', 20)->default('paid'); // draft, paid, refunded, cancelled
            $table->uuid('store_id');
            $table->foreign('store_id')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();

            // Seller / Publisher
            $table->string('seller_name', 150);
            $table->string('seller_email', 150)->nullable();
            $table->string('seller_vat_number', 30)->nullable();

            // Buyer
            $table->string('buyer_store_name', 200);
            $table->string('buyer_organization_name', 200)->nullable();
            $table->string('buyer_vat_number', 30)->nullable();
            $table->string('buyer_email', 150)->nullable();

            // Line items summary
            $table->string('item_description', 500);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(15.00); // Saudi VAT 15%
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('SAR');

            // Payment
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();

            // Subscription-specific
            $table->string('billing_period', 50)->nullable(); // e.g. "2026-03-26 to 2026-04-26"
            $table->boolean('is_recurring')->default(false);

            // Notes
            $table->text('notes')->nullable();
            $table->text('notes_ar')->nullable();

            $table->timestamps();
        });

        // Add invoice_id FK on template_purchases for quick lookup
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->uuid('invoice_id')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('template_purchases', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });

        Schema::dropIfExists('marketplace_purchase_invoices');
    }
};
