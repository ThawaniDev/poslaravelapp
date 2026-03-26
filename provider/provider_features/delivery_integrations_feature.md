# Third-Party Delivery Platform Integrations — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Laravel Backend)  
> **Module:** HungerStation, Jahez, Marsool Menu Sync, Order Ingestion, Status Push  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Dio · Laravel 11 · Webhooks · REST API  

---

## 1. Feature Overview

Third-Party Delivery Platform Integrations connect the POS system bidirectionally with Saudi Arabia's major food delivery platforms: **HungerStation**, **Jahez**, and **Marsool**. The integration pushes the store's product catalog and availability outbound to each platform and ingests incoming delivery orders inbound to the POS order queue. Status updates (accepted, preparing, ready, dispatched) are pushed back to the delivery platform in real time.

### What This Feature Does
- **Menu sync (outbound)** — push product catalog (names AR/EN, prices, images, availability) to each delivery platform via their merchant API
- **Stock availability sync** — when a product goes out of stock, automatically marks it unavailable on all connected platforms
- **Order ingestion (inbound)** — receive new delivery orders from platforms via webhook or polling; convert to local POS order format
- **Order status push** — when order moves through preparation stages, push status updates back to delivery platform
- **Auto-accept / manual accept** — configurable per platform; auto-accept bypasses manual review
- **Delivery platform credentials** — store API keys, merchant IDs, and webhook secrets per platform per branch
- **Operating hours sync** — push store opening/closing hours to platforms; auto-pause when store is closed
- **Order throttling** — limit the number of delivery orders accepted per time window to prevent kitchen overload
- **Commission tracking** — record delivery platform commission per order for financial reconciliation
- **Multi-platform dashboard** — unified view of all delivery orders across all platforms

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Product & Catalog Management** | Product data is the source for menu sync |
| **Inventory Management** | Stock levels determine product availability on platforms |
| **Order Management** | Delivery orders are created as orders in the order management system |
| **Payments & Finance** | Commission tracking feeds into financial reporting |
| **Notifications** | New delivery order alerts dispatched through notification system |
| **Roles & Permissions** | `delivery.manage` permission for configuration; `orders.manage` for order handling |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Order Management** | Delivery orders feed the order queue |
| **Reports & Analytics** | Revenue by delivery platform, commission reports |
| **POS Terminal** | Incoming delivery orders appear on the POS order queue |

### Features to Review After Changing This Feature
1. **Product & Catalog Management** — menu sync payload structure depends on product schema
2. **Order Management** — order creation from webhook payloads must match order schema
3. **Inventory Management** — stock availability toggle affects all connected platforms

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **dio** | HTTP client for outbound API calls to delivery platforms |
| **drift** | SQLite ORM — local delivery credentials, order mapping, sync state |
| **riverpod** / **flutter_bloc** | State management for delivery dashboard, order queue |
| **crypto** | HMAC-SHA256 for webhook signature verification |
| **uuid** | Generate UUIDs for local order mapping |

### 3.2 Technologies
- **Laravel 11 REST API** — webhook receiver endpoints; outbound API calls to delivery platforms
- **Webhook Listeners** — Laravel controllers that receive incoming order webhooks from each platform
- **Queue Workers (Laravel Horizon)** — asynchronous processing of menu sync jobs and order status push
- **Platform-Specific API Adapters** — abstraction layer with a common interface; each platform has its own adapter (HungerStationAdapter, JahezAdapter, MarsoolAdapter) implementing `DeliveryPlatformInterface`
- **Polling Fallback** — if webhooks are unreliable, a scheduled job polls platform APIs every 60 seconds for new orders
- **Flutter Desktop** — delivery dashboard and order queue UI; receives orders via sync from Laravel backend
- **Event-Driven Architecture** — order status changes fire Laravel events that trigger platform-specific status push jobs

---

## 4. Screens

### 4.1 Delivery Platform Configuration Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/delivery-platforms` |
| **Purpose** | Configure and manage delivery platform connections |
| **Layout** | Card per platform (HungerStation, Jahez, Marsool); each card shows: connection status, enable/disable toggle, credentials form |
| **Per-Platform Fields** | API Key, Merchant ID, Webhook Secret, Branch ID (platform side), Auto-accept toggle, Order throttle limit |
| **Actions** | Test connection, Trigger full menu sync, View sync logs |
| **Access** | `delivery.manage` (Owner, Branch Manager) |

