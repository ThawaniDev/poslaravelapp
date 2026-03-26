# Product & Catalog Management — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Product Catalog, Variants, Modifiers, Suppliers, Barcodes  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · Dio · Laravel 11 REST API  

---

## 1. Feature Overview

Product & Catalog Management is the **foundation of every sales operation**. Before anything can be sold, scanned, or reported on, it must exist in the product catalog. This feature covers the full lifecycle of products — from creation through categorisation, pricing, variant/modifier configuration, barcode assignment, and supplier linking. The catalog lives centrally on the cloud (PostgreSQL) and is mirrored locally in every POS terminal's SQLite database via the sync engine.

### What This Feature Does
- **Product creation** — AR/EN names, SKU, one or more barcodes, images, description, unit type (piece/kg/litre/custom), sell price, cost price, tax category
- **Category hierarchy** — unlimited-depth category tree for organising products (e.g. Dairy → Milk → Full-Fat Milk)
- **Store-specific pricing** — override organisation-level price per branch (with optional validity dates)
- **Bulk import** — CSV / Excel import for mass product uploads
- **Product variants** — size, colour, flavour axes; each variant combination gets its own SKU, barcode, and price adjustment
- **Combo / bundle products** — define a bundle of products sold together at a fixed or discounted price
- **Product modifiers & add-ons** — required and optional modifier groups per product (e.g. burger size, sauce choice, extra cheese +3 SAR)
- **Weighable products** — flag for price-by-weight items; supports tare weight configuration
- **Product availability toggle** — enable/disable per branch without deleting
- **Expiry date tracking** — per product; feeds into Inventory Management alerts
- **Tax category assignment** — standard VAT (15%), zero-rated, or exempt — applied per product
- **Cost price tracking** — purchase cost for margin and profit analysis
- **Supplier assignment** — link products to one or more suppliers with supplier SKU, cost, and lead time
- **Auto-generated internal barcodes** — for unlabelled items, generates 200-prefix barcodes from a per-store sequence
- **Age-restriction flag** — marks products that require age verification at POS checkout

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Business Type Onboarding** | Category templates seeded from business type selection |
| **Language & Localization** | Dual-column names (name/name_ar), RTL display |
| **Roles & Permissions** | `products.manage` permission required for create/edit/delete |
| **Offline/Online Sync** | Product catalog synced to local SQLite; delta sync by `sync_version` |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Product lookup, pricing, variant/modifier resolution, barcode scan |
| **Inventory Management** | Stock is tracked per product; recipes reference product as ingredient |
| **Barcode Label Printing** | Labels are generated from product data (name, price, barcode) |
| **Reports & Analytics** | Revenue by product, category, margin, best/slow sellers |
| **Order Management** | Order items reference products; modifiers resolve from product config |
| **Promotions & Coupons** | Promotions target specific products or categories |
| **Delivery Integrations** | Product catalog pushed to third-party delivery platforms |
| **Thawani Integration** | Product/price/stock sync with Thawani marketplace |
| **Customer Management** | Purchase history shows product names |
| **ZATCA Compliance** | Product tax category determines VAT line on ZATCA invoice |

### Features to Review After Changing This Feature
1. **POS Terminal** — product lookup and barcode resolution rely on product schema
2. **Inventory Management** — stock is keyed on product_id; any product restructure affects inventory
3. **Delivery Integrations** — outbound product sync payloads derive from product schema
4. **Barcode Label Printing** — label templates reference product fields
5. **Promotions & Coupons** — promotion_products join table FK's into products
6. **Reports** — product_sales_summary aggregation must match product structure

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local product catalog, categories, variants, modifiers |
| **riverpod** / **flutter_bloc** | State management for product list, category tree, search results |
| **dio** | HTTP client for REST API calls (CRUD operations, bulk import, catalog sync) |
| **excel** / **csv** | Parse CSV/Excel files for bulk product import |
| **image_picker** / **file_selector** | Select product images from file system (desktop file dialog) |
| **cached_network_image** | Display product images with caching |
| **barcode** (pub.dev) | Generate barcode data for internal 200-prefix barcodes (EAN-13, Code128) |
| **uuid** | Generate UUIDs for new products, categories, variants |
| **intl** | Number formatting for prices, currency display |

