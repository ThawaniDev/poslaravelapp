<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROVIDER CORE: User Preferences & Localization
 *
 * Tables: user_preferences, translation_overrides
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE user_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    pos_handedness VARCHAR(10),
    font_size VARCHAR(15),
    theme VARCHAR(50),
    pos_layout_id UUID REFERENCES pos_layout_templates(id)
);

CREATE TABLE translation_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    string_key VARCHAR(200) NOT NULL,
    locale VARCHAR(5) NOT NULL,
    custom_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, string_key, locale)
);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_overrides');
        Schema::dropIfExists('user_preferences');
    }
};
