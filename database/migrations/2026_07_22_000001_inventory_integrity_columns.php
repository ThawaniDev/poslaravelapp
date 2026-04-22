<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns required for inventory data-integrity guarantees:
 *
 *  - stock_movements.idempotency_key — prevents double-applying the same
 *    receipt/adjustment when an API call is retried. Unique per
 *    (reference_type, reference_id, idempotency_key).
 *
 *  - stock_transfer_items.variance_qty / variance_reason — captures the
 *    qty difference when a transfer is received with less than was sent
 *    (in-transit loss, damage, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_movements') && !Schema::hasColumn('stock_movements', 'idempotency_key')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->after('performed_by');
            });

            // Partial unique index — only enforced when idempotency_key is provided.
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS uniq_stock_movements_idempotency '
                    . 'ON stock_movements (reference_type, reference_id, idempotency_key) '
                    . 'WHERE idempotency_key IS NOT NULL'
                );
            } else {
                // SQLite/MySQL fallback: composite index without partial filter.
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->index(['reference_type', 'reference_id', 'idempotency_key'], 'idx_stock_movements_idempotency');
                });
            }
        }

        if (Schema::hasTable('stock_transfer_items')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_transfer_items', 'variance_qty')) {
                    $table->decimal('variance_qty', 12, 3)->nullable()->after('quantity_received');
                }
                if (!Schema::hasColumn('stock_transfer_items', 'variance_reason')) {
                    $table->string('variance_reason', 255)->nullable()->after('variance_qty');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_movements')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS uniq_stock_movements_idempotency');
            } else {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->dropIndex('idx_stock_movements_idempotency');
                });
            }
            if (Schema::hasColumn('stock_movements', 'idempotency_key')) {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->dropColumn('idempotency_key');
                });
            }
        }

        if (Schema::hasTable('stock_transfer_items')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                foreach (['variance_qty', 'variance_reason'] as $col) {
                    if (Schema::hasColumn('stock_transfer_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
