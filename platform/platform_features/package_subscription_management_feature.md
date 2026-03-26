# Package & Subscription Management — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** SaaS Billing, Plan Configuration & Feature Gating  
> **Tech Stack:** Laravel 11 + Filament v3 · Cashier (optional) · Spatie Permission  

---

## Implemented — Admin Panel (Filament)

### SubscriptionPlanResource
- Full plan builder with tabbed form (General / Pricing / Features / Limits)
- 27 feature toggles via repeater (pos_basic through zatca_compliance)
- 11 plan limit types via repeater (products, staff_members, branches, etc.)
- Infolist view with grouped feature/limit display
- StoreSubscriptionsRelationManager
- Table with subscriber count, status badges, toggle/duplicate actions

### StoreSubscriptionResource
- Full subscription lifecycle management
- Change plan, apply credit, cancel, resume actions via BillingService
- Status badges, multiple filters, InvoicesRelationManager, SubscriptionCreditsRelationManager

### SubscriptionDiscountResource
- Corrected field names matching model (type, value, valid_from, valid_to, applicable_plan_ids)
- DiscountType enum integration, usage tracking, duplicate action

### PlanAddOnResource
- Enhanced with store subscriber count, toggle active, StoreAddOnsRelationManager, infolist view

---

## 1. Feature Overview

Package & Subscription Management is the **commercial engine** of Thawani POS. It defines what each provider gets, for how much, and enforces those limits in real-time across the entire system.

### What This Feature Does
- Provides a **Package Builder** — admin creates/edits subscription plans with name (AR/EN), pricing, feature toggles, hard limits, and display settings
- Manages **feature toggles per plan** — kitchen display, advanced analytics, custom themes, modifier groups, recipe inventory, employee scheduling, accounting export, tip management, reservation system, etc.
- Enforces **hard limits per plan** — max cashiers, max terminals, max products, max branches, max delivery platforms, max custom roles — with optional price-per-extra-unit
- Manages **store subscriptions** — view all active subscriptions, change plans, manage billing cycle
- Handles **invoices** — auto-generated and manual, with line items, tax, PDF generation
- Supports **discount codes / coupons** for subscription plans
- Manages **add-ons** — paid extras like Thawani integration, white-label, API access, accounting export, reservation system — independently priced
- Configures **trial periods** and **grace periods** per plan
- Drives the **pricing page** — plan data is consumed by the public-facing pricing page

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Provider Management** | Store profile shows current plan, usage meters, plan-change action |
| **POS Layout Management** | Theme and layout visibility are gated by plan (via `theme_package_visibility`, `layout_package_visibility`) |
| **Provider Roles & Permissions** | Custom role creation is gated by `custom_role_package_config.is_custom_roles_enabled` per plan |
| **Third-Party Delivery Platform Mgmt** | `max_delivery_platforms` limit per plan |
| **Billing & Finance** | Invoices, payments, refunds, revenue dashboards all source from subscription data |
| **Analytics** | MRR, ARR, churn, plan distribution metrics derive from `store_subscriptions` + `invoices` |
| **Content & Onboarding** | Pricing page driven by `subscription_plans` data |
| **Every Provider-Side Feature** | Feature gating middleware checks `plan_feature_toggles` before granting access to any advanced feature |

### Features to Review After Changing This Feature
1. **Billing & Finance** — invoice generation logic, payment retry rules
2. **Provider Management** — usage meters must recalculate against new limits
3. **POS Layout / Theme Management** — visibility joins must respect new plans
4. **Provider Roles** — custom role gating per plan
5. **Feature Gate Middleware** — any new feature toggle key must be added to the middleware map
6. **Pricing Page** — any plan field change affects public page rendering
7. **Analytics** — MRR/ARR calculations must account for plan pricing changes

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for Plans, Subscriptions, Invoices, Discounts, Add-Ons |
| **spatie/laravel-permission** | Access gating: `billing.plans`, `billing.edit` |
| **barryvdh/laravel-dompdf** or **spatie/laravel-pdf** | Invoice PDF generation |
| **maatwebsite/laravel-excel** | Export subscription/billing data |
| **laravel/cashier-stripe** *(optional)* | If using Stripe for SaaS billing; otherwise, custom billing logic |
| **spatie/laravel-activitylog** | Audit log on plan changes |

