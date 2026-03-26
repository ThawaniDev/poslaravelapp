# Provider Management — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Store / Chain Lifecycle Administration  
> **Tech Stack:** Laravel 11 + Filament v3 · Spatie Permission · Livewire 3  

---

## 1. Feature Overview

Provider Management is the **core operational hub** of the Super Admin Panel. It lets the Thawani team view, approve, configure, monitor, and support every store and chain that subscribes to the POS platform.

### What This Feature Does
- Displays a searchable, filterable **master list** of all organizations, stores, and branches
- Drives the **registration approval workflow** — new provider signups arrive as pending requests for admin review
- Enables **impersonation** — support agents can log in as a store owner to troubleshoot
- Shows **live usage metrics** per store (cashiers used, products, terminals, orders, sync status, ZATCA compliance)
- Provides **internal notes** per provider for CRM-like context
- Offers **limit override** capability — temporarily raise hard caps per store without changing the plan
- Tracks **cancellation reasons** when a provider churns
- Allows **manual onboarding** — admin creates a store on behalf of a provider who calls in

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Package & Subscription Management** | Subscription is assigned per store; changing plan happens from store detail |
| **Billing & Finance Admin** | Invoices, payment status are visible from store profile |
| **Support Ticket System** | Tickets are linked to organization + store — support agents navigate here first |
| **Platform Analytics** | Analytics drill-down links to individual store pages |
| **Security & Audit** | Impersonate triggers an audit log entry |
| **User Management** | Staff list per store is viewed from store detail |
| **Provider Roles & Permissions** | Custom roles and permission usage visible per provider |

### Provider-Side Tables Read by This Feature
This feature **reads** many provider-scope tables in read-only mode:
- `organizations`, `stores`, `users`, `registers`
- `transactions`, `orders`, `products`, `inventory`
- `pos_sessions`, `store_delivery_platforms`, `delivery_sync_logs`
- `zatca_device_config`, `zatca_invoices`
- `time_clock_entries`, `commission_rules`, `tip_pools`
- `store_pos_settings`, `roles`, `role_permissions`
- `store_accounting_configs`, `accounting_exports` (for accounting integration status)

### Features to Review After Changing This Feature
1. **Package & Subscription** — plan assignment flow is embedded in store edit
2. **Support Tickets** — store link in ticket detail must still resolve
3. **Analytics** — store drill-down cards rely on store detail page URLs
4. **Impersonate** — any auth changes may break impersonate flow
5. **Audit Logs** — all provider management actions generate audit entries

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resource for Stores, Organisations; custom pages; widgets |
| **spatie/laravel-permission** | Gate checks for `stores.view`, `stores.edit`, `stores.impersonate`, etc. |
| **spatie/laravel-activitylog** | Auto-log every create/update/delete on Store and Organisation models |
| **lab404/laravel-impersonate** or custom | Impersonate provider user session securely |
| **maatwebsite/laravel-excel** | Export store data to CSV/Excel |
| **filament/tables** | Advanced data tables with search, sort, filter |

### 3.2 Technologies
- **Laravel 11** — controllers, policies, form requests
- **Filament v3** — Resources, RelationManagers, InfoLists, Widgets
- **Livewire 3** — real-time search, live metric refresh
- **PostgreSQL** — multi-table joins for store profile view
- **Redis** — cache store metrics (5-min TTL) to avoid heavy queries on every page load

---

## 4. Pages

### 4.1 Store / Organisation List
| Field | Detail |
|---|---|
| **Route** | `/admin/stores` |
| **Filament Resource** | `StoreResource` |
| **Purpose** | Master list of all stores/chains |
| **Table Columns** | Store Name, Organisation, Owner Email, Plan Badge, Status (active/suspended/trial), Branches #, Cashiers #, Last Sync, Created At |
| **Filters** | Status (active/suspended/trial/cancelled), Plan, Business Type, ZATCA Compliant (yes/no), Has Delivery Platforms, Region/City |
| **Search** | By store name, owner email, phone, CR number, tax number |
| **Bulk Actions** | Suspend selected, Export selected |
| **Row Actions** | View, Edit, Impersonate, Suspend/Activate |
| **Access** | `stores.view` permission |

