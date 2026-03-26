# Content & Onboarding Management — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Business Types, Onboarding Steps, Help Articles, Pricing Page Content, Layout Templates  
> **Tech Stack:** Laravel 11 + Filament v3 · Spatie Permission · Spatie ActivityLog  

---

## 1. Feature Overview

Content & Onboarding Management controls all the content that providers encounter when they first register, set up their store, and browse the public-facing pricing page. It manages the list of business types shown during onboarding, the step-by-step onboarding flow, help articles and integration guides, and the marketing content (feature bullet lists, FAQs) displayed on the pricing page. Several of these entities are shared with other features — business types are also used by POS Layout Management, and help articles overlap with the Support Ticket System's knowledge base.

### What This Feature Does
- Manage business type options shown to providers during onboarding (supermarket, restaurant, pharmacy, bakery, etc.)
- Manage POS layout templates available per business type
- Manage onboarding flow steps and instructions
- In-app announcement banners (target all providers or specific plan) — cross-link with Platform Announcements
- Help articles and integration guides per third-party platform
- Public pricing page content (package names, feature bullet lists, FAQs)
- **Default category templates per business type** — categories seeded into new stores during onboarding
- **Default shift templates per business type** — pre-built shift schedules (morning, afternoon, evening) seeded into new stores so providers don't start from scratch
- **Default receipt templates per business type** — receipt layout presets (section order, ZATCA QR placement, logo position, footer text) seeded per business type
- **Default industry workflow configuration per business type** — which industry-specific modules activate and their default settings (e.g. Restaurant gets table management + KDS; Pharmacy gets prescription mode + FEFO)
- **Default promotion templates per business type** — sample promotions (happy hour for restaurants, seasonal discounts for retail) seeded as inactive examples
- **Default commission rule templates per business type** — sample commission structures seeded for new stores (e.g. 2% on sales for retail, per-service commission for salons)
- **Default loyalty program config per business type** — earning rates, redemption values, tier thresholds, and program type (points / stamps / cashback) seeded per business type so providers have a ready-to-activate loyalty program
- **Default customer group templates per business type** — predefined customer segments (VIP, Wholesale, Walk-in, Employee, etc.) seeded into new stores with default discount rules
- **Default return policy per business type** — return window, refund methods, restocking fee, void grace period defaults seeded per business type (e.g. electronics: 14 days, full refund; food: no returns)
- **Default waste reason categories per business type** — predefined waste/shrinkage reason codes (expired, damaged, stolen, sampling, spillage, etc.) seeded per business type for inventory management
- **Default appointment booking config per business type** — slot durations, booking windows, cancellation policies, and service category templates for service-based businesses (salons, clinics, repair shops) that enable appointment booking
- **Default gift registry types per business type** — registry type templates (wedding, baby shower, birthday, housewarming) with fulfilment rules, seeded for retail businesses that enable gift registries
- **Default loyalty gamification templates per business type** — badge definitions, challenge templates (e.g. "Buy 5 coffees, get 1 free"), and milestone configurations seeded as inactive examples for businesses enabling loyalty gamification

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **POS Layout Mgmt** | Business types defined here are the parent entity for POS layout templates |
| **Provider Management** | Onboarding steps reference determines the new-store setup wizard flow |
| **Package & Subscription Mgmt** | Pricing page content is linked to subscription plans |
| **Support Ticket System** | Help articles / knowledge base articles are shared or cross-linked |
| **Delivery Platform Mgmt** | Help articles can be tagged to a specific delivery platform for integration guides |
| **Platform Announcements** | In-app banners mentioned here are managed through the Announcements feature |
| **Staff & User Mgmt (Provider)** | Default shift templates seeded to new stores are consumed by the provider's shift scheduling feature |
| **POS Terminal (Provider)** | Default receipt templates determine the initial receipt layout for new stores |
| **Industry Workflows (Provider)** | Industry workflow configuration defines which modules activate per business type |
| **Promotions & Coupons (Provider)** | Default promotion templates are seeded as inactive samples for new stores |
| **Payments & Finance (Provider)** | Default commission rule templates are seeded for new stores |
| **Customer Management (Provider)** | Default loyalty config, customer group templates, and store credit policies are seeded for new stores |
| **Order Management (Provider)** | Default return policy templates define initial return/refund rules for new stores |
| **Inventory Management (Provider)** | Default waste reason categories are seeded for new stores' inventory waste tracking |
| **Nice-to-Have Features (Provider)** | Default appointment booking configs, gift registry types, and loyalty gamification templates are seeded for stores that enable these optional features |

### Features to Review After Changing This Feature
1. **POS Layout Mgmt** — adding or removing a business type affects layout template assignment
2. **Package & Subscription** — pricing page content must stay in sync with plan features
3. **Support Ticket System** — knowledge base articles and help articles may share a table; changes affect both features
4. **Provider Management** — onboarding step changes affect the provider registration wizard
5. **Staff & User Mgmt (Provider)** — default shift template changes affect the initial shifts seeded to new stores
6. **Industry Workflows (Provider)** — workflow config changes affect which modules activate for new stores of that business type
7. **POS Terminal (Provider)** — receipt template changes affect the initial receipt layout for new stores
8. **Customer Management (Provider)** — loyalty config and customer group template changes affect the initial loyalty program and segments for new stores
9. **Order Management (Provider)** — return policy template changes affect the initial return/refund rules for new stores
10. **Inventory Management (Provider)** — waste reason category changes affect the initial waste tracking codes for new stores

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for Business Types, Onboarding Steps, Help Articles, Pricing Content |
| **spatie/laravel-permission** | `content.view`, `content.manage`, `content.pricing` |
| **spatie/laravel-activitylog** | Audit trail for content changes |
| **filament-tiptap-editor** (optional) | Rich-text editor for help article bodies and pricing FAQ answers |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | Eloquent, API Resources, cache invalidation |
| **Filament v3** | Admin UI |
| **PostgreSQL** | Content tables |
| **Redis** | Cache for public pricing page content (5-minute TTL), onboarding steps cache (10-minute TTL), business types cache |
| **DigitalOcean Spaces** | Business type icons, help article images (S3-compatible) |

---

## 4. Pages

### 4.1 Business Types List
| Field | Detail |
|---|---|
| **Route** | `/admin/content/business-types` |
| **Filament Resource** | `BusinessTypeResource` |
| **Table Columns** | Icon, Name, Name (AR), Slug, Is Active badge, Sort Order |
| **Row Actions** | Edit, Deactivate, Reorder (drag-and-drop) |
| **Header Actions** | Create Business Type |
| **Access** | `content.manage` |

