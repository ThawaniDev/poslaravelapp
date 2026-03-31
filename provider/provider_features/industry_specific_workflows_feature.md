# Industry-Specific Workflows — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Pharmacy, Jewelry, Mobile Phone Shop, Flower Shop, Bakery, Restaurant/Café — Industry-Specific Features  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11  

---

## 1. Feature Overview

Industry-Specific Workflows provides specialized POS functionality for each supported business type. These are feature modules that activate based on the store's business type selection during onboarding. Each vertical adds custom fields, workflows, screens, and business logic that generic retail POS systems lack.

### What This Feature Does

### Groceries
- **Produce management** — track produce items with weight-based pricing; scale integration
- **Age-restricted products** — require age verification for alcohol, tobacco sales; optional ID
- **Weighing scale integration** — connect to label printers and scales for produce
- **Loyalty points** — earn points on grocery purchases; redeemable for discounts



#### 🏥 Pharmacy
- **Prescription management** — link sales to prescriptions; track prescription number, doctor, patient
- **Drug scheduling** — categorize products by drug schedule (OTC, prescription-only, controlled)
- **Expiry date tracking** — FEFO (First Expiry, First Out) inventory management; expiry alerts
- **Insurance claims** — process insurance-covered prescriptions; partial payment by insurance
- **Drug interaction warnings** — optional alerts when selling conflicting medications together
- **Batch/lot tracking** — track manufacturer batch numbers for recalls

#### 💎 Jewelry
- **Gold/silver weight tracking** — track precious metal weight (grams) per item; weight-based pricing
- **Karat tracking** — 18K, 21K, 22K, 24K gold with different pricing per karat
- **Daily gold rate** — update daily gold/silver rates; prices auto-calculate based on weight × rate + making charges
- **Making charges** — flat or percentage-based fabrication charges per item
- **Stone details** — track gemstone type, weight (carat), quality, certification number
- **Buyback/exchange** — purchase gold from customers at buyback rate; gold exchange transactions
- **Certificate management** — link product to lab certificates (GIA, etc.)

#### 📱 Mobile Phone Shop
- **IMEI tracking** — unique IMEI number per device; IMEI validation (Luhn check)
- **Device condition grading** — grade used devices (A/B/C/D); affects pricing
- **Warranty tracking** — manufacturer warranty + store warranty periods; warranty claim processing
- **Trade-in** — customer trades old device; value assessed by condition grade; applied as credit
- **Repair tracking** — repair jobs with status (received → diagnosing → repairing → testing → ready → collected)
- **Accessory bundling** — suggest compatible accessories (case, screen protector) for selected device
- **Activation services** — SIM activation, carrier setup tracked as service items

#### 🌸 Flower Shop
- **Arrangement builder** — compose flower arrangements from individual stems; calculate total price
- **Freshness tracking** — received date, expected vase life; auto-discount as flowers near end of life
- **Delivery scheduling** — date/time delivery slots; recurring delivery (weekly arrangements)
- **Occasion templates** — pre-built arrangements for occasions (wedding, funeral, birthday, Eid)
- **Custom message cards** — print message cards with customer's text
- **Subscription bouquets** — recurring weekly/monthly flower delivery subscriptions

#### 🍞 Bakery
- **Production planning** — daily production schedule based on demand forecast and par levels
- **Recipe management** — recipes with ingredient lists; auto-deduct raw materials from inventory
- **Batch production** — produce in batches; track yield vs expected yield
- **Made-to-order** — custom cake orders with delivery date; order queue for production
- **Ingredient cost tracking** — auto-calculate cost per product based on recipe ingredients
- **Shelf life management** — short shelf life items tracked; auto-discount before expiry; auto-remove after
- **Display case management** — track items on display vs in storage

#### 🍽️ Restaurant / Café
- **Table management** — floor plan, table assignment, table status (available, occupied, reserved)
- **Kitchen Display System (KDS)** — order items sent to kitchen display; ticket management
- **Course management** — fire courses sequentially (appetizer → main → dessert)
- **Split bill** — split by item, equal split, or custom amount split
- **Modifiers & customizations** — add/remove/substitute ingredients per item
- **Tab management** — open tabs; add items over time; close tab to pay
- **Dine-in / Takeaway / Delivery** — different order types with appropriate workflows
- **Kitchen printer routing** — different item categories print to different kitchen printers

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Business Type & Onboarding** | Activates workflows based on business type |
| **Product Catalog** | Extended product fields per industry |
| **Inventory Management** | Industry-specific inventory features (FEFO, batch tracking) |
| **Order Management** | Industry-specific order workflows |
| **POS Terminal** | Industry POS screen customizations |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Renders industry-specific UI elements |
| **Reports & Analytics** | Industry-specific reports |
| **Barcode & Label Printing** | Industry-specific label formats |

