# Business Type & Onboarding — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Store Setup Wizard, Business Type Selection, Industry-Specific Configuration, Multi-Branch Setup  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11  

---

## 1. Feature Overview

Business Type & Onboarding guides new store owners through the complete setup of their POS system. The onboarding wizard adapts based on the selected business type (restaurant, retail, pharmacy, etc.) to pre-configure the POS with industry-appropriate settings, product templates, tax configurations, and workflows.

### What This Feature Does
- **Business type selection** — choose from: Retail, Restaurant/Café, Pharmacy, Grocery/Supermarket, Jewelry, Mobile Phone Shop, Flower Shop, Bakery, General Service, Custom
- **Setup wizard** — step-by-step onboarding: Business Info → Business Type → Tax Configuration → Hardware Setup → Product Import → Staff Setup → Payment Methods → Review & Launch
- **Industry pre-configuration** — selecting a business type auto-configures: default categories, tax rates, receipt templates, relevant features, quick-access layout
- **Product import** — bulk import from CSV/Excel, or start with sample catalog for the business type
- **Tax rate wizard** — configure VAT rate based on country (Oman 0%, Saudi Arabia 15%); pre-fills ZATCA settings for Saudi
- **Multi-branch setup** — during or after onboarding, add additional branches with shared or independent catalogs
- **Onboarding checklist** — persistent checklist showing setup completion percentage; items can be completed in any order
- **Demo mode** — explore the POS with sample data before committing to setup
- **Re-onboarding** — ability to re-run the wizard to change business type (resets industry-specific configurations)

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Subscription & Billing** | Plan selection during onboarding |
| **Hardware Support** | Hardware setup step in wizard |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Product Catalog** | Initial categories and sample products |
| **Industry-Specific Workflows** | Business type determines which workflows are activated |
| **POS Interface Customization** | Default quick-access layout per business type |
| **ZATCA Compliance** | Tax configuration based on country |
| **Language & Localization** | Currency and locale based on country |
| **Reports & Analytics** | Industry-specific report templates |

### Features to Review After Changing This Feature
1. **Industry-Specific Workflows** — new business types need corresponding workflow configurations
2. **POS Interface Customization** — default layouts per business type

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local store configuration storage |
| **riverpod** / **flutter_bloc** | State management for wizard flow |
| **dio** | HTTP client for onboarding API |
| **excel** / **csv** | Product import from spreadsheets |
| **stepper** / **flutter_wizard** | Multi-step wizard UI component |
| **image_picker** | Store logo upload |

### 3.2 Technologies
- **Wizard state machine** — onboarding wizard uses a state machine pattern; each step validates before advancing
- **Business type templates** — JSON configuration files per business type defining default categories, features, layouts
- **Seed data** — sample products, categories, and configurations per business type stored as JSON fixtures
- **Laravel Seeder** — server-side seeder creates store with business-type-specific defaults
- **CSV/Excel parser** — bulk product import with column mapping (auto-detects common formats)

---

## 4. Screens

### 4.1 Welcome Screen
| Field | Detail |
|---|---|
| **Route** | `/onboarding/welcome` |
| **Purpose** | First screen after installation |
| **Layout** | Welcome message, language selector (Arabic/English), "Start Setup" button, "Explore Demo" button |
| **Access** | Unauthenticated (first launch only) |

### 4.2 Business Info Step
| Field | Detail |
|---|---|
| **Route** | `/onboarding/business-info` |
| **Purpose** | Enter basic business information |
| **Fields** | Store name (AR + EN), Logo upload, Country (Oman/Saudi Arabia), City, Address, Phone, Email, Commercial Registration number, Tax Registration number (VAT) |
| **Validation** | Store name required; country required; CR number validated format |

### 4.3 Business Type Selection Step
| Field | Detail |
|---|---|
| **Route** | `/onboarding/business-type` |
| **Purpose** | Select industry/business type |
| **Layout** | Grid of illustrated cards — one per business type: Retail, Restaurant/Café, Pharmacy, Grocery, Jewelry, Mobile Phone Shop, Flower Shop, Bakery, General Service, Custom |
| **Preview** | On hover/select, shows what will be pre-configured: sample categories, enabled features, recommended hardware |

