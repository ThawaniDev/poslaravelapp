# Customer Management — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Customer Directory, Loyalty Programme, Store Credit, Digital Receipts  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · Dio · Laravel 11 REST API  

---

## 1. Feature Overview

Customer Management maintains a customer database that links to purchase history, loyalty points, store credit balances, and digital receipt delivery. Customers can be attached to transactions at POS checkout for tracking and loyalty purposes. The loyalty programme earns points on qualifying purchases and redeems them for discounts, encouraging repeat business.

### What This Feature Does
- **Customer directory** — searchable database of customers with name, phone, email, address, notes
- **Customer attach at POS** — search and attach a customer to a transaction by phone or loyalty code
- **Quick customer creation** — create a new customer profile inline at POS without leaving the checkout screen
- **Purchase history** — view all past orders for a customer with totals and dates
- **Loyalty programme** — configurable points-per-SAR earning rate; configurable points-to-SAR redemption rate
- **Store credit** — customer balance that can be used as a payment method; funded by refunds or manual top-up
- **Digital receipts** — send receipt to customer via email or WhatsApp after purchase
- **Customer groups** — tag customers into groups (VIP, wholesale, staff) for targeted promotions
- **Customer analytics** — total spend, visit frequency, average basket, last visit date
- **Data privacy** — customers can request data deletion (GDPR-like compliance)

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **POS Terminal** | Customer attached during checkout; loyalty points earned on sale |
| **Order Management** | Purchase history linked through orders |
| **Payments & Finance** | Store credit used as payment method |
| **Roles & Permissions** | `customers.view`, `customers.manage` permissions |
| **Offline/Online Sync** | Customer records synced to local SQLite for offline lookup |
| **Notifications** | Digital receipt delivery |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Promotions & Coupons** | Customer-specific and group-specific promotions |
| **Reports & Analytics** | Customer reports, retention analysis, top customers |
| **POS Terminal** | Loyalty points display on POS; store credit as payment option |

### Features to Review After Changing This Feature
1. **POS Terminal** — customer lookup and loyalty display at checkout
2. **Promotions & Coupons** — customer group targeting
3. **Payments & Finance** — store credit payment method balance resolution

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local customer directory for offline lookup |
| **riverpod** / **flutter_bloc** | State management for customer search, profile, loyalty |
| **dio** | HTTP client for customer API calls and digital receipt delivery |
| **uuid** | Generate UUIDs for customer records |
| **intl** | Phone formatting, date display |
| **url_launcher** | Open WhatsApp for receipt sharing |

### 3.2 Technologies
- **Flutter 3.x Desktop** — customer screens in POS back-office and lookup at checkout
- **Dart** — loyalty point calculation, store credit balance management
- **SQLite (via Drift)** — local customer cache for fast offline search
- **PostgreSQL** — cloud customer master; authoritative for loyalty balances
- **Laravel 11 REST API** — customer CRUD, loyalty transactions, digital receipt delivery
- **WhatsApp Business API / Twilio** — send digital receipts via WhatsApp
- **Email (SMTP / Mailgun)** — digital receipt email delivery

---

## 4. Screens

### 4.1 Customer Directory Screen
| Field | Detail |
|---|---|
| **Route** | `/customers` |
| **Purpose** | Browse and manage all customers |
| **Table Columns** | Name, Phone, Email, Group, Total spend, Visit count, Loyalty points, Last visit, Actions |
| **Search** | By name, phone, email, loyalty code |
| **Filters** | Group (VIP/wholesale/staff/all), Has loyalty, Date range of last visit |
| **Row Actions** | Edit, View history, Adjust loyalty, Adjust store credit, Send message, Delete |
| **Bulk Actions** | Add to group, Export CSV |
| **Access** | `customers.view` |

### 4.2 Customer Create / Edit Screen
| Field | Detail |
|---|---|
| **Route** | `/customers/create` or `/customers/{id}/edit` |
| **Purpose** | Full customer profile form |
| **Fields** | Name, Phone (required), Email, Address, Date of birth, Group assignment, Notes, Tax registration number (for B2B) |
| **Auto-generated** | Loyalty code (unique alphanumeric), Created date, Last visit |
| **Access** | `customers.manage` |

### 4.3 Customer Profile / History Screen
| Field | Detail |
|---|---|
| **Route** | `/customers/{id}` |
| **Purpose** | Detailed customer profile with purchase history |
| **Sections** | Profile header (name, phone, group, member since), KPI tiles (total spend, visits, avg basket, loyalty points), Purchase history table, Loyalty transaction log, Store credit transaction log |
| **Actions** | Edit, Adjust loyalty (add/deduct manually), Top-up store credit, Send receipt for past order, Delete customer |
| **Access** | `customers.view` |

