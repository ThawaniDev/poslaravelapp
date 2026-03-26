# Provider Roles & Permissions Management — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Master Permission List, Default Role Templates, Custom-Role Gating, Permission Seeding  
> **Tech Stack:** Laravel 11 + Filament v3 · Spatie Permission · Spatie ActivityLog  

---

## 1. Feature Overview

Provider Roles & Permissions Management controls the authorisation model that every provider organisation inherits. The platform team defines the **master list of provider-side permissions** (e.g. `pos.sell`, `pos.void`, `inventory.adjust`), curates **default role templates** (Owner, Chain Manager, Branch Manager, Cashier, etc.) with preset permission sets, and gates the **custom role** capability by package tier. When a new store is created, the default templates are copied into the provider's own role tables, giving them an out-of-the-box RBAC setup that they can optionally customise (if their plan allows).

### What This Feature Does
- Define the master list of provider-side permissions available across the platform
- Manage default role templates shipped to every new provider (Owner, Chain Manager, Branch Manager, Cashier, Inventory Clerk, Accountant, Kitchen Staff)
- Edit permission sets of default role templates — changes apply to **all new stores** (existing stores keep their current configuration)
- Custom Role feature flag: enable/disable the ability for providers to create custom roles (gated by package tier)
- Set maximum number of custom roles per package tier
- View which providers have created custom roles and their permissions
- Audit log of all role and permission changes across providers

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Package & Subscription Mgmt** | `custom_role_package_config` determines whether a plan allows custom roles and how many |
| **Provider Management** | New store creation triggers seeding of default roles from templates defined here |
| **User Management** | Provider users are assigned roles whose permissions originate from this feature |
| **POS Layout Mgmt** | Some layout actions (e.g. void, discount) are gated by permissions defined here |
| **Analytics & Reporting** | Feature adoption stats track custom-role creation rates |

### Features to Review After Changing This Feature
1. **Package & Subscription** — changing `custom_role_package_config` affects provider plan enforcement
2. **Provider Management** — new store seeding logic must stay in sync with default template changes
3. **User Management** — if a permission is renamed or removed, provider-side role_permissions may reference orphaned permission names
4. **POS client (Flutter)** — permission keys are hard-coded in the POS app; adding new permissions requires app update coordination

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for Provider Permissions, Default Role Templates, Custom Role Config |
| **spatie/laravel-permission** | Platform-side guard (`platform`) for admin access; provider permissions seeded from `provider_permissions` table |
| **spatie/laravel-activitylog** | Audit trail for template changes |
| **maatwebsite/laravel-excel** | Export permission matrix |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | Seeders (initial permission + template data), Observers (store creation → seed roles), Eloquent |
| **Filament v3** | Admin UI |
| **PostgreSQL** | Platform-level permission/template tables |
| **Redis** | Cache of default templates used during store creation (invalidated on template save) |

### 3.3 Seeding Flow on Store Creation
```
1. Store created (Provider Registration approved or manual creation)
2. StoreCreated event dispatched
3. SeedDefaultRoles listener:
   a. Read all default_role_templates + default_role_template_permissions
   b. For each template → INSERT into provider-side `roles` table (scoped to organization)
   c. For each template-permission → INSERT into provider-side `role_permissions`
4. Owner role automatically assigned to the registering user
```

---

## 4. Pages

### 4.1 Provider Permissions List
| Field | Detail |
|---|---|
| **Route** | `/admin/provider-roles/permissions` |
| **Filament Resource** | `ProviderPermissionResource` |
| **Table Columns** | Permission Name (e.g. `pos.sell`), Group (POS / Inventory / Reports / Settings / Staff / Finance / Kitchen), Description |
| **Filters** | Group |
| **Search** | By name or description |
| **Row Actions** | Edit (description only; name immutable after first use), Deactivate (soft-hide from new templates) |
| **Header Actions** | Create Permission |
| **Access** | `provider_roles.manage` |