### Features to Review After Changing This Feature
1. **POS Terminal** — industry-specific button placement and workflows
2. **Product Catalog** — custom fields per business type
3. **Inventory Management** — batch tracking, FEFO logic

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — industry-specific tables |
| **riverpod** / **flutter_bloc** | State management for industry workflows |
| **flutter_reorderable_grid_view** | Table floor plan arrangement (restaurant) |
| **intl** | Weight and measurement formatting |
| **pdf** | Certificate printing (jewelry) |

### 3.2 Technologies
- **Feature module pattern** — each industry is a self-contained Flutter module loaded based on business type
- **Conditional table creation** — Drift migrations create industry-specific tables only when the business type requires them
- **Kitchen Display System** — secondary screen or tablet running a KDS view via WebSocket from POS
- **FEFO algorithm** — First-Expiry-First-Out stock selection; Drift queries order by expiry date
- **IMEI validation** — Luhn algorithm for IMEI check digit verification
- **Gold rate API** — optional integration with gold rate API for auto-updating daily prices

---

## 4. Screens

### 4.1 Pharmacy Screens
| Screen | Route | Purpose |
|---|---|---|
| Prescription Entry | `/pharmacy/prescription` | Link sale to prescription details |
| Drug Search (enhanced) | POS search overlay | Search by active ingredient, trade name, or barcode with schedule badge |
| Insurance Claim | `/pharmacy/insurance` | Process insurance-covered prescription |
| Expiry Dashboard | `/pharmacy/expiry` | Products expiring soon; FEFO compliance |
| Batch/Lot Lookup | `/pharmacy/batch/{barcode}` | View batch info, recall status |

### 4.2 Jewelry Screens
| Screen | Route | Purpose |
|---|---|---|
| Daily Rate Entry | `/jewelry/rates` | Set today's gold/silver rates by karat |
| Weight Calculator | POS side panel | Gold weight × rate + making charges = price |
| Buyback Entry | `/jewelry/buyback` | Purchase gold from customer |
| Exchange Transaction | `/jewelry/exchange` | Customer exchanges old gold for new |
| Certificate View | `/jewelry/certificate/{id}` | View/print gemstone certificate |

### 4.3 Mobile Phone Shop Screens
| Screen | Route | Purpose |
|---|---|---|
| IMEI Entry | POS item dialog | Scan/enter IMEI for device sale |
| Device Trade-In | `/mobile/trade-in` | Assess and accept trade-in device |
| Repair Queue | `/mobile/repairs` | Kanban: Received → Diagnosing → Repairing → Ready → Collected |
| Warranty Lookup | `/mobile/warranty/{imei}` | Check warranty status for a device |
| Device Grading | `/mobile/grade` | Grade used device condition |

### 4.4 Flower Shop Screens
| Screen | Route | Purpose |
|---|---|---|
| Arrangement Builder | `/flowers/arrangement` | Compose arrangement from stems |
| Freshness Dashboard | `/flowers/freshness` | Track vase life; items needing markdown |
| Delivery Calendar | `/flowers/deliveries` | Calendar view of scheduled deliveries |
| Message Card Editor | `/flowers/message-card` | Type and preview customer message |
| Subscription Manager | `/flowers/subscriptions` | Manage recurring bouquet deliveries |

### 4.5 Bakery Screens
| Screen | Route | Purpose |
|---|---|---|
| Production Schedule | `/bakery/production` | Daily bake plan with quantities |
| Recipe Manager | `/bakery/recipes` | Create/edit recipes with ingredients |
| Custom Order Entry | `/bakery/custom-order` | Custom cake/item order with delivery date |
| Batch Production Log | `/bakery/batches` | Log production batches; record yield |
| Display Case Tracker | `/bakery/display` | Items currently on display |

