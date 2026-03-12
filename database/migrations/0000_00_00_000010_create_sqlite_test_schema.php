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
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $tables = [
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
