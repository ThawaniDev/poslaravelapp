# Roles & Permissions — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** RBAC — Role-Based Access Control, PIN Protection, Feature Gates  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11 · spatie/laravel-permission  

---

## 1. Feature Overview

The Roles & Permissions system controls what each staff member can see and do within the POS application and the Store Owner Web Dashboard. Every screen, button, and API endpoint is gated by a permission check. Roles are customizable per store, with a set of predefined roles (Owner, Branch Manager, Cashier, Inventory Clerk) that can be extended.

### What This Feature Does
- **Predefined roles** — Owner, Branch Manager, Cashier, Inventory Clerk, Accountant, Viewer — each with sensible default permission sets
- **Custom roles** — store owner can create custom roles with hand-picked permissions
- **Granular permissions** — organized by module (POS, Inventory, Finance, Reports, Settings) with view / create / edit / delete granularity
- **PIN-protected actions** — sensitive actions (void, refund, discount > threshold, drawer open) require manager PIN override
- **Time-limited permissions** — temporary permission elevation (e.g., "Allow cashier to give discounts for 1 hour")
- **Multi-store role assignment** — one user can have different roles in different branches
- **Permission inheritance** — higher roles inherit all permissions from lower roles
- **Audit trail** — every permission change and PIN override is logged

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Staff & User Management** | Users to whom roles are assigned |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Action gating (void, refund, discount, drawer open) |
| **Inventory Management** | Access to stock adjustments, transfers, purchase orders |
| **Payments & Finance** | Cash session access, expense management |
| **Reports & Analytics** | Report visibility per role |
| **Promotions & Coupons** | Promotion creation/editing permission |
| **Customer Management** | Customer data access level |
| **POS Interface Customization** | Settings access |
| **Notifications** | Notification routing based on role |
| **Store Owner Web Dashboard** | Dashboard access level |
| **All Features** | Every feature checks permissions before action |

### Features to Review After Changing This Feature
1. **Every feature** — permission constant names must stay consistent
2. **POS Terminal** — PIN override flow for sensitive actions
3. **Staff & User Management** — role assignment UI

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **spatie/laravel-permission** | Laravel RBAC — roles, permissions, model traits |
| **drift** | SQLite ORM — local role and permission caching for offline POS |
| **riverpod** / **flutter_bloc** | State management for permission checks in Flutter UI |
| **flutter_secure_storage** | Secure PIN storage and hashed PIN verification |

### 3.2 Technologies
- **spatie/laravel-permission** — industry-standard Laravel RBAC package; stores roles and permissions in DB with cache
- **Guard-based scoping** — separate guard for `staff` vs `owner` authentication context
- **Policy classes** — Laravel Policy classes per model for authorization
- **Flutter `PermissionGuard` widget** — wrapper widget that hides/disables UI elements based on current user's permissions
- **Offline permission cache** — full permission matrix synced to local SQLite; POS never calls API for permission checks during operation

---

## 4. Screens

### 4.1 Roles List Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/roles` |
| **Purpose** | View and manage all roles for the store |
| **Layout** | Card-per-role: role name, user count, quick permission summary (icons for granted module groups) |
| **Actions** | Add New Role, Edit Role (opens 4.2), Delete Role (only if no users assigned), Duplicate Role |
| **Predefined Roles** | Marked with lock icon — cannot delete, can only customize permissions |
| **Access** | `roles.view` permission (typically Owner, Branch Manager) |

### 4.2 Role Editor Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/roles/{id}/edit` |
| **Purpose** | Edit permissions for a role |
| **Layout** | Two-column: left = permission tree (collapsible modules), right = role details (name, description) |
| **Permission Tree** | Module headers (POS, Inventory, Finance, Reports, Settings) with children (e.g., POS → Void Transaction, Apply Discount, Open Drawer). Checkboxes per permission. Select-all per module. |
| **PIN Override Section** | Which actions require manager PIN when performed by this role |
| **Access** | `roles.edit` permission |