### 4.2 Create / Edit Business Type
| Field | Detail |
|---|---|
| **Route** | `/admin/content/business-types/create` or `/{id}/edit` |
| **Form Fields** | Name (EN), Name (AR), Slug (auto-generated from EN name, editable), Icon (file upload or icon-picker), Is Active toggle, Sort Order (int) |
| **Relation Manager** | Default Category Templates — categories seeded into new stores of this business type (e.g. for "Restaurant": Appetizers, Main Course, Beverages, Desserts) |
| **Relation Manager** | Default Shift Templates — shift definitions seeded into new stores (e.g. Morning 06:00–14:00, Afternoon 14:00–22:00, Evening 22:00–06:00) |
| **Relation Manager** | Default Receipt Template — receipt layout preset seeded to new stores of this type |
| **Relation Manager** | Industry Workflow Config — which industry modules activate and their default settings |
| **Relation Manager** | Default Promotion Templates — sample promotions seeded as inactive examples |
| **Relation Manager** | Default Commission Rule Templates — sample commission rules seeded for new stores |
| **Relation Manager** | Default Loyalty Program Config — loyalty earning rates, redemption values, tiers, and program type seeded for new stores |
| **Relation Manager** | Default Customer Group Templates — customer segments (VIP, Wholesale, Walk-in) with default discount rules |
| **Relation Manager** | Default Return Policy — return window days, refund methods, restocking fee, void grace period |
| **Relation Manager** | Default Waste Reason Categories — predefined waste/shrinkage reason codes for inventory tracking |
| **Relation Manager** | Default Appointment Booking Config — slot durations, booking window, cancellation policy for service businesses |
| **Relation Manager** | Default Gift Registry Types — registry type templates with fulfilment rules for retail businesses |
| **Relation Manager** | Default Loyalty Gamification Templates — badges, challenges, milestone configs for gamified loyalty programs |
| **Access** | `content.manage` |

### 4.9 Default Shift Templates (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Table Columns** | Name (EN), Name (AR), Start Time, End Time, Days of Week (tags), Is Default |
| **Row Actions** | Edit, Delete |
| **Create Form** | Name (EN), Name (AR), Start Time (HH:MM), End Time (HH:MM), Days of Week (multi-select: Sun-Sat), Break Duration Minutes, Is Default toggle |
| **Purpose** | When a new store selects this business type during onboarding, these shift definitions are copied into the store's `shift_templates` table. Providers can then modify them. |
| **Access** | `content.manage` |

### 4.10 Default Receipt Template (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Form Fields** | Paper Width select (58mm / 80mm), Header sections (JSONB — ordered list: store_logo, store_name, store_address, store_phone, store_vat_number), Body sections (JSONB — ordered list: items_table, subtotal, discount, vat, total, payment_method), Footer sections (JSONB — ordered list: zatca_qr, receipt_number, cashier_name, thank_you_message, custom_footer_text), ZATCA QR Position (header / footer), Show Bilingual toggle, Font Size (small / medium / large) |
| **Purpose** | When a new store of this business type is created, this receipt layout is copied as the store's default receipt configuration. Providers can customize it in their receipt settings. |
| **Note** | Only one receipt template per business type (not a list — a single config form) |
| **Access** | `content.manage` |

### 4.11 Industry Workflow Configuration (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Form Sections** | |
| **Active Modules** | Multi-select of available industry modules for this business type (e.g. Restaurant: table_management, kds, course_management, split_bill, tab_management, modifiers; Pharmacy: prescription_mode, drug_scheduling, fefo_tracking, insurance_claims, batch_tracking) |
| **Default Settings (JSONB)** | Key-value configuration per active module (e.g. `{"table_management": {"default_floor_count": 1, "max_tables": 20}, "kds": {"ticket_timeout_minutes": 15}}`) |
| **Required Product Fields** | Multi-select of extra product fields providers must fill (e.g. Pharmacy: `drug_schedule`, `expiry_date`, `batch_number`; Jewelry: `weight_grams`, `karat`, `making_charge`) |
| **Purpose** | Maps business types to their industry-specific POS modules. When a store selects this business type, the POS activates the listed modules with the default settings. New stores need only minimal configuration to be fully operational. |
| **Access** | `content.manage` |

### 4.12 Default Promotion Templates (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Table Columns** | Name (EN), Name (AR), Promotion Type (%, fixed, BOGO, happy_hour), Is Active (always created as inactive) |
| **Row Actions** | Edit, Delete |
| **Create Form** | Name (EN), Name (AR), Description, Promotion Type select, Discount Value, Applies To (all_products / specific_category placeholder), Time Constraints (for happy hour: start/end time, days), Minimum Order (SAR) |
| **Purpose** | Sample promotions seeded into new stores as **inactive** examples. Providers can activate, modify, or delete them. Helps new store owners understand promotion capabilities. |
| **Access** | `content.manage` |

### 4.13 Default Commission Rule Templates (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Table Columns** | Name, Type (percentage / fixed_per_transaction / tiered), Value, Applies To |
| **Row Actions** | Edit, Delete |
| **Create Form** | Name (EN), Name (AR), Commission Type select (percentage / fixed_per_transaction / tiered), Value (% or SAR), Applies To select (all_sales / specific_category_placeholder / specific_product_placeholder), Tier Thresholds (for tiered: JSONB array of `{min_sales, max_sales, rate}`) |
| **Purpose** | Sample commission structures seeded into new stores. For example, a Salon business type might seed a 10% per-service commission, while Retail seeds a 2% on total sales commission. Created as **inactive** templates. |
| **Access** | `content.manage` |

### 4.14 Default Loyalty Program Config (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Form Fields** | Program Type select (points / stamps / cashback / none), Points Earning Rate (points per SAR — e.g. 1 point per 1 SAR), Points Redemption Value (SAR per point — e.g. 0.01 SAR per point), Minimum Redemption Points (e.g. 100), Stamps Card Size (for stamps type — e.g. 10 stamps for 1 free), Cashback Percentage (for cashback type), Points Expiry Days (0 = never), Enable Tiers toggle, Tier Definitions (JSONB — `[{"name":"Silver","name_ar":"فضي","min_points":0,"multiplier":1.0},{"name":"Gold","name_ar":"ذهبي","min_points":500,"multiplier":1.5},{"name":"Platinum","name_ar":"بلاتيني","min_points":2000,"multiplier":2.0}]`) |
| **Note** | Only one loyalty config per business type (single form, not a list). Created as **inactive** — providers activate when ready. |
| **Purpose** | When a new store is created, this loyalty program config is seeded as a ready-to-activate template. For example, a Café business type might seed a stamps-based program (10 stamps = 1 free), while a Supermarket seeds a points-based program (1 point per SAR). |
| **Access** | `content.manage` |

### 4.15 Default Customer Group Templates (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Table Columns** | Group Name (EN), Group Name (AR), Discount Percentage, Is Default Group |
| **Row Actions** | Edit, Delete |
| **Create Form** | Group Name (EN), Group Name (AR), Description, Discount Percentage (default pricing discount — e.g. 10% for Wholesale), Credit Limit (SAR — 0 for no credit), Payment Terms Days (0 = immediate, 30 = net-30), Is Default Group toggle (the group assigned to walk-in customers) |
| **Purpose** | When a new store is created, these customer groups are seeded into the store's `customer_groups` table. For example, a Wholesale business type might seed "Retail" (0% discount) and "Wholesale" (15% discount, net-30 terms), while a Restaurant seeds "Walk-in" (default) and "VIP" (5% discount). |
| **Access** | `content.manage` |