### 3.2 Technologies
- **Flutter 3.x Desktop** — product management screens in the POS app and back-office
- **Dart** — business logic for variant matrix generation, modifier validation, price calculation
- **SQLite (via Drift)** — local offline catalog mirror; supports full-text search on product names
- **PostgreSQL** — cloud master catalog; all products created/edited sync here
- **Laravel 11 REST API** — server-side CRUD, validation, image upload (DigitalOcean Spaces), sync endpoints
- **DigitalOcean Spaces** — cloud storage for product images (S3-compatible API)
- **Delta sync** — products have a `sync_version` column; POS queries `GET /products/changes?since=X` to fetch only changed items

---

## 4. Screens

### 4.1 Product List Screen
| Field | Detail |
|---|---|
| **Route** | `/products` |
| **Purpose** | Browse, search, and manage the full product catalog |
| **Layout** | Left sidebar: category tree (expandable/collapsible). Main area: product data table/grid |
| **Table Columns** | Image thumbnail, Name (AR/EN), SKU, Barcode, Category, Price, Cost, Stock, Status (active/inactive), Actions |
| **Search** | By name, SKU, barcode — instant filter as user types |
| **Filters** | Category, Status (active/inactive), Tax category, Weighable, Age-restricted, Has variants, Supplier |
| **Bulk Actions** | Activate, Deactivate, Delete, Print Labels, Export CSV |
| **Row Actions** | Edit, Duplicate, View stock, Print label, Toggle active |
| **Access** | `products.view` (all staff except Kitchen) |

### 4.2 Product Create / Edit Screen
| Field | Detail |
|---|---|
| **Route** | `/products/create` or `/products/{id}/edit` |
| **Purpose** | Full product form with all fields |
| **Tabs** | General, Pricing, Variants, Modifiers, Inventory, Barcodes, Supplier |
| — General tab | Name (EN), Name (AR), Description, Category (tree picker), Unit type, Images (drag-drop multi-upload), Is Weighable toggle, Tare weight, Is Active, Age-restricted toggle |
| — Pricing tab | Sell price (org-level), Cost price, Tax category (standard/zero/exempt), Store-specific price overrides table (branch → price → valid from/to) |
| — Variants tab | Add variant groups (Size, Colour, etc.); define values per group; auto-generate variant matrix; per-variant: SKU, barcode, price adjustment, image |
| — Modifiers tab | Add modifier groups (e.g. "Size", "Sauce"); is_required, min/max selections; add options with name, price adjustment, is_default, sort order |
| — Inventory tab | Current stock per branch (read-only); reorder point, max stock level; recipe (BOM) builder if applicable |
| — Barcodes tab | Primary barcode, additional barcodes, auto-generate 200-prefix button |
| — Supplier tab | Link suppliers from supplier directory; supplier SKU, cost price, lead time per supplier |
| **Validation** | Name required, sell price ≥ 0, at least one barcode (or auto-generate), category required |
| **Access** | `products.manage` (Inventory Clerk, Branch Manager, Owner) |

### 4.3 Bulk Import Screen
| Field | Detail |
|---|---|
| **Route** | `/products/import` |
| **Purpose** | Upload CSV/Excel file with product data |
| **Flow** | 1. Upload file → 2. Column mapping (map CSV columns to product fields) → 3. Preview rows with validation errors highlighted → 4. Confirm import |
| **Supported Fields** | Name, Name AR, SKU, Barcode, Category path (e.g. "Dairy > Milk"), Sell price, Cost price, Unit, Tax category, Description |
| **Error Handling** | Rows with errors are skipped and listed in a downloadable error report |
| **Access** | `products.manage` |

### 4.4 Category Management Screen
| Field | Detail |
|---|---|
| **Route** | `/categories` |
| **Purpose** | CRUD on category tree |
| **Layout** | Drag-and-drop tree view; inline rename; add child category; delete (only if no products assigned) |
| **Fields per Category** | Name (EN), Name (AR), Parent category, Sort order, Image/icon |
| **Access** | `products.manage` |

