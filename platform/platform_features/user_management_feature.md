# User Management (Cross-Store) — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Provider Staff Search, Password Reset, Account Control, Admin Team Management  
> **Tech Stack:** Laravel 11 + Filament v3 · Spatie Permission · Spatie ActivityLog  

---

## 1. Feature Overview

User Management gives the Thawani platform team a unified view of every user account in the system — both provider staff (cashiers, managers, owners) and platform admins. It enables cross-store user lookup, password resets, account enable/disable, activity log review, and Super Admin team management. The feature does not introduce its own tables; it queries the shared `users` table (provider-side accounts) and `admin_users` (platform accounts), enriched with data from `organizations`, `stores`, and audit logs.

### What This Feature Does
- List all users across all stores with search by email / phone
- Reset passwords / force password change
- Disable / enable individual user accounts
- View user activity logs
- Super admin team management (invite, assign roles, deactivate)

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Platform Roles** | Admin user CRUD is performed here; role assignment links to Platform Roles feature |
| **Security & Audit** | User account disable/enable, password resets, and admin invites are security-sensitive and audit-logged |
| **Provider Management** | Provider store detail → staff tab links to individual user records here |
| **Support Ticket System** | Ticket submitter identity looked up via user records |

### Features to Review After Changing This Feature
1. **Platform Roles** — admin_users schema and role assignment must stay in sync
2. **Security & Audit** — password reset and account disable emit security events
3. **Provider Management** — staff count per store is derived from the same `users` table

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for Users (provider-side) and AdminUsers (platform-side) |
| **spatie/laravel-permission** | Permissions: `users.view`, `users.edit`, `users.reset_password`, `admin_users.manage` |
| **spatie/laravel-activitylog** | Audit trail for all user management actions |
| **lab404/laravel-impersonate** | Impersonate a provider user for debugging (links from user record) |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | Eloquent (User, AdminUser), Password broker, Hash facade, Notifications |
| **Filament v3** | Admin UI — two separate Resources |
| **PostgreSQL** | `users` + `admin_users` tables |
| **Redis** | Password reset token storage (Laravel default), session invalidation on disable |

---

## 4. Pages

### 4.1 Provider Users List
| Field | Detail |
|---|---|
| **Route** | `/admin/users/provider-users` |
| **Filament Resource** | `ProviderUserResource` (read-heavy, limited edit) |
| **Table Columns** | Name, Email, Phone, Organization, Store(s), Role(s), Is Active badge, Last Login At |
| **Filters** | Organization, Store, Role, Is Active, Created date range |
| **Search** | By name, email, phone (global cross-store search) |
| **Row Actions** | View Detail, Reset Password, Force Password Change, Disable / Enable, Impersonate, View Activity |
| **Bulk Actions** | Disable selected, Enable selected |
| **Access** | `users.view` |

### 4.2 Provider User Detail
| Field | Detail |
|---|---|
| **Route** | `/admin/users/provider-users/{id}` |
| **Sections** | Profile info (name, email, phone, avatar), Organization & Store memberships table, Assigned roles table, Recent activity timeline (last 50 audit entries), Current sessions (if trackable) |
| **Actions** | Reset Password, Force Password Change, Disable / Enable, Impersonate |
| **Access** | `users.view` (password reset requires `users.reset_password`, impersonate requires `providers.impersonate`) |

### 4.3 Admin Users List (Platform Team)
| Field | Detail |
|---|---|
| **Route** | `/admin/users/admin-users` |
| **Filament Resource** | `AdminUserResource` |
| **Table Columns** | Name, Email, Role(s), Is Active badge, 2FA Enabled badge, Last Login At, Last Login IP |
| **Row Actions** | Edit, Deactivate, Reset 2FA |
| **Header Actions** | Invite Admin |
| **Access** | `admin_users.manage` (Super Admin) |

### 4.4 Invite / Create Admin User
| Field | Detail |
|---|---|
| **Route** | `/admin/users/admin-users/create` |
| **Form Fields** | Name, Email, Role(s) multi-select, Is Active toggle |
| **Behaviour** | On save: creates `admin_users` row with random temporary password, sends invite email with password-set link |
| **Access** | `admin_users.manage` |

### 4.5 Edit Admin User
| Field | Detail |
|---|---|
| **Route** | `/admin/users/admin-users/{id}/edit` |
| **Form Fields** | Name, Email (read-only after creation), Role(s) multi-select, Is Active toggle |
| **Access** | `admin_users.manage` |

### 4.6 User Activity Log
| Field | Detail |
|---|---|
| **Route** | `/admin/users/provider-users/{id}/activity` or `/admin/users/admin-users/{id}/activity` |
| **Purpose** | Filtered view of audit log for a specific user |
| **Table Columns** | Timestamp, Action, Entity Type, Entity ID, IP Address, Details |
| **Access** | `users.view` (provider users) or `admin_users.manage` (admin users) |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
CRUD + bulk actions for both Resources.

