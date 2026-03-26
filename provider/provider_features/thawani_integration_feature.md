# Thawani Integration — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Thawani Marketplace Connection, Product Sync, Order Sync, Delivery Management  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11 · Thawani API  

---

## 1. Feature Overview

Thawani Integration connects the provider's POS system to the existing Thawani e-commerce marketplace. Products listed in the POS can be published to Thawani for online ordering; incoming online orders from Thawani appear on the POS for fulfillment. This bridges the physical store (POS) with the online storefront (Thawani app).

### What This Feature Does
- **Product publishing** — push POS product catalog to Thawani marketplace; manage which products are available online
- **Price sync** — maintain separate online prices or sync POS prices to Thawani; support promotional online pricing
- **Inventory sync** — real-time stock level sync to Thawani; auto-hide products when out of stock
- **Order ingestion** — receive online orders from Thawani into POS order queue; audio alert for new orders
- **Order status updates** — update Thawani order status from POS (preparing, ready, dispatched, completed)
- **Store availability** — toggle online store open/closed from POS; set operating hours
- **Menu management** — configure which POS categories/products appear on Thawani; reorder for online display
- **Delivery zone management** — define delivery zones and charges that sync to Thawani
- **Rating & reviews** — view customer ratings and reviews from Thawani orders on POS/Dashboard
- **Settlement reports** — view Thawani settlement amounts and reconcile with POS financial data

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Product Catalog** | Products to publish to Thawani |
| **Inventory Management** | Stock levels for availability sync |
| **Order Management** | Order processing workflow |
| **Offline/Online Sync** | Network connectivity for API calls |
| **Payments & Finance** | Settlement reconciliation |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Thawani order alerts on POS |
| **Reports & Analytics** | Online vs in-store sales reports |
| **Delivery Integrations** | Thawani as a delivery order source |
| **Notifications** | New online order notifications |

### Features to Review After Changing This Feature
1. **Product Catalog** — product fields that map to Thawani listing
2. **Order Management** — Thawani order status mapping

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **dio** | HTTP client for Thawani API calls |
| **drift** | SQLite ORM — local Thawani config and order cache |
| **riverpod** / **flutter_bloc** | State management for Thawani connection status, order queue |
| **web_socket_channel** | Real-time order notifications from Thawani via WebSocket |
| **audioplayers** | Sound alert for new Thawani orders |

### 3.2 Technologies
- **Thawani REST API** — existing Thawani marketplace API for product publishing, order management, store management
- **Laravel Thawani Service** — Laravel backend acts as middleware between POS and Thawani API; handles authentication, rate limiting, and data transformation
- **Webhook receiver** — Laravel receives Thawani webhooks for new orders, order cancellations, and settlements
- **WebSocket relay** — Thawani webhook triggers WebSocket event to POS for real-time order notification
- **OAuth2** — Thawani API authentication via OAuth2 tokens managed by Laravel backend

---

## 4. Screens

### 4.1 Thawani Connection Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/thawani` |
| **Purpose** | Configure Thawani marketplace connection |
| **Layout** | Connection status indicator (Connected / Disconnected), Store ID mapping, API credentials (managed via Web Dashboard), Auto-sync toggles (products, inventory, orders), Operating hours configuration |
| **Actions** | Test Connection, Force Sync Now, Disconnect |
| **Access** | `settings.thawani` permission (Owner) |

### 4.2 Online Menu Management Screen
| Field | Detail |
|---|---|
| **Route** | `/thawani/menu` |
| **Purpose** | Manage which products appear on Thawani |
| **Layout** | Category tree with product toggles (published / hidden); drag-to-reorder for online display order; online price override per product; product image selection for online listing |
| **Bulk Actions** | Publish All, Hide All, Sync Prices (copy POS prices to online) |
| **Access** | `thawani.menu` permission |

### 4.3 Thawani Orders Queue Screen
| Field | Detail |
|---|---|
| **Route** | `/thawani/orders` |
| **Purpose** | View and manage incoming online orders |
| **Layout** | Kanban columns: New → Accepted → Preparing → Ready → Dispatched → Completed; each card shows order number, items, customer name, delivery type (delivery/pickup), time elapsed |
| **Actions** | Accept / Reject order, Update status, Print order ticket, View order detail |
| **Sound Alert** | Persistent chime for new orders until acknowledged |
| **Access** | All POS staff (filtered by branch) |