### 4.5 Supplier Directory Screen
| Field | Detail |
|---|---|
| **Route** | `/suppliers` |
| **Purpose** | Manage the supplier database |
| **Table Columns** | Name, Phone, Email, Address, Linked products count, Last order date |
| **Row Actions** | Edit, View linked products, Create PO (→ Inventory Management) |
| **Access** | `inventory.view` |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/pos/products/catalog` | GET | Full catalog download (initial sync) | Bearer token |
| `GET /api/pos/products/changes?since={timestamp}` | GET | Delta changes since last sync | Bearer token |
| `GET /api/products` | GET | Paginated product list with filters | Bearer token |
| `POST /api/products` | POST | Create product | Bearer token + `products.manage` |
| `PUT /api/products/{id}` | PUT | Update product | Bearer token + `products.manage` |
| `DELETE /api/products/{id}` | DELETE | Soft-delete product | Bearer token + `products.manage` |
| `POST /api/products/bulk-import` | POST | Upload CSV/Excel for import | Bearer token + `products.manage` |
| `GET /api/products/{id}/variants` | GET | List variants for a product | Bearer token |
| `POST /api/products/{id}/variants` | POST | Create/update variants | Bearer token + `products.manage` |
| `GET /api/products/{id}/modifiers` | GET | List modifier groups and options | Bearer token |
| `POST /api/products/{id}/modifiers` | POST | Create/update modifier groups | Bearer token + `products.manage` |
| `GET /api/categories` | GET | Category tree | Bearer token |
| `POST /api/categories` | POST | Create category | Bearer token + `products.manage` |
| `PUT /api/categories/{id}` | PUT | Update category (name, parent, sort) | Bearer token + `products.manage` |
| `DELETE /api/categories/{id}` | DELETE | Delete category (fails if has products) | Bearer token + `products.manage` |
| `GET /api/suppliers` | GET | Supplier list | Bearer token |
| `POST /api/suppliers` | POST | Create supplier | Bearer token + `products.manage` |
| `PUT /api/suppliers/{id}` | PUT | Update supplier | Bearer token + `products.manage` |
| `POST /api/products/{id}/generate-barcode` | POST | Auto-generate 200-prefix barcode | Bearer token + `products.manage` |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `ProductRepository` | CRUD operations in local Drift DB; exposes streams for reactive UI updates |
| `ProductSyncService` | Handles delta sync — fetches changes from API, upserts locally, manages sync_version |
| `CategoryRepository` | Local category tree queries; recursive parent-child resolution |
| `VariantService` | Generates variant matrix from axis groups; manages SKU/barcode per variant |
| `ModifierService` | Validates modifier selections (required groups, min/max constraints) |
| `BarcodeGeneratorService` | Auto-generates 200-prefix EAN-13 barcodes from per-store sequence counter |
| `BulkImportService` | Parses CSV/Excel, maps columns to fields, validates rows, calls API |
| `ProductSearchService` | Full-text search on product names + barcode + SKU in local SQLite |
| `SupplierRepository` | Manages supplier records locally and syncs with cloud |

---

## 6. Full Database Schema

### 6.1 Tables

#### `categories`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| parent_id | UUID | FK → categories(id), NULLABLE | Self-referencing for hierarchy |
| name | VARCHAR(255) | NOT NULL | English name |
| name_ar | VARCHAR(255) | NULLABLE | Arabic name |
| image_url | TEXT | NULLABLE | Category icon/image |
| sort_order | INT | DEFAULT 0 | Display order within parent |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sync_version | INT | DEFAULT 1 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    parent_id UUID REFERENCES categories(id),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    image_url TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `products`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| category_id | UUID | FK → categories(id), NULLABLE | |
| name | VARCHAR(255) | NOT NULL | English name |
| name_ar | VARCHAR(255) | NULLABLE | Arabic name |
| description | TEXT | NULLABLE | |
| description_ar | TEXT | NULLABLE | |
| sku | VARCHAR(100) | NULLABLE | Stock Keeping Unit |
| barcode | VARCHAR(50) | NULLABLE | Primary barcode |
| sell_price | DECIMAL(12,2) | NOT NULL | Organisation-level sell price |
| cost_price | DECIMAL(12,2) | NULLABLE | Purchase / cost price |
| unit | VARCHAR(20) | DEFAULT 'piece' | piece / kg / litre / custom |
| tax_rate | DECIMAL(5,2) | DEFAULT 15.00 | 15.00 / 0.00 / NULL (exempt) |
| is_weighable | BOOLEAN | DEFAULT FALSE | Price-by-weight item |
| tare_weight | DECIMAL(8,3) | DEFAULT 0 | Container weight to subtract |
| is_active | BOOLEAN | DEFAULT TRUE | |
| is_combo | BOOLEAN | DEFAULT FALSE | Bundle/combo product |
| age_restricted | BOOLEAN | DEFAULT FALSE | Requires age verification |
| image_url | TEXT | NULLABLE | Primary product image |
| sync_version | INT | DEFAULT 1 | For delta sync |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete |

```sql
CREATE TABLE products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    category_id UUID REFERENCES categories(id),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    description TEXT,
    description_ar TEXT,
    sku VARCHAR(100),
    barcode VARCHAR(50),
    sell_price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'piece',
    tax_rate DECIMAL(5,2) DEFAULT 15.00,
    is_weighable BOOLEAN DEFAULT FALSE,
    tare_weight DECIMAL(8,3) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_combo BOOLEAN DEFAULT FALSE,
    age_restricted BOOLEAN DEFAULT FALSE,
    image_url TEXT,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);
