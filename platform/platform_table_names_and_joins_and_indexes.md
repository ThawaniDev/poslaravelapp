# Wameed POS — Platform (Admin) DB Reference (Tables, Joins & Indexes)

> Quick-reference for table names, join/pivot tables, and recommended indexes.  
> **No SQL here** — just names and notes for planning.  
> Platform tables live in the **central PostgreSQL database** and are accessed by the Laravel + Filament v3 Super Admin Panel.  
> Some tables are shared with the provider scope (e.g. organizations, stores, users) — noted where applicable.

---

## 🏢 Core / Shared Tables (platform reads & writes)

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **organizations** | Chain / brand entities | Created when a provider registers. tax_number, cr_number, settings JSONB, subscription_plan |
| **stores** | Individual branches | FK → organizations. ZATCA creds, business_type_id, is_active, lat/lng |
| **users** | All accounts (provider staff + platform admins) | is_platform_admin flag or separate admin_users table (see below) |
| **registers** | POS devices | FK → stores. device_id, app_version, last_sync_at |

---

## 👑 Platform Roles

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **admin_users** | Platform admin accounts (Thawani internal) | name, email, password_hash, is_active, two_factor_secret, two_factor_enabled, last_login_at, last_login_ip |
| **admin_roles** | Platform-level roles | name, slug (super_admin/platform_manager/support_agent/finance_admin/integration_manager/sales/viewer/custom), description, is_system |
| **admin_permissions** | Granular platform permissions | name (e.g. "stores.view", "billing.edit", "tickets.respond"), group, description |
| **admin_role_permissions** | Permissions assigned to admin role | Join: FK → admin_roles, admin_permissions |
| **admin_user_roles** | Roles assigned to an admin user | Join: FK → admin_users, admin_roles. Supports multiple roles per user |
| **admin_activity_logs** | All admin action audit trail | FK → admin_users. action, entity_type, entity_id, ip_address, details JSONB, created_at |

### Indexes
- `admin_users.email` — UNIQUE
- `admin_roles.slug` — UNIQUE
- `admin_permissions.name` — UNIQUE
- `admin_role_permissions(admin_role_id, admin_permission_id)` — UNIQUE composite
- `admin_user_roles(admin_user_id, admin_role_id)` — UNIQUE composite
- `admin_activity_logs(admin_user_id, created_at)`
- `admin_activity_logs(entity_type, entity_id)`
- `admin_activity_logs.action`

---

## 🏪 Provider Management

> Uses **organizations**, **stores**, **users**, **store_subscriptions** and adds management-specific tables.

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **provider_registrations** | New provider signup queue | organization_name, owner_name, owner_email, owner_phone, status (pending/approved/rejected), reviewed_by (FK → admin_users), reviewed_at, rejection_reason |
| **provider_notes** | Internal admin notes per provider | FK → organizations, admin_users (author). note_text, created_at |
| **provider_limit_overrides** | One-off limit exceptions | FK → stores. limit_key (max_cashiers/max_products/…), override_value, reason, set_by (FK → admin_users), expires_at |
| **cancellation_reasons** | Tracked when provider cancels | FK → store_subscriptions. reason_category (price/features/competitor/other), reason_text, cancelled_at |

### Indexes
- `provider_registrations.status`
- `provider_registrations(owner_email)`
- `provider_notes.organization_id`
- `provider_limit_overrides(store_id, limit_key)` — UNIQUE composite
- `cancellation_reasons.store_subscription_id`

---

