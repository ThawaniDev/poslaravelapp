<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE thawani_store_config
    ADD COLUMN IF NOT EXISTS marketplace_url VARCHAR(500),
    ADD COLUMN IF NOT EXISTS api_key VARCHAR(255),
    ADD COLUMN IF NOT EXISTS api_secret VARCHAR(255);
SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE thawani_store_config
    DROP COLUMN IF EXISTS marketplace_url,
    DROP COLUMN IF EXISTS api_key,
    DROP COLUMN IF EXISTS api_secret;
SQL);
    }
};