### 4.2 Delivery Orders Dashboard
| Field | Detail |
|---|---|
| **Route** | `/delivery/orders` |
| **Purpose** | Unified view of all delivery orders across all platforms |
| **Layout** | Filterable table; colour-coded platform badges (HungerStation = orange, Jahez = green, Marsool = blue) |
| **Table Columns** | Order #, Platform, Customer, Items, Total, Commission, Status, Time, Actions |
| **Filters** | Platform, Status, Date range |
| **Actions** | Accept/Reject (if manual accept), Move to preparing, Mark ready, View detail |
| **Auto-Refresh** | Polls every 15 seconds; visual + audio alert on new order |
| **Access** | `orders.view` |

### 4.3 Menu Sync Status Screen
| Field | Detail |
|---|---|
| **Route** | `/delivery/menu-sync` |
| **Purpose** | View menu sync status per platform |
| **Layout** | Per-platform section: last sync time, items synced, errors, failed items |
| **Actions** | Trigger manual sync, View failed items, Retry failed |
| **Access** | `delivery.manage` |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/delivery/platforms` | POST | Save platform credentials and config | Bearer token + `delivery.manage` |
| `GET /api/delivery/platforms` | GET | List configured platforms and status | Bearer token |
| `POST /api/delivery/platforms/{platform}/sync-menu` | POST | Trigger menu sync to specific platform | Bearer token + `delivery.manage` |
| `GET /api/delivery/platforms/{platform}/sync-status` | GET | Menu sync status and log | Bearer token |
| `POST /api/delivery/platforms/{platform}/test` | POST | Test platform API connection | Bearer token + `delivery.manage` |
| **Webhook Receivers** | | | |
| `POST /webhooks/hungerstation/orders` | POST | Receive HungerStation order webhook | HMAC signature |
| `POST /webhooks/jahez/orders` | POST | Receive Jahez order webhook | HMAC signature |
| `POST /webhooks/marsool/orders` | POST | Receive Marsool order webhook | HMAC signature |
| **Outbound (called by Laravel to platforms)** | | | |
| `PUT /api/delivery/orders/{id}/status` | PUT | Push status update to delivery platform | Bearer token + `orders.manage` |
| `GET /api/delivery/orders` | GET | List delivery orders (unified across platforms) | Bearer token + `orders.view` |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `DeliveryPlatformConfigService` | CRUD on platform credentials and settings |
| `DeliveryOrderService` | Fetches delivery orders from backend; manages order queue state |
| `MenuSyncTriggerService` | Triggers menu sync from POS UI; polls sync status |
| `DeliveryOrderAlertService` | Plays audio alert and shows notification on new delivery order |

### 5.3 Laravel-Side Services (Backend)
| Service Class | Purpose |
|---|---|
| `DeliveryPlatformInterface` | Common interface: `syncMenu()`, `pushOrderStatus()`, `fetchOrders()` |
| `HungerStationAdapter` | HungerStation-specific API calls; menu format mapping |
| `JahezAdapter` | Jahez-specific API calls; menu format mapping |
| `MarsoolAdapter` | Marsool-specific API calls; menu format mapping |
| `WebhookVerificationService` | Validates HMAC signatures on incoming webhooks |
| `OrderIngestService` | Converts platform-specific order payload to unified order format |
| `MenuSyncJob` | Queued job: assembles product catalog and pushes to platform API |
| `StatusPushJob` | Queued job: pushes order status updates to platform API |

---

## 6. Full Database Schema

### 6.1 Tables

#### `delivery_platform_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| platform | VARCHAR(50) | NOT NULL | hungerstation, jahez, marsool |
| api_key | TEXT | NOT NULL | Encrypted |
| merchant_id | VARCHAR(100) | NULLABLE | Platform merchant ID |
| webhook_secret | TEXT | NULLABLE | For signature verification |
| branch_id_on_platform | VARCHAR(100) | NULLABLE | |
| is_enabled | BOOLEAN | DEFAULT FALSE | |
| auto_accept | BOOLEAN | DEFAULT TRUE | |
| throttle_limit | INT | NULLABLE | Max orders per 15 minutes |
| last_menu_sync_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE delivery_platform_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    platform VARCHAR(50) NOT NULL,
    api_key TEXT NOT NULL,
    merchant_id VARCHAR(100),
    webhook_secret TEXT,
    branch_id_on_platform VARCHAR(100),
    is_enabled BOOLEAN DEFAULT FALSE,
    auto_accept BOOLEAN DEFAULT TRUE,
    throttle_limit INT,
    last_menu_sync_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, platform)
);
```

#### `delivery_order_mappings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| order_id | UUID | FK → orders(id), NOT NULL | Local order |
| platform | VARCHAR(50) | NOT NULL | |
| external_order_id | VARCHAR(100) | NOT NULL | Platform's order ID |
| external_status | VARCHAR(50) | NULLABLE | Last known platform status |
| commission_amount | DECIMAL(12,2) | DEFAULT 0 | Platform commission |
| commission_percent | DECIMAL(5,2) | NULLABLE | Commission rate |
| raw_payload | JSONB | NULLABLE | Original webhook payload for debugging |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE delivery_order_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id),
    platform VARCHAR(50) NOT NULL,
    external_order_id VARCHAR(100) NOT NULL,
    external_status VARCHAR(50),
    commission_amount DECIMAL(12,2) DEFAULT 0,
    commission_percent DECIMAL(5,2),
    raw_payload JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `delivery_menu_sync_logs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| platform | VARCHAR(50) | NOT NULL | |
| status | VARCHAR(20) | NOT NULL | success, partial, failed |
| items_synced | INT | DEFAULT 0 | |
| items_failed | INT | DEFAULT 0 | |
| error_details | JSONB | NULLABLE | Failed items with error messages |
| started_at | TIMESTAMP | DEFAULT NOW() | |
| completed_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE delivery_menu_sync_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    platform VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    items_synced INT DEFAULT 0,
    items_failed INT DEFAULT 0,
    error_details JSONB,
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `delivery_configs_store_platform` | (store_id, platform) | UNIQUE | One config per platform per store |
| `delivery_mappings_external` | (platform, external_order_id) | UNIQUE | External order dedup |
| `delivery_mappings_order` | order_id | B-TREE | Find delivery info for an order |
| `delivery_sync_logs_store_platform` | (store_id, platform) | B-TREE | Sync log queries |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ delivery_platform_configs
stores ──1:N──▶ delivery_menu_sync_logs
orders ──1:1──▶ delivery_order_mappings
delivery_platform_configs ──context──▶ delivery_menu_sync_logs (same platform)
```