## 📦 Package & Subscription Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **subscription_plans** | Package definitions | name / name_ar, slug, monthly_price, annual_price, is_active, is_highlighted, sort_order, trial_days, grace_period_days |
| **plan_feature_toggles** | Feature flags per plan | FK → subscription_plans. feature_key (kitchen_display/advanced_analytics/custom_themes/modifier_groups/recipe_inventory/employee_scheduling/accounting_export/tip_management/reservation_system/…), is_enabled |
| **plan_limits** | Hard limits per plan | FK → subscription_plans. limit_key (max_cashiers/max_terminals/max_products/max_branches/max_delivery_platforms/max_custom_roles), limit_value, price_per_extra_unit |
| **store_subscriptions** | Active subscription per store | FK → stores, subscription_plans. status (trial/active/grace/cancelled/expired), current_period_start, current_period_end, trial_ends_at, payment_method |
| **invoices** | Billing invoices | FK → store_subscriptions. amount, tax, total, status (draft/pending/paid/failed/refunded), due_date, paid_at, pdf_url |
| **invoice_line_items** | Breakdown per invoice | FK → invoices. description, quantity, unit_price, total |
| **subscription_discounts** | Discount codes for plans | code (UNIQUE), type (percentage/fixed), value, max_uses, times_used, valid_from, valid_to, applicable_plan_ids JSONB |
| **subscription_credits** | Manual credits applied | FK → store_subscriptions, admin_users (applied_by). amount, reason, applied_at |
| **plan_add_ons** | Paid extras (Thawani integration, white-label, API, accounting export, reservation) | name / name_ar, slug, monthly_price, description, is_active |
| **store_add_ons** | Add-ons activated per store | Join: FK → stores, plan_add_ons. activated_at, is_active |

### Indexes
- `subscription_plans.slug` — UNIQUE
- `subscription_plans(is_active, sort_order)`
- `plan_feature_toggles(subscription_plan_id, feature_key)` — UNIQUE composite
- `plan_limits(subscription_plan_id, limit_key)` — UNIQUE composite
- `store_subscriptions(store_id)` — UNIQUE (one active per store)
- `store_subscriptions.status`
- `store_subscriptions.subscription_plan_id`
- `invoices(store_subscription_id, due_date)`
- `invoices.status`
- `subscription_discounts.code` — UNIQUE
- `store_add_ons(store_id, plan_add_on_id)` — UNIQUE composite

---

## 🚚 Third-Party Delivery Platform Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **delivery_platforms** | Master platform definitions (admin-managed) | name, slug (hungerstation/keeta/jahez/…), logo_url, auth_method (bearer/api_key/basic/oauth2), is_active, sort_order |
| **delivery_platform_fields** | Custom credential fields per platform | FK → delivery_platforms. field_label, field_key, field_type (text/password/url), is_required, sort_order |
| **delivery_platform_endpoints** | Operation endpoint templates | FK → delivery_platforms. operation (product_create/product_update/product_delete/category_sync/bulk_menu_push), url_template, http_method, request_mapping JSONB |
| **delivery_platform_webhook_templates** | Inbound webhook path pattern | FK → delivery_platforms. path_template (e.g. "/webhooks/{platform_slug}/{store_id}") |

> Per-store activation stored in provider-side **store_delivery_platforms** (FK → stores, delivery_platforms).

### Indexes
- `delivery_platforms.slug` — UNIQUE
- `delivery_platforms.is_active`
- `delivery_platform_fields(delivery_platform_id, field_key)` — UNIQUE composite
- `delivery_platform_endpoints(delivery_platform_id, operation)` — UNIQUE composite

---

## 🔔 Notification Template Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **notification_templates** | Message templates per event + channel | event_key (e.g. "order.new", "inventory.low_stock"), channel (in_app/push/sms/email/whatsapp), title / title_ar, body / body_ar, available_variables JSONB (e.g. ["order_id", "total", "store_name"]), is_active |

### Indexes
- `notification_templates(event_key, channel)` — UNIQUE composite

---

## 🎨 POS Interface & Layout Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **business_types** | Master list of business types | name / name_ar, slug, icon, is_active, sort_order |
| **pos_layout_templates** | Layout variants per business type | FK → business_types. layout_key, name / name_ar, config JSONB, is_default, sort_order, is_active |
| **platform_ui_defaults** | Global UI defaults | key (handedness/font_size/theme), value. Single-row config or key-value pairs |
| **themes** | Theme presets | name, slug, primary_color, secondary_color, background_color, text_color, is_active, is_system. Admin can add/edit |
| **theme_package_visibility** | Which themes visible per plan | Join: FK → themes, subscription_plans |
| **layout_package_visibility** | Which layouts visible per plan | Join: FK → pos_layout_templates, subscription_plans |

