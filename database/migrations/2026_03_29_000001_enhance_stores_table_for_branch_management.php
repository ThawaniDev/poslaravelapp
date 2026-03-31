<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance stores table with comprehensive branch-management columns.
 *
 * Adds: manager, contact, capacity, operational flags, metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // ─── Manager / contact ───────────────────────
            $table->uuid('manager_id')->nullable()->after('organization_id');
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            $table->string('contact_person', 255)->nullable()->after('email');
            $table->string('secondary_phone', 20)->nullable()->after('contact_person');

            // ─── Location enhancements ───────────────────
            $table->string('region', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('region');
            $table->string('country', 5)->default('SA')->after('postal_code');
            $table->string('google_maps_url', 500)->nullable()->after('country');

            // ─── Operational info ────────────────────────
            $table->date('opening_date')->nullable()->after('is_main_branch');
            $table->date('closing_date')->nullable()->after('opening_date');
            $table->integer('max_registers')->default(5)->after('closing_date');
            $table->integer('max_staff')->default(20)->after('max_registers');
            $table->decimal('area_sqm', 10, 2)->nullable()->after('max_staff');
            $table->integer('seating_capacity')->nullable()->after('area_sqm');

            // ─── Display & description ───────────────────
            if (!Schema::hasColumn('stores', 'description')) {
                $table->text('description')->nullable()->after('name_ar');
            }
            $table->text('description_ar')->nullable()->after('description');
            $table->string('logo_url', 500)->nullable()->after('description_ar');
            $table->string('cover_image_url', 500)->nullable()->after('logo_url');

            // ─── Operational flags ───────────────────────
            $table->boolean('is_warehouse')->default(false)->after('is_active');
            $table->boolean('accepts_online_orders')->default(false)->after('is_warehouse');
            $table->boolean('accepts_reservations')->default(false)->after('accepts_online_orders');
            $table->boolean('has_delivery')->default(false)->after('accepts_reservations');
            $table->boolean('has_pickup')->default(false)->after('has_delivery');

            // ─── Metadata ────────────────────────────────
            $table->string('cr_number', 50)->nullable()->after('has_pickup');
            $table->string('vat_number', 20)->nullable()->after('cr_number');
            $table->string('municipal_license', 100)->nullable()->after('vat_number');
            $table->date('license_expiry_date')->nullable()->after('municipal_license');
            $table->jsonb('social_links')->default('{}')->after('license_expiry_date');
            $table->jsonb('extra_metadata')->default('{}')->after('social_links');
            $table->text('internal_notes')->nullable()->after('extra_metadata');
            $table->integer('sort_order')->default(0)->after('internal_notes');

            // ─── Indexes ─────────────────────────────────
            $table->index('manager_id');
            $table->index('region');
            $table->index('is_warehouse');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['region']);
            $table->dropIndex(['is_warehouse']);
            $table->dropIndex(['sort_order']);

            $table->dropColumn([
                'manager_id', 'contact_person', 'secondary_phone',
                'region', 'postal_code', 'country', 'google_maps_url',
                'opening_date', 'closing_date', 'max_registers', 'max_staff',
                'area_sqm', 'seating_capacity',
                'description_ar', 'logo_url', 'cover_image_url',
                'is_warehouse', 'accepts_online_orders', 'accepts_reservations',
                'has_delivery', 'has_pickup',
                'cr_number', 'vat_number', 'municipal_license', 'license_expiry_date',
                'social_links', 'extra_metadata', 'internal_notes', 'sort_order',
            ]);
        });
    }
};
