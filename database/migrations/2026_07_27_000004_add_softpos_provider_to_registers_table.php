<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add softpos_provider column to registers table.
 *
 *  softpos_provider  — which SoftPOS SDK/provider is configured for this terminal
 *                      values: 'nearpay' | 'edfapay' | null (not configured)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('registers', 'softpos_provider')) {
            return;
        }

        Schema::table('registers', function (Blueprint $table) {
            $table->string('softpos_provider', 20)
                  ->nullable()
                  ->after('softpos_enabled')
                  ->comment('SoftPOS SDK provider: nearpay | edfapay');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn('softpos_provider');
        });
    }
};
