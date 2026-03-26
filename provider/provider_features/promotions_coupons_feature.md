# Promotions & Coupons — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Discounts, Promotions, Coupon Codes, Happy Hours, BOGO, Bundle Deals  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · Dio · Laravel 11 REST API  

---

## 1. Feature Overview

Promotions & Coupons provides a flexible discount engine that supports multiple discount types (percentage, fixed amount, BOGO, bundle pricing), targeting rules (specific products, categories, customer groups, time windows), and usage limits. Promotions are evaluated automatically at the POS during checkout, while coupon codes are entered manually and validated against configured rules.

### What This Feature Does
- **Percentage discounts** — e.g. 20% off all Dairy products
- **Fixed amount discounts** — e.g. 5 SAR off orders over 50 SAR
- **Buy-One-Get-One (BOGO)** — buy X get Y free or at reduced price
- **Bundle pricing** — buy items A + B + C together for a special price
- **Happy hour** — time-based automatic discounts (e.g. 15% off 2PM–4PM)
- **Coupon codes** — alphanumeric codes customers present at checkout; single-use or multi-use
- **Minimum order threshold** — promotion applies only when order total meets a minimum
- **Product/category targeting** — apply discount to specific products or entire categories
- **Customer group targeting** — discounts for VIP, wholesale, or staff groups
- **Usage limits** — maximum total uses, maximum uses per customer, valid date range
- **Stacking rules** — configurable whether promotions can stack with other promotions or coupons
- **Automatic application** — qualifying promotions are auto-applied at POS; cashier can also manually apply or remove
- **Promotion analytics** — track usage, revenue impact, and discount cost per promotion

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Product & Catalog Management** | Promotions target products and categories |
| **Customer Management** | Customer groups for targeted promotions |
| **POS Terminal** | Promotion engine evaluates at checkout |
| **Roles & Permissions** | `promotions.manage` permission for CRUD; `promotions.apply_manual` for manual override |
| **Offline/Online Sync** | Active promotions synced to local SQLite for offline evaluation |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Displays applied discounts on checkout screen |
| **Order Management** | Discount amounts recorded on orders and order items |
| **Reports & Analytics** | Promotion effectiveness reports, discount cost analysis |
| **ZATCA Compliance** | Discounts reflected on ZATCA invoice line items |

### Features to Review After Changing This Feature
1. **POS Terminal** — promotion evaluation engine embedded in checkout flow
2. **Order Management** — discount_amount fields on orders and order_items
3. **Reports & Analytics** — promotion usage aggregation queries
4. **ZATCA Compliance** — discount handling in tax calculation

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local promotion and coupon storage for offline evaluation |
| **riverpod** / **flutter_bloc** | State management for promotion application at POS, promotion CRUD |
| **dio** | HTTP client for promotion API calls and sync |
| **uuid** | Generate UUIDs for promotions and coupons |
| **intl** | Currency formatting for discount displays |

### 3.2 Technologies
- **Flutter 3.x Desktop** — promotion management screens, promotion evaluation in POS checkout
- **Dart** — promotion evaluation engine with rule-based matching and conflict resolution
- **SQLite (via Drift)** — local promotion mirror for offline application
- **PostgreSQL** — cloud master promotion data
- **Laravel 11 REST API** — promotion CRUD, coupon validation, usage tracking
- **Rule Engine Pattern** — promotions are evaluated by a `PromotionEvaluator` that iterates active promotions, checks eligibility criteria, and applies the best discount (or stacks if allowed)

---

## 4. Screens

### 4.1 Promotion List Screen
| Field | Detail |
|---|---|
| **Route** | `/promotions` |
| **Purpose** | Browse and manage all promotions and coupons |
| **Table Columns** | Name, Type (%, fixed, BOGO, bundle, happy hour), Status (active/scheduled/expired/disabled), Valid from, Valid to, Usage count, Max uses, Actions |
| **Filters** | Type, Status, Date range |
| **Row Actions** | Edit, Duplicate, Enable/Disable, View analytics, Delete |
| **Access** | `promotions.manage` |

### 4.2 Promotion Create / Edit Screen
| Field | Detail |
|---|---|
| **Route** | `/promotions/create` or `/promotions/{id}/edit` |
| **Purpose** | Full promotion configuration form |
| **Sections** | |
| — Basic Info | Name, Description, Promotion type (percentage/fixed/bogo/bundle/happy_hour), Is coupon (yes/no), Coupon code (if yes) |
| — Discount Value | Percentage (%) or Fixed amount (SAR); for BOGO: buy quantity, get quantity, get discount %; for Bundle: select products, set bundle price |
| — Targeting | Apply to: All products, Specific products (multi-select), Specific categories (multi-select), Specific customer groups |
| — Conditions | Minimum order total, Minimum item quantity, Valid from date, Valid to date, Active days of week, Active time range (for happy hour) |
| — Usage Limits | Max total uses, Max uses per customer, Single-use per transaction toggle |
| — Stacking | Allow stacking with other promotions (yes/no) |
| **Access** | `promotions.manage` |

