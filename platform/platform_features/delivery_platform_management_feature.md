# Third-Party Delivery Platform Management — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Integration Builder, Platform Registry & Sync Monitoring  
> **Tech Stack:** Laravel 11 + Filament v3 · Livewire 3 · Laravel Queues  

---

## 1. Feature Overview

Third-Party Delivery Platform Management allows Thawani admin to **add, configure, and monitor** any food/grocery delivery platform — without code deployment. The admin defines the platform's auth method, credential field schema, operation endpoints, webhook templates, and field mapping. Providers then enter their credentials and toggle integrations on/off via their POS settings.

### What This Feature Does
- Maintain a master registry of delivery platforms (HungerStation, Keeta, Jahez, Noon Food, Ninja, Mrsool, The Chefz, Talabat, ToYou, … plus custom)
- Per-platform configuration: name, logo, slug, auth method, custom credential fields, operation endpoints, request field mapping, webhook path template
- Enable/disable platforms globally (without deleting)
- Test connectivity to any platform endpoint from the admin panel
- Monitor platform-wide sync health: error rates, last sync time, affected stores
- View which providers have each platform enabled
- Extensible: add an entirely new delivery platform at runtime

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Package & Subscription Mgmt** | `max_delivery_platforms` plan limit controls how many platforms a provider can enable |
| **Provider Management** | Store detail shows enabled delivery platforms, sync status |
| **Notification Templates** | `order.new_external` notification template uses `{{platform}}` variable sourced from platform name |
| **POS Terminal (Provider)** | Inbound orders from delivery platforms appear as external orders on the POS |
| **Analytics & Reporting** | "Third-party integration usage by platform" and "Delivery sync error rate per platform" metrics |
| **System Configuration** | Webhook URL base path configuration affects inbound order routing |

### Features to Review After Changing This Feature
1. **Package limits** — if a new platform is added, max_delivery_platforms limits still apply
2. **Notification templates** — ensure `{{platform}}` variable resolves for new platforms
3. **Inbound order controller** — webhook path template must match routing
4. **Provider credential forms** — custom field definitions drive the provider-side UI
5. **Sync engine** — new operation types or field mappings need to be compatible with the job pipeline
6. **Analytics** — new platforms auto-appear in usage metrics (if indexed properly)

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for DeliveryPlatform, Fields, Endpoints |
| **spatie/laravel-activitylog** | Audit log for platform config changes |
| **guzzlehttp/guzzle** via Laravel `Http` | Test connectivity to platform endpoints |
| **spatie/laravel-permission** | Access gating: `integrations.manage` |
| **laravel/horizon** | Monitor queued sync jobs |

### 3.2 Technologies
- **Laravel 11** — Eloquent, Jobs (SyncProductToThirdParties, PushToPlatform), Events
- **Filament v3** — Admin UI: Repeater fields for custom key definitions, Endpoints
- **PostgreSQL** — relational storage for platform definitions
- **Redis** — queue for outbound sync jobs (3 retries, 60s backoff)
- **AES-256 Encryption** — provider credentials encrypted at rest via `encrypt()`/`decrypt()`

---

## 4. Pages

### 4.1 Delivery Platforms List
| Field | Detail |
|---|---|
| **Route** | `/admin/integrations/platforms` |
| **Filament Resource** | `DeliveryPlatformResource` |
| **Table Columns** | Logo (image), Name, Slug, Auth Method badge, Active Providers count, Is Active badge, Sort Order |
| **Filters** | Active/Inactive, Auth Method |
| **Row Actions** | Edit, Test Connectivity, Deactivate, Delete (only if no providers use it) |
| **Header Action** | Create New Platform |
| **Access** | `integrations.manage` |

### 4.2 Platform Create / Edit (Integration Builder)
| Field | Detail |
|---|---|
| **Route** | `/admin/integrations/platforms/create` · `/admin/integrations/platforms/{id}/edit` |
| **Form Sections** | |
| — Basic Info | Platform Name, Slug (auto), Logo URL (upload/URL), Auth Method select (Bearer / API Key Header / Basic Auth / OAuth2), Is Active toggle, Sort Order |
| — Custom Credential Fields | **Repeater**: each row = Field Label (shown to provider, e.g. "Restaurant ID"), Field Key (internal, e.g. `restaurant_id`), Field Type (text / password / url), Is Required toggle, Sort Order. Unlimited fields per platform |
| — Operation Endpoints | **Repeater**: each row = Operation select (product_create / product_update / product_delete / category_sync / bulk_menu_push / custom), URL Template, HTTP Method (POST / PUT / DELETE / PATCH), Request Mapping JSONB editor |
| — Inbound Webhook | Path template (e.g. `/webhooks/{platform_slug}/{store_id}`), Expected headers, Sample payload |
| **Validation** | Slug unique, at least 1 credential field, at least 1 endpoint |
| **Side Effects** | Changes logged via activitylog; existing provider credentials are not affected |
| **Access** | `integrations.manage` |

