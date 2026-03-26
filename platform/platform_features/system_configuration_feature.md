# System Configuration — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Global Settings, Feature Flags, Tax / VAT Config, Third-Party Credentials, Maintenance Mode  
> **Tech Stack:** Laravel 11 + Filament v3 · Spatie Permission · Spatie ActivityLog · Redis  

---

## 1. Feature Overview

System Configuration is the central control panel for all platform-wide operational settings. It houses API credentials for third-party services, global tax/locale defaults, feature flag rollout controls, and maintenance mode. Changes here affect every provider on the platform, so all mutations are audit-logged and protected by strict permissions.

### What This Feature Does
- ZATCA environment toggle (sandbox / production) and API credentials
- Payment gateway credentials and webhook URL management
- SMS provider settings (Unifonic / Taqnyat / Msegat)
- Email provider settings (SMTP / Mailgun / SES)
- FCM / APNs push notification credentials
- WhatsApp Business API configuration
- Maintenance mode toggle with provider-facing banner message
- Feature flags for gradual rollouts to subsets of providers
- Global VAT rate and currency / locale defaults
- Sync conflict resolution policy settings
- Accounting integration settings: API credentials for QuickBooks, Xero, Qoyod (Saudi) — providers connect from their store settings
- Tax exemption category management: define exemption types, required documentation
- Age-restricted product category management: define which categories trigger verification prompt
- **Payment method registry** — master list of available payment methods (cash, NearPay card types, store credit, gift card, mobile payment) with per-method configuration, enable/disable per market
- **Hardware compatibility catalog** — platform-managed list of certified POS peripherals (receipt printers, barcode scanners, scales, label printers, NFC readers, NearPay terminals) with driver/firmware details that the POS app references for auto-detection and setup guidance
- **Language & translation management** — master ARB translation strings, supported locales, translation version management; providers can request overrides but the platform controls the canonical translations
- **Thawani marketplace integration config** — OAuth2 app credentials, API endpoints, webhook URLs, and version settings for the Thawani marketplace integration that providers opt into
- **Security policy defaults** — platform-wide security settings that govern provider POS behavior: session timeout durations, PIN complexity requirements, biometric authentication defaults, failed login lockout thresholds, and device registration policies. Providers inherit these as their minimum security floor and can optionally enforce stricter settings.

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Billing & Finance** | Payment gateway credentials referenced during invoice payment processing |
| **Notification Templates** | SMS / email / push / WhatsApp channel availability depends on the corresponding provider credentials being configured here |
| **Delivery Platform Mgmt** | Webhook base URL and global API timeout settings |
| **Package & Subscription Mgmt** | Feature flags can gate new package features before full rollout |
| **POS Layout Mgmt** | Feature flags can control availability of new layout options |
| **Analytics & Reporting** | Feature adoption stats reference feature_flags table |
| **Security & Audit** | Credential changes are audit-logged; encrypted at rest |
| **Provider Management** | Maintenance mode blocks provider actions and shows banner |
| **All Provider-Side Features** | VAT rate, locale defaults, ZATCA environment flow through to every transaction |
| **Payments & Finance (Provider)** | Payment method registry defines which methods are available in the POS payment screen |
| **Hardware Support (Provider)** | Hardware catalog determines which peripherals the POS auto-detects and shows setup instructions for |
| **Language & Localization (Provider)** | Master translation strings and supported locales are sourced from this config |
| **Thawani Integration (Provider)** | Marketplace API credentials and webhook config are managed here |
| **Security (Provider)** | Security policy defaults (session timeout, PIN complexity, lockout rules) set the minimum security floor that all providers inherit |

### Features to Review After Changing This Feature
1. **Billing & Finance** — changing payment gateway credentials requires re-testing connectivity
2. **Notification Templates** — switching SMS/email provider may require template format adjustments
3. **Provider Management** — maintenance mode toggle must be coordinated with announcements
4. **Security & Audit** — credential rotation re-encrypts secrets; audit trail must capture the change
5. **Payments & Finance (Provider)** — payment method registry changes affect which methods appear in POS checkout
6. **Hardware Support (Provider)** — adding or removing certified hardware affects auto-detection and setup guidance
7. **Language & Localization (Provider)** — publishing new translation versions triggers an OTA string update to all POS apps
8. **Thawani Integration (Provider)** — credential or API version changes require all integrated stores to re-authenticate
9. **Security (Provider)** — changing security policy defaults (session timeout, PIN complexity) affects all stores that haven't set stricter custom policies; POS apps enforce the new minimums on next sync

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Custom settings pages, Resources for flags / tax types / age categories |
| **spatie/laravel-permission** | Permissions: `settings.view`, `settings.edit`, `settings.feature_flags`, `settings.credentials`, `settings.hardware_catalog`, `settings.translations`, `settings.payment_methods`, `settings.security_policies` |
| **spatie/laravel-activitylog** | Audit log for every settings change |
| **spatie/laravel-settings** (optional) | Typed settings classes backed by DB (alternative to raw key-value) |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | `encrypt()` / `decrypt()` for credentials, `.env`-level overrides, config caching |
| **Filament v3** | Custom pages (grouped settings forms) + Resources |
| **PostgreSQL** | Settings storage (`system_settings`, `feature_flags`, etc.) |
| **Redis** | Settings cache (5-minute TTL); feature flag cache (60s TTL); maintenance mode flag cache |
| **DigitalOcean Spaces** | Backup of encrypted credentials export (disaster recovery) — S3-compatible |

