# Inventory Management — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Stock Tracking, Goods Receipt, Adjustments, Transfers, Recipes, Waste, Purchase Orders  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · Dio · Laravel 11 REST API  

---

## 1. Feature Overview

Inventory Management tracks every unit of stock across all branches. It records how inventory enters the business (goods receipts, purchase orders), how it moves between branches (stock transfers), how it decreases (sales, waste, adjustments), and what levels should trigger replenishment. For food-service and manufacturing businesses, the recipe (bill of materials) engine decomposes finished products into their raw ingredients, automatically deducting ingredient stock on every sale.

### What This Feature Does
- **Real-time stock levels** — per product, per branch, with current quantity and value
- **Goods receipt** — record incoming stock with supplier, invoice number, cost price, batch number, expiry date
- **Stock adjustments** — increase or decrease stock manually with reason codes (damage, theft, count correction, sample)
- **Stock transfers** — move inventory between branches with pending/in-transit/received workflow
- **Purchase orders** — create POs to suppliers; link to goods receipt on arrival
- **Stocktake / physical count** — full or partial count workflow: create count → staff counts → system compares expected vs actual → generates adjustment
- **Low stock alerts** — configurable reorder point per product per branch; triggers notifications when stock falls below threshold
- **Recipes / bill of materials (BOM)** — define raw ingredient requirements per finished product; auto-deduct ingredient stock on sale
- **Waste tracking** — record spoiled/expired/broken items with reason and cost impact
- **Batch / lot tracking** — track goods by batch number; FEFO (first-expiry-first-out) logic for perishable products
- **Expiry management** — dashboard of products approaching expiry; automated alerts at configurable days-before-expiry
- **Stock valuation** — weighted average cost method; cost updates on goods receipt
- **Stock movement log** — full audit trail of every stock-in, stock-out, transfer, and adjustment
- **Multi-unit handling** — conversions between units (e.g. case → piece, kg → gram)

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Product & Catalog Management** | Products must exist before stock can be tracked |
| **Staff & User Management** | Receipts, adjustments, and transfers record who performed them |
| **Roles & Permissions** | `inventory.manage`, `inventory.view`, `inventory.adjust` permissions |
| **Offline/Online Sync** | Stock levels sync between local SQLite and cloud PostgreSQL |
| **Notifications** | Low stock and expiry alerts dispatched through notification system |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Displays stock availability; prevents overselling when enforce-stock is enabled; auto-deducts stock on sale |
| **Reports & Analytics** | Stock valuation reports, shrinkage analysis, turnover ratios |
| **Barcode Label Printing** | Labels printed after goods receipt include batch/expiry data |
| **Delivery Integrations** | Stock availability pushed to delivery platforms |
| **Thawani Integration** | Stock levels synced with Thawani marketplace |
| **Order Management** | Order fulfilment deducts stock; returns restore stock |

### Features to Review After Changing This Feature
1. **POS Terminal** — stock enforcement logic reads inventory tables directly
2. **Reports & Analytics** — cost and valuation reports depend on stock movement calculations
3. **Delivery Integrations** — stock availability affects menu availability on HungerStation/Jahez
4. **Recipes / BOM** — ingredient deduction logic tightly coupled to sales workflow

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local stock levels, movements, recipes, batches |
| **riverpod** / **flutter_bloc** | State management for stock list, adjustment forms, transfer flow |
| **dio** | HTTP client for inventory API calls (receipts, adjustments, POs, sync) |
| **excel** / **csv** | Export stock reports and import stocktake counts |
| **intl** | Number/date formatting for quantities, costs, expiry dates |
| **uuid** | Generate UUIDs for movements, receipts, transfers |

### 3.2 Technologies
- **Flutter 3.x Desktop** — all inventory screens in the POS back-office area
- **Dart** — stock calculation logic, FEFO sorting, weighted average cost computation
- **SQLite (via Drift)** — local stock mirror; triggers auto-update on transaction commit
- **PostgreSQL** — cloud master stock data; authoritative for multi-branch transfers
- **Laravel 11 REST API** — server-side stock validation, transfer approval, PO workflow, sync endpoints
- **Weighted Average Cost (WAC)** — cost calculation method: new_avg_cost = (existing_qty × existing_avg_cost + received_qty × receipt_cost) / (existing_qty + received_qty)

