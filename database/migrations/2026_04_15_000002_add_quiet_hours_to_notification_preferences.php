<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE notification_preferences ADD COLUMN IF NOT EXISTS quiet_hours_start TIME");
        DB::statement("ALTER TABLE notification_preferences ADD COLUMN IF NOT EXISTS quiet_hours_end TIME");
        DB::statement("ALTER TABLE notification_preferences ADD COLUMN IF NOT EXISTS preferences_json JSONB DEFAULT '{}'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE notification_preferences DROP COLUMN IF EXISTS quiet_hours_start");
        DB::statement("ALTER TABLE notification_preferences DROP COLUMN IF EXISTS quiet_hours_end");
        DB::statement("ALTER TABLE notification_preferences DROP COLUMN IF EXISTS preferences_json");
    }
};