---

## 4. Pages

### 4.1 General Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/general` |
| **Purpose** | Global defaults: VAT, currency, locale, sync policy |
| **Form Sections** | |
| **Locale & Currency** | Default currency (SAR), currency symbol position, number format (AR / EN digits), default language |
| **VAT** | Global VAT rate (%), VAT registration number |
| **Sync** | Conflict resolution policy (server-wins / client-wins / last-write-wins / manual), sync interval seconds |
| **Access** | `settings.edit` |

### 4.2 ZATCA Configuration
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/zatca` |
| **Form Fields** | Environment select (sandbox / production), API base URL (auto-filled from environment), Client ID (encrypted), Client Secret (encrypted), Certificate Path |
| **Actions** | Test Connection button (calls ZATCA compliance check endpoint) |
| **Access** | `settings.credentials` (Super Admin) |

### 4.3 Payment Gateway Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/payment-gateways` |
| **Note** | Also accessible from Billing & Finance → Gateways. Shared Resource `PaymentGatewayConfigResource` |
| **Access** | `settings.credentials` |

### 4.4 SMS Provider Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/sms` |
| **Form Fields** | Provider select (unifonic / taqnyat / msegat), API Key (encrypted), Sender Name, Base URL |
| **Actions** | Send Test SMS (to admin's phone) |
| **Access** | `settings.credentials` |

### 4.5 Email Provider Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/email` |
| **Form Fields** | Provider select (smtp / mailgun / ses), Host, Port, Username (encrypted), Password (encrypted), From Address, From Name |
| **Actions** | Send Test Email (to admin's email) |
| **Access** | `settings.credentials` |

### 4.6 Push Notification Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/push` |
| **Form Fields** | FCM Server Key (encrypted), FCM Project ID, APNs Key ID, APNs Team ID, APNs Key File upload |
| **Access** | `settings.credentials` |

### 4.7 WhatsApp Business API Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/whatsapp` |
| **Form Fields** | Provider (meta_cloud_api / twilio), Access Token (encrypted), Phone Number ID, Business Account ID, Webhook Verify Token |
| **Access** | `settings.credentials` |

### 4.8 Maintenance Mode
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/maintenance` |
| **Form Fields** | Is Maintenance Mode toggle, Banner Message (AR / EN), Expected End Time (datetime), Allowed IPs during maintenance (textarea, one per line) |
| **Behaviour** | When enabled, all provider-facing API endpoints return 503 with banner message; admin panel remains accessible |
| **Access** | `settings.edit` (Super Admin) |

### 4.9 Feature Flags
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/feature-flags` |
| **Filament Resource** | `FeatureFlagResource` |
| **Table Columns** | Flag Key, Description, Is Enabled badge, Rollout %, Target Plans, Target Stores, Updated At |
| **Row Actions** | Edit, Toggle On/Off |
| **Create/Edit Form** | Flag Key (slug), Description, Is Enabled, Rollout Percentage (0–100), Target Plan IDs (multi-select), Target Store IDs (multi-select, searchable), Description |
| **Access** | `settings.feature_flags` |

### 4.10 Tax Exemption Types
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/tax-exemptions` |
| **Filament Resource** | `TaxExemptionTypeResource` |
| **Table Columns** | Code, Name, Name (AR), Required Documents, Is Active |
| **Row Actions** | Edit, Deactivate |
| **Create Form** | Code (slug), Name (EN / AR), Required Documents (textarea), Is Active |
| **Access** | `settings.edit` |

### 4.11 Age-Restricted Categories
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/age-restrictions` |
| **Filament Resource** | `AgeRestrictedCategoryResource` |
| **Table Columns** | Category Slug, Minimum Age, Is Active |
| **Row Actions** | Edit, Deactivate |
| **Create Form** | Category Slug (matched to provider-side category slugs), Minimum Age, Is Active |
| **Access** | `settings.edit` |

### 4.12 Accounting Integration Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/accounting` |
| **Filament Resource** | `AccountingIntegrationConfigResource` |
| **Table Columns** | Provider Name, Redirect URL, Is Active |
| **Row Actions** | Edit, Test Connection |
| **Create/Edit Form** | Provider Name select (quickbooks / xero / qoyod), Client ID (encrypted), Client Secret (encrypted), Redirect URL, Is Active |
| **Note** | These are platform-level OAuth app credentials. Providers connect their own accounts from their store settings using these app credentials. |
| **Access** | `settings.credentials` |

### 4.13 Payment Method Registry
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/payment-methods` |
| **Filament Resource** | `PaymentMethodResource` |
| **Table Columns** | Icon, Method Key (slug), Name (EN), Name (AR), Category badge (cash / card / digital / credit), Is Active, Sort Order |
| **Row Actions** | Edit, Activate/Deactivate |
| **Header Actions** | Create Payment Method |
| **Purpose** | Master registry of all payment methods available to providers on the POS. Each method defines its category, display properties, and provider-side configuration requirements. Adding a new payment method here makes it available for stores to enable in their POS. |
| **Access** | `settings.payment_methods` |

### 4.14 Payment Method Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/payment-methods/create` or `/{id}/edit` |
| **Form Fields** | Method Key (slug, unique — e.g. `cash`, `card_mada`, `card_visa`, `card_mastercard`, `store_credit`, `gift_card`, `mobile_payment`), Name (EN), Name (AR), Icon (emoji/upload), Category select (cash / card / digital / credit), Requires Terminal toggle (e.g. NearPay), Requires Customer Profile toggle (e.g. store credit), Provider Config Schema (JSONB — defines what configuration fields the provider must fill, e.g. `{}` for cash, `{"terminal_id": "string"}` for card), Is Active toggle, Sort Order |
| **Note** | Methods marked `requires_terminal = true` are hidden in POS if no payment terminal is configured. Methods marked `requires_customer_profile = true` are hidden unless a customer is attached to the sale. |
| **Access** | `settings.payment_methods` |

### 4.15 Hardware Compatibility Catalog
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/hardware-catalog` |
| **Filament Resource** | `CertifiedHardwareResource` |
| **Table Columns** | Device Type badge (receipt_printer / barcode_scanner / weighing_scale / label_printer / cash_drawer / card_terminal / nfc_reader / customer_display), Brand, Model, Driver Protocol, Is Certified badge, Is Active |
| **Filters** | Device Type, Brand, Is Certified |
| **Search** | By brand, model |
| **Row Actions** | Edit, Deactivate |
| **Header Actions** | Create Hardware Entry |
| **Purpose** | Platform-managed catalog of all hardware peripherals known to work with the POS system. The POS app fetches this catalog during setup to show recommended devices and auto-detect compatible ones. |
| **Access** | `settings.hardware_catalog` |

### 4.16 Certified Hardware Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/hardware-catalog/create` or `/{id}/edit` |
| **Form Fields** | Device Type select, Brand, Model, Driver Protocol (esc_pos / zpl / tspl / serial_scale / hid / nearpay_sdk / nfc_hid), Connection Types (multi-select: USB / Network / Bluetooth / Serial), Firmware Version (min supported), Paper Width (if printer: 58mm/80mm/both), Setup Instructions (EN, rich text), Setup Instructions (AR, rich text), Is Certified toggle (Thawani-tested), Is Active toggle, Notes |
| **Access** | `settings.hardware_catalog` |

### 4.17 Language & Translation Management
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/translations` |
| **Filament Resource** | `TranslationStringResource` |
| **Table Columns** | String Key, English Value (truncated), Arabic Value (truncated), Category (ui / receipt / notification / report), Updated At |
| **Filters** | Category, Has Missing Translation, Recently Updated |
| **Search** | By key or value text |
| **Row Actions** | Edit |
| **Bulk Actions** | Import ARB file, Export ARB file |
| **Purpose** | Master translation string management. All POS app translation strings are managed here. The POS app downloads the latest strings on sync. This is the authoritative source — providers can only override a limited subset (receipt/customer-facing strings). |
| **Access** | `settings.translations` |

### 4.18 Translation String Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/translations/{id}/edit` |
| **Form Fields** | String Key (read-only), Category select, English Value (textarea), Arabic Value (textarea), Description/Context (for translator reference), Is Overridable toggle (whether providers can override this string in their store settings) |
| **Access** | `settings.translations` |

### 4.19 Supported Locales Management
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/locales` |
| **Filament Resource** | `SupportedLocaleResource` |
| **Table Columns** | Locale Code, Language Name, Direction (LTR/RTL), Is Active, Is Default |
| **Row Actions** | Edit, Set as Default, Deactivate |
| **Header Actions** | Add Locale |
| **Form Fields** | Locale Code (e.g. `ar`, `en`, `ur`), Language Name (EN), Language Name (native), Direction (LTR / RTL), Date Format, Number Format, Calendar System (Gregorian / Hijri / both), Is Active, Is Default |
| **Purpose** | Controls which languages are available across the platform. Adding a new locale here means translation strings must be provided for that locale before it is activated. |
| **Access** | `settings.translations` |

### 4.20 Thawani Marketplace Integration Config
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/thawani-marketplace` |
| **Purpose** | Manage the Thawani marketplace API integration credentials |
| **Form Sections** | |
| **OAuth2 App** | Client ID (encrypted), Client Secret (encrypted), Redirect / Callback URL |
| **API Settings** | API Base URL (environment-based), API Version (e.g. `v2`), Webhook URL (auto-generated, displayed read-only for Thawani to register), Webhook Secret (encrypted) |
| **Rate Limits** | Requests per minute (display from Thawani's published limits), Sync interval (minutes — how often stores push catalog to marketplace) |
| **Connection** | Test Connection button, Last successful connection timestamp, Connection status badge |
| **Access** | `settings.credentials` (Super Admin) |

### 4.21 Security Policy Defaults
| Field | Detail |
|---|---|
| **Route** | `/admin/settings/security-policies` |
| **Purpose** | Platform-wide security policy defaults that all provider POS apps inherit as the minimum security floor. Providers can enforce stricter policies in their store settings but cannot go below these minimums. |
| **Form Sections** | |
| **Session Management** | Session Timeout Minutes (default: 30 — POS auto-locks after this idle time; min 5, max 480), Require Re-authentication on Wake toggle (if POS screen was locked/slept) |
| **PIN & Password** | PIN Minimum Length (default: 4, min: 4, max: 8), PIN Complexity select (numeric_only / alphanumeric / alphanumeric_with_special), Require Unique PINs Per Store toggle (no two staff can share a PIN within a store), PIN Expiry Days (0 = never; force PIN change after N days) |
| **Biometric** | Biometric Authentication Enabled Default toggle (whether fingerprint/face unlock is offered by default on compatible devices), Biometric Can Replace PIN toggle (if true, biometric alone is sufficient; if false, biometric is only a convenience shortcut that still requires PIN on sensitive operations) |
| **Login Protection** | Max Failed Login Attempts (default: 5 — after this many consecutive failed PINs, the user/terminal is locked), Lockout Duration Minutes (default: 15 — how long the lockout lasts; 0 = until manager unlock), Failed Attempt Alert to Owner toggle (notify store owner of lockout events) |
| **Device Management** | Device Registration Policy select (open / approval_required / whitelist_only — open: any device can connect; approval_required: new devices need owner approval; whitelist_only: only pre-registered devices), Max Devices Per Store (default: 10 — maximum number of registered POS terminals/devices) |
| **Access** | `settings.edit` (Super Admin) |

### 5.1 Internal Livewire (Filament auto-generated)
Standard form saves for all settings pages, plus CRUD for feature flags, tax exemption types, age-restricted categories, accounting configs.

### 5.2 Custom Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /admin/settings/zatca/test` | POST | Test ZATCA API connectivity | `settings.credentials` |
| `POST /admin/settings/sms/test` | POST | Send test SMS | `settings.credentials` |
| `POST /admin/settings/email/test` | POST | Send test email | `settings.credentials` |
| `POST /admin/settings/accounting/{provider}/test` | POST | Test OAuth app credentials | `settings.credentials` |

### 5.3 Provider-Facing APIs
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/config/feature-flags` | GET | Returns resolved feature flags for the calling store (considering plan, rollout %, store targeting) | Store API token |
| `GET /api/v1/config/maintenance` | GET | Returns maintenance mode status + banner message | Public / Store API token |
| `GET /api/v1/config/tax` | GET | Returns global VAT rate, tax exemption types | Store API token |
| `GET /api/v1/config/age-restrictions` | GET | Returns active age-restricted categories | Store API token |
| `GET /api/v1/config/payment-methods` | GET | Returns active payment methods with display info and config schema | Store API token |
| `GET /api/v1/config/hardware-catalog` | GET | Returns certified hardware list for POS auto-detection and setup | Store API token |
| `GET /api/v1/config/translations/{locale}` | GET | Returns full master translation strings for the given locale | Store API token |
| `GET /api/v1/config/translations/version` | GET | Returns current translation version hash (POS checks this to decide whether to download new strings) | Store API token |
| `GET /api/v1/config/locales` | GET | Returns list of active supported locales with formatting info | Store API token |
| `GET /api/v1/config/security-policies` | GET | Returns platform security policy defaults (session timeout, PIN complexity, lockout rules, device policy) — the POS enforces these as minimums | Store API token |
| `POST /admin/settings/thawani-marketplace/test` | POST | Test Thawani marketplace API connectivity | `settings.credentials` |

### 5.4 Internal Jobs
| Job | Purpose |
|---|---|
| `CacheFeatureFlags` | Rebuilds Redis feature flag cache every 60s (or on flag update event) |
| `RotateCredentials` (manual dispatch) | Re-encrypts all credential fields after `APP_KEY` rotation |

---

## 6. Full Database Schema

### 6.1 Tables

#### `system_settings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| key | VARCHAR(100) | NOT NULL, UNIQUE | e.g. `vat.rate`, `locale.default_language`, `sync.conflict_policy` |
| value | JSONB | NOT NULL | Flexible value — string, number, object |
| group | VARCHAR(50) | NOT NULL | zatca / payment / sms / email / push / whatsapp / sync / vat / locale / maintenance |
| description | VARCHAR(255) | NULLABLE | Human-readable description |
| updated_by | UUID | FK → admin_users(id), NULLABLE | Last editor |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key VARCHAR(100) NOT NULL UNIQUE,
    value JSONB NOT NULL,
    "group" VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    updated_by UUID REFERENCES admin_users(id),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_system_settings_group ON system_settings ("group");
```

#### `feature_flags`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| flag_key | VARCHAR(100) | NOT NULL, UNIQUE | e.g. `new_kds_layout`, `recipe_inventory_v2` |
| is_enabled | BOOLEAN | DEFAULT FALSE | Global on/off |
| rollout_percentage | INT | DEFAULT 0 | 0–100; stores randomly assigned |
| target_plan_ids | JSONB | DEFAULT '[]' | Empty = all plans |
| target_store_ids | JSONB | DEFAULT '[]' | Empty = all stores |
| description | VARCHAR(255) | NULLABLE | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE feature_flags (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    flag_key VARCHAR(100) NOT NULL UNIQUE,
    is_enabled BOOLEAN DEFAULT FALSE,
    rollout_percentage INT DEFAULT 0 CHECK (rollout_percentage BETWEEN 0 AND 100),
    target_plan_ids JSONB DEFAULT '[]',
    target_store_ids JSONB DEFAULT '[]',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `tax_exemption_types`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| code | VARCHAR(20) | NOT NULL, UNIQUE | e.g. `DIPLO`, `GOV`, `EXPORT` |
| name | VARCHAR(100) | NOT NULL | |
| name_ar | VARCHAR(100) | NOT NULL | |
| required_documents | TEXT | NULLABLE | Description of what provider must upload |
| is_active | BOOLEAN | DEFAULT TRUE | |

```sql
CREATE TABLE tax_exemption_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    required_documents TEXT,
    is_active BOOLEAN DEFAULT TRUE
);
```

#### `age_restricted_categories`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| category_slug | VARCHAR(100) | NOT NULL | Matches provider-side category rules |
| min_age | INT | NOT NULL | Minimum customer age |
| is_active | BOOLEAN | DEFAULT TRUE | |

```sql
CREATE TABLE age_restricted_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category_slug VARCHAR(100) NOT NULL,
    min_age INT NOT NULL CHECK (min_age > 0),
    is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX idx_age_restricted_slug ON age_restricted_categories (category_slug);
```

#### `accounting_integration_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| provider_name | VARCHAR(30) | NOT NULL, UNIQUE | quickbooks / xero / qoyod |
| client_id_encrypted | TEXT | NOT NULL | Laravel `encrypt()` |
| client_secret_encrypted | TEXT | NOT NULL | Laravel `encrypt()` |
| redirect_url | VARCHAR(255) | NOT NULL | OAuth callback URL |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE accounting_integration_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_name VARCHAR(30) NOT NULL UNIQUE,
    client_id_encrypted TEXT NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    redirect_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `payment_methods` (Payment Method Registry)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| method_key | VARCHAR(30) | NOT NULL, UNIQUE | cash, card_mada, card_visa, card_mastercard, store_credit, gift_card, mobile_payment |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| icon | VARCHAR(255) | NULLABLE | Emoji or icon URL |
| category | VARCHAR(20) | NOT NULL | cash / card / digital / credit |
| requires_terminal | BOOLEAN | DEFAULT FALSE | Hidden in POS if no terminal connected |
| requires_customer_profile | BOOLEAN | DEFAULT FALSE | Hidden unless customer attached |
| provider_config_schema | JSONB | DEFAULT '{}' | Required config fields providers must fill |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE payment_methods (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    method_key VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    icon VARCHAR(255),
    category VARCHAR(20) NOT NULL,
    requires_terminal BOOLEAN DEFAULT FALSE,
    requires_customer_profile BOOLEAN DEFAULT FALSE,
    provider_config_schema JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Seed default payment methods
INSERT INTO payment_methods (method_key, name, name_ar, category, requires_terminal, sort_order) VALUES
('cash', 'Cash', 'نقد', 'cash', false, 1),
('card_mada', 'Mada Card', 'بطاقة مدى', 'card', true, 2),
('card_visa', 'Visa', 'فيزا', 'card', true, 3),
('card_mastercard', 'Mastercard', 'ماستركارد', 'card', true, 4),
('store_credit', 'Store Credit', 'رصيد المتجر', 'credit', false, 5),
('gift_card', 'Gift Card', 'بطاقة هدية', 'credit', false, 6),
('mobile_payment', 'Mobile Payment', 'دفع بالجوال', 'digital', false, 7);
```

#### `certified_hardware` (Hardware Compatibility Catalog)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| device_type | VARCHAR(30) | NOT NULL | receipt_printer, barcode_scanner, weighing_scale, label_printer, cash_drawer, card_terminal, nfc_reader, customer_display |
| brand | VARCHAR(100) | NOT NULL | Epson, Bixolon, Star, Zebra, TSC, NearPay, etc. |
| model | VARCHAR(100) | NOT NULL | e.g. TM-T88VI, SRP-350III |
| driver_protocol | VARCHAR(30) | NOT NULL | esc_pos, zpl, tspl, serial_scale, hid, nearpay_sdk, nfc_hid |
| connection_types | JSONB | DEFAULT '[]' | ["usb", "network", "bluetooth"] |
| firmware_version_min | VARCHAR(20) | NULLABLE | Minimum firmware version supported |
| paper_widths | JSONB | NULLABLE | [58, 80] — for printers only |
| setup_instructions | TEXT | NULLABLE | EN, rich text |
| setup_instructions_ar | TEXT | NULLABLE | AR |
| is_certified | BOOLEAN | DEFAULT FALSE | Thawani QA-tested badge |
| is_active | BOOLEAN | DEFAULT TRUE | |
| notes | TEXT | NULLABLE | Internal notes |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE certified_hardware (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    device_type VARCHAR(30) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    driver_protocol VARCHAR(30) NOT NULL,
    connection_types JSONB DEFAULT '[]',
    firmware_version_min VARCHAR(20),
    paper_widths JSONB,
    setup_instructions TEXT,
    setup_instructions_ar TEXT,
    is_certified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(brand, model)
);

CREATE INDEX idx_certified_hardware_type ON certified_hardware (device_type, is_active);
```

#### `master_translation_strings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| string_key | VARCHAR(200) | NOT NULL, UNIQUE | ARB key (e.g. `pos.checkout.total`, `receipt.header.store_name`) |
| category | VARCHAR(30) | NOT NULL | ui / receipt / notification / report |
| value_en | TEXT | NOT NULL | English translation |
| value_ar | TEXT | NOT NULL | Arabic translation |
| description | VARCHAR(255) | NULLABLE | Context for translators |
| is_overridable | BOOLEAN | DEFAULT FALSE | Whether providers can override this string |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE master_translation_strings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    string_key VARCHAR(200) NOT NULL UNIQUE,
    category VARCHAR(30) NOT NULL,
    value_en TEXT NOT NULL,
    value_ar TEXT NOT NULL,
    description VARCHAR(255),
    is_overridable BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_master_translations_category ON master_translation_strings (category);
```

#### `supported_locales`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| locale_code | VARCHAR(10) | NOT NULL, UNIQUE | ar, en, ur |
| language_name | VARCHAR(50) | NOT NULL | English |
| language_name_native | VARCHAR(50) | NOT NULL | العربية |
| direction | VARCHAR(3) | NOT NULL | ltr / rtl |
| date_format | VARCHAR(20) | DEFAULT 'DD/MM/YYYY' | |
| number_format | VARCHAR(20) | DEFAULT 'western' | western / eastern_arabic |
| calendar_system | VARCHAR(20) | DEFAULT 'gregorian' | gregorian / hijri / both |
| is_active | BOOLEAN | DEFAULT TRUE | |
| is_default | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE supported_locales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    locale_code VARCHAR(10) NOT NULL UNIQUE,
    language_name VARCHAR(50) NOT NULL,
    language_name_native VARCHAR(50) NOT NULL,
    direction VARCHAR(3) NOT NULL DEFAULT 'ltr',
    date_format VARCHAR(20) DEFAULT 'DD/MM/YYYY',
    number_format VARCHAR(20) DEFAULT 'western',
    calendar_system VARCHAR(20) DEFAULT 'gregorian',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Seed defaults
INSERT INTO supported_locales (locale_code, language_name, language_name_native, direction, calendar_system, is_default) VALUES
('ar', 'Arabic', 'العربية', 'rtl', 'both', true),
('en', 'English', 'English', 'ltr', 'gregorian', false);
```

#### `translation_versions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| version_hash | VARCHAR(64) | NOT NULL | SHA-256 of all translation strings; POS compares to decide if re-download needed |
| published_at | TIMESTAMP | DEFAULT NOW() | |
| published_by | UUID | FK → admin_users(id) | |
| notes | VARCHAR(255) | NULLABLE | e.g. "Added 15 new strings for CFD feature" |

```sql
CREATE TABLE translation_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version_hash VARCHAR(64) NOT NULL,
    published_at TIMESTAMP DEFAULT NOW(),
    published_by UUID REFERENCES admin_users(id),
    notes VARCHAR(255)
);
```

#### `thawani_marketplace_config` (singleton — one row)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| client_id_encrypted | TEXT | NOT NULL | OAuth2 client ID |
| client_secret_encrypted | TEXT | NOT NULL | OAuth2 client secret |
| redirect_url | VARCHAR(255) | NOT NULL | |
| api_base_url | VARCHAR(255) | NOT NULL | |
| api_version | VARCHAR(10) | DEFAULT 'v2' | |
| webhook_url | VARCHAR(255) | NOT NULL | Auto-generated; given to Thawani |
| webhook_secret_encrypted | TEXT | NOT NULL | |
| sync_interval_minutes | INT | DEFAULT 60 | How often stores push catalog to marketplace |
| is_active | BOOLEAN | DEFAULT TRUE | |
| last_connection_at | TIMESTAMP | NULLABLE | |
| connection_status | VARCHAR(20) | DEFAULT 'unknown' | connected / failed / unknown |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE thawani_marketplace_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id_encrypted TEXT NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    redirect_url VARCHAR(255) NOT NULL,
    api_base_url VARCHAR(255) NOT NULL,
    api_version VARCHAR(10) DEFAULT 'v2',
    webhook_url VARCHAR(255) NOT NULL,
    webhook_secret_encrypted TEXT NOT NULL,
    sync_interval_minutes INT DEFAULT 60,
    is_active BOOLEAN DEFAULT TRUE,
    last_connection_at TIMESTAMP,
    connection_status VARCHAR(20) DEFAULT 'unknown',
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes Summary

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `system_settings_key` | key | UNIQUE | Fast lookup by key |
| `system_settings_group` | group | B-TREE | Group-based page loading |
| `feature_flags_key` | flag_key | UNIQUE | Flag resolution |
| `tax_exemption_types_code` | code | UNIQUE | Code lookup |
| `age_restricted_slug` | category_slug | B-TREE | Category matching |
| `accounting_configs_provider` | provider_name | UNIQUE | One config per provider |
| `payment_methods_key` | method_key | UNIQUE | Payment method lookup |
| `payment_methods_category_sort` | (category, sort_order) | B-TREE | Grouped listing in POS |
| `certified_hardware_brand_model` | (brand, model) | UNIQUE | Prevents duplicates |
| `certified_hardware_type_active` | (device_type, is_active) | B-TREE | Filter by device type |
| `master_translations_key` | string_key | UNIQUE | Fast string lookup |
| `master_translations_category` | category | B-TREE | Category-based loading |
| `supported_locales_code` | locale_code | UNIQUE | Locale lookup |
| `thawani_marketplace_config` | — | Single row | Singleton config |

---

## 7. Business Rules

1. **Credential encryption** — all API keys, secrets, tokens stored via Laravel `encrypt()`. Raw values never appear in logs, exports, or API responses.
2. **Settings cache** — `system_settings` rows cached in Redis with 5-minute TTL per group; cache invalidated on save.
3. **Feature flag resolution** — a flag is active for a store when: `is_enabled = true` AND (store's plan is in `target_plan_ids` OR `target_plan_ids` is empty) AND (store ID is in `target_store_ids` OR `target_store_ids` is empty) AND store's hash-based bucket ≤ `rollout_percentage`.
4. **Maintenance mode** — sets a Redis flag checked by API middleware; admin panel is exempt; provider apps show the banner message and retry after `expected_end_time`.
5. **VAT rate propagation** — changing global VAT rate takes effect for all new transactions platform-wide; providers cannot override.
6. **Sync conflict policy** — `server-wins` (default) means server data overwrites client on conflict; other modes available but rarely changed.
7. **Test connection** — test endpoints execute a lightweight health-check call to the third-party API and return success/failure + latency.
8. **Feature flag audit** — every toggle or edit to a feature flag is logged with old/new values in `admin_activity_logs`.
9. **Tax exemption types** — deactivating a type does not remove it from historical transactions; it only hides it from future use.
10. **Age restriction enforcement** — POS client reads `age_restricted_categories` on sync and prompts cashier for age verification when a matching product is scanned; enforcement is client-side but audit-logged.
11. **Accounting OAuth** — platform stores app-level OAuth credentials; individual providers complete the OAuth flow from their store settings to link their own accounting accounts.
12. **Payment method deactivation** — deactivating a payment method hides it from the POS checkout screen for all stores; existing transactions with that method are not affected; stores cannot add it to new transactions.
13. **Payment method seeding** — new stores receive the full active payment method list on creation; stores can disable methods they don't accept but cannot create custom methods.
14. **Hardware catalog as advice** — the catalog is advisory; the POS auto-detect still attempts to connect to unknown devices but shows a "not officially supported" warning. Only `is_certified = true` devices get the Thawani certification badge.
15. **Translation version tracking** — every time an admin saves a translation string, a background job recalculates the `version_hash`. POS apps poll `/api/v1/config/translations/version` and only re-download if the hash changed. This prevents unnecessary data transfer.
16. **Translation override governance** — only strings marked `is_overridable = true` can be overridden at the store level (via `translation_overrides` table on the provider side). System UI strings, error messages, and security-related strings are not overridable.
17. **Locale activation** — activating a new locale requires that at least 95% of `master_translation_strings` have a non-empty value for that locale; this is enforced by a validation check before the locale is set to active.
18. **Thawani marketplace singleton** — the `thawani_marketplace_config` table has exactly one row; the Filament page is an edit form, not a list/create resource.
19. **Thawani marketplace test connection** — test endpoint calls Thawani's health-check API with the configured credentials and updates `last_connection_at` and `connection_status`.
20. **Hardware catalog sync** — POS app fetches the hardware catalog on first setup and caches it locally; re-fetches weekly or when the admin triggers a "publish hardware update" action.
21. **Security policy as minimum floor** — platform security policy defaults (stored as `system_settings` keys in the `security` group: `security.session_timeout_minutes`, `security.pin_min_length`, `security.pin_complexity`, `security.biometric_enabled_default`, `security.biometric_can_replace_pin`, `security.max_failed_login_attempts`, `security.lockout_duration_minutes`, `security.device_registration_policy`, `security.max_devices_per_store`, `security.pin_expiry_days`) act as the **minimum security floor**. Providers can configure stricter settings in their store settings (e.g. shorter timeout, longer PIN) but cannot go below the platform minimum. The POS app enforces `max(platform_default, store_setting)` for numeric minimums and respects the platform toggle for booleans.
22. **Session timeout enforcement** — the POS Flutter app starts a timer on last user interaction; when `session_timeout_minutes` elapses, the screen locks and requires PIN/biometric re-authentication. The timer resets on any touch/key/scan input.
23. **Lockout and recovery** — after `max_failed_login_attempts` consecutive failed PINs, the user or terminal is locked for `lockout_duration_minutes`. If `lockout_duration_minutes = 0`, the lockout persists until a manager with `security.unlock` permission manually unlocks it from another terminal or the admin panel.
24. **Device registration policy** — `open` mode allows any device to connect and register as a terminal; `approval_required` creates a pending registration that the store owner must approve; `whitelist_only` rejects connections from non-pre-registered devices. The policy is enforced during the POS terminal pairing flow.
25. **Security policy sync** — POS apps fetch `/api/v1/config/security-policies` on every sync (along with other config) and store the policies locally. Changes take effect on next sync (within the sync interval, typically 5 minutes).