### 4.6 Restaurant / Café Screens
| Screen | Route | Purpose |
|---|---|---|
| Floor Plan & Tables | `/restaurant/tables` | Visual table layout; status indicators |
| Kitchen Display (KDS) | `/restaurant/kds` | Kitchen ticket queue; mark items as ready |
| Table Order View | `/restaurant/table/{id}` | Current order for a table; add items |
| Split Bill Dialog | POS checkout overlay | Split options: by item, equal, custom |
| Tab Manager | `/restaurant/tabs` | Open tabs; search; close and pay |
| Reservation Calendar | `/restaurant/reservations` | Table reservations by date/time |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| **Pharmacy** | | | |
| `POST /api/pharmacy/prescriptions` | POST | Create prescription record | Bearer token |
| `GET /api/pharmacy/expiry-alerts` | GET | Products expiring within N days | Bearer token |
| `POST /api/pharmacy/insurance-claims` | POST | Submit insurance claim | Bearer token |
| **Jewelry** | | | |
| `PUT /api/jewelry/daily-rates` | PUT | Update daily gold/silver rates | Bearer token |
| `GET /api/jewelry/daily-rates` | GET | Get current rates | Bearer token |
| `POST /api/jewelry/buyback` | POST | Record buyback transaction | Bearer token |
| **Mobile** | | | |
| `POST /api/mobile/imei/validate` | POST | Validate IMEI number | Bearer token |
| `POST /api/mobile/trade-in` | POST | Record trade-in | Bearer token |
| `GET /api/mobile/repairs` | GET | List repair jobs | Bearer token |
| `PUT /api/mobile/repairs/{id}/status` | PUT | Update repair status | Bearer token |
| **Flower** | | | |
| `POST /api/flowers/arrangements` | POST | Save custom arrangement | Bearer token |
| `GET /api/flowers/subscriptions` | GET | List active subscriptions | Bearer token |
| **Bakery** | | | |
| `GET /api/bakery/production-schedule` | GET | Daily production plan | Bearer token |
| `POST /api/bakery/batches` | POST | Log production batch | Bearer token |
| `GET /api/bakery/recipes` | GET | List recipes | Bearer token |
| **Restaurant** | | | |
| `GET /api/restaurant/tables` | GET | Table layout and statuses | Bearer token |
| `PUT /api/restaurant/tables/{id}/status` | PUT | Update table status | Bearer token |
| `POST /api/restaurant/kds/ready` | POST | Mark KDS ticket as ready | Bearer token |
| `GET /api/restaurant/reservations` | GET | List reservations | Bearer token |
| `POST /api/restaurant/reservations` | POST | Create reservation | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `PharmacyService` | Prescription management, drug schedule checks, insurance claims |
| `JewelryService` | Gold rate management, weight-based pricing, buyback |
| `MobileShopService` | IMEI tracking, repair queue, trade-in, warranty |
| `FlowerShopService` | Arrangement builder, freshness tracking, subscriptions |
| `BakeryService` | Production planning, recipe costing, batch tracking |
| `RestaurantService` | Table management, KDS, split bill, tab management |
| `IndustryModuleLoader` | Loads industry-specific modules based on business type |

---

## 6. Full Database Schema

### 6.1 Tables

#### Pharmacy Tables

##### `prescriptions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| order_id | UUID | FK → orders(id), NULLABLE | Linked sale |
| prescription_number | VARCHAR(50) | NOT NULL | |
| patient_name | VARCHAR(200) | NOT NULL | |
| patient_id | VARCHAR(50) | NULLABLE | National ID / insurance ID |
| doctor_name | VARCHAR(200) | NULLABLE | |
| doctor_license | VARCHAR(50) | NULLABLE | |
| insurance_provider | VARCHAR(100) | NULLABLE | |
| insurance_claim_amount | DECIMAL(12,3) | NULLABLE | |
| notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE prescriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID REFERENCES orders(id),
    prescription_number VARCHAR(50) NOT NULL,
    patient_name VARCHAR(200) NOT NULL,
    patient_id VARCHAR(50),
    doctor_name VARCHAR(200),
    doctor_license VARCHAR(50),
    insurance_provider VARCHAR(100),
    insurance_claim_amount DECIMAL(12,3),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

##### `drug_schedules`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id), NOT NULL, UNIQUE | |
| schedule_type | VARCHAR(20) | NOT NULL | otc, prescription_only, controlled |
| active_ingredient | VARCHAR(200) | NULLABLE | |
| dosage_form | VARCHAR(50) | NULLABLE | tablet, capsule, syrup, injection |
| strength | VARCHAR(50) | NULLABLE | e.g., "500mg" |
| manufacturer | VARCHAR(200) | NULLABLE | |
| requires_prescription | BOOLEAN | DEFAULT FALSE | |

```sql
CREATE TABLE drug_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL UNIQUE REFERENCES products(id),
    schedule_type VARCHAR(20) NOT NULL DEFAULT 'otc',
    active_ingredient VARCHAR(200),
    dosage_form VARCHAR(50),
    strength VARCHAR(50),
    manufacturer VARCHAR(200),
    requires_prescription BOOLEAN DEFAULT FALSE
);
```

#### Jewelry Tables

