# Platform Roles — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Authentication, Authorisation & Role Management  
> **Tech Stack:** Laravel 11 + Filament v3 + Spatie Permission  

---

## 1. Feature Overview

Platform Roles govern **who on the Thawani internal team can do what** inside the Super Admin Panel. Every Filament page, every API endpoint, and every admin action is guarded by this feature. It is the security backbone of the entire platform scope.

### What This Feature Does
- Defines a fixed set of **system roles** (Super Admin, Platform Manager, Support Agent, Finance Admin, Integration Manager, Sales, Viewer) plus **custom roles**
- Provides a **granular permission catalogue** — each permission maps to a specific admin action (e.g. `stores.view`, `billing.edit`, `tickets.respond`)
- Links admin users to one or more roles via a many-to-many join
- Enforces role-based access on every Filament Resource, Page, and Widget
- Records a tamper-evident **audit trail** of every admin action with actor, IP, timestamp, entity affected, and change details

### Roles Defined

| Role | Slug | Key Access | is_system |
|---|---|---|---|
| **Super Admin** | `super_admin` | Full platform control — all stores, billing, infrastructure, settings, admin team management | ✅ |
| **Platform Manager** | `platform_manager` | Manage providers, approve registrations, assign packages, manage integrations | ✅ |
| **Support Agent** | `support_agent` | Read-only view of any provider account, respond to support tickets | ✅ |
| **Finance Admin** | `finance_admin` | Billing, subscriptions, invoices, package pricing, refunds | ✅ |
| **Integration Manager** | `integration_manager` | Create/edit third-party delivery platform configs, test connections | ✅ |
| **Sales** | `sales` | View store pipeline, create trial accounts, apply discounts within limits | ✅ |
| **Viewer** | `viewer` | Read-only dashboard and reports (stakeholders, investors) | ✅ |
| **Custom** | user-defined | Admin-defined permission combination — pick individual permissions from any category | ❌ |

---

## 2. Affected Features & Dependencies

Changing Platform Roles **directly impacts** nearly every other platform feature since access control is enforced globally.

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Provider Management** | Only users with `stores.view`, `stores.edit`, `stores.impersonate` can access |
| **Package & Subscription Management** | Gated by `billing.*` permissions |
| **Billing & Finance Admin** | Finance Admin role is the primary consumer |
| **Support Ticket System** | Support Agent role routes here; `tickets.*` permissions required |
| **Third-Party Delivery Platform Management** | Integration Manager role is gated by `integrations.*` |
| **Security & Audit** | Audit logs record the actor's role at time of action |
| **System Configuration** | Only Super Admin and users with `settings.*` permissions |
| **App & Update Management** | Release management gated to Super Admin / Platform Manager |
| **Platform Announcements** | Create/send requires `announcements.create` |
| **Analytics & Reporting** | Viewer role primary consumer; data filters may apply per role |
| **User Management (Cross-Store)** | `users.*` permissions for staff lookup |
| **Provider Roles & Permissions Management** | `roles.manage_provider_defaults` permission |

### Features to Review After Changing This Feature
1. **Every Filament Resource** — ensure `canAccess()` policy respects new/modified roles
2. **Security & Audit** — verify the audit log still captures the correct role name even after role renames
3. **Middleware & Policies** — all route-level and model-level authorization
4. **Provider Management → Impersonate** — ensure only designated roles can impersonate
5. **Billing** — ensure finance-only users cannot access non-billing pages

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Admin panel framework (Resources, Pages, Widgets, Navigation, Policies) |
| **spatie/laravel-permission** | Role & permission management with team support; used with guard `platform` |
| **filament/filament-shield** *(optional)* | Auto-generates Filament policies per Resource from Spatie permissions |
| **laravel/fortify** or **filament-breezy** | 2FA (TOTP) enforcement for admin logins |
| **pragmarx/google2fa-laravel** | TOTP token generation & verification |
| **laragear/two-factor** | Alternative 2FA package — recovery codes, enforceable per user |
| **spatie/laravel-activitylog** | Structured activity/audit logging with old/new value snapshots |

### 3.2 Technologies
- **Laravel 11** — framework
- **Filament v3** — admin panel UI
- **Livewire 3** — reactive components
- **Alpine.js** — minimal frontend JS
- **Tailwind CSS** — styling
- **PostgreSQL** — database
- **Redis** — session store, cache for permission lookups

---

## 4. Pages

