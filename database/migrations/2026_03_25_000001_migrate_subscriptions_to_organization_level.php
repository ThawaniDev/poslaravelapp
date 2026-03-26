<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Skip on SQLite — test schema already has organization_id columns
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // ── 1. store_subscriptions: store_id → organization_id ───
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->dropForeign('store_subscriptions_store_id_fkey');
        });

        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->renameColumn('store_id', 'organization_id');
        });

        // Re-point existing rows: replace store IDs with their organization IDs
        DB::statement("
            UPDATE store_subscriptions
            SET organization_id = stores.organization_id
            FROM stores
            WHERE store_subscriptions.organization_id = stores.id
        ");

        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
        });

        // ── 2. subscription_usage_snapshots: store_id → organization_id
        Schema::table('subscription_usage_snapshots', function (Blueprint $table) {
            $table->dropForeign('subscription_usage_snapshots_store_id_fkey');
        });

        Schema::table('subscription_usage_snapshots', function (Blueprint $table) {
            $table->renameColumn('store_id', 'organization_id');
        });

        DB::statement("
            UPDATE subscription_usage_snapshots
            SET organization_id = stores.organization_id
            FROM stores
            WHERE subscription_usage_snapshots.organization_id = stores.id
        ");

        Schema::table('subscription_usage_snapshots', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
        });

        // ── 3. provider_limit_overrides: store_id → organization_id
        Schema::table('provider_limit_overrides', function (Blueprint $table) {
            $table->dropForeign('provider_limit_overrides_store_id_fkey');
        });

        Schema::table('provider_limit_overrides', function (Blueprint $table) {
            $table->renameColumn('store_id', 'organization_id');
        });

        DB::statement("
            UPDATE provider_limit_overrides
            SET organization_id = stores.organization_id
            FROM stores
            WHERE provider_limit_overrides.organization_id = stores.id
        ");

        Schema::table('provider_limit_overrides', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Reverse: organization_id → store_id (points to main branch)
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->renameColumn('organization_id', 'store_id');
        });

        DB::statement("
            UPDATE store_subscriptions
            SET store_id = stores.id
            FROM stores
            WHERE store_subscriptions.store_id = stores.organization_id
              AND stores.is_main_branch = true
        ");

        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->foreign('store_id')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();
        });

        Schema::table('subscription_usage_snapshots', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->renameColumn('organization_id', 'store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('provider_limit_overrides', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->renameColumn('organization_id', 'store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }
};
