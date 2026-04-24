<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_platform_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_platform_configs', 'operating_hours_json')) {
                $table->json('operating_hours_json')->nullable()->after('webhook_url');
            }
            if (! Schema::hasColumn('delivery_platform_configs', 'auto_accept_timeout_seconds')) {
                // Spec Rule #6: manual-accept orders auto-rejected after this window.
                $table->unsignedInteger('auto_accept_timeout_seconds')->default(300)->after('auto_accept');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_platform_configs', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_platform_configs', 'operating_hours_json')) {
                $table->dropColumn('operating_hours_json');
            }
            if (Schema::hasColumn('delivery_platform_configs', 'auto_accept_timeout_seconds')) {
                $table->dropColumn('auto_accept_timeout_seconds');
            }
        });
    }
};