### 4.3 PIN Override Dialog
| Field | Detail |
|---|---|
| **Route** | Modal overlay (triggered by action button) |
| **Purpose** | Manager enters PIN to authorize a restricted action for a cashier |
| **Layout** | 4-6 digit PIN pad; action description shown; authorizing manager name shown after verification |
| **Timeout** | If no PIN entered in 30 seconds, dialog auto-dismisses |
| **Access** | Triggered when user lacking permission attempts a PIN-protectable action |

### 4.4 Permission Audit Log Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/roles/audit` |
| **Purpose** | View history of permission changes and PIN overrides |
| **Layout** | Data table — timestamp, user, action (role changed / PIN override), details, affected role or action |
| **Filters** | Date range, User, Action type |
| **Access** | `roles.audit` permission (Owner only by default) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/roles` | GET | List all roles for store | Bearer token, `roles.view` |
| `POST /api/roles` | POST | Create custom role | Bearer token, `roles.create` |
| `GET /api/roles/{id}` | GET | Get role with permissions | Bearer token, `roles.view` |
| `PUT /api/roles/{id}` | PUT | Update role permissions | Bearer token, `roles.edit` |
| `DELETE /api/roles/{id}` | DELETE | Delete custom role | Bearer token, `roles.delete` |
| `POST /api/roles/{id}/duplicate` | POST | Duplicate a role | Bearer token, `roles.create` |
| `GET /api/permissions` | GET | List all available permissions grouped by module | Bearer token |
| `POST /api/pin-override` | POST | Verify manager PIN for override | Bearer token |
| `GET /api/roles/audit-log` | GET | Permission change and PIN override log | Bearer token, `roles.audit` |
| `GET /api/sync/roles-permissions` | GET | Full role-permission matrix for offline sync | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `PermissionService` | Central permission checker — `hasPermission(String code)`, `canPerform(String action)` |
| `RoleRepository` | Local Drift storage for roles and permissions; synced from API |
| `PinOverrideService` | Shows PIN dialog, verifies PIN locally (hashed), logs override |
| `PermissionGuardWidget` | Flutter widget that conditionally renders children based on permission |
| `RoleSyncService` | Syncs roles and permissions from Laravel to local SQLite |

---

## 6. Full Database Schema

### 6.1 Tables

#### `roles` (managed by spatie/laravel-permission)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | BIGINT | PK, AUTO_INCREMENT | |
| store_id | UUID | FK → stores(id), NOT NULL | Scoped to store |
| name | VARCHAR(125) | NOT NULL | e.g., "cashier", "branch_manager" |
| display_name | VARCHAR(255) | NOT NULL | Human-readable name |
| guard_name | VARCHAR(125) | NOT NULL | "staff" |
| is_predefined | BOOLEAN | DEFAULT FALSE | True for system-created roles |
| description | TEXT | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

```sql
CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(125) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(125) NOT NULL DEFAULT 'staff',
    is_predefined BOOLEAN DEFAULT FALSE,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, name, guard_name)
);
```

#### `permissions` (managed by spatie/laravel-permission)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | BIGINT | PK, AUTO_INCREMENT | |
| name | VARCHAR(125) | NOT NULL, UNIQUE | e.g., "pos.void_transaction" |
| display_name | VARCHAR(255) | NOT NULL | Human-readable |
| module | VARCHAR(50) | NOT NULL | Module grouping: pos, inventory, finance, reports, settings |
| guard_name | VARCHAR(125) | NOT NULL | "staff" |
| requires_pin | BOOLEAN | DEFAULT FALSE | Whether this action can require PIN override |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

```sql
CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(125) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    module VARCHAR(50) NOT NULL,
    guard_name VARCHAR(125) NOT NULL DEFAULT 'staff',
    requires_pin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `role_has_permissions` (pivot — spatie)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| permission_id | BIGINT | FK → permissions(id) | |