### 4.2 Store Detail (View)
| Field | Detail |
|---|---|
| **Route** | `/admin/stores/{id}` |
| **Purpose** | Full store profile — read-only dashboard |
| **Sections** | |
| — Owner Info | Name, email, phone, organisation name, CR number, VAT number |
| — Subscription | Plan name, status badge, billing cycle, current period dates, payment method |
| — Usage Meters | Cashiers (used/limit), Products (used/limit), Terminals (used/limit), Branches (used/limit), Delivery platforms (used/limit) |
| — POS Terminals | List of registers: device_id, app_version, last_sync_at, online/offline badge |
| — ZATCA Status | Device compliance status, last invoice date, any pending clearances |
| — Transaction History | Recent 50 transactions with expandable line items (links to full list) |
| — Delivery Integrations | Which platforms are active, sync status per platform |
| — Staff Overview | Employee count by role, scheduling/tip/commission config summary |
| — Internal Notes | Timeline of admin notes |
| — Accounting Integration | Connected/disconnected badge per provider (see [accounting_integration_feature.md](../../provider/provider_features/accounting_integration_feature.md) for provider-side connection flow). Displays: provider name (QuickBooks/Xero/Qoyod), connection date, last export date, sync health status |
| **Widgets** | Revenue trend (last 30 days), Order count chart |
| **Access** | `stores.view` |

### 4.3 Store Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/stores/{id}/edit` |
| **Purpose** | Edit store settings from admin side |
| **Form Fields** | Is Active toggle, Subscription Plan select, Business Type select, ZATCA environment select (sandbox/production), Notes text area |
| **Side Effects** | Status change → audit logged; Plan change → subscription updated; Deactivation → all POS sessions force-closed |
| **Access** | `stores.edit` |

### 4.4 Store Create (Manual Onboarding)
| Field | Detail |
|---|---|
| **Route** | `/admin/stores/create` |
| **Purpose** | Admin creates a store on behalf of a provider who cannot self-register |
| **Form Fields** | Organisation Name (EN/AR), Owner Name, Owner Email, Phone, CR Number, VAT Number, Business Type, Subscription Plan, Skip Trial toggle |
| **Side Effects** | Creates organisation + store + owner user; sends welcome email with password-set link; audit logged |
| **Access** | `stores.create` |

### 4.5 Provider Registration Queue
| Field | Detail |
|---|---|
| **Route** | `/admin/registrations` |
| **Purpose** | Pending signup requests |
| **Table Columns** | Organisation Name, Owner Email, Phone, Submitted At, Status badge |
| **Filters** | Status (pending/approved/rejected) |
| **Row Actions** | Approve, Reject (with rejection reason modal) |
| **Side Effects** | Approve → creates org + store + owner user; sends approval email. Reject → sends rejection email with reason |
| **Access** | `stores.edit` (Platform Manager+) |

### 4.6 Impersonate
| Field | Detail |
|---|---|
| **Route** | `/admin/stores/{id}/impersonate` |
| **Purpose** | Login as the store owner to troubleshoot their POS/dashboard |
| **Behaviour** | Opens the provider portal in a new tab with the admin logged in as the owner user. A banner "Impersonating: {store_name}" is shown. Click "End Impersonation" to return. Original admin session is preserved |
| **Audit** | Start and end of impersonation are logged with admin_user_id, store_id, timestamps |
| **Access** | `stores.impersonate` (Support Agent + Super Admin only) |

### 4.7 Store Data Export
| Field | Detail |
|---|---|
| **Route** | Triggered via action on store list or detail |
| **Exports** | Products CSV, Transactions CSV, Inventory CSV, Customers CSV |
| **Delivery** | Queued Laravel job → downloadable link in notifications |
| **Access** | `stores.export` |

---

## 5. APIs

### 5.1 Filament Internal (Livewire)
All CRUD operations on `StoreResource`, `OrganisationResource`, `ProviderRegistrationResource` are handled via Livewire round-trips. No external REST API for admin store management.

### 5.2 Custom Endpoints (internal use)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /admin/api/stores/{id}/impersonate` | POST | Start impersonation session | Admin Bearer token + `stores.impersonate` |
| `POST /admin/api/stores/{id}/suspend` | POST | Suspend store | Admin Bearer + `stores.suspend` |
| `POST /admin/api/stores/{id}/activate` | POST | Reactivate store | Admin Bearer + `stores.suspend` |
| `GET /admin/api/stores/{id}/metrics` | GET | Live usage metrics JSON | Admin Bearer + `stores.view` |
| `POST /admin/api/stores/{id}/export` | POST | Queue data export | Admin Bearer + `stores.export` |
| `POST /admin/api/registrations/{id}/approve` | POST | Approve registration | Admin Bearer + `stores.edit` |
| `POST /admin/api/registrations/{id}/reject` | POST | Reject with reason | Admin Bearer + `stores.edit` |

---

## 6. Full Database Schema

### 6.1 Tables

