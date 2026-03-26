# Security & Audit (Platform) — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Admin Authentication, 2FA, IP Allowlist/Blocklist, Device Trust, Session Management, Security Alerts, Audit Logging  
> **Tech Stack:** Laravel 11 + Filament v3 · Spatie Permission · Spatie ActivityLog · pragmarx/google2fa-laravel or laragear/two-factor  

---

## 1. Feature Overview

Security & Audit provides layered protection for the Thawani Super Admin Panel. It enforces two-factor authentication, restricts access by IP address, manages device trust, monitors sessions, raises alerts on suspicious behaviour, and maintains a tamper-evident audit chain of every admin action. The feature ensures compliance with internal security policy and builds the foundation for future PCI-DSS / PDPL requirements.

### What This Feature Does
- All admin actions logged: actor, role, IP, action, timestamp
- Role-based access control: support agents read-only; finance admins cannot impersonate
- Two-factor authentication enforced for all platform admin accounts
- IP allowlist for admin panel access
- IP blocklist management for known threat sources
- Suspicious activity alerts (brute-force logins, bulk data exports)
- Device trust management (trusted devices per admin account)
- Security event investigation and resolution workflow
- View and revoke active platform admin sessions
- Encrypted storage of all platform-level secrets and credentials

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Platform Roles** | Role assignment, permission checks, and all admin actions rely on security middleware (2FA gate, IP check, session validation) |
| **Provider Management** | Impersonation gated by role + 2FA re-prompt; every impersonation event audit-logged |
| **Billing & Finance** | Refund / credit actions require `billing.refund` permission, audit-logged at `high` risk level |
| **System Configuration** | Secret credential changes (gateway keys, SMS API keys) logged and encrypted |
| **All Other Platform Features** | Every Filament page served behind the security middleware stack |

### Features to Review After Changing This Feature
1. **Platform Roles** — permission names referenced by security middleware must stay in sync
2. **Notification Templates** — `security.*` event keys must match alert types defined here
3. **Infrastructure & Operations** — session store (Redis) and queue (alert dispatch) rely on infra health
4. **Provider Management** — impersonation guard logic couples to 2FA and audit

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for IP lists, sessions, security alerts, audit log viewer |
| **spatie/laravel-permission** | Guard `platform`; permissions like `security.view`, `security.manage_ips`, `security.manage_sessions` |
| **spatie/laravel-activitylog** | Core audit trail — structured log entries with `causer`, `subject`, `properties` |
| **pragmarx/google2fa-laravel** _or_ **laragear/two-factor** | TOTP-based two-factor authentication for admin accounts |
| **jenssegers/agent** (optional) | Parse user-agent for device trust display |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | Middleware pipeline (2FA gate, IP gate), encrypted model attributes, session driver |
| **Filament v3** | Admin UI — custom pages + Resources |
| **PostgreSQL** | Persistent storage for allowlists, blocklists, devices, sessions, alerts, audit logs |
| **Redis** | Session store backend, rate limiter store, alert dispatch queue |
| **DigitalOcean Spaces / Encrypted Disk** | Long-term audit log archive (if offloaded) |
| **Alpine.js** | Inline session revoke confirmations, device trust toggle |

### 3.3 Middleware Stack (order matters)
```
1. VerifyAdminIp          → reject if IP not in allowlist (when allowlist is non-empty)
2. RejectBlockedIp        → reject if IP in blocklist
3. Authenticate (Filament) → standard auth guard "platform"
4. Enforce2FA             → redirect to 2FA setup/verify if not completed
5. TrackAdminSession      → update admin_sessions.last_activity_at, log request
6. AuditRequest           → write admin_activity_logs entry for state-changing requests
```

---

## 4. Pages

### 4.1 Security Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/security` |
| **Purpose** | Overview of security posture |
| **Widgets** | Active sessions count card, Unresolved alerts count card, Failed login attempts (last 24h) stat, 2FA adoption rate stat (should be 100%), IP allowlist count, recent security events timeline (latest 10) |
| **Access** | `security.view` |

### 4.2 Audit Log
| Field | Detail |
|---|---|
| **Route** | `/admin/security/audit-log` |
| **Filament Resource** | `AdminActivityLogResource` (read-only) |
| **Table Columns** | Timestamp, Admin Name, Role(s), Action, Entity Type, Entity ID, IP Address, Details (truncated JSON) |
| **Filters** | Admin user, Action group (auth / billing / providers / tickets / settings / security), Entity type, Date range, Risk level |
| **Search** | By admin name, entity ID |
| **Row Actions** | View detail (full JSON properties) |
| **Export** | CSV / Excel (filtered set) |
| **Access** | `security.view` |

### 4.3 IP Allowlist
| Field | Detail |
|---|---|
| **Route** | `/admin/security/ip-allowlist` |
| **Filament Resource** | `AdminIpAllowlistResource` |
| **Table Columns** | IP Address, Label, Added By, Created At |
| **Row Actions** | Delete |
| **Create Form** | IP Address (IPv4 or IPv6, validated), Label |
| **Business Note** | When allowlist is empty, panel is open to all IPs. Adding first entry activates allowlist enforcement. |
| **Access** | `security.manage_ips` |