---

## 4. Screens

### 4.1 Stock Overview Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory` |
| **Purpose** | View current stock levels across all products |
| **Table Columns** | Product name, SKU, Barcode, Category, On-hand qty, Reserved qty, Available qty, Reorder point, Cost value, Status (OK / Low / Out) |
| **Filters** | Category, Status (Low/Out/OK), Supplier, Branch (if multi-branch) |
| **Search** | By product name, SKU, or barcode |
| **Colour Coding** | Green = above reorder, Yellow = at reorder, Red = zero stock |
| **Actions** | Adjust stock, View movements, Print labels, Create PO |
| **Access** | `inventory.view` |

### 4.2 Goods Receipt Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/receipts/create` |
| **Purpose** | Record incoming stock from a supplier |
| **Fields** | Supplier (dropdown), Invoice/reference number, Receipt date, Notes |
| **Line Items** | Product search, Quantity received, Unit cost, Batch number (optional), Expiry date (optional) |
| **Actions** | Save as draft, Confirm receipt (finalises stock and cost updates), Link to PO |
| **On Confirm** | Creates stock_movements (type: receipt), updates stock_levels, recalculates WAC |
| **Access** | `inventory.manage` |

### 4.3 Stock Adjustment Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/adjustments/create` |
| **Purpose** | Manually increase or decrease stock |
| **Fields** | Adjustment type (increase/decrease), Reason code (damage, theft, correction, sample, other), Notes |
| **Line Items** | Product search, Quantity, Cost per unit (for increases) |
| **On Save** | Creates stock_movements, updates stock_levels |
| **Access** | `inventory.adjust` |

### 4.4 Stock Transfer Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/transfers` |
| **Purpose** | Initiate and manage stock transfers between branches |
| **Workflow** | **Pending** → approved by sending branch → **In Transit** → received by destination branch → **Completed** |
| **Create Form** | Source branch, Destination branch, Product list with quantities, Notes |
| **List View** | Table of transfers: Reference #, From, To, Status, Items count, Date, Actions |
| **Actions** | Create, Approve/Send, Mark received, Cancel |
| **Access** | `inventory.transfer` |

### 4.5 Purchase Order Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/purchase-orders` |
| **Purpose** | Create and track purchase orders to suppliers |
| **Workflow** | **Draft** → **Sent** → **Partially Received** → **Fully Received** |
| **Create Form** | Supplier, Delivery branch, Expected date, PO items (product, qty, unit cost) |
| **Auto-Generate** | "Generate PO from low stock" button — auto-creates PO with all items below reorder point, quantities = reorder qty − current qty |
| **Access** | `inventory.manage` |

### 4.6 Stocktake Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/stocktake` |
| **Purpose** | Physical inventory count workflow |
| **Workflow** | 1. Create stocktake (full or filtered by category/location) → 2. Print count sheets / scan & enter counts → 3. Review variance (expected vs counted) → 4. Apply adjustments |
| **Variance View** | Product, Expected qty, Counted qty, Difference, Cost impact |
| **Actions** | Accept all, Accept individual, Reject (recount), Export variance report |
| **Access** | `inventory.stocktake` |

### 4.7 Recipe / BOM Management Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/recipes` |
| **Purpose** | Define ingredient breakdown for finished products |
| **Form** | Finished product (dropdown), Yield quantity |
| **Ingredients Table** | Raw product (dropdown), Quantity per yield, Unit, Waste % |
| **Preview** | Shows cost per unit of finished product (sum of ingredient costs + waste %) |
| **Access** | `inventory.manage` |

### 4.8 Waste & Expired Items Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/waste` |
| **Purpose** | Record and track wastage/spoilage |
| **Form** | Product, Quantity, Reason (expired, damaged, spillage, overproduction), Batch number, Notes |
| **List** | Date, Product, Qty, Reason, Cost impact, Recorded by |
| **Access** | `inventory.adjust` |