### 4.16 Default Return Policy (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Form Fields** | Return Window Days (e.g. 14 days — 0 = no returns allowed), Refund Methods (multi-select: original_payment / store_credit / cash / exchange_only), Require Receipt toggle, Restocking Fee Percentage (e.g. 15% — 0 = no fee), Void Grace Period Minutes (time after sale within which the transaction can be fully voided without going through the return process — e.g. 5 minutes), Require Manager Approval for Returns toggle, Max Return Value Without Approval (SAR — above this a manager override is needed), Return Reason Required toggle, Partial Return Allowed toggle |
| **Note** | Only one return policy per business type (single form). |
| **Purpose** | When a new store is created, this return/refund policy is seeded as the store's default. For example, Electronics: 14-day return window, 15% restocking fee, receipt required; Food/Bakery: no returns; Fashion: 30-day exchange-only. Providers can modify after setup. |
| **Access** | `content.manage` |

### 4.17 Default Waste Reason Categories (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Table Columns** | Reason Code, Name (EN), Name (AR), Category (spoilage / damage / theft / sampling / operational), Requires Approval, Sort Order |
| **Row Actions** | Edit, Delete |
| **Create Form** | Reason Code (slug — e.g. `expired`, `damaged`, `stolen`, `staff_meal`, `spillage`), Name (EN), Name (AR), Category select (spoilage / damage / theft / sampling / operational), Description, Requires Approval toggle (if true, waste entry needs manager sign-off), Affects Cost Reporting toggle (whether this waste type is included in cost of goods reporting), Sort Order |
| **Purpose** | When a new store is created, these waste reason codes are seeded into the store's inventory waste tracking. For example, a Restaurant seeds: expired, spoiled, staff_meal, plate_waste, spillage; a Supermarket seeds: expired, damaged, stolen, sampling, write_off; a Pharmacy seeds: expired, recalled, damaged, returned_to_supplier. Helps providers categorize inventory loss from day one. |
| **Access** | `content.manage` |

### 4.18 Default Appointment Booking Config (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page (shown only for service-oriented business types) |
| **Form Fields** | Default Slot Duration Minutes select (15 / 30 / 45 / 60 / 90 / 120), Min Advance Booking Hours (e.g. 2 — customer must book at least 2 hours ahead), Max Advance Booking Days (e.g. 30 — customer can book up to 30 days ahead), Cancellation Window Hours (e.g. 24 — free cancellation up to 24 hours before; after that a fee applies), Cancellation Fee Type select (none / fixed / percentage), Cancellation Fee Value, Allow Walk-ins toggle (accept non-booked customers), Overbooking Buffer Percentage (e.g. 10% — allow slight overbooking), Require Deposit toggle, Deposit Percentage (if deposit required) |
| **Service Category Templates** | Inline table: Category Name (EN), Category Name (AR), Default Duration Minutes, Default Price (SAR), Sort Order — e.g. for a Salon: "Haircut / قص شعر / 30 min / 50 SAR", "Coloring / صبغ / 90 min / 200 SAR" |
| **Note** | Only one appointment config per business type. Only relevant for business types with appointment_booking in their industry workflow modules. |
| **Purpose** | When a store enables appointment booking (via subscription toggle), this config seeds the initial booking settings and service categories. For example, a Salon seeds 30-min default slots with haircut/coloring/styling categories; a Clinic seeds 15-min consult slots. |
| **Access** | `content.manage` |

### 4.19 Default Gift Registry Types (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page (shown only for retail-oriented business types) |
| **Table Columns** | Registry Type Name (EN), Name (AR), Expiry Days, Is Active |
| **Row Actions** | Edit, Delete |
| **Create Form** | Registry Type Name (EN), Registry Type Name (AR), Description, Icon/Emoji, Default Expiry Days (e.g. 90 days for wedding, 30 days for birthday), Allow Public Sharing toggle, Allow Partial Fulfilment toggle, Require Minimum Items toggle, Minimum Items Count, Sort Order |
| **Purpose** | When a store enables gift registries, these registry type templates are seeded. For example, a Department Store seeds: Wedding (90 days, public sharing), Baby Shower (60 days), Birthday (30 days), Housewarming (45 days). Providers can add custom types. |
| **Access** | `content.manage` |

### 4.20 Default Loyalty Gamification Templates (Relation Manager within Business Type)
| Field | Detail |
|---|---|
| **Location** | Tab within Business Type edit page |
| **Form Sections** | |
| **Badge Definitions** | Inline table: Badge Name (EN), Badge Name (AR), Icon/Image URL, Trigger Type select (purchase_count / spend_total / streak_days / category_explorer / referral_count), Trigger Threshold (e.g. 10 purchases), Points Reward, Description (EN/AR) — e.g. "Coffee Connoisseur / 10 coffee purchases / 50 bonus points" |
| **Challenge Templates** | Inline table: Challenge Name (EN), Challenge Name (AR), Type select (buy_x_get_y / spend_target / visit_streak / category_spend / referral), Target Value, Reward Type select (points / discount_percentage / free_item / badge), Reward Value, Duration Days, Is Recurring toggle — e.g. "Weekend Warrior / Visit 4 weekends in a row / 200 bonus points / 28 days" |
| **Milestone Configs** | Inline table: Milestone Name (EN/AR), Type select (total_spend / total_visits / membership_days), Threshold Value, Reward Type, Reward Value — e.g. "First 1000 SAR spent / Silver status + 500 points" |
| **Note** | All gamification templates are seeded as **inactive**. Extends the base loyalty config (4.14) with engagement mechanics. Only relevant when `loyalty_gamification` feature toggle is enabled for the store's plan. |
| **Purpose** | Seeds gamification building blocks (badges, challenges, milestones) so providers don't start from scratch. For example, a Café gets "Coffee Connoisseur" badge + "Weekly Latte Challenge"; a Gym gets "Consistency Champion" badge + "30-Day Streak Challenge". |
| **Access** | `content.manage` |

### 4.21 Onboarding Steps List
| Field | Detail |
|---|---|
| **Route** | `/admin/content/onboarding-steps` |
| **Filament Resource** | `OnboardingStepResource` |
| **Table Columns** | Step Number, Title, Title (AR), Is Required badge, Sort Order |
| **Row Actions** | Edit, Reorder |
| **Header Actions** | Create Step |
| **Access** | `content.manage` |

### 4.22 Create / Edit Onboarding Step
| Field | Detail |
|---|---|
| **Route** | `/admin/content/onboarding-steps/create` or `/{id}/edit` |
| **Form Fields** | Step Number (int, unique), Title (EN), Title (AR), Description (EN, rich text), Description (AR, rich text), Is Required toggle, Sort Order |
| **Access** | `content.manage` |