### 5.2 Custom Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /admin/users/provider-users/{id}/reset-password` | POST | Generate password reset link, send to user's email | `users.reset_password` |
| `POST /admin/users/provider-users/{id}/force-password-change` | POST | Set `must_change_password = true`; user forced to change on next login | `users.reset_password` |
| `POST /admin/users/provider-users/{id}/toggle-active` | POST | Enable or disable account | `users.edit` |
| `POST /admin/users/admin-users/{id}/reset-2fa` | POST | Clear 2FA secret; admin must re-enroll | `admin_users.manage` |

### 5.3 Internal Events
| Event | Dispatched When | Listeners |
|---|---|---|
| `UserPasswordReset` | Password reset link sent | Audit log |
| `UserAccountDisabled` | Account disabled by admin | Audit log, invalidate all sessions (Redis), notify user via email |
| `UserAccountEnabled` | Account re-enabled | Audit log, notify user |
| `AdminInvited` | New admin user created + invite sent | Audit log |

---

## 6. Full Database Schema

> This feature does **not** introduce new tables. It operates on shared tables defined in other features.

### 6.1 Tables Referenced

#### `users` (provider-side, shared)
| Column | Type | Notes |
|---|---|---|
| id | UUID | PK |
| name | VARCHAR(100) | |
| email | VARCHAR(255) | UNIQUE |
| phone | VARCHAR(20) | NULLABLE |
| password | VARCHAR(255) | Bcrypt hash |
| organization_id | UUID | FK → organizations(id) |
| is_active | BOOLEAN | DEFAULT TRUE |
| must_change_password | BOOLEAN | DEFAULT FALSE |
| last_login_at | TIMESTAMP | |
| last_login_ip | VARCHAR(45) | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

#### `admin_users` (platform-side, defined in Platform Roles)
| Column | Type | Notes |
|---|---|---|
| id | UUID | PK |
| name | VARCHAR(100) | |
| email | VARCHAR(255) | UNIQUE |
| password_hash | VARCHAR(255) | |
| is_active | BOOLEAN | DEFAULT TRUE |
| two_factor_secret | TEXT | Encrypted |
| two_factor_enabled | BOOLEAN | DEFAULT FALSE |
| last_login_at | TIMESTAMP | |
| last_login_ip | VARCHAR(45) | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

#### Join tables used
- `admin_user_roles` — roles per admin user
- `model_has_roles` / `model_has_permissions` (Spatie) — if using Spatie for provider-side roles
- `admin_activity_logs` — audit trail

### 6.2 Key Query Patterns

```sql
-- Cross-store user search by email or phone
SELECT u.*, o.name AS org_name, s.name AS store_name
FROM users u
JOIN organizations o ON o.id = u.organization_id
LEFT JOIN store_users su ON su.user_id = u.id
LEFT JOIN stores s ON s.id = su.store_id
WHERE u.email ILIKE '%search%' OR u.phone ILIKE '%search%'
ORDER BY u.created_at DESC
LIMIT 50;

-- Admin user list with roles
SELECT au.*, STRING_AGG(ar.name, ', ') AS role_names
FROM admin_users au
LEFT JOIN admin_user_roles aur ON aur.admin_user_id = au.id
LEFT JOIN admin_roles ar ON ar.id = aur.admin_role_id
GROUP BY au.id
ORDER BY au.name;

-- User activity log
SELECT * FROM admin_activity_logs
WHERE causer_type = 'App\\Models\\User' AND causer_id = :user_id
ORDER BY created_at DESC
LIMIT 50;
```

---

## 7. Business Rules

1. **Cross-store visibility** — platform admins see all users across all organizations; no store-level scoping applies
2. **Password reset** — sends a standard Laravel password reset email; token expires in 60 minutes
3. **Force password change** — sets `users.must_change_password = true`; POS client and provider portal check this flag on login and redirect to change-password screen
4. **Account disable** — setting `is_active = false` immediately invalidates all active sessions (Redis flush) and revokes API tokens; the user cannot log in until re-enabled
5. **Admin invite** — creates account with temporary random password; invite email contains a set-password link; the admin must also enroll in 2FA on first real login
6. **Impersonate guard** — impersonating a provider user requires both `providers.impersonate` permission and a 2FA re-prompt; every impersonation start and stop is audit-logged with the admin's IP
7. **Self-edit restriction** — admin users cannot deactivate their own account or remove their own Super Admin role
8. **Bulk disable** — limited to 50 users per batch to prevent accidental mass lockout; confirmation modal shows count and requires typing "CONFIRM"
9. **Search performance** — `users.email` and `users.phone` are indexed; ILIKE search may use trigram index (`pg_trgm`) for partial matching at scale
10. **Audit retention** — user management actions logged in `admin_activity_logs` with `entity_type = 'user'` or `entity_type = 'admin_user'`
