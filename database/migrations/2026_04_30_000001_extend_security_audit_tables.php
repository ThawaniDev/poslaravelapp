<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the security & audit tables with columns required by the models.
 *
 * The original migration 2026_03_10_040008 created bare-minimum columns.
 * This migration adds every column that was added to Eloquent models after
 * the initial schema was deployed to Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── admin_ip_allowlist ────────────────────────────────────
        Schema::table('admin_ip_allowlist', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_ip_allowlist', 'is_cidr')) {
                $table->boolean('is_cidr')->default(false)->after('ip_address');
            }
            if (! Schema::hasColumn('admin_ip_allowlist', 'description')) {
                $table->text('description')->nullable()->after('label');
            }
            if (! Schema::hasColumn('admin_ip_allowlist', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('description');
            }
            if (! Schema::hasColumn('admin_ip_allowlist', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('last_used_at');
            }
        });

        // ── admin_ip_blocklist ────────────────────────────────────
        Schema::table('admin_ip_blocklist', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_ip_blocklist', 'is_cidr')) {
                $table->boolean('is_cidr')->default(false)->after('ip_address');
            }
            if (! Schema::hasColumn('admin_ip_blocklist', 'hit_count')) {
                $table->unsignedInteger('hit_count')->default(0)->after('reason');
            }
            if (! Schema::hasColumn('admin_ip_blocklist', 'last_hit_at')) {
                $table->timestamp('last_hit_at')->nullable()->after('hit_count');
            }
            if (! Schema::hasColumn('admin_ip_blocklist', 'source')) {
                $table->string('source', 50)->nullable()->after('last_hit_at');
            }
            if (! Schema::hasColumn('admin_ip_blocklist', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('source');
            }
            if (! Schema::hasColumn('admin_ip_blocklist', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('blocked_at');
            }
        });

        // ── admin_sessions ────────────────────────────────────────
        Schema::table('admin_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_sessions', 'status')) {
                $table->string('status', 20)->default('active')->after('revoked_at');
            }
            if (! Schema::hasColumn('admin_sessions', 'started_at')) {
                // Rename created_at → started_at is not safe on live data; add separately
                $table->timestamp('started_at')->nullable()->after('two_fa_verified');
            }
            if (! Schema::hasColumn('admin_sessions', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('revoked_at');
            }
        });

        // Index for fast stale-session cleanup
        try {
            Schema::table('admin_sessions', function (Blueprint $table) {
                $table->index(['status', 'expires_at'], 'idx_admin_sessions_status_expires');
                $table->index(['status', 'last_activity_at'], 'idx_admin_sessions_status_activity');
            });
        } catch (\Exception) {
            // Indexes may already exist in production DB
        }

        // ── security_alerts ───────────────────────────────────────
        Schema::table('security_alerts', function (Blueprint $table) {
            if (! Schema::hasColumn('security_alerts', 'description')) {
                $table->text('description')->nullable()->after('alert_type');
            }
            if (! Schema::hasColumn('security_alerts', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_ip_allowlist', function (Blueprint $table) {
            $table->dropColumn(['is_cidr', 'description', 'last_used_at', 'expires_at']);
        });

        Schema::table('admin_ip_blocklist', function (Blueprint $table) {
            $table->dropColumn(['is_cidr', 'hit_count', 'last_hit_at', 'source', 'blocked_at', 'expires_at']);
        });

        Schema::table('admin_sessions', function (Blueprint $table) {
            $table->dropColumn(['status', 'started_at', 'ended_at']);
        });

        Schema::table('security_alerts', function (Blueprint $table) {
            $table->dropColumn(['description', 'ip_address']);
        });
    }
};