### 4.23 Help Articles List
| Field | Detail |
|---|---|
| **Route** | `/admin/content/help-articles` |
| **Filament Resource** | `HelpArticleResource` (shared with / alias of `KnowledgeBaseArticleResource` in Support Ticket System) |
| **Table Columns** | Title, Category, Delivery Platform (if tagged), Is Published badge, Sort Order, Updated At |
| **Filters** | Category, Is Published, Delivery Platform |
| **Search** | By title |
| **Row Actions** | Edit, Preview, Unpublish |
| **Header Actions** | Create Article |
| **Access** | `content.manage` |

### 4.24 Create / Edit Help Article
| Field | Detail |
|---|---|
| **Route** | `/admin/content/help-articles/create` or `/{id}/edit` |
| **Form Fields** | Title (EN), Title (AR), Slug (auto-generated), Category (select: getting_started / pos_usage / inventory / delivery / billing / troubleshooting), Body (EN, rich text with image upload), Body (AR, rich text), Delivery Platform (optional FK → delivery_platforms), Is Published toggle, Sort Order |
| **Access** | `content.manage` |

### 4.25 Pricing Page Content
| Field | Detail |
|---|---|
| **Route** | `/admin/content/pricing` |
| **Filament Resource** | `PricingPageContentResource` |
| **Table Columns** | Plan Name (from subscription_plans join), Feature Bullets Count, FAQ Count, Updated At |
| **Row Actions** | Edit |
| **Access** | `content.pricing` |

### 4.26 Edit Pricing Page Content
| Field | Detail |
|---|---|
| **Route** | `/admin/content/pricing/{id}/edit` |
| **Form Fields** | Subscription Plan (read-only), Feature Bullet List (JSONB editor — array of EN/AR string pairs), FAQ (JSONB editor — array of question/answer pairs in EN/AR) |
| **Preview** | Live preview panel showing how the pricing card will render |
| **Access** | `content.pricing` |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
CRUD for all Resources.

### 5.2 Provider-Facing APIs
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/onboarding/business-types` | GET | List active business types (for registration wizard) | Public or Store API token |
| `GET /api/v1/onboarding/steps` | GET | List onboarding steps (ordered) | Store API token |
| `GET /api/v1/help/articles` | GET | List published help articles; optional filter by category or delivery_platform_id | Store API token |
| `GET /api/v1/help/articles/{slug}` | GET | Single article detail | Store API token |
| `GET /api/v1/pricing` | GET | Public pricing page data: plans with feature bullets + FAQs | Public (no auth) |
| `GET /api/v1/onboarding/business-types/{slug}/defaults` | GET | All default templates for a business type: categories, shifts, receipt layout, industry config, sample promotions, sample commissions, loyalty config, customer groups, return policy, waste reasons | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/shift-templates` | GET | Default shift templates for a business type | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/receipt-template` | GET | Default receipt template config for a business type | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/industry-config` | GET | Industry workflow config (active modules, default settings, required fields) | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/loyalty-config` | GET | Default loyalty program config (earning rates, tiers, program type) | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/customer-groups` | GET | Default customer group templates with discount/credit settings | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/return-policy` | GET | Default return/refund policy (return window, void grace period, refund methods) | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/waste-reasons` | GET | Default waste reason categories for inventory waste tracking | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/appointment-config` | GET | Default appointment booking config with service categories | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/gift-registry-types` | GET | Default gift registry type templates | Store API token |
| `GET /api/v1/onboarding/business-types/{slug}/gamification-templates` | GET | Default gamification badges, challenges, and milestones | Store API token |

### 5.3 Public Website Integration
The pricing page content API (`GET /api/v1/pricing`) is consumed by the Thawani public marketing website to render the pricing comparison table. It returns:
```json
[
  {
    "plan_name": "Starter",
    "plan_name_ar": "المبتدئ",
    "monthly_price": 99,
    "annual_price": 990,
    "is_highlighted": false,
    "feature_bullets": [
      { "en": "Up to 5 cashiers", "ar": "حتى 5 كاشيرات" },
      ...
    ],
    "faq": [
      { "question_en": "Can I upgrade later?", "question_ar": "…", "answer_en": "Yes, …", "answer_ar": "…" }
    ]
  }
]
```

---

## 6. Full Database Schema

### 6.1 Tables

#### `business_types` (shared with POS Layout Mgmt)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(50) | NOT NULL | EN |
| name_ar | VARCHAR(50) | NOT NULL | AR |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | e.g. `restaurant`, `supermarket`, `pharmacy` |
| icon | VARCHAR(255) | NULLABLE | Spaces URL or icon class name |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE business_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL,
    name_ar VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `business_type_category_templates` (seeding data for new stores)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE | |
| category_name | VARCHAR(100) | NOT NULL | EN |
| category_name_ar | VARCHAR(100) | NOT NULL | AR |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_category_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    category_name VARCHAR(100) NOT NULL,
    category_name_ar VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_category_templates_type ON business_type_category_templates (business_type_id, sort_order);
```

#### `business_type_shift_templates` (default shifts seeded to new stores)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE | |
| name | VARCHAR(100) | NOT NULL | EN — e.g. "Morning Shift" |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| start_time | TIME | NOT NULL | e.g. 06:00 |
| end_time | TIME | NOT NULL | e.g. 14:00 |
| days_of_week | JSONB | DEFAULT '[]' | e.g. ["sun","mon","tue","wed","thu"] |
| break_duration_minutes | INT | DEFAULT 30 | |
| is_default | BOOLEAN | DEFAULT FALSE | If true, auto-assigned to new staff |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_shift_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    days_of_week JSONB DEFAULT '[]',
    break_duration_minutes INT DEFAULT 30,
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_shift_templates_type ON business_type_shift_templates (business_type_id, sort_order);
```

#### `business_type_receipt_templates` (default receipt layout per business type)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE, UNIQUE | One per business type |
| paper_width | INT | DEFAULT 80 | 58 or 80 (mm) |
| header_sections | JSONB | NOT NULL | Ordered list: `["store_logo","store_name","store_address","store_phone","store_vat_number"]` |
| body_sections | JSONB | NOT NULL | Ordered list: `["items_table","subtotal","discount","vat","total","payment_method"]` |
| footer_sections | JSONB | NOT NULL | Ordered list: `["zatca_qr","receipt_number","cashier_name","thank_you_message"]` |
| zatca_qr_position | VARCHAR(10) | DEFAULT 'footer' | header / footer |
| show_bilingual | BOOLEAN | DEFAULT TRUE | Show Arabic + English on receipt |
| font_size | VARCHAR(10) | DEFAULT 'medium' | small / medium / large |
| custom_footer_text | VARCHAR(200) | NULLABLE | e.g. "Thank you / شكراً لك" |
| custom_footer_text_ar | VARCHAR(200) | NULLABLE | |