### 4.9 Expiry Dashboard
| Field | Detail |
|---|---|
| **Route** | `/inventory/expiry-dashboard` |
| **Purpose** | View products approaching expiry |
| **Sections** | Expired (past date, red), Expiring within 7 days (orange), Expiring within 30 days (yellow) |
| **Actions** | Mark as wasted, Discount (→ opens price override), Print reduced label |
| **Access** | `inventory.view` |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/inventory/stock-levels` | GET | Current stock levels (paginated, filterable) | Bearer token + `inventory.view` |
| `GET /api/inventory/stock-levels/{productId}` | GET | Stock detail for one product (all branches) | Bearer token |
| `POST /api/inventory/goods-receipts` | POST | Create goods receipt | Bearer token + `inventory.manage` |
| `GET /api/inventory/goods-receipts` | GET | List goods receipts | Bearer token |
| `POST /api/inventory/adjustments` | POST | Create stock adjustment | Bearer token + `inventory.adjust` |
| `GET /api/inventory/adjustments` | GET | List adjustments | Bearer token |
| `POST /api/inventory/transfers` | POST | Create stock transfer | Bearer token + `inventory.transfer` |
| `PUT /api/inventory/transfers/{id}/approve` | PUT | Approve/send transfer | Bearer token + `inventory.transfer` |
| `PUT /api/inventory/transfers/{id}/receive` | PUT | Mark transfer received at destination | Bearer token + `inventory.transfer` |
| `GET /api/inventory/transfers` | GET | List transfers | Bearer token |
| `POST /api/inventory/purchase-orders` | POST | Create purchase order | Bearer token + `inventory.manage` |
| `PUT /api/inventory/purchase-orders/{id}` | PUT | Update PO status | Bearer token + `inventory.manage` |
| `GET /api/inventory/purchase-orders` | GET | List POs | Bearer token |
| `POST /api/inventory/stocktake` | POST | Create stocktake session | Bearer token + `inventory.stocktake` |
| `PUT /api/inventory/stocktake/{id}/apply` | PUT | Apply stocktake variance adjustments | Bearer token + `inventory.stocktake` |
| `GET /api/inventory/stock-movements` | GET | Stock movement audit trail | Bearer token + `inventory.view` |
| `POST /api/inventory/waste` | POST | Record waste | Bearer token + `inventory.adjust` |
| `GET /api/inventory/expiry-alerts` | GET | Products approaching expiry | Bearer token |
| `GET /api/inventory/recipes` | GET | List recipes | Bearer token |
| `POST /api/inventory/recipes` | POST | Create/update recipe | Bearer token + `inventory.manage` |
| `GET /api/pos/inventory/sync?since={ts}` | GET | Delta stock sync for POS terminal | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `StockLevelRepository` | Local stock level queries; reactive streams for stock display |
| `StockMovementRepository` | Records all stock-in/out movements locally |
| `GoodsReceiptService` | Creates goods receipt, generates movements, recalculates WAC |
| `StockAdjustmentService` | Validates adjustment, creates movement, updates stock level |
| `StockTransferService` | Manages transfer lifecycle (create/approve/receive) with sync |
| `PurchaseOrderService` | PO CRUD, auto-generate from low stock, link to goods receipt |
| `StocktakeService` | Manages count workflow, calculates variance, generates adjustments |
| `RecipeService` | BOM resolution — given a sold product, returns ingredient list and quantities |
| `RecipeDeductionService` | Called on every sale: resolves recipe, deducts ingredient stock from stock_levels |
| `WasteService` | Records waste entries, creates negative stock movements |
| `ExpiryAlertService` | Scans stock_batches for approaching expiry; triggers notifications |
| `InventorySyncService` | Delta sync of stock levels between local SQLite and cloud |
| `CostCalculationService` | Weighted average cost calculations on receipt |

---

## 6. Full Database Schema

### 6.1 Tables

#### `stock_levels`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Current on-hand |
| reserved_quantity | DECIMAL(12,3) | DEFAULT 0 | Reserved for pending orders |
| reorder_point | DECIMAL(12,3) | NULLABLE | Low stock threshold |
| max_stock_level | DECIMAL(12,3) | NULLABLE | |
| average_cost | DECIMAL(12,4) | DEFAULT 0 | Weighted average cost |
| sync_version | INT | DEFAULT 1 | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE stock_levels (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    reserved_quantity DECIMAL(12,3) DEFAULT 0,
    reorder_point DECIMAL(12,3),
    max_stock_level DECIMAL(12,3),
    average_cost DECIMAL(12,4) DEFAULT 0,
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, product_id)
);
```