##### `daily_metal_rates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| metal_type | VARCHAR(20) | NOT NULL | gold, silver, platinum |
| karat | VARCHAR(10) | NULLABLE | 18K, 21K, 22K, 24K (gold only) |
| rate_per_gram | DECIMAL(12,3) | NOT NULL | |
| buyback_rate_per_gram | DECIMAL(12,3) | NULLABLE | Rate for buying back from customers |
| effective_date | DATE | NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE daily_metal_rates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10),
    rate_per_gram DECIMAL(12,3) NOT NULL,
    buyback_rate_per_gram DECIMAL(12,3),
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, metal_type, karat, effective_date)
);
```

##### `jewelry_product_details`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id), NOT NULL, UNIQUE | |
| metal_type | VARCHAR(20) | NOT NULL | gold, silver, platinum |
| karat | VARCHAR(10) | NULLABLE | |
| gross_weight_g | DECIMAL(10,3) | NOT NULL | Total weight in grams |
| net_weight_g | DECIMAL(10,3) | NOT NULL | Metal weight excluding stones |
| making_charges_type | VARCHAR(20) | DEFAULT 'percentage' | flat, percentage, per_gram |
| making_charges_value | DECIMAL(10,2) | NOT NULL | |
| stone_type | VARCHAR(50) | NULLABLE | diamond, ruby, emerald, etc. |
| stone_weight_carat | DECIMAL(10,3) | NULLABLE | |
| stone_count | INTEGER | NULLABLE | |
| certificate_number | VARCHAR(100) | NULLABLE | Lab certificate ID |
| certificate_url | VARCHAR(500) | NULLABLE | |

```sql
CREATE TABLE jewelry_product_details (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL UNIQUE REFERENCES products(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10),
    gross_weight_g DECIMAL(10,3) NOT NULL,
    net_weight_g DECIMAL(10,3) NOT NULL,
    making_charges_type VARCHAR(20) DEFAULT 'percentage',
    making_charges_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    stone_type VARCHAR(50),
    stone_weight_carat DECIMAL(10,3),
    stone_count INTEGER,
    certificate_number VARCHAR(100),
    certificate_url VARCHAR(500)
);
```

##### `buyback_transactions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| metal_type | VARCHAR(20) | NOT NULL | |
| karat | VARCHAR(10) | NOT NULL | |
| weight_g | DECIMAL(10,3) | NOT NULL | |
| rate_per_gram | DECIMAL(12,3) | NOT NULL | Rate at time of purchase |
| total_amount | DECIMAL(12,3) | NOT NULL | |
| payment_method | VARCHAR(20) | NOT NULL | cash, bank_transfer, credit_note |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE buyback_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    metal_type VARCHAR(20) NOT NULL,
    karat VARCHAR(10) NOT NULL,
    weight_g DECIMAL(10,3) NOT NULL,
    rate_per_gram DECIMAL(12,3) NOT NULL,
    total_amount DECIMAL(12,3) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### Mobile Phone Shop Tables