```sql
CREATE TABLE business_type_receipt_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    paper_width INT DEFAULT 80,
    header_sections JSONB NOT NULL DEFAULT '["store_logo","store_name","store_address","store_phone","store_vat_number"]',
    body_sections JSONB NOT NULL DEFAULT '["items_table","subtotal","discount","vat","total","payment_method"]',
    footer_sections JSONB NOT NULL DEFAULT '["zatca_qr","receipt_number","cashier_name","thank_you_message"]',
    zatca_qr_position VARCHAR(10) DEFAULT 'footer',
    show_bilingual BOOLEAN DEFAULT TRUE,
    font_size VARCHAR(10) DEFAULT 'medium',
    custom_footer_text VARCHAR(200),
    custom_footer_text_ar VARCHAR(200)
);
```

#### `business_type_industry_configs` (industry workflow defaults per business type)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE, UNIQUE | One per business type |
| active_modules | JSONB | NOT NULL DEFAULT '[]' | e.g. `["table_management","kds","course_management","split_bill","modifiers"]` for Restaurant |
| default_settings | JSONB | DEFAULT '{}' | Per-module default config: `{"table_management":{"default_floor_count":1},"kds":{"ticket_timeout_minutes":15}}` |
| required_product_fields | JSONB | DEFAULT '[]' | Extra fields providers must fill: `["drug_schedule","expiry_date","batch_number"]` for Pharmacy |

```sql
CREATE TABLE business_type_industry_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    active_modules JSONB NOT NULL DEFAULT '[]',
    default_settings JSONB DEFAULT '{}',
    required_product_fields JSONB DEFAULT '[]'
);
```

#### `business_type_promotion_templates` (sample promotions seeded to new stores)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| description | TEXT | NULLABLE | |
| promotion_type | VARCHAR(20) | NOT NULL | percentage / fixed / bogo / happy_hour |
| discount_value | DECIMAL(10,2) | NULLABLE | % or SAR amount |
| applies_to | VARCHAR(30) | DEFAULT 'all_products' | all_products / specific_category |
| time_start | TIME | NULLABLE | For happy hour |
| time_end | TIME | NULLABLE | |
| active_days | JSONB | NULLABLE | ["sun","mon",...] |
| minimum_order | DECIMAL(10,2) | DEFAULT 0 | |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_promotion_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    promotion_type VARCHAR(20) NOT NULL,
    discount_value DECIMAL(10,2),
    applies_to VARCHAR(30) DEFAULT 'all_products',
    time_start TIME,
    time_end TIME,
    active_days JSONB,
    minimum_order DECIMAL(10,2) DEFAULT 0,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_promotion_templates_type ON business_type_promotion_templates (business_type_id);
```

#### `business_type_commission_templates` (sample commission rules seeded to new stores)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE | |
| name | VARCHAR(100) | NOT NULL | EN — e.g. "Standard Sales Commission" |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| commission_type | VARCHAR(30) | NOT NULL | percentage / fixed_per_transaction / tiered |
| value | DECIMAL(10,2) | NULLABLE | % or SAR (used for percentage and fixed types) |
| applies_to | VARCHAR(30) | DEFAULT 'all_sales' | all_sales / specific_category / specific_product |
| tier_thresholds | JSONB | NULLABLE | For tiered: `[{"min_sales":0,"max_sales":5000,"rate":1.5},{"min_sales":5001,"max_sales":null,"rate":3.0}]` |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_commission_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    commission_type VARCHAR(30) NOT NULL,
    value DECIMAL(10,2),
    applies_to VARCHAR(30) DEFAULT 'all_sales',
    tier_thresholds JSONB,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_commission_templates_type ON business_type_commission_templates (business_type_id);
```

#### `business_type_loyalty_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id), UNIQUE | One loyalty config per business type |
| program_type | VARCHAR(20) | NOT NULL | points / stamps / cashback / none |
| earning_rate | DECIMAL(8,4) | DEFAULT 1.0 | Points earned per SAR spent (for points type) |
| redemption_value | DECIMAL(8,4) | DEFAULT 0.01 | SAR value per point redeemed |
| min_redemption_points | INT | DEFAULT 100 | Minimum points before redemption is allowed |
| stamps_card_size | INT | NULLABLE | Number of stamps for 1 reward (stamps type only) |
| cashback_percentage | DECIMAL(5,2) | NULLABLE | Cashback % on purchases (cashback type only) |
| points_expiry_days | INT | DEFAULT 0 | 0 = never expire |
| enable_tiers | BOOLEAN | DEFAULT FALSE | |
| tier_definitions | JSONB | DEFAULT '[]' | `[{"name":"Silver","name_ar":"فضي","min_points":0,"multiplier":1.0}, …]` |
| is_active | BOOLEAN | DEFAULT FALSE | Seeded as inactive — provider activates when ready |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE business_type_loyalty_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    program_type VARCHAR(20) NOT NULL DEFAULT 'points',
    earning_rate DECIMAL(8,4) DEFAULT 1.0,
    redemption_value DECIMAL(8,4) DEFAULT 0.01,
    min_redemption_points INT DEFAULT 100,
    stamps_card_size INT,
    cashback_percentage DECIMAL(5,2),
    points_expiry_days INT DEFAULT 0,
    enable_tiers BOOLEAN DEFAULT FALSE,
    tier_definitions JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `business_type_customer_group_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| description | TEXT | NULLABLE | |
| discount_percentage | DECIMAL(5,2) | DEFAULT 0 | Default pricing discount for this group |
| credit_limit | DECIMAL(12,2) | DEFAULT 0 | SAR — 0 means no credit |
| payment_terms_days | INT | DEFAULT 0 | 0 = immediate, 30 = net-30, etc. |
| is_default_group | BOOLEAN | DEFAULT FALSE | The group auto-assigned to walk-in customers |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_customer_group_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    credit_limit DECIMAL(12,2) DEFAULT 0,
    payment_terms_days INT DEFAULT 0,
    is_default_group BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_customer_group_templates_type ON business_type_customer_group_templates (business_type_id);
```

#### `business_type_return_policies`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id), UNIQUE | One return policy per business type |
| return_window_days | INT | NOT NULL DEFAULT 14 | 0 = no returns accepted |
| refund_methods | JSONB | DEFAULT '["original_payment"]' | Array: original_payment / store_credit / cash / exchange_only |
| require_receipt | BOOLEAN | DEFAULT TRUE | |
| restocking_fee_percentage | DECIMAL(5,2) | DEFAULT 0 | 0 = no fee |
| void_grace_period_minutes | INT | DEFAULT 5 | Time after sale within which cashier can void without return flow |
| require_manager_approval | BOOLEAN | DEFAULT FALSE | |
| max_return_without_approval | DECIMAL(12,2) | DEFAULT 0 | SAR — above this, manager override needed; 0 = always needs approval if toggle is on |
| return_reason_required | BOOLEAN | DEFAULT TRUE | |
| partial_return_allowed | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE business_type_return_policies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    return_window_days INT NOT NULL DEFAULT 14,
    refund_methods JSONB DEFAULT '["original_payment"]',
    require_receipt BOOLEAN DEFAULT TRUE,
    restocking_fee_percentage DECIMAL(5,2) DEFAULT 0,
    void_grace_period_minutes INT DEFAULT 5,
    require_manager_approval BOOLEAN DEFAULT FALSE,
    max_return_without_approval DECIMAL(12,2) DEFAULT 0,
    return_reason_required BOOLEAN DEFAULT TRUE,
    partial_return_allowed BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `business_type_waste_reason_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| reason_code | VARCHAR(30) | NOT NULL | Slug: expired, damaged, stolen, staff_meal, spillage, recalled, write_off |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| category | VARCHAR(20) | NOT NULL | spoilage / damage / theft / sampling / operational |
| description | TEXT | NULLABLE | |
| requires_approval | BOOLEAN | DEFAULT FALSE | If true, waste entry needs manager sign-off |
| affects_cost_reporting | BOOLEAN | DEFAULT TRUE | Include in COGS reporting |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_waste_reason_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    reason_code VARCHAR(30) NOT NULL,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    category VARCHAR(20) NOT NULL,
    description TEXT,
    requires_approval BOOLEAN DEFAULT FALSE,
    affects_cost_reporting BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    UNIQUE (business_type_id, reason_code)
);

CREATE INDEX idx_bt_waste_reason_templates_type ON business_type_waste_reason_templates (business_type_id);
```