### 4.2 Create / Edit Provider Permission
| Field | Detail |
|---|---|
| **Route** | `/admin/provider-roles/permissions/create` or `/{id}/edit` |
| **Form Fields** | Name (slug format, e.g. `pos.void` — immutable after creation), Group (select), Description (EN / AR) |
| **Validation** | Name must be unique, lowercase, dot-separated |
| **Access** | `provider_roles.manage` |

### 4.3 Default Role Templates List
| Field | Detail |
|---|---|
| **Route** | `/admin/provider-roles/templates` |
| **Filament Resource** | `DefaultRoleTemplateResource` |
| **Table Columns** | Role Name, Slug, Description, Permissions Count, Updated At |
| **Row Actions** | Edit |
| **Access** | `provider_roles.manage` |

### 4.4 Edit Default Role Template
| Field | Detail |
|---|---|
| **Route** | `/admin/provider-roles/templates/{id}/edit` |
| **Form Fields** | Name (EN / AR, displayed to providers), Slug (read-only), Description (EN / AR) |
| **Relation Manager** | Permissions checklist grouped by permission group; checkboxes for each `provider_permissions` row |
| **Save Behaviour** | Syncs `default_role_template_permissions` pivot. Changes affect **new stores only**; banner warns admin of this. |
| **Access** | `provider_roles.manage` |

### 4.5 Permission Matrix View
| Field | Detail |
|---|---|
| **Route** | `/admin/provider-roles/matrix` |
| **Purpose** | Cross-tab view: rows = permissions, columns = default role templates; cells = ✓ / — |
| **Export** | Download as Excel |
| **Access** | `provider_roles.manage` |

### 4.6 Custom Role Package Config
| Field | Detail |
|---|---|
| **Route** | `/admin/provider-roles/custom-config` |
| **Filament Resource** | `CustomRolePackageConfigResource` |
| **Table Columns** | Plan Name, Is Custom Roles Enabled badge, Max Custom Roles |
| **Row Actions** | Edit |
| **Form Fields** | Subscription Plan (read-only), Is Custom Roles Enabled toggle, Max Custom Roles (int) |
| **Access** | `provider_roles.manage` |

### 4.7 Provider Custom Roles Audit
| Field | Detail |
|---|---|
| **Route** | `/admin/provider-roles/custom-audit` |
| **Purpose** | View custom roles created by providers across the platform |
| **Table Columns** | Organization Name, Store Name, Custom Role Name, Permissions (comma-separated), Created At |
| **Filters** | Organization, Plan |
| **Note** | Read-only — platform cannot edit provider custom roles directly |
| **Access** | `provider_roles.manage` |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
CRUD for permissions, templates, config.

### 5.2 Provider-Facing APIs
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/roles/permissions` | GET | List all available permissions for custom role creation (filtered by plan) | Store API token |
| `GET /api/v1/roles/defaults` | GET | List default role templates and their permissions | Store API token |
| `GET /api/v1/roles/custom-limits` | GET | Returns `is_custom_roles_enabled` and `max_custom_roles` for store's plan | Store API token |

### 5.3 Internal Events
| Event | Dispatched When | Listeners |
|---|---|---|
| `StoreCreated` | New store created | `SeedDefaultRoles` — copies templates into provider-side tables |
| `DefaultRoleTemplateUpdated` | Admin edits a template | Audit log, invalidate Redis template cache |
| `ProviderPermissionCreated` | New permission added | Audit log |

---

## 6. Full Database Schema

### 6.1 Tables

#### `provider_permissions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(50) | NOT NULL, UNIQUE | e.g. `pos.sell`, `pos.void`, `inventory.adjust` |
| group | VARCHAR(30) | NOT NULL | POS / Inventory / Reports / Settings / Staff / Finance / Kitchen |
| description | VARCHAR(255) | NULLABLE | EN |
| description_ar | VARCHAR(255) | NULLABLE | AR |
| is_active | BOOLEAN | DEFAULT TRUE | Deactivated permissions hidden from new templates |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE provider_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL UNIQUE,
    "group" VARCHAR(30) NOT NULL,
    description VARCHAR(255),
    description_ar VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_provider_permissions_group ON provider_permissions ("group");
