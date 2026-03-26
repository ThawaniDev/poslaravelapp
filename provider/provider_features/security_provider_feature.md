# Security (Provider) — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Laravel Backend + Cloud Infrastructure)  
> **Module:** Data Encryption, PCI Compliance, Authentication, Session Management, Audit Trails, Device Security  
> **Tech Stack:** Flutter 3.x Desktop · flutter_secure_storage · pointycastle · Laravel 11 · Sanctum · PostgreSQL  

---

## 1. Feature Overview

Security is a cross-cutting concern that permeates every feature of the POS system. This document consolidates all security mechanisms, policies, and technical implementations that protect store data, customer information, payment card data, and system integrity. The POS handles sensitive financial transactions and must comply with PCI DSS for card payments, protect personal data per Oman and Saudi Arabia data protection regulations, and ensure that only authorized users perform authorized actions.

### What This Feature Covers
- **Authentication** — staff login via PIN, owner login via password + 2FA, biometric unlock
- **Authorization** — role-based access control; PIN overrides for manager-level actions
- **Data encryption at rest** — local SQLite database encryption, secure credential storage
- **Data encryption in transit** — TLS 1.3 for all API communication, certificate pinning
- **PCI DSS compliance** — card data never stored; NearPay SDK handles card processing in a PCI-compliant manner
- **Session management** — session timeouts, screen lock, shift-based sessions
- **Audit trails** — every security event is logged (login, logout, override, delete, settings change)
- **Device security** — device registration, remote wipe capability, hardware identifiers
- **API security** — rate limiting, token-based auth, request signing, input validation
- **Backup encryption** — AES-256-GCM for backup files

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Hardware Support** | Biometric scanner for biometric auth |
| **Offline/Online Sync** | Token refresh and certificate validation when online |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **ALL Features** | Every feature relies on authentication, authorization, and encryption |
| **POS Terminal** | Screen lock, staff login, shift PIN |
| **Roles & Permissions** | Permission enforcement, PIN overrides |
| **Staff Management** | Staff authentication, activity logging |
| **Payments & Finance** | PCI compliance, payment data handling |
| **ZATCA Compliance** | Private key security, certificate management |
| **Backup & Recovery** | Backup encryption |
| **Mobile Companion App** | API authentication, session tokens |
| **Store Owner Web Dashboard** | Web session security, CSRF protection |

### Features to Review After Changing This Feature
1. **ALL features** — security changes have system-wide impact
2. **POS Terminal** — login flow and screen lock directly affected
3. **Roles & Permissions** — permission enforcement mechanisms

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **flutter_secure_storage** | Secure storage for tokens, PINs, private keys (Windows DPAPI / macOS Keychain) |
| **pointycastle** | Cryptographic operations: AES-256-GCM, ECDSA, SHA-256, HMAC |
| **crypto** | SHA-256 hashing for PIN verification, integrity checks |
| **encrypt** | AES encryption helper for database encryption |
| **local_auth** | Biometric authentication (fingerprint / face) |
| **dio** | HTTP client with certificate pinning support |
| **device_info_plus** | Collect device hardware identifiers for device registration |
| **package_info_plus** | App version for security audit logs |
| **sqflite_sqlcipher** | SQLCipher-based SQLite encryption (alternative to drift encryption) |
| **laravel/sanctum** | API token authentication (Laravel backend) |

### 3.2 Technologies
- **AES-256-GCM** — symmetric encryption for backup files, local secrets, and encrypted database fields
- **ECDSA (secp256k1)** — asymmetric signing for ZATCA invoices and request signing
- **SHA-256** — hashing for PIN storage, invoice chain, integrity verification
- **bcrypt** — password hashing for owner/admin accounts (Laravel default)
- **Argon2id** — password hashing alternative for higher-security scenarios
- **TLS 1.3** — transport layer encryption for all API calls
- **Certificate pinning** — prevent MITM attacks by pinning the server certificate
- **SQLCipher** — transparent AES-256 encryption of the entire local SQLite database
- **DPAPI (Windows)** — Windows Data Protection API for secure credential storage via `flutter_secure_storage`
- **CSRF tokens** — Laravel automatic CSRF protection for web dashboard
- **CORS** — Cross-Origin Resource Sharing configured for allowed origins only
- **Rate limiting** — Laravel rate limiting middleware (throttle) on sensitive endpoints
- **JWT / Sanctum tokens** — stateless API authentication for mobile and POS clients

---

## 4. Screens