#### `business_type_appointment_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id), UNIQUE | One appointment config per business type |
| default_slot_duration_minutes | INT | DEFAULT 30 | 15 / 30 / 45 / 60 / 90 / 120 |
| min_advance_booking_hours | INT | DEFAULT 2 | Must book at least N hours ahead |
| max_advance_booking_days | INT | DEFAULT 30 | Can book up to N days ahead |
| cancellation_window_hours | INT | DEFAULT 24 | Free cancellation up to N hours before |
| cancellation_fee_type | VARCHAR(15) | DEFAULT 'none' | none / fixed / percentage |
| cancellation_fee_value | DECIMAL(10,2) | DEFAULT 0 | |
| allow_walkins | BOOLEAN | DEFAULT TRUE | |
| overbooking_buffer_percentage | DECIMAL(5,2) | DEFAULT 0 | |
| require_deposit | BOOLEAN | DEFAULT FALSE | |
| deposit_percentage | DECIMAL(5,2) | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE business_type_appointment_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL UNIQUE REFERENCES business_types(id) ON DELETE CASCADE,
    default_slot_duration_minutes INT DEFAULT 30,
    min_advance_booking_hours INT DEFAULT 2,
    max_advance_booking_days INT DEFAULT 30,
    cancellation_window_hours INT DEFAULT 24,
    cancellation_fee_type VARCHAR(15) DEFAULT 'none',
    cancellation_fee_value DECIMAL(10,2) DEFAULT 0,
    allow_walkins BOOLEAN DEFAULT TRUE,
    overbooking_buffer_percentage DECIMAL(5,2) DEFAULT 0,
    require_deposit BOOLEAN DEFAULT FALSE,
    deposit_percentage DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `business_type_service_category_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| default_duration_minutes | INT | NOT NULL | |
| default_price | DECIMAL(10,2) | NULLABLE | SAR |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_service_category_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    default_duration_minutes INT NOT NULL DEFAULT 30,
    default_price DECIMAL(10,2),
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_service_category_templates_type ON business_type_service_category_templates (business_type_id);
```

#### `business_type_gift_registry_types`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| name | VARCHAR(100) | NOT NULL | EN — e.g. Wedding, Baby Shower, Birthday |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| description | TEXT | NULLABLE | |
| icon | VARCHAR(10) | NULLABLE | Emoji or icon code |
| default_expiry_days | INT | DEFAULT 90 | |
| allow_public_sharing | BOOLEAN | DEFAULT TRUE | |
| allow_partial_fulfilment | BOOLEAN | DEFAULT TRUE | |
| require_minimum_items | BOOLEAN | DEFAULT FALSE | |
| minimum_items_count | INT | DEFAULT 0 | |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_gift_registry_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10),
    default_expiry_days INT DEFAULT 90,
    allow_public_sharing BOOLEAN DEFAULT TRUE,
    allow_partial_fulfilment BOOLEAN DEFAULT TRUE,
    require_minimum_items BOOLEAN DEFAULT FALSE,
    minimum_items_count INT DEFAULT 0,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gift_registry_types_type ON business_type_gift_registry_types (business_type_id);
```

#### `business_type_gamification_badges`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| icon_url | TEXT | NULLABLE | Badge icon/image |
| trigger_type | VARCHAR(30) | NOT NULL | purchase_count / spend_total / streak_days / category_explorer / referral_count |
| trigger_threshold | INT | NOT NULL | Number of actions to earn badge |
| points_reward | INT | DEFAULT 0 | Bonus points awarded with badge |
| description | TEXT | NULLABLE | EN |
| description_ar | TEXT | NULLABLE | AR |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_gamification_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    icon_url TEXT,
    trigger_type VARCHAR(30) NOT NULL,
    trigger_threshold INT NOT NULL DEFAULT 1,
    points_reward INT DEFAULT 0,
    description TEXT,
    description_ar TEXT,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gamification_badges_type ON business_type_gamification_badges (business_type_id);
```

#### `business_type_gamification_challenges`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| challenge_type | VARCHAR(20) | NOT NULL | buy_x_get_y / spend_target / visit_streak / category_spend / referral |
| target_value | INT | NOT NULL | e.g. 5 purchases, 500 SAR |
| reward_type | VARCHAR(20) | NOT NULL | points / discount_percentage / free_item / badge |
| reward_value | VARCHAR(50) | NOT NULL | e.g. "200" (points), "10" (%), item SKU |
| duration_days | INT | DEFAULT 30 | |
| is_recurring | BOOLEAN | DEFAULT FALSE | Resets after completion |
| description | TEXT | NULLABLE | EN |
| description_ar | TEXT | NULLABLE | AR |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_gamification_challenges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    challenge_type VARCHAR(20) NOT NULL,
    target_value INT NOT NULL DEFAULT 1,
    reward_type VARCHAR(20) NOT NULL DEFAULT 'points',
    reward_value VARCHAR(50) NOT NULL DEFAULT '0',
    duration_days INT DEFAULT 30,
    is_recurring BOOLEAN DEFAULT FALSE,
    description TEXT,
    description_ar TEXT,
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gamification_challenges_type ON business_type_gamification_challenges (business_type_id);
```

#### `business_type_gamification_milestones`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| milestone_type | VARCHAR(20) | NOT NULL | total_spend / total_visits / membership_days |
| threshold_value | DECIMAL(12,2) | NOT NULL | e.g. 1000 SAR, 50 visits |
| reward_type | VARCHAR(20) | NOT NULL | points / discount_percentage / tier_upgrade / badge |
| reward_value | VARCHAR(50) | NOT NULL | |
| sort_order | INT | DEFAULT 0 | |

```sql
CREATE TABLE business_type_gamification_milestones (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    milestone_type VARCHAR(20) NOT NULL,
    threshold_value DECIMAL(12,2) NOT NULL,
    reward_type VARCHAR(20) NOT NULL DEFAULT 'points',
    reward_value VARCHAR(50) NOT NULL DEFAULT '0',
    sort_order INT DEFAULT 0
);

