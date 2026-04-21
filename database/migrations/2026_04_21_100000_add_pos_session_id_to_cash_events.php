<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cash_events.cash_session_id` carries a FK to `cash_sessions`, but POS
 * cash drops/payouts are anchored to `pos_sessions` — a different table.
 * Add a nullable `pos_session_id` column so POS-originated events can
 * reference the till session directly, and relax the `cash_session_id`
 * NOT NULL so events can omit it.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cash_events')) {
            return;
        }

        Schema::table('cash_events', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_events', 'pos_session_id')) {
                $table->uuid('pos_session_id')->nullable()->after('cash_session_id')->index();
            }
        });

        // Drop NOT NULL on cash_session_id so POS events can omit it
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            try {
                \DB::statement('ALTER TABLE cash_events ALTER COLUMN cash_session_id DROP NOT NULL');
            } catch (\Throwable $e) {
                // already nullable
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cash_events')) {
            return;
        }

        Schema::table('cash_events', function (Blueprint $table) {
            if (Schema::hasColumn('cash_events', 'pos_session_id')) {
                $table->dropColumn('pos_session_id');
            }
        });
    }
};