### Indexes
- `business_types.slug` — UNIQUE
- `pos_layout_templates(business_type_id, is_default)`
- `pos_layout_templates.layout_key` — UNIQUE
- `themes.slug` — UNIQUE
- `theme_package_visibility(theme_id, subscription_plan_id)` — UNIQUE composite
- `layout_package_visibility(pos_layout_template_id, subscription_plan_id)` — UNIQUE composite

---

## 📊 Platform Analytics & Reporting

> Analytics are **read-only aggregation queries** across existing tables.  
> Key source tables: store_subscriptions, invoices, stores, transactions, orders, delivery_sync_logs, support_tickets, zatca_invoices, admin_activity_logs, registers.

### Materialised / Cache Tables (optional, for dashboard performance)
| Table | Purpose | Notes |
|---|---|---|
| **platform_daily_stats** | Pre-aggregated daily platform metrics | date, total_active_stores, new_registrations, total_orders, total_gmv, total_mrr, churn_count. Rebuilt nightly |
| **platform_plan_stats** | Per-plan breakdown | subscription_plan_id, date, active_count, trial_count, churned_count, mrr |
| **feature_adoption_stats** | Feature usage tracking | feature_key, date, stores_using_count, total_events |
| **store_health_snapshots** | Per-store periodic health | store_id, date, sync_status, zatca_compliance, error_count, last_activity_at |

### Indexes
- `platform_daily_stats.date` — UNIQUE
- `platform_plan_stats(subscription_plan_id, date)` — UNIQUE composite
- `feature_adoption_stats(feature_key, date)` — UNIQUE composite
- `store_health_snapshots(store_id, date)` — UNIQUE composite

---

## 🎫 Support Ticket System

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **support_tickets** | Tickets from providers | FK → organizations, stores, admin_users (assigned_to). ticket_number, subject, priority (low/medium/high/critical), status (open/in_progress/resolved/closed), sla_deadline_at, created_at, resolved_at, closed_at |
| **support_ticket_messages** | Conversation thread | FK → support_tickets. sender_type (provider/admin), sender_id, message_text, attachments JSONB, is_internal_note, sent_at |
| **canned_responses** | Pre-written reply templates | title, shortcut (e.g. "/greeting"), body / body_ar, category, is_active |
| **knowledge_base_articles** | Help articles | title / title_ar, slug, body / body_ar, category, is_published, sort_order, created_at, updated_at |

### Indexes
- `support_tickets.ticket_number` — UNIQUE
- `support_tickets(status, priority)`
- `support_tickets.organization_id`
- `support_tickets.assigned_to`
- `support_tickets(created_at)`
- `support_ticket_messages.support_ticket_id`
- `support_ticket_messages(sent_at)`
- `canned_responses.shortcut` — UNIQUE
- `knowledge_base_articles.slug` — UNIQUE
- `knowledge_base_articles(is_published, category)`

---

## 💰 Billing & Finance Admin

> Core billing tables already listed under **Package & Subscription Management**: invoices, invoice_line_items, subscription_discounts, subscription_credits, store_add_ons.

### Additional Tables
| Table | Purpose | Notes |
|---|---|---|
| **payment_gateway_configs** | Gateway credentials (Thawani Pay / Stripe) | gateway_name, credentials_encrypted JSONB, webhook_url, is_active, environment (sandbox/production) |
| **payment_retry_rules** | Failed payment retry config | max_retries, retry_interval_hours, grace_period_after_failure_days |
| **hardware_sales** | Hardware sold to providers (terminals, printers) | FK → stores, admin_users (sold_by). item_type, item_description, serial_number, amount, sold_at |
| **implementation_fees** | Setup / training fees per store | FK → stores. fee_type (setup/training/custom_dev), amount, status (invoiced/paid), notes, created_at |

