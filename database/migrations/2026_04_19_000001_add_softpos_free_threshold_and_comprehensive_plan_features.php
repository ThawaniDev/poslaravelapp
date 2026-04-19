<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add softPOS free-after-threshold columns to subscription_plans ──
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('softpos_free_eligible')->default(false)->after('is_highlighted');
            $table->unsignedInteger('softpos_free_threshold')->nullable()->after('softpos_free_eligible')
                ->comment('Number of SoftPOS transactions to reach for free plan');
            $table->string('softpos_free_threshold_period', 20)->default('monthly')->after('softpos_free_threshold')
                ->comment('Period for threshold: monthly, yearly, lifetime');
        });

        // ── 2. Track softPOS transactions at the store subscription level ──
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->boolean('is_softpos_free')->default(false)->after('cancelled_at')
                ->comment('Whether the subscription is currently free due to SoftPOS threshold');
            $table->unsignedInteger('softpos_transaction_count')->default(0)->after('is_softpos_free')
                ->comment('Current SoftPOS transaction count for the threshold period');
            $table->timestamp('softpos_count_reset_at')->nullable()->after('softpos_transaction_count')
                ->comment('When the softPOS transaction count was last reset');
            $table->decimal('original_amount', 10, 2)->nullable()->after('softpos_count_reset_at')
                ->comment('Original subscription amount before softPOS discount');
            $table->string('discount_reason')->nullable()->after('original_amount');
        });

        // ── 3. Create softpos_transactions table for tracking ──
        Schema::create('softpos_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable();
            $table->decimal('amount', 12, 3);
            $table->string('currency', 5)->default('SAR');
            $table->string('transaction_ref')->nullable();
            $table->string('payment_method', 50)->nullable()->comment('mada, visa, mastercard, apple_pay, stc_pay');
            $table->string('terminal_id')->nullable();
            $table->string('status', 20)->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'status', 'created_at']);
        });

        // ── 4. Add gateway-related fields to invoices for PayTabs tracking ──
        if (! Schema::hasColumn('invoices', 'gateway_transaction_ref')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('gateway_transaction_ref')->nullable()->after('pdf_url');
                $table->string('gateway_name')->nullable()->after('gateway_transaction_ref');
                $table->string('payment_method_used')->nullable()->after('gateway_name');
                $table->json('gateway_response')->nullable()->after('payment_method_used');
            });
        }

        // ── 5. Add plan feature mapping table for sidebar/page gating ──
        Schema::create('plan_feature_route_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('feature_key', 100);
            $table->string('route_path', 200)->comment('Flutter route or Laravel route path');
            $table->string('sidebar_key', 100)->nullable()->comment('Sidebar item key for Flutter');
            $table->string('platform', 20)->default('both')->comment('flutter, laravel, both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['feature_key', 'route_path', 'platform'], 'feature_route_platform_unique');
            $table->index('feature_key');
        });

        // ── 6. Add deactivated_at to store_add_ons ──
        if (! Schema::hasColumn('store_add_ons', 'deactivated_at')) {
            Schema::table('store_add_ons', function (Blueprint $table) {
                $table->timestamp('deactivated_at')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_add_ons', 'deactivated_at')) {
            Schema::table('store_add_ons', function (Blueprint $table) {
                $table->dropColumn('deactivated_at');
            });
        }

        Schema::dropIfExists('plan_feature_route_mappings');
        Schema::dropIfExists('softpos_transactions');

        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'is_softpos_free',
                'softpos_transaction_count',
                'softpos_count_reset_at',
                'original_amount',
                'discount_reason',
            ]);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'softpos_free_eligible',
                'softpos_free_threshold',
                'softpos_free_threshold_period',
            ]);
        });

        if (Schema::hasColumn('invoices', 'gateway_transaction_ref')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn([
                    'gateway_transaction_ref',
                    'gateway_name',
                    'payment_method_used',
                    'gateway_response',
                ]);
            });
        }
    }
};
