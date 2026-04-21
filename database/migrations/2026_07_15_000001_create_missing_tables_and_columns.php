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
        // ─── Create cms_pages table ────────────────────────
        DB::statement("
            CREATE TABLE IF NOT EXISTS cms_pages (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                slug VARCHAR(150) NOT NULL UNIQUE,
                title VARCHAR(255),
                title_ar VARCHAR(255),
                body TEXT,
                body_ar TEXT,
                page_type VARCHAR(50) NOT NULL DEFAULT 'general',
                is_published BOOLEAN NOT NULL DEFAULT false,
                meta_title VARCHAR(255),
                meta_title_ar VARCHAR(255),
                meta_description TEXT,
                meta_description_ar TEXT,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            );
        ");

        // ─── Create platform_event_logs table ──────────────
        DB::statement("
            CREATE TABLE IF NOT EXISTS platform_event_logs (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                event_type VARCHAR(50) NOT NULL,
                level VARCHAR(20) NOT NULL DEFAULT 'info',
                source VARCHAR(100),
                message TEXT,
                details JSONB,
                admin_user_id UUID,
                created_at TIMESTAMP DEFAULT NOW()
            );
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_platform_event_logs_type ON platform_event_logs(event_type);
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_platform_event_logs_level ON platform_event_logs(level);
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_platform_event_logs_created ON platform_event_logs(created_at);
        ");

        // ─── Add missing columns to existing tables ────────

        // user_preferences: add accessibility_json
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'user_preferences' AND column_name = 'accessibility_json'
                ) THEN
                    ALTER TABLE user_preferences ADD COLUMN accessibility_json JSONB DEFAULT '{}';
                END IF;
            END $$;
        ");

        // notification_preferences: add updated_at
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'notification_preferences' AND column_name = 'updated_at'
                ) THEN
                    ALTER TABLE notification_preferences ADD COLUMN updated_at TIMESTAMP DEFAULT NOW();
                END IF;
            END $$;
        ");

        // jewelry_product_details: add created_at
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'jewelry_product_details' AND column_name = 'created_at'
                ) THEN
                    ALTER TABLE jewelry_product_details ADD COLUMN created_at TIMESTAMP DEFAULT NOW();
                END IF;
            END $$;
        ");

        // open_tabs: make order_id and customer_name nullable (tabs can be opened before order)
        DB::statement("
            ALTER TABLE open_tabs ALTER COLUMN order_id DROP NOT NULL;
        ");
        DB::statement("
            ALTER TABLE open_tabs ALTER COLUMN customer_name DROP NOT NULL;
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('DROP TABLE IF EXISTS platform_event_logs');
        DB::statement('DROP TABLE IF EXISTS cms_pages');

        DB::statement("
            ALTER TABLE user_preferences DROP COLUMN IF EXISTS accessibility_json;
        ");
        DB::statement("
            ALTER TABLE notification_preferences DROP COLUMN IF EXISTS updated_at;
        ");
        DB::statement("
            ALTER TABLE jewelry_product_details DROP COLUMN IF EXISTS created_at;
        ");
    }
};
