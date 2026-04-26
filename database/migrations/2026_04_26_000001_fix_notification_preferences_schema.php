<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix notification_preferences table to support JSON-blob per-user schema.
 *
 * The original migration created a per-row schema with UNIQUE(user_id, event_key, channel).
 * The current codebase uses UserNotificationPreference which stores ONE blob per user.
 * This migration:
 *   1. Drops the old composite unique constraint
 *   2. Makes event_key / channel nullable (they are not used in blob approach)
 *   3. Adds UNIQUE(user_id) for the blob approach
 *
 * SQLite (tests) already has the correct schema — skip on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
DO $$
DECLARE
    v_constraint text;
BEGIN
    -- Drop old composite unique constraint (user_id, event_key, channel) if it exists
    SELECT conname INTO v_constraint
    FROM pg_constraint
    WHERE conrelid = 'notification_preferences'::regclass
      AND contype = 'u'
      AND array_to_string(
            ARRAY(SELECT attname FROM pg_attribute
                  WHERE attrelid = conrelid
                    AND attnum = ANY(conkey)
                  ORDER BY array_position(conkey, attnum)),
            ','
          ) LIKE '%event_key%';

    IF v_constraint IS NOT NULL THEN
        EXECUTE 'ALTER TABLE notification_preferences DROP CONSTRAINT ' || v_constraint;
    END IF;

    -- Make event_key and channel nullable (they are unused in blob schema)
    ALTER TABLE notification_preferences
        ALTER COLUMN event_key DROP NOT NULL,
        ALTER COLUMN channel   DROP NOT NULL;

    -- Add UNIQUE(user_id) if it does not exist
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'notification_preferences'::regclass
          AND contype = 'u'
          AND array_to_string(
                ARRAY(SELECT attname FROM pg_attribute
                      WHERE attrelid = conrelid
                        AND attnum = ANY(conkey)
                      ORDER BY array_position(conkey, attnum)),
                ','
              ) = 'user_id'
    ) THEN
        -- Remove duplicate user rows keeping only the most recently updated
        DELETE FROM notification_preferences np1
        USING notification_preferences np2
        WHERE np1.user_id = np2.user_id
          AND np1.ctid < np2.ctid;

        ALTER TABLE notification_preferences
            ADD CONSTRAINT notification_preferences_user_id_unique UNIQUE (user_id);
    END IF;
END;
$$;
SQL);
    }

    public function down(): void
    {
        // This migration is not safely reversible — the old per-row schema
        // is incompatible with the blob schema used by the application.
    }
};