### 4.3 Coupon Code Management Screen
| Field | Detail |
|---|---|
| **Route** | `/promotions/coupons` |
| **Purpose** | Manage coupon codes separately from auto-promotions |
| **Table** | Code, Linked promotion, Total uses, Max uses, Status |
| **Batch Generation** | Generate N unique coupon codes for a promotion (e.g. 500 single-use codes for a marketing campaign) |
| **Access** | `promotions.manage` |

### 4.4 Promotion Analytics Screen
| Field | Detail |
|---|---|
| **Route** | `/promotions/{id}/analytics` |
| **Purpose** | View performance metrics for a specific promotion |
| **Metrics** | Total uses, Unique customers, Total discount given (SAR), Revenue generated by promoted items, Average basket size with promotion, Usage over time chart |
| **Access** | `promotions.manage` + `reports.view` |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/promotions` | GET | List all promotions | Bearer token + `promotions.manage` |
| `POST /api/promotions` | POST | Create promotion | Bearer token + `promotions.manage` |
| `PUT /api/promotions/{id}` | PUT | Update promotion | Bearer token + `promotions.manage` |
| `DELETE /api/promotions/{id}` | DELETE | Delete promotion | Bearer token + `promotions.manage` |
| `POST /api/promotions/{id}/toggle` | POST | Enable/disable promotion | Bearer token + `promotions.manage` |
| `POST /api/coupons/validate` | POST | Validate coupon code at POS | Bearer token |
| `POST /api/coupons/redeem` | POST | Record coupon usage | Bearer token |
| `POST /api/coupons/batch-generate` | POST | Generate batch of coupon codes | Bearer token + `promotions.manage` |
| `GET /api/promotions/{id}/analytics` | GET | Promotion performance data | Bearer token + `reports.view` |
| `GET /api/pos/promotions/sync?since={ts}` | GET | Delta sync of active promotions for POS | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `PromotionRepository` | Local Drift DB CRUD for promotions; sync with cloud |
| `PromotionEvaluator` | Core engine: takes cart items + customer → returns applicable discounts |
| `CouponValidationService` | Validates coupon code (exists, active, within limits, customer eligible) |
| `PromotionSyncService` | Delta sync of active promotions to local SQLite |
| `DiscountCalculationService` | Calculates discount amounts for each promotion type (%, fixed, BOGO, bundle) |
| `PromotionStackingResolver` | Resolves conflicts: if stacking disabled, picks best discount; if enabled, applies all |

---

## 6. Full Database Schema

### 6.1 Tables

#### `promotions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| name | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| type | VARCHAR(30) | NOT NULL | percentage, fixed_amount, bogo, bundle, happy_hour |
| discount_value | DECIMAL(12,2) | NULLABLE | % or SAR depending on type |
| buy_quantity | INT | NULLABLE | For BOGO: buy N |
| get_quantity | INT | NULLABLE | For BOGO: get M |
| get_discount_percent | DECIMAL(5,2) | NULLABLE | For BOGO: discount on the "get" items (100 = free) |
| bundle_price | DECIMAL(12,2) | NULLABLE | For bundle type |
| min_order_total | DECIMAL(12,2) | NULLABLE | Minimum order to qualify |
| min_item_quantity | INT | NULLABLE | Minimum item qty to qualify |
| valid_from | TIMESTAMP | NULLABLE | |
| valid_to | TIMESTAMP | NULLABLE | |
| active_days | JSONB | DEFAULT '[]' | Array of day numbers [0=Sun, 1=Mon, ...] for happy hour |
| active_time_from | TIME | NULLABLE | Happy hour start |
| active_time_to | TIME | NULLABLE | Happy hour end |
| max_uses | INT | NULLABLE | Total usage limit |
| max_uses_per_customer | INT | NULLABLE | Per-customer limit |
| is_stackable | BOOLEAN | DEFAULT FALSE | Can stack with other promotions |
| is_active | BOOLEAN | DEFAULT TRUE | |
| is_coupon | BOOLEAN | DEFAULT FALSE | Requires coupon code entry |
| usage_count | INT | DEFAULT 0 | Running total |
| sync_version | INT | DEFAULT 1 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE promotions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(30) NOT NULL,
    discount_value DECIMAL(12,2),
    buy_quantity INT,
    get_quantity INT,
    get_discount_percent DECIMAL(5,2),
    bundle_price DECIMAL(12,2),
    min_order_total DECIMAL(12,2),
    min_item_quantity INT,
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    active_days JSONB DEFAULT '[]',
    active_time_from TIME,
    active_time_to TIME,
    max_uses INT,
    max_uses_per_customer INT,
    is_stackable BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    is_coupon BOOLEAN DEFAULT FALSE,
    usage_count INT DEFAULT 0,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `promotion_products`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| promotion_id | UUID | FK → promotions(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id) ON DELETE CASCADE, NOT NULL | |

```sql
CREATE TABLE promotion_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE (promotion_id, product_id)
);
```