### 3.2 Technologies
- **Laravel 11** — Eloquent, Jobs, Events, Notifications
- **Filament v3** — Admin UI
- **PostgreSQL** — relational data
- **Redis** — cache plan features for quick lookup in middleware
- **Laravel Scheduler** — nightly jobs for grace period expiration, trial end checks, invoice generation

---

## 4. Pages

### 4.1 Subscription Plans List
| Field | Detail |
|---|---|
| **Route** | `/admin/plans` |
| **Filament Resource** | `SubscriptionPlanResource` |
| **Table Columns** | Name (EN), Name (AR), Slug, Monthly Price, Annual Price, Active stores count, Is Active badge, Is Highlighted badge, Sort Order |
| **Filters** | Active/Inactive |
| **Row Actions** | Edit, Deactivate, Duplicate |
| **Access** | `billing.plans` |

### 4.2 Plan Create / Edit (Package Builder)
| Field | Detail |
|---|---|
| **Route** | `/admin/plans/create` · `/admin/plans/{id}/edit` |
| **Form Sections** | |
| — Basic Info | Name (EN), Name (AR), Slug (auto from name), Monthly Price (SAR), Annual Price (SAR), Trial Days, Grace Period Days, Sort Order, Is Active toggle, Is Highlighted toggle |
| — Feature Toggles | Repeater/checkbox list of feature keys (kitchen_display, advanced_analytics, custom_themes, modifier_groups, recipe_inventory, employee_scheduling, accounting_export, tip_management, reservation_system, loyalty_program, advanced_coupons, white_label, api_full_access, multi_branch, inventory_expiry_tracking, third_party_integrations, custom_roles, customer_facing_display, digital_signage, appointment_booking, gift_registry, wishlist, loyalty_gamification), each with is_enabled toggle |
| — Hard Limits | Key-value form: max_cashiers, max_terminals, max_products, max_branches, max_delivery_platforms, max_custom_roles, max_storage_gb — each with limit_value (0 = unlimited) and price_per_extra_unit (SAR) |
| **Validation** | Slug unique, prices >= 0, limits >= 0 |
| **Side Effects** | Plan changes apply immediately to all active subscribers; audit logged |
| **Access** | `billing.plans` |

### 4.3 Subscriptions List
| Field | Detail |
|---|---|
| **Route** | `/admin/subscriptions` |
| **Filament Resource** | `StoreSubscriptionResource` |
| **Table Columns** | Store Name, Organisation, Plan Name, Status badge (trial/active/grace/cancelled/expired), Billing Cycle, Current Period End, Payment Method, Created At |
| **Filters** | Status, Plan, Billing Cycle |
| **Search** | By store name, owner email |
| **Row Actions** | View Detail, Change Plan, Apply Credit, Cancel |
| **Access** | `billing.view` |

### 4.4 Subscription Detail
| Field | Detail |
|---|---|
| **Route** | `/admin/subscriptions/{id}` |
| **Sections** | Store info, Plan info, Usage meters vs limits, Billing history (invoices), Applied credits, Active add-ons, Cancellation history (if any) |
| **Actions** | Change Plan, Apply Discount/Credit, Generate Invoice, Cancel Subscription |
| **Access** | `billing.view` (actions require `billing.edit`) |

### 4.5 Invoices List
| Field | Detail |
|---|---|
| **Route** | `/admin/invoices` |
| **Table Columns** | Invoice Number, Store Name, Amount, Tax, Total, Status badge (draft/pending/paid/failed/refunded), Due Date, Paid At |
| **Filters** | Status, Date range, Plan |
| **Row Actions** | View, Download PDF, Mark as Paid (manual), Refund |
| **Access** | `billing.invoices` |

