<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add custom columns to Spatie's permissions and roles tables
 * to support POS-specific fields required by our models.
 *
 * Runs AFTER the Spatie create_permission_tables migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Custom columns on permissions ───────────────────
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('permissions', 'module')) {
                $table->string('module')->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('permissions', 'requires_pin')) {
                $table->boolean('requires_pin')->default(false)->after('module');
            }
        });

        // ─── Custom columns on roles ─────────────────────────
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'store_id')) {
                $table->uuid('store_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('roles', 'is_predefined')) {
                $table->boolean('is_predefined')->default(false)->after('guard_name');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('is_predefined');
            }
        });

        // Replace the Spatie unique index on roles (name, guard_name)
        // with one that includes store_id, so the same role name can
        // exist in different stores.
        DB::statement('DROP INDEX IF EXISTS roles_name_guard_name_unique');
        DB::statement('CREATE UNIQUE INDEX roles_name_guard_name_store_id_unique ON roles (name, guard_name, store_id)');
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'module', 'requires_pin']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['store_id', 'display_name', 'is_predefined', 'description']);
        });
    }
};