#### `promotion_categories`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| promotion_id | UUID | FK → promotions(id) ON DELETE CASCADE, NOT NULL | |
| category_id | UUID | FK → categories(id) ON DELETE CASCADE, NOT NULL | |

```sql
CREATE TABLE promotion_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    category_id UUID NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE (promotion_id, category_id)
);
```

#### `promotion_customer_groups`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| promotion_id | UUID | FK → promotions(id) ON DELETE CASCADE, NOT NULL | |
| customer_group_id | UUID | FK → customer_groups(id) ON DELETE CASCADE, NOT NULL | |

```sql
CREATE TABLE promotion_customer_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    customer_group_id UUID NOT NULL REFERENCES customer_groups(id) ON DELETE CASCADE,
    UNIQUE (promotion_id, customer_group_id)
);
```

#### `coupon_codes`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| promotion_id | UUID | FK → promotions(id) ON DELETE CASCADE, NOT NULL | |
| code | VARCHAR(30) | NOT NULL, UNIQUE | Alphanumeric coupon code |
| max_uses | INT | DEFAULT 1 | Per-code limit (1 = single use) |
| usage_count | INT | DEFAULT 0 | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE coupon_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    code VARCHAR(30) NOT NULL UNIQUE,
    max_uses INT DEFAULT 1,
    usage_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `promotion_usage_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| promotion_id | UUID | FK → promotions(id), NOT NULL | |
| coupon_code_id | UUID | FK → coupon_codes(id), NULLABLE | NULL for auto promotions |
| order_id | UUID | FK → orders(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| discount_amount | DECIMAL(12,2) | NOT NULL | Actual discount applied |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE promotion_usage_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id),
    coupon_code_id UUID REFERENCES coupon_codes(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    customer_id UUID REFERENCES customers(id),
    discount_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `bundle_products`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| promotion_id | UUID | FK → promotions(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity | INT | DEFAULT 1 | Quantity required in bundle |

```sql
CREATE TABLE bundle_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    promotion_id UUID NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity INT DEFAULT 1
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `promotions_org_active` | (organization_id, is_active) | B-TREE | Active promotion listing |
| `promotions_valid_dates` | (valid_from, valid_to) | B-TREE | Date-range filtering |
| `promotion_products_promotion` | promotion_id | B-TREE | Products per promotion |
| `promotion_products_product` | product_id | B-TREE | Promotions per product |
| `promotion_categories_promotion` | promotion_id | B-TREE | Categories per promotion |
| `coupon_codes_code` | code | UNIQUE | Coupon code lookup |
| `coupon_codes_promotion` | promotion_id | B-TREE | Codes per promotion |
| `promotion_usage_promotion` | promotion_id | B-TREE | Usage tracking |
| `promotion_usage_customer` | customer_id | B-TREE | Per-customer usage check |

### 6.3 Relationships Diagram
```
organizations ──1:N──▶ promotions
promotions ──N:M──▶ products (via promotion_products)
promotions ──N:M──▶ categories (via promotion_categories)
promotions ──N:M──▶ customer_groups (via promotion_customer_groups)
promotions ──1:N──▶ coupon_codes
promotions ──1:N──▶ promotion_usage_log
promotions ──1:N──▶ bundle_products
coupon_codes ──1:N──▶ promotion_usage_log
orders ──1:N──▶ promotion_usage_log
```

---

## 7. Business Rules

1. **Promotion evaluation order** — promotions are evaluated in priority: (1) coupon entered by cashier, (2) customer group discount, (3) auto-promotions sorted by discount value descending (best first)
2. **Non-stackable default** — by default promotions do not stack; only one discount applies per item (the best one); stacking must be explicitly enabled
3. **Stacking cap** — even when stacking is enabled, the total discount per item cannot exceed 100% of the item price (floor at 0 SAR)
4. **Coupon validation** — a coupon is valid if: (a) code exists, (b) linked promotion is active, (c) within valid date range, (d) usage count < max_uses, (e) customer hasn't exceeded per-customer limit
5. **Expired promotion cleanup** — promotions past their `valid_to` date are automatically marked as expired but not deleted; they remain for analytics
6. **BOGO calculation** — buy 2 get 1 free: the cheapest qualifying item is made free; buy 3 get 1 at 50% off: the cheapest qualifying item gets 50% discount
7. **Happy hour auto-trigger** — happy hour promotions are automatically evaluated based on the POS terminal's local time; they activate and deactivate without cashier intervention
8. **Manual discount override** — cashiers with `promotions.apply_manual` can apply or remove promotions manually; manual overrides are logged in the usage log with the cashier's ID
9. **Promotion on returns** — when an item that had a promotion is returned, the discount is proportionally reversed; if a BOGO return breaks the qualifying threshold, the free item is charged at full price on the return
10. **Batch coupon generation** — batch codes follow pattern: PREFIX-XXXXXX where PREFIX is user-defined and XXXXXX is random alphanumeric; codes are unique across the organisation
