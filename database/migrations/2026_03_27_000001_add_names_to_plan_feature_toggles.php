<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add bilingual display names (name / name_ar) to plan_feature_toggles.
 * These are optional human-readable labels used in the admin and exposed via the public API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_feature_toggles', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('feature_key');
            $table->string('name_ar', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('plan_feature_toggles', function (Blueprint $table) {
            $table->dropColumn(['name', 'name_ar']);
        });
    }
};