### 4.1 Staff PIN Login Screen
| Field | Detail |
|---|---|
| **Route** | `/login` (main entry point) |
| **Purpose** | Staff authentication at POS terminal |
| **Layout** | Store logo, PIN pad (4-6 digit numeric), Staff avatar grid (quick select), Biometric prompt button. Clock In / Start Shift CTA |
| **Security** | PIN is hashed with SHA-256 + per-user salt before comparison. 5 failed attempts locks the account for 15 minutes. Brute force protection: exponential backoff |
| **Access** | Public (unauthenticated) |

### 4.2 Owner Login Screen
| Field | Detail |
|---|---|
| **Route** | `/owner/login` |
| **Purpose** | Owner/admin access to POS settings and management |
| **Layout** | Username/email + password. 2FA code input (TOTP — Google Authenticator). "Remember this device" (30-day device token) |
| **Security** | Passwords: bcrypt with cost 12. 2FA: TOTP (RFC 6238) with 30-second window. Brute force: account locks after 10 failed attempts |

### 4.3 Screen Lock (Session Timeout)
| Field | Detail |
|---|---|
| **Route** | `/lock` (overlay) |
| **Purpose** | Lock POS after inactivity or manual lock |
| **Layout** | Blurred background with PIN pad overlay. Staff name displayed. "Switch User" button. Biometric prompt |
| **Trigger** | Auto-lock after configurable timeout (default: 2 minutes inactivity). Manual lock via Ctrl+L or lock button |

### 4.4 PIN Override Dialog
| Field | Detail |
|---|---|
| **Route** | Modal dialog (not a separate route) |
| **Purpose** | Manager/owner PIN required for restricted actions |
| **Layout** | "Manager authorization required" header. Action description. PIN pad. Manager name displayed after successful entry |
| **Triggers** | Price override, void transaction, return without receipt, discount above threshold, cash drawer open, settings access |

### 4.5 Security Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/security` |
| **Purpose** | Configure security policies |
| **Layout** | PIN policy (min length, complexity), Auto-lock timeout, Session duration, 2FA settings, Device list (registered POS devices), Audit log export |
| **Actions** | Update PIN policy, Enable/disable 2FA, Deregister device, Remote wipe, Export audit log |
| **Access** | Owner only |