---

## 7. Business Rules

1. **One config per platform per store** — each branch can have its own credentials and settings for each delivery platform
2. **Menu sync triggers** — menu sync is triggered: (a) manually from UI, (b) automatically every 6 hours via cron, (c) on product create/update/delete if auto-sync is enabled
3. **Stock availability auto-toggle** — when a product goes to 0 stock, it is automatically marked unavailable on all connected platforms within 60 seconds; when restocked, it is re-enabled
4. **Webhook signature verification** — all incoming webhooks are verified via HMAC-SHA256 (or platform-specific algorithm); unsigned requests are rejected with 401
5. **Order deduplication** — `external_order_id` is checked before creating a new order; duplicate webhooks are acknowledged with 200 OK but not processed
6. **Auto-accept timer** — if auto-accept is disabled, the order must be manually accepted within the platform's timeout (typically 5 minutes); unaccepted orders are auto-rejected
7. **Order throttling** — if `throttle_limit` is set and the number of active orders exceeds it, new orders are auto-rejected with a "store busy" response; the platform will mark the store as temporarily unavailable
8. **Commission recording** — commission amount and percentage are recorded per order for financial reconciliation; commission is calculated based on the platform's rate (stored in config or parsed from webhook)
9. **Status push retry** — if a status push to the delivery platform fails, it is retried with exponential backoff (3 attempts, 30s / 2min / 10min); failures are logged
10. **Platform credential encryption** — API keys and webhook secrets are stored encrypted at rest (AES-256) in the database

---

## 8. Additional Delivery Platform Support

Beyond the three fully-documented adapters (HungerStation, Jahez, Marsool), the system supports additional delivery platforms through a **generic adapter architecture**. These platforms are configured and managed at the **platform admin level** and exposed to providers through the same unified delivery order queue.

### 8.1 Supported Additional Platforms

| Platform | Region | Integration Type | Status |
|---|---|---|---|
| **Keeta** (by STC) | Saudi Arabia | REST API webhook | Platform-managed |
| **Noon Food** | UAE, Saudi Arabia | REST API webhook | Platform-managed |
| **Ninja** | Saudi Arabia | REST API webhook | Platform-managed |
| **The Chefz** | Saudi Arabia, UAE | REST API webhook | Platform-managed |
| **Talabat** | GCC-wide | REST API webhook | Platform-managed |
| **ToYou** | Saudi Arabia | REST API webhook | Platform-managed |
| **Carriage** | GCC-wide | REST API webhook | Platform-managed |