| role_id | BIGINT | FK → roles(id) | |

```sql
CREATE TABLE role_has_permissions (
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);
```

#### `model_has_roles` (pivot — spatie)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| role_id | BIGINT | FK → roles(id) | |
| model_type | VARCHAR(255) | NOT NULL | "App\\Models\\StaffUser" |
| model_id | UUID | NOT NULL | Staff user ID |

```sql
CREATE TABLE model_has_roles (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    model_type VARCHAR(255) NOT NULL,
    model_id UUID NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type)
);
```

#### `pin_overrides`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| requesting_user_id | UUID | FK → users(id), NOT NULL | Cashier who triggered the action |
| authorizing_user_id | UUID | FK → users(id), NOT NULL | Manager who entered PIN |
| permission_code | VARCHAR(125) | NOT NULL | Permission that was overridden |
| action_context | JSONB | NULLABLE | { "order_id": "...", "discount_pct": 25 } |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE pin_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    requesting_user_id UUID NOT NULL REFERENCES users(id),
    authorizing_user_id UUID NOT NULL REFERENCES users(id),
    permission_code VARCHAR(125) NOT NULL,
    action_context JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `role_audit_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| user_id | UUID | FK → users(id), NOT NULL | Who made the change |
| action | VARCHAR(50) | NOT NULL | role_created, role_updated, permission_granted, permission_revoked |
| role_id | BIGINT | FK → roles(id), NULLABLE | |
| details | JSONB | NULLABLE | What changed |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE role_audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_id UUID NOT NULL REFERENCES users(id),
    action VARCHAR(50) NOT NULL,
    role_id BIGINT REFERENCES roles(id),
    details JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `roles_store_guard` | (store_id, guard_name) | B-TREE | List roles per store |
| `permissions_module` | module | B-TREE | Group permissions by module |
| `model_has_roles_model` | (model_type, model_id) | B-TREE | Find roles for a user |
| `pin_overrides_store_date` | (store_id, created_at) | B-TREE | PIN override audit |
| `role_audit_store_date` | (store_id, created_at) | B-TREE | Audit log queries |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ roles
roles ──M:N──▶ permissions (via role_has_permissions)
users ──M:N──▶ roles (via model_has_roles)
users ──1:N──▶ pin_overrides (requesting)
users ──1:N──▶ pin_overrides (authorizing)
stores ──1:N──▶ role_audit_log
```

---

## 7. Business Rules

1. **Predefined roles cannot be deleted** — Owner, Branch Manager, Cashier, Inventory Clerk, Accountant, Viewer are seeded on store creation and marked `is_predefined = true`
2. **Owner role is immutable in permissions** — Owner always has all permissions; cannot be restricted
3. **Minimum one Owner** — every store must have at least one user with the Owner role; the system prevents removing the last Owner
4. **Custom role deletion** — a custom role can only be deleted if no staff members are currently assigned to it
5. **PIN hash algorithm** — PINs are stored as bcrypt hashes; comparison is done locally on POS for offline compatibility
6. **PIN cooldown** — after 5 consecutive failed PIN attempts, the PIN override is locked for 5 minutes
7. **Permission inheritance** — granting a module-level permission (e.g., `inventory.*`) automatically grants all child permissions
8. **Role sync on login** — when a staff member logs into POS, the full permission matrix is synced from cloud to local SQLite
9. **Session-scoped permission cache** — permissions are cached in memory for the duration of the POS session; changes require re-login or manual sync
10. **Temporary permission elevation** — a manager can grant temporary permission to a cashier; the elevation automatically expires after the configured duration (default 1 hour)
11. **PIN override logging** — every PIN override is logged with the requesting user, authorizing user, permission code, and action context; these logs are immutable
12. **Permission constants** — all permission codes follow the pattern `{module}.{action}` (e.g., `pos.void_transaction`, `inventory.adjust_stock`, `finance.view_reports`)