### 4.6 Invoice Detail
| Field | Detail |
|---|---|
| **Route** | `/admin/invoices/{id}` |
| **Sections** | Invoice header (number, store, dates), Line items table, Totals (subtotal, tax, total), Payment info, Status |
| **Actions** | Download PDF, Mark Paid, Process Refund |

### 4.7 Discount Codes & Coupons
| Field | Detail |
|---|---|
| **Route** | `/admin/discounts` |
| **Filament Resource** | `SubscriptionDiscountResource` |
| **Table Columns** | Code, Type (percentage/fixed), Value, Max Uses, Times Used, Valid From, Valid To, Applicable Plans |
| **Row Actions** | Edit, Deactivate |
| **Create Form** | Code (auto-generate option), Type select, Value, Max Uses, Date range, Applicable plan multi-select (or all plans) |
| **Access** | `billing.edit` |

### 4.8 Add-On Management
| Field | Detail |
|---|---|
| **Route** | `/admin/add-ons` |
| **Filament Resource** | `PlanAddOnResource` |
| **Table Columns** | Name (EN), Name (AR), Slug, Monthly Price, Active Stores count, Is Active |
| **Row Actions** | Edit, Deactivate |
| **Create Form** | Name (EN/AR), Slug, Monthly Price, Description, Is Active |
| **Access** | `billing.plans` |

### 4.9 Revenue Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/revenue` |
| **Purpose** | Financial overview |
| **Widgets** | MRR card, ARR card, Total Revenue chart (monthly), Revenue by Plan pie chart, Upcoming Renewals list, Failed Payments count |
| **Access** | `billing.view` |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD on `SubscriptionPlanResource`, `StoreSubscriptionResource`, `InvoiceResource`, etc.

### 5.2 Provider-Facing APIs (consumed by POS app and provider portal)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/subscription/current` | GET | Return current store subscription: plan, status, features, limits, usage | Store API token |
| `GET /api/v1/subscription/plans` | GET | List available plans for upgrade page | Store API token |
| `GET /api/v1/subscription/invoices` | GET | List invoices for the store | Store API token |
| `GET /api/v1/subscription/invoices/{id}/pdf` | GET | Download invoice PDF | Store API token |
| `POST /api/v1/subscription/upgrade` | POST | Request plan upgrade | Store API token |
| `POST /api/v1/subscription/cancel` | POST | Cancel subscription (with reason) | Store API token |

### 5.3 Feature Gate Check (used by every provider-facing API)
| Endpoint | Method | Purpose |
|---|---|---|
| Middleware `feature-gate:{feature_key}` | — | Applied to route groups; returns 403 with `upgrade_url` if feature not in plan |
| `Store::hasFeature(string $key): bool` | — | Model method; checks `plan_feature_toggles` |
| `Store::withinLimit(string $key): bool` | — | Model method; checks `plan_limits` + current usage + `provider_limit_overrides` |

---

## 6. Full Database Schema

### 6.1 Tables

#### `subscription_plans`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | English name |
| name_ar | VARCHAR(100) | NOT NULL | Arabic name |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | starter / professional / enterprise |
| monthly_price | DECIMAL(10,2) | NOT NULL | SAR |
| annual_price | DECIMAL(10,2) | NOT NULL | SAR |
| trial_days | INT | DEFAULT 14 | |
| grace_period_days | INT | DEFAULT 7 | Days after expiry before features are disabled |
| is_active | BOOLEAN | DEFAULT TRUE | |
| is_highlighted | BOOLEAN | DEFAULT FALSE | "Most Popular" badge |
| sort_order | INT | DEFAULT 0 | Pricing page order |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE subscription_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    monthly_price DECIMAL(10,2) NOT NULL,
    annual_price DECIMAL(10,2) NOT NULL,
    trial_days INT DEFAULT 14,
    grace_period_days INT DEFAULT 7,
    is_active BOOLEAN DEFAULT TRUE,
    is_highlighted BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `plan_feature_toggles`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |
| feature_key | VARCHAR(50) | NOT NULL | kitchen_display, advanced_analytics, custom_themes, modifier_groups, recipe_inventory, employee_scheduling, accounting_export, tip_management, reservation_system, loyalty_program, advanced_coupons, white_label, api_full_access, multi_branch, inventory_expiry_tracking, third_party_integrations, custom_roles, customer_facing_display, digital_signage, appointment_booking, gift_registry, wishlist, loyalty_gamification |
| is_enabled | BOOLEAN | DEFAULT FALSE | |

```sql
CREATE TABLE plan_feature_toggles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    feature_key VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT FALSE,
    UNIQUE (subscription_plan_id, feature_key)
);
```

#### `plan_limits`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |
| limit_key | VARCHAR(50) | NOT NULL | max_cashiers, max_terminals, max_products, max_branches, max_delivery_platforms, max_custom_roles, max_storage_gb |
| limit_value | INT | NOT NULL | 0 = unlimited |
| price_per_extra_unit | DECIMAL(10,2) | DEFAULT 0 | SAR per extra unit above the limit |

```sql
CREATE TABLE plan_limits (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    limit_key VARCHAR(50) NOT NULL,
    limit_value INT NOT NULL DEFAULT 0,
    price_per_extra_unit DECIMAL(10,2) DEFAULT 0,
    UNIQUE (subscription_plan_id, limit_key)
);
```

#### `store_subscriptions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id) ON DELETE CASCADE, UNIQUE | One active sub per organization |
| subscription_plan_id | UUID | FK → subscription_plans(id) | |
| status | VARCHAR(20) | NOT NULL | trial / active / grace / cancelled / expired |
| billing_cycle | VARCHAR(10) | DEFAULT 'monthly' | monthly / yearly |
| current_period_start | TIMESTAMP | NOT NULL | |
| current_period_end | TIMESTAMP | NOT NULL | |
| trial_ends_at | TIMESTAMP | NULLABLE | |
| payment_method | VARCHAR(50) | NULLABLE | credit_card, mada, bank_transfer |
| cancelled_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE store_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL UNIQUE REFERENCES organizations(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id),
    status VARCHAR(20) NOT NULL DEFAULT 'trial',
    billing_cycle VARCHAR(10) DEFAULT 'monthly',
    current_period_start TIMESTAMP NOT NULL,
    current_period_end TIMESTAMP NOT NULL,
    trial_ends_at TIMESTAMP,
    payment_method VARCHAR(50),
    cancelled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `invoices`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_subscription_id | UUID | FK → store_subscriptions(id) | |
