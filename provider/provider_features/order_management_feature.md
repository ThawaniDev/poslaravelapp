# Order Management — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Orders, Returns, Exchanges, Order Tracking, Delivery Fulfilment  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · Dio · Laravel 11 REST API  

---

## 1. Feature Overview

Order Management governs the complete lifecycle of every sale from the moment a transaction is completed at the POS through fulfilment, delivery, returns, and exchanges. For walk-in register sales, the "order" is created and fulfilled instantly. For delivery orders (from third-party platforms or phone orders), the order moves through preparation and dispatch stages. Returns and exchanges are handled as linked child orders referencing the original transaction.

### What This Feature Does
- **Order creation** — automatic on POS transaction completion; manual for phone / web orders
- **Order lifecycle** — statuses: new → preparing → ready → dispatched → delivered / picked_up / completed
- **Order queue display** — kitchen/prep display for pending orders (especially F&B)
- **Delivery order management** — orders from HungerStation, Jahez, Marsool, or phone orders with driver assignment
- **Return processing** — full or partial returns against a previous order; restores stock, issues refund
- **Exchange processing** — return items and add replacement items in a single transaction; calculates net payment
- **Order history** — searchable archive of all orders with filters by date, customer, status, source
- **Receipt reprinting** — reprint receipt for any historical order
- **Order notes** — internal notes and customer-visible notes per order
- **Order source tracking** — identifies where the order originated: POS, Thawani app, HungerStation, Jahez, phone, web
- **Void / cancel** — cancel an order before fulfilment; requires manager approval after grace period
- **Split payment orders** — orders can be paid with multiple payment methods (handled via Payments & Finance)
- **Pending orders** — orders that are saved but not yet paid (layaway / deferred payment)

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **POS Terminal** | Orders are created from POS transactions |
| **Product & Catalog Management** | Order items reference products, variants, modifiers |
| **Inventory Management** | Stock deducted on order completion; restored on return |
| **Payments & Finance** | Payment records linked to orders |
| **Customer Management** | Optional customer linkage; loyalty points earned on orders |
| **Delivery Integrations** | Delivery orders ingested from third-party platforms |
| **ZATCA Compliance** | Each completed order generates a ZATCA-compliant invoice |
| **Roles & Permissions** | `orders.view`, `orders.manage`, `orders.return`, `orders.void` permissions |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Reports & Analytics** | Revenue, order count, average ticket, refund rate — all from order data |
| **Customer Management** | Purchase history and loyalty calculations reference orders |
| **Notifications** | Order status changes trigger notifications |
| **Thawani Integration** | Order data synced to Thawani platform |

### Features to Review After Changing This Feature
1. **Reports & Analytics** — revenue aggregation queries read from orders tables
2. **ZATCA Compliance** — invoice generation triggers from order completion
3. **Inventory Management** — stock deduction/restoration logic linked to order status transitions
4. **Delivery Integrations** — status updates pushed to delivery platforms on order lifecycle changes

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local orders, order items, returns stored offline-first |
| **riverpod** / **flutter_bloc** | State management for order queue, order detail, return form |
| **dio** | HTTP client for order sync, status updates to cloud |
| **uuid** | Generate UUIDs for orders, returns, exchanges |
| **intl** | Currency formatting, date/time display on order screens |
| **flutter_local_notifications** | Local alerts for new incoming delivery orders |

### 3.2 Technologies
- **Flutter 3.x Desktop** — order management screens, order queue display
- **Dart** — order lifecycle state machine, return/exchange calculation logic
- **SQLite (via Drift)** — local order storage; all orders searchable offline
- **PostgreSQL** — cloud order master; definitive record for reporting
- **Laravel 11 REST API** — order CRUD, status transitions, return processing, sync
- **WebSocket / polling** — real-time incoming delivery order notifications

---

## 4. Screens

### 4.1 Order Queue Screen
| Field | Detail |
|---|---|
| **Route** | `/orders/queue` |
| **Purpose** | Real-time display of pending orders for preparation |
| **Layout** | Kanban columns: New → Preparing → Ready → Dispatched (for delivery) |
| **Card Content** | Order #, Source (POS/delivery platform), Items summary, Time elapsed, Customer name |
| **Actions** | Move to next stage (drag or button), View details, Print kitchen ticket |
| **Auto-refresh** | Polls every 15 seconds for new delivery orders; local POS orders appear instantly |
| **Access** | `orders.view` |

### 4.2 Order History Screen
| Field | Detail |
|---|---|
| **Route** | `/orders/history` |
| **Purpose** | Searchable archive of all completed and active orders |
| **Table Columns** | Order #, Date/time, Customer, Items, Total, Payment method, Source, Status, Actions |
| **Filters** | Date range, Status, Source (POS/delivery/phone), Payment method, Customer, Cashier |
| **Search** | By order number, customer name, phone, product name |
| **Row Actions** | View detail, Reprint receipt, Process return, Process exchange |
| **Export** | CSV / Excel export |
| **Access** | `orders.view` |