### 4.3 Platform Provider Usage View
| Field | Detail |
|---|---|
| **Route** | `/admin/integrations/platforms/{id}/providers` |
| **Purpose** | List all stores using this platform |
| **Table Columns** | Store Name, Organisation, Sync Status badge (ok / error / pending), Last Sync At, Error Count, Is Enabled |
| **Filters** | Sync Status, Enabled/Disabled |
| **Row Actions** | View Store Detail |
| **Access** | `integrations.manage` |

### 4.4 Sync Health Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/integrations/health` |
| **Purpose** | Platform-wide sync monitoring |
| **Widgets** | Total active integrations count, Overall error rate % (24h), Error rate per platform (bar chart), Failed syncs list (last 50, with retry action), Top error messages |
| **Auto-refresh** | Every 60 seconds via Livewire polling |
| **Access** | `integrations.manage` |

### 4.5 Test Connectivity Tool
| Field | Detail |
|---|---|
| **Route** | Modal within Platform Edit |
| **Purpose** | Send a test request to a platform endpoint with sample data |
| **Form** | Operation select, Test credentials input (or pick a provider), Request preview |
| **Result** | HTTP status, Response body (truncated), Latency (ms) |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD for `DeliveryPlatformResource`.

### 5.2 Provider-Facing APIs
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/integrations/platforms` | GET | List available delivery platforms with their field definitions | Store API token |
| `GET /api/v1/integrations/{platform_slug}/config` | GET | Get platform config + provider's saved credentials (masked) | Store API token |
| `POST /api/v1/integrations/{platform_slug}/credentials` | POST | Save/update provider credentials for a platform | Store API token |
| `POST /api/v1/integrations/{platform_slug}/toggle` | POST | Enable/disable platform for this store | Store API token |
| `POST /api/v1/integrations/{platform_slug}/test` | POST | Test connectivity with saved credentials | Store API token |
| `POST /api/v1/integrations/{platform_slug}/sync-menu` | POST | Trigger full menu push | Store API token |
| `POST /api/pos/orders/inbound/{platform_slug}` | POST | Receive inbound order from delivery platform | X-Api-Key header (auto-generated per provider per platform) |

### 5.3 Internal Sync Jobs
| Job | Purpose |
|---|---|
| `SyncProductToThirdParties` | Dispatched on product create/update/delete; fans out to `PushToPlatform` per enabled platform |
| `PushToPlatform` | Sends one HTTP request to one platform; 3 retries, 60s backoff; updates `sync_status` and `last_sync_at` |

---

## 6. Full Database Schema

### 6.1 Tables

#### `delivery_platforms`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | Platform display name |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | hungerstation, keeta, jahez, etc. |
| logo_url | TEXT | NULLABLE | |
| auth_method | VARCHAR(20) | NOT NULL | bearer / api_key / basic / oauth2 |
| is_active | BOOLEAN | DEFAULT TRUE | Global enable/disable |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE delivery_platforms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    logo_url TEXT,
    auth_method VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `delivery_platform_fields`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| delivery_platform_id | UUID | FK → delivery_platforms(id) ON DELETE CASCADE | |
| field_label | VARCHAR(100) | NOT NULL | Shown to provider (e.g. "Restaurant ID") |
| field_key | VARCHAR(50) | NOT NULL | Internal key (e.g. `restaurant_id`) |
| field_type | VARCHAR(20) | NOT NULL | text / password / url |
| is_required | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE delivery_platform_fields (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id) ON DELETE CASCADE,
    field_label VARCHAR(100) NOT NULL,
    field_key VARCHAR(50) NOT NULL,
    field_type VARCHAR(20) NOT NULL DEFAULT 'text',
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    UNIQUE (delivery_platform_id, field_key)
);
```

#### `delivery_platform_endpoints`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| delivery_platform_id | UUID | FK → delivery_platforms(id) ON DELETE CASCADE | |
| operation | VARCHAR(50) | NOT NULL | product_create / product_update / product_delete / category_sync / bulk_menu_push |
| url_template | TEXT | NOT NULL | |
| http_method | VARCHAR(10) | NOT NULL | POST / PUT / DELETE / PATCH |
| request_mapping | JSONB | NULLABLE | Maps Thawani schema → platform schema |