CREATE INDEX idx_bt_gamification_milestones_type ON business_type_gamification_milestones (business_type_id);
```

#### `onboarding_steps`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| step_number | INT | NOT NULL, UNIQUE | 1, 2, 3, … |
| title | VARCHAR(100) | NOT NULL | EN |
| title_ar | VARCHAR(100) | NOT NULL | AR |
| description | TEXT | NULLABLE | EN, supports Markdown/HTML |
| description_ar | TEXT | NULLABLE | AR |
| is_required | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE onboarding_steps (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    step_number INT NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    title_ar VARCHAR(100) NOT NULL,
    description TEXT,
    description_ar TEXT,
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `knowledge_base_articles` (shared with Support Ticket System)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| title | VARCHAR(200) | NOT NULL | EN |
| title_ar | VARCHAR(200) | NOT NULL | AR |
| slug | VARCHAR(200) | NOT NULL, UNIQUE | URL-friendly |
| body | TEXT | NOT NULL | EN, rich text |
| body_ar | TEXT | NOT NULL | AR |
| category | VARCHAR(50) | NOT NULL | getting_started / pos_usage / inventory / delivery / billing / troubleshooting |
| delivery_platform_id | UUID | FK → delivery_platforms(id), NULLABLE | Links article to a specific delivery platform |
| is_published | BOOLEAN | DEFAULT FALSE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE knowledge_base_articles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(200) NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    delivery_platform_id UUID REFERENCES delivery_platforms(id),
    is_published BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_kb_articles_published_category ON knowledge_base_articles (is_published, category);
```