### 4.3 Order Detail Screen
| Field | Detail |
|---|---|
| **Route** | `/orders/{id}` |
| **Purpose** | Complete order detail with all line items, payments, and history |
| **Sections** | Header (order #, date, status, source), Customer info, Line items (product, variant, modifiers, qty, unit price, total), Payments (method, amount, reference), Tax breakdown, Returns/exchanges linked to this order, Status timeline, Notes |
| **Actions** | Reprint receipt, Process return, Process exchange, Void (if eligible), Add note |
| **Access** | `orders.view` |

### 4.4 Return Processing Screen
| Field | Detail |
|---|---|
| **Route** | `/orders/{id}/return` |
| **Purpose** | Process full or partial return against a previous order |
| **Flow** | 1. Select items to return (checkbox + qty) → 2. Select return reason → 3. Choose refund method (same as original / cash / store credit) → 4. Confirm |
| **Reason Codes** | Defective, Wrong item, Customer changed mind, Expired, Other |
| **Stock Restoration** | Returned items are added back to stock (unless defective → routed to waste) |
| **ZATCA** | Generates a credit note linked to original invoice |
| **Access** | `orders.return` |

### 4.5 Exchange Processing Screen
| Field | Detail |
|---|---|
| **Route** | `/orders/{id}/exchange` |
| **Purpose** | Return items and replace with different items in a single transaction |
| **Flow** | 1. Select items to return → 2. Scan/add replacement items → 3. System calculates net: if replacement > return → customer pays difference; if return > replacement → refund difference → 4. Process payment/refund → 5. Confirm |
| **Access** | `orders.return` + `orders.manage` |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/orders` | GET | Paginated order list with filters | Bearer token + `orders.view` |
| `GET /api/orders/{id}` | GET | Order detail with items, payments, returns | Bearer token + `orders.view` |
| `POST /api/orders` | POST | Create order (from phone/web; POS orders sync automatically) | Bearer token + `orders.manage` |
| `PUT /api/orders/{id}/status` | PUT | Update order status (lifecycle transition) | Bearer token + `orders.manage` |
| `POST /api/orders/{id}/void` | POST | Void/cancel order | Bearer token + `orders.void` |
| `POST /api/orders/{id}/return` | POST | Process return (full or partial) | Bearer token + `orders.return` |
| `POST /api/orders/{id}/exchange` | POST | Process exchange | Bearer token + `orders.return` |
| `GET /api/orders/{id}/receipt` | GET | Get receipt data for reprinting | Bearer token |
| `POST /api/orders/{id}/notes` | POST | Add note to order | Bearer token + `orders.manage` |
| `GET /api/pos/orders/sync?since={ts}` | GET | Delta sync of orders for POS terminal | Bearer token |
| `GET /api/orders/queue` | GET | Active order queue for preparation display | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `OrderRepository` | Local Drift DB CRUD for orders; reactive streams for order queue |
| `OrderLifecycleService` | State machine for order status transitions with validation |
| `ReturnService` | Calculates refund amounts, handles stock restoration, creates credit note |
| `ExchangeService` | Manages return + replacement flow, calculates net payment |
| `OrderSyncService` | Delta sync of orders between local and cloud |
| `OrderSearchService` | Full-text search on local order history |
| `OrderQueueService` | Manages preparation queue with real-time updates |
| `ReceiptReprintService` | Retrieves historical order data and sends to receipt printer |

---

## 6. Full Database Schema

### 6.1 Tables

#### `orders`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| transaction_id | UUID | FK → transactions(id), NULLABLE | Link to POS transaction |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| order_number | VARCHAR(50) | NOT NULL, UNIQUE per store | Auto-generated: ORD-YYYYMMDD-NNNN |
| source | VARCHAR(30) | NOT NULL | pos, thawani, hungerstation, jahez, marsool, phone, web |
| status | VARCHAR(30) | NOT NULL, DEFAULT 'new' | new, preparing, ready, dispatched, delivered, picked_up, completed, cancelled, voided |
| subtotal | DECIMAL(12,2) | NOT NULL | Before tax |
| tax_amount | DECIMAL(12,2) | NOT NULL | |
| discount_amount | DECIMAL(12,2) | DEFAULT 0 | |
| total | DECIMAL(12,2) | NOT NULL | Final total |
| notes | TEXT | NULLABLE | Internal notes |
| customer_notes | TEXT | NULLABLE | Customer-facing notes |
| external_order_id | VARCHAR(100) | NULLABLE | ID from delivery platform |
| delivery_address | TEXT | NULLABLE | For delivery orders |
| created_by | UUID | FK → users(id), NULLABLE | Cashier or system |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    transaction_id UUID REFERENCES transactions(id),
    customer_id UUID REFERENCES customers(id),
    order_number VARCHAR(50) NOT NULL,
    source VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    notes TEXT,
    customer_notes TEXT,
    external_order_id VARCHAR(100),
    delivery_address TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, order_number)
);
```

#### `order_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| order_id | UUID | FK → orders(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| variant_id | UUID | FK → product_variants(id), NULLABLE | |
| product_name | VARCHAR(255) | NOT NULL | Snapshot at time of order |
| product_name_ar | VARCHAR(255) | NULLABLE | |
| quantity | DECIMAL(12,3) | NOT NULL | |
| unit_price | DECIMAL(12,2) | NOT NULL | Price at time of sale |
| discount_amount | DECIMAL(12,2) | DEFAULT 0 | |
| tax_amount | DECIMAL(12,2) | DEFAULT 0 | |
| total | DECIMAL(12,2) | NOT NULL | (unit_price × qty) - discount + tax |
| notes | TEXT | NULLABLE | Item-level notes |

```sql
CREATE TABLE order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    variant_id UUID REFERENCES product_variants(id),
    product_name VARCHAR(255) NOT NULL,
    product_name_ar VARCHAR(255),
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    notes TEXT
);
```

#### `order_item_modifiers`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| order_item_id | UUID | FK → order_items(id) ON DELETE CASCADE, NOT NULL | |
| modifier_option_id | UUID | FK → modifier_options(id), NULLABLE | |
| modifier_name | VARCHAR(255) | NOT NULL | Snapshot |
| modifier_name_ar | VARCHAR(255) | NULLABLE | |
| price_adjustment | DECIMAL(12,2) | DEFAULT 0 | |

```sql
CREATE TABLE order_item_modifiers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_item_id UUID NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,
    modifier_option_id UUID REFERENCES modifier_options(id),
    modifier_name VARCHAR(255) NOT NULL,
    modifier_name_ar VARCHAR(255),
    price_adjustment DECIMAL(12,2) DEFAULT 0
);
```

#### `order_status_history`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| order_id | UUID | FK → orders(id) ON DELETE CASCADE, NOT NULL | |
| from_status | VARCHAR(30) | NULLABLE | NULL for initial creation |
| to_status | VARCHAR(30) | NOT NULL | |
| changed_by | UUID | FK → users(id), NULLABLE | |
| notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE order_status_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    from_status VARCHAR(30),
    to_status VARCHAR(30) NOT NULL,
    changed_by UUID REFERENCES users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `returns`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| order_id | UUID | FK → orders(id), NOT NULL | Original order |
| return_number | VARCHAR(50) | NOT NULL, UNIQUE per store | RTN-YYYYMMDD-NNNN |
| type | VARCHAR(20) | NOT NULL | full, partial |
| reason_code | VARCHAR(50) | NOT NULL | defective, wrong_item, changed_mind, expired, other |
| refund_method | VARCHAR(30) | NOT NULL | original_method, cash, store_credit |
| subtotal | DECIMAL(12,2) | NOT NULL | |
| tax_amount | DECIMAL(12,2) | NOT NULL | |
| total_refund | DECIMAL(12,2) | NOT NULL | |
| notes | TEXT | NULLABLE | |
| processed_by | UUID | FK → users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE returns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    return_number VARCHAR(50) NOT NULL,
    type VARCHAR(20) NOT NULL,
    reason_code VARCHAR(50) NOT NULL,
    refund_method VARCHAR(30) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    total_refund DECIMAL(12,2) NOT NULL,
    notes TEXT,
    processed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, return_number)
);
```

#### `return_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| return_id | UUID | FK → returns(id) ON DELETE CASCADE, NOT NULL | |
| order_item_id | UUID | FK → order_items(id), NOT NULL | Original order item |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity | DECIMAL(12,3) | NOT NULL | Quantity returned |
| unit_price | DECIMAL(12,2) | NOT NULL | Price at original sale |
| refund_amount | DECIMAL(12,2) | NOT NULL | |
| restore_stock | BOOLEAN | DEFAULT TRUE | FALSE if defective → waste |