### 4.4 IP Blocklist
| Field | Detail |
|---|---|
| **Route** | `/admin/security/ip-blocklist` |
| **Filament Resource** | `AdminIpBlocklistResource` |
| **Table Columns** | IP Address, Reason, Blocked By, Created At |
| **Row Actions** | Delete (unblock) |
| **Create Form** | IP Address, Reason |
| **Access** | `security.manage_ips` |

### 4.5 Trusted Devices
| Field | Detail |
|---|---|
| **Route** | `/admin/security/trusted-devices` |
| **Filament Resource** | `AdminTrustedDeviceResource` (read-only for non-super-admin, manage for super admin) |
| **Table Columns** | Admin Name, Device Name, Device Fingerprint (truncated), User Agent, Trusted At, Last Used At |
| **Filters** | Admin user |
| **Row Actions** | Revoke Trust |
| **Access** | `security.manage_sessions` |

### 4.6 Active Sessions
| Field | Detail |
|---|---|
| **Route** | `/admin/security/sessions` |
| **Filament Resource** | `AdminSessionResource` (read-only) |
| **Table Columns** | Admin Name, IP Address, User Agent, Created At, Last Activity At, Status badge (active / expired / revoked) |
| **Filters** | Admin user, Status |
| **Row Actions** | Revoke Session |
| **Bulk Action** | Revoke all sessions for selected admin |
| **Access** | `security.manage_sessions` |

### 4.7 Security Alerts
| Field | Detail |
|---|---|
| **Route** | `/admin/security/alerts` |
| **Filament Resource** | `SecurityAlertResource` |
| **Table Columns** | Alert Type badge, Severity badge (low/medium/high/critical), Admin Name (if applicable), Details (truncated), Status badge (new/investigating/resolved), Created At |
| **Filters** | Alert type, Severity, Status |
| **Row Actions** | View Detail, Start Investigation (sets status → investigating), Resolve (requires resolution notes) |
| **Create** | N/A — alerts are system-generated |
| **Access** | `security.view` (resolve requires `security.manage_alerts`) |

### 4.8 Two-Factor Authentication Management
| Field | Detail |
|---|---|
| **Route** | `/admin/security/2fa` |
| **Purpose** | View 2FA status of all admins; force-reset if needed |
| **Table Columns** | Admin Name, Email, 2FA Enabled, Enabled At |
| **Row Actions** | Reset 2FA (clears secret, forces re-enrollment on next login) |
| **Access** | `security.manage_sessions` (Super Admin only for reset) |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD for IP lists, session/device management, alert status changes.

### 5.2 Custom Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /admin/security/2fa/verify` | POST | Verify TOTP code during login | Session (pre-2FA) |
| `POST /admin/security/2fa/setup` | POST | Generate QR code & secret for enrollment | Session (pre-2FA) |
| `POST /admin/security/sessions/{id}/revoke` | POST | Revoke a specific session | `security.manage_sessions` |
| `POST /admin/security/sessions/revoke-all/{admin_id}` | POST | Revoke all sessions for an admin | `security.manage_sessions` |

### 5.3 Internal Events
| Event | Dispatched When | Listeners |
|---|---|---|
| `AdminLoginSucceeded` | Successful login | Log audit, update session |
| `AdminLoginFailed` | Failed login attempt | Log audit, increment counter, check brute-force threshold |
| `Admin2FAVerified` | 2FA code accepted | Update session 2fa_verified flag |
| `SuspiciousActivityDetected` | Threshold exceeded (failed logins, bulk exports) | Create `security_alerts` row, dispatch notification to Super Admins |
| `AdminSessionRevoked` | Session manually revoked | Log audit, invalidate Redis session |

---

## 6. Full Database Schema

### 6.1 Tables