### 4.4 Tax Configuration Step
| Field | Detail |
|---|---|
| **Route** | `/onboarding/tax` |
| **Purpose** | Configure tax rates |
| **Layout** | Country auto-fills default VAT rate (Oman: 0%, Saudi: 15%); option to add custom tax rates; ZATCA settings shown for Saudi Arabia |
| **Automation** | If Saudi Arabia selected, ZATCA compliance feature is auto-enabled |

### 4.5 Hardware Setup Step
| Field | Detail |
|---|---|
| **Route** | `/onboarding/hardware` |
| **Purpose** | Detect and configure connected peripherals |
| **Layout** | Auto-detect scan; list detected devices; manual configuration option; "Skip for now" link |

### 4.6 Product Import Step
| Field | Detail |
|---|---|
| **Route** | `/onboarding/products` |
| **Purpose** | Initial product catalog setup |
| **Options** | "Import from CSV/Excel", "Use Sample Catalog for [Business Type]", "Start Empty", "Import from Thawani store" |
| **CSV Import** | File upload → column mapping UI → preview → import |

### 4.7 Staff Setup Step
| Field | Detail |
|---|---|
| **Route** | `/onboarding/staff` |
| **Purpose** | Add initial staff members |
| **Layout** | Add staff form (name, role, PIN); minimum one owner account; "Add More" button; "Skip for now" link |

### 4.8 Onboarding Checklist (Post-Wizard)
| Field | Detail |
|---|---|
| **Route** | Dashboard sidebar widget |
| **Purpose** | Track remaining setup tasks |
| **Layout** | Checklist with percentage complete: ✅ Business Info, ✅ Business Type, ☐ Add Products, ☐ Configure Printer, ☐ Add Staff, ☐ Process First Sale |
| **Persistence** | Shown until all items checked or user dismisses |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/onboarding/store` | POST | Create store with business info | Bearer token |
| `PUT /api/onboarding/business-type` | PUT | Set business type and apply template | Bearer token, Owner |
| `PUT /api/onboarding/tax-config` | PUT | Save tax configuration | Bearer token, Owner |
| `POST /api/onboarding/import-products` | POST | Bulk product import (CSV/Excel) | Bearer token, Owner |
| `GET /api/onboarding/sample-catalog/{type}` | GET | Get sample catalog for business type | Bearer token |
| `POST /api/onboarding/complete` | POST | Mark onboarding as completed | Bearer token, Owner |
| `GET /api/onboarding/checklist` | GET | Get onboarding checklist status | Bearer token |
| `PUT /api/onboarding/checklist/{item}` | PUT | Mark checklist item as completed | Bearer token |
| `GET /api/business-types` | GET | List available business types with descriptions | Bearer token |
| `GET /api/business-types/{type}/template` | GET | Get configuration template for business type | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `OnboardingWizardService` | State machine for wizard flow; step validation and navigation |
| `BusinessTypeService` | Loads and applies business type templates |
| `ProductImportService` | CSV/Excel parsing, column mapping, batch product creation |
| `TaxConfigService` | Country-based tax rate configuration |
| `OnboardingChecklistService` | Persistent checklist state; completion tracking |
| `SampleDataService` | Loads and imports sample catalog data for business type |
| `DemoModeService` | Creates sandbox environment for POS exploration |

---

## 6. Full Database Schema

### 6.1 Tables

#### `business_type_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| code | VARCHAR(50) | NOT NULL, UNIQUE | retail, restaurant, pharmacy, grocery, jewelry, mobile_shop, flower_shop, bakery, service, custom |
| name_ar | VARCHAR(100) | NOT NULL | |
| name_en | VARCHAR(100) | NOT NULL | |
| description_ar | TEXT | NULLABLE | |
| description_en | TEXT | NULLABLE | |
| icon | VARCHAR(50) | NOT NULL | Icon identifier |
| template_json | JSONB | NOT NULL | Default categories, features, settings, quick-access layout |
| sample_products_json | JSONB | NULLABLE | Sample product catalog |
| is_active | BOOLEAN | DEFAULT TRUE | |
| display_order | INTEGER | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE business_type_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(50) NOT NULL UNIQUE,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    icon VARCHAR(50) NOT NULL,
    template_json JSONB NOT NULL DEFAULT '{}',
    sample_products_json JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);