```sql
CREATE TABLE return_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_id UUID NOT NULL REFERENCES returns(id) ON DELETE CASCADE,
    order_item_id UUID NOT NULL REFERENCES order_items(id),
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    refund_amount DECIMAL(12,2) NOT NULL,
    restore_stock BOOLEAN DEFAULT TRUE
);
```

#### `exchanges`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| original_order_id | UUID | FK → orders(id), NOT NULL | |
| return_id | UUID | FK → returns(id), NOT NULL | Return half of exchange |
| new_order_id | UUID | FK → orders(id), NOT NULL | Replacement order |
| net_amount | DECIMAL(12,2) | NOT NULL | Positive = customer pays, Negative = refund |
| processed_by | UUID | FK → users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE exchanges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    original_order_id UUID NOT NULL REFERENCES orders(id),
    return_id UUID NOT NULL REFERENCES returns(id),
    new_order_id UUID NOT NULL REFERENCES orders(id),
    net_amount DECIMAL(12,2) NOT NULL,
    processed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `order_delivery_info`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| order_id | UUID | FK → orders(id) ON DELETE CASCADE, NOT NULL, UNIQUE | |
| platform | VARCHAR(50) | NOT NULL | hungerstation, jahez, marsool, internal, phone |
| driver_name | VARCHAR(255) | NULLABLE | |
| driver_phone | VARCHAR(50) | NULLABLE | |
| estimated_delivery | TIMESTAMP | NULLABLE | |
| actual_delivery | TIMESTAMP | NULLABLE | |
| delivery_fee | DECIMAL(12,2) | DEFAULT 0 | |
| tracking_url | TEXT | NULLABLE | |