### 4.6 Audit Log Viewer
| Field | Detail |
|---|---|
| **Route** | `/settings/security/audit-log` |
| **Purpose** | View security event history |
| **Layout** | Filterable table: Timestamp, User, Action, Details, IP/Device, Status (success/fail). Color-coded severity: green (info), yellow (warning), red (critical) |
| **Actions** | Filter by user/action/date, Export CSV, Search |
| **Access** | Owner only |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/v2/auth/login` | POST | Staff/owner authentication (PIN or password) | Public |
| `POST /api/v2/auth/verify-2fa` | POST | Verify TOTP 2FA code | Pending-2FA |
| `POST /api/v2/auth/refresh-token` | POST | Refresh bearer token | Sanctum |
| `POST /api/v2/auth/logout` | POST | Invalidate session/token | Sanctum |
| `POST /api/v2/auth/register-device` | POST | Register POS device with hardware ID | Sanctum, Owner |
| `DELETE /api/v2/auth/devices/{id}` | DELETE | Deregister device | Sanctum, Owner |
| `POST /api/v2/auth/remote-wipe/{deviceId}` | POST | Issue remote wipe command | Sanctum, Owner |
| `GET /api/v2/auth/devices` | GET | List registered devices | Sanctum, Owner |
| `POST /api/v2/auth/change-pin` | POST | Change staff PIN | Sanctum |
| `POST /api/v2/auth/change-password` | POST | Change owner password | Sanctum, Owner |
| `POST /api/v2/auth/enable-2fa` | POST | Enable 2FA; returns QR code for TOTP setup | Sanctum, Owner |
| `POST /api/v2/auth/disable-2fa` | POST | Disable 2FA (requires current 2FA code) | Sanctum, Owner |
| `GET /api/v2/security/audit-log` | GET | Paginated security audit log | Sanctum, Owner |
| `POST /api/v2/security/verify-pin-override` | POST | Validate manager PIN for override actions | Sanctum |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `AuthenticationService` | Login, logout, token management, 2FA flow |
| `PinAuthService` | Local PIN verification (hash comparison); PIN change; brute force tracking |
| `BiometricAuthService` | Fingerprint/face authentication via `local_auth` |
| `SessionManager` | Session lifecycle: start, timeout, lock, unlock, shift tracking |
| `ScreenLockService` | Auto-lock timer, manual lock/unlock, lock screen overlay |
| `SecureStorageService` | Abstraction over `flutter_secure_storage`; store/retrieve tokens, keys, PINs |
| `EncryptionService` | AES-256-GCM encrypt/decrypt for backup files and sensitive local fields |
| `DeviceRegistrationService` | Collect hardware ID, register device with backend, handle remote wipe commands |
| `CertificatePinningService` | Configure Dio HTTP client with pinned server certificates |
| `AuditLogService` | Log security events locally and sync to cloud; query local audit log |
| `PinOverrideService` | Display PIN override dialog, validate manager PIN, log override event |

---

## 6. Full Database Schema

### 6.1 Tables

> **Note:** Many security-related columns exist within other feature tables (e.g., `staff_users.pin_hash`, `staff_users.is_active`). This section documents tables DEDICATED to security that are NOT defined elsewhere.

#### `device_registrations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| device_name | VARCHAR(100) | NOT NULL | User-facing name (e.g., "Counter 1 POS") |
| hardware_id | VARCHAR(200) | NOT NULL | Unique device hardware fingerprint |
| os_info | VARCHAR(100) | NULLABLE | e.g., "Windows 11 22H2" |
| app_version | VARCHAR(20) | NULLABLE | |
| last_active_at | TIMESTAMP | NULLABLE | |
| is_active | BOOLEAN | DEFAULT true | |
| remote_wipe_requested | BOOLEAN | DEFAULT false | Set to true to trigger remote wipe |
| registered_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE device_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    device_name VARCHAR(100) NOT NULL,
    hardware_id VARCHAR(200) NOT NULL,
    os_info VARCHAR(100),
    app_version VARCHAR(20),
    last_active_at TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    remote_wipe_requested BOOLEAN DEFAULT false,
    registered_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, hardware_id)
);
```

#### `security_audit_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| user_id | UUID | NULLABLE | NULL for system events |
| user_type | VARCHAR(20) | NULLABLE | staff, owner, system |
| action | VARCHAR(50) | NOT NULL | login, logout, pin_override, failed_login, settings_change, remote_wipe, etc. |
| resource_type | VARCHAR(50) | NULLABLE | order, product, staff_user, settings, etc. |
| resource_id | UUID | NULLABLE | |
| details | JSONB | DEFAULT '{}' | Additional context (IP, device, old/new values) |
| severity | VARCHAR(10) | DEFAULT 'info' | info, warning, critical |
| ip_address | VARCHAR(45) | NULLABLE | IPv4 or IPv6 |
| device_id | UUID | FK → device_registrations(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE security_audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_id UUID,
    user_type VARCHAR(20),
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50),
    resource_id UUID,
    details JSONB DEFAULT '{}',
    severity VARCHAR(10) DEFAULT 'info',
    ip_address VARCHAR(45),
    device_id UUID REFERENCES device_registrations(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `login_attempts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| user_identifier | VARCHAR(100) | NOT NULL | PIN hash prefix, email, or staff ID attempted |
| attempt_type | VARCHAR(20) | NOT NULL | pin, password, biometric, two_factor |
| is_successful | BOOLEAN | NOT NULL | |
| ip_address | VARCHAR(45) | NULLABLE | |
| device_id | UUID | NULLABLE | |
| attempted_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE login_attempts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    user_identifier VARCHAR(100) NOT NULL,
    attempt_type VARCHAR(20) NOT NULL,
    is_successful BOOLEAN NOT NULL,
    ip_address VARCHAR(45),
    device_id UUID,
    attempted_at TIMESTAMP DEFAULT NOW()
);
```

#### `security_policies` (Local SQLite only — not synced to cloud)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INTEGER | PK AUTOINCREMENT | |
| store_id | TEXT | NOT NULL | |
| pin_min_length | INTEGER | DEFAULT 4 | Minimum PIN length |
| pin_max_length | INTEGER | DEFAULT 6 | Maximum PIN length |
| auto_lock_seconds | INTEGER | DEFAULT 120 | Screen auto-lock timeout |
| max_failed_attempts | INTEGER | DEFAULT 5 | Before account lockout |
| lockout_duration_minutes | INTEGER | DEFAULT 15 | Account lockout duration |
| require_2fa_owner | INTEGER | DEFAULT 1 | 1 = 2FA required for owner login |
| session_max_hours | INTEGER | DEFAULT 12 | Maximum shift/session duration |
| require_pin_override_void | INTEGER | DEFAULT 1 | Require manager PIN for void |
| require_pin_override_return | INTEGER | DEFAULT 1 | Require manager PIN for return |
| require_pin_override_discount | INTEGER | DEFAULT 1 | Require manager PIN for discount above threshold |
| discount_override_threshold | REAL | DEFAULT 20.0 | Discount % requiring manager PIN |
| updated_at | TEXT | | |

```sql
-- Local SQLite table (Drift ORM)
CREATE TABLE security_policies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id TEXT NOT NULL,
    pin_min_length INTEGER DEFAULT 4,
    pin_max_length INTEGER DEFAULT 6,
    auto_lock_seconds INTEGER DEFAULT 120,
    max_failed_attempts INTEGER DEFAULT 5,
    lockout_duration_minutes INTEGER DEFAULT 15,
    require_2fa_owner INTEGER DEFAULT 1,
    session_max_hours INTEGER DEFAULT 12,
    require_pin_override_void INTEGER DEFAULT 1,
    require_pin_override_return INTEGER DEFAULT 1,
    require_pin_override_discount INTEGER DEFAULT 1,
    discount_override_threshold REAL DEFAULT 20.0,
    updated_at TEXT
);
```

### 6.2 Indexes

| Index | Table | Columns | Type | Purpose |
|---|---|---|---|---|
| `device_reg_store_hw` | device_registrations | (store_id, hardware_id) | B-TREE UNIQUE | Unique device per store |
| `device_reg_store_active` | device_registrations | (store_id, is_active) | B-TREE | Active device listing |
| `audit_store_date` | security_audit_log | (store_id, created_at) | B-TREE | Audit log browsing |
| `audit_store_action` | security_audit_log | (store_id, action) | B-TREE | Filter by action type |
| `audit_user` | security_audit_log | (user_id, created_at) | B-TREE | User-specific audit trail |
| `audit_severity` | security_audit_log | (store_id, severity) | B-TREE | Critical event filtering |
| `login_attempts_store_user` | login_attempts | (store_id, user_identifier, attempted_at) | B-TREE | Brute force detection |
| `login_attempts_date` | login_attempts | (attempted_at) | B-TREE | Cleanup old records |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ device_registrations
stores ──1:N──▶ security_audit_log ◀──N:1── device_registrations
stores ──1:N──▶ login_attempts
stores ──1:1──▶ security_policies (local only)
```

---

## 7. Business Rules

1. **PIN hashing** — staff PINs are stored as `SHA-256(salt + PIN)` where the salt is unique per staff member; PINs are NEVER stored in plaintext; comparison is hash-to-hash
2. **Brute force protection** — after N consecutive failed login attempts (configurable, default 5), the account is locked for M minutes (default 15); the lockout counter resets on successful login
3. **Screen auto-lock** — POS terminal auto-locks after configurable inactivity timeout (default 2 minutes); touching any key resets the timer; locked screen requires PIN/biometric to unlock
4. **PIN override segregation** — a staff member cannot PIN-override their own restricted action; a different user with equal or higher privilege must authorize; the override event is logged with both user IDs
5. **Card data never stored** — the POS system NEVER receives, processes, or stores raw card numbers (PAN), CVV, or magnetic stripe data; all card processing is handled by the NearPay SDK in its PCI-compliant environment; only transaction reference IDs and approval codes are stored
6. **TLS certificate pinning** — the Dio HTTP client pins the server's TLS certificate; if the certificate changes without an app update, API calls fail and an alert is raised; this prevents man-in-the-middle attacks on public WiFi
7. **Database encryption** — the local SQLite database is encrypted with SQLCipher (AES-256-CBC); the encryption key is stored in `flutter_secure_storage` (backed by Windows DPAPI); the database file is unreadable without the key
8. **Backup encryption** — all backup files are encrypted with AES-256-GCM before being written to disk or uploaded to cloud; the encryption key is derived from the store's master key using PBKDF2 (100,000 iterations)
9. **Remote wipe** — if a POS device is lost or compromised, the owner can trigger a remote wipe via the web dashboard or mobile app; on next sync, the device deletes the local database, clears secure storage, and reverts to the factory setup state
10. **Audit log immutability** — audit log entries cannot be edited or deleted by any user (including owner); they are append-only; synced to the cloud database where they are protected by database-level permissions
11. **Session expiry** — POS staff sessions expire after the configured maximum duration (default 12 hours / end of shift); web dashboard sessions expire after 2 hours of inactivity; mobile app tokens expire after 30 days
12. **2FA for owner** — Two-Factor Authentication (TOTP) is mandatory for owner accounts accessing the web dashboard or performing sensitive POS operations (certificate management, remote wipe, staff deletion); 2FA can optionally be enabled for staff login
13. **Sensitive data masking** — customer phone numbers, email addresses, and card last-4 digits are masked in audit logs and exports (e.g., `+968 9*** ***12`); full data is only visible to authorized roles
14. **Rate limiting** — API endpoints are rate-limited: login = 10 attempts per minute per IP; general API = 60 requests per minute per token; export = 5 per hour
15. **Security event notifications** — critical security events (failed login burst, remote wipe trigger, new device registration, 2FA disable) generate push notifications to the store owner's mobile app