### Indexes
- `payment_gateway_configs(gateway_name, environment)` — UNIQUE composite
- `hardware_sales(store_id, sold_at)`
- `hardware_sales.serial_number`
- `implementation_fees(store_id, fee_type)`

---

## 🔐 Security & Audit (Platform)

> Audit logging already covered by **admin_activity_logs** (under Platform Roles).

### Additional Tables
| Table | Purpose | Notes |
|---|---|---|
| **admin_ip_allowlist** | Allowed IPs for admin panel access | ip_address, label, added_by (FK → admin_users), created_at |
| **admin_ip_blocklist** | Blocked IPs | ip_address, reason, blocked_by (FK → admin_users), created_at |
| **admin_trusted_devices** | Trusted devices per admin | FK → admin_users. device_fingerprint, device_name, user_agent, trusted_at, last_used_at |
| **admin_sessions** | Active login sessions | FK → admin_users. session_token_hash, ip_address, user_agent, created_at, expires_at, revoked_at |
| **security_alerts** | Suspicious activity alerts | FK → admin_users (if applicable). alert_type (brute_force/bulk_export/unusual_ip), severity, details JSONB, status (new/investigating/resolved), created_at |

### Indexes
- `admin_ip_allowlist.ip_address` — UNIQUE
- `admin_ip_blocklist.ip_address` — UNIQUE
- `admin_trusted_devices(admin_user_id, device_fingerprint)` — UNIQUE composite
- `admin_sessions(admin_user_id, revoked_at)`
- `admin_sessions.session_token_hash` — UNIQUE
- `security_alerts(alert_type, status, created_at)`

---

## ⚙️ System Configuration

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **system_settings** | Key-value config store | key (UNIQUE), value JSONB, group (zatca/payment/sms/email/push/whatsapp/sync/vat/locale), description, updated_by (FK → admin_users), updated_at |
| **feature_flags** | Gradual rollout flags | flag_key (UNIQUE), is_enabled, rollout_percentage, target_plan_ids JSONB, target_store_ids JSONB, description, updated_at |
| **tax_exemption_types** | Tax exemption categories | code, name / name_ar, required_documents, is_active |
| **age_restricted_categories** | Categories requiring age check | FK → (links to provider-side categories by category slug or rule), category_slug, min_age, is_active |
| **accounting_integration_configs** | Platform-level accounting API creds | provider_name (quickbooks/xero/qoyod), client_id_encrypted, client_secret_encrypted, redirect_url, is_active |

### Indexes
- `system_settings.key` — UNIQUE
- `system_settings.group`
- `feature_flags.flag_key` — UNIQUE
- `tax_exemption_types.code` — UNIQUE
- `age_restricted_categories.category_slug`
- `accounting_integration_configs.provider_name` — UNIQUE

---

## 🚀 App & Update Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **app_releases** | Flutter desktop release versions | version_number, platform (windows/macos), channel (stable/beta), download_url, release_notes / release_notes_ar, is_force_update, min_supported_version, rollout_percentage, is_active, released_at |
| **app_update_stats** | Per-store update tracking | FK → stores, app_releases. status (pending/downloading/downloaded/installed/failed), updated_at |

### Indexes
- `app_releases(platform, channel, version_number)` — UNIQUE composite
- `app_releases(platform, channel, is_active)`
- `app_update_stats(app_release_id, status)`
- `app_update_stats.store_id`

---

## 👥 User Management (Cross-Store)

> Uses the shared **users** table (provider-side accounts) and **admin_users** (platform accounts).  
> No additional tables needed — queries against users with joins to organizations and stores.

### Key Queries Touch
- `users` — search by email/phone, filter by is_active, organization_id
- `admin_users` — platform team management
- `audit_logs` / `admin_activity_logs` — user activity

---