### 4.4 Settlement Reports Screen
| Field | Detail |
|---|---|
| **Route** | `/thawani/settlements` |
| **Purpose** | View Thawani payment settlements |
| **Layout** | Data table — settlement date, gross amount, commission, net amount, order count; expandable to see individual orders per settlement |
| **Reconciliation** | Side-by-side with POS revenue for same period |
| **Access** | `finance.settlements` permission (Owner, Accountant) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/thawani/status` | GET | Connection status and Thawani store info | Bearer token |
| `POST /api/thawani/connect` | POST | Initialize Thawani store connection | Bearer token, Owner |
| `POST /api/thawani/disconnect` | POST | Disconnect Thawani store | Bearer token, Owner |
| `POST /api/thawani/products/sync` | POST | Push product catalog to Thawani | Bearer token, `thawani.menu` |
| `PUT /api/thawani/products/{id}/publish` | PUT | Publish/unpublish single product | Bearer token, `thawani.menu` |
| `POST /api/thawani/inventory/sync` | POST | Push current stock levels to Thawani | Bearer token |
| `GET /api/thawani/orders` | GET | List Thawani orders (filterable) | Bearer token |
| `PUT /api/thawani/orders/{id}/status` | PUT | Update Thawani order status | Bearer token |
| `POST /api/thawani/orders/{id}/accept` | POST | Accept incoming order | Bearer token |
| `POST /api/thawani/orders/{id}/reject` | POST | Reject incoming order with reason | Bearer token |
| `GET /api/thawani/settlements` | GET | Settlement reports | Bearer token, `finance.settlements` |
| `PUT /api/thawani/store/availability` | PUT | Set store open/closed and hours | Bearer token, `thawani.menu` |
| `POST /webhook/thawani/orders` | POST | Webhook: new order from Thawani | Webhook signature |
| `POST /webhook/thawani/settlements` | POST | Webhook: settlement notification | Webhook signature |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `ThawaniConnectionService` | Connection management, status monitoring |
| `ThawaniMenuService` | Online menu management — publish/hide products, price overrides |
| `ThawaniOrderService` | Order queue management — accept, reject, status updates |
| `ThawaniInventorySyncService` | Real-time stock level push to Thawani |
| `ThawaniSettlementService` | Settlement data display and reconciliation |
| `ThawaniNotificationService` | Sound alerts and visual notifications for new online orders |

---

## 6. Full Database Schema

### 6.1 Tables

#### `thawani_store_config`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL, UNIQUE | |
| thawani_store_id | VARCHAR(100) | NOT NULL | Thawani marketplace store ID |
| is_connected | BOOLEAN | DEFAULT FALSE | |
| auto_sync_products | BOOLEAN | DEFAULT TRUE | |
| auto_sync_inventory | BOOLEAN | DEFAULT TRUE | |
| auto_accept_orders | BOOLEAN | DEFAULT FALSE | |
| operating_hours_json | JSONB | NULLABLE | {"mon": {"open": "09:00", "close": "22:00"}, ...} |
| commission_rate | DECIMAL(5,2) | NULLABLE | Thawani commission percentage |
| connected_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE thawani_store_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    thawani_store_id VARCHAR(100) NOT NULL,
    is_connected BOOLEAN DEFAULT FALSE,
    auto_sync_products BOOLEAN DEFAULT TRUE,
    auto_sync_inventory BOOLEAN DEFAULT TRUE,
    auto_accept_orders BOOLEAN DEFAULT FALSE,
    operating_hours_json JSONB,
    commission_rate DECIMAL(5,2),
    connected_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `thawani_product_mappings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | Local product |
