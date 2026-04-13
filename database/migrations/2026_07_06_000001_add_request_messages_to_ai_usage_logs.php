<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_usage_logs') && !Schema::hasColumn('ai_usage_logs', 'request_messages')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                $table->text('request_messages')->nullable()->after('metadata_json');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_usage_logs') && Schema::hasColumn('ai_usage_logs', 'request_messages')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                $table->dropColumn('request_messages');
            });
        }
    }
};
