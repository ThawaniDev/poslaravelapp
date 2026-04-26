<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_reports')) {
            return;
        }
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('report_type', 50); // sales_summary, product_performance, financial_pl, etc.
            $table->string('name', 255);
            $table->string('frequency', 20); // daily, weekly, monthly
            $table->json('filters')->nullable(); // stored date range / category filters
            $table->json('recipients'); // array of email addresses
            $table->string('format', 20)->default('pdf'); // pdf, csv
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->index(['store_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