| thawani_product_id | VARCHAR(100) | NOT NULL | Thawani product ID |
| is_published | BOOLEAN | DEFAULT TRUE | |
| online_price | DECIMAL(12,3) | NULLABLE | Override price for online; NULL = use POS price |
| display_order | INTEGER | DEFAULT 0 | Order in online menu |
| last_synced_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE thawani_product_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    thawani_product_id VARCHAR(100) NOT NULL,
    is_published BOOLEAN DEFAULT TRUE,
    online_price DECIMAL(12,3),
    display_order INTEGER DEFAULT 0,
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, product_id)
);
```

#### `thawani_order_mappings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| order_id | UUID | FK → orders(id), NULLABLE | Local POS order (created on accept) |
| thawani_order_id | VARCHAR(100) | NOT NULL | Thawani order ID |
| thawani_order_number | VARCHAR(50) | NOT NULL | Customer-facing order number |
| status | VARCHAR(30) | NOT NULL | new, accepted, preparing, ready, dispatched, completed, rejected, cancelled |
| delivery_type | VARCHAR(20) | NOT NULL | delivery, pickup |
| customer_name | VARCHAR(200) | NULLABLE | |
| customer_phone | VARCHAR(20) | NULLABLE | |
| delivery_address | TEXT | NULLABLE | |
| order_total | DECIMAL(12,3) | NOT NULL | |
| commission_amount | DECIMAL(12,3) | NULLABLE | |
| rejection_reason | TEXT | NULLABLE | |
| accepted_at | TIMESTAMP | NULLABLE | |
| prepared_at | TIMESTAMP | NULLABLE | |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE thawani_order_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID REFERENCES orders(id),
    thawani_order_id VARCHAR(100) NOT NULL,
    thawani_order_number VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    delivery_type VARCHAR(20) NOT NULL DEFAULT 'delivery',
    customer_name VARCHAR(200),
    customer_phone VARCHAR(20),
    delivery_address TEXT,
    order_total DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3),
    rejection_reason TEXT,
    accepted_at TIMESTAMP,
    prepared_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `thawani_settlements`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| settlement_date | DATE | NOT NULL | |
| gross_amount | DECIMAL(12,3) | NOT NULL | Total order value |
| commission_amount | DECIMAL(12,3) | NOT NULL | Thawani commission |
| net_amount | DECIMAL(12,3) | NOT NULL | Amount paid to store |
| order_count | INTEGER | NOT NULL | |
| thawani_reference | VARCHAR(100) | NULLABLE | Thawani settlement reference |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE thawani_settlements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    settlement_date DATE NOT NULL,
    gross_amount DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3) NOT NULL,
    net_amount DECIMAL(12,3) NOT NULL,
    order_count INTEGER NOT NULL,
    thawani_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, settlement_date, thawani_reference)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `thawani_products_store` | (store_id, is_published) | B-TREE | Published products query |
| `thawani_orders_store_status` | (store_id, status) | B-TREE | Order queue by status |
| `thawani_orders_thawani_id` | thawani_order_id | B-TREE UNIQUE | Lookup by Thawani ID |
| `thawani_settlements_store_date` | (store_id, settlement_date) | B-TREE | Settlement reports |

### 6.3 Relationships Diagram
```
stores ──1:1──▶ thawani_store_config
stores ──1:N──▶ thawani_product_mappings ◀──N:1── products
stores ──1:N──▶ thawani_order_mappings ──N:1──▶ orders (optional)
stores ──1:N──▶ thawani_settlements
```

---

## 7. Business Rules

1. **Order acceptance timer** — new Thawani orders must be accepted or rejected within 5 minutes; after timeout, the order is auto-rejected and customer is notified
2. **Auto-accept mode** — when enabled, orders are automatically accepted with a configurable preparation time estimate
3. **Stock sync on sale** — when a POS sale reduces stock to zero, the product is automatically marked as unavailable on Thawani within 60 seconds
4. **Price sync direction** — by default, POS prices are the master; online prices are overrides; if `online_price` is NULL, the POS price is used
5. **Commission deduction** — Thawani charges a commission (stored in `thawani_store_config.commission_rate`); this is deducted from the settlement, not from individual orders
6. **Order-to-POS mapping** — when a Thawani order is accepted, a corresponding POS order is created with source = "thawani"; this appears in regular POS reports
7. **Operating hours enforcement** — Thawani store is automatically set to "closed" outside configured operating hours; POS can override to close early or open late
8. **Disconnection behavior** — if the store disconnects from Thawani, all published products are unpublished; pending orders must be completed or cancelled before disconnection
9. **Image sync** — product images are synced to Thawani; the first image is used as the primary display image
10. **Settlement reconciliation** — settlement amounts should match the sum of completed Thawani orders minus commission; discrepancies are flagged in the settlement report
