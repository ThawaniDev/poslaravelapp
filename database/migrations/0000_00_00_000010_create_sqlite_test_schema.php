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
            $table->string('device_id', 100)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('platform', 20)->default('android');
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_active')->default(true);
            // SoftPOS
            $table->boolean('softpos_enabled')->default(false);
            $table->string('nearpay_tid', 50)->nullable();
            $table->string('nearpay_mid', 50)->nullable();
            $table->string('nearpay_auth_key', 255)->nullable();
            // Acquirer
            $table->string('acquirer_source', 30)->nullable();
            $table->string('acquirer_name', 100)->nullable();
            $table->string('acquirer_reference', 100)->nullable();
            // Device hardware
            $table->string('device_model', 100)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->boolean('nfc_capable')->default(false);
            $table->string('serial_number', 100)->nullable();
            // Fee config
            $table->string('fee_profile', 30)->default('standard');
            $table->decimal('fee_mada_percentage', 5, 4)->default(0.0150);
            $table->decimal('fee_visa_mc_percentage', 5, 4)->default(0.0200);
            $table->decimal('fee_flat_per_txn', 8, 2)->default(0.00);
            $table->decimal('wameed_margin_percentage', 5, 4)->default(0.0040);
            // Settlement
            $table->string('settlement_cycle', 10)->default('T+1');
            $table->string('settlement_bank_name', 100)->nullable();
            $table->string('settlement_iban', 34)->nullable();
            // Status
            $table->string('softpos_status', 20)->default('pending');
            $table->timestamp('softpos_activated_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->text('admin_notes')->nullable();
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
                $table->string('password_hash');
                $table->string('phone', 50)->nullable();
                $table->text('avatar_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('two_factor_secret')->nullable();
                $table->boolean('two_factor_enabled')->default(false);
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // ─── Admin: admin_roles ──────────────────────────────
        if (!Schema::hasTable('admin_roles')) {
            Schema::create('admin_roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 100);
                $table->string('slug', 50)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        // ─── Admin: admin_permissions ────────────────────────
        if (!Schema::hasTable('admin_permissions')) {
            Schema::create('admin_permissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 100)->unique();
                $table->string('group', 50);
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Admin: admin_role_permissions (pivot) ───────────
        if (!Schema::hasTable('admin_role_permissions')) {
            Schema::create('admin_role_permissions', function (Blueprint $table) {
                $table->uuid('admin_role_id');
                $table->uuid('admin_permission_id');
                $table->foreign('admin_role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
                $table->foreign('admin_permission_id')->references('id')->on('admin_permissions')->cascadeOnDelete();
                $table->primary(['admin_role_id', 'admin_permission_id']);
            });
        }

        // ─── Admin: admin_user_roles (pivot) ─────────────────
        if (!Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table) {
                $table->uuid('admin_user_id');
                $table->uuid('admin_role_id');
                $table->timestamp('assigned_at')->nullable();
                $table->uuid('assigned_by')->nullable();
                $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
                $table->foreign('admin_role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
                $table->primary(['admin_user_id', 'admin_role_id']);
            });
        }

        // ─── Admin: admin_activity_logs ──────────────────────
        if (!Schema::hasTable('admin_activity_logs')) {
            Schema::create('admin_activity_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('admin_user_id')->nullable();
                $table->string('action', 100);
                $table->string('entity_type', 50)->nullable();
                $table->uuid('entity_id')->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->default('127.0.0.1');
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Provider: provider_registrations ────────────────
        if (!Schema::hasTable('provider_registrations')) {
            Schema::create('provider_registrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('organization_name');
                $table->string('organization_name_ar')->nullable();
                $table->string('owner_name');
                $table->string('owner_email');
                $table->string('owner_phone', 50);
                $table->string('cr_number', 50)->nullable();
                $table->string('vat_number', 50)->nullable();
                $table->uuid('business_type_id')->nullable();
                $table->string('status', 20)->default('pending');
                $table->uuid('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }

        // ─── Provider: provider_notes ────────────────────────
        if (!Schema::hasTable('provider_notes')) {
            Schema::create('provider_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('admin_user_id');
                $table->text('note_text');
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Provider: cancellation_reasons ──────────────────
        if (!Schema::hasTable('cancellation_reasons')) {
            Schema::create('cancellation_reasons', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_subscription_id');
                $table->string('reason_category', 30);
                $table->text('reason_text')->nullable();
                $table->timestamp('cancelled_at')->nullable();
            });
        }

        // ─── Support: support_tickets ────────────────────────
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ticket_number', 20)->unique();
                $table->uuid('organization_id');
                $table->uuid('store_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->uuid('assigned_to')->nullable();
                $table->string('category', 50);
                $table->string('priority', 10)->default('medium');
                $table->string('status', 20)->default('open');
                $table->string('subject');
                $table->text('description');
                $table->timestamp('sla_deadline_at')->nullable();
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Support: support_ticket_messages ────────────────
        if (!Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('support_ticket_id');
                $table->string('sender_type', 10);
                $table->uuid('sender_id');
                $table->text('message_text');
                $table->json('attachments')->nullable();
                $table->boolean('is_internal_note')->default(false);
                $table->timestamp('sent_at')->nullable();
            });
        }

        // ─── Support: canned_responses ───────────────────────
        if (!Schema::hasTable('canned_responses')) {
            Schema::create('canned_responses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('shortcut', 50)->unique()->nullable();
                $table->text('body');
                $table->text('body_ar');
                $table->string('category', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Support: knowledge_base_articles ────────────────
        if (!Schema::hasTable('knowledge_base_articles')) {
            Schema::create('knowledge_base_articles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('title_ar');
                $table->string('slug', 100)->unique();
                $table->text('body');
                $table->text('body_ar');
                $table->string('category', 50)->nullable();
                $table->uuid('delivery_platform_id')->nullable();
                $table->boolean('is_published')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // ─── Platform: platform_announcements ────────────────
        if (!Schema::hasTable('platform_announcements')) {
            Schema::create('platform_announcements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type', 20)->default('info');
                $table->string('title');
                $table->string('title_ar')->nullable();
                $table->text('body');
                $table->text('body_ar')->nullable();
                $table->json('target_filter')->nullable();
                $table->timestamp('display_start_at')->nullable();
                $table->timestamp('display_end_at')->nullable();
                $table->boolean('is_banner')->default(false);
                $table->boolean('send_push')->default(false);
                $table->boolean('send_email')->default(false);
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        // ─── Platform: platform_announcement_dismissals ──────
        if (!Schema::hasTable('platform_announcement_dismissals')) {
            Schema::create('platform_announcement_dismissals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('announcement_id');
                $table->uuid('store_id');
                $table->uuid('user_id')->nullable();
                $table->timestamp('dismissed_at')->nullable();
            });
        }

        // ─── Platform: payment_reminders ─────────────────────
        if (!Schema::hasTable('payment_reminders')) {
            Schema::create('payment_reminders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_subscription_id');
                $table->string('reminder_type', 20);
                $table->string('channel', 20);
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('next_send_at')->nullable();
                $table->boolean('is_sent')->default(false);
            });
        }

        // ─── Billing: subscription_discounts ─────────────────
        if (!Schema::hasTable('subscription_discounts')) {
            Schema::create('subscription_discounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 50)->unique();
                $table->string('type', 20);
                $table->decimal('value', 10, 2);
                $table->integer('max_uses')->nullable();
                $table->integer('times_used')->default(0);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_to')->nullable();
                $table->json('applicable_plan_ids')->nullable();
                $table->timestamps();
            });
        }

        // ─── Billing: subscription_credits ───────────────────
        if (!Schema::hasTable('subscription_credits')) {
            Schema::create('subscription_credits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_subscription_id');
                $table->uuid('applied_by');
                $table->decimal('amount', 10, 2);
                $table->text('reason');
                $table->timestamp('applied_at')->nullable();
            });
        }

        // ─── Billing: payment_gateway_configs ────────────────
        if (!Schema::hasTable('payment_gateway_configs')) {
            Schema::create('payment_gateway_configs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('gateway_name', 50);
                $table->json('credentials_encrypted');
                $table->text('webhook_url')->nullable();
                $table->string('environment', 20)->default('sandbox');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ─── Billing: hardware_sales ─────────────────────────
        if (!Schema::hasTable('hardware_sales')) {
            Schema::create('hardware_sales', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('sold_by');
                $table->string('item_type', 50);
                $table->string('item_description')->nullable();
                $table->string('serial_number', 100)->nullable();
                $table->decimal('amount', 10, 2);
                $table->text('notes')->nullable();
                $table->timestamp('sold_at')->nullable();
            });
        }

        // ─── Billing: implementation_fees ────────────────────
        if (!Schema::hasTable('implementation_fees')) {
            Schema::create('implementation_fees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('fee_type', 20);
                $table->decimal('amount', 10, 2);
                $table->string('status', 20)->default('invoiced');
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Billing: payment_retry_rules ────────────────────
        if (!Schema::hasTable('payment_retry_rules')) {
            Schema::create('payment_retry_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->integer('max_retries')->default(3);
                $table->integer('retry_interval_hours')->default(24);
                $table->integer('grace_period_after_failure_days')->default(7);
                $table->timestamp('updated_at')->nullable();
            });
        }

        // ─── Platform: system_settings ───────────────────────
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key', 100)->unique();
                $table->json('value')->nullable();
                $table->string('group', 50);
                $table->text('description')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // ─── Platform: translation_overrides ─────────────────
        if (!Schema::hasTable('translation_overrides')) {
            Schema::create('translation_overrides', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('string_key');
                $table->string('locale', 10);
                $table->text('custom_value');
                $table->timestamp('updated_at')->nullable();
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            });
        }

        // ─── Platform: supported_locales ─────────────────────
        if (!Schema::hasTable('supported_locales')) {
            Schema::create('supported_locales', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('locale_code', 10)->unique();
                $table->string('language_name');
                $table->string('language_name_native');
                $table->string('direction', 3)->default('rtl');
                $table->string('date_format', 20)->nullable();
                $table->string('number_format', 20)->nullable();
                $table->string('calendar_system', 20)->default('gregorian');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
            });
        }

        // ─── Platform: master_translation_strings ────────────
        if (!Schema::hasTable('master_translation_strings')) {
            Schema::create('master_translation_strings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('string_key')->unique();
                $table->string('category', 50);
                $table->text('value_en');
                $table->text('value_ar');
                $table->text('description')->nullable();
                $table->boolean('is_overridable')->default(true);
            });
        }

        // ─── Platform: translation_versions ──────────────────
        if (!Schema::hasTable('translation_versions')) {
            Schema::create('translation_versions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('version_hash');
                $table->timestamp('published_at')->nullable();
                $table->uuid('published_by')->nullable();
                $table->text('notes')->nullable();
            });
        }

        // ─── Platform: tax_exemption_types ───────────────────
        if (!Schema::hasTable('tax_exemption_types')) {
            Schema::create('tax_exemption_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->string('name_ar');
                $table->text('required_documents')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }

        // ─── Platform: age_restricted_categories ─────────────
        if (!Schema::hasTable('age_restricted_categories')) {
            Schema::create('age_restricted_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('category_slug', 50)->unique();
                $table->integer('min_age');
                $table->boolean('is_active')->default(true);
            });
        }

        // ─── Platform: payment_methods ───────────────────────
        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('method_key', 50)->unique();
                $table->string('name');
                $table->string('name_ar');
                $table->string('icon')->nullable();
                $table->string('category', 50);
                $table->boolean('requires_terminal')->default(false);
                $table->boolean('requires_customer_profile')->default(false);
                $table->json('provider_config_schema')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // ─── Platform: certified_hardware ────────────────────
        if (!Schema::hasTable('certified_hardware')) {
            Schema::create('certified_hardware', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('device_type', 50);
                $table->string('brand');
                $table->string('model');
                $table->string('driver_protocol', 50);
                $table->json('connection_types')->nullable();
                $table->string('firmware_version_min')->nullable();
                $table->json('paper_widths')->nullable();
                $table->text('setup_instructions')->nullable();
                $table->text('setup_instructions_ar')->nullable();
                $table->boolean('is_certified')->default(true);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['brand', 'model']);
            });
        }

        // ─── Platform: security_policy_defaults ──────────────
        if (!Schema::hasTable('security_policy_defaults')) {
            Schema::create('security_policy_defaults', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->integer('session_timeout_minutes')->default(30);
                $table->boolean('require_reauth_on_wake')->default(true);
                $table->integer('pin_min_length')->default(4);
                $table->string('pin_complexity', 20)->default('numeric');
                $table->boolean('require_unique_pins')->default(true);
                $table->integer('pin_expiry_days')->default(0);
                $table->boolean('biometric_enabled_default')->default(false);
                $table->boolean('biometric_can_replace_pin')->default(false);
                $table->integer('max_failed_login_attempts')->default(5);
                $table->integer('lockout_duration_minutes')->default(15);
                $table->boolean('failed_attempt_alert_to_owner')->default(true);
                $table->string('device_registration_policy', 30)->default('open');
                $table->integer('max_devices_per_store')->default(10);
                $table->uuid('updated_by')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        // ─── Platform: feature_flags ─────────────────────────
        if (!Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('flag_key', 50)->unique();
                $table->boolean('is_enabled')->default(false);
                $table->integer('rollout_percentage')->default(100);
                $table->json('target_plan_ids')->nullable();
                $table->json('target_store_ids')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // ─── Platform: ab_tests ─────────────────────────────
        if (!Schema::hasTable('ab_tests')) {
            Schema::create('ab_tests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->uuid('feature_flag_id')->nullable();
                $table->string('status', 30)->default('draft'); // draft, running, completed, cancelled
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('metric_key', 100)->nullable();
                $table->integer('traffic_percentage')->default(100);
                $table->timestamps();

                $table->foreign('feature_flag_id')->references('id')->on('feature_flags')->nullOnDelete();
            });
        }

        // ─── Platform: ab_test_variants ──────────────────────
        if (!Schema::hasTable('ab_test_variants')) {
            Schema::create('ab_test_variants', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ab_test_id');
                $table->string('variant_key', 50);
                $table->string('variant_label', 150)->nullable();
                $table->integer('weight')->default(50);
                $table->boolean('is_control')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('ab_test_id')->references('id')->on('ab_tests')->cascadeOnDelete();
            });
        }

        // ─── Platform: ab_test_events ────────────────────────
        if (!Schema::hasTable('ab_test_events')) {
            Schema::create('ab_test_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ab_test_id');
                $table->uuid('variant_id');
                $table->string('event_type', 20); // impression, conversion
                $table->uuid('store_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('ab_test_id')->references('id')->on('ab_tests')->cascadeOnDelete();
                $table->foreign('variant_id')->references('id')->on('ab_test_variants')->cascadeOnDelete();
                $table->index(['ab_test_id', 'variant_id']);
                $table->index(['ab_test_id', 'event_type']);
            });
        }

        // ─── Platform: notification_templates ────────────────
        if (!Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('event_key', 100);
                $table->string('channel', 20);
                $table->string('title')->nullable();
                $table->string('title_ar')->nullable();
                $table->text('body')->nullable();
                $table->text('body_ar')->nullable();
                $table->json('available_variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ─── Content: cms_pages ──────────────────────────────
        if (!Schema::hasTable('cms_pages')) {
            Schema::create('cms_pages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug', 150)->unique();
                $table->string('title');
                $table->string('title_ar')->nullable();
                $table->text('body')->nullable();
                $table->text('body_ar')->nullable();
                $table->string('page_type', 50)->default('general');
                $table->boolean('is_published')->default(false);
                $table->string('meta_title')->nullable();
                $table->string('meta_title_ar')->nullable();
                $table->text('meta_description')->nullable();
                $table->text('meta_description_ar')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // ─── Logs: platform_event_logs ────────────────────────
        if (!Schema::hasTable('platform_event_logs')) {
            Schema::create('platform_event_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('event_type', 50);
                $table->string('level', 20)->default('info');
                $table->string('source', 100)->nullable();
                $table->text('message');
                $table->json('details')->nullable();
                $table->uuid('admin_user_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Logs: system_health_checks ──────────────────────
        if (!Schema::hasTable('system_health_checks')) {
            Schema::create('system_health_checks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('service', 50);
                $table->string('status', 20)->default('healthy');
                $table->integer('response_time_ms')->nullable();
                $table->json('details')->nullable();
                $table->timestamp('checked_at')->nullable();
            });
        }

        // ─── Delivery: delivery_platforms (registry) ─────────
        if (!Schema::hasTable('delivery_platforms')) {
            Schema::create('delivery_platforms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug', 50)->unique();
                $table->string('logo_url')->nullable();
                $table->text('description')->nullable();
                $table->string('auth_method', 20)->default('api_key');
                $table->string('base_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // ─── Delivery: delivery_platform_fields ──────────────
        if (!Schema::hasTable('delivery_platform_fields')) {
            Schema::create('delivery_platform_fields', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('delivery_platform_id');
                $table->string('field_key', 50);
                $table->string('field_label');
                $table->string('field_type', 20)->default('text');
                $table->boolean('is_required')->default(false);
                $table->boolean('is_encrypted')->default(false);
                $table->text('placeholder')->nullable();
                $table->integer('sort_order')->default(0);
            });
        }

        // ─── Delivery: delivery_platform_endpoints ───────────
        if (!Schema::hasTable('delivery_platform_endpoints')) {
            Schema::create('delivery_platform_endpoints', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('delivery_platform_id');
                $table->string('operation', 50);
                $table->string('http_method', 10);
                $table->string('path');
                $table->json('headers')->nullable();
                $table->json('request_body_template')->nullable();
                $table->json('response_mapping')->nullable();
            });
        }

        // ─── App: app_releases ───────────────────────────────
        if (!Schema::hasTable('app_releases')) {
            Schema::create('app_releases', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('version_number', 20);
                $table->string('platform', 10);
                $table->string('channel', 10)->default('stable');
                $table->text('download_url');
                $table->text('store_url')->nullable();
                $table->string('build_number', 20)->nullable();
                $table->string('submission_status', 20)->default('not_applicable');
                $table->text('release_notes')->nullable();
                $table->text('release_notes_ar')->nullable();
                $table->boolean('is_force_update')->default(false);
                $table->string('min_supported_version', 20)->nullable();
                $table->integer('rollout_percentage')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->unique(['platform', 'channel', 'version_number']);
            });
        }

        // ─── App: app_update_stats ───────────────────────────
        if (!Schema::hasTable('app_update_stats')) {
            Schema::create('app_update_stats', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('app_release_id');
                $table->string('status', 20)->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        // ─── Security: admin_ip_allowlist ────────────────────
        if (!Schema::hasTable('admin_ip_allowlist')) {

        // ─── BackupSync: database_backups ────────────────────
        if (!Schema::hasTable('database_backups')) {
            Schema::create('database_backups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('backup_type', 20);
                $table->text('file_path');
                $table->bigInteger('file_size_bytes')->nullable();
                $table->string('status', 20)->default('in_progress');
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
            });
        }

        // ─── BackupSync: backup_history ──────────────────────
        if (!Schema::hasTable('backup_history')) {
            Schema::create('backup_history', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('terminal_id');
                $table->string('backup_type', 20);
                $table->string('storage_location', 20)->default('local');
                $table->text('local_path')->nullable();
                $table->string('cloud_key', 500)->nullable();
                $table->bigInteger('file_size_bytes')->default(0);
                $table->string('checksum', 64)->default('');
                $table->integer('db_version')->default(1);
                $table->integer('records_count')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_encrypted')->default(true);
                $table->string('status', 20)->default('completed');
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── BackupSync: sync_log ────────────────────────────
        if (!Schema::hasTable('sync_log')) {
            Schema::create('sync_log', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('terminal_id');
                $table->string('direction', 10);
                $table->integer('records_count')->default(0);
                $table->integer('duration_ms')->default(0);
                $table->string('status', 20);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
            });
        }

        // ─── BackupSync: sync_conflicts ──────────────────────
        if (!Schema::hasTable('sync_conflicts')) {
            Schema::create('sync_conflicts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('table_name', 100);
                $table->uuid('record_id');
                $table->text('local_data');
                $table->text('cloud_data');
                $table->string('resolution', 20)->nullable();
                $table->uuid('resolved_by')->nullable();
                $table->timestamp('detected_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
            });
        }

        // ─── BackupSync: provider_backup_status ──────────────
        if (!Schema::hasTable('provider_backup_status')) {
            Schema::create('provider_backup_status', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('terminal_id');
                $table->timestamp('last_successful_sync')->nullable();
                $table->timestamp('last_cloud_backup')->nullable();
                $table->bigInteger('storage_used_bytes')->default(0);
                $table->string('status', 20)->default('unknown');
                $table->timestamp('updated_at')->nullable();
            });
        }

            Schema::create('admin_ip_allowlist', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ip_address', 45);
                $table->string('label')->nullable();
                $table->uuid('added_by')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Security: admin_ip_blocklist ────────────────────
        if (!Schema::hasTable('admin_ip_blocklist')) {
            Schema::create('admin_ip_blocklist', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ip_address', 45);
                $table->string('reason')->nullable();
                $table->uuid('blocked_by')->nullable();
                $table->timestamp('blocked_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Security: admin_trusted_devices ─────────────────
        if (!Schema::hasTable('admin_trusted_devices')) {
            Schema::create('admin_trusted_devices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('admin_user_id');
                $table->string('device_fingerprint');
                $table->string('device_name')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('trusted_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
            });
        }

        // ─── Security: admin_sessions ────────────────────────
        if (!Schema::hasTable('admin_sessions')) {
            Schema::create('admin_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('admin_user_id');
                $table->string('session_token_hash', 64)->nullable();
                $table->string('ip_address', 45);
                $table->text('user_agent')->nullable();
                $table->string('status', 20)->default('active');
                $table->boolean('two_fa_verified')->default(false);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
            });
        }

        // ─── Security: security_alerts ───────────────────────
        if (!Schema::hasTable('security_alerts')) {
            Schema::create('security_alerts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('alert_type', 50);
                $table->string('severity', 20);
                $table->text('description');
                $table->uuid('admin_user_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->json('details')->nullable();
                $table->string('status', 20)->default('new');
                $table->uuid('resolved_by')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Security: device_registrations ──────────────────
        if (!Schema::hasTable('device_registrations')) {
            Schema::create('device_registrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('device_name');
                $table->string('hardware_id');
                $table->text('os_info')->nullable();
                $table->string('app_version')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('remote_wipe_requested')->default(false);
                $table->string('ip_address', 45)->nullable();
                $table->string('screen_resolution', 20)->nullable();
                $table->string('last_known_location', 100)->nullable();
                $table->string('device_type', 30)->default('desktop');
                $table->timestamp('registered_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Security: security_audit_log ────────────────────
        if (!Schema::hasTable('security_audit_log')) {
            Schema::create('security_audit_log', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->string('user_type', 20)->nullable();
                $table->string('action', 50);
                $table->string('resource_type', 50)->nullable();
                $table->uuid('resource_id')->nullable();
                $table->json('details')->nullable();
                $table->string('severity', 20)->default('info');
                $table->string('ip_address', 45)->nullable();
                $table->uuid('device_id')->nullable();
                $table->string('request_method', 10)->nullable();
                $table->text('request_url')->nullable();
                $table->integer('response_code')->nullable();
                $table->integer('duration_ms')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        // ─── Security: login_attempts ────────────────────────
        if (!Schema::hasTable('login_attempts')) {
            Schema::create('login_attempts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->nullable();
                $table->string('user_identifier');
                $table->string('attempt_type', 30);
                $table->boolean('is_successful')->default(false);
                $table->string('ip_address', 45)->nullable();
                $table->uuid('device_id')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('failure_reason', 100)->nullable();
                $table->string('geo_location', 100)->nullable();
                $table->string('device_name', 100)->nullable();
                $table->timestamp('attempted_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Security: security_policies ─────────────────────
        if (!Schema::hasTable('security_policies')) {
            Schema::create('security_policies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->integer('pin_min_length')->default(4);
                $table->integer('pin_max_length')->default(8);
                $table->integer('auto_lock_seconds')->default(300);
                $table->integer('max_failed_attempts')->default(5);
                $table->integer('lockout_duration_minutes')->default(15);
                $table->boolean('require_2fa_owner')->default(false);
                $table->integer('session_max_hours')->default(24);
                $table->boolean('require_pin_override_void')->default(true);
                $table->boolean('require_pin_override_return')->default(true);
                $table->boolean('require_pin_override_discount')->default(false);
                $table->decimal('discount_override_threshold', 5, 2)->default(0);
                $table->boolean('biometric_enabled')->default(true);
                $table->integer('pin_expiry_days')->default(0);
                $table->boolean('require_unique_pins')->default(true);
                $table->integer('max_devices')->default(10);
                $table->integer('audit_retention_days')->default(90);
                $table->boolean('force_logout_on_role_change')->default(true);
                $table->integer('password_expiry_days')->default(0);
                $table->boolean('require_strong_password')->default(false);
                $table->boolean('ip_restriction_enabled')->default(false);
                $table->json('allowed_ip_ranges')->nullable();
                $table->timestamps();
            });
        }

        // ─── Analytics: platform_daily_stats ─────────────────
        if (!Schema::hasTable('platform_daily_stats')) {
            Schema::create('platform_daily_stats', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->date('date')->unique();
                $table->integer('total_active_stores')->default(0);
                $table->integer('new_registrations')->default(0);
                $table->integer('total_orders')->default(0);
                $table->decimal('total_gmv', 14, 2)->default(0);
                $table->decimal('total_mrr', 12, 2)->default(0);
                $table->integer('churn_count')->default(0);
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Analytics: platform_plan_stats ──────────────────
        if (!Schema::hasTable('platform_plan_stats')) {
            Schema::create('platform_plan_stats', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->date('date');
                $table->uuid('subscription_plan_id');
                $table->integer('active_stores')->default(0);
                $table->integer('trial_stores')->default(0);
                $table->integer('churned_stores')->default(0);
                $table->decimal('revenue', 12, 2)->default(0);
            });
        }

        // ─── Analytics: feature_adoption_stats ───────────────
        if (!Schema::hasTable('feature_adoption_stats')) {
            Schema::create('feature_adoption_stats', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->date('date');
                $table->string('feature_key', 50);
                $table->integer('stores_using')->default(0);
                $table->integer('total_eligible')->default(0);
            });
        }

        // ─── Analytics: store_health_snapshots ───────────────
        if (!Schema::hasTable('store_health_snapshots')) {
            Schema::create('store_health_snapshots', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->date('date');
                $table->string('sync_status', 20)->default('ok');
                $table->boolean('zatca_compliance')->default(false);
                $table->integer('error_count')->default(0);
                $table->timestamp('last_activity_at')->nullable();
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
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
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
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('limit_key');
            $table->integer('override_value');
            $table->string('reason')->nullable();
            $table->uuid('set_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Billing: subscription_usage_snapshots ───────────
        // Model: App\Domain\ProviderSubscription\Models\SubscriptionUsageSnapshot
        Schema::create('subscription_usage_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
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

        // ─── Content Onboarding: business_types ──────────────
        Schema::create('business_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->string('slug', 50)->unique();
            $table->string('icon', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Predefined Catalog: predefined_categories ────────
        Schema::create('predefined_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_type_id');
            $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('predefined_categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Predefined Catalog: predefined_products ─────────
        Schema::create('predefined_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_type_id');
            $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
            $table->uuid('predefined_category_id')->nullable();
            $table->foreign('predefined_category_id')->references('id')->on('predefined_categories')->nullOnDelete();
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('sku', 50)->nullable();
            $table->string('barcode', 50)->nullable();
            $table->decimal('sell_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('unit', 20)->default('piece');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_weighable')->default(false);
            $table->decimal('tare_weight', 8, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('age_restricted')->default(false);
            $table->text('image_url')->nullable();
            $table->timestamps();
        });

        // ─── Predefined Catalog: predefined_product_images ───
        Schema::create('predefined_product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('predefined_product_id');
            $table->foreign('predefined_product_id')->references('id')->on('predefined_products')->cascadeOnDelete();
            $table->text('image_url');
            $table->integer('sort_order')->default(0);
        });

        // ─── Content Onboarding: pos_layout_templates ────────
        Schema::create('pos_layout_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_type_id');
            $table->foreign('business_type_id')->references('id')->on('business_types');
            $table->string('layout_key', 50)->unique();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->text('description')->nullable();
            $table->text('preview_image_url')->nullable();
            $table->json('config');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('canvas_columns')->default(24);
            $table->integer('canvas_rows')->default(16);
            $table->integer('canvas_gap_px')->default(4);
            $table->integer('canvas_padding_px')->default(8);
            $table->json('breakpoints')->default('{}');
            $table->string('version', 20)->default('1.0.0');
            $table->boolean('is_locked')->default(false);
            $table->uuid('clone_source_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // ─── Content Onboarding: platform_ui_defaults ────────
        Schema::create('platform_ui_defaults', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->string('value', 100);
        });

        // ─── Content Onboarding: themes ──────────────────────
        Schema::create('themes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->string('primary_color', 7);
            $table->string('secondary_color', 7);
            $table->string('background_color', 7);
            $table->string('text_color', 7);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->json('typography_config')->default('{}');
            $table->json('spacing_config')->default('{}');
            $table->json('border_config')->default('{}');
            $table->json('shadow_config')->default('{}');
            $table->json('animation_config')->default('{}');
            $table->json('css_variables')->default('{}');
            $table->timestamps();
        });

        // ─── Content Onboarding: theme_package_visibility ────
        Schema::create('theme_package_visibility', function (Blueprint $table) {
            $table->uuid('theme_id');
            $table->uuid('subscription_plan_id');
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->primary(['theme_id', 'subscription_plan_id']);
        });

        // ─── Content Onboarding: layout_package_visibility ───
        Schema::create('layout_package_visibility', function (Blueprint $table) {
            $table->uuid('pos_layout_template_id');
            $table->uuid('subscription_plan_id');
            $table->foreign('pos_layout_template_id')->references('id')->on('pos_layout_templates')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->primary(['pos_layout_template_id', 'subscription_plan_id']);
        });

        // ─── Content Onboarding: receipt_layout_templates ────
        Schema::create('receipt_layout_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->string('slug', 50)->unique();
            $table->integer('paper_width')->default(80);
            $table->json('header_config')->default('{}');
            $table->json('body_config')->default('{}');
            $table->json('footer_config')->default('{}');
            $table->string('zatca_qr_position', 10)->default('footer');
            $table->boolean('show_bilingual')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Content Onboarding: receipt_template_package_visibility
        Schema::create('receipt_template_package_visibility', function (Blueprint $table) {
            $table->uuid('receipt_layout_template_id');
            $table->uuid('subscription_plan_id');
            $table->foreign('receipt_layout_template_id')->references('id')->on('receipt_layout_templates')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->primary(['receipt_layout_template_id', 'subscription_plan_id']);
        });

        // ─── Content Onboarding: cfd_themes ─────────────────
        Schema::create('cfd_themes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('text_color', 7)->default('#333333');
            $table->string('accent_color', 7)->default('#1A56A0');
            $table->string('font_family', 50)->default('system');
            $table->string('cart_layout', 10)->default('list');
            $table->string('idle_layout', 20)->default('slideshow');
            $table->string('animation_style', 10)->default('fade');
            $table->integer('transition_seconds')->default(5);
            $table->boolean('show_store_logo')->default(true);
            $table->boolean('show_running_total')->default(true);
            $table->string('thank_you_animation', 15)->default('check');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Content Onboarding: cfd_theme_package_visibility
        Schema::create('cfd_theme_package_visibility', function (Blueprint $table) {
            $table->uuid('cfd_theme_id');
            $table->uuid('subscription_plan_id');
            $table->foreign('cfd_theme_id')->references('id')->on('cfd_themes')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->primary(['cfd_theme_id', 'subscription_plan_id']);
        });

        // ─── Content Onboarding: signage_templates ───────────
        Schema::create('signage_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->string('slug', 50)->unique();
            $table->string('template_type', 20);
            $table->json('layout_config')->default('[]');
            $table->json('placeholder_content')->nullable();
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('text_color', 7)->default('#333333');
            $table->string('font_family', 50)->default('system');
            $table->string('transition_style', 10)->default('fade');
            $table->text('preview_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Content Onboarding: signage_template_business_types
        Schema::create('signage_template_business_types', function (Blueprint $table) {
            $table->uuid('signage_template_id');
            $table->uuid('business_type_id');
            $table->foreign('signage_template_id')->references('id')->on('signage_templates')->cascadeOnDelete();
            $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
            $table->primary(['signage_template_id', 'business_type_id']);
        });

        // ─── Content Onboarding: signage_template_package_visibility
        Schema::create('signage_template_package_visibility', function (Blueprint $table) {
            $table->uuid('signage_template_id');
            $table->uuid('subscription_plan_id');
            $table->foreign('signage_template_id')->references('id')->on('signage_templates')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->primary(['signage_template_id', 'subscription_plan_id']);
        });

        // ─── Content Onboarding: label_layout_templates ──────
        Schema::create('label_layout_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->string('slug', 50)->unique();
            $table->string('label_type', 20);
            $table->integer('label_width_mm');
            $table->integer('label_height_mm');
            $table->string('barcode_type', 15)->default('CODE128');
            $table->json('barcode_position')->nullable();
            $table->boolean('show_barcode_number')->default(true);
            $table->json('field_layout')->default('[]');
            $table->string('font_family', 50)->default('system');
            $table->string('default_font_size', 10)->default('small');
            $table->boolean('show_border')->default(false);
            $table->string('border_style', 10)->default('solid');
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->text('preview_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Content Onboarding: label_template_business_types
        Schema::create('label_template_business_types', function (Blueprint $table) {
            $table->uuid('label_layout_template_id');
            $table->uuid('business_type_id');
            $table->foreign('label_layout_template_id')->references('id')->on('label_layout_templates')->cascadeOnDelete();
            $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
            $table->primary(['label_layout_template_id', 'business_type_id']);
        });

        // ─── Content Onboarding: label_template_package_visibility
        Schema::create('label_template_package_visibility', function (Blueprint $table) {
            $table->uuid('label_layout_template_id');
            $table->uuid('subscription_plan_id');
            $table->foreign('label_layout_template_id')->references('id')->on('label_layout_templates')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->primary(['label_layout_template_id', 'subscription_plan_id']);
        });

        // ─── Content Onboarding: user_preferences ────────────
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('pos_handedness', 10)->nullable();
            $table->string('font_size', 15)->nullable();
            $table->string('theme', 50)->nullable();
            $table->uuid('pos_layout_id')->nullable();
            $table->json('accessibility_json')->nullable();
        });

        // ─── Content Onboarding: pos_customization_settings ──
        if (!Schema::hasTable('pos_customization_settings')) {
            Schema::create('pos_customization_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('theme', 20)->nullable();
                $table->string('primary_color', 7)->nullable();
                $table->string('secondary_color', 7)->nullable();
                $table->string('accent_color', 7)->nullable();
                $table->decimal('font_scale', 3, 1)->nullable();
                $table->string('handedness', 10)->nullable();
                $table->integer('grid_columns')->nullable();
                $table->boolean('show_product_images')->nullable();
                $table->boolean('show_price_on_grid')->nullable();
                $table->string('cart_display_mode', 20)->nullable();
                $table->string('layout_direction', 5)->nullable();
                $table->integer('sync_version')->default(1);
                $table->timestamps();
            });
        }

        // ─── Layout Builder: layout_widgets ──────────────────
        Schema::create('layout_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('category', 30);
            $table->string('icon', 50)->nullable();
            $table->integer('default_width')->default(6);
            $table->integer('default_height')->default(4);
            $table->integer('min_width')->default(2);
            $table->integer('min_height')->default(2);
            $table->integer('max_width')->default(24);
            $table->integer('max_height')->default(16);
            $table->boolean('is_resizable')->default(true);
            $table->boolean('is_required')->default(false);
            $table->json('properties_schema')->default('[]');
            $table->json('default_properties')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Layout Builder: layout_widget_placements ────────
        Schema::create('layout_widget_placements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_layout_template_id');
            $table->foreign('pos_layout_template_id')->references('id')->on('pos_layout_templates')->cascadeOnDelete();
            $table->uuid('layout_widget_id');
            $table->foreign('layout_widget_id')->references('id')->on('layout_widgets');
            $table->string('instance_key', 50);
            $table->integer('grid_x')->default(0);
            $table->integer('grid_y')->default(0);
            $table->integer('grid_w')->default(6);
            $table->integer('grid_h')->default(4);
            $table->integer('z_index')->default(0);
            $table->json('properties')->default('{}');
            $table->boolean('is_visible')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->unique(['pos_layout_template_id', 'instance_key']);
        });

        // ─── Layout Builder: widget_theme_overrides ──────────
        Schema::create('widget_theme_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('layout_widget_placement_id');
            $table->foreign('layout_widget_placement_id')->references('id')->on('layout_widget_placements')->cascadeOnDelete();
            $table->string('variable_key', 100);
            $table->string('value', 255);
            $table->unique(['layout_widget_placement_id', 'variable_key'], 'wto_placement_key_unique');
        });

        // ─── Marketplace: marketplace_categories ─────────────
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->string('slug', 50)->unique();
            $table->string('icon', 50)->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Marketplace: template_marketplace_listings ──────
        Schema::create('template_marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_layout_template_id')->unique();
            $table->foreign('pos_layout_template_id')->references('id')->on('pos_layout_templates')->cascadeOnDelete();
            $table->uuid('theme_id')->nullable();
            $table->foreign('theme_id')->references('id')->on('themes')->nullOnDelete();
            $table->string('publisher_name', 100);
            $table->text('publisher_avatar_url')->nullable();
            $table->string('title', 150);
            $table->string('title_ar', 150);
            $table->text('description');
            $table->text('description_ar');
            $table->string('short_description', 300)->nullable();
            $table->string('short_description_ar', 300)->nullable();
            $table->json('preview_images')->default('[]');
            $table->text('demo_video_url')->nullable();
            $table->string('pricing_type', 20)->default('free');
            $table->decimal('price_amount', 10, 2)->default(0.00);
            $table->string('price_currency', 3)->default('SAR');
            $table->string('subscription_interval', 10)->nullable();
            $table->uuid('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('marketplace_categories')->nullOnDelete();
            $table->json('tags')->default('[]');
            $table->string('version', 20)->default('1.0.0');
            $table->text('changelog')->nullable();
            $table->integer('download_count')->default(0);
            $table->decimal('average_rating', 2, 1)->default(0.0);
            $table->integer('review_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('status', 20)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // ─── Marketplace: template_purchases ─────────────────
        Schema::create('template_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->uuid('marketplace_listing_id');
            $table->foreign('marketplace_listing_id')->references('id')->on('template_marketplace_listings');
            $table->string('purchase_type', 20);
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('SAR');
            $table->string('payment_reference', 100)->nullable();
            $table->string('payment_gateway', 30)->nullable();
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->timestamps();
        });

        // ─── Marketplace: template_reviews ───────────────────
        Schema::create('template_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketplace_listing_id');
            $table->foreign('marketplace_listing_id')->references('id')->on('template_marketplace_listings')->cascadeOnDelete();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->smallInteger('rating');
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_published')->default(true);
            $table->text('admin_response')->nullable();
            $table->timestamp('admin_responded_at')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_listing_id', 'user_id']);
        });

        // ─── Marketplace: marketplace_purchase_invoices ──────
        Schema::create('marketplace_purchase_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_purchase_id');
            $table->foreign('template_purchase_id')->references('id')->on('template_purchases')->cascadeOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->string('status', 20)->default('paid');
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('seller_name', 150);
            $table->string('seller_email', 150)->nullable();
            $table->string('seller_vat_number', 30)->nullable();
            $table->string('buyer_store_name', 200);
            $table->string('buyer_organization_name', 200)->nullable();
            $table->string('buyer_vat_number', 30)->nullable();
            $table->string('buyer_email', 150)->nullable();
            $table->string('item_description', 500);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(15.00);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('billing_period', 50)->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->text('notes')->nullable();
            $table->text('notes_ar')->nullable();
            $table->timestamps();
        });

        // ─── Versioning: template_versions ───────────────────
        Schema::create('template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_layout_template_id');
            $table->foreign('pos_layout_template_id')->references('id')->on('pos_layout_templates')->cascadeOnDelete();
            $table->string('version_number', 20);
            $table->text('changelog')->nullable();
            $table->json('canvas_snapshot');
            $table->json('theme_snapshot')->nullable();
            $table->json('widget_placements_snapshot');
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // ─── Theming: theme_variables ────────────────────────
        Schema::create('theme_variables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('theme_id');
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
            $table->string('variable_key', 100);
            $table->string('variable_value', 255);
            $table->string('variable_type', 20);
            $table->string('category', 30);
            $table->timestamps();
            $table->unique(['theme_id', 'variable_key']);
        });

        // ─── Provider Roles: provider_permissions ────────────
        if (!Schema::hasTable('provider_permissions')) {
            Schema::create('provider_permissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 50)->unique();
                $table->string('group', 30);
                $table->string('description', 255)->nullable();
                $table->string('description_ar', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Provider Roles: default_role_templates ──────────
        if (!Schema::hasTable('default_role_templates')) {
            Schema::create('default_role_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 50);
                $table->string('name_ar', 50)->nullable();
                $table->string('slug', 30)->unique();
                $table->string('description', 255)->nullable();
                $table->string('description_ar', 255)->nullable();
                $table->timestamps();
            });
        }

        // ─── Provider Roles: default_role_template_permissions
        if (!Schema::hasTable('default_role_template_permissions')) {
            Schema::create('default_role_template_permissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('default_role_template_id');
                $table->uuid('provider_permission_id');
                $table->unique(['default_role_template_id', 'provider_permission_id'], 'drtp_template_permission_unique');
            });
        }

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
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
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
            $table->decimal('offer_price', 12, 2)->nullable();
            $table->date('offer_start')->nullable();
            $table->date('offer_end')->nullable();
            $table->decimal('min_order_qty', 12, 3)->default(1);
            $table->decimal('max_order_qty', 12, 3)->nullable();
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
            $table->string('contact_person')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('payment_terms', 100)->nullable();
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

        // ─── Inventory: stocktakes ───────────────────────────
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('reference_number', 50)->nullable();
            $table->string('type', 20)->default('full');
            $table->string('status', 20)->default('in_progress');
            $table->uuid('category_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('started_by');
            $table->uuid('completed_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // ─── Inventory: stocktake_items ──────────────────────
        Schema::create('stocktake_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('stocktake_id');
            $table->uuid('product_id');
            $table->decimal('expected_qty', 12, 3)->default(0);
            $table->decimal('counted_qty', 12, 3)->nullable();
            $table->decimal('variance', 12, 3)->nullable();
            $table->decimal('cost_impact', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('counted_at')->nullable();
        });

        // ─── Inventory: waste_records ────────────────────────
        Schema::create('waste_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('reason', 50);
            $table->string('batch_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by');
            $table->timestamp('created_at')->nullable();
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

        // ─── Customer: wishlists ──────────────────────────────
        Schema::create('wishlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('customer_id');
            $table->uuid('product_id');
            $table->timestamp('added_at')->nullable();
        });

        // ─── Customer: appointments ───────────────────────────
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('customer_id');
            $table->uuid('staff_id')->nullable();
            $table->uuid('service_product_id')->nullable();
            $table->date('appointment_date')->nullable();
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();
        });

        // ─── Customer: cfd_configurations ─────────────────────
        Schema::create('cfd_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->boolean('is_enabled')->default(false);
            $table->string('target_monitor', 50)->nullable();
            $table->json('theme_config')->nullable();
            $table->json('idle_content')->nullable();
            $table->integer('idle_rotation_seconds')->default(10);
        });

        // ─── Customer: gift_registries ────────────────────────
        Schema::create('gift_registries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('customer_id');
            $table->string('name', 200);
            $table->string('event_type', 50)->nullable();
            $table->date('event_date')->nullable();
            $table->string('share_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
        });

        // ─── Customer: gift_registry_items ────────────────────
        Schema::create('gift_registry_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('registry_id');
            $table->uuid('product_id');
            $table->integer('quantity_desired')->default(1);
            $table->integer('quantity_purchased')->default(0);
            $table->string('purchased_by_name')->nullable();
        });

        // ─── Customer: signage_playlists ──────────────────────
        Schema::create('signage_playlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('name', 200);
            $table->json('slides')->nullable();
            $table->json('schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Customer: loyalty_challenges ─────────────────────
        Schema::create('loyalty_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('challenge_type', 30)->nullable();
            $table->decimal('target_value', 12, 2)->default(0);
            $table->string('reward_type', 30)->nullable();
            $table->decimal('reward_value', 12, 2)->default(0);
            $table->uuid('reward_badge_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
        });

        // ─── Customer: loyalty_badges ─────────────────────────
        Schema::create('loyalty_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('icon_url')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
        });

        // ─── Customer: loyalty_tiers ──────────────────────────
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('tier_name_ar')->nullable();
            $table->string('tier_name_en')->nullable();
            $table->integer('tier_order')->default(0);
            $table->integer('min_points')->default(0);
            $table->json('benefits')->nullable();
            $table->string('icon_url')->nullable();
        });

        // ─── Customer: customer_challenge_progress ────────────
        Schema::create('customer_challenge_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('challenge_id');
            $table->decimal('current_value', 12, 2)->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('reward_claimed')->default(false);
            $table->timestamps();
        });

        // ─── Customer: customer_badges ────────────────────────
        Schema::create('customer_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('badge_id');
            $table->timestamp('earned_at')->nullable();
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

        // ─── POS: exchange_transactions ───────────────────────
        Schema::create('exchange_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('return_transaction_id');
            $table->uuid('sale_transaction_id');
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        // ─── POS: tax_exemptions ──────────────────────────────
        Schema::create('tax_exemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->uuid('customer_id')->nullable();
            $table->string('exemption_type', 30);
            $table->string('customer_tax_id', 50)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
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

        // ─── Order: exchanges ─────────────────────────────────
        Schema::create('exchanges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->uuid('original_order_id');
            $table->uuid('return_id');
            $table->uuid('new_order_id');
            $table->decimal('net_amount', 12, 2);
            $table->uuid('processed_by');
            $table->timestamp('created_at')->nullable();
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

        // ─── Payment: gift_card_transactions ────────────────
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gift_card_id');
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->uuid('payment_id')->nullable();
            $table->uuid('store_id');
            $table->uuid('performed_by');
            $table->timestamp('created_at')->nullable();
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
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->uuid('notifiable_id');
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

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
            $table->string('priority', 10)->default('normal');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('channel', 20)->default('in_app');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->json('preferences_json')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->json('per_category_channels')->nullable();
            $table->boolean('sound_enabled')->default(true);
            $table->string('email_digest', 20)->default('none');
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

        Schema::create('notification_provider_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 30);
            $table->string('channel', 20);
            $table->boolean('is_enabled')->default(true);
            $table->integer('priority')->default(1);
            $table->boolean('is_healthy')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->integer('failure_count_24h')->default(0);
            $table->integer('success_count_24h')->default(0);
            $table->integer('avg_latency_ms')->nullable();
            $table->text('disabled_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->string('channel', 20);
            $table->string('provider', 30);
            $table->string('recipient', 500);
            $table->string('status', 20);
            $table->string('provider_message_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->boolean('is_fallback')->default(false);
            $table->json('attempted_providers')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ─── Notification Schedules ─────────────────────────
        if (!Schema::hasTable('notification_schedules')) {
            Schema::create('notification_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('event_key', 50);
                $table->string('channel', 20);
                $table->uuid('recipient_user_id')->nullable();
                $table->string('recipient_group', 50)->nullable();
                $table->json('variables')->nullable();
                $table->string('schedule_type', 20)->default('once');
                $table->timestamp('scheduled_at');
                $table->string('cron_expression', 100)->nullable();
                $table->string('timezone', 50)->default('Asia/Riyadh');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        // ─── Notification Batches ───────────────────────────
        if (!Schema::hasTable('notification_batches')) {
            Schema::create('notification_batches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->nullable();
                $table->string('event_key', 50);
                $table->string('channel', 20);
                $table->integer('total_recipients')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->string('status', 20)->default('pending');
                $table->json('metadata')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // ─── Notification Sound Configs ─────────────────────
        if (!Schema::hasTable('notification_sound_configs')) {
            Schema::create('notification_sound_configs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('event_key', 50);
                $table->boolean('is_enabled')->default(true);
                $table->string('sound_file', 255)->default('default');
                $table->decimal('volume', 3, 2)->default(0.80);
                $table->integer('repeat_count')->default(1);
                $table->integer('repeat_interval_seconds')->default(5);
                $table->timestamps();
            });
        }

        // ─── Notification Read Receipts ─────────────────────
        if (!Schema::hasTable('notification_read_receipts')) {
            Schema::create('notification_read_receipts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('notification_id');
                $table->uuid('user_id');
                $table->timestamp('read_at')->useCurrent();
                $table->string('read_via', 30)->default('click');
                $table->string('device_type', 20)->nullable();
            });
        }

        // ─── Security Sessions (Provider-side) ──────────────
        if (!Schema::hasTable('security_sessions')) {
            Schema::create('security_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('user_id');
                $table->uuid('device_id')->nullable();
                $table->string('session_type', 20)->default('shift');
                $table->string('status', 20)->default('active');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('end_reason', 50)->nullable();
                $table->json('metadata')->nullable();
            });
        }

        // ─── Security Incidents (Provider-side) ─────────────
        if (!Schema::hasTable('security_incidents')) {
            Schema::create('security_incidents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('incident_type', 50);
                $table->string('severity', 20)->default('medium');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->uuid('user_id')->nullable();
                $table->uuid('device_id')->nullable();
                $table->string('status', 20)->default('open');
                $table->uuid('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->boolean('auto_detected')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // ─── Accounting Integration ─────────────────────────
        Schema::create('store_accounting_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->unique();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->text('access_token_encrypted')->nullable()->default('');
            $table->text('refresh_token_encrypted')->nullable()->default('');
            $table->timestamp('token_expires_at')->nullable();
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
            $table->text('api_key')->nullable();
            $table->string('merchant_id', 100)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('branch_id_on_platform', 100)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('auto_accept')->default(true);
            $table->integer('throttle_limit')->nullable();
            $table->integer('max_daily_orders')->nullable();
            $table->boolean('operating_hours_synced')->default(false);
            $table->timestamp('last_order_received_at')->nullable();
            $table->integer('daily_order_count')->default(0);
            $table->boolean('sync_menu_on_product_change')->default(true);
            $table->integer('menu_sync_interval_hours')->default(6);
            $table->text('webhook_url')->nullable();
            $table->string('status', 20)->default('inactive');
            $table->timestamp('last_menu_sync_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'platform']);
        });

        Schema::create('delivery_order_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->string('platform', 50);
            $table->string('external_order_id', 100);
            $table->string('external_status', 50)->nullable();
            $table->string('delivery_status', 30)->default('pending');
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->integer('items_count')->default(0);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->integer('estimated_prep_minutes')->nullable();
            $table->text('notes')->nullable();
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
            $table->integer('products_count')->default(0);
            $table->json('error_details')->nullable();
            $table->text('error_message')->nullable();
            $table->string('triggered_by', 30)->default('manual');
            $table->string('sync_type', 30)->default('full');
            $table->integer('duration_seconds')->nullable();
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
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->uuid('reconciled_by')->nullable();
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
            $table->timestamps();
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
            $table->timestamps();
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
            $table->uuid('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders');
            $table->string('customer_name', 200)->nullable();
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

        // ─── ZATCA Compliance ───────────────────────────────
        if (!Schema::hasTable('zatca_invoices')) {
            Schema::create('zatca_invoices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->uuid('order_id');
                $table->string('invoice_number', 50);
                $table->string('invoice_type', 20);
                $table->text('invoice_xml');
                $table->string('invoice_hash', 64);
                $table->string('previous_invoice_hash', 64);
                $table->text('digital_signature');
                $table->text('qr_code_data');
                $table->decimal('total_amount', 12, 2);
                $table->decimal('vat_amount', 12, 2);
                $table->string('submission_status', 20)->default('pending');
                $table->string('zatca_response_code', 10)->nullable();
                $table->text('zatca_response_message')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('zatca_certificates')) {
            Schema::create('zatca_certificates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id');
                $table->string('certificate_type', 20);
                $table->text('certificate_pem');
                $table->string('ccsid', 100);
                $table->string('pcsid', 100)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Cashier Gamification & Theft Deterrence ─────────────
        Schema::create('cashier_gamification_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->boolean('leaderboard_enabled')->default(true);
            $table->boolean('badges_enabled')->default(true);
            $table->boolean('anomaly_detection_enabled')->default(true);
            $table->boolean('shift_reports_enabled')->default(true);
            $table->boolean('auto_generate_on_session_close')->default(true);
            $table->decimal('risk_score_void_weight', 5, 2)->default(30);
            $table->decimal('risk_score_no_sale_weight', 5, 2)->default(25);
            $table->decimal('risk_score_discount_weight', 5, 2)->default(25);
            $table->decimal('risk_score_price_override_weight', 5, 2)->default(20);
            $table->decimal('anomaly_z_score_threshold', 5, 2)->default(2.0);
            $table->timestamps();
            $table->unique('store_id');
        });

        Schema::create('cashier_performance_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->uuid('cashier_id');
            $table->foreign('cashier_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('pos_session_id')->nullable();
            $table->string('period_type', 20)->default('daily');
            $table->date('date');
            $table->timestamp('shift_start')->nullable();
            $table->timestamp('shift_end')->nullable();
            $table->integer('active_minutes')->default(0);
            $table->integer('total_transactions')->default(0);
            $table->integer('total_items_sold')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_discount_given', 12, 2)->default(0);
            $table->decimal('avg_basket_size', 12, 2)->default(0);
            $table->decimal('items_per_minute', 8, 2)->default(0);
            $table->integer('avg_transaction_time_seconds')->default(0);
            $table->integer('void_count')->default(0);
            $table->decimal('void_amount', 12, 2)->default(0);
            $table->decimal('void_rate', 5, 4)->default(0);
            $table->integer('return_count')->default(0);
            $table->decimal('return_amount', 12, 2)->default(0);
            $table->integer('discount_count')->default(0);
            $table->decimal('discount_rate', 5, 4)->default(0);
            $table->integer('price_override_count')->default(0);
            $table->integer('no_sale_count')->default(0);
            $table->integer('upsell_count')->default(0);
            $table->decimal('upsell_rate', 5, 4)->default(0);
            $table->decimal('cash_variance', 12, 2)->default(0);
            $table->decimal('cash_variance_absolute', 12, 2)->default(0);
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->json('anomaly_flags')->nullable();
            $table->timestamps();
        });

        Schema::create('cashier_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->string('slug', 50);
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->string('description_en', 500)->nullable();
            $table->string('description_ar', 500)->nullable();
            $table->string('icon', 50)->default('emoji_events');
            $table->string('color', 20)->default('#FD8209');
            $table->string('trigger_type', 50);
            $table->decimal('trigger_threshold', 12, 2)->default(0);
            $table->string('period', 20)->default('daily');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'slug']);
        });

        Schema::create('cashier_badge_awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->uuid('cashier_id');
            $table->foreign('cashier_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('badge_id');
            $table->foreign('badge_id')->references('id')->on('cashier_badges')->cascadeOnDelete();
            $table->uuid('snapshot_id')->nullable();
            $table->date('earned_date');
            $table->string('period', 20)->default('daily');
            $table->decimal('metric_value', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('cashier_anomalies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->uuid('cashier_id');
            $table->foreign('cashier_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('snapshot_id')->nullable();
            $table->string('anomaly_type', 50);
            $table->string('severity', 20)->default('medium');
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->string('title_en', 255);
            $table->string('title_ar', 255);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('metric_name', 50);
            $table->decimal('metric_value', 12, 2);
            $table->decimal('store_average', 12, 2)->default(0);
            $table->decimal('store_stddev', 12, 2)->default(0);
            $table->decimal('z_score', 8, 2)->default(0);
            $table->json('reference_ids')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->date('detected_date');
            $table->timestamps();
        });

        Schema::create('cashier_shift_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->uuid('pos_session_id')->nullable();
            $table->uuid('cashier_id');
            $table->foreign('cashier_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('report_date');
            $table->timestamp('shift_start')->nullable();
            $table->timestamp('shift_end')->nullable();
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->integer('total_items')->default(0);
            $table->decimal('items_per_minute', 8, 2)->default(0);
            $table->decimal('avg_basket_size', 12, 2)->default(0);
            $table->integer('void_count')->default(0);
            $table->decimal('void_amount', 12, 2)->default(0);
            $table->integer('return_count')->default(0);
            $table->decimal('return_amount', 12, 2)->default(0);
            $table->integer('discount_count')->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->integer('no_sale_count')->default(0);
            $table->integer('price_override_count')->default(0);
            $table->decimal('cash_variance', 12, 2)->default(0);
            $table->integer('upsell_count')->default(0);
            $table->decimal('upsell_rate', 5, 4)->default(0);
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->string('risk_level', 20)->default('normal');
            $table->integer('anomaly_count')->default(0);
            $table->json('badges_earned')->nullable();
            $table->text('summary_en')->nullable();
            $table->text('summary_ar')->nullable();
            $table->boolean('sent_to_owner')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $tables = [
            // Cashier Gamification
            'cashier_shift_reports', 'cashier_anomalies', 'cashier_badge_awards',
            'cashier_badges', 'cashier_performance_snapshots', 'cashier_gamification_settings',
            // ZATCA Compliance
            'zatca_certificates', 'zatca_invoices',
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
            'notification_read_receipts', 'notification_sound_configs', 'notification_batches', 'notification_schedules',
            'notification_delivery_logs', 'notification_provider_status',
            'notification_events_log', 'fcm_tokens', 'notification_preferences', 'notifications_custom', 'notifications',
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
            'refunds', 'gift_card_transactions', 'gift_cards', 'expenses', 'cash_events', 'cash_sessions', 'payments',
            // Order domain
            'return_items', 'returns', 'exchanges', 'order_status_history', 'order_items', 'orders',
            // POS domain
            'tax_exemptions', 'exchange_transactions', 'held_carts', 'transaction_items', 'transactions', 'pos_sessions',
            // Label domain
            'label_print_history', 'label_templates',
            // Customer domain
            'customer_badges', 'customer_challenge_progress', 'loyalty_tiers', 'loyalty_badges', 'loyalty_challenges',
            'signage_playlists', 'gift_registry_items', 'gift_registries', 'cfd_configurations', 'appointments', 'wishlists',
            'digital_receipt_log', 'store_credit_transactions', 'loyalty_transactions',
            'loyalty_config', 'customers', 'customer_groups',
            // Inventory domain
            'waste_records', 'stocktake_items', 'stocktakes',
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
            // Platform Admin tables
            'store_health_snapshots', 'feature_adoption_stats', 'platform_plan_stats', 'platform_daily_stats',
            'security_incidents', 'security_sessions',
            'security_alerts', 'security_policies', 'security_audit_log', 'login_attempts', 'device_registrations',
            'admin_sessions', 'admin_trusted_devices', 'admin_ip_blocklist', 'admin_ip_allowlist',
            'app_update_stats', 'app_releases',
            'delivery_platform_endpoints', 'delivery_platform_fields', 'delivery_platforms',
            'system_health_checks', 'platform_event_logs',
            'cms_pages', 'notification_templates',
            'feature_flags', 'ab_test_events', 'ab_test_variants', 'ab_tests',
            'security_policy_defaults', 'certified_hardware', 'payment_methods',
            'age_restricted_categories', 'tax_exemption_types', 'translation_versions',
            'master_translation_strings', 'supported_locales', 'translation_overrides', 'system_settings',
            'implementation_fees', 'hardware_sales', 'payment_gateway_configs', 'payment_retry_rules',
            'subscription_credits', 'subscription_discounts',
            'payment_reminders', 'platform_announcement_dismissals', 'platform_announcements',
            'knowledge_base_articles', 'canned_responses', 'support_ticket_messages', 'support_tickets',
            'cancellation_reasons', 'provider_notes', 'provider_registrations',
            'admin_activity_logs', 'admin_user_roles', 'admin_role_permissions', 'admin_permissions', 'admin_roles',
            // Content onboarding
            'pos_customization_settings', 'user_preferences',
            'label_template_package_visibility', 'label_template_business_types', 'label_layout_templates',
            'signage_template_package_visibility', 'signage_template_business_types', 'signage_templates',
            'cfd_theme_package_visibility', 'cfd_themes',
            'receipt_template_package_visibility', 'receipt_layout_templates',
            'layout_package_visibility', 'theme_package_visibility',
            // Layout builder & marketplace
            'theme_variables', 'template_versions',
            'template_reviews', 'template_purchases', 'template_marketplace_listings',
            'marketplace_categories',
            'widget_theme_overrides', 'layout_widget_placements', 'layout_widgets',
            'themes', 'platform_ui_defaults', 'pos_layout_templates',
            'predefined_product_images', 'predefined_products', 'predefined_categories',
            'business_types',
            // Original tables
            'audit_logs', 'role_audit_log', 'pin_overrides',
            'default_role_template_permissions', 'default_role_templates', 'provider_permissions',
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