```sql
CREATE TABLE delivery_platform_endpoints (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id) ON DELETE CASCADE,
    operation VARCHAR(50) NOT NULL,
    url_template TEXT NOT NULL,
    http_method VARCHAR(10) NOT NULL DEFAULT 'POST',
    request_mapping JSONB,
    UNIQUE (delivery_platform_id, operation)
);
```

#### `delivery_platform_webhook_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| delivery_platform_id | UUID | FK → delivery_platforms(id) ON DELETE CASCADE | |
| path_template | TEXT | NOT NULL | e.g. `/webhooks/{platform_slug}/{store_id}` |

```sql
CREATE TABLE delivery_platform_webhook_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id) ON DELETE CASCADE,
    path_template TEXT NOT NULL
);
```

#### Cross-Referenced Provider-Side Table: `store_delivery_platforms`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) ON DELETE CASCADE | |
| delivery_platform_id | UUID | FK → delivery_platforms(id) | |
| credentials | JSONB | NOT NULL | Encrypted: `{"restaurant_id":"abc","api_key":"xyz"}` |
| inbound_api_key | VARCHAR(48) | UNIQUE | Auto-generated per provider per platform |
| is_enabled | BOOLEAN | DEFAULT FALSE | |
| sync_status | VARCHAR(10) | DEFAULT 'pending' | ok / error / pending |
| last_sync_at | TIMESTAMP | NULLABLE | |
| last_error | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE store_delivery_platforms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    delivery_platform_id UUID NOT NULL REFERENCES delivery_platforms(id),
    credentials JSONB NOT NULL DEFAULT '{}',
    inbound_api_key VARCHAR(48) UNIQUE,
    is_enabled BOOLEAN DEFAULT FALSE,
    sync_status VARCHAR(10) DEFAULT 'pending',
    last_sync_at TIMESTAMP,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, delivery_platform_id)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `delivery_platforms_slug_unique` | slug | UNIQUE | Lookup by slug |
| `delivery_platforms_is_active` | is_active | B-TREE | Filter active platforms |
| `delivery_platform_fields_platform_key` | (delivery_platform_id, field_key) | UNIQUE | One field per key per platform |
| `delivery_platform_endpoints_platform_op` | (delivery_platform_id, operation) | UNIQUE | One endpoint per operation per platform |
| `store_delivery_platforms_store_platform` | (store_id, delivery_platform_id) | UNIQUE | One record per store per platform |
| `store_delivery_platforms_inbound_key` | inbound_api_key | UNIQUE | Fast webhook auth lookup |
| `store_delivery_platforms_sync_status` | sync_status | B-TREE | Filter by sync health |

### 6.3 Relationships Diagram
```
delivery_platforms ──1:N──▶ delivery_platform_fields
delivery_platforms ──1:N──▶ delivery_platform_endpoints
delivery_platforms ──1:N──▶ delivery_platform_webhook_templates
delivery_platforms ──1:N──▶ store_delivery_platforms (provider side)

stores ──1:N──▶ store_delivery_platforms (provider side)

subscription_plans.plan_limits (limit_key='max_delivery_platforms') ──gates──▶ store_delivery_platforms count
```

---

## 7. Business Rules

1. **No code deployment required** — adding a new delivery platform is entirely UI-driven; admin defines fields, endpoints, mapping, and providers can immediately see and connect
2. **Credential encryption** — all provider credentials stored in `credentials` JSONB are AES-256 encrypted at rest via Laravel `encrypt()`
3. **Auto-generated inbound API key** — when a provider enables a platform, a 48-character random key is generated for webhook authentication
4. **Plan limit enforcement** — a provider cannot enable more platforms than `max_delivery_platforms` in their subscription plan
5. **Global deactivation** — setting `is_active = false` on a platform hides it from all providers; existing integrations pause but credentials remain
6. **Sync retry policy** — `PushToPlatform` job retries 3 times with 60-second backoff; on final failure, `sync_status` set to `error` and `last_error` updated
7. **Test connectivity** — admin can test-call any endpoint with sample data; response is displayed in-panel (never stored)
8. **Field mapping extensibility** — the `request_mapping` JSONB in `delivery_platform_endpoints` defines a declarative transformation from Thawani product schema to the platform's expected schema
9. **Inbound order routing** — all inbound webhooks hit `/api/pos/orders/inbound/{platform_slug}`, authenticated by `X-Api-Key` header matching `store_delivery_platforms.inbound_api_key`