```

#### `product_barcodes`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id) ON DELETE CASCADE, NOT NULL | |
| barcode | VARCHAR(50) | NOT NULL, UNIQUE | |
| is_primary | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE product_barcodes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    barcode VARCHAR(50) NOT NULL UNIQUE,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `store_prices`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| sell_price | DECIMAL(12,2) | NOT NULL | Branch-specific override |
| valid_from | DATE | NULLABLE | |
| valid_to | DATE | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE store_prices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    sell_price DECIMAL(12,2) NOT NULL,
    valid_from DATE,
    valid_to DATE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, product_id)
);
```

#### `product_variant_groups`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| name | VARCHAR(100) | NOT NULL | e.g. "Size", "Colour" |
| name_ar | VARCHAR(100) | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE product_variant_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `product_variants`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id) ON DELETE CASCADE, NOT NULL | |
| variant_group_id | UUID | FK → product_variant_groups(id), NOT NULL | |
| variant_value | VARCHAR(100) | NOT NULL | e.g. "Large", "Red" |
| variant_value_ar | VARCHAR(100) | NULLABLE | |
| sku | VARCHAR(100) | NULLABLE | Variant-specific SKU |
| barcode | VARCHAR(50) | NULLABLE | Variant-specific barcode |
| price_adjustment | DECIMAL(12,2) | DEFAULT 0 | + or − from base price |
| image_url | TEXT | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE product_variants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    variant_group_id UUID NOT NULL REFERENCES product_variant_groups(id),
    variant_value VARCHAR(100) NOT NULL,
    variant_value_ar VARCHAR(100),
    sku VARCHAR(100),
    barcode VARCHAR(50),
    price_adjustment DECIMAL(12,2) DEFAULT 0,
    image_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `product_images`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id) ON DELETE CASCADE, NOT NULL | |
| image_url | TEXT | NOT NULL | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE product_images (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    image_url TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `combo_products`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id) ON DELETE CASCADE, NOT NULL | The combo/bundle product |
| name | VARCHAR(255) | NOT NULL | Combo name (can differ from product name) |
| combo_price | DECIMAL(12,2) | NULLABLE | Fixed combo price (NULL = sum of items) |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE combo_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    combo_price DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `combo_product_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| combo_product_id | UUID | FK → combo_products(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | Component product |
| quantity | DECIMAL(12,3) | NOT NULL, DEFAULT 1 | |
| is_optional | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE combo_product_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    combo_product_id UUID NOT NULL REFERENCES combo_products(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
    is_optional BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `modifier_groups`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id) ON DELETE CASCADE, NOT NULL | |
| name | VARCHAR(255) | NOT NULL | e.g. "Size", "Sauce" |
| name_ar | VARCHAR(255) | NULLABLE | |
| is_required | BOOLEAN | DEFAULT FALSE | Must the customer pick at least one? |
| min_select | INT | DEFAULT 0 | Minimum selections |
| max_select | INT | DEFAULT 1 | Maximum selections |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE modifier_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    is_required BOOLEAN DEFAULT FALSE,
    min_select INT DEFAULT 0,
    max_select INT DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `modifier_options`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| modifier_group_id | UUID | FK → modifier_groups(id) ON DELETE CASCADE, NOT NULL | |
| name | VARCHAR(255) | NOT NULL | e.g. "Large", "Extra Cheese" |
| name_ar | VARCHAR(255) | NULLABLE | |
| price_adjustment | DECIMAL(12,2) | DEFAULT 0 | + or − from product price |
| is_default | BOOLEAN | DEFAULT FALSE | Pre-selected |
| sort_order | INT | DEFAULT 0 | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE modifier_options (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    modifier_group_id UUID NOT NULL REFERENCES modifier_groups(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    price_adjustment DECIMAL(12,2) DEFAULT 0,
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `suppliers`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| name | VARCHAR(255) | NOT NULL | |
| phone | VARCHAR(50) | NULLABLE | |
| email | VARCHAR(255) | NULLABLE | |
| address | TEXT | NULLABLE | |
| notes | TEXT | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE suppliers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `product_suppliers`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id) ON DELETE CASCADE, NOT NULL | |
| supplier_id | UUID | FK → suppliers(id) ON DELETE CASCADE, NOT NULL | |
| cost_price | DECIMAL(12,2) | NULLABLE | Supplier-specific cost |
| lead_time_days | INT | NULLABLE | Delivery lead time |
| supplier_sku | VARCHAR(100) | NULLABLE | Supplier's SKU for this product |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE product_suppliers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    supplier_id UUID NOT NULL REFERENCES suppliers(id) ON DELETE CASCADE,
    cost_price DECIMAL(12,2),
    lead_time_days INT,
    supplier_sku VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (product_id, supplier_id)
);
```

