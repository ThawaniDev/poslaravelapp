<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add business_type_id (UUID FK → business_types) to stores.
 *
 * The stores table already has `business_type` (enum from Core domain).
 * This new `business_type_id` links to the ContentOnboarding business_types
 * UUID table so template seeding can target the correct template set.
 *
 * Nullable: existing stores without a business-type selection are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite in CI — add column without FK constraint
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('stores', function (Blueprint $table) {
                $table->uuid('business_type_id')->nullable()->after('business_type');
            });
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->uuid('business_type_id')
                ->nullable()
                ->after('business_type');

            $table->foreign('business_type_id')
                ->references('id')
                ->on('business_types')
                ->nullOnDelete();

            $table->index('business_type_id', 'stores_business_type_id_idx');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite cannot drop columns on older versions; no-op
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['business_type_id']);
            $table->dropIndex('stores_business_type_id_idx');
            $table->dropColumn('business_type_id');
        });
    }
};
