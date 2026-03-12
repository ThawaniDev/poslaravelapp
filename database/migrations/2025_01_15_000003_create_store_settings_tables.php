<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORE SETTINGS & WORKING HOURS
 *
 * store_settings — single-row per store key/value configuration
 * store_working_hours — 7-day weekly schedule per store
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->unique();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();

            // Tax
            $table->string('tax_label', 50)->default('VAT');
            $table->decimal('tax_rate', 5, 2)->default(15.00);
            $table->boolean('prices_include_tax')->default(true);
            $table->string('tax_number', 50)->nullable();

            // Receipt
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->boolean('receipt_show_logo')->default(true);
            $table->boolean('receipt_show_tax_breakdown')->default(true);

            // Currency & formatting
            $table->string('currency_code', 10)->default('SAR');
            $table->string('currency_symbol', 5)->default('﷼');
            $table->integer('decimal_places')->default(2);
            $table->string('thousand_separator', 3)->default(',');
            $table->string('decimal_separator', 3)->default('.');

            // POS behaviour
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('require_customer_for_sale')->default(false);
            $table->boolean('auto_print_receipt')->default(true);
            $table->integer('session_timeout_minutes')->default(480); // 8 hours
            $table->integer('max_discount_percent')->default(100);
            $table->boolean('enable_tips')->default(false);
            $table->boolean('enable_kitchen_display')->default(false);

            // Notifications
            $table->boolean('low_stock_alert')->default(true);
            $table->integer('low_stock_threshold')->default(5);

            // All other custom settings
            $table->jsonb('extra')->default('{}');

            $table->timestamps();
        });

        Schema::create('store_working_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();

            // 0=Sunday, 1=Monday ... 6=Saturday
            $table->smallInteger('day_of_week');
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();

            // Break / split shift
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_working_hours');
        Schema::dropIfExists('store_settings');
    }
};
