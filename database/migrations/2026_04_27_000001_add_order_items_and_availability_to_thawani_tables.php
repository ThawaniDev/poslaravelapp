<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add order_items JSON column to thawani_order_mappings
        Schema::table('thawani_order_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('thawani_order_mappings', 'order_items')) {
                $table->jsonb('order_items')->nullable()->after('delivery_address');
            }
            if (!Schema::hasColumn('thawani_order_mappings', 'delivery_fee')) {
                $table->decimal('delivery_fee', 12, 3)->nullable()->after('order_items');
            }
            if (!Schema::hasColumn('thawani_order_mappings', 'notes')) {
                $table->text('notes')->nullable()->after('delivery_fee');
            }
            if (!Schema::hasColumn('thawani_order_mappings', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('thawani_order_mappings', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('scheduled_at');
            }
        });

        // Add store availability to thawani_store_config
        Schema::table('thawani_store_config', function (Blueprint $table) {
            if (!Schema::hasColumn('thawani_store_config', 'is_store_open')) {
                $table->boolean('is_store_open')->default(true)->after('auto_accept_orders');
            }
            if (!Schema::hasColumn('thawani_store_config', 'store_closed_reason')) {
                $table->string('store_closed_reason', 255)->nullable()->after('is_store_open');
            }
            if (!Schema::hasColumn('thawani_store_config', 'order_acceptance_timeout_minutes')) {
                $table->unsignedSmallInteger('order_acceptance_timeout_minutes')->default(5)->after('store_closed_reason');
            }
        });

        // Add reconciliation to thawani_settlements (if not exists from previous migration)
        Schema::table('thawani_settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('thawani_settlements', 'reconciled')) {
                $table->boolean('reconciled')->default(false)->after('thawani_reference');
            }
            if (!Schema::hasColumn('thawani_settlements', 'reconciled_at')) {
                $table->timestamp('reconciled_at')->nullable()->after('reconciled');
            }
            if (!Schema::hasColumn('thawani_settlements', 'reconciled_by')) {
                $table->uuid('reconciled_by')->nullable()->after('reconciled_at');
            }
            if (!Schema::hasColumn('thawani_settlements', 'notes')) {
                $table->text('notes')->nullable()->after('reconciled_by');
            }
        });

        // Add online_price and display_order to thawani_product_mappings if not exists
        Schema::table('thawani_product_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('thawani_product_mappings', 'online_price')) {
                $table->decimal('online_price', 12, 3)->nullable()->after('is_published');
            }
            if (!Schema::hasColumn('thawani_product_mappings', 'display_order')) {
                $table->unsignedInteger('display_order')->default(0)->after('online_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('thawani_order_mappings', function (Blueprint $table) {
            $table->dropColumn(['order_items', 'delivery_fee', 'notes', 'scheduled_at', 'rejected_at']);
        });

        Schema::table('thawani_store_config', function (Blueprint $table) {
            $table->dropColumn(['is_store_open', 'store_closed_reason', 'order_acceptance_timeout_minutes']);
        });

        Schema::table('thawani_product_mappings', function (Blueprint $table) {
            $table->dropColumn(['online_price', 'display_order']);
        });
    }
};
