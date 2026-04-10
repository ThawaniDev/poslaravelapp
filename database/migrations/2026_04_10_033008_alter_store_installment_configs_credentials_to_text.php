<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE store_installment_configs ALTER COLUMN credentials TYPE TEXT USING credentials::TEXT');
        DB::statement("ALTER TABLE store_installment_configs ALTER COLUMN credentials SET DEFAULT ''");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM store_installment_configs WHERE credentials != '' AND credentials != '{}'");
        DB::statement("UPDATE store_installment_configs SET credentials = '{}' WHERE credentials = ''");
        DB::statement('ALTER TABLE store_installment_configs ALTER COLUMN credentials TYPE JSONB USING credentials::JSONB');
        DB::statement("ALTER TABLE store_installment_configs ALTER COLUMN credentials SET DEFAULT '{}'::jsonb");
    }
};