```

**`template_json` example for "restaurant":**
```json
{
  "default_categories": ["Appetizers", "Main Course", "Desserts", "Beverages", "Sides"],
  "enabled_features": ["modifiers", "kitchen_display", "table_management", "delivery_integrations"],
  "receipt_template": "restaurant_receipt",
  "quick_access_layout": "grid_6x4",
  "recommended_hardware": ["receipt_printer_80mm", "kitchen_printer", "cash_drawer"]
}
```

#### `onboarding_progress`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL, UNIQUE | |
| current_step | VARCHAR(50) | DEFAULT 'welcome' | Current wizard step |
| completed_steps | JSONB | DEFAULT '[]' | Array of completed step codes |
| checklist_items | JSONB | DEFAULT '{}' | { "business_info": true, "add_products": false, ... } |
| is_wizard_completed | BOOLEAN | DEFAULT FALSE | |
| is_checklist_dismissed | BOOLEAN | DEFAULT FALSE | |
| started_at | TIMESTAMP | DEFAULT NOW() | |
| completed_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE onboarding_progress (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    current_step VARCHAR(50) DEFAULT 'welcome',
    completed_steps JSONB DEFAULT '[]',
    checklist_items JSONB DEFAULT '{}',
    is_wizard_completed BOOLEAN DEFAULT FALSE,
    is_checklist_dismissed BOOLEAN DEFAULT FALSE,
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `business_type_code` | code | B-TREE UNIQUE | Template lookup |
| `onboarding_store` | store_id | B-TREE UNIQUE | One progress per store |

### 6.3 Relationships Diagram
```
business_type_templates (global — shared across all stores)
stores ──1:1──▶ onboarding_progress
stores.business_type ── references business_type_templates.code
```

---

## 7. Business Rules

1. **One-time wizard** — the onboarding wizard runs only once; after completion, settings are managed through regular settings screens
2. **Business type change** — changing business type after onboarding requires confirmation; it resets industry-specific configurations (categories, quick-access layout) but preserves products and transactions
3. **Mandatory fields** — store name, country, and business type are mandatory before POS can be used; other steps can be skipped
4. **Country determines defaults** — selecting Oman sets currency to OMR (3 decimals), VAT to 0%; selecting Saudi Arabia sets currency to SAR (2 decimals), VAT to 15%, enables ZATCA
5. **Sample catalog is non-destructive** — importing sample catalog adds products without removing existing ones; products are marked as "sample" and can be bulk-deleted later
6. **CSV import mapping** — the importer auto-detects columns (name, barcode, price, category, stock) by header name; unmapped columns are shown for manual mapping
7. **First sale milestone** — the onboarding checklist includes "Process First Sale"; this is automatically checked after the first completed transaction
8. **Demo mode isolation** — demo mode uses separate sample data; exiting demo mode cleanly removes all sample data without affecting the real store
9. **Multi-branch onboarding** — additional branches go through a simplified setup (name, address, hardware) — they inherit the business type and tax config from the primary branch
10. **Re-onboarding protection** — the re-onboarding option is buried in advanced settings with a clear warning about what will be reset

---

## Implemented — Admin Filament Panel

### Admin Store Management
- StoreResource: comprehensive form with tabbed layout, BusinessType enum, organization picker with inline create
- Suspend/Activate/Reset Onboarding actions per store
- View page with subscription and onboarding progress tabs
- WorkingHours, Users, Registers relation managers

### Admin Organization Onboarding
- OrganizationResource with "Onboard New Organization" wizard (2-step: Org info → First Store)
- "Onboard New Store" action per organization row
- StoreService integration for auto-creating settings + working hours on store creation
- Cascade suspend/activate across organization stores

### Admin Onboarding Steps
- OnboardingStepResource with bilingual fields, RichEditor, step ordering, drag-and-drop reorder
- View page with full infolist

### Admin Business Type Templates
- BusinessTypeResource with 8 relation managers for all template types
- Duplicate action copies business type + templates
- Category, Shift, Promotion, Commission, Customer Group, Waste Reason, Service Category, Gamification Badge templates all manageable inline