### 4.1 Admin Team List
| Field | Detail |
|---|---|
| **Route** | `/admin/team` |
| **Filament Resource** | `AdminUserResource` |
| **Purpose** | List all Thawani internal admin accounts |
| **Table Columns** | Name, Email, Role(s) badge(s), Last Login, Status (active/disabled), Created At |
| **Filters** | Role, Status (active/disabled) |
| **Search** | By name, email |
| **Bulk Actions** | Deactivate selected |
| **Row Actions** | Edit, Deactivate/Activate, Reset Password, Revoke Sessions |
| **Access** | `super_admin` role only |

### 4.2 Admin Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/team/create` · `/admin/team/{id}/edit` |
| **Purpose** | Create or edit a platform admin account |
| **Form Fields** | Name, Email (unique), Phone, Password (create only), Avatar upload, Is Active toggle, Roles multi-select, Enforce 2FA toggle |
| **Validation** | Email unique in admin_users, Password min 12 chars with complexity |
| **Side Effects** | Creating user → sends email invite; Role change → audit logged; Deactivate → revokes all sessions |
| **Access** | `super_admin` only |

### 4.3 Admin Roles List
| Field | Detail |
|---|---|
| **Route** | `/admin/roles` |
| **Filament Resource** | `AdminRoleResource` |
| **Purpose** | List all platform roles (system + custom) |
| **Table Columns** | Name, Slug, Description, is_system badge, Users Count, Permissions Count |
| **Row Actions** | View Permissions, Edit (custom only), Delete (custom only, prevents if users assigned) |
| **Access** | `super_admin` |

### 4.4 Admin Role Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/roles/create` · `/admin/roles/{id}/edit` |
| **Purpose** | Create custom admin role or edit a custom role's permissions |
| **Form Fields** | Name, Slug (auto-generated from name), Description, Permissions checklist grouped by category (Stores, Billing, Tickets, Integrations, Settings, Analytics, Announcements, Users, App Updates) |
| **Validation** | Slug unique, cannot edit system role name/slug |
| **Side Effects** | Permission change → immediately applies to all users with this role; Audit logged |
| **Access** | `super_admin` |

### 4.5 Admin Permissions List (Reference View)
| Field | Detail |
|---|---|
| **Route** | `/admin/permissions` |
| **Purpose** | Reference-only list of all granular permissions, grouped by category |
| **Table Columns** | Name, Group, Description |
| **Actions** | None (read-only; permissions are seeded via migrations) |
| **Access** | `super_admin` |

### 4.6 Admin Activity Log
| Field | Detail |
|---|---|
| **Route** | `/admin/activity-log` |
| **Purpose** | View all admin actions with full detail |
| **Table Columns** | Timestamp, Admin Name, Role, Action, Entity Type, Entity ID, IP Address |
| **Filters** | Date range, Admin User, Action type, Entity type |
| **Detail View** | Expandable row showing `details` JSONB (old/new values) |
| **Export** | CSV / Excel export available |
| **Access** | `super_admin`, `viewer` (read-only) |

---

## 5. APIs

> The Super Admin Panel is primarily server-rendered via Filament (Livewire). Public-facing APIs are minimal. Internal Livewire endpoints handle CRUD.

### 5.1 Filament Internal Livewire Endpoints (auto-generated)
| Action | Endpoint Pattern | Method |
|---|---|---|
| List admin users | `livewire/admin-user-resource.list-records` | POST (Livewire) |
| Create admin user | `livewire/admin-user-resource.create-record` | POST |
| Update admin user | `livewire/admin-user-resource.edit-record` | POST |
| List roles | `livewire/admin-role-resource.list-records` | POST |
| CRUD on roles | `livewire/admin-role-resource.*` | POST |
| Activity log list | `livewire/admin-activity-log-resource.list-records` | POST |

### 5.2 Custom API Endpoints (if needed for external tooling)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/admin/me` | GET | Return current admin user profile + roles + permissions | Bearer token (admin scope) |
| `POST /api/admin/auth/login` | POST | Admin login (returns Sanctum token) | Public |
| `POST /api/admin/auth/2fa/verify` | POST | Verify TOTP code after password auth | Partial auth |
| `POST /api/admin/auth/logout` | POST | Destroy session / revoke token | Bearer token |

---

## 6. Full Database Schema

### 6.1 Tables