#### `admin_ip_allowlist`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| ip_address | VARCHAR(45) | NOT NULL, UNIQUE | IPv4 or IPv6 |
| label | VARCHAR(100) | NULLABLE | Human-readable label (e.g. "Office VPN") |
| added_by | UUID | FK → admin_users(id) | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE admin_ip_allowlist (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    label VARCHAR(100),
    added_by UUID NOT NULL REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `admin_ip_blocklist`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| ip_address | VARCHAR(45) | NOT NULL, UNIQUE | |
| reason | VARCHAR(255) | NULLABLE | |
| blocked_by | UUID | FK → admin_users(id) | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE admin_ip_blocklist (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    blocked_by UUID NOT NULL REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `admin_trusted_devices`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| admin_user_id | UUID | FK → admin_users(id) ON DELETE CASCADE | |
| device_fingerprint | VARCHAR(64) | NOT NULL | SHA-256 of browser/device characteristics |
| device_name | VARCHAR(100) | NULLABLE | Parsed from user agent or user-supplied |
| user_agent | TEXT | NULLABLE | Raw user agent string |
| trusted_at | TIMESTAMP | DEFAULT NOW() | |
| last_used_at | TIMESTAMP | NULLABLE | Updated on each request from device |

```sql
CREATE TABLE admin_trusted_devices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    device_fingerprint VARCHAR(64) NOT NULL,
    device_name VARCHAR(100),
    user_agent TEXT,
    trusted_at TIMESTAMP DEFAULT NOW(),
    last_used_at TIMESTAMP,
    UNIQUE (admin_user_id, device_fingerprint)
);
```

#### `admin_sessions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| admin_user_id | UUID | FK → admin_users(id) ON DELETE CASCADE | |
| session_token_hash | VARCHAR(64) | NOT NULL, UNIQUE | SHA-256 of session token; actual token in Redis |
| ip_address | VARCHAR(45) | NOT NULL | |
| user_agent | TEXT | NULLABLE | |
| two_fa_verified | BOOLEAN | DEFAULT FALSE | Set true after TOTP verified |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| last_activity_at | TIMESTAMP | DEFAULT NOW() | |
| expires_at | TIMESTAMP | NOT NULL | |
| revoked_at | TIMESTAMP | NULLABLE | Non-null = revoked |

```sql
CREATE TABLE admin_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    session_token_hash VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    two_fa_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    last_activity_at TIMESTAMP DEFAULT NOW(),
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP
);

CREATE INDEX idx_admin_sessions_user_revoked ON admin_sessions (admin_user_id, revoked_at);
```

#### `security_alerts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| admin_user_id | UUID | FK → admin_users(id), NULLABLE | Null for IP-level alerts with no identified user |
| alert_type | VARCHAR(50) | NOT NULL | brute_force / bulk_export / unusual_ip / permission_escalation / after_hours_access |
| severity | VARCHAR(20) | NOT NULL | low / medium / high / critical |
| details | JSONB | NULLABLE | Context: `{"failed_attempts":12,"ip":"…","window_minutes":5}` |
| status | VARCHAR(20) | NOT NULL DEFAULT 'new' | new / investigating / resolved |
| resolved_at | TIMESTAMP | NULLABLE | |
| resolved_by | UUID | FK → admin_users(id), NULLABLE | |
| resolution_notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE security_alerts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id),
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    details JSONB,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    resolved_at TIMESTAMP,
    resolved_by UUID REFERENCES admin_users(id),
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_security_alerts_type_status ON security_alerts (alert_type, status, created_at);
```

### 6.2 Related Tables (defined in other features)
- `admin_users` — account records with `two_factor_secret`, `two_factor_enabled`, `last_login_at`, `last_login_ip` columns
- `admin_activity_logs` — full audit trail (defined in Platform Roles feature)

### 6.3 Indexes Summary

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `admin_ip_allowlist_ip` | ip_address | UNIQUE | Fast lookup during middleware check |
| `admin_ip_blocklist_ip` | ip_address | UNIQUE | Fast rejection check |
| `admin_trusted_devices_user_fp` | (admin_user_id, device_fingerprint) | UNIQUE | One trust entry per device per admin |
| `admin_sessions_user_revoked` | (admin_user_id, revoked_at) | B-TREE | List active sessions per admin |
| `admin_sessions_token_hash` | session_token_hash | UNIQUE | Session validation on every request |
| `security_alerts_type_status` | (alert_type, status, created_at) | B-TREE | Alert dashboard filtering |

---

## 7. Business Rules

1. **2FA is mandatory** — every `admin_users` account must complete TOTP enrollment before accessing any Filament page; the `Enforce2FA` middleware redirects to setup if `two_factor_enabled = false`
2. **IP allowlist activation** — the `VerifyAdminIp` middleware is a no-op when `admin_ip_allowlist` is empty; adding the first entry activates enforcement; always allow the IP of the admin who adds the first entry
3. **Blocklist takes precedence** — `RejectBlockedIp` runs before allowlist check; a blocked IP is rejected even if also in the allowlist
4. **Brute-force detection** — 5 failed login attempts from the same IP within 5 minutes triggers a `brute_force` security alert (severity = `high`) and temporarily blocks the IP for 15 minutes via cache
5. **Bulk export alert** — exporting > 500 records from any list page within 10 minutes triggers a `bulk_export` alert (severity = `medium`)
6. **Unusual IP alert** — login from an IP not seen in the admin's previous 30 sessions triggers an `unusual_ip` alert (severity = `low`) and prompts device trust confirmation
7. **Session lifetime** — sessions expire after 8 hours; inactivity timeout is 30 minutes (configurable in System Configuration)
8. **Revoke cascades** — revoking a session deletes the corresponding Redis key immediately, logging the admin out in real-time
9. **Device trust** — when an admin trusts a device, subsequent logins from that device skip the unusual-IP alert; trust can be revoked at any time
10. **Secret encryption** — all credential fields across the platform (`payment_gateway_configs.credentials_encrypted`, SMS API keys, etc.) use Laravel `encrypt()` with `APP_KEY`; rotation requires re-encryption migration
11. **Audit immutability** — `admin_activity_logs` rows are insert-only; no update or delete operations are allowed (enforced by Filament Resource being read-only and DB trigger or policy)
12. **Retention** — audit logs retained for 2 years minimum; older logs can be archived to DigitalOcean Spaces cold storage via scheduled job