#### `provider_registrations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_name | VARCHAR(255) | NOT NULL | Name from signup form |
| organization_name_ar | VARCHAR(255) | NULLABLE | |
| owner_name | VARCHAR(255) | NOT NULL | |
| owner_email | VARCHAR(255) | NOT NULL | |
| owner_phone | VARCHAR(50) | NOT NULL | |
| cr_number | VARCHAR(50) | NULLABLE | Commercial registration |
| vat_number | VARCHAR(50) | NULLABLE | |
| business_type_id | UUID | FK → business_types(id) NULLABLE | |
| status | VARCHAR(20) | NOT NULL, DEFAULT 'pending' | pending/approved/rejected |
| reviewed_by | UUID | FK → admin_users(id) NULLABLE | |
| reviewed_at | TIMESTAMP | NULLABLE | |
| rejection_reason | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE provider_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_name VARCHAR(255) NOT NULL,
    organization_name_ar VARCHAR(255),
    owner_name VARCHAR(255) NOT NULL,
    owner_email VARCHAR(255) NOT NULL,
    owner_phone VARCHAR(50) NOT NULL,
    cr_number VARCHAR(50),
    vat_number VARCHAR(50),
    business_type_id UUID REFERENCES business_types(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by UUID REFERENCES admin_users(id),
    reviewed_at TIMESTAMP,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `provider_notes`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id) ON DELETE CASCADE | |
| admin_user_id | UUID | FK → admin_users(id) | Author |
| note_text | TEXT | NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE provider_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    admin_user_id UUID NOT NULL REFERENCES admin_users(id),
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `provider_limit_overrides`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) ON DELETE CASCADE | |
| limit_key | VARCHAR(50) | NOT NULL | max_cashiers, max_products, max_terminals, max_branches, max_delivery_platforms |
| override_value | INT | NOT NULL | The new limit value |
| reason | TEXT | NULLABLE | Admin note why override was granted |
| set_by | UUID | FK → admin_users(id) | |
| expires_at | TIMESTAMP | NULLABLE | NULL = permanent |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE provider_limit_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    limit_key VARCHAR(50) NOT NULL,
    override_value INT NOT NULL,
    reason TEXT,
    set_by UUID NOT NULL REFERENCES admin_users(id),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, limit_key)
);
```

#### `cancellation_reasons`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_subscription_id | UUID | FK → store_subscriptions(id) | |
| reason_category | VARCHAR(30) | NOT NULL | price/features/competitor/support/other |
| reason_text | TEXT | NULLABLE | Free-form detail |
| cancelled_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE cancellation_reasons (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    reason_category VARCHAR(30) NOT NULL,
    reason_text TEXT,
    cancelled_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Shared Tables Read by This Feature
| Table | From Scope | Used For |
|---|---|---|
| `organizations` | Shared | Organisation profile |
| `stores` | Shared | Store list, detail |
| `users` | Provider | Staff counts, owner info |
| `registers` | Provider | Terminal status |
| `store_subscriptions` | Shared | Plan + billing status |
| `transactions` | Provider | Transaction history view |
| `orders` | Provider | Order counts |
| `products` | Provider | Product count metric |
| `store_delivery_platforms` | Provider | Which platforms enabled |
| `zatca_device_config` | Provider | ZATCA compliance |
| `time_clock_entries` | Provider | Scheduling metrics |
| `commission_rules` | Provider | Commission config view |
| `tip_pools` | Provider | Tip config view |

### 6.3 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `provider_registrations_status` | status | B-TREE | Filter pending queue |
| `provider_registrations_email` | owner_email | B-TREE | Lookup by email |
| `provider_notes_org` | organization_id | B-TREE | Notes per provider |
| `provider_limit_overrides_unique` | (store_id, limit_key) | UNIQUE | One override per limit per store |
| `cancellation_reasons_sub` | store_subscription_id | B-TREE | Lookup by subscription |

### 6.4 Relationships Diagram
```
admin_users ──1:N──▶ provider_notes
admin_users ──1:N──▶ provider_limit_overrides (set_by)
admin_users ──1:N──▶ provider_registrations (reviewed_by)
organizations ──1:N──▶ provider_notes
stores ──1:N──▶ provider_limit_overrides
store_subscriptions ──1:1──▶ cancellation_reasons
business_types ──1:N──▶ provider_registrations
```

---

## 7. Business Rules

1. **Registration auto-expiry** — pending registrations older than 30 days are auto-rejected with a system note
2. **Impersonate is time-limited** — session auto-expires after 30 minutes; can be extended once
3. **Suspension cascade** — suspending a store disables all POS login for that store's users; the desktop app shows a "Your account has been suspended" screen
4. **Limit overrides** respect expiration — a scheduled Laravel command (`CheckExpiredOverrides`) removes expired overrides nightly
5. **Cancellation reason is mandatory** — the cancellation flow forces selection of a category before confirming
6. **Data export is async** — queued as a Laravel job; admin receives a downloadable link via notification
7. **All actions audit-logged** — approve, reject, suspend, activate, impersonate, note add, limit override, plan change