#### `admin_users`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK, DEFAULT gen_random_uuid() | |
| name | VARCHAR(255) | NOT NULL | |
| email | VARCHAR(255) | NOT NULL, UNIQUE | |
| password_hash | VARCHAR(255) | NOT NULL | Bcrypt or Argon2id |
| phone | VARCHAR(50) | NULLABLE | |
| avatar_url | TEXT | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| two_factor_secret | TEXT | NULLABLE, ENCRYPTED | TOTP secret |
| two_factor_enabled | BOOLEAN | DEFAULT FALSE | |
| two_factor_confirmed_at | TIMESTAMP | NULLABLE | |
| last_login_at | TIMESTAMP | NULLABLE | |
| last_login_ip | VARCHAR(45) | NULLABLE | IPv4/IPv6 |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE admin_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    avatar_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    two_factor_secret TEXT,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_confirmed_at TIMESTAMP,
    last_login_at TIMESTAMP,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `admin_roles`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | super_admin, platform_manager, etc. |
| description | TEXT | NULLABLE | |
| is_system | BOOLEAN | DEFAULT FALSE | System roles cannot be deleted |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE admin_roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `admin_permissions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL, UNIQUE | e.g. `stores.view`, `billing.edit` |
| group | VARCHAR(50) | NOT NULL | Stores, Billing, Tickets, Integrations, Settings, Analytics, Announcements, Users, App Updates |
| description | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE admin_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    "group" VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `admin_role_permissions` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| admin_role_id | UUID | FK → admin_roles(id) ON DELETE CASCADE | |
| admin_permission_id | UUID | FK → admin_permissions(id) ON DELETE CASCADE | |

```sql
CREATE TABLE admin_role_permissions (
    admin_role_id UUID NOT NULL REFERENCES admin_roles(id) ON DELETE CASCADE,
    admin_permission_id UUID NOT NULL REFERENCES admin_permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (admin_role_id, admin_permission_id)
);
```

#### `admin_user_roles` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| admin_user_id | UUID | FK → admin_users(id) ON DELETE CASCADE | |
| admin_role_id | UUID | FK → admin_roles(id) ON DELETE CASCADE | |
| assigned_at | TIMESTAMP | DEFAULT NOW() | |
| assigned_by | UUID | FK → admin_users(id) NULLABLE | Who assigned this role |

```sql
CREATE TABLE admin_user_roles (
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    admin_role_id UUID NOT NULL REFERENCES admin_roles(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT NOW(),
    assigned_by UUID REFERENCES admin_users(id),
    PRIMARY KEY (admin_user_id, admin_role_id)
);
```

#### `admin_activity_logs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| admin_user_id | UUID | FK → admin_users(id) SET NULL | |
| action | VARCHAR(100) | NOT NULL | e.g. `store.suspend`, `role.update`, `subscription.upgrade` |
| entity_type | VARCHAR(50) | NULLABLE | e.g. `store`, `admin_user`, `subscription_plan` |
| entity_id | UUID | NULLABLE | |
| details | JSONB | NULLABLE | Old/new values, extra context |
| ip_address | VARCHAR(45) | NOT NULL | |
| user_agent | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE admin_activity_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id) ON SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    details JSONB,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `admin_users_email_unique` | email | UNIQUE | Login lookup |
| `admin_roles_slug_unique` | slug | UNIQUE | Permission check |
| `admin_permissions_name_unique` | name | UNIQUE | Guard lookup |
| `admin_role_permissions_pk` | (admin_role_id, admin_permission_id) | UNIQUE PK | Prevent duplicate grants |
| `admin_user_roles_pk` | (admin_user_id, admin_role_id) | UNIQUE PK | Prevent duplicate assignment |
| `admin_activity_logs_user_date` | (admin_user_id, created_at) | B-TREE | Filter by user + date |
| `admin_activity_logs_entity` | (entity_type, entity_id) | B-TREE | Lookup all actions on one entity |
| `admin_activity_logs_action` | action | B-TREE | Filter by action type |

### 6.3 Relationships Diagram (text)
```
admin_users ──many-to-many──▶ admin_roles  (via admin_user_roles)
admin_roles ──many-to-many──▶ admin_permissions  (via admin_role_permissions)
admin_users ──one-to-many──▶ admin_activity_logs
```

### 6.4 Cross-References to Other Feature Tables
- `admin_users.id` is referenced as FK by: `provider_notes.admin_user_id`, `provider_limit_overrides.set_by`, `subscription_credits.applied_by`, `platform_announcements.created_by`, `admin_ip_allowlist.added_by`, `admin_ip_blocklist.blocked_by`, `security_alerts.admin_user_id`, `hardware_sales.sold_by`, `support_tickets.assigned_to`
- `admin_activity_logs` captures actions across **all platform features** — any admin write triggers a log entry

---

## 7. Permission Catalogue (Seed Data)