##### `device_imei_records`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id), NOT NULL | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| imei | VARCHAR(15) | NOT NULL | 15-digit IMEI |
| imei2 | VARCHAR(15) | NULLABLE | Dual-SIM second IMEI |
| serial_number | VARCHAR(50) | NULLABLE | |
| condition_grade | VARCHAR(5) | NULLABLE | A, B, C, D (used devices) |
| purchase_price | DECIMAL(12,3) | NULLABLE | |
| status | VARCHAR(20) | DEFAULT 'in_stock' | in_stock, sold, traded_in, returned |
| warranty_end_date | DATE | NULLABLE | |
| store_warranty_end_date | DATE | NULLABLE | |
| sold_order_id | UUID | FK → orders(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE device_imei_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    imei VARCHAR(15) NOT NULL,
    imei2 VARCHAR(15),
    serial_number VARCHAR(50),
    condition_grade VARCHAR(5),
    purchase_price DECIMAL(12,3),
    status VARCHAR(20) DEFAULT 'in_stock',
    warranty_end_date DATE,
    store_warranty_end_date DATE,
    sold_order_id UUID REFERENCES orders(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

##### `repair_jobs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| device_description | VARCHAR(200) | NOT NULL | e.g., "iPhone 14 Pro Max" |
| imei | VARCHAR(15) | NULLABLE | |
| issue_description | TEXT | NOT NULL | |
| status | VARCHAR(20) | DEFAULT 'received' | received, diagnosing, repairing, testing, ready, collected, cancelled |
| diagnosis_notes | TEXT | NULLABLE | |
| repair_notes | TEXT | NULLABLE | |
| estimated_cost | DECIMAL(12,3) | NULLABLE | |
| final_cost | DECIMAL(12,3) | NULLABLE | |
| parts_used | JSONB | NULLABLE | [{product_id, name, qty, price}] |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| received_at | TIMESTAMP | DEFAULT NOW() | |
| estimated_ready_at | TIMESTAMP | NULLABLE | |
| completed_at | TIMESTAMP | NULLABLE | |
| collected_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE repair_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    device_description VARCHAR(200) NOT NULL,
    imei VARCHAR(15),
    issue_description TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'received',
    diagnosis_notes TEXT,
    repair_notes TEXT,
    estimated_cost DECIMAL(12,3),
    final_cost DECIMAL(12,3),
    parts_used JSONB,
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    received_at TIMESTAMP DEFAULT NOW(),
    estimated_ready_at TIMESTAMP,
    completed_at TIMESTAMP,
    collected_at TIMESTAMP
);
```

##### `trade_in_records`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| device_description | VARCHAR(200) | NOT NULL | |
| imei | VARCHAR(15) | NULLABLE | |
| condition_grade | VARCHAR(5) | NOT NULL | A, B, C, D |
| assessed_value | DECIMAL(12,3) | NOT NULL | |
| applied_to_order_id | UUID | FK → orders(id), NULLABLE | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE trade_in_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    device_description VARCHAR(200) NOT NULL,
    imei VARCHAR(15),
    condition_grade VARCHAR(5) NOT NULL,
    assessed_value DECIMAL(12,3) NOT NULL,
    applied_to_order_id UUID REFERENCES orders(id),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### Flower Shop Tables

##### `flower_arrangements`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| name | VARCHAR(200) | NOT NULL | |
| occasion | VARCHAR(50) | NULLABLE | wedding, funeral, birthday, eid, valentines |
| items_json | JSONB | NOT NULL | [{product_id, quantity, price}] |
| total_price | DECIMAL(12,3) | NOT NULL | |
| is_template | BOOLEAN | DEFAULT FALSE | Reusable template |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE flower_arrangements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(200) NOT NULL,
    occasion VARCHAR(50),
    items_json JSONB NOT NULL,
    total_price DECIMAL(12,3) NOT NULL,
    is_template BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

##### `flower_freshness_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| product_id | UUID | FK → products(id), NOT NULL | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| received_date | DATE | NOT NULL | |
| expected_vase_life_days | INTEGER | NOT NULL | |
| markdown_date | DATE | NULLABLE | Auto-calculated: received + vase_life - 2 |
| dispose_date | DATE | NULLABLE | Auto-calculated: received + vase_life |
| quantity | INTEGER | NOT NULL | |
| status | VARCHAR(20) | DEFAULT 'fresh' | fresh, marked_down, disposed |

```sql
CREATE TABLE flower_freshness_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    received_date DATE NOT NULL,
    expected_vase_life_days INTEGER NOT NULL,
    markdown_date DATE,
    dispose_date DATE,
    quantity INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'fresh'
);
```

##### `flower_subscriptions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NOT NULL | |
| arrangement_template_id | UUID | FK → flower_arrangements(id), NULLABLE | |
| frequency | VARCHAR(20) | NOT NULL | weekly, biweekly, monthly |
| delivery_day | VARCHAR(10) | NULLABLE | monday, tuesday, etc. |
| delivery_address | TEXT | NOT NULL | |
| price_per_delivery | DECIMAL(12,3) | NOT NULL | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| next_delivery_date | DATE | NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE flower_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    arrangement_template_id UUID REFERENCES flower_arrangements(id),
    frequency VARCHAR(20) NOT NULL,
    delivery_day VARCHAR(10),
    delivery_address TEXT NOT NULL,
    price_per_delivery DECIMAL(12,3) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    next_delivery_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### Bakery Tables

##### `bakery_recipes` (extends recipes table from inventory)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | Output product |
| name | VARCHAR(200) | NOT NULL | |
| expected_yield | INTEGER | NOT NULL | Expected output quantity |
| prep_time_minutes | INTEGER | NULLABLE | |
| bake_time_minutes | INTEGER | NULLABLE | |
| bake_temperature_c | INTEGER | NULLABLE | |
| instructions | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE bakery_recipes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    name VARCHAR(200) NOT NULL,
    expected_yield INTEGER NOT NULL DEFAULT 1,
    prep_time_minutes INTEGER,
    bake_time_minutes INTEGER,
    bake_temperature_c INTEGER,
    instructions TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

##### `production_schedules`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| recipe_id | UUID | FK → bakery_recipes(id), NOT NULL | |
| schedule_date | DATE | NOT NULL | |
| planned_batches | INTEGER | NOT NULL | |
| actual_batches | INTEGER | NULLABLE | |
| planned_yield | INTEGER | NOT NULL | |
| actual_yield | INTEGER | NULLABLE | |
| status | VARCHAR(20) | DEFAULT 'planned' | planned, in_progress, completed |
| notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE production_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    recipe_id UUID NOT NULL REFERENCES bakery_recipes(id),
    schedule_date DATE NOT NULL,
    planned_batches INTEGER NOT NULL DEFAULT 1,
    actual_batches INTEGER,
    planned_yield INTEGER NOT NULL,
    actual_yield INTEGER,
    status VARCHAR(20) DEFAULT 'planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

##### `custom_cake_orders`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| order_id | UUID | FK → orders(id), NULLABLE | Linked POS order |
| description | TEXT | NOT NULL | Custom order description |
| size | VARCHAR(50) | NULLABLE | e.g., "2-tier", "12-inch" |
| flavor | VARCHAR(100) | NULLABLE | |
| decoration_notes | TEXT | NULLABLE | |
| delivery_date | DATE | NOT NULL | |
| delivery_time | TIME | NULLABLE | |
| price | DECIMAL(12,3) | NOT NULL | |
| deposit_paid | DECIMAL(12,3) | DEFAULT 0 | |
| status | VARCHAR(20) | DEFAULT 'ordered' | ordered, in_production, ready, delivered |
| reference_image_url | VARCHAR(500) | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE custom_cake_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    order_id UUID REFERENCES orders(id),
    description TEXT NOT NULL,
    size VARCHAR(50),
    flavor VARCHAR(100),
    decoration_notes TEXT,
    delivery_date DATE NOT NULL,
    delivery_time TIME,
    price DECIMAL(12,3) NOT NULL,
    deposit_paid DECIMAL(12,3) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'ordered',
    reference_image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### Restaurant / Café Tables

##### `restaurant_tables`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| table_number | VARCHAR(20) | NOT NULL | |
| display_name | VARCHAR(50) | NULLABLE | e.g., "Patio 1", "VIP Room" |
| seats | INTEGER | NOT NULL | |
| zone | VARCHAR(50) | NULLABLE | indoor, outdoor, patio, vip |
| position_x | INTEGER | DEFAULT 0 | Grid position for floor plan |
| position_y | INTEGER | DEFAULT 0 | |
| status | VARCHAR(20) | DEFAULT 'available' | available, occupied, reserved, cleaning |
| current_order_id | UUID | FK → orders(id), NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE restaurant_tables (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_number VARCHAR(20) NOT NULL,
    display_name VARCHAR(50),
    seats INTEGER NOT NULL DEFAULT 4,
    zone VARCHAR(50),
    position_x INTEGER DEFAULT 0,
    position_y INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'available',
    current_order_id UUID REFERENCES orders(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, table_number)
);
```

##### `kitchen_tickets`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| order_id | UUID | FK → orders(id), NOT NULL | |
| table_id | UUID | FK → restaurant_tables(id), NULLABLE | |
| ticket_number | INTEGER | NOT NULL | Sequential per day |
| items_json | JSONB | NOT NULL | [{name, qty, modifiers, course, notes}] |
| station | VARCHAR(50) | NULLABLE | grill, cold, dessert, bar |
| status | VARCHAR(20) | DEFAULT 'pending' | pending, preparing, ready, served |
| course_number | INTEGER | DEFAULT 1 | |
| fire_at | TIMESTAMP | NULLABLE | When to start preparing |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| completed_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE kitchen_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    table_id UUID REFERENCES restaurant_tables(id),
    ticket_number INTEGER NOT NULL,
    items_json JSONB NOT NULL,
    station VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    course_number INTEGER DEFAULT 1,
    fire_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);
```

##### `table_reservations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| table_id | UUID | FK → restaurant_tables(id), NULLABLE | NULL = any available table |
| customer_name | VARCHAR(200) | NOT NULL | |
| customer_phone | VARCHAR(20) | NULLABLE | |
| party_size | INTEGER | NOT NULL | |
| reservation_date | DATE | NOT NULL | |
| reservation_time | TIME | NOT NULL | |
| duration_minutes | INTEGER | DEFAULT 90 | |
| status | VARCHAR(20) | DEFAULT 'confirmed' | confirmed, seated, completed, cancelled, no_show |
| notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE table_reservations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_id UUID REFERENCES restaurant_tables(id),
    customer_name VARCHAR(200) NOT NULL,
    customer_phone VARCHAR(20),
    party_size INTEGER NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    duration_minutes INTEGER DEFAULT 90,
    status VARCHAR(20) DEFAULT 'confirmed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

##### `open_tabs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| order_id | UUID | FK → orders(id), NOT NULL | Linked order accumulating items |
| customer_name | VARCHAR(200) | NOT NULL | |
| table_id | UUID | FK → restaurant_tables(id), NULLABLE | |
| opened_at | TIMESTAMP | DEFAULT NOW() | |
| closed_at | TIMESTAMP | NULLABLE | |
| status | VARCHAR(20) | DEFAULT 'open' | open, closed |

```sql
CREATE TABLE open_tabs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    customer_name VARCHAR(200) NOT NULL,
    table_id UUID REFERENCES restaurant_tables(id),
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'open'
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| **Pharmacy** | | | |
| `prescriptions_store_date` | (store_id, created_at) | B-TREE | Prescription history |
| `drug_schedules_product` | product_id | B-TREE UNIQUE | Drug info per product |
| **Jewelry** | | | |
| `metal_rates_store_date` | (store_id, effective_date) | B-TREE | Current rate lookup |
| `jewelry_details_product` | product_id | B-TREE UNIQUE | Jewelry info per product |
| `buyback_store_date` | (store_id, created_at) | B-TREE | Buyback history |
| **Mobile** | | | |
| `imei_records_imei` | imei | B-TREE UNIQUE | IMEI lookup |
| `imei_records_store_status` | (store_id, status) | B-TREE | In-stock devices |
| `repair_jobs_store_status` | (store_id, status) | B-TREE | Repair queue |
| **Flower** | | | |
| `freshness_store_status` | (store_id, status) | B-TREE | Freshness dashboard |
| `subscriptions_next_delivery` | (store_id, next_delivery_date) | B-TREE | Due deliveries |
| **Bakery** | | | |
| `production_store_date` | (store_id, schedule_date) | B-TREE | Daily schedule |
| `custom_cakes_store_status` | (store_id, status) | B-TREE | Custom order queue |
| **Restaurant** | | | |
| `tables_store_status` | (store_id, status) | B-TREE | Available tables |
| `kitchen_tickets_store_status` | (store_id, status) | B-TREE | KDS queue |
| `reservations_store_date` | (store_id, reservation_date) | B-TREE | Reservations calendar |
| `open_tabs_store_status` | (store_id, status) | B-TREE | Active tabs |

### 6.3 Relationships Diagram
```
PHARMACY:
    stores ──1:N──▶ prescriptions ──N:1──▶ orders
    products ──1:1──▶ drug_schedules

JEWELRY:
    stores ──1:N──▶ daily_metal_rates
    products ──1:1──▶ jewelry_product_details
    stores ──1:N──▶ buyback_transactions

MOBILE:
    products ──1:N──▶ device_imei_records
    stores ──1:N──▶ repair_jobs
    stores ──1:N──▶ trade_in_records

FLOWER:
    stores ──1:N──▶ flower_arrangements
    stores ──1:N──▶ flower_freshness_log
    stores ──1:N──▶ flower_subscriptions ◀──N:1── customers

BAKERY:
    stores ──1:N──▶ bakery_recipes ──N:1──▶ products
    stores ──1:N──▶ production_schedules ──N:1──▶ bakery_recipes
    stores ──1:N──▶ custom_cake_orders

RESTAURANT:
    stores ──1:N──▶ restaurant_tables
    stores ──1:N──▶ kitchen_tickets ──N:1──▶ orders
    stores ──1:N──▶ table_reservations
    stores ──1:N──▶ open_tabs ──1:1──▶ orders
```

---

## 7. Permissions

Industry-specific permissions are activated per store based on the selected business type. They follow the standard `{module}.{action}` naming convention.

| # | Permission | Module | Description | Description (AR) | Default Roles |
|---|---|---|---|---|---|
| 1 | `pharmacy.prescriptions` | pharmacy | Create and manage prescription records, process insurance claims | إنشاء وإدارة سجلات الوصفات الطبية | Cashier, Branch Manager, Owner |
| 2 | `pharmacy.controlled_substances` | pharmacy | Sell controlled/schedule drugs (requires elevated access) | بيع الأدوية المجدولة والخاضعة للرقابة | Branch Manager, Owner |
| 3 | `jewelry.manage_rates` | jewelry | Update daily gold/silver/platinum rates | تحديث أسعار الذهب والفضة والبلاتين | Branch Manager, Owner |
| 4 | `jewelry.buyback` | jewelry | Process gold buyback transactions | معالجة معاملات إعادة شراء الذهب | Branch Manager, Owner |
| 5 | `mobile.repairs` | mobile | Manage repair queue (create/update status) | إدارة طلبات الإصلاح وتتبع الحالة | Cashier, Branch Manager, Owner |
| 6 | `mobile.trade_in` | mobile | Assess and accept device trade-ins | تقييم وقبول الأجهزة المستبدلة | Branch Manager, Owner |
| 7 | `mobile.imei` | mobile | Register and track IMEI records | تسجيل وتتبع سجلات IMEI | Cashier, Branch Manager, Owner |
| 8 | `flowers.arrangements` | flowers | Create and manage flower arrangements | إنشاء وإدارة تنسيقات الزهور | Cashier, Branch Manager, Owner |
| 9 | `flowers.subscriptions` | flowers | Manage recurring delivery subscriptions | إدارة اشتراكات التوصيل المتكررة | Branch Manager, Owner |
| 10 | `bakery.production` | bakery | Manage production schedules and log batches | إدارة جداول الإنتاج وتسجيل الدفعات | Branch Manager, Owner |
| 11 | `bakery.recipes` | bakery | Create and edit recipes | إنشاء وتعديل الوصفات | Branch Manager, Owner |
| 12 | `bakery.custom_orders` | bakery | Manage custom cake/item orders | إدارة طلبات الكيك والمنتجات المخصصة | Cashier, Branch Manager, Owner |
| 13 | `restaurant.tables` | restaurant | Manage table layout and status | إدارة تخطيط وحالة الطاولات | Branch Manager, Owner |
| 14 | `restaurant.kds` | restaurant | Access kitchen display system, mark items ready | الوصول إلى نظام عرض المطبخ | Kitchen Staff, Branch Manager, Owner |
| 15 | `restaurant.reservations` | restaurant | Create and manage table reservations | إنشاء وإدارة حجوزات الطاولات | Cashier, Branch Manager, Owner |
| 16 | `restaurant.tabs` | restaurant | Open and close tabs | فتح وإغلاق الحسابات المفتوحة | Cashier, Branch Manager, Owner |
| 17 | `restaurant.split_bill` | restaurant | Split bills between diners | تقسيم الفاتورة بين الزبائن | Cashier, Branch Manager, Owner |

> **Note:** These permissions are seeded into `provider_permissions` and are gated at runtime — they only appear in role configuration UI when the store's business type includes the corresponding vertical.

---

## 8. Business Rules

### General
1. **Feature gating by business type** — industry tables are only created and features only shown for the selected business type; switching type requires migration
2. **Hybrid businesses** — a store can activate multiple industry modules (e.g., bakery + café); this is a premium feature

### Pharmacy
3. **Prescription-only enforcement** — products marked `requires_prescription = true` cannot be sold without linking to a prescription record
4. **FEFO enforcement** — when selling a product with batch tracking, the system auto-selects the batch with the earliest expiry date
5. **Expiry alerts** — products expiring within 90 days appear in the expiry dashboard; within 30 days are flagged red

### Jewelry
6. **Dynamic pricing** — jewelry product selling price = (net_weight_g × rate_per_gram) + making_charges + stone_value; recalculated when daily rates change
7. **Buyback rate cap** — buyback rate cannot exceed 95% of the current selling rate
8. **Weight verification** — if a connected scale reads a weight that differs from the recorded product weight by > 0.2g, a warning is shown

### Mobile
9. **IMEI uniqueness** — each IMEI can only exist once in the system; duplicate IMEI entry is blocked
10. **IMEI validation** — IMEI must pass Luhn check digit validation
11. **Repair status notifications** — customer receives SMS (if configured) when repair status changes to "ready"

### Flower
12. **Auto-markdown** — flowers within 2 days of expected disposal date are automatically marked down by a configurable percentage (default 30%)
13. **Subscription scheduling** — the system auto-creates delivery reminders and production tasks for upcoming subscription deliveries

### Bakery
14. **Recipe ingredient deduction** — when a production batch is completed, raw materials are automatically deducted from inventory based on recipe × actual_batches
15. **Yield variance alert** — if actual_yield differs from planned_yield by > 15%, an alert is raised for manager review

### Restaurant
16. **Table auto-release** — tables occupied for longer than 4 hours without activity auto-flag for review; configurable timeout
17. **KDS FIFO** — kitchen tickets are displayed in order received; course-based firing sends courses sequentially when the previous course is marked ready
18. **Tab limit** — open tabs cannot exceed a configurable maximum amount (default 50 SAR / 200 SAR) without manager authorization
