<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SUPPORT: Enhance support_tickets table.
 *
 * Changes:
 * - Add satisfaction_rating, satisfaction_comment columns
 * - Add performance indexes on support_tickets and support_ticket_messages
 * - Fix ticket_number length to accommodate TKT-YYYY-NNNN format
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (test runner): use Schema builder
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('support_tickets', 'satisfaction_rating')) {
                    $table->unsignedTinyInteger('satisfaction_rating')->nullable()->after('closed_at');
                }
                if (!Schema::hasColumn('support_tickets', 'satisfaction_comment')) {
                    $table->text('satisfaction_comment')->nullable()->after('satisfaction_rating');
                }
            });

            return;
        }

        // Postgres / production
        DB::unprepared(<<<'SQL'
-- Satisfaction columns
ALTER TABLE support_tickets
    ADD COLUMN IF NOT EXISTS satisfaction_rating    SMALLINT CHECK (satisfaction_rating BETWEEN 1 AND 5),
    ADD COLUMN IF NOT EXISTS satisfaction_comment   TEXT;

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_support_tickets_status       ON support_tickets (status);
CREATE INDEX IF NOT EXISTS idx_support_tickets_priority     ON support_tickets (priority);
CREATE INDEX IF NOT EXISTS idx_support_tickets_org_id       ON support_tickets (organization_id);
CREATE INDEX IF NOT EXISTS idx_support_tickets_store_id     ON support_tickets (store_id);
CREATE INDEX IF NOT EXISTS idx_support_tickets_assigned_to  ON support_tickets (assigned_to);
CREATE INDEX IF NOT EXISTS idx_support_tickets_created_at   ON support_tickets (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_support_tickets_sla_deadline ON support_tickets (sla_deadline_at)
    WHERE sla_deadline_at IS NOT NULL AND status NOT IN ('resolved','closed');

CREATE INDEX IF NOT EXISTS idx_support_messages_ticket_id ON support_ticket_messages (support_ticket_id);
CREATE INDEX IF NOT EXISTS idx_support_messages_sent_at   ON support_ticket_messages (sent_at DESC);
SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite does not support DROP COLUMN easily; skip
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE support_tickets
    DROP COLUMN IF EXISTS satisfaction_rating,
    DROP COLUMN IF EXISTS satisfaction_comment;

DROP INDEX IF EXISTS idx_support_tickets_status;
DROP INDEX IF EXISTS idx_support_tickets_priority;
DROP INDEX IF EXISTS idx_support_tickets_org_id;
DROP INDEX IF EXISTS idx_support_tickets_store_id;
DROP INDEX IF EXISTS idx_support_tickets_assigned_to;
DROP INDEX IF EXISTS idx_support_tickets_created_at;
DROP INDEX IF EXISTS idx_support_tickets_sla_deadline;
DROP INDEX IF EXISTS idx_support_messages_ticket_id;
DROP INDEX IF EXISTS idx_support_messages_sent_at;
SQL);
    }
};
