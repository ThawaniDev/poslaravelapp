<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite Test Schema
 *
 * Creates essential tables (normally created via raw PostgreSQL SQL in 2026_03_10_040* migrations)
 * using Schema Builder for SQLite test database compatibility.
 *
 * Only runs when DB_CONNECTION=sqlite (testing environment).
 * In production, the real PostgreSQL migrations handle DDL.
 *
 * Table names + columns MUST match model $table + $fillable exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        // ─── Core: organizations ─────────────────────────────
        // Model: App\Domain\Core\Models\Organization
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug', 100)->nullable();
            $table->string('cr_number', 50)->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('business_type', 50)->nullable();
            $table->text('logo_url')->nullable();
            $table->string('country', 5)->default('SA');
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Core: stores ────────────────────────────────────
        // Model: App\Domain\Core\Models\Store
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug', 100)->nullable();
            $table->string('branch_code', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('timezone', 50)->default('Asia/Riyadh');
            $table->string('currency', 10)->default('SAR');
            $table->string('locale', 10)->default('ar');
            $table->string('business_type', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main_branch')->default(false);
            $table->decimal('storage_used_mb', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ─── Core: registers ─────────────────────────────────
        Schema::create('registers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // ─── Core: store_settings, store_working_hours, user_devices ──────
        // These tables are created by their own Schema Builder migrations:
        //   2025_01_15_000002_create_user_devices_table.php
        //   2025_01_15_000003_create_store_settings_tables.php
        // So they are NOT duplicated here.

        // ─── Admin: admin_users ──────────────────────────────
        if (!Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // ─── BusinessType: business_type_templates ───────────
        // Model: App\Domain\ProviderRegistration\Models\BusinessTypeTemplate
        Schema::create('business_type_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('icon', 50)->nullable();
            $table->json('template_json')->nullable();
            $table->json('sample_products_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
        });

        // ─── Onboarding: onboarding_progress ─────────────────
        // Model: App\Domain\ProviderRegistration\Models\OnboardingProgress
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('current_step', 50)->nullable();
            $table->json('completed_steps')->default('[]');
            $table->json('checklist_items')->nullable();
            $table->boolean('is_wizard_completed')->default(false);
            $table->boolean('is_checklist_dismissed')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        // ─── Auth: otp_verifications ─────────────────────────
        // Created by 2025_01_15_000001_create_otp_verifications_table.php
        // NOT duplicated here.

        // ─── Subscription: subscription_plans ────────────────
        // Model: App\Domain\Subscription\Models\SubscriptionPlan
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('annual_price', 10, 2)->nullable();
            $table->integer('trial_days')->default(0);
            $table->integer('grace_period_days')->default(3);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Subscription: plan_feature_toggles ──────────────
        // Model: App\Domain\Subscription\Models\PlanFeatureToggle
        Schema::create('plan_feature_toggles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_plan_id');
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->string('feature_key');
            $table->boolean('is_enabled')->default(true);
        });

        // ─── Subscription: plan_limits ───────────────────────
        // Model: App\Domain\Subscription\Models\PlanLimit
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_plan_id');
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->string('limit_key');
            $table->integer('limit_value')->default(-1);
            $table->decimal('price_per_extra_unit', 10, 2)->nullable();
        });

        // ─── Billing: store_subscriptions ────────────────────
        // Model: App\Domain\ProviderSubscription\Models\StoreSubscription
        Schema::create('store_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->uuid('subscription_plan_id');
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans');
            $table->string('status', 30)->default('active');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->string('payment_method', 30)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        // ─── Billing: invoices ───────────────────────────────
        // Model: App\Domain\ProviderSubscription\Models\Invoice
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_subscription_id')->nullable();
            $table->foreign('store_subscription_id')->references('id')->on('store_subscriptions');
            $table->string('invoice_number', 50)->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('pdf_url')->nullable();
            $table->timestamps();
        });

        // ─── Billing: invoice_line_items ─────────────────────
        // Model: App\Domain\ProviderSubscription\Models\InvoiceLineItem
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
        });

        // ─── Billing: provider_limit_overrides ───────────────
        // Model: App\Domain\ProviderSubscription\Models\ProviderLimitOverride
        Schema::create('provider_limit_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('limit_key');
            $table->integer('override_value');
            $table->string('reason')->nullable();
            $table->uuid('set_by')->nullable();
            $table->timestamp('expires_at')->nullable();
        });

        // ─── Billing: subscription_usage_snapshots ───────────
        // Model: App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot
        Schema::create('subscription_usage_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('resource_type');
            $table->integer('current_count')->default(0);
            $table->integer('plan_limit')->nullable();
            $table->date('snapshot_date')->nullable();
        });

        // ─── Billing: add_ons (legacy) ───────────────────────
        Schema::create('add_ons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Billing: plan_add_ons ───────────────────────────
        // Model: App\Domain\Subscription\Models\PlanAddOn
        Schema::create('plan_add_ons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Billing: store_add_ons (pivot) ──────────────────
        // Model: App\Domain\ProviderSubscription\Models\StoreAddOn
        Schema::create('store_add_ons', function (Blueprint $table) {
            $table->id();
            $table->uuid('store_id');
            $table->uuid('plan_add_on_id');
            $table->timestamp('activated_at')->nullable();
            $table->boolean('is_active')->default(true);
        });

        // ─── Security: pin_overrides ─────────────────────────
        // Model: App\Domain\Security\Models\PinOverride
        if (!Schema::hasTable('pin_overrides')) {
            Schema::create('pin_overrides', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('requesting_user_id');
                $table->uuid('authorizing_user_id');
                $table->string('permission_code');
                $table->json('action_context')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Security: role_audit_log ────────────────────────
        // Model: App\Domain\Security\Models\RoleAuditLog
        if (!Schema::hasTable('role_audit_log')) {
            Schema::create('role_audit_log', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->string('action');
                $table->unsignedBigInteger('role_id')->nullable();
                $table->json('details')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Generic: audit_logs (general) ───────────────────
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->string('action');
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();
            });
        }

        // ══════════════════════════════════════════════════════
        // ─── CATALOG DOMAIN ──────────────────────────────────
        // ══════════════════════════════════════════════════════

        // ─── Catalog: categories ─────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sync_version')->default(1);
            $table->timestamps();
        });

        // ─── Catalog: products ───────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('category_id')->nullable();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 50)->nullable();
            $table->decimal('sell_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('unit', 20)->default('piece');
            $table->decimal('tax_rate', 5, 2)->default(15.00);
            $table->boolean('is_weighable')->default(false);
            $table->decimal('tare_weight', 8, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_combo')->default(false);
            $table->boolean('age_restricted')->default(false);
            $table->text('image_url')->nullable();
            $table->integer('sync_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Catalog: product_barcodes ───────────────────────
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('barcode', 50)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        // ─── Catalog: store_prices ───────────────────────────
        Schema::create('store_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('product_id');
            $table->decimal('sell_price', 12, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'product_id']);
        });

        // ─── Catalog: product_variant_groups ─────────────────
        Schema::create('product_variant_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Catalog: product_variants ───────────────────────
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('variant_group_id');
            $table->string('variant_value', 100);
            $table->string('variant_value_ar', 100)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 50)->nullable();
            $table->decimal('price_adjustment', 12, 2)->default(0);
            $table->text('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Catalog: product_images ─────────────────────────
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->text('image_url');
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        // ─── Catalog: combo_products ─────────────────────────
        Schema::create('combo_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('name');
            $table->decimal('combo_price', 12, 2)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Catalog: combo_product_items ────────────────────
        Schema::create('combo_product_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('combo_product_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->boolean('is_optional')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        // ─── Catalog: modifier_groups ────────────────────────
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Catalog: modifier_options ───────────────────────
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('modifier_group_id');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->decimal('price_adjustment', 12, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        // ─── Catalog: suppliers ──────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Catalog: product_suppliers ──────────────────────
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('supplier_id');
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->string('supplier_sku', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['product_id', 'supplier_id']);
        });

        // ─── Catalog: internal_barcode_sequence ──────────────
        Schema::create('internal_barcode_sequence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->unique();
            $table->integer('last_sequence')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        // ═══════════════════════════════════════════════════════
        // Inventory Domain
        // ═══════════════════════════════════════════════════════

        // ─── Inventory: stock_levels ─────────────────────────
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->decimal('reorder_point', 12, 2)->nullable();
            $table->decimal('max_stock_level', 12, 2)->nullable();
            $table->decimal('average_cost', 12, 2)->default(0);
            $table->integer('sync_version')->default(1);
            $table->unique(['store_id', 'product_id']);
        });

        // ─── Inventory: stock_movements ──────────────────────
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('product_id');
            $table->string('type', 30);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type', 30)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ─── Inventory: goods_receipts ───────────────────────
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('supplier_id')->nullable();
            $table->uuid('purchase_order_id')->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
        });

        // ─── Inventory: goods_receipt_items ───────────────────
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('goods_receipt_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
        });

        // ─── Inventory: stock_adjustments ────────────────────
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('type', 20);
            $table->string('reason_code', 30)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('adjusted_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ─── Inventory: stock_adjustment_items ───────────────
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('stock_adjustment_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
        });

        // ─── Inventory: stock_transfers ──────────────────────
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('from_store_id');
            $table->uuid('to_store_id');
            $table->string('status', 20)->default('pending');
            $table->string('reference_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        // ─── Inventory: stock_transfer_items ─────────────────
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('stock_transfer_id');
            $table->uuid('product_id');
            $table->decimal('quantity_sent', 12, 2);
            $table->decimal('quantity_received', 12, 2)->nullable();
        });

        // ─── Inventory: purchase_orders ──────────────────────
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('store_id');
            $table->uuid('supplier_id')->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('expected_date')->nullable();
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        // ─── Inventory: purchase_order_items ─────────────────
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('product_id');
            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('quantity_received', 12, 2)->default(0);
        });

        // ─── Inventory: stock_batches ────────────────────────
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('product_id');
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->uuid('goods_receipt_id')->nullable();
        });

        // ─── Inventory: recipes ──────────────────────────────
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('product_id');
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->decimal('yield_quantity', 12, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Inventory: recipe_ingredients ───────────────────
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recipe_id');
            $table->uuid('ingredient_product_id');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20)->nullable();
            $table->decimal('waste_percent', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $tables = [
            // Inventory domain
            'recipe_ingredients', 'recipes',
            'stock_batches', 'purchase_order_items', 'purchase_orders',
            'stock_transfer_items', 'stock_transfers',
            'stock_adjustment_items', 'stock_adjustments',
            'goods_receipt_items', 'goods_receipts',
            'stock_movements', 'stock_levels',
            // Catalog domain
            'internal_barcode_sequence', 'product_suppliers', 'suppliers',
            'modifier_options', 'modifier_groups',
            'combo_product_items', 'combo_products',
            'product_images', 'product_variants', 'product_variant_groups',
            'store_prices', 'product_barcodes', 'products', 'categories',
            // Original tables
            'audit_logs', 'role_audit_log', 'pin_overrides',
            'store_add_ons', 'plan_add_ons', 'add_ons',
            'subscription_usage_snapshots', 'provider_limit_overrides',
            'invoice_line_items', 'invoices', 'store_subscriptions',
            'plan_limits', 'plan_feature_toggles', 'subscription_plans',
            'onboarding_progress', 'business_type_templates',
            'admin_users',
            'registers', 'stores', 'organizations',
        ];

        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }
};
