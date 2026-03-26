# POS Interface Customization — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Theme, Layout, Handedness, Quick-Access Grid, Font Size, RTL  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · SharedPreferences  

---

## 1. Feature Overview

POS Interface Customization allows store owners and individual cashiers to tailor the POS screen layout, visual theme, and interaction preferences. This includes handedness settings (left/right-handed mode), theme selection (light/dark/custom brand colours), font size scaling, product grid vs list view, quick-access button configuration, and full RTL support for Arabic-primary users.

### What This Feature Does
- **Handedness mode** — left-handed or right-handed layout; moves the cart panel and action buttons to the preferred side
- **Theme selection** — light mode, dark mode, or custom brand colours (primary, secondary, accent)
- **Font size scaling** — small, medium, large, extra-large — adjusts all UI text proportionally
- **Product grid layout** — configure columns (2/3/4/5/6), tile size, show/hide images, show/hide price on tile
- **Quick-access buttons** — configurable button grid on the POS main screen for most-sold products or categories
- **Cart display** — compact view (name + price only) or detailed view (name + quantity + modifiers + price)
- **Receipt header/footer** — customise printed receipt with store logo, header text, footer text, social media links
- **RTL / LTR toggle** — force right-to-left or left-to-right layout regardless of system locale
- **Sound effects** — enable/disable beep on scan, payment success chime, error alert sound
- **Screen timeout** — lock screen after N minutes of inactivity; requires PIN to unlock

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Language & Localization** | RTL/LTR toggle works in conjunction with language selection |
| **Roles & Permissions** | `settings.manage` for store-level settings; individual cashiers can configure their own preferences |
| **Subscription & Billing** | Advanced customization options may be gated by subscription tier |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | All visual aspects of the POS screen are controlled by these settings |
| **Barcode Label Printing** | Receipt customization (header/footer) is configured here |
| **Accessibility** | Font scaling and high contrast are accessibility-related settings |

### Features to Review After Changing This Feature
1. **POS Terminal** — any layout change affects POS screen rendering
2. **Accessibility** — font and contrast settings overlap with accessibility features

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **flutter_secure_storage** | Store sensitive preferences (PIN) |
| **shared_preferences** | Persist non-sensitive UI preferences locally |
| **drift** | SQLite ORM — store-level settings synced from cloud |
| **riverpod** / **flutter_bloc** | State management for theme, layout, and preference reactive updates |
| **google_fonts** / **flutter** built-in | Custom font loading and scaling |
| **image_picker** / **file_selector** | Upload store logo for receipt header |

### 3.2 Technologies
- **Flutter 3.x Desktop** — all customization applied to the POS app UI
- **Dart ThemeData** — Flutter's built-in theming system; custom ThemeData generated from user preferences
- **MediaQuery textScaleFactor** — font size scaling across the entire app
- **Directionality widget** — wraps the app to force RTL or LTR layout direction
- **SharedPreferences** — per-terminal settings stored locally (not synced to cloud)
- **Drift DB** — store-level settings (receipt template, default theme) synced via cloud

---

## 4. Screens

### 4.1 POS Settings — Appearance Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/appearance` |
| **Purpose** | Configure visual theme and layout |
| **Sections** | Theme (Light/Dark/Custom + colour pickers), Font Size slider (0.8× to 1.5×), Handedness toggle (Left/Right), Product grid columns (2–6 slider), Show product images toggle, Show price on grid tile toggle |
| **Preview** | Live preview panel showing POS layout with current settings |
| **Scope** | Per-terminal (each POS registers its own preference) |
| **Access** | All users (own terminal), `settings.manage` (store defaults) |

### 4.2 POS Settings — Quick Access Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/quick-access` |
| **Purpose** | Configure quick-access product/category buttons on POS main screen |
| **Layout** | Drag-and-drop grid editor; add product or category button; set colour, icon, label |
| **Button Types** | Product (adds product to cart on tap), Category (filters product grid to category), Function (open cash drawer, hold cart, etc.) |
| **Grid Size** | Configurable rows × columns (default 4×5) |
| **Access** | `settings.manage` |

### 4.3 Receipt Customization Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/receipt` |
| **Purpose** | Customise printed receipt appearance |
| **Fields** | Store logo (image upload), Header text line 1 (e.g. store name), Header text line 2 (e.g. address), Footer text (e.g. "Thank you! Visit again"), Show VAT number on receipt toggle, Show loyalty points on receipt toggle, Show barcode/QR on receipt toggle, Paper width (58mm/80mm) |
| **Preview** | Receipt preview panel |
| **Access** | `settings.manage` |

