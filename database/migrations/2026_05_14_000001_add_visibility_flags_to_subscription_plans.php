<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add two visibility-control columns to subscription_plans:
 *
 * - hide_from_public: plan is not shown on the public pricing / plans page
 *   (used for private / partner plans sold direct).
 * - hide_unselected_features: when true, sidebar items whose feature_key is
 *   disabled for this plan are hidden entirely rather than shown as locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('hide_from_public')->default(false)->after('is_highlighted');
            $table->boolean('hide_unselected_features')->default(false)->after('hide_from_public');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['hide_from_public', 'hide_unselected_features']);
        });
    }
};