#### `internal_barcode_sequence`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL, UNIQUE | One sequence per store |
| last_sequence | INT | NOT NULL, DEFAULT 0 | Incremented on each barcode generation |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE internal_barcode_sequence (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    last_sequence INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `products_barcode` | barcode | B-TREE | Fast barcode scan lookup |
| `products_org_category` | (organization_id, category_id) | B-TREE | Category filter |
| `products_org_active` | (organization_id, is_active) | B-TREE | Active product listing |
| `products_sku` | sku | B-TREE | SKU lookup |
| `product_barcodes_barcode` | barcode | UNIQUE | Multi-barcode lookup |
| `store_prices_store_product` | (store_id, product_id) | UNIQUE | Branch price override |
| `product_variants_product_group` | (product_id, variant_group_id) | B-TREE | Variant axis lookup |
| `modifier_groups_product` | product_id | B-TREE | Modifiers per product |
| `modifier_options_group` | modifier_group_id | B-TREE | Options per modifier group |
| `product_suppliers_composite` | (product_id, supplier_id) | UNIQUE | Supplier link |
| `categories_org_parent` | (organization_id, parent_id) | B-TREE | Category tree queries |
| `categories_org_active` | (organization_id, is_active) | B-TREE | Active categories |

### 6.3 Relationships Diagram
```
organizations ──1:N──▶ categories
organizations ──1:N──▶ products
organizations ──1:N──▶ suppliers
organizations ──1:N──▶ product_variant_groups
categories ──1:N──▶ products
categories ──self──▶ categories (parent_id)
products ──1:N──▶ product_barcodes
products ──1:N──▶ product_images
products ──1:N──▶ product_variants
products ──1:N──▶ modifier_groups
products ──1:1──▶ combo_products
products ──N:M──▶ suppliers (via product_suppliers)
product_variant_groups ──1:N──▶ product_variants
modifier_groups ──1:N──▶ modifier_options
combo_products ──1:N──▶ combo_product_items
combo_product_items ──N:1──▶ products (component)
stores ──1:N──▶ store_prices
stores ──1:1──▶ internal_barcode_sequence
```

---

## 7. Business Rules

1. **Category deletion** — a category can only be deleted if it has zero products assigned; otherwise the user must reassign or delete products first
2. **Barcode uniqueness** — barcodes must be globally unique within the organisation (across `products.barcode` and `product_barcodes.barcode`); duplicate barcode entry is rejected
3. **Internal barcode prefix** — auto-generated barcodes use the 200-prefix range (EAN-13 format: 200XXXXXXXXXC where X = sequence and C = check digit); this avoids collision with manufacturer barcodes
4. **Weighable barcode prefixes** — 21/22-prefix barcodes contain embedded weight; 23/24-prefix contain embedded price. The POS Terminal's BarcodeService parses these prefixes to extract weight/price on scan
5. **Store price override cascade** — when selling, the POS checks: (a) store-specific price (within valid_from/valid_to), (b) organisation sell_price. First valid match wins
6. **Variant matrix** — when variant groups are configured, each unique combination must have its own barcode; the system auto-generates the matrix and the user fills in SKUs/barcodes
7. **Modifier validation** — at POS, if a product has a required modifier group, the sale cannot proceed until the cashier selects options within min/max constraints
8. **Sync versioning** — every product update increments `sync_version`; POS terminals use this to detect changes via delta sync
9. **Soft delete** — products are never physically deleted; `deleted_at` is set, and they are excluded from active queries but remain for historical transaction references
10. **Bulk import limits** — maximum 10,000 rows per import file; larger catalogs must be split into multiple files
11. **Cost price visibility** — only users with `reports.view_margin` permission can see cost price and margin data
12. **Tax category inheritance** — if a product has no explicit tax_rate, it inherits the organisation default (15% VAT); zero-rated and exempt must be explicitly set
