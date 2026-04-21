<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ai_usage_logs')) {
            return;
        }
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('ai_feature_definition_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('ai_usage_logs')) {
            return;
        }
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('ai_feature_definition_id')->nullable(false)->change();
        });
    }
};