### 4.4 Customer Lookup (POS Inline)
| Field | Detail |
|---|---|
| **Route** | Overlay within `/pos` |
| **Purpose** | Quick search and attach customer to current transaction |
| **Search** | Phone number, name, or loyalty code |
| **Display** | Matching results with name, phone, loyalty balance |
| **Actions** | Select (attach), Create new customer (inline form with just name + phone) |
| **Access** | `customers.view` (all cashiers) |

### 4.5 Loyalty Configuration Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/loyalty` |
| **Purpose** | Configure loyalty programme parameters |
| **Fields** | Earning rate (points per SAR, e.g. 1 point per 1 SAR), Redemption rate (SAR per point, e.g. 0.01 SAR per point = 100 points = 1 SAR), Minimum redemption (e.g. 100 points), Expiry (months before points expire, 0 = never), Excluded categories, Double-points days |
| **Access** | `settings.manage` (Owner) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/customers` | GET | Paginated customer list | Bearer token + `customers.view` |
| `GET /api/customers/{id}` | GET | Customer detail with analytics | Bearer token + `customers.view` |
| `POST /api/customers` | POST | Create customer | Bearer token + `customers.manage` |
| `PUT /api/customers/{id}` | PUT | Update customer | Bearer token + `customers.manage` |
| `DELETE /api/customers/{id}` | DELETE | Delete customer (data wipe) | Bearer token + `customers.manage` |
| `GET /api/customers/{id}/orders` | GET | Customer purchase history | Bearer token + `customers.view` |
| `GET /api/customers/{id}/loyalty` | GET | Loyalty transaction log | Bearer token + `customers.view` |
| `POST /api/customers/{id}/loyalty/adjust` | POST | Manual loyalty adjustment | Bearer token + `customers.manage` |
| `POST /api/customers/{id}/loyalty/redeem` | POST | Redeem loyalty points | Bearer token + `customers.manage` |
| `GET /api/customers/{id}/store-credit` | GET | Store credit transaction log | Bearer token + `customers.view` |
| `POST /api/customers/{id}/store-credit/top-up` | POST | Top up store credit | Bearer token + `customers.manage` |
| `POST /api/customers/{id}/receipt` | POST | Send digital receipt (email/WhatsApp) | Bearer token |
| `GET /api/customers/search?q={query}` | GET | Quick search for POS lookup | Bearer token |
| `GET /api/pos/customers/sync?since={ts}` | GET | Delta sync of customers for POS | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `CustomerRepository` | Local Drift DB CRUD for customers; offline search |
| `CustomerSyncService` | Delta sync of customer records with cloud |
| `LoyaltyService` | Calculates points earned on transaction; validates redemption; manages balance |
| `StoreCreditService` | Manages store credit balance; tops up on refund; deducts on payment |
| `CustomerSearchService` | Fast local search by phone, name, loyalty code |
| `DigitalReceiptService` | Triggers receipt delivery via email or WhatsApp through API |

---

## 6. Full Database Schema

### 6.1 Tables

#### `customers`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| name | VARCHAR(255) | NOT NULL | |
| phone | VARCHAR(50) | NOT NULL | |
| email | VARCHAR(255) | NULLABLE | |
| address | TEXT | NULLABLE | |
| date_of_birth | DATE | NULLABLE | |
| loyalty_code | VARCHAR(20) | UNIQUE | Auto-generated |
| loyalty_points | INT | DEFAULT 0 | Current balance |
| store_credit_balance | DECIMAL(12,2) | DEFAULT 0 | |
| group_id | UUID | FK → customer_groups(id), NULLABLE | |
| tax_registration_number | VARCHAR(50) | NULLABLE | For B2B customers |
| notes | TEXT | NULLABLE | |
| total_spend | DECIMAL(14,2) | DEFAULT 0 | Running total |
| visit_count | INT | DEFAULT 0 | |
| last_visit_at | TIMESTAMP | NULLABLE | |
| sync_version | INT | DEFAULT 1 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |
| deleted_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE customers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255),
    address TEXT,
    date_of_birth DATE,
    loyalty_code VARCHAR(20) UNIQUE,
    loyalty_points INT DEFAULT 0,
    store_credit_balance DECIMAL(12,2) DEFAULT 0,
    group_id UUID REFERENCES customer_groups(id),
    tax_registration_number VARCHAR(50),
    notes TEXT,
    total_spend DECIMAL(14,2) DEFAULT 0,
    visit_count INT DEFAULT 0,
    last_visit_at TIMESTAMP,
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);
```

