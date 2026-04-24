<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite (used for the in-memory test schema). Add indexes idempotently.
            $this->createIndexIfMissing('customers', 'customers_org_phone_idx', ['organization_id', 'phone']);
            $this->createIndexIfMissing('customers', 'customers_org_name_idx', ['organization_id', 'name']);
            $this->createIndexIfMissing('customers', 'customers_loyalty_code_idx', ['loyalty_code']);
            $this->createIndexIfMissing('customers', 'customers_org_updated_idx', ['organization_id', 'updated_at']);
            $this->createIndexIfMissing('loyalty_transactions', 'loyalty_txn_customer_idx', ['customer_id']);
            $this->createIndexIfMissing('store_credit_transactions', 'store_credit_txn_customer_idx', ['customer_id']);
            $this->createIndexIfMissing('digital_receipt_log', 'digital_receipt_order_idx', ['order_id']);
            return;
        }

        // PostgreSQL: use raw IF NOT EXISTS for idempotency.
        \DB::statement('CREATE INDEX IF NOT EXISTS customers_org_phone_idx ON customers (organization_id, phone)');
        \DB::statement('CREATE INDEX IF NOT EXISTS customers_org_name_idx ON customers (organization_id, name)');
        \DB::statement('CREATE INDEX IF NOT EXISTS customers_loyalty_code_idx ON customers (loyalty_code)');
        \DB::statement('CREATE INDEX IF NOT EXISTS customers_org_updated_idx ON customers (organization_id, updated_at)');
        \DB::statement('CREATE INDEX IF NOT EXISTS loyalty_txn_customer_idx ON loyalty_transactions (customer_id)');
        \DB::statement('CREATE INDEX IF NOT EXISTS store_credit_txn_customer_idx ON store_credit_transactions (customer_id)');
        \DB::statement('CREATE INDEX IF NOT EXISTS digital_receipt_order_idx ON digital_receipt_log (order_id)');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            foreach ([
                'customers_org_phone_idx',
                'customers_org_name_idx',
                'customers_loyalty_code_idx',
                'customers_org_updated_idx',
                'loyalty_txn_customer_idx',
                'store_credit_txn_customer_idx',
                'digital_receipt_order_idx',
            ] as $idx) {
                try { \DB::statement("DROP INDEX IF EXISTS {$idx}"); } catch (\Throwable $e) {}
            }
            return;
        }
        foreach ([
            'customers_org_phone_idx',
            'customers_org_name_idx',
            'customers_loyalty_code_idx',
            'customers_org_updated_idx',
            'loyalty_txn_customer_idx',
            'store_credit_txn_customer_idx',
            'digital_receipt_order_idx',
        ] as $idx) {
            \DB::statement("DROP INDEX IF EXISTS {$idx}");
        }
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        try {
            $cols = implode(',', $columns);
            \DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$cols})");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