## 🛡️ Provider Roles & Permissions Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **provider_permissions** | Master list of provider-side permissions | name (e.g. "pos.sell", "pos.void", "inventory.adjust"), group (POS/Inventory/Reports/Settings/Staff), description. Platform-seeded, referenced by provider-side **role_permissions** |
| **default_role_templates** | Default roles shipped to new providers | name, slug (owner/chain_manager/branch_manager/cashier/inventory_clerk/accountant/kitchen_staff), description |
| **default_role_template_permissions** | Permissions per default role | Join: FK → default_role_templates, provider_permissions |
| **custom_role_package_config** | Custom role feature gating per plan | FK → subscription_plans. is_custom_roles_enabled, max_custom_roles |

> When a new store is created, default_role_templates + default_role_template_permissions are copied into the provider-side **roles** and **role_permissions** tables for that organization.

### Indexes
- `provider_permissions.name` — UNIQUE
- `provider_permissions.group`
- `default_role_templates.slug` — UNIQUE
- `default_role_template_permissions(default_role_template_id, provider_permission_id)` — UNIQUE composite
- `custom_role_package_config.subscription_plan_id` — UNIQUE

---

## 🔔 Platform Announcements

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **platform_announcements** | Announcements to providers | type (info/warning/maintenance/update), title / title_ar, body / body_ar, target_filter JSONB (plan_ids, region, all), display_start_at, display_end_at, is_banner, created_by (FK → admin_users), created_at |
| **payment_reminders** | Automated renewal reminders | FK → store_subscriptions. reminder_type (upcoming/overdue), sent_at, channel (email/sms/push) |

### Indexes
- `platform_announcements(display_start_at, display_end_at)` — active announcements
- `platform_announcements.type`
- `payment_reminders(store_subscription_id, reminder_type)`
- `payment_reminders.sent_at`

---

## ☁️ Infrastructure & Operations

> Primarily monitored via **Laravel Horizon**, **Redis**, and server metrics — not stored in application database.

### Tables (if tracking in DB)
| Table | Purpose | Notes |
|---|---|---|
| **failed_jobs** | Laravel failed job log | Built-in Laravel table. connection, queue, payload, exception, failed_at |
| **database_backups** | Platform DB backup log | backup_type (auto_daily/auto_weekly/manual), file_path, file_size_bytes, status (completed/failed), started_at, completed_at |

### Indexes
- `failed_jobs.failed_at`
- `database_backups(backup_type, started_at)`

---

## 📋 Content & Onboarding Management

### Tables
| Table | Purpose | Notes |
|---|---|---|
| **business_types** | (Shared — listed under POS Interface & Layout) | name / name_ar, slug, icon, sort_order, is_active |
| **onboarding_steps** | Step definitions for provider onboarding flow | step_number, title / title_ar, description / description_ar, is_required, sort_order |
| **help_articles** | (Same as knowledge_base_articles under Support) | Categorised by topic, optionally linked to delivery_platform_id |
| **pricing_page_content** | Public pricing page data | FK → subscription_plans. feature_bullet_list JSONB, faq JSONB |

> The **business_type_category_templates** table (see provider DB reference) seeds default categories during onboarding.

### Indexes
- `onboarding_steps.step_number` — UNIQUE
- `pricing_page_content.subscription_plan_id` — UNIQUE

---

## 📌 Cross-Reference: Shared Tables

These tables are jointly used by both provider and platform scopes:

| Table | Provider Use | Platform Use |
|---|---|---|
| **organizations** | Store owner's org | Admin views/edits, approvals |
| **stores** | Store config & operations | Provider management, analytics |
| **users** | Staff accounts | Cross-store user management |
| **store_subscriptions** | Self-service billing view | Full billing admin |
| **invoices** | View & download own invoices | Generate, refund, manage all |
| **store_delivery_platforms** | Enter creds, toggle on/off | View which stores have which platforms |
| **registers** | POS device config | View terminal status per store |
| **zatca_device_config** | ZATCA onboarding | Monitor compliance rate |
| **business_types** | Onboarding selection | Manage list & templates |
| **pos_layout_templates** | Select active layout | Create/edit/manage all layouts |