#### `customer_groups`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| name | VARCHAR(100) | NOT NULL | e.g. VIP, Wholesale, Staff |
| discount_percent | DECIMAL(5,2) | DEFAULT 0 | Auto-applied group discount |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE customer_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(100) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `loyalty_transactions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| customer_id | UUID | FK → customers(id), NOT NULL | |
| type | VARCHAR(20) | NOT NULL | earn, redeem, adjust, expire |
| points | INT | NOT NULL | Positive for earn, negative for redeem/expire |
| balance_after | INT | NOT NULL | Balance after this transaction |
| order_id | UUID | FK → orders(id), NULLABLE | Linked order |
| notes | VARCHAR(255) | NULLABLE | |
| performed_by | UUID | FK → users(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE loyalty_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    type VARCHAR(20) NOT NULL,
    points INT NOT NULL,
    balance_after INT NOT NULL,
    order_id UUID REFERENCES orders(id),
    notes VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `store_credit_transactions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| customer_id | UUID | FK → customers(id), NOT NULL | |
| type | VARCHAR(20) | NOT NULL | refund_credit, top_up, spend, adjust |
| amount | DECIMAL(12,2) | NOT NULL | Positive for credit, negative for spend |
| balance_after | DECIMAL(12,2) | NOT NULL | |
| order_id | UUID | FK → orders(id), NULLABLE | |
| payment_id | UUID | FK → payments(id), NULLABLE | |
| notes | VARCHAR(255) | NULLABLE | |
| performed_by | UUID | FK → users(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE store_credit_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    order_id UUID REFERENCES orders(id),
    payment_id UUID REFERENCES payments(id),
    notes VARCHAR(255),
    performed_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `loyalty_config`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL, UNIQUE | |
| points_per_sar | DECIMAL(5,2) | DEFAULT 1 | Points earned per 1 SAR spent |
| sar_per_point | DECIMAL(8,4) | DEFAULT 0.01 | Redemption value per point |
| min_redemption_points | INT | DEFAULT 100 | |
| points_expiry_months | INT | DEFAULT 0 | 0 = no expiry |
| excluded_category_ids | JSONB | DEFAULT '[]' | Categories that don't earn points |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE loyalty_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL UNIQUE REFERENCES organizations(id),
    points_per_sar DECIMAL(5,2) DEFAULT 1,
    sar_per_point DECIMAL(8,4) DEFAULT 0.01,
    min_redemption_points INT DEFAULT 100,
    points_expiry_months INT DEFAULT 0,
    excluded_category_ids JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `digital_receipt_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| order_id | UUID | FK → orders(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NOT NULL | |
| channel | VARCHAR(20) | NOT NULL | email, whatsapp |
| destination | VARCHAR(255) | NOT NULL | Email address or phone number |
| status | VARCHAR(20) | DEFAULT 'sent' | sent, delivered, failed |
| sent_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE digital_receipt_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    channel VARCHAR(20) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'sent',
    sent_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `customers_org_phone` | (organization_id, phone) | B-TREE | Phone search |
| `customers_loyalty_code` | loyalty_code | UNIQUE | Loyalty code lookup |
| `customers_org_name` | (organization_id, name) | B-TREE | Name search |
| `loyalty_txn_customer` | customer_id | B-TREE | Loyalty history |
| `store_credit_txn_customer` | customer_id | B-TREE | Credit history |
| `digital_receipt_order` | order_id | B-TREE | Receipt log per order |

### 6.3 Relationships Diagram
```
organizations ──1:N──▶ customers
organizations ──1:N──▶ customer_groups
organizations ──1:1──▶ loyalty_config
customer_groups ──1:N──▶ customers
customers ──1:N──▶ loyalty_transactions
customers ──1:N──▶ store_credit_transactions
customers ──1:N──▶ digital_receipt_log
orders ──1:N──▶ loyalty_transactions
orders ──1:N──▶ store_credit_transactions
orders ──1:N──▶ digital_receipt_log
```

---

## 7. Business Rules

1. **Phone number uniqueness** — phone number must be unique within the organisation; duplicate phone rejects creation
2. **Loyalty earned on net amount** — loyalty points are calculated on the net sale amount (after discounts, before tax); returns deduct the proportional points
3. **Minimum redemption** — loyalty points can only be redeemed if the balance meets or exceeds `min_redemption_points`
4. **Points expiry** — if configured, points that have not been used within `points_expiry_months` from the date earned are expired via a nightly cron job
5. **Store credit cannot go negative** — if a payment attempt exceeds the store credit balance, the system applies only the available balance and prompts for the remainder in another method
6. **Customer group discount stacking** — group discount is applied before promotion/coupon discounts; total discount cannot exceed the item price (floor at 0 SAR)
7. **Digital receipt prompt** — after checkout, if a customer is attached and has an email/phone, the POS prompts "Send receipt?" with one-tap send
8. **Customer deletion** — deleting a customer soft-deletes the record; purchase history is retained with anonymised customer reference for reporting integrity
9. **Loyalty code format** — auto-generated 8-character alphanumeric code (e.g. TH4X92KP); can be printed on a loyalty card or barcode
10. **B2B customers** — customers with a `tax_registration_number` are treated as B2B; their ZATCA invoices include the customer's VAT number
