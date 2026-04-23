<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add the indexes mandated by the Barcode Label Printing feature spec
 * (section 6.2):
 *   - label_templates_org              on (organization_id)
 *   - label_templates_org_default      on (organization_id, is_default)
 *   - label_print_history_store_date   on (store_id, printed_at)
 *
 * SQLite test schema is recreated from scratch so we can add via Schema
 * builder when on sqlite. Postgres uses raw SQL with IF NOT EXISTS for
 * idempotency in case the supabase clone already has them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::hasTable('label_templates')) {
                Schema::table('label_templates', function ($table) {
                    $table->index(['organization_id'], 'label_templates_org');
                    $table->index(['organization_id', 'is_default'], 'label_templates_org_default');
                });
            }
            if (Schema::hasTable('label_print_history')) {
                Schema::table('label_print_history', function ($table) {
                    $table->index(['store_id', 'printed_at'], 'label_print_history_store_date');
                });
            }
            return;
        }

        // Postgres
        DB::statement('CREATE INDEX IF NOT EXISTS label_templates_org ON label_templates (organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS label_templates_org_default ON label_templates (organization_id, is_default)');
        DB::statement('CREATE INDEX IF NOT EXISTS label_print_history_store_date ON label_print_history (store_id, printed_at)');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::hasTable('label_templates')) {
                Schema::table('label_templates', function ($table) {
                    $table->dropIndex('label_templates_org');
                    $table->dropIndex('label_templates_org_default');
                });
            }
            if (Schema::hasTable('label_print_history')) {
                Schema::table('label_print_history', function ($table) {
                    $table->dropIndex('label_print_history_store_date');
                });
            }
            return;
        }

        DB::statement('DROP INDEX IF EXISTS label_templates_org');
        DB::statement('DROP INDEX IF EXISTS label_templates_org_default');
        DB::statement('DROP INDEX IF EXISTS label_print_history_store_date');
    }
};
