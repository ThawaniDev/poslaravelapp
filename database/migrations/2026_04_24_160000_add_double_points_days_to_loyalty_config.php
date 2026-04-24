<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec §4.5 — loyalty config supports a "double-points days" picker so
 * organisations can configure weekday(s) on which earning is doubled.
 * Stored as a JSONB array of ISO weekday integers (1=Mon ... 7=Sun).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if (! Schema::hasColumn('loyalty_config', 'double_points_days')) {
            DB::statement("ALTER TABLE loyalty_config ADD COLUMN double_points_days JSONB DEFAULT '[]'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if (Schema::hasColumn('loyalty_config', 'double_points_days')) {
            DB::statement('ALTER TABLE loyalty_config DROP COLUMN double_points_days');
        }
    }
};
