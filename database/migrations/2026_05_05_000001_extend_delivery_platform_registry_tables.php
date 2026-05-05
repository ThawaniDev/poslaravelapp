<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add extended columns to delivery_platforms for the Integration Builder UI:
 *  - name_ar, description_ar  : Arabic localisation
 *  - api_type                  : rest | webhook | polling
 *  - documentation_url         : link to platform API docs
 *  - default_commission_percent: default commission rate
 *  - supported_countries       : JSON array of country codes
 *
 * Also creates store_delivery_platforms if it does not yet exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite test schema already includes these columns (added in
            // 0000_00_00_000010_create_sqlite_test_schema.php). Skip here.
            return;
        }

        Schema::table('delivery_platforms', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_platforms', 'name_ar')) {
                $table->string('name_ar', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('delivery_platforms', 'description')) {
                $table->text('description')->nullable()->after('logo_url');
            }
            if (! Schema::hasColumn('delivery_platforms', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('description');
            }
            if (! Schema::hasColumn('delivery_platforms', 'api_type')) {
                $table->string('api_type', 20)->default('rest')->after('auth_method');
            }
            if (! Schema::hasColumn('delivery_platforms', 'documentation_url')) {
                $table->text('documentation_url')->nullable()->after('base_url');
            }
            if (! Schema::hasColumn('delivery_platforms', 'default_commission_percent')) {
                $table->decimal('default_commission_percent', 5, 2)->default(0)->after('documentation_url');
            }
            if (! Schema::hasColumn('delivery_platforms', 'supported_countries')) {
                $table->jsonb('supported_countries')->nullable()->after('default_commission_percent');
            }
        });

        if (! Schema::hasTable('store_delivery_platforms')) {
            Schema::create('store_delivery_platforms', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
                $table->uuid('store_id')->index();
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
                $table->uuid('delivery_platform_id')->index();
                $table->foreign('delivery_platform_id')->references('id')->on('delivery_platforms')->onDelete('cascade');
                $table->jsonb('credentials')->default('{}');
                $table->string('inbound_api_key', 48)->unique()->nullable();
                $table->boolean('is_enabled')->default(false);
                $table->string('sync_status', 10)->default('pending');
                $table->timestamp('last_sync_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'delivery_platform_id'], 'sdp_store_platform_unique');
            });
        }

        // Add url_template + request_mapping columns to delivery_platform_endpoints if missing
        if (Schema::hasTable('delivery_platform_endpoints')) {
            Schema::table('delivery_platform_endpoints', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_platform_endpoints', 'url_template')) {
                    $table->text('url_template')->nullable()->after('operation');
                }
                if (! Schema::hasColumn('delivery_platform_endpoints', 'request_mapping')) {
                    $table->jsonb('request_mapping')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::dropIfExists('store_delivery_platforms');

        Schema::table('delivery_platforms', function (Blueprint $table) {
            $table->dropColumnIfExists('name_ar');
            $table->dropColumnIfExists('description');
            $table->dropColumnIfExists('description_ar');
            $table->dropColumnIfExists('api_type');
            $table->dropColumnIfExists('documentation_url');
            $table->dropColumnIfExists('default_commission_percent');
            $table->dropColumnIfExists('supported_countries');
        });
    }
};
