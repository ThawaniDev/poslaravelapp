<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLATFORM: Announcements
 *
 * Tables: platform_announcements, platform_announcement_dismissals, payment_reminders
 *
 * Generated from database_schema.sql — fake-run via migrate --fake
 * since these tables already exist in Supabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (\Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE platform_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type VARCHAR(20) NOT NULL,
    title VARCHAR(200) NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    target_filter JSONB NOT NULL DEFAULT '{"scope":"all"}',
    display_start_at TIMESTAMP NOT NULL,
    display_end_at TIMESTAMP NOT NULL,
    is_banner BOOLEAN DEFAULT FALSE,
    send_push BOOLEAN DEFAULT FALSE,
    send_email BOOLEAN DEFAULT FALSE,
    created_by UUID NOT NULL REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE platform_announcement_dismissals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    announcement_id UUID NOT NULL REFERENCES platform_announcements(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id),
    dismissed_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (announcement_id, store_id)
);

CREATE TABLE payment_reminders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    reminder_type VARCHAR(20) NOT NULL,
    channel VARCHAR(10) NOT NULL,
    sent_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_announcements_display ON platform_announcements (display_start_at, display_end_at);

CREATE INDEX idx_announcements_type ON platform_announcements (type);

CREATE INDEX idx_payment_reminders_sub_type ON payment_reminders (store_subscription_id, reminder_type);

CREATE INDEX idx_payment_reminders_sent ON payment_reminders (sent_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
        Schema::dropIfExists('platform_announcement_dismissals');
        Schema::dropIfExists('platform_announcements');
    }
};
