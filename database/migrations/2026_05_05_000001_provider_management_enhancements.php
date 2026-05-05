<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider Management Enhancements
 *
 * - Adds suspend_reason + suspended_at to stores
 * - Adds internal_notes, source, plan_id to provider_registrations
 * - Ensures cancellation_reasons table exists with recorded_by column
 * - Adds active_subscription view helper index
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── stores: add suspend_reason + suspended_at ────────────────
        if (!Schema::hasColumn('stores', 'suspend_reason')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->text('suspend_reason')->nullable()->after('is_active');
                $table->timestamp('suspended_at')->nullable()->after('suspend_reason');
            });
        }

        // ─── provider_registrations: add new columns ──────────────────
        if (!Schema::hasColumn('provider_registrations', 'internal_notes')) {
            Schema::table('provider_registrations', function (Blueprint $table) {
                $table->text('internal_notes')->nullable()->after('rejection_reason');
                $table->string('source', 30)->default('website')->after('internal_notes');
            });
        }
        if (!Schema::hasColumn('provider_registrations', 'plan_id')) {
            Schema::table('provider_registrations', function (Blueprint $table) {
                $table->uuid('plan_id')->nullable()->after('source');
                // FK to subscription_plans — add after check
            });
            // Only add FK if table exists
            if (Schema::hasTable('subscription_plans')) {
                Schema::table('provider_registrations', function (Blueprint $table) {
                    $table->foreign('plan_id')->references('id')->on('subscription_plans')->nullOnDelete();
                });
            }
        }

        // ─── cancellation_reasons: ensure table exists ────────────────
        if (!Schema::hasTable('cancellation_reasons')) {
            Schema::create('cancellation_reasons', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->foreignUuid('store_subscription_id')
                    ->constrained('store_subscriptions')
                    ->cascadeOnDelete();
                $table->string('reason_category', 30)->comment('price|features|competitor|support|other');
                $table->text('reason_text')->nullable();
                $table->uuid('recorded_by')->nullable();
                $table->foreign('recorded_by')->references('id')->on('admin_users')->nullOnDelete();
                $table->timestamp('cancelled_at')->useCurrent();
            });
        } elseif (!Schema::hasColumn('cancellation_reasons', 'recorded_by')) {
            Schema::table('cancellation_reasons', function (Blueprint $table) {
                $table->uuid('recorded_by')->nullable()->after('reason_text');
                $table->foreign('recorded_by')->references('id')->on('admin_users')->nullOnDelete();
            });
        }

        // ─── indexes ──────────────────────────────────────────────────
        DB::statement('CREATE INDEX IF NOT EXISTS idx_stores_is_active ON stores(is_active)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_stores_organization_id ON stores(organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cancellation_reasons_subscription ON cancellation_reasons(store_subscription_id)');
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['suspend_reason', 'suspended_at']);
        });

        Schema::table('provider_registrations', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['internal_notes', 'source', 'plan_id']);
        });
    }
};