### 4.4 Sound & Security Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/terminal` |
| **Purpose** | Configure terminal-level behaviour |
| **Fields** | Scan beep toggle, Payment chime toggle, Error sound toggle, Screen timeout (minutes, 0 = disabled), Lock PIN configuration |
| **Access** | All users (own terminal) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/settings/pos-customization` | GET | Get store-level POS customization settings | Bearer token |
| `PUT /api/settings/pos-customization` | PUT | Update store-level settings | Bearer token + `settings.manage` |
| `GET /api/settings/receipt-template` | GET | Get receipt template settings | Bearer token |
| `PUT /api/settings/receipt-template` | PUT | Update receipt template | Bearer token + `settings.manage` |
| `POST /api/settings/receipt-template/logo` | POST | Upload store logo | Bearer token + `settings.manage` |
| `GET /api/settings/quick-access` | GET | Get quick-access button config | Bearer token |
| `PUT /api/settings/quick-access` | PUT | Save quick-access button config | Bearer token + `settings.manage` |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `ThemeService` | Generates Flutter ThemeData from stored preferences; notifies listeners on change |
| `LayoutPreferenceService` | Manages handedness, grid columns, cart display mode |
| `QuickAccessConfigService` | CRUD on quick-access button grid configuration |
| `ReceiptTemplateService` | Manages receipt header/footer/logo; generates ESC/POS receipt template |
| `TerminalPreferenceService` | Sound toggles, screen timeout, lock PIN via SharedPreferences |
| `FontScaleService` | Manages textScaleFactor; applies globally via MediaQuery |

---

## 6. Full Database Schema

### 6.1 Tables

#### `pos_customization_settings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL, UNIQUE | One per store |
| theme | VARCHAR(20) | DEFAULT 'light' | light, dark, custom |
| primary_color | VARCHAR(7) | DEFAULT '#1976D2' | Hex colour |
| secondary_color | VARCHAR(7) | NULLABLE | |
| accent_color | VARCHAR(7) | NULLABLE | |
| font_scale | DECIMAL(3,2) | DEFAULT 1.00 | 0.80 to 1.50 |
| handedness | VARCHAR(10) | DEFAULT 'right' | left, right |
| grid_columns | INT | DEFAULT 4 | 2–6 |
| show_product_images | BOOLEAN | DEFAULT TRUE | |
| show_price_on_grid | BOOLEAN | DEFAULT TRUE | |
| cart_display_mode | VARCHAR(20) | DEFAULT 'detailed' | compact, detailed |
| layout_direction | VARCHAR(5) | DEFAULT 'auto' | ltr, rtl, auto |
| sync_version | INT | DEFAULT 1 | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE pos_customization_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    theme VARCHAR(20) DEFAULT 'light',
    primary_color VARCHAR(7) DEFAULT '#1976D2',
    secondary_color VARCHAR(7),
    accent_color VARCHAR(7),
    font_scale DECIMAL(3,2) DEFAULT 1.00,
    handedness VARCHAR(10) DEFAULT 'right',
    grid_columns INT DEFAULT 4,
    show_product_images BOOLEAN DEFAULT TRUE,
    show_price_on_grid BOOLEAN DEFAULT TRUE,
    cart_display_mode VARCHAR(20) DEFAULT 'detailed',
    layout_direction VARCHAR(5) DEFAULT 'auto',
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `receipt_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL, UNIQUE | |
| logo_url | TEXT | NULLABLE | Store logo image URL |
| header_line_1 | VARCHAR(255) | NULLABLE | |
| header_line_2 | VARCHAR(255) | NULLABLE | |
| footer_text | TEXT | NULLABLE | |
| show_vat_number | BOOLEAN | DEFAULT TRUE | |
| show_loyalty_points | BOOLEAN | DEFAULT TRUE | |
| show_barcode | BOOLEAN | DEFAULT TRUE | |
| paper_width_mm | INT | DEFAULT 80 | 58 or 80 |
| sync_version | INT | DEFAULT 1 | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE receipt_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    logo_url TEXT,
    header_line_1 VARCHAR(255),
    header_line_2 VARCHAR(255),
    footer_text TEXT,
    show_vat_number BOOLEAN DEFAULT TRUE,
    show_loyalty_points BOOLEAN DEFAULT TRUE,
    show_barcode BOOLEAN DEFAULT TRUE,
    paper_width_mm INT DEFAULT 80,
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `quick_access_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL, UNIQUE | |
| grid_rows | INT | DEFAULT 4 | |
| grid_cols | INT | DEFAULT 5 | |
| buttons_json | JSONB | NOT NULL | Array of {position, type, reference_id, label, color, icon} |
| sync_version | INT | DEFAULT 1 | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE quick_access_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    grid_rows INT DEFAULT 4,
    grid_cols INT DEFAULT 5,
    buttons_json JSONB NOT NULL DEFAULT '[]',
    sync_version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `pos_customization_store` | store_id | UNIQUE | One setting per store |
| `receipt_templates_store` | store_id | UNIQUE | One receipt template per store |
| `quick_access_store` | store_id | UNIQUE | One quick-access config per store |

### 6.3 Relationships Diagram
```
stores ──1:1──▶ pos_customization_settings
stores ──1:1──▶ receipt_templates
stores ──1:1──▶ quick_access_configs
```

---

## 7. Business Rules

1. **Per-terminal vs per-store** — theme, font scale, handedness, and sound settings are per-terminal (stored in SharedPreferences); receipt template, quick-access grid, and grid columns are per-store (synced to cloud)
2. **Font scale limits** — minimum 0.80×, maximum 1.50× — prevents unusable extremes
3. **Handedness swap** — switching handedness mirrors the cart panel and action button panel; no data loss, purely visual
4. **Quick-access max buttons** — maximum grid size is 6×8 = 48 buttons; minimum is 2×2 = 4
5. **Custom theme validation** — custom hex colours must be valid 6-character hex codes; the system validates contrast ratio between text and background to ensure readability
6. **Receipt logo** — logo image is resized to fit receipt width (max 384px for 80mm, 256px for 58mm); only PNG and JPEG accepted
7. **Screen timeout PIN** — if screen timeout is enabled, the lock PIN must be set; PIN is 4–6 digits, stored hashed in flutter_secure_storage
8. **RTL auto-detection** — when `layout_direction` is 'auto', the app uses the current language setting to determine direction (Arabic = RTL, English = LTR)
9. **Settings sync on login** — store-level settings are synced from cloud on terminal login; per-terminal settings remain local
10. **Default settings** — new stores get default settings (light theme, right-handed, 4-column grid, 80mm paper) automatically on creation