#### `stock_movements`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| type | VARCHAR(30) | NOT NULL | receipt, sale, adjustment_in, adjustment_out, transfer_out, transfer_in, waste, recipe_deduction |
| quantity | DECIMAL(12,3) | NOT NULL | Positive = in, Negative = out |
| unit_cost | DECIMAL(12,4) | NULLABLE | Cost at time of movement |
| reference_type | VARCHAR(50) | NULLABLE | goods_receipt, adjustment, transfer, transaction, waste, stocktake |
| reference_id | UUID | NULLABLE | FK to the source record |
| reason | VARCHAR(255) | NULLABLE | Reason code or notes |
| performed_by | UUID | FK → users(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE stock_movements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    type VARCHAR(30) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4),
    reference_type VARCHAR(50),
    reference_id UUID,
    reason VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `goods_receipts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| supplier_id | UUID | FK → suppliers(id), NULLABLE | |
| purchase_order_id | UUID | FK → purchase_orders(id), NULLABLE | |
| reference_number | VARCHAR(100) | NULLABLE | Supplier invoice number |
| status | VARCHAR(20) | DEFAULT 'draft' | draft, confirmed |
| total_cost | DECIMAL(14,2) | DEFAULT 0 | Sum of all line items |
| notes | TEXT | NULLABLE | |
| received_by | UUID | FK → users(id), NOT NULL | |
| received_at | TIMESTAMP | DEFAULT NOW() | |
| confirmed_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE goods_receipts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    supplier_id UUID REFERENCES suppliers(id),
    purchase_order_id UUID REFERENCES purchase_orders(id),
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'draft',
    total_cost DECIMAL(14,2) DEFAULT 0,
    notes TEXT,
    received_by UUID NOT NULL REFERENCES users(id),
    received_at TIMESTAMP DEFAULT NOW(),
    confirmed_at TIMESTAMP
);
```

#### `goods_receipt_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| goods_receipt_id | UUID | FK → goods_receipts(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity | DECIMAL(12,3) | NOT NULL | |
| unit_cost | DECIMAL(12,4) | NOT NULL | |
| batch_number | VARCHAR(100) | NULLABLE | |
| expiry_date | DATE | NULLABLE | |

```sql
CREATE TABLE goods_receipt_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    goods_receipt_id UUID NOT NULL REFERENCES goods_receipts(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    batch_number VARCHAR(100),
    expiry_date DATE
);
```

#### `stock_adjustments`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| type | VARCHAR(20) | NOT NULL | increase, decrease |
| reason_code | VARCHAR(50) | NOT NULL | damage, theft, correction, sample, other |
| notes | TEXT | NULLABLE | |
| adjusted_by | UUID | FK → users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE stock_adjustments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    type VARCHAR(20) NOT NULL,
    reason_code VARCHAR(50) NOT NULL,
    notes TEXT,
    adjusted_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `stock_adjustment_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| stock_adjustment_id | UUID | FK → stock_adjustments(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity | DECIMAL(12,3) | NOT NULL | Positive for increase, negative for decrease |
| unit_cost | DECIMAL(12,4) | NULLABLE | |

```sql
CREATE TABLE stock_adjustment_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    stock_adjustment_id UUID NOT NULL REFERENCES stock_adjustments(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4)
);
```

#### `stock_transfers`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| from_store_id | UUID | FK → stores(id), NOT NULL | Sending branch |
| to_store_id | UUID | FK → stores(id), NOT NULL | Receiving branch |
| status | VARCHAR(20) | DEFAULT 'pending' | pending, in_transit, completed, cancelled |
| reference_number | VARCHAR(50) | UNIQUE | Auto-generated TRF-YYYYMMDD-NNN |
| notes | TEXT | NULLABLE | |
| created_by | UUID | FK → users(id), NOT NULL | |
| approved_by | UUID | FK → users(id), NULLABLE | |
| received_by | UUID | FK → users(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| approved_at | TIMESTAMP | NULLABLE | |
| received_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE stock_transfers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    from_store_id UUID NOT NULL REFERENCES stores(id),
    to_store_id UUID NOT NULL REFERENCES stores(id),
    status VARCHAR(20) DEFAULT 'pending',
    reference_number VARCHAR(50) UNIQUE,
    notes TEXT,
    created_by UUID NOT NULL REFERENCES users(id),
    approved_by UUID REFERENCES users(id),
    received_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    approved_at TIMESTAMP,
    received_at TIMESTAMP
);
```

