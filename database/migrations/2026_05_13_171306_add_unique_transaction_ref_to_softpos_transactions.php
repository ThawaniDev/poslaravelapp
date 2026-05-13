<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a partial unique index on (organization_id, transaction_ref) WHERE transaction_ref IS NOT NULL.
     * Prevents double-counting from network retries or duplicate submissions.
     */
    public function up(): void
    {
        // PostgreSQL supports partial unique indexes natively.
        // For MySQL we add a regular unique index (transaction_ref is nullable so
        // MySQL only enforces uniqueness for non-null values by default on InnoDB).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS softpos_txn_org_ref_unique
                 ON softpos_transactions (organization_id, transaction_ref)
                 WHERE transaction_ref IS NOT NULL'
            );
        } else {
            Schema::table('softpos_transactions', function (Blueprint $table) {
                $table->unique(['organization_id', 'transaction_ref'], 'softpos_txn_org_ref_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS softpos_txn_org_ref_unique');
        } else {
            Schema::table('softpos_transactions', function (Blueprint $table) {
                $table->dropUnique('softpos_txn_org_ref_unique');
            });
        }
    }
};
