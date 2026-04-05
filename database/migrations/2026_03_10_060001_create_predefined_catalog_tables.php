<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PREDEFINED CATALOG: Categories & Products
 *
 * Tables: predefined_categories, predefined_products, predefined_product_images
 *
 * These tables store template products and categories that can be cloned
 * by stores of specific business types (grocery, pharmacy, electronics, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Predefined Categories ──────────────────────────────
        if (!Schema::hasTable('predefined_categories')) {
            Schema::create('predefined_categories', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->uuid('business_type_id');
                $table->uuid('parent_id')->nullable();
                $table->string('name', 255);
                $table->string('name_ar', 255)->nullable();
                $table->text('description')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('image_url')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
                $table->index(['business_type_id', 'is_active']);
            });

            // Self-referencing FK must be added after table creation in PostgreSQL
            Schema::table('predefined_categories', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('predefined_categories')->nullOnDelete();
            });
        }

        // ─── Predefined Products ────────────────────────────────
        if (!Schema::hasTable('predefined_products')) {
            Schema::create('predefined_products', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->uuid('business_type_id');
                $table->uuid('predefined_category_id')->nullable();
                $table->string('name', 255);
                $table->string('name_ar', 255)->nullable();
                $table->text('description')->nullable();
                $table->text('description_ar')->nullable();
                $table->string('sku', 100)->nullable();
                $table->string('barcode', 50)->nullable();
                $table->decimal('sell_price', 12, 2)->default(0);
                $table->decimal('cost_price', 12, 2)->nullable();
                $table->string('unit', 20)->default('piece');
                $table->decimal('tax_rate', 5, 2)->default(15.00);
                $table->boolean('is_weighable')->default(false);
                $table->decimal('tare_weight', 8, 3)->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('age_restricted')->default(false);
                $table->text('image_url')->nullable();
                $table->timestamps();

                $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
                $table->foreign('predefined_category_id')->references('id')->on('predefined_categories')->nullOnDelete();
                $table->index(['business_type_id', 'is_active']);
                $table->index('predefined_category_id');
            });
        }

        // ─── Predefined Product Images ──────────────────────────
        if (!Schema::hasTable('predefined_product_images')) {
            Schema::create('predefined_product_images', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->uuid('predefined_product_id');
                $table->text('image_url');
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('predefined_product_id')
                    ->references('id')
                    ->on('predefined_products')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('predefined_product_images');
        Schema::dropIfExists('predefined_products');
        Schema::dropIfExists('predefined_categories');
    }
};
