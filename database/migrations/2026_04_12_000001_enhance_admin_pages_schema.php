<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── IP Blocklist enhancements ─────────────────────────
        if (Schema::hasTable('admin_ip_blocklist')) {
            Schema::table('admin_ip_blocklist', function (Blueprint $table) {
                if (! Schema::hasColumn('admin_ip_blocklist', 'hit_count')) {
                    $table->unsignedInteger('hit_count')->default(0)->after('reason');
                }
                if (! Schema::hasColumn('admin_ip_blocklist', 'last_hit_at')) {
                    $table->timestamp('last_hit_at')->nullable()->after('hit_count');
                }
                if (! Schema::hasColumn('admin_ip_blocklist', 'is_cidr')) {
                    $table->boolean('is_cidr')->default(false)->after('ip_address');
                }
                if (! Schema::hasColumn('admin_ip_blocklist', 'source')) {
                    $table->string('source', 30)->default('manual')->after('reason'); // manual, auto_brute_force, import
                }
            });
        }

        // ── IP Allowlist enhancements ─────────────────────────
        if (Schema::hasTable('admin_ip_allowlist')) {
            Schema::table('admin_ip_allowlist', function (Blueprint $table) {
                if (! Schema::hasColumn('admin_ip_allowlist', 'description')) {
                    $table->text('description')->nullable()->after('label');
                }
                if (! Schema::hasColumn('admin_ip_allowlist', 'is_cidr')) {
                    $table->boolean('is_cidr')->default(false)->after('ip_address');
                }
                if (! Schema::hasColumn('admin_ip_allowlist', 'last_used_at')) {
                    $table->timestamp('last_used_at')->nullable()->after('description');
                }
                if (! Schema::hasColumn('admin_ip_allowlist', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('last_used_at');
                }
            });
        }

        // ── Payment Methods enhancements ──────────────────────
        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_methods', 'description')) {
                    $table->text('description')->nullable()->after('name_ar');
                }
                if (! Schema::hasColumn('payment_methods', 'description_ar')) {
                    $table->text('description_ar')->nullable()->after('description');
                }
                if (! Schema::hasColumn('payment_methods', 'supported_currencies')) {
                    $table->json('supported_currencies')->nullable()->after('provider_config_schema');
                }
                if (! Schema::hasColumn('payment_methods', 'min_amount')) {
                    $table->decimal('min_amount', 10, 2)->nullable()->after('supported_currencies');
                }
                if (! Schema::hasColumn('payment_methods', 'max_amount')) {
                    $table->decimal('max_amount', 10, 2)->nullable()->after('min_amount');
                }
                if (! Schema::hasColumn('payment_methods', 'processing_fee_percent')) {
                    $table->decimal('processing_fee_percent', 5, 2)->nullable()->after('max_amount');
                }
                if (! Schema::hasColumn('payment_methods', 'processing_fee_fixed')) {
                    $table->decimal('processing_fee_fixed', 10, 2)->nullable()->after('processing_fee_percent');
                }
            });
        }

        // ── Database Backups enhancements ─────────────────────
        if (Schema::hasTable('database_backups')) {
            Schema::table('database_backups', function (Blueprint $table) {
                if (! Schema::hasColumn('database_backups', 'triggered_by')) {
                    $table->uuid('triggered_by')->nullable()->after('error_message');
                }
                if (! Schema::hasColumn('database_backups', 'notes')) {
                    $table->text('notes')->nullable()->after('triggered_by');
                }
                if (! Schema::hasColumn('database_backups', 'tables_count')) {
                    $table->unsignedInteger('tables_count')->nullable()->after('file_size_bytes');
                }
                if (! Schema::hasColumn('database_backups', 'rows_count')) {
                    $table->unsignedBigInteger('rows_count')->nullable()->after('tables_count');
                }
                if (! Schema::hasColumn('database_backups', 'checksum')) {
                    $table->string('checksum', 64)->nullable()->after('rows_count');
                }
            });
        }

        // ── System Health Checks enhancements ─────────────────
        if (Schema::hasTable('system_health_checks')) {
            Schema::table('system_health_checks', function (Blueprint $table) {
                if (! Schema::hasColumn('system_health_checks', 'error_message')) {
                    $table->text('error_message')->nullable()->after('details');
                }
                if (! Schema::hasColumn('system_health_checks', 'triggered_by')) {
                    $table->string('triggered_by', 30)->default('scheduled')->after('error_message'); // scheduled, manual
                }
            });
        }

        // ── Notification Provider Status enhancements ─────────
        if (Schema::hasTable('notification_provider_status')) {
            Schema::table('notification_provider_status', function (Blueprint $table) {
                if (! Schema::hasColumn('notification_provider_status', 'last_test_at')) {
                    $table->timestamp('last_test_at')->nullable();
                }
                if (! Schema::hasColumn('notification_provider_status', 'last_test_result')) {
                    $table->string('last_test_result', 20)->nullable(); // success, failed
                }
                if (! Schema::hasColumn('notification_provider_status', 'configuration')) {
                    $table->json('configuration')->nullable();
                }
                if (! Schema::hasColumn('notification_provider_status', 'rate_limit_per_minute')) {
                    $table->unsignedInteger('rate_limit_per_minute')->nullable();
                }
                if (! Schema::hasColumn('notification_provider_status', 'cost_per_message')) {
                    $table->decimal('cost_per_message', 8, 4)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_ip_blocklist')) {
            Schema::table('admin_ip_blocklist', function (Blueprint $table) {
                $table->dropColumn(['hit_count', 'last_hit_at', 'is_cidr', 'source']);
            });
        }
        if (Schema::hasTable('admin_ip_allowlist')) {
            Schema::table('admin_ip_allowlist', function (Blueprint $table) {
                $table->dropColumn(['description', 'is_cidr', 'last_used_at', 'expires_at']);
            });
        }
        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn(['description', 'description_ar', 'supported_currencies', 'min_amount', 'max_amount', 'processing_fee_percent', 'processing_fee_fixed']);
            });
        }
        if (Schema::hasTable('database_backups')) {
            Schema::table('database_backups', function (Blueprint $table) {
                $table->dropColumn(['triggered_by', 'notes', 'tables_count', 'rows_count', 'checksum']);
            });
        }
        if (Schema::hasTable('system_health_checks')) {
            Schema::table('system_health_checks', function (Blueprint $table) {
                $table->dropColumn(['error_message', 'triggered_by']);
            });
        }
        if (Schema::hasTable('notification_provider_status')) {
            Schema::table('notification_provider_status', function (Blueprint $table) {
                $table->dropColumn(['last_test_at', 'last_test_result', 'configuration', 'rate_limit_per_minute', 'cost_per_message']);
            });
        }
    }
};
