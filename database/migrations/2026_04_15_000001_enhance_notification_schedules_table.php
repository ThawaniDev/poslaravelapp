<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add category, title, message, priority columns to notification_schedules
        DB::statement("ALTER TABLE notification_schedules ADD COLUMN IF NOT EXISTS category VARCHAR(30)");
        DB::statement("ALTER TABLE notification_schedules ADD COLUMN IF NOT EXISTS title VARCHAR(255)");
        DB::statement("ALTER TABLE notification_schedules ADD COLUMN IF NOT EXISTS message TEXT");
        DB::statement("ALTER TABLE notification_schedules ADD COLUMN IF NOT EXISTS priority VARCHAR(10) DEFAULT 'normal'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE notification_schedules DROP COLUMN IF EXISTS category");
        DB::statement("ALTER TABLE notification_schedules DROP COLUMN IF EXISTS title");
        DB::statement("ALTER TABLE notification_schedules DROP COLUMN IF EXISTS message");
        DB::statement("ALTER TABLE notification_schedules DROP COLUMN IF EXISTS priority");
    }
};
