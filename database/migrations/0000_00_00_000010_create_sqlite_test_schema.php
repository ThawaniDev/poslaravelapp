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

        // ─── Customer: customer_groups ────────────────────────
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->decimal('discount_percent', 5, 2)->default(0);
        });

        // ─── Customer: customers ──────────────────────────────
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('loyalty_code', 20)->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->decimal('store_credit_balance', 12, 2)->default(0);
            $table->uuid('group_id')->nullable();
            $table->string('tax_registration_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_spend', 12, 2)->default(0);
            $table->integer('visit_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->integer('sync_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Customer: loyalty_config ─────────────────────────
        Schema::create('loyalty_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->decimal('points_per_sar', 8, 2)->default(1);
            $table->decimal('sar_per_point', 8, 2)->default(0.01);
            $table->integer('min_redemption_points')->default(100);
            $table->integer('points_expiry_months')->default(12);
            $table->json('excluded_category_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Customer: loyalty_transactions ───────────────────
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type', 20);
            $table->integer('points');
            $table->integer('balance_after');
            $table->uuid('order_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Customer: store_credit_transactions ──────────────
        Schema::create('store_credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->uuid('order_id')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Customer: digital_receipt_log ────────────────────
        Schema::create('digital_receipt_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('channel', 20);
            $table->string('destination');
            $table->string('status', 20)->default('sent');
            $table->timestamp('sent_at')->nullable();
        });

        // ─── Label: label_templates ───────────────────────────
        Schema::create('label_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->decimal('label_width_mm', 8, 2)->default(50);
            $table->decimal('label_height_mm', 8, 2)->default(30);
            $table->json('layout_json')->nullable();
            $table->boolean('is_preset')->default(false);
            $table->boolean('is_default')->default(false);
            $table->uuid('created_by')->nullable();
            $table->integer('sync_version')->default(1);
            $table->timestamps();
        });

        // ─── Label: label_print_history ───────────────────────
        Schema::create('label_print_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('template_id');
            $table->uuid('printed_by')->nullable();
            $table->integer('product_count')->default(0);
            $table->integer('total_labels')->default(0);
            $table->string('printer_name')->nullable();
            $table->timestamp('printed_at')->nullable();
        });

        // ─── POS: pos_sessions ────────────────────────────────
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('register_id')->nullable();
            $table->uuid('cashier_id');
            $table->string('status', 20)->default('open');
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('closing_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('cash_difference', 12, 2)->nullable();
            $table->decimal('total_cash_sales', 12, 2)->default(0);
            $table->decimal('total_card_sales', 12, 2)->default(0);
            $table->decimal('total_other_sales', 12, 2)->default(0);
            $table->decimal('total_refunds', 12, 2)->default(0);
            $table->decimal('total_voids', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('z_report_printed')->default(false);
            $table->timestamps();
        });

        // ─── POS: transactions ────────────────────────────────
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('store_id');
            $table->uuid('register_id')->nullable();
            $table->uuid('pos_session_id')->nullable();
            $table->uuid('cashier_id');
            $table->uuid('customer_id')->nullable();
            $table->string('transaction_number', 50);
            $table->string('type', 20);
            $table->string('status', 20)->default('completed');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('tip_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->uuid('return_transaction_id')->nullable();
            $table->string('external_type', 50)->nullable();
            $table->string('external_id', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('zatca_uuid', 100)->nullable();
            $table->text('zatca_hash')->nullable();
            $table->text('zatca_qr_code')->nullable();
            $table->string('zatca_status', 20)->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->integer('sync_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── POS: transaction_items ───────────────────────────
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->uuid('product_id')->nullable();
            $table->string('barcode', 50)->nullable();
            $table->string('product_name');
            $table->string('product_name_ar')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('serial_number', 100)->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('modifier_selections')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_return_item')->default(false);
            $table->boolean('age_verified')->default(false);
        });

        // ─── POS: held_carts ──────────────────────────────────
        Schema::create('held_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('register_id')->nullable();
            $table->uuid('cashier_id');
            $table->uuid('customer_id')->nullable();
            $table->json('cart_data');
            $table->string('label')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('recalled_at')->nullable();
            $table->uuid('recalled_by')->nullable();
        });

        // ─── Order: orders ────────────────────────────────────
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('transaction_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('order_number', 50);
            $table->string('source', 20)->default('pos');
            $table->string('status', 20)->default('new');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->string('external_order_id', 100)->nullable();
            $table->text('delivery_address')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        // ─── Order: order_items ───────────────────────────────
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('variant_id')->nullable();
            $table->string('product_name');
            $table->string('product_name_ar')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
        });

        // ─── Order: order_status_history ──────────────────────
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->uuid('changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Order: returns ───────────────────────────────────
        Schema::create('returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->string('return_number', 50);
            $table->string('type', 20)->default('partial');
            $table->string('reason_code', 50)->nullable();
            $table->string('refund_method', 20)->default('cash');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_refund', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('processed_by');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Order: return_items ──────────────────────────────
        Schema::create('return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('return_id');
            $table->uuid('order_item_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('reason', 100)->nullable();
            $table->string('condition', 20)->default('good');
        });

        // ─── Payment: payments ────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->string('method', 30);
            $table->decimal('amount', 12, 2);
            $table->decimal('cash_tendered', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->nullable();
            $table->decimal('tip_amount', 12, 2)->default(0);
            $table->string('card_brand', 20)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_auth_code', 50)->nullable();
            $table->string('card_reference', 100)->nullable();
            $table->string('gift_card_code', 50)->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->integer('loyalty_points_used')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        // ─── Payment: cash_sessions ───────────────────────────
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('terminal_id')->nullable();
            $table->uuid('opened_by');
            $table->uuid('closed_by')->nullable();
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_notes')->nullable();
        });

        // ─── Payment: cash_events ─────────────────────────────
        Schema::create('cash_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_session_id');
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('performed_by');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Payment: expenses ────────────────────────────────
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('cash_session_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->text('receipt_image_url')->nullable();
            $table->uuid('recorded_by');
            $table->date('expense_date')->nullable();
        });

        // ─── Payment: gift_cards ──────────────────────────────
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('code', 50)->unique();
            $table->string('barcode', 50)->nullable();
            $table->decimal('initial_amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->string('recipient_name')->nullable();
            $table->string('status', 20)->default('active');
            $table->uuid('issued_by')->nullable();
            $table->uuid('issued_at_store')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });

        // ─── Payment: refunds ─────────────────────────────────
        // ─── Promotions ─────────────────────────────────────
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->integer('buy_quantity')->nullable();
            $table->integer('get_quantity')->nullable();
            $table->decimal('get_discount_percent', 5, 2)->nullable();
            $table->decimal('bundle_price', 12, 2)->nullable();
            $table->decimal('min_order_total', 12, 2)->nullable();
            $table->integer('min_item_quantity')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->json('active_days')->nullable();
            $table->time('active_time_from')->nullable();
            $table->time('active_time_to')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('max_uses_per_customer')->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_coupon')->default(false);
            $table->integer('usage_count')->default(0);
            $table->integer('sync_version')->default(1);
            $table->timestamps();
        });

        Schema::create('promotion_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['promotion_id', 'product_id']);
        });

        Schema::create('promotion_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            $table->uuid('category_id');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->unique(['promotion_id', 'category_id']);
        });

        Schema::create('promotion_customer_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            $table->uuid('customer_group_id');
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->cascadeOnDelete();
            $table->unique(['promotion_id', 'customer_group_id']);
        });

        Schema::create('coupon_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->integer('max_uses')->default(1);
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('promotion_usage_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->foreign('promotion_id')->references('id')->on('promotions');
            $table->uuid('coupon_code_id')->nullable();
            $table->foreign('coupon_code_id')->references('id')->on('coupon_codes');
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->uuid('customer_id')->nullable();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('bundle_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promotion_id');
            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->integer('quantity')->default(1);
        });

        // ─── Staff Management ────────────────────────────────
        Schema::create('staff_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('pin_hash')->nullable();
            $table->string('nfc_badge_uid', 50)->nullable()->unique();
            $table->boolean('biometric_enabled')->default(false);
            $table->string('employment_type', 20)->default('full_time');
            $table->string('salary_type', 20)->default('monthly');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('language_preference', 5)->default('ar');
            $table->timestamps();
        });

        Schema::create('staff_branch_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->foreign('staff_user_id')->references('id')->on('staff_users');
            $table->uuid('branch_id');
            $table->foreign('branch_id')->references('id')->on('stores');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->unique(['staff_user_id', 'branch_id']);
        });

        Schema::create('shift_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('color', 7)->default('#4CAF50');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('staff_user_id');
            $table->foreign('staff_user_id')->references('id')->on('staff_users');
            $table->uuid('shift_template_id');
            $table->foreign('shift_template_id')->references('id')->on('shift_templates');
            $table->date('date');
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->uuid('swapped_with_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['staff_user_id', 'date', 'shift_template_id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->foreign('staff_user_id')->references('id')->on('staff_users');
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->integer('break_minutes')->default(0);
            $table->uuid('scheduled_shift_id')->nullable();
            $table->integer('overtime_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->string('auth_method', 20)->default('pin');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('break_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_record_id');
            $table->foreign('attendance_record_id')->references('id')->on('attendance_records')->cascadeOnDelete();
            $table->timestamp('break_start');
            $table->timestamp('break_end')->nullable();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('staff_user_id')->nullable();
            $table->string('type', 20)->default('flat_percentage');
            $table->decimal('percentage', 5, 2)->nullable();
            $table->json('tiers_json')->nullable();
            $table->uuid('product_category_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('commission_earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->foreign('staff_user_id')->references('id')->on('staff_users');
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->uuid('commission_rule_id');
            $table->foreign('commission_rule_id')->references('id')->on('commission_rules');
            $table->decimal('order_total', 12, 3);
            $table->decimal('commission_amount', 12, 3);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('staff_activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->foreign('staff_user_id')->references('id')->on('staff_users');
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('action', 100);
            $table->string('entity_type', 50)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->foreign('staff_user_id')->references('id')->on('staff_users');
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('transactions_count')->default(0);
            $table->text('notes')->nullable();
        });

        Schema::create('staff_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->foreign('staff_user_id')->references('id')->on('staff_users');
            $table->string('document_type', 50);
            $table->string('file_url', 500);
            $table->date('expiry_date')->nullable();
            $table->timestamp('uploaded_at')->nullable();
        });

        // ─── Reports & Analytics ─────────────────────────────
        Schema::create('product_sales_summary', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->date('date');
            $table->decimal('quantity_sold', 12, 3)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('return_quantity', 12, 3)->default(0);
            $table->decimal('return_amount', 12, 2)->default(0);
            $table->unique(['store_id', 'product_id', 'date']);
        });

        Schema::create('daily_sales_summary', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->date('date');
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_refunds', 12, 2)->default(0);
            $table->decimal('net_revenue', 14, 2)->default(0);
            $table->decimal('cash_revenue', 14, 2)->default(0);
            $table->decimal('card_revenue', 14, 2)->default(0);
            $table->decimal('other_revenue', 14, 2)->default(0);
            $table->decimal('avg_basket_size', 12, 2)->default(0);
            $table->integer('unique_customers')->default(0);
            $table->unique(['store_id', 'date']);
        });

        // ─── Notifications ──────────────────────────────────
        Schema::create('notifications_custom', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('store_id')->nullable();
            $table->string('category', 30);
            $table->string('title');
            $table->text('message');
            $table->string('action_url', 500)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->json('preferences_json')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('token');
            $table->string('device_type', 20);
            $table->timestamps();
        });

        Schema::create('notification_events_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
        });

        // ─── Accounting Integration ─────────────────────────
        Schema::create('store_accounting_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->unique();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->text('access_token_encrypted');
            $table->text('refresh_token_encrypted');
            $table->timestamp('token_expires_at');
            $table->string('realm_id', 50)->nullable();
            $table->string('tenant_id', 50)->nullable();
            $table->string('company_name')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('pos_account_key', 50);
            $table->string('provider_account_id', 100);
            $table->string('provider_account_name');
            $table->timestamps();
            $table->unique(['store_id', 'pos_account_key']);
        });

        Schema::create('accounting_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->json('export_types')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('entries_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('journal_entry_ids')->nullable();
            $table->text('csv_url')->nullable();
            $table->string('triggered_by', 20)->default('manual');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('auto_export_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->unique();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('frequency', 20)->default('daily');
            $table->integer('day_of_week')->nullable();
            $table->integer('day_of_month')->nullable();
            $table->time('time')->default('23:00');
            $table->json('export_types')->nullable();
            $table->string('notify_email')->nullable();
            $table->boolean('retry_on_failure')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        // ─── Delivery Integrations ──────────────────────────
        Schema::create('delivery_platform_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('platform', 50);
            $table->text('api_key');
            $table->string('merchant_id', 100)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('branch_id_on_platform', 100)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('auto_accept')->default(true);
            $table->integer('throttle_limit')->nullable();
            $table->timestamp('last_menu_sync_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'platform']);
        });

        Schema::create('delivery_order_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->string('platform', 50);
            $table->string('external_order_id', 100);
            $table->string('external_status', 50)->nullable();
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_menu_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('platform', 50);
            $table->string('status', 20);
            $table->integer('items_synced')->default(0);
            $table->integer('items_failed')->default(0);
            $table->json('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        // ─── Thawani Integration ────────────────────────────
        Schema::create('thawani_store_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->unique();
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('thawani_store_id', 100);
            $table->boolean('is_connected')->default(false);
            $table->boolean('auto_sync_products')->default(true);
            $table->boolean('auto_sync_inventory')->default(true);
            $table->boolean('auto_accept_orders')->default(false);
            $table->json('operating_hours_json')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('thawani_product_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('thawani_product_id', 100);
            $table->boolean('is_published')->default(true);
            $table->decimal('online_price', 12, 3)->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'product_id']);
        });

        Schema::create('thawani_order_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('order_id')->nullable();
            $table->string('thawani_order_id', 100);
            $table->string('thawani_order_number', 50);
            $table->string('status', 30)->default('new');
            $table->string('delivery_type', 20)->default('delivery');
            $table->string('customer_name', 200)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('order_total', 12, 3);
            $table->decimal('commission_amount', 12, 3)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('thawani_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->date('settlement_date');
            $table->decimal('gross_amount', 12, 3);
            $table->decimal('commission_amount', 12, 3);
            $table->decimal('net_amount', 12, 3);
            $table->integer('order_count');
            $table->string('thawani_reference', 100)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Industry: Pharmacy ─────────────────────────────
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('order_id')->nullable();
            $table->string('prescription_number', 50);
            $table->string('patient_name', 200);
            $table->string('patient_id', 50)->nullable();
            $table->string('doctor_name', 200)->nullable();
            $table->string('doctor_license', 50)->nullable();
            $table->string('insurance_provider', 100)->nullable();
            $table->decimal('insurance_claim_amount', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('drug_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->unique();
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('schedule_type', 20)->default('otc');
            $table->string('active_ingredient', 200)->nullable();
            $table->string('dosage_form', 50)->nullable();
            $table->string('strength', 50)->nullable();
            $table->string('manufacturer', 200)->nullable();
            $table->boolean('requires_prescription')->default(false);
        });

        // ─── Industry: Jewelry ──────────────────────────────
        Schema::create('daily_metal_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('metal_type', 20);
            $table->string('karat', 10)->nullable();
            $table->decimal('rate_per_gram', 12, 3);
            $table->decimal('buyback_rate_per_gram', 12, 3)->nullable();
            $table->date('effective_date');
            $table->timestamp('created_at')->nullable();
            $table->unique(['store_id', 'metal_type', 'karat', 'effective_date']);
        });

        Schema::create('jewelry_product_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->unique();
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('metal_type', 20);
            $table->string('karat', 10)->nullable();
            $table->decimal('gross_weight_g', 10, 3);
            $table->decimal('net_weight_g', 10, 3);
            $table->string('making_charges_type', 20)->default('percentage');
            $table->decimal('making_charges_value', 10, 2)->default(0);
            $table->string('stone_type', 50)->nullable();
            $table->decimal('stone_weight_carat', 10, 3)->nullable();
            $table->integer('stone_count')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->string('certificate_url', 500)->nullable();
        });

        Schema::create('buyback_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('customer_id')->nullable();
            $table->string('metal_type', 20);
            $table->string('karat', 10);
            $table->decimal('weight_g', 10, 3);
            $table->decimal('rate_per_gram', 12, 3);
            $table->decimal('total_amount', 12, 3);
            $table->string('payment_method', 20);
            $table->uuid('staff_user_id');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Industry: Electronics (Mobile) ─────────────────
        Schema::create('device_imei_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('imei', 15);
            $table->string('imei2', 15)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->string('condition_grade', 5)->nullable();
            $table->decimal('purchase_price', 12, 3)->nullable();
            $table->string('status', 20)->default('in_stock');
            $table->date('warranty_end_date')->nullable();
            $table->date('store_warranty_end_date')->nullable();
            $table->uuid('sold_order_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('customer_id')->nullable();
            $table->string('device_description', 200);
            $table->string('imei', 15)->nullable();
            $table->text('issue_description');
            $table->string('status', 20)->default('received');
            $table->text('diagnosis_notes')->nullable();
            $table->text('repair_notes')->nullable();
            $table->decimal('estimated_cost', 12, 3)->nullable();
            $table->decimal('final_cost', 12, 3)->nullable();
            $table->json('parts_used')->nullable();
            $table->uuid('staff_user_id');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('estimated_ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('collected_at')->nullable();
        });

        Schema::create('trade_in_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('customer_id')->nullable();
            $table->string('device_description', 200);
            $table->string('imei', 15)->nullable();
            $table->string('condition_grade', 5);
            $table->decimal('assessed_value', 12, 3);
            $table->uuid('applied_to_order_id')->nullable();
            $table->uuid('staff_user_id');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Industry: Florist ──────────────────────────────
        Schema::create('flower_arrangements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('name', 200);
            $table->string('occasion', 50)->nullable();
            $table->json('items_json');
            $table->decimal('total_price', 12, 3);
            $table->boolean('is_template')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('flower_freshness_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->date('received_date');
            $table->integer('expected_vase_life_days');
            $table->date('markdown_date')->nullable();
            $table->date('dispose_date')->nullable();
            $table->integer('quantity');
            $table->string('status', 20)->default('fresh');
        });

        Schema::create('flower_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('customer_id');
            $table->uuid('arrangement_template_id')->nullable();
            $table->string('frequency', 20);
            $table->string('delivery_day', 10)->nullable();
            $table->text('delivery_address');
            $table->decimal('price_per_delivery', 12, 3);
            $table->boolean('is_active')->default(true);
            $table->date('next_delivery_date');
            $table->timestamp('created_at')->nullable();
        });

        // ─── Industry: Bakery ───────────────────────────────
        Schema::create('bakery_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('name', 200);
            $table->integer('expected_yield')->default(1);
            $table->integer('prep_time_minutes')->nullable();
            $table->integer('bake_time_minutes')->nullable();
            $table->integer('bake_temperature_c')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('production_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('recipe_id');
            $table->foreign('recipe_id')->references('id')->on('bakery_recipes');
            $table->date('schedule_date');
            $table->integer('planned_batches')->default(1);
            $table->integer('actual_batches')->nullable();
            $table->integer('planned_yield');
            $table->integer('actual_yield')->nullable();
            $table->string('status', 20)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('custom_cake_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('customer_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->text('description');
            $table->string('size', 50)->nullable();
            $table->string('flavor', 100)->nullable();
            $table->text('decoration_notes')->nullable();
            $table->date('delivery_date');
            $table->time('delivery_time')->nullable();
            $table->decimal('price', 12, 3);
            $table->decimal('deposit_paid', 12, 3)->default(0);
            $table->string('status', 20)->default('ordered');
            $table->string('reference_image_url', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Industry: Restaurant ───────────────────────────
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->string('table_number', 20);
            $table->string('display_name', 50)->nullable();
            $table->integer('seats')->default(4);
            $table->string('zone', 50)->nullable();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->string('status', 20)->default('available');
            $table->uuid('current_order_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->unique(['store_id', 'table_number']);
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->uuid('table_id')->nullable();
            $table->integer('ticket_number');
            $table->json('items_json');
            $table->string('station', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('course_number')->default(1);
            $table->timestamp('fire_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('table_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('table_id')->nullable();
            $table->string('customer_name', 200);
            $table->string('customer_phone', 20)->nullable();
            $table->integer('party_size');
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->integer('duration_minutes')->default(90);
            $table->string('status', 20)->default('confirmed');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('open_tabs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->string('customer_name', 200);
            $table->uuid('table_id')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 20)->default('open');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('return_id');
            $table->uuid('payment_id')->nullable();
            $table->string('method', 30);
            $table->decimal('amount', 12, 2);
            $table->string('reference_number', 100)->nullable();
            $table->string('status', 20)->default('completed');
            $table->uuid('processed_by');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $tables = [
            // Industry: Restaurant
            'open_tabs', 'table_reservations', 'kitchen_tickets', 'restaurant_tables',
            // Industry: Bakery
            'custom_cake_orders', 'production_schedules', 'bakery_recipes',
            // Industry: Florist
            'flower_subscriptions', 'flower_freshness_log', 'flower_arrangements',
            // Industry: Electronics
            'trade_in_records', 'repair_jobs', 'device_imei_records',
            // Industry: Jewelry
            'buyback_transactions', 'jewelry_product_details', 'daily_metal_rates',
            // Industry: Pharmacy
            'drug_schedules', 'prescriptions',
            // Thawani integration
            'thawani_settlements', 'thawani_order_mappings', 'thawani_product_mappings', 'thawani_store_config',
            // Delivery integrations
            'delivery_menu_sync_logs', 'delivery_order_mappings', 'delivery_platform_configs',
            // Accounting integration
            'auto_export_configs', 'accounting_exports', 'account_mappings', 'store_accounting_configs',
            // Notifications
            'notification_events_log', 'fcm_tokens', 'notification_preferences', 'notifications_custom',
            // Reports & Analytics
            'daily_sales_summary', 'product_sales_summary',
            // Staff Management
            'staff_documents', 'training_sessions', 'staff_activity_log',
            'commission_earnings', 'commission_rules',
            'break_records', 'attendance_records',
            'shift_schedules', 'shift_templates',
            'staff_branch_assignments', 'staff_users',
            // Promotions
            'bundle_products', 'promotion_usage_log', 'coupon_codes',
            'promotion_customer_groups', 'promotion_categories', 'promotion_products', 'promotions',
            // Payment domain
            'refunds', 'gift_cards', 'expenses', 'cash_events', 'cash_sessions', 'payments',
            // Order domain
            'return_items', 'returns', 'order_status_history', 'order_items', 'orders',
            // POS domain
            'held_carts', 'transaction_items', 'transactions', 'pos_sessions',
            // Label domain
            'label_print_history', 'label_templates',
            // Customer domain
            'digital_receipt_log', 'store_credit_transactions', 'loyalty_transactions',
            'loyalty_config', 'customers', 'customer_groups',
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