```

#### `default_role_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(50) | NOT NULL | EN display name |
| name_ar | VARCHAR(50) | NULLABLE | AR display name |
| slug | VARCHAR(30) | NOT NULL, UNIQUE | owner / chain_manager / branch_manager / cashier / inventory_clerk / accountant / kitchen_staff |
| description | VARCHAR(255) | NULLABLE | |
| description_ar | VARCHAR(255) | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE default_role_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL,
    name_ar VARCHAR(50),
    slug VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255),
    description_ar VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `default_role_template_permissions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| default_role_template_id | UUID | FK → default_role_templates(id) ON DELETE CASCADE | |
| provider_permission_id | UUID | FK → provider_permissions(id) ON DELETE CASCADE | |

```sql
CREATE TABLE default_role_template_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    default_role_template_id UUID NOT NULL REFERENCES default_role_templates(id) ON DELETE CASCADE,
    provider_permission_id UUID NOT NULL REFERENCES provider_permissions(id) ON DELETE CASCADE,
    UNIQUE (default_role_template_id, provider_permission_id)
);
```

#### `custom_role_package_config`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| subscription_plan_id | UUID | FK → subscription_plans(id), UNIQUE | One config per plan |
| is_custom_roles_enabled | BOOLEAN | DEFAULT FALSE | |
| max_custom_roles | INT | NOT NULL DEFAULT 0 | 0 if disabled |

```sql
CREATE TABLE custom_role_package_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL UNIQUE REFERENCES subscription_plans(id),
    is_custom_roles_enabled BOOLEAN DEFAULT FALSE,
    max_custom_roles INT NOT NULL DEFAULT 0
);
```

### 6.2 Provider-Side Tables (cross-reference only — defined in provider DB)
- `roles` — provider's active roles (seeded from `default_role_templates`, plus any custom roles)
- `role_permissions` — permissions per role (seeded from `default_role_template_permissions`)
- `user_roles` — users assigned to roles

### 6.3 Indexes Summary

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `provider_permissions_name` | name | UNIQUE | Permission lookup |
| `provider_permissions_group` | group | B-TREE | Group-based filtering |
| `default_role_templates_slug` | slug | UNIQUE | Template identification |
| `default_role_template_permissions_composite` | (default_role_template_id, provider_permission_id) | UNIQUE | Prevent duplicate assignments |
| `custom_role_package_config_plan` | subscription_plan_id | UNIQUE | One config per plan |

---

## 7. Business Rules

1. **Permission immutability** — once a permission `name` is created and used in any template or provider role, it cannot be renamed; it can only be deactivated (hidden from new templates)
2. **Template changes are prospective** — editing a default role template's permissions only affects stores created **after** the change; existing stores keep their current role configurations
3. **Owner role is mandatory** — the `owner` default role template always includes all permissions and cannot be deleted or have permissions removed
4. **Custom role gating** — providers can only create custom roles if their plan has `is_custom_roles_enabled = true` in `custom_role_package_config`; the POS API checks this before allowing role creation
5. **Max custom roles** — enforced via check: `count(roles WHERE is_custom = true AND organization_id = ?) < max_custom_roles`; attempt to exceed returns 403
6. **Permission groups** — groups are predefined (POS, Inventory, Reports, Settings, Staff, Finance, Kitchen); adding a new group requires developer involvement (UI select list)
7. **Seeding on store creation** — `SeedDefaultRoles` listener runs synchronously within the `StoreCreated` event transaction; failure rolls back store creation
8. **Template cache** — default templates are cached in Redis (10-minute TTL); cache cleared on any template edit event
9. **Audit** — every template permission change (add/remove) is logged with old and new permission sets in `admin_activity_logs`
10. **Cross-platform consistency** — the `provider_permissions.name` values are also hard-coded as enum values in the Flutter Desktop POS app; platform permission additions require coordinated app update