| Group | Permission Name | Description |
|---|---|---|
| **Stores** | `stores.view` | View store list and details |
| | `stores.edit` | Edit store settings and status |
| | `stores.create` | Manual store onboarding |
| | `stores.suspend` | Suspend/reactivate a store |
| | `stores.delete` | Permanently delete a store |
| | `stores.impersonate` | Login as store owner for support |
| | `stores.export` | Export store data |
| **Billing** | `billing.view` | View subscriptions and invoices |
| | `billing.edit` | Modify subscriptions, apply discounts/credits |
| | `billing.refund` | Process refunds |
| | `billing.invoices` | Generate and manage invoices |
| | `billing.plans` | Create/edit subscription plans |
| **Tickets** | `tickets.view` | View support tickets |
| | `tickets.respond` | Reply to tickets |
| | `tickets.assign` | Assign tickets to agents |
| | `tickets.manage` | Create/close/delete tickets |
| **Integrations** | `integrations.view` | View delivery platform configs |
| | `integrations.manage` | Create/edit/delete platform configs |
| | `integrations.test` | Test connectivity |
| **Settings** | `settings.view` | View system configuration |
| | `settings.edit` | Modify system settings |
| | `settings.feature_flags` | Manage feature flags |
| | `settings.maintenance` | Toggle maintenance mode |
| | `settings.credentials` | Manage encrypted third-party API credentials |
| | `settings.hardware_catalog` | Manage certified POS hardware compatibility catalog |
| | `settings.translations` | Manage master ARB translation strings and locales |
| | `settings.payment_methods` | Manage the payment method registry |
| | `settings.security_policies` | Manage platform-wide security policy defaults |
| **Analytics** | `analytics.view` | View platform dashboards and reports |
| | `analytics.export` | Export analytics data |
| **Announcements** | `announcements.view` | View announcements |
| | `announcements.create` | Create announcements |
| | `announcements.manage` | Create, edit, and delete announcements |
| | `announcements.send` | Send and dispatch announcement notifications |
| **Users** | `users.view` | View cross-store user list |
| | `users.manage` | Reset passwords, disable accounts |
| | `users.edit` | Enable or disable individual user accounts |
| | `users.reset_password` | Reset provider user passwords |
| **App Updates** | `app_updates.view` | View app versions and adoption stats |
| | `app_updates.manage` | Release new versions, adjust rollout |
| | `app_updates.rollback` | Deactivate a release and trigger rollback |
| **Admin Team** | `admin_team.view` | View admin users |
| | `admin_team.manage` | Create/edit/deactivate admin accounts |
| | `admin_team.roles` | Manage custom roles and permissions |
| **Roles (Provider)** | `provider_roles.view` | View provider permission master list |
| | `provider_roles.manage` | Edit default role templates |
| **Infrastructure** | `infrastructure.view` | View infrastructure dashboard and server health |
| | `infrastructure.manage` | Queue management, failed job retry, cache flush |
| | `infrastructure.backups` | View, trigger, and download database backups |
| **Content** | `content.view` | View business types and onboarding configuration |
| | `content.manage` | Manage business types, onboarding, and templates |
| | `content.pricing` | Manage public pricing page content |
| **Notifications** | `notifications.manage` | Manage notification templates for all channels |
| **UI / Layout** | `ui.manage` | Manage POS layout templates, themes, and receipt layouts |
| **Security** | `security.view` | View security dashboard and audit logs |
| | `security.manage_ips` | Manage IP allowlist and blocklist |
| | `security.manage_sessions` | View and revoke admin sessions |
| | `security.manage_alerts` | Investigate and resolve security alerts |
| **Knowledge Base** | `kb.manage` | Manage canned responses and knowledge base articles |

> **Total: 59 permissions** across 16 groups. The `super_admin` role always has all 59 permissions. See `permissions_seed.sql` for the complete seed data including role-permission assignments.

---

## 8. Business Rules

1. **System roles** (is_system = true) cannot be deleted or renamed via UI; their slug is seeded
2. **Super Admin** role always has all permissions — cannot be restricted
3. 2FA is **mandatory** for all admin accounts; login is blocked until 2FA is configured
4. Role changes take effect **immediately** — no session restart required (permissions cached in Redis, TTL 60s)
5. An admin user can hold **multiple roles** — effective permissions are the union of all assigned roles
6. Deleting a custom role that still has assigned users is **prevented** — users must be reassigned first
7. Every write action across the entire admin panel inserts a row into `admin_activity_logs`
8. Activity logs are **append-only** — no edit or delete capability
9. The initial Super Admin account is created via `php artisan db:seed --class=AdminSeeder` — never via UI