### 8.2 Platform-Managed vs Store-Managed

| Aspect | HungerStation / Jahez / Marsool | Additional Platforms |
|---|---|---|
| **Credential Storage** | Per-store (provider configures) | Platform-level (admin configures) |
| **Menu Sync** | Store triggers manually or auto | Platform syncs centrally, maps to store catalog |
| **Order Ingestion** | Direct webhook to store config | Webhook to platform → routed to store |
| **Configuration UI** | Full settings in provider POS | Simple enable/disable toggle |
| **Commission Rates** | Configurable per store | Set by platform admin |

### 8.3 Generic Adapter Architecture

The generic adapter implements `DeliveryPlatformInterface` with the following flow:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Delivery App    │    │  Platform API   │    │  Provider POS   │
│ (Keeta, etc.)   │    │  (Laravel)      │    │  (Flutter)      │
└────────┬────────┘    └────────┬────────┘    └────────┬────────┘
         │                      │                      │
         │ POST /webhooks/generic/{platform}           │
         │─────────────────────▶│                      │
         │                      │ Route to store by    │
         │                      │ branch_id/merchant   │
         │                      │─────────────────────▶│
         │                      │   (order appears in  │
         │                      │    delivery queue)   │
         │                      │                      │
         │                      │◀─────────────────────│
         │                      │   Status update      │
         │◀─────────────────────│                      │
         │    (via platform)    │                      │
```

### 8.4 Provider Configuration for Generic Platforms

Providers see a simplified configuration screen for platform-managed integrations:

| Field | Detail |
|---|---|
| **Platform Toggle** | Enable/disable receiving orders from this platform |
| **Branch Mapping** | Confirm which branch maps to platform's branch ID (usually auto-detected) |
| **Auto-Accept** | Toggle auto-accept for this platform |
| **Operating Hours** | Inherit from store hours or set platform-specific hours |

### 8.5 Adding New Delivery Platforms

To add a new delivery platform:

1. **Platform Admin** creates new entry in `platform_delivery_integrations` table (platform admin feature)
2. **Platform Admin** configures OAuth/API credentials at platform level
3. **Platform Admin** creates adapter class extending `GenericDeliveryAdapter`:
   ```php
   class KeetaAdapter extends GenericDeliveryAdapter
   {
       protected string $platform = 'keeta';
       protected string $baseUrl = 'https://api.keeta.sa/merchant/v1';
       
       protected function transformOrderPayload(array $raw): array
       {
           // Map Keeta's order format to unified format
       }
       
       protected function transformStatusForPlatform(string $status): string
       {
           // Map internal status to Keeta's expected status codes
       }
   }
   ```
4. **Provider** enables the platform in their delivery settings
5. **Orders flow** through the unified delivery queue

### 8.6 Database: Platform-Level Configuration

Platform-managed integrations store credentials at the platform level, not per-store:

#### `platform_delivery_integrations` (Platform Admin)
| Column | Type | Notes |
|---|---|---|
| id | UUID | PK |
| platform_slug | VARCHAR(50) | keeta, noon_food, ninja, talabat, etc. |
| display_name | VARCHAR(100) | "Keeta by STC" |
| api_base_url | TEXT | |
| client_id | TEXT | Platform OAuth client |
| client_secret_encrypted | TEXT | Platform OAuth secret |
| webhook_secret_encrypted | TEXT | For signature verification |
| default_commission_percent | DECIMAL(5,2) | Default commission rate |
| is_active | BOOLEAN | Enabled at platform level |
| supported_countries | JSONB | ["SA", "AE"] |
| logo_url | TEXT | Platform logo |
| created_at | TIMESTAMP | |

Stores only need a simple mapping table:

#### `store_delivery_platform_enrollments`
| Column | Type | Notes |
|---|---|---|
| id | UUID | PK |
| store_id | UUID | FK → stores |
| platform_slug | VARCHAR(50) | FK → platform_delivery_integrations |
| merchant_id_on_platform | VARCHAR(100) | Store's ID on the delivery platform |
| is_enabled | BOOLEAN | Provider toggle |
| auto_accept | BOOLEAN | |
| created_at | TIMESTAMP | |

This allows the platform to add support for new delivery apps without requiring providers to manage API credentials.