#### `stock_transfer_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| stock_transfer_id | UUID | FK → stock_transfers(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity_sent | DECIMAL(12,3) | NOT NULL | Quantity dispatched |
| quantity_received | DECIMAL(12,3) | NULLABLE | Quantity received (may differ) |

```sql
CREATE TABLE stock_transfer_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    stock_transfer_id UUID NOT NULL REFERENCES stock_transfers(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity_sent DECIMAL(12,3) NOT NULL,
    quantity_received DECIMAL(12,3)
);
```

#### `purchase_orders`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| store_id | UUID | FK → stores(id), NOT NULL | Delivery branch |
| supplier_id | UUID | FK → suppliers(id), NOT NULL | |
| reference_number | VARCHAR(50) | UNIQUE | Auto-generated PO-YYYYMMDD-NNN |
| status | VARCHAR(20) | DEFAULT 'draft' | draft, sent, partially_received, fully_received, cancelled |
| expected_date | DATE | NULLABLE | |
| total_cost | DECIMAL(14,2) | DEFAULT 0 | |
| notes | TEXT | NULLABLE | |
| created_by | UUID | FK → users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE purchase_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    supplier_id UUID NOT NULL REFERENCES suppliers(id),
    reference_number VARCHAR(50) UNIQUE,
    status VARCHAR(20) DEFAULT 'draft',
    expected_date DATE,
    total_cost DECIMAL(14,2) DEFAULT 0,
    notes TEXT,
    created_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `purchase_order_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| purchase_order_id | UUID | FK → purchase_orders(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity_ordered | DECIMAL(12,3) | NOT NULL | |
| unit_cost | DECIMAL(12,4) | NOT NULL | |
| quantity_received | DECIMAL(12,3) | DEFAULT 0 | Running total across goods receipts |

```sql
CREATE TABLE purchase_order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    purchase_order_id UUID NOT NULL REFERENCES purchase_orders(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity_ordered DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    quantity_received DECIMAL(12,3) DEFAULT 0
);
```

#### `stock_batches`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| batch_number | VARCHAR(100) | NULLABLE | |
| expiry_date | DATE | NULLABLE | |
| quantity | DECIMAL(12,3) | NOT NULL | Remaining quantity in this batch |
| unit_cost | DECIMAL(12,4) | NULLABLE | |
| goods_receipt_id | UUID | FK → goods_receipts(id), NULLABLE | Source receipt |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE stock_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    batch_number VARCHAR(100),
    expiry_date DATE,
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,4),
    goods_receipt_id UUID REFERENCES goods_receipts(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `recipes`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | Finished product |
| yield_quantity | DECIMAL(12,3) | NOT NULL, DEFAULT 1 | Number of finished units this recipe produces |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE recipes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    product_id UUID NOT NULL REFERENCES products(id),
    yield_quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `recipe_ingredients`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| recipe_id | UUID | FK → recipes(id) ON DELETE CASCADE, NOT NULL | |
| ingredient_product_id | UUID | FK → products(id), NOT NULL | Raw material product |
| quantity | DECIMAL(12,3) | NOT NULL | Quantity per yield |
| unit | VARCHAR(20) | DEFAULT 'piece' | |
| waste_percent | DECIMAL(5,2) | DEFAULT 0 | Preparation waste % |

```sql
CREATE TABLE recipe_ingredients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    recipe_id UUID NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    ingredient_product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL,
    unit VARCHAR(20) DEFAULT 'piece',
    waste_percent DECIMAL(5,2) DEFAULT 0
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `stock_levels_store_product` | (store_id, product_id) | UNIQUE | Core stock lookup |
| `stock_movements_store_product` | (store_id, product_id) | B-TREE | Movement history per product |
| `stock_movements_created_at` | created_at | B-TREE | Time-range queries |
| `stock_movements_reference` | (reference_type, reference_id) | B-TREE | Link back to source |
| `goods_receipts_store_date` | (store_id, received_at) | B-TREE | Receipt listing |
| `stock_transfers_org_status` | (organization_id, status) | B-TREE | Transfer workflow queries |
| `purchase_orders_org_status` | (organization_id, status) | B-TREE | PO workflow |
| `stock_batches_product_expiry` | (product_id, expiry_date) | B-TREE | FEFO queries and expiry alerts |
| `stock_batches_store_product` | (store_id, product_id) | B-TREE | Batch lookup per product per store |
| `recipes_product` | product_id | B-TREE | Recipe lookup on sale |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ stock_levels
stores ──1:N──▶ stock_movements
stores ──1:N──▶ stock_batches
stores ──1:N──▶ goods_receipts
products ──1:N──▶ stock_levels
products ──1:N──▶ stock_movements
products ──1:N──▶ stock_batches
goods_receipts ──1:N──▶ goods_receipt_items
goods_receipt_items ──N:1──▶ products
stock_adjustments ──1:N──▶ stock_adjustment_items
stock_adjustment_items ──N:1──▶ products
stock_transfers ──1:N──▶ stock_transfer_items
stock_transfer_items ──N:1──▶ products
purchase_orders ──1:N──▶ purchase_order_items
purchase_order_items ──N:1──▶ products
purchase_orders ──N:1──▶ suppliers
recipes ──1:N──▶ recipe_ingredients
recipe_ingredients ──N:1──▶ products (ingredient)
recipes ──N:1──▶ products (finished product)
goods_receipts ──N:1──▶ purchase_orders
```

---

## 7. Business Rules

1. **Stock can never go negative (configurable)** — by default, the system prevents selling when stock = 0; the store owner can disable this enforcement per product or globally
2. **Goods receipt finalisation is permanent** — once confirmed, a goods receipt cannot be edited; a corrective adjustment must be used instead
3. **Transfer quantity mismatch** — if the received quantity differs from the sent quantity, the difference is automatically logged as a stock adjustment at the sending branch (reason: transfer_variance)
4. **FEFO enforcement** — when multiple batches exist for a product, the system deducts from the earliest-expiring batch first
5. **Weighted average cost** — on goods receipt, the new average cost = (old_qty × old_avg_cost + receipt_qty × receipt_cost) / (old_qty + receipt_qty); used for all cost/margin calculations
6. **Recipe auto-deduction** — when a product with an active recipe is sold at POS, the RecipeDeductionService deducts each ingredient's proportional quantity from stock_levels; if ingredient stock is insufficient and enforcement is on, the POS warns but does not block the sale (configurable)
7. **Low stock alert threshold** — alerts fire once when stock crosses below reorder_point; they do not re-fire until stock goes above and back below (to avoid alert spam)
8. **Expiry alert intervals** — alerts are generated at 30, 14, 7, 3, and 1 day(s) before expiry; expired items generate a daily reminder until disposed
9. **Purchase order auto-generation** — the "Generate PO" function selects all products where current qty < reorder_point; it groups by supplier and creates one PO per supplier
10. **Stocktake locks** — while a stocktake session is open for a product/branch, that product's stock cannot be adjusted by other means; sales still deduct but are tracked separately and reconciled on count finalisation
11. **Audit trail immutability** — stock_movements records are insert-only; they can never be updated or deleted, ensuring a complete audit trail
12. **Multi-unit conversion** — if a product is received in cases and sold in pieces, the system uses a conversion factor (e.g. 1 case = 24 pieces) defined in the product's unit configuration
