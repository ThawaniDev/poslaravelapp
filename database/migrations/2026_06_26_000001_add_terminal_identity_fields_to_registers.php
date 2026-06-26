<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            // ── EdfaPay / Terminal Identity ───────────────────────────
            // TRSM = Terminal Risk State Machine identifier issued by EdfaPay
            if (! Schema::hasColumn('registers', 'trsm')) {
                $table->string('trsm', 100)->nullable()->after('edfapay_token_updated_at')
                      ->comment('EdfaPay TRSM identifier for this terminal');
            }

            // Provider TID (Terminal ID) and MID (Merchant ID) from the payment provider
            if (! Schema::hasColumn('registers', 'provider_tid')) {
                $table->string('provider_tid', 50)->nullable()->after('trsm')
                      ->comment('Provider-issued Terminal ID');
            }
            if (! Schema::hasColumn('registers', 'provider_mid')) {
                $table->string('provider_mid', 50)->nullable()->after('provider_tid')
                      ->comment('Provider-issued Merchant ID');
            }

            // ── Location ─────────────────────────────────────────────
            if (! Schema::hasColumn('registers', 'location_lat')) {
                $table->decimal('location_lat', 10, 7)->nullable()->after('provider_mid')
                      ->comment('GPS latitude of the terminal location');
            }
            if (! Schema::hasColumn('registers', 'location_lng')) {
                $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat')
                      ->comment('GPS longitude of the terminal location');
            }

            // ── Device ID uniqueness ─────────────────────────────────
            // A device_id must be unique per store (one physical device → one register).
            // We add a partial unique index rather than a column-level unique constraint
            // so that NULL device_id values don't violate uniqueness (multiple unassigned
            // registers are allowed).
            // Only add if neither index already exists.
            if (! $this->indexExists('registers', 'registers_store_device_unique')) {
                $table->unique(['store_id', 'device_id'], 'registers_store_device_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn(['trsm', 'provider_tid', 'provider_mid', 'location_lat', 'location_lng']);
            if ($this->indexExists('registers', 'registers_store_device_unique')) {
                $table->dropUnique('registers_store_device_unique');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        try {
            $sm      = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes($tableName);
            return isset($indexes[$indexName]);
        } catch (\Throwable) {
            return false;
        }
    }
};
