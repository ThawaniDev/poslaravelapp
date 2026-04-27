<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing columns to payment-related tables:
 * - payments: status, nearpay_transaction_id, sync_version, updated_at
 * - expenses: updated_at (enables edit tracking)
 * - gift_cards: created_at (for sorting)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── payments ────────────────────────────────────────────
        if (!Schema::hasColumn('payments', 'status')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status', 20)->default('completed')->after('loyalty_points_used');
            });
        }
        if (!Schema::hasColumn('payments', 'nearpay_transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('nearpay_transaction_id', 100)->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('payments', 'sync_version')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->integer('sync_version')->default(1)->after('nearpay_transaction_id');
            });
        }
        if (!Schema::hasColumn('payments', 'updated_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }

        // ─── expenses ────────────────────────────────────────────
        if (!Schema::hasColumn('expenses', 'created_at')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable()->after('expense_date');
            });
        }
        if (!Schema::hasColumn('expenses', 'updated_at')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }

        // Postgres only: add index for payment date range queries
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('CREATE INDEX IF NOT EXISTS idx_payments_created_at ON payments(created_at)');
            DB::unprepared('CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status)');
            DB::unprepared('CREATE INDEX IF NOT EXISTS idx_expenses_store_date ON expenses(store_id, expense_date)');
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumnIfExists('status');
            $table->dropColumnIfExists('nearpay_transaction_id');
            $table->dropColumnIfExists('sync_version');
            $table->dropColumnIfExists('updated_at');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumnIfExists('created_at');
            $table->dropColumnIfExists('updated_at');
        });
    }
};
