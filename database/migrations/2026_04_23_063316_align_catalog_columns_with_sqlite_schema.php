<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the production Postgres schema with the additional product +
 * supplier columns the SQLite test schema (and the Flutter UI) already
 * use:
 *
 *   products: offer_price, offer_start, offer_end, min_order_qty,
 *             max_order_qty
 *   suppliers: contact_person, tax_number
 *
 * The earlier sqlite test migration added these so tests pass, but
 * the canonical Postgres CREATE migration never knew about them.
 * Each column is added defensively (only if missing) so this migration
 * is safe to re-run on environments where some columns already exist.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'offer_price')) {
                $table->decimal('offer_price', 10, 2)->nullable()->after('cost_price');
            }
            if (! Schema::hasColumn('products', 'offer_start')) {
                $table->timestamp('offer_start')->nullable()->after('offer_price');
            }
            if (! Schema::hasColumn('products', 'offer_end')) {
                $table->timestamp('offer_end')->nullable()->after('offer_start');
            }
            if (! Schema::hasColumn('products', 'min_order_qty')) {
                $table->decimal('min_order_qty', 10, 3)->nullable()->after('offer_end');
            }
            if (! Schema::hasColumn('products', 'max_order_qty')) {
                $table->decimal('max_order_qty', 10, 3)->nullable()->after('min_order_qty');
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'contact_person')) {
                $table->string('contact_person', 255)->nullable()->after('name');
            }
            if (! Schema::hasColumn('suppliers', 'tax_number')) {
                $table->string('tax_number', 50)->nullable()->after('contact_person');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['offer_price', 'offer_start', 'offer_end', 'min_order_qty', 'max_order_qty']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'tax_number']);
        });
    }
};
