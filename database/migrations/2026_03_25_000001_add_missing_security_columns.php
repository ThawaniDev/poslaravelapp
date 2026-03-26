<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // ── admin_sessions: add status, started_at, ended_at; make session_token_hash & expires_at nullable
            DB::statement("ALTER TABLE admin_sessions ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE admin_sessions ADD COLUMN IF NOT EXISTS started_at TIMESTAMP DEFAULT NOW()");
            DB::statement("ALTER TABLE admin_sessions ADD COLUMN IF NOT EXISTS ended_at TIMESTAMP");
            DB::statement("ALTER TABLE admin_sessions ALTER COLUMN session_token_hash DROP NOT NULL");
            DB::statement("ALTER TABLE admin_sessions ALTER COLUMN expires_at DROP NOT NULL");

            // ── security_alerts: add description, ip_address
            DB::statement("ALTER TABLE security_alerts ADD COLUMN IF NOT EXISTS description TEXT NOT NULL DEFAULT ''");
            DB::statement("ALTER TABLE security_alerts ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE admin_sessions DROP COLUMN IF EXISTS status");
            DB::statement("ALTER TABLE admin_sessions DROP COLUMN IF EXISTS started_at");
            DB::statement("ALTER TABLE admin_sessions DROP COLUMN IF EXISTS ended_at");

            DB::statement("ALTER TABLE security_alerts DROP COLUMN IF EXISTS description");
            DB::statement("ALTER TABLE security_alerts DROP COLUMN IF EXISTS ip_address");
        }
    }
};
