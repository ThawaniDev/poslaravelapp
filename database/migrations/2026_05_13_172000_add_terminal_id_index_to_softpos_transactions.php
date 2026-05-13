<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a performance index on softpos_transactions.terminal_id.
 *
 * Queries that filter or join on terminal_id (e.g. per-terminal analytics,
 * AdminSoftPosController::transactions, and ReconcileSoftPosCountersCommand)
 * perform a full-table scan without this index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('softpos_transactions', function (Blueprint $table) {
            $table->index('terminal_id', 'softpos_txn_terminal_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('softpos_transactions', function (Blueprint $table) {
            $table->dropIndex('softpos_txn_terminal_id_idx');
        });
    }
};