```sql
CREATE TABLE order_delivery_info (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
    platform VARCHAR(50) NOT NULL,
    driver_name VARCHAR(255),
    driver_phone VARCHAR(50),
    estimated_delivery TIMESTAMP,
    actual_delivery TIMESTAMP,
    delivery_fee DECIMAL(12,2) DEFAULT 0,
    tracking_url TEXT
);
```

#### `pending_orders`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| items_json | JSONB | NOT NULL | Cart items snapshot |
| total | DECIMAL(12,2) | NOT NULL | |
| notes | TEXT | NULLABLE | |
| created_by | UUID | FK → users(id), NOT NULL | |
| expires_at | TIMESTAMP | NULLABLE | Auto-cancel after expiry |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE pending_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    items_json JSONB NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    notes TEXT,
    created_by UUID NOT NULL REFERENCES users(id),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `orders_store_number` | (store_id, order_number) | UNIQUE | Order number lookup |
| `orders_store_date` | (store_id, created_at) | B-TREE | Date-range queries |
| `orders_store_status` | (store_id, status) | B-TREE | Active order queue |
| `orders_customer` | customer_id | B-TREE | Customer purchase history |
| `orders_source` | source | B-TREE | Filter by order source |
| `orders_external_id` | external_order_id | B-TREE | Delivery platform ID lookup |
| `order_items_order` | order_id | B-TREE | Item listing per order |
| `order_items_product` | product_id | B-TREE | Product sales history |
| `returns_order` | order_id | B-TREE | Returns per order |
| `returns_store_date` | (store_id, created_at) | B-TREE | Return history |
| `order_status_history_order` | order_id | B-TREE | Status timeline |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ orders
orders ──1:N──▶ order_items
orders ──1:N──▶ order_status_history
orders ──1:1──▶ order_delivery_info
orders ──1:N──▶ returns
order_items ──1:N──▶ order_item_modifiers
order_items ──N:1──▶ products
order_items ──N:1──▶ product_variants
returns ──1:N──▶ return_items
return_items ──N:1──▶ order_items (original)
return_items ──N:1──▶ products
exchanges ──N:1──▶ orders (original)
exchanges ──N:1──▶ returns
exchanges ──N:1──▶ orders (new)
orders ──N:1──▶ customers
orders ──N:1──▶ transactions
stores ──1:N──▶ pending_orders
```

---

## 7. Business Rules

1. **Order number sequence** — order numbers are auto-generated per store: ORD-YYYYMMDD-NNNN; the sequence resets daily
2. **Status transitions are unidirectional** — an order can only move forward in the lifecycle; the only exception is void/cancel which can happen from any pre-completed status
3. **Void grace period** — orders can be voided without approval within 5 minutes of creation; after that, manager approval (`orders.void`) is required
4. **Return window** — returns are accepted within 30 days of the original order by default (configurable per store); beyond that, the return is rejected by the system
5. **Partial return** — individual items or partial quantities can be returned; the system recalculates tax proportionally
6. **Stock restoration on return** — returned items are added back to stock unless the return reason is "defective" or "expired", in which case they are routed to waste tracking
7. **Exchange net calculation** — net amount = (replacement items total) − (returned items total); positive = customer pays, negative = refund issued
8. **ZATCA credit note** — every return generates a ZATCA-compliant credit note referencing the original invoice number
9. **Delivery order auto-accept** — incoming delivery platform orders are auto-accepted if auto-accept is enabled; otherwise they appear in the queue with a 5-minute accept timer (platform-specific timeout)
10. **Order source immutability** — the `source` field is set on creation and cannot be changed
11. **Pending order expiry** — pending orders auto-cancel after their `expires_at` timestamp; default expiry is 7 days
12. **Product name snapshot** — order items store the product name at the time of sale (`product_name`, `product_name_ar`), not a live reference, ensuring historical accuracy even if the product is later renamed or deleted
