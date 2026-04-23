<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run each statement in its own transaction so a failed CREATE INDEX
     * (e.g. the index already exists on Postgres) does not poison the
     * remainder of the migration.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // ── registers: short code used as transaction-number prefix ────────
        if (!Schema::hasColumn('registers', 'code')) {
            Schema::table('registers', function (Blueprint $table) {
                $table->string('code', 8)->nullable()->after('name');
            });
        }
        $this->safeIndex('registers', ['store_id', 'code'], 'idx_registers_store_code');

        $this->backfillRegisterCodes();

        // ── store_settings: held cart expiry + return-without-receipt ─────
        if (Schema::hasTable('store_settings')) {
            if (!Schema::hasColumn('store_settings', 'held_cart_expiry_hours')) {
                Schema::table('store_settings', function (Blueprint $table) {
                    $table->integer('held_cart_expiry_hours')->default(24);
                });
            }
            if (!Schema::hasColumn('store_settings', 'return_without_receipt_policy')) {
                Schema::table('store_settings', function (Blueprint $table) {
                    // values: 'deny' | 'refund_to_credit' | 'exchange_only'
                    $table->string('return_without_receipt_policy', 20)->default('deny');
                });
            }
        }

        // ── transaction_items: persist age verification metadata ──────────
        if (!Schema::hasColumn('transaction_items', 'age_verified_at')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->timestamp('age_verified_at')->nullable();
                $table->uuid('age_verified_by')->nullable();
            });
        }

        // ── transactions: approver + void reason ──────────────────────────
        if (!Schema::hasColumn('transactions', 'approver_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->uuid('approver_id')->nullable();
                $table->string('void_reason', 500)->nullable();
            });
        }

        // ── exchange_transactions: ensure net_amount column exists ────────
        if (Schema::hasTable('exchange_transactions') && !Schema::hasColumn('exchange_transactions', 'net_amount')) {
            Schema::table('exchange_transactions', function (Blueprint $table) {
                $table->decimal('net_amount', 12, 2)->default(0);
            });
        }

        // ── lookup indexes ────────────────────────────────────────────────
        $this->safeIndex('tax_exemptions', ['transaction_id'], 'idx_tax_exemptions_txn');
        $this->safeIndex('transactions', ['customer_id'], 'idx_transactions_customer');
        $this->safeIndex('transactions', ['external_id'], 'idx_transactions_external');
        $this->safeIndex('transactions', ['store_id', 'sync_status'], 'idx_transactions_store_sync');
        $this->safeIndex('transactions', ['register_id', 'created_at'], 'idx_transactions_register_created');
        $this->safeIndex('transaction_items', ['barcode'], 'idx_transaction_items_barcode');
        $this->safeIndex('payments', ['transaction_id'], 'idx_payments_txn');
        $this->safeIndex('held_carts', ['store_id', 'register_id'], 'idx_held_carts_store_register');

        // Postgres partial index for "currently active held carts" — fall
        // back to a regular composite when not on Postgres (SQLite testing).
        if (DB::connection()->getDriverName() === 'pgsql') {
            try { DB::statement('CREATE INDEX IF NOT EXISTS idx_held_carts_active ON held_carts (store_id) WHERE recalled_at IS NULL'); } catch (\Throwable $e) {}
        } else {
            $this->safeIndex('held_carts', ['store_id', 'recalled_at'], 'idx_held_carts_active');
        }
    }

    public function down(): void
    {
        $this->safeDropIndex('tax_exemptions', 'idx_tax_exemptions_txn');
        $this->safeDropIndex('transactions', 'idx_transactions_customer');
        $this->safeDropIndex('transactions', 'idx_transactions_external');
        $this->safeDropIndex('transactions', 'idx_transactions_store_sync');
        $this->safeDropIndex('transactions', 'idx_transactions_register_created');
        $this->safeDropIndex('transaction_items', 'idx_transaction_items_barcode');
        $this->safeDropIndex('payments', 'idx_payments_txn');
        $this->safeDropIndex('held_carts', 'idx_held_carts_store_register');
        $this->safeDropIndex('held_carts', 'idx_held_carts_active');
        $this->safeDropIndex('registers', 'idx_registers_store_code');

        if (Schema::hasTable('store_settings')) {
            Schema::table('store_settings', function (Blueprint $table) {
                if (Schema::hasColumn('store_settings', 'held_cart_expiry_hours')) $table->dropColumn('held_cart_expiry_hours');
                if (Schema::hasColumn('store_settings', 'return_without_receipt_policy')) $table->dropColumn('return_without_receipt_policy');
            });
        }
        Schema::table('transaction_items', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_items', 'age_verified_by')) $table->dropColumn('age_verified_by');
            if (Schema::hasColumn('transaction_items', 'age_verified_at')) $table->dropColumn('age_verified_at');
        });
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'approver_id')) $table->dropColumn('approver_id');
            if (Schema::hasColumn('transactions', 'void_reason')) $table->dropColumn('void_reason');
        });
        if (Schema::hasColumn('exchange_transactions', 'net_amount')) {
            Schema::table('exchange_transactions', fn (Blueprint $t) => $t->dropColumn('net_amount'));
        }
        if (Schema::hasColumn('registers', 'code')) {
            Schema::table('registers', fn (Blueprint $t) => $t->dropColumn('code'));
        }
    }

    private function safeIndex(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table)) return;
        $driver = DB::connection()->getDriverName();
        try {
            if ($driver === 'pgsql') {
                $cols = implode(',', array_map(fn ($c) => '"' . $c . '"', $columns));
                DB::statement("CREATE INDEX IF NOT EXISTS \"{$name}\" ON \"{$table}\" ({$cols})");
            } else {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        } catch (\Throwable $e) {
            // index already exists — ignore
        }
    }

    private function safeDropIndex(string $table, string $name): void
    {
        if (!Schema::hasTable($table)) return;
        $driver = DB::connection()->getDriverName();
        try {
            if ($driver === 'pgsql') {
                DB::statement("DROP INDEX IF EXISTS \"{$name}\"");
            } else {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        } catch (\Throwable $e) {}
    }

    private function backfillRegisterCodes(): void
    {
        $rows = DB::table('registers')->whereNull('code')->get(['id', 'store_id', 'name']);
        $usedPerStore = [];

        foreach ($rows as $row) {
            $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $row->name ?? '') ?: 'REG');
            $base = substr($base, 0, 6) ?: 'REG';

            $usedPerStore[$row->store_id] ??= DB::table('registers')
                ->where('store_id', $row->store_id)
                ->whereNotNull('code')
                ->pluck('code')
                ->all();

            $candidate = $base;
            $suffix = 1;
            while (in_array($candidate, $usedPerStore[$row->store_id], true)) {
                $candidate = substr($base, 0, max(1, 6 - strlen((string) $suffix))) . $suffix;
                $suffix++;
                if ($suffix > 999) { $candidate = $base . substr((string) $row->id, 0, 4); break; }
            }
            $usedPerStore[$row->store_id][] = $candidate;
            DB::table('registers')->where('id', $row->id)->update(['code' => $candidate]);
        }
    }
};