#### `pricing_page_content`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| subscription_plan_id | UUID | FK → subscription_plans(id), UNIQUE | One content row per plan |
| feature_bullet_list | JSONB | NOT NULL DEFAULT '[]' | `[{"en":"Up to 5 cashiers","ar":"حتى 5 كاشيرات"}, …]` |
| faq | JSONB | NOT NULL DEFAULT '[]' | `[{"question_en":"…","question_ar":"…","answer_en":"…","answer_ar":"…"}, …]` |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE pricing_page_content (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL UNIQUE REFERENCES subscription_plans(id),
    feature_bullet_list JSONB NOT NULL DEFAULT '[]',
    faq JSONB NOT NULL DEFAULT '[]',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes Summary

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `business_types_slug` | slug | UNIQUE | Lookup by slug |
| `bt_category_templates_type_sort` | (business_type_id, sort_order) | B-TREE | Ordered category list per type |
| `bt_shift_templates_type_sort` | (business_type_id, sort_order) | B-TREE | Ordered shift list per type |
| `bt_receipt_templates_type` | business_type_id | UNIQUE | One receipt template per type |
| `bt_industry_configs_type` | business_type_id | UNIQUE | One industry config per type |
| `bt_promotion_templates_type` | business_type_id | B-TREE | Promotion templates per type |
| `bt_commission_templates_type` | business_type_id | B-TREE | Commission templates per type |
| `bt_loyalty_configs_type` | business_type_id | UNIQUE | One loyalty config per type |
| `bt_customer_group_templates_type` | business_type_id | B-TREE | Customer group templates per type |
| `bt_return_policies_type` | business_type_id | UNIQUE | One return policy per type |
| `bt_waste_reason_templates_type_code` | (business_type_id, reason_code) | UNIQUE | Unique reason code per type |
| `bt_appointment_configs_type` | business_type_id | UNIQUE | One appointment config per type |
| `bt_service_category_templates_type` | business_type_id | B-TREE | Service categories per type |
| `bt_gift_registry_types_type` | business_type_id | B-TREE | Gift registry types per type |
| `bt_gamification_badges_type` | business_type_id | B-TREE | Badges per type |
| `bt_gamification_challenges_type` | business_type_id | B-TREE | Challenges per type |
| `bt_gamification_milestones_type` | business_type_id | B-TREE | Milestones per type |
| `onboarding_steps_number` | step_number | UNIQUE | Ordered step retrieval |
| `kb_articles_slug` | slug | UNIQUE | Article URL routing |
| `kb_articles_published_category` | (is_published, category) | B-TREE | Published article listing |
| `pricing_page_content_plan` | subscription_plan_id | UNIQUE | One content row per plan |

### 6.3 Cross-Referenced Tables (defined in other features)
- `delivery_platforms` — help articles can be tagged to a platform for integration guides
- `subscription_plans` — pricing page content FK
- `pos_layout_templates` — linked to business types (defined in POS Layout Mgmt)

### 6.4 Relationships Diagram
```
business_types ──1:N──▶ business_type_category_templates
business_types ──1:N──▶ business_type_shift_templates
business_types ──1:1──▶ business_type_receipt_templates
business_types ──1:1──▶ business_type_industry_configs
business_types ──1:N──▶ business_type_promotion_templates
business_types ──1:N──▶ business_type_commission_templates
business_types ──1:1──▶ business_type_loyalty_configs
business_types ──1:N──▶ business_type_customer_group_templates
business_types ──1:1──▶ business_type_return_policies
business_types ──1:N──▶ business_type_waste_reason_templates
business_types ──1:1──▶ business_type_appointment_configs
business_types ──1:N──▶ business_type_service_category_templates
business_types ──1:N──▶ business_type_gift_registry_types
business_types ──1:N──▶ business_type_gamification_badges
business_types ──1:N──▶ business_type_gamification_challenges
business_types ──1:N──▶ business_type_gamification_milestones
business_types ──1:N──▶ pos_layout_templates (defined in POS Layout Mgmt)
knowledge_base_articles ──N:1──▶ delivery_platforms
pricing_page_content ──1:1──▶ subscription_plans
```

---

## 7. Business Rules

1. **Business type deactivation** — deactivating a business type hides it from the onboarding wizard but does not affect existing stores that chose it; their layout templates and categories remain intact
2. **Category template seeding** — when a new store selects a business type during onboarding, `business_type_category_templates` rows are copied into the store's categories table as starter data
3. **Shift template seeding** — when a new store is created, `business_type_shift_templates` rows are copied into the store's `shift_templates` table. Providers can freely modify, add, or delete shifts after seeding.
4. **Receipt template seeding** — the `business_type_receipt_templates` config is copied as the store's initial receipt configuration. Providers customize via their receipt settings screen.
5. **Industry workflow activation** — `business_type_industry_configs.active_modules` determines which industry-specific Flutter modules are activated in the POS app for this store. Changing the platform config does NOT affect existing stores — only newly created stores.
6. **Promotion template seeding** — promotions from `business_type_promotion_templates` are seeded as **inactive** into the new store's promotions table. This gives providers ready-to-use examples without accidentally applying discounts.
7. **Commission template seeding** — commissions from `business_type_commission_templates` are seeded as **inactive** into the new store's commission rules. Providers activate them when ready.
8. **Seeding is one-time** — all template seeding (categories, shifts, receipts, industry config, promotions, commissions, loyalty config, customer groups, return policy, waste reasons, appointment config, gift registry types, gamification templates) happens once at store creation. Subsequent changes to platform templates do NOT propagate to existing stores. A future "re-sync defaults" action could be added if needed.
9. **Loyalty config seeding** — `business_type_loyalty_configs` is copied as the store's initial loyalty program configuration, always seeded as **inactive**. Providers activate the program and customize earning rates when ready. If `program_type = none`, no loyalty program is seeded.
10. **Customer group seeding** — `business_type_customer_group_templates` rows are copied into the store's `customer_groups` table. Exactly one group should be marked `is_default_group = true` (auto-assigned to walk-in customers). Providers can modify groups, adjust discounts, and add new groups.
11. **Return policy seeding** — the `business_type_return_policies` config is copied as the store's initial return/refund policy. A `return_window_days = 0` means "no returns accepted" (appropriate for food/bakery). The void grace period is separate from the return window — it's the time immediately after a sale within which the cashier can void without going through the formal return flow.
12. **Waste reason seeding** — `business_type_waste_reason_templates` rows are copied into the store's `waste_reasons` table. These standardize inventory loss tracking across stores of the same type. Providers can add custom reasons but cannot delete platform-seeded ones (they can deactivate them).
13. **Default customer group constraint** — exactly one `business_type_customer_group_templates` row per business type must have `is_default_group = true`. Form validation enforces this. The default group is assigned to walk-in / anonymous customers.
14. **Appointment config seeding** — `business_type_appointment_configs` and associated `business_type_service_category_templates` are seeded when a store enables appointment booking. If the business type has no appointment config, the feature uses system defaults (30-min slots, 24-hour cancellation window). Service categories are copied into the store's services table matching the provider's service catalog structure.
15. **Gift registry type seeding** — `business_type_gift_registry_types` are seeded when a store enables gift registries. Only business types appropriate for registries (retail, department store, specialty) should have registry types configured. Seeded as **active** since these are type definitions, not customer-facing promotions.
16. **Gamification template seeding** — badges, challenges, and milestones from `business_type_gamification_*` tables are seeded as **inactive** when a store enables loyalty gamification. Providers activate individual badges/challenges when ready. This extends the base loyalty config (rule 9) — gamification is an optional layer on top of the core loyalty program.
17. **Gamification requires loyalty** — gamification templates only seed if the business type has a loyalty config (`business_type_loyalty_configs`) with `program_type != 'none'`. Gamification cannot function without an underlying loyalty points/stamps system.
18. **Appointment config only for service types** — the Appointment Booking Config tab only appears for business types whose `business_type_industry_configs.active_modules` includes `appointment_booking`. The admin panel conditionally shows/hides this Relation Manager.
14. **Onboarding step ordering** — `step_number` must be unique and contiguous (1, 2, 3…); reordering updates all affected step numbers in a single transaction
15. **Required steps** — the provider onboarding wizard enforces completion of all steps where `is_required = true` before the store is considered fully set up
16. **Help article shared table** — `knowledge_base_articles` is the same table used by the Support Ticket System; articles managed here with category = `delivery` are the integration guides, while category = `troubleshooting` maps to support-facing articles
17. **Pricing page cache** — `GET /api/v1/pricing` response cached in Redis for 5 minutes; cache invalidated when any `pricing_page_content` row is saved
18. **Feature bullet ordering** — the JSONB array order in `feature_bullet_list` determines display order on the pricing page; admin reorders via drag-and-drop in the Filament editor
19. **FAQ limits** — maximum 10 FAQ items per plan to keep the pricing page clean; enforced by form validation
20. **Slug auto-generation** — business type and article slugs are auto-generated from the EN title using `Str::slug()`; admin can override; must be unique
21. **Bilingual requirement** — all content fields (names, titles, descriptions, bullets, FAQ) require both EN and AR values; form validation ensures both are filled

---

## Implemented — Admin Filament Panel

### OrganizationResource (`/admin/organizations`)
- Full CRUD with tabbed form (Basic Info, Contact & Location, Status)
- Auto-slug generation from name
- Legal info section (CR Number, VAT Number)
- Table with counts badge for stores, business type badge, active status
- Filters: active, business type, country, has/no stores
- "Onboard New Store" action per organization (creates store via StoreService)
- "Onboard New Organization" wizard action on list page (Org → First Store in 2 steps)
- Suspend/Activate actions (cascades to all org stores)
- Bulk suspend/activate
- View page with infolist, stores count
- StoresRelationManager for inline store management

### StoreResource (`/admin/stores`)
- Full CRUD with tabbed form (Basic Info, Contact & Location, Settings)
- BusinessType enum dropdown (replaces hardcoded options)
- Auto-slug from store name
- Inline organization creation from store form
- Table: name + arabic, org, branch code, city, business type badge, plan, subscription status, active
- Toggleable columns: main branch, phone, email, onboarding status, created_at
- Filters: active, business type (multi), organization, main branch, has/no subscription, onboarding incomplete, date range
- Actions: Suspend, Activate, Reset Onboarding (via OnboardingService)
- Bulk suspend/activate
- View page with infolist tabs: Overview, Subscription, Onboarding
- Navigation badge (active stores count)
- Global search on name, email, phone, slug, branch_code
- WorkingHoursRelationManager (day picker, open/close times, break times, toggle)
- UsersRelationManager (view only)
- RegistersRelationManager (CRUD with name, code, active toggle)

### OnboardingStepResource (`/admin/onboarding-steps`)
- Full CRUD with bilingual fields (title, title_ar, description, description_ar)
- RichEditor for descriptions with toolbar buttons
- Step number + sort order configuration
- Required toggle with helper text
- Table sorted by step_number ascending
- Drag-and-drop reorder via sort_order
- Filters: required status
- View page with infolist (content + configuration sections)
- Navigation badge (required steps count)

### BusinessTypeResource (`/admin/business-types`)
- Full CRUD with auto-slug, icon, sort order, active toggle
- Table: name + arabic, slug badge, icon, category count, shift count, active, sort
- Drag-and-drop reorder
- "Duplicate" action (copies type + category & shift templates)
- View page with infolist + template counts section
- 8 RelationManagers for template CRUD:
  - CategoryTemplatesRelationManager (name EN/AR, sort order)
  - ShiftTemplatesRelationManager (name, times, days of week, break, default flag)
  - PromotionTemplatesRelationManager (name, type, discount, applies to, days, minimum)
  - CommissionTemplatesRelationManager (name, type, value, applies to, tier thresholds)
  - CustomerGroupTemplatesRelationManager (name, discount %, credit limit, terms, default)
  - WasteReasonTemplatesRelationManager (code, name, category, approval, cost reporting)
  - ServiceCategoryTemplatesRelationManager (name, duration, default price)
  - GamificationBadgesRelationManager (name, trigger type/threshold, points reward)