| invoice_number | VARCHAR(50) | UNIQUE | Auto-generated: INV-2026-0001 |
| amount | DECIMAL(10,2) | NOT NULL | Subtotal |
| tax | DECIMAL(10,2) | DEFAULT 0 | VAT |
| total | DECIMAL(10,2) | NOT NULL | amount + tax |
| status | VARCHAR(20) | NOT NULL | draft / pending / paid / failed / refunded |
| due_date | DATE | NOT NULL | |
| paid_at | TIMESTAMP | NULLABLE | |
| pdf_url | TEXT | NULLABLE | DigitalOcean Spaces path |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    due_date DATE NOT NULL,
    paid_at TIMESTAMP,
    pdf_url TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `invoice_line_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| invoice_id | UUID | FK → invoices(id) ON DELETE CASCADE | |
| description | VARCHAR(255) | NOT NULL | e.g. "Professional Plan — Monthly" |
| quantity | INT | DEFAULT 1 | |
| unit_price | DECIMAL(10,2) | NOT NULL | |
| total | DECIMAL(10,2) | NOT NULL | |

```sql
CREATE TABLE invoice_line_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL
);
```

#### `subscription_discounts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| code | VARCHAR(50) | NOT NULL, UNIQUE | Promo code |
| type | VARCHAR(20) | NOT NULL | percentage / fixed |
| value | DECIMAL(10,2) | NOT NULL | % or SAR amount |
| max_uses | INT | NULLABLE | NULL = unlimited |
| times_used | INT | DEFAULT 0 | |
| valid_from | TIMESTAMP | NULLABLE | |
| valid_to | TIMESTAMP | NULLABLE | |
| applicable_plan_ids | JSONB | NULLABLE | Array of plan UUIDs; NULL = all plans |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE subscription_discounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    max_uses INT,
    times_used INT DEFAULT 0,
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    applicable_plan_ids JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `subscription_credits`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_subscription_id | UUID | FK → store_subscriptions(id) | |
| applied_by | UUID | FK → admin_users(id) | Admin who applied |
| amount | DECIMAL(10,2) | NOT NULL | SAR credit amount |
| reason | TEXT | NOT NULL | |
| applied_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE subscription_credits (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    applied_by UUID NOT NULL REFERENCES admin_users(id),
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    applied_at TIMESTAMP DEFAULT NOW()
);
```

#### `plan_add_ons`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | |
| name_ar | VARCHAR(100) | NOT NULL | |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | thawani_integration, white_label, api_access, accounting_export, reservation_system |
| monthly_price | DECIMAL(10,2) | NOT NULL | |
| description | TEXT | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE plan_add_ons (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    monthly_price DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `store_add_ons` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| store_id | UUID | FK → stores(id) ON DELETE CASCADE | |
| plan_add_on_id | UUID | FK → plan_add_ons(id) | |
| activated_at | TIMESTAMP | DEFAULT NOW() | |
| is_active | BOOLEAN | DEFAULT TRUE | |

```sql
CREATE TABLE store_add_ons (
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    plan_add_on_id UUID NOT NULL REFERENCES plan_add_ons(id),
    activated_at TIMESTAMP DEFAULT NOW(),
    is_active BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (store_id, plan_add_on_id)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `subscription_plans_slug_unique` | slug | UNIQUE | Lookup by slug |
| `subscription_plans_active_sort` | (is_active, sort_order) | B-TREE | Pricing page query |
| `plan_feature_toggles_plan_key` | (subscription_plan_id, feature_key) | UNIQUE | One toggle per feature per plan |
| `plan_limits_plan_key` | (subscription_plan_id, limit_key) | UNIQUE | One limit per key per plan |
| `store_subscriptions_org_unique` | organization_id | UNIQUE | One active per organization |
| `store_subscriptions_status` | status | B-TREE | Filter by status |
| `store_subscriptions_plan` | subscription_plan_id | B-TREE | Count subscribers per plan |
| `invoices_sub_due` | (store_subscription_id, due_date) | B-TREE | Upcoming renewals |
| `invoices_status` | status | B-TREE | Failed payment lookup |
| `subscription_discounts_code` | code | UNIQUE | Coupon lookup |
| `store_add_ons_pk` | (store_id, plan_add_on_id) | UNIQUE PK | Prevent duplicate add-on per store |

### 6.3 Relationships Diagram
```
subscription_plans ──1:N──▶ plan_feature_toggles
subscription_plans ──1:N──▶ plan_limits
subscription_plans ──1:N──▶ store_subscriptions
subscription_plans ──M:N──▶ themes  (via theme_package_visibility)
subscription_plans ──M:N──▶ pos_layout_templates  (via layout_package_visibility)
subscription_plans ──1:1──▶ custom_role_package_config

organizations ──1:1──▶ store_subscriptions
store_subscriptions ──1:N──▶ invoices
store_subscriptions ──1:N──▶ subscription_credits
invoices ──1:N──▶ invoice_line_items

stores ──M:N──▶ plan_add_ons  (via store_add_ons)
```

---

## 7. Feature Toggle Reference

Each feature toggle maps to a provider-side capability. When a toggle is disabled in the plan, the corresponding provider feature is gated.

| Feature Key | Provider Feature | Description |
|---|---|---|
| `kitchen_display` | [KDS Feature](../../provider/provider_features/kds_feature.md) | Kitchen display system for order preparation |
| `advanced_analytics` | [Reports & Analytics](../../provider/provider_features/reports_analytics_feature.md) | Advanced reporting and analytics |
| `custom_themes` | [POS Terminal](../../provider/provider_features/pos_terminal_feature.md) | Custom UI themes |
| `modifier_groups` | [Product & Catalog](../../provider/provider_features/product_catalog_management_feature.md) | Product modifiers and variants |
| `recipe_inventory` | [Inventory Management](../../provider/provider_features/inventory_management_feature.md) | Recipe-based inventory tracking |
| `employee_scheduling` | [Staff & User Management](../../provider/provider_features/staff_user_management_feature.md) | Shift scheduling and time tracking |
| `accounting_export` | [Accounting Integration](../../provider/provider_features/accounting_integration_feature.md) | QuickBooks/Xero/Qoyod integration |
| `tip_management` | [Payments & Finance](../../provider/provider_features/payments_finance_feature.md) | Tip pooling and distribution |
| `reservation_system` | [Order Management](../../provider/provider_features/order_management_feature.md) | Table/resource reservations |
| `loyalty_program` | [Customer Management](../../provider/provider_features/customer_management_feature.md) | Loyalty points and rewards |
| `advanced_coupons` | [Customer Management](../../provider/provider_features/customer_management_feature.md) | Complex coupon rules |
| `white_label` | [Store Settings](../../provider/provider_features/store_settings_feature.md) | Custom branding |
| `api_full_access` | All API Features | Full API access for integrations |
| `multi_branch` | [Multi-Branch](../../provider/provider_features/multi_branch_management_feature.md) | Multiple branch management |
| `inventory_expiry_tracking` | [Inventory Management](../../provider/provider_features/inventory_management_feature.md) | Product expiry tracking |
| `third_party_integrations` | [Delivery Integrations](../../provider/provider_features/delivery_integrations_feature.md) | Third-party delivery platform integrations |
| `custom_roles` | [Roles & Permissions](../../provider/provider_features/roles_permissions_feature.md) | Custom role creation |
| `customer_facing_display` | [Secondary Display](../../provider/provider_features/secondary_display_feature.md) | Customer-facing screen |
| `digital_signage` | Digital Signage | Menu board display |
| `appointment_booking` | [Appointments](../../provider/provider_features/appointments_feature.md) | Appointment scheduling |
| `gift_registry` | [Gift Registry](../../provider/provider_features/gift_registry_feature.md) | Gift registry functionality |
| `wishlist` | [Customer Management](../../provider/provider_features/customer_management_feature.md) | Customer wishlists |
| `loyalty_gamification` | [Gamification](../../provider/provider_features/gamification_feature.md) | Badges, challenges, milestones |

---

## 8. Business Rules

1. **Plan changes are immediate** — updating a plan's price/features/limits affects all current subscribers; existing invoices remain unchanged
2. **Trial → Active** — when trial ends, if payment method is set, auto-charge and transition to active; otherwise, transition to grace
3. **Grace → Expired** — after `grace_period_days`, subscription moves to expired; POS shows "subscription expired" and locks non-basic features
4. **Limits use max(plan_limit, override_limit)** — provider_limit_overrides take precedence when set and not expired
5. **Discount code validation** — check valid_from/valid_to, max_uses > times_used, applicable_plan_ids contains the target plan
6. **Invoice auto-generation** — a scheduled Laravel job runs daily, generates next-period invoices for subscriptions renewing within 3 days
7. **PDF generation** — invoice PDF is generated asynchronously via a queued job, stored in DigitalOcean Spaces, URL saved in `pdf_url`
8. **Feature gating** — every provider API endpoint and POS feature checks `Store::hasFeature()` and `Store::withinLimit()` before proceeding; returns 403 with upgrade prompt
9. **Add-ons are independent** — activated per store, billed separately on the monthly invoice alongside the plan subscription
10. **Cancellation flow** — provider must select a `cancellation_reason` category; admin can view aggregated churn reasons in analytics
