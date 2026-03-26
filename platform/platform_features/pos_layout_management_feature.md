# POS Interface & Layout Management — Comprehensive Feature Documentation

> **Scope:** Platform ( Internal Admin Panel)  
> **Module:** Layouts, Themes, Handedness, Font Sizes, Business-Type Templates, Drag-and-Drop Layout Builder, Widget System & Template Marketplace  
> **Tech Stack:** Laravel 12 + Filament v3.3 · Flutter Desktop (consumer) · Drift/SQLite (POS local) · PostgreSQL (Supabase)  

---

## 1. Feature Overview

POS Interface & Layout Management controls **how the POS looks and behaves** for every store on the platform.  admin manages layout templates per business type, global themes, handedness defaults, font-size presets, and package-tier visibility rules. Providers and individual users can override defaults via a cascade system (user → store → platform).

The feature includes an **advanced drag-and-drop Layout Builder** that enables building POS cashier page layouts using a widget-based canvas system (24×16 grid by default, configurable), a **Widget Catalog** with categorised reusable UI components (product grid, cart panel, payment buttons, etc.), **per-widget theme overrides** for granular styling, **template versioning** with full canvas/theme/placement snapshots, and **template cloning** with deep copy of all placements and overrides.

A **Template Marketplace** allows publishers to list their layout templates for other stores to discover, purchase (one-time or subscription), and review. The marketplace includes a full approval workflow (draft → pending review → approved/rejected/suspended), rating/review system with verified purchase badges, and subscription management with auto-renewal and cancellation.

### What This Feature Does
- **Layout templates per business type**: multiple POS view options for each type (e.g. Supermarket has 5 layouts: barcode-scan grid, full product grid, split view, express checkout, self-checkout kiosk; Restaurant has 3: table management, quick-order, KDS-integrated)
- Add, edit, reorder, or disable layout templates per business type
- Set which layout is the default for each business type
- **Handedness defaults**: set platform-wide default (right-hand / left-hand / centered); providers and users can override
- **Font size defaults**: set platform-wide default (small 0.85× / medium 1× / large 1.2× / extra-large 1.5×)
- **Theme management**: create, edit, deactivate themes (Light Classic, Dark Mode, High Contrast,  Brand, custom hex palettes)
- Provider and user preferences override platform default (**cascade: user → store → platform**)
- Control which customisation options (themes, layouts) are visible per package tier via visibility join tables
- **Receipt layout template designer** — platform-managed receipt layout templates that define section ordering, fonts, ZATCA QR placement, and bilingual rendering; providers can select from platform templates or customize
- **Customer-Facing Display (CFD) themes** — platform-managed CFD visual themes (colours, idle content layout, animation style) available to providers based on subscription tier
- **Digital signage content templates** — platform-managed pre-built signage templates (menu board, promotional slideshow, queue display) per business type that providers can use as starting points
- **Label layout templates** — platform-managed barcode and price label templates for various label sizes (30×20mm, 40×30mm, 50×30mm, etc.) per business type. Providers select from these templates in their label printing settings. Templates define field layout (barcode position, product name, price, weight, expiry date) and are gated by subscription tier.
- **Drag-and-drop Layout Builder** — a canvas-based layout editor for building POS cashier pages by placing, resizing, and arranging widgets on a configurable grid. Each template supports a 24×16 default canvas (configurable up to 48×32), with gap and padding controls. Widgets snap to grid positions and enforce min/max size constraints.
- **Widget catalog** — a managed library of reusable POS UI components organised into categories: Core (product grid, cart panel, payment buttons), Commerce (category bar, search, customer info, order summary, quick actions), Display (receipt preview, clock, branding), and Utility (numpad, custom HTML, held orders). Each widget has configurable properties via a JSON schema.
- **Per-widget theme overrides** — CSS variable overrides (e.g., `--bg-color`, `--font-size`) that can be set on individual widget placements, allowing fine-grained styling within a single template without modifying the global theme.
- **Template versioning** — every layout template can have version snapshots that capture the complete state (canvas config, theme config, all widget placements with properties) at a point in time. Versions support changelogs and can be browsed in the admin panel.
- **Template cloning** — deep copy of any template including all widget placements and their theme overrides, generating a unique `layout_key` for the clone.
- **Template Marketplace** — a marketplace where publishers list layout templates (with optional bundled themes) for stores to discover, filter, and acquire. Supports three pricing models: Free, One-Time purchase, and Subscription (monthly/yearly). Includes a full admin approval workflow, review/rating system with verified purchase tracking, and featured listing promotion.
- **Enhanced theming** — themes now support granular design tokens: `typography_config`, `spacing_config`, `border_config`, `shadow_config`, `animation_config`, and `css_variables` (all JSONB). A `theme_variables` table stores individual CSS variables with typed categories (Color, Size, Font, Spacing, Opacity, Shadow, BorderRadius) for systematic theme management.

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Package & Subscription Mgmt** | `theme_package_visibility` and `layout_package_visibility` join tables gate which themes/layouts appear per plan |
| **Content & Onboarding Mgmt** | Business types list and their default layout are part of the onboarding flow |
| **Provider Management** | Store detail shows current layout, theme, and handedness |
| **POS Terminal (Flutter)** | POS app reads layout config, theme, handedness, font size on login and renders the appropriate view. The Layout Builder full-layout export API provides complete canvas + widget placement data for the Flutter rendering engine. |
| **Provider Settings (provider-side)** | Store owner selects layout, theme, handedness from the platform-managed options |
| **Nice-to-Have Features (Provider)** | CFD themes and digital signage templates are the platform-managed counterparts of the provider's CFD settings and signage manager |
| **POS Terminal (Provider)** | Receipt layout templates define the starting receipt configuration that the provider's receipt settings screen then customizes |
| **Barcode & Label Printing (Provider)** | Label layout templates define the available label designs for the provider's label printing feature; templates are selected per product or globally |
| **Template Marketplace (Stores)** | Stores browse, purchase (one-time or subscribe), and apply marketplace templates to their POS layout. Purchase and subscription status gates access to templates. |
| **Billing & Payments** | Marketplace one-time purchases and recurring subscriptions require payment processing integration (payment_reference + payment_gateway stored per purchase) |

### Features to Review After Changing This Feature
1. **Package visibility** — if a new layout or theme is added, it must be assigned to relevant plan tiers via join tables
2. **Flutter POS Layout Factory** — new `layout_key` values must have corresponding Flutter widget implementations
3. **Onboarding flow** — changes to business types or default layouts affect the provider registration wizard
4. **Provider preferences** — cascade logic must pick the correct fallback when a preference is deleted or plan changes
5. **Nice-to-Have Features (Provider)** — CFD theme or signage template changes affect what providers see in their CFD settings and signage manager
6. **Content & Onboarding** — receipt template changes here should be consistent with the business-type-level receipt defaults from onboarding
7. **Barcode & Label Printing (Provider)** — label template changes affect which label designs are available to providers in their label printing settings
8. **Layout Builder canvas** — changing canvas defaults (columns, rows, gap, padding) affects all templates using those defaults; existing placements may need repositioning
9. **Widget catalog** — adding/removing/deactivating widgets affects which components are available in the layout builder; existing placements of removed widgets should be handled gracefully
10. **Marketplace listings** — changing template pricing or suspending listings affects active subscribers; pricing type cannot be changed on listings with active purchases
11. **Marketplace subscriptions** — subscription expiry and auto-renewal need background job integration; expired subscriptions should revoke template access
12. **Template versioning** — version snapshots are point-in-time captures; restoring a version replaces the current canvas and placements

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3.3 | Resources for BusinessType, PosLayoutTemplate, Theme, PlatformUiDefaults, LayoutWidget, MarketplaceCategory, TemplateMarketplaceListing, TemplatePurchase |
| **spatie/laravel-activitylog** | Audit log for layout and theme changes |
| **spatie/laravel-permission** | Access gating: `ui.manage` |
| **intervention/image** *(optional)* | Theme preview image generation |
| **laravel/sanctum** | API authentication for all layout builder and marketplace endpoints |

### 3.2 Technologies
- **Laravel 12 / PHP 8.4** — Eloquent models (HasUuids), Filament Resources, cascade resolution helper, domain-driven folder structure (`app/Domain/ContentOnboarding/`)
- **Filament v3.3** — Admin UI with JSONB editor for layout `config`, colour pickers for themes, reactive forms for marketplace pricing types, approval workflow table actions
- **PostgreSQL (Supabase)** — relational data + JSONB for layout config, canvas breakpoints, theme design tokens, widget properties schemas
- **SQLite :memory:** — in-memory test database with full schema mirror (`RefreshDatabase` trait)
- **Flutter 3.x Desktop** — `POSLayoutFactory` dynamic layout rendering based on `layout_key` and full-layout canvas data
- **Drift (SQLite)** — local cache of layout config, theme, user preferences, widget placements for offline POS
- **Redis** — cache resolved preferences per user (60s TTL)

### 3.3 Application Architecture
```
app/Domain/ContentOnboarding/
├── Controllers/Api/
│   ├── UiController.php           (14 endpoints: layouts, themes, defaults, preferences, receipts, CFD, signage, labels)
│   ├── LayoutBuilderController.php (15 endpoints: widget catalog, canvas config, placements, theme overrides, clone, versions)
│   └── MarketplaceController.php   (12 endpoints: listings, categories, purchases, reviews)
├── Services/
│   ├── PosLayoutService.php        (preference cascade, layout resolution)
│   ├── LayoutBuilderService.php    (widget catalog, canvas CRUD, placement CRUD, theme overrides, clone, versioning)
│   └── MarketplaceService.php      (browse, purchase, subscribe, review, approval workflow)
├── Models/                       (18 models: BusinessType, PosLayoutTemplate, Theme, LayoutWidget, LayoutWidgetPlacement, etc.)
├── Enums/                        (14 enums: WidgetCategory, MarketplacePricingType, MarketplaceListingStatus, etc.)
└── Filament/Resources/           (10 resources: BusinessType, PosLayoutTemplate, Theme, LayoutWidget, MarketplaceCategory, etc.)
```

### 3.4 Enums
| Enum | Values | Used By |
|---|---|---|
| `WidgetCategory` | core, commerce, display, utility, custom | `layout_widgets.category` |
| `MarketplacePricingType` | free, one_time, subscription | `template_marketplace_listings.pricing_type` |
| `MarketplaceListingStatus` | draft, pending_review, approved, rejected, suspended | `template_marketplace_listings.status` |
| `SubscriptionInterval` | monthly, yearly | `template_marketplace_listings.subscription_interval` |
| `PurchaseType` | one_time, subscription | `template_purchases.purchase_type` |
| `ThemeVariableType` | color, size, font, spacing, opacity, shadow, border_radius | `theme_variables.variable_type` |
| `ThemeVariableCategory` | typography, colors, spacing, borders, shadows, animations | `theme_variables.category` |

### 3.5 Preference Cascade Logic
```
Resolution order (first non-null wins):
  1. user_preferences (per cashier/operator)
  2. store.default_preferences (set by store owner)
  3. platform_ui_defaults (set by  admin)
  4. hardcoded system defaults (right-hand, medium font, light theme)
```

---

## 4. Pages

### 4.1 Business Types List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/business-types` |
| **Filament Resource** | `BusinessTypeResource` |
| **Table Columns** | Icon, Name (EN), Name (AR), Slug, Layout Count, Is Active badge, Sort Order |
| **Row Actions** | Edit, View Layouts, Deactivate |
| **Header Action** | Create New Business Type |
| **Access** | `ui.manage` |

### 4.2 Business Type Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/business-types/create` · `/admin/ui/business-types/{id}/edit` |
| **Form Fields** | Name (EN), Name (AR), Slug (auto), Icon (emoji/icon picker), Is Active, Sort Order |
| **Relation Manager** | Inline: POS Layout Templates for this business type (see 4.3) |
| **Access** | `ui.manage` |

### 4.3 POS Layout Templates List (per Business Type)
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/business-types/{id}/layouts` (RelationManager within BusinessType) |
| **Table Columns** | Preview Image (thumbnail), Name (EN), Name (AR), Layout Key, Is Default badge, Is Active badge, Sort Order, Visible Plans (tags) |
| **Row Actions** | Edit, Set as Default, Deactivate, Manage Plan Visibility |
| **Header Action** | Create New Layout |
| **Access** | `ui.manage` |

### 4.4 Layout Template Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/layouts/create` · `/admin/ui/layouts/{id}/edit` |
| **Form Sections** | |
| — Basic Info | Name (EN), Name (AR), Layout Key (unique, e.g. `supermarket_barcode_grid`), Preview Image URL (upload), Is Default toggle, Is Active toggle, Sort Order |
| — Layout Config (JSONB) | Full configuration editor: layout_type (grid/split/minimal/table_view), cart_position (right/bottom/floating), cart_width (%), show_categories, category_style (tabs/sidebar/icons), product_display (grid/list/images), product_columns, show_images, quick_actions array, payment_buttons array, special_features (weighable_items, table_management, kitchen_display, prescription_mode, imei_tracking) |
| — Package Visibility | Multi-select: which subscription plans can see this layout (via `layout_package_visibility`) |
| **Access** | `ui.manage` |

### 4.5 Themes List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/themes` |
| **Filament Resource** | `ThemeResource` |
| **Table Columns** | Colour swatches (primary, secondary, bg), Name, Slug, Is System badge, Is Active badge, Visible Plans (tags) |
| **Row Actions** | Edit, Deactivate |
| **Header Action** | Create New Theme |
| **Access** | `ui.manage` |

### 4.6 Theme Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/themes/create` · `/admin/ui/themes/{id}/edit` |
| **Form Fields** | Name, Slug (auto), Primary Colour (hex picker), Secondary Colour (hex picker), Background Colour (hex picker), Text Colour (hex picker), Is System toggle *(system themes cannot be deleted)*, Is Active toggle |
| — Package Visibility | Multi-select: which subscription plans can see this theme |
| **Access** | `ui.manage` |

### 4.7 Platform UI Defaults
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/defaults` |
| **Purpose** | Set platform-wide defaults for handedness, font size, and default theme |
| **Form Fields** | Default Handedness select (right / left / center), Default Font Size select (small / medium / large / extra-large), Default Theme select (from active themes) |
| **Access** | `ui.manage` |

### 4.8 Receipt Layout Templates List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/receipt-templates` |
| **Filament Resource** | `ReceiptLayoutTemplateResource` |
| **Table Columns** | Name (EN), Name (AR), Paper Width (58/80mm), Business Types (tags), Is Active, Sort Order |
| **Filters** | Paper Width, Business Type, Is Active |
| **Row Actions** | Edit, Preview, Deactivate |
| **Header Actions** | Create Receipt Template |
| **Purpose** | Platform-managed receipt layout templates. These are more detailed than the business-type-level receipt defaults in Content & Onboarding — these define the visual design (fonts, borders, section spacing) while the onboarding defaults define which sections are enabled. Providers select a receipt template from this list in their receipt settings. |
| **Access** | `ui.manage` |

### 4.9 Receipt Layout Template Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/receipt-templates/create` or `/{id}/edit` |
| **Form Sections** | |
| — Basic Info | Name (EN), Name (AR), Slug (auto), Paper Width (58/80mm), Is Active, Sort Order |
| — Header Design | Logo max height (px), Store name font size, Store name bold toggle, Address font size, VAT number display toggle, Separator style (line / dashes / none) |
| — Body Design | Item name font size, Price alignment (right / left), Show SKU toggle, Show barcode toggle, Column widths (JSONB: name %, qty %, price %), Row separator (line / none), Subtotal/Discount/VAT/Total styling (bold, font size) |
| — Footer Design | ZATCA QR size (px), Receipt number format, Cashier name toggle, Custom footer text (EN/AR), Thank-you message (EN/AR), Social media handles display |
| — Package Visibility | Multi-select: which subscription plans can use this template |
| **Preview** | Live preview panel showing a mock receipt with the current settings |
| **Access** | `ui.manage` |

### 4.10 CFD Themes List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/cfd-themes` |
| **Filament Resource** | `CfdThemeResource` |
| **Table Columns** | Preview (thumbnail), Name, Slug, Idle Layout (slideshow / static / video), Is Active, Visible Plans (tags) |
| **Row Actions** | Edit, Preview, Deactivate |
| **Header Actions** | Create CFD Theme |
| **Purpose** | Platform-managed visual themes for the Customer-Facing Display. Providers select a CFD theme in their CFD settings. Each theme defines the visual appearance of the cart display, idle content layout, and animation transitions. |
| **Access** | `ui.manage` |

### 4.11 CFD Theme Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/cfd-themes/create` or `/{id}/edit` |
| **Form Fields** | Name, Slug (auto), Background Color (hex), Text Color (hex), Accent Color (hex), Font Family, Cart Display Layout (list / grid), Idle Content Layout (slideshow / static_image / video_loop), Animation Style (fade / slide / none), Transition Duration (seconds), Show Store Logo toggle, Show Running Total toggle, Thank-You Animation (confetti / check / none), Is Active, Package Visibility (multi-select plans) |
| **Access** | `ui.manage` |

### 4.12 Digital Signage Templates List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/signage-templates` |
| **Filament Resource** | `SignageTemplateResource` |
| **Table Columns** | Preview (thumbnail), Name, Slug, Template Type (menu_board / promo_slideshow / queue_display / info_board), Business Types (tags), Is Active |
| **Row Actions** | Edit, Preview, Deactivate |
| **Header Actions** | Create Signage Template |
| **Purpose** | Platform-managed pre-built digital signage templates. Providers start from these templates in their signage manager and customize content (images, product data, text). Templates define the layout structure; providers fill in the content. |
| **Access** | `ui.manage` |

### 4.13 Signage Template Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/signage-templates/create` or `/{id}/edit` |
| **Form Sections** | |
| — Basic Info | Name (EN), Name (AR), Slug (auto), Template Type select (menu_board / promo_slideshow / queue_display / info_board), Business Types (multi-select), Is Active |
| — Layout Config (JSONB) | Regions: array of `{region_id, type (image/text/product_grid/video/clock), position (x%, y%, w%, h%), default_content}` — defines the visual regions/zones of the signage layout |
| — Placeholder Content | Default images, default text blocks, sample product grid config — providers replace these with their own content |
| — Styling | Background color, text color, font family, transition style between slides |
| — Package Visibility | Multi-select: subscription plans |
| **Access** | `ui.manage` |

### 4.14 Label Layout Templates List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/label-templates` |
| **Filament Resource** | `LabelLayoutTemplateResource` |
| **Table Columns** | Preview (thumbnail), Name (EN), Name (AR), Label Size (30×20mm, 40×30mm, etc.), Label Type (barcode / price / shelf / jewelry / pharmacy), Business Types (tags), Is Active |
| **Filters** | Label Type, Label Size, Business Type, Is Active |
| **Row Actions** | Edit, Preview, Deactivate |
| **Header Actions** | Create Label Template |
| **Purpose** | Platform-managed label layout templates for barcode/price label printing. Providers select a label template in their label printing settings. Templates define field positions (barcode, product name, price, weight, expiry) on the label and are gated by subscription plan. Different business types need different label fields (e.g. Jewelry: karat, weight, making charge; Pharmacy: drug info, expiry, batch; Supermarket: barcode, price, weight). |
| **Access** | `ui.manage` |

### 4.15 Label Template Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/label-templates/create` or `/{id}/edit` |
| **Form Sections** | |
| — Basic Info | Name (EN), Name (AR), Slug (auto), Label Type select (barcode / price / shelf / jewelry / pharmacy), Label Size select (30x20 / 40x30 / 50x30 / 60x40 / custom), Custom Width mm (if custom), Custom Height mm (if custom), Business Types (multi-select), Is Active |
| — Barcode Settings | Barcode Type select (CODE128 / EAN13 / EAN8 / QR / Code39), Barcode Position (x%, y%, w%, h%), Show Barcode Number toggle |
| — Field Layout (JSONB) | Array of `{field_key, label_en, label_ar, position: {x%, y%, w%, h%}, font_size, is_bold, alignment}` — available field keys: `product_name`, `product_name_ar`, `sku`, `barcode`, `price`, `price_before_discount`, `weight`, `unit`, `expiry_date`, `batch_number`, `manufacture_date`, `origin_country`, `karat`, `making_charge`, `drug_schedule`, `store_name`, `custom_text` |
| — Styling | Font Family, Default Font Size, Border toggle, Border Style (solid / dashed), Background Color |
| — Package Visibility | Multi-select: subscription plans |
| **Preview** | Live preview panel showing a mock label with sample data |
| **Access** | `ui.manage` |

### 4.16 Layout Widgets List (Widget Catalog)
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/layout-widgets` |
| **Filament Resource** | `LayoutWidgetResource` |
| **Table Columns** | Name (EN), Name (AR), Slug, Category (color-coded badge: Core=primary, Commerce=success, Display=info, Utility=warning, Custom=gray), Default Size (W×H), Is Required badge, Is Active badge, Sort Order |
| **Filters** | Category, Is Active, Is Required |
| **Row Actions** | Edit, Toggle Active |
| **Header Actions** | Create Widget |
| **Purpose** | Manage the widget catalog used in the drag-and-drop Layout Builder. Each widget represents a reusable POS UI component (e.g., product grid, cart panel, numpad). Widgets define their sizing constraints (min/max/default width and height), a JSON properties schema, and default property values. |
| **Access** | `ui.manage` |

### 4.17 Layout Widget Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/layout-widgets/create` or `/{id}/edit` |
| **Form Sections** | |
| — Widget Info | Name (EN), Name (AR), Slug (unique), Description, Category select (core / commerce / display / utility / custom), Icon (Heroicon), Is Active, Is Required (required widgets must be placed on every template), Sort Order |
| — Sizing | Default Width, Default Height, Min Width, Min Height, Max Width, Max Height (all in grid units), Is Resizable toggle |
| — Properties | Properties Schema (JSONB editor — defines configurable options like columns count, display mode, etc.), Default Properties (JSONB editor — default values for the schema) |
| **Access** | `ui.manage` |

### 4.18 Marketplace Categories List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/marketplace-categories` |
| **Filament Resource** | `MarketplaceCategoryResource` |
| **Table Columns** | Name (EN), Name (AR), Slug, Parent Category, Listings Count, Is Active badge, Sort Order |
| **Row Actions** | Edit |
| **Header Actions** | Create Category |
| **Purpose** | Manage hierarchical categories for organising marketplace template listings (e.g., Retail, Restaurant, Grocery, Pharmacy, Electronics, Fashion, Services, Minimal). Supports parent-child nesting. |
| **Access** | `ui.manage` |

### 4.19 Template Marketplace Listings List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/marketplace-listings` |
| **Filament Resource** | `TemplateMarketplaceListingResource` |
| **Table Columns** | Title (EN), Publisher Name, Template Name (relation), Pricing Type (color-coded badge: free=success, one_time=info, subscription=warning), Price Amount, Status (color-coded: draft=gray, pending_review=warning, approved=success, rejected=danger, suspended=danger), Is Featured badge, Download Count, Average Rating |
| **Filters** | Status, Pricing Type, Is Featured, Category |
| **Row Actions** | Edit, Approve, Reject (with reason modal), Suspend, Toggle Featured |
| **Header Actions** | Create Listing |
| **Purpose** | Full marketplace listing management with approval workflow. Listings can be submitted for review, approved/rejected/suspended by admins. Rejected listings show the rejection reason. The form is reactive — pricing-type-dependent fields (price, subscription interval) show/hide based on the selected pricing type. |
| **Access** | `ui.manage` |

### 4.20 Template Marketplace Listing Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/marketplace-listings/create` or `/{id}/edit` |
| **Form Sections** | |
| — Template Link | POS Layout Template select (FK), Bundled Theme select (optional FK to themes) |
| — Publisher Info | Publisher Name, Publisher URL (optional) |
| — Listing Info | Title (EN), Title (AR), Description (EN, RichEditor), Description (AR, RichEditor) |
| — Pricing | Pricing Type select (free / one_time / subscription → reactive), Price Amount (shown if one_time or subscription), Subscription Interval select (monthly / yearly, shown if subscription) |
| — Category & Media | Category select (from marketplace_categories), Preview Images (JSONB array of URLs), Tags (JSONB tag input) |
| — Flags | Is Featured toggle |
| **Access** | `ui.manage` |

### 4.21 Template Purchases List
| Field | Detail |
|---|---|
| **Route** | `/admin/ui/template-purchases` |
| **Filament Resource** | `TemplatePurchaseResource` |
| **Table Columns** | Store Name (relation), Listing Title (relation), Purchase Type badge (one_time=info, subscription=warning), Amount Paid, Payment Gateway, Is Active badge, Subscription Expires At, Auto Renew badge |
| **Row Actions** | Edit (limited — admin read-mostly view) |
| **Purpose** | Admin view of all template purchases across the platform. No create action — purchases are made via the API by stores. Useful for support, refund tracking, and subscription monitoring. |
| **Access** | `ui.manage` |

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD for `BusinessTypeResource`, `PosLayoutTemplateResource`, `ThemeResource`, `LayoutWidgetResource`, `MarketplaceCategoryResource`, `TemplateMarketplaceListingResource`, `TemplatePurchaseResource`.

### 5.2 Provider-Facing APIs (consumed by POS app)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v2/ui/layouts` | GET | List available layouts for the store's business type + plan | Sanctum |
| `GET /api/v2/ui/themes` | GET | List available themes for the store's plan | Sanctum |
| `GET /api/v2/ui/defaults` | GET | Get platform-wide defaults (handedness, font, theme) | Sanctum |
| `GET /api/v2/ui/preferences` | GET | Get resolved preferences for current user (cascade applied) | Sanctum |
| `PUT /api/v2/ui/preferences` | PUT | Update user-level preferences (handedness, font, theme, layout) | Sanctum |
| `PUT /api/v2/ui/store-defaults` | PUT | Store owner sets store-level default preferences | Sanctum |
| `GET /api/v2/ui/receipt-templates` | GET | List available receipt layout templates (plan-filtered) | Sanctum |
| `GET /api/v2/ui/receipt-templates/{slug}` | GET | Get full receipt template config for rendering | Sanctum |
| `GET /api/v2/ui/cfd-themes` | GET | List available CFD themes (plan-filtered) | Sanctum |
| `GET /api/v2/ui/cfd-themes/{slug}` | GET | Get full CFD theme config | Sanctum |
| `GET /api/v2/ui/signage-templates` | GET | List available signage templates for store's business type + plan | Sanctum |
| `GET /api/v2/ui/signage-templates/{slug}` | GET | Get full signage template config (regions, placeholders) | Sanctum |
| `GET /api/v2/ui/label-templates` | GET | List available label layout templates for store's business type + plan | Sanctum |
| `GET /api/v2/ui/label-templates/{slug}` | GET | Get full label template config (field layout, barcode settings, styling) | Sanctum |

### 5.3 Layout Builder APIs (consumed by admin/Flutter layout editor)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v2/ui/layout-builder/widgets` | GET | List all active widgets (optional `?category=` filter) | Sanctum |
| `GET /api/v2/ui/layout-builder/widgets/{id}` | GET | Get single widget detail with properties schema | Sanctum |
| `GET /api/v2/ui/layout-builder/templates/{id}/canvas` | GET | Get canvas config (columns, rows, gap, padding, breakpoints, lock status) | Sanctum |
| `PUT /api/v2/ui/layout-builder/templates/{id}/canvas` | PUT | Update canvas config (validates: columns 1-48, rows 1-32, gap 0-32, padding 0-64). Blocked if template is locked. | Sanctum |
| `GET /api/v2/ui/layout-builder/templates/{id}/placements` | GET | List all widget placements for a template (with widget details) | Sanctum |
| `POST /api/v2/ui/layout-builder/templates/{id}/placements` | POST | Add widget to template (grid_x, grid_y, grid_w, grid_h, z_index, properties). Uses widget defaults if size omitted. Blocked if template locked or widget inactive. | Sanctum |
| `PUT /api/v2/ui/layout-builder/placements/{id}` | PUT | Update placement position/size. Enforces widget min/max constraints (clamps values). | Sanctum |
| `DELETE /api/v2/ui/layout-builder/placements/{id}` | DELETE | Remove widget placement from template | Sanctum |
| `PUT /api/v2/ui/layout-builder/templates/{id}/placements/batch` | PUT | Batch update multiple placements at once (transactional) | Sanctum |
| `PUT /api/v2/ui/layout-builder/placements/{id}/theme-overrides` | PUT | Set CSS variable overrides for a specific widget placement (upsert pattern) | Sanctum |
| `DELETE /api/v2/ui/layout-builder/placements/{id}/theme-overrides/{key}` | DELETE | Remove a single theme override by variable key | Sanctum |
| `POST /api/v2/ui/layout-builder/templates/{id}/clone` | POST | Deep clone template with all placements + theme overrides. Generates unique layout_key. | Sanctum |
| `GET /api/v2/ui/layout-builder/templates/{id}/versions` | GET | List all version snapshots for a template | Sanctum |
| `POST /api/v2/ui/layout-builder/templates/{id}/versions` | POST | Create version snapshot (captures canvas, theme, all placements). Updates template version number. | Sanctum |
| `GET /api/v2/ui/layout-builder/templates/{id}/full` | GET | Export full layout (template + canvas config + all placements with widget data) | Sanctum |

### 5.4 Marketplace APIs (consumed by POS app store browser)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v2/ui/marketplace/listings` | GET | Browse approved listings with filters: `?search=`, `?category_id=`, `?pricing_type=`, `?is_featured=`, `?min_rating=`, `?sort=` (newest/popular/rating/price_asc/price_desc) | Sanctum |
| `GET /api/v2/ui/marketplace/listings/{id}` | GET | Get single listing with full details | Sanctum |
| `GET /api/v2/ui/marketplace/categories` | GET | List all active marketplace categories (hierarchical with children) | Sanctum |
| `GET /api/v2/ui/marketplace/categories/{id}` | GET | Get single category detail | Sanctum |
| `POST /api/v2/ui/marketplace/listings/{id}/purchase` | POST | Purchase/subscribe to a listing. Accepts `payment_reference`, `payment_gateway`, `auto_renew`. Prevents duplicate active purchases. Calculates subscription expiry. | Sanctum |
| `GET /api/v2/ui/marketplace/my-purchases` | GET | List all purchases for the authenticated user's store | Sanctum |
| `GET /api/v2/ui/marketplace/listings/{id}/check-access` | GET | Check if store has active access to a listing (returns `{has_access: bool}`) | Sanctum |
| `POST /api/v2/ui/marketplace/purchases/{id}/cancel` | POST | Cancel subscription (sets auto_renew=false, records cancelled_at) | Sanctum |
| `GET /api/v2/ui/marketplace/listings/{id}/reviews` | GET | List published reviews for a listing | Sanctum |
| `POST /api/v2/ui/marketplace/listings/{id}/reviews` | POST | Create review (rating 1-5, title, body). Checks for verified purchase. Prevents duplicate reviews. Recalculates listing average rating. | Sanctum |
| `PUT /api/v2/ui/marketplace/reviews/{id}` | PUT | Update own review. Recalculates listing average rating. | Sanctum |
| `DELETE /api/v2/ui/marketplace/reviews/{id}` | DELETE | Delete own review. Recalculates listing average rating. | Sanctum |

---

## 6. Full Database Schema

### 6.1 Tables

#### `business_types`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | English name |
| name_ar | VARCHAR(100) | NOT NULL | Arabic name |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | supermarket, restaurant, pharmacy, bakery, etc. |
| icon | VARCHAR(10) | NULLABLE | Emoji or icon code |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE business_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `pos_layout_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| business_type_id | UUID | FK → business_types(id) | |
| layout_key | VARCHAR(50) | NOT NULL, UNIQUE | e.g. `supermarket_barcode_grid` |
| name | VARCHAR(100) | NOT NULL | |
| name_ar | VARCHAR(100) | NOT NULL | |
| description | TEXT | NULLABLE | |
| preview_image_url | TEXT | NULLABLE | |
| config | JSONB | NOT NULL | Full layout configuration (layout_type, cart_position, cart_width, show_categories, category_style, product_display, product_columns, show_images, quick_actions, payment_buttons, special_features) |
| is_default | BOOLEAN | DEFAULT FALSE | Default layout for this business type |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| canvas_columns | INT | DEFAULT 24 | Number of columns in the widget grid canvas |
| canvas_rows | INT | DEFAULT 16 | Number of rows in the widget grid canvas |
| canvas_gap_px | INT | DEFAULT 4 | Gap between grid cells in pixels |
| canvas_padding_px | INT | DEFAULT 8 | Outer padding of the canvas in pixels |
| breakpoints | JSONB | DEFAULT '[]' | Responsive breakpoint overrides for different screen sizes |
| version | VARCHAR(20) | DEFAULT '1.0.0' | Current semantic version of the template |
| is_locked | BOOLEAN | DEFAULT FALSE | Locked templates cannot have canvas/placements modified |
| clone_source_id | UUID | NULLABLE FK → pos_layout_templates(id) | Self-referencing FK for cloned templates |
| published_at | TIMESTAMP | NULLABLE | When the template was published |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE pos_layout_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID NOT NULL REFERENCES business_types(id),
    layout_key VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    preview_image_url TEXT,
    config JSONB NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    canvas_columns INT DEFAULT 24,
    canvas_rows INT DEFAULT 16,
    canvas_gap_px INT DEFAULT 4,
    canvas_padding_px INT DEFAULT 8,
    breakpoints JSONB DEFAULT '[]',
    version VARCHAR(20) DEFAULT '1.0.0',
    is_locked BOOLEAN DEFAULT FALSE,
    clone_source_id UUID REFERENCES pos_layout_templates(id),
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `platform_ui_defaults`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| key | VARCHAR(50) | PK | handedness / font_size / theme |
| value | VARCHAR(100) | NOT NULL | right / medium / light_classic |

```sql
CREATE TABLE platform_ui_defaults (
    key VARCHAR(50) PRIMARY KEY,
    value VARCHAR(100) NOT NULL
);
-- Seed: INSERT INTO platform_ui_defaults VALUES ('handedness','right'),('font_size','medium'),('theme','light_classic');
```

#### `themes`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | light_classic, dark_mode, high_contrast, _brand |
| primary_color | VARCHAR(7) | NOT NULL | Hex, e.g. #1A56A0 |
| secondary_color | VARCHAR(7) | NOT NULL | |
| background_color | VARCHAR(7) | NOT NULL | |
| text_color | VARCHAR(7) | NOT NULL | |
| typography_config | JSONB | DEFAULT '{}' | Font family, sizes, weights, line heights |
| spacing_config | JSONB | DEFAULT '{}' | Base spacing unit, scale, margins, paddings |
| border_config | JSONB | DEFAULT '{}' | Border widths, radius values, styles |
| shadow_config | JSONB | DEFAULT '{}' | Box shadow definitions, elevation levels |
| animation_config | JSONB | DEFAULT '{}' | Duration, easing, transition definitions |
| css_variables | JSONB | DEFAULT '{}' | Custom CSS variable key-value pairs |
| is_active | BOOLEAN | DEFAULT TRUE | |
| is_system | BOOLEAN | DEFAULT FALSE | System themes cannot be deleted |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE themes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    primary_color VARCHAR(7) NOT NULL,
    secondary_color VARCHAR(7) NOT NULL,
    background_color VARCHAR(7) NOT NULL,
    text_color VARCHAR(7) NOT NULL,
    typography_config JSONB DEFAULT '{}',
    spacing_config JSONB DEFAULT '{}',
    border_config JSONB DEFAULT '{}',
    shadow_config JSONB DEFAULT '{}',
    animation_config JSONB DEFAULT '{}',
    css_variables JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `theme_package_visibility` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| theme_id | UUID | FK → themes(id) ON DELETE CASCADE | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |

```sql
CREATE TABLE theme_package_visibility (
    theme_id UUID NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (theme_id, subscription_plan_id)
);
```

#### `layout_package_visibility` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| pos_layout_template_id | UUID | FK → pos_layout_templates(id) ON DELETE CASCADE | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |

```sql
CREATE TABLE layout_package_visibility (
    pos_layout_template_id UUID NOT NULL REFERENCES pos_layout_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (pos_layout_template_id, subscription_plan_id)
);
```

#### `receipt_layout_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | e.g. `clean_minimal`, `classic_detailed`, `pharmacy_compact` |
| paper_width | INT | NOT NULL | 58 or 80 (mm) |
| header_config | JSONB | NOT NULL | `{"logo_max_height_px":60,"store_name_font_size":"large","store_name_bold":true,"address_font_size":"small","show_vat_number":true,"separator":"dashes"}` |
| body_config | JSONB | NOT NULL | `{"item_font_size":"medium","price_alignment":"right","show_sku":false,"show_barcode":false,"column_widths":{"name":50,"qty":15,"price":35},"row_separator":"none","totals_bold":true}` |
| footer_config | JSONB | NOT NULL | `{"zatca_qr_size_px":120,"show_receipt_number":true,"show_cashier_name":true,"custom_footer_text":"","custom_footer_text_ar":"","thank_you_en":"Thank you!","thank_you_ar":"شكراً لزيارتكم","show_social_handles":false}` |
| zatca_qr_position | VARCHAR(10) | DEFAULT 'footer' | header / footer |
| show_bilingual | BOOLEAN | DEFAULT TRUE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE receipt_layout_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    paper_width INT NOT NULL DEFAULT 80,
    header_config JSONB NOT NULL DEFAULT '{}',
    body_config JSONB NOT NULL DEFAULT '{}',
    footer_config JSONB NOT NULL DEFAULT '{}',
    zatca_qr_position VARCHAR(10) DEFAULT 'footer',
    show_bilingual BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `receipt_template_package_visibility` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| receipt_layout_template_id | UUID | FK → receipt_layout_templates(id) ON DELETE CASCADE | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |

```sql
CREATE TABLE receipt_template_package_visibility (
    receipt_layout_template_id UUID NOT NULL REFERENCES receipt_layout_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (receipt_layout_template_id, subscription_plan_id)
);
```

#### `cfd_themes`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | |
| background_color | VARCHAR(7) | NOT NULL | Hex |
| text_color | VARCHAR(7) | NOT NULL | |
| accent_color | VARCHAR(7) | NOT NULL | |
| font_family | VARCHAR(50) | DEFAULT 'system' | |
| cart_layout | VARCHAR(10) | DEFAULT 'list' | list / grid |
| idle_layout | VARCHAR(20) | DEFAULT 'slideshow' | slideshow / static_image / video_loop |
| animation_style | VARCHAR(10) | DEFAULT 'fade' | fade / slide / none |
| transition_seconds | INT | DEFAULT 5 | |
| show_store_logo | BOOLEAN | DEFAULT TRUE | |
| show_running_total | BOOLEAN | DEFAULT TRUE | |
| thank_you_animation | VARCHAR(15) | DEFAULT 'check' | confetti / check / none |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE cfd_themes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    background_color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    text_color VARCHAR(7) NOT NULL DEFAULT '#333333',
    accent_color VARCHAR(7) NOT NULL DEFAULT '#1A56A0',
    font_family VARCHAR(50) DEFAULT 'system',
    cart_layout VARCHAR(10) DEFAULT 'list',
    idle_layout VARCHAR(20) DEFAULT 'slideshow',
    animation_style VARCHAR(10) DEFAULT 'fade',
    transition_seconds INT DEFAULT 5,
    show_store_logo BOOLEAN DEFAULT TRUE,
    show_running_total BOOLEAN DEFAULT TRUE,
    thank_you_animation VARCHAR(15) DEFAULT 'check',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `cfd_theme_package_visibility` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| cfd_theme_id | UUID | FK → cfd_themes(id) ON DELETE CASCADE | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |

```sql
CREATE TABLE cfd_theme_package_visibility (
    cfd_theme_id UUID NOT NULL REFERENCES cfd_themes(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (cfd_theme_id, subscription_plan_id)
);
```

#### `signage_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | |
| template_type | VARCHAR(20) | NOT NULL | menu_board / promo_slideshow / queue_display / info_board |
| layout_config | JSONB | NOT NULL | Regions: `[{"region_id":"main","type":"product_grid","position":{"x":0,"y":0,"w":100,"h":80}},{"region_id":"footer","type":"text","position":{"x":0,"y":80,"w":100,"h":20}}]` |
| placeholder_content | JSONB | DEFAULT '{}' | Default images/text per region |
| background_color | VARCHAR(7) | DEFAULT '#FFFFFF' | |
| text_color | VARCHAR(7) | DEFAULT '#333333' | |
| font_family | VARCHAR(50) | DEFAULT 'system' | |
| transition_style | VARCHAR(10) | DEFAULT 'fade' | |
| preview_image_url | TEXT | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE signage_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    template_type VARCHAR(20) NOT NULL,
    layout_config JSONB NOT NULL DEFAULT '[]',
    placeholder_content JSONB DEFAULT '{}',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    text_color VARCHAR(7) DEFAULT '#333333',
    font_family VARCHAR(50) DEFAULT 'system',
    transition_style VARCHAR(10) DEFAULT 'fade',
    preview_image_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `signage_template_business_types` (Join Table — templates available per business type)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| signage_template_id | UUID | FK → signage_templates(id) ON DELETE CASCADE | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE | |

```sql
CREATE TABLE signage_template_business_types (
    signage_template_id UUID NOT NULL REFERENCES signage_templates(id) ON DELETE CASCADE,
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    PRIMARY KEY (signage_template_id, business_type_id)
);
```

#### `signage_template_package_visibility` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| signage_template_id | UUID | FK → signage_templates(id) ON DELETE CASCADE | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |

```sql
CREATE TABLE signage_template_package_visibility (
    signage_template_id UUID NOT NULL REFERENCES signage_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (signage_template_id, subscription_plan_id)
);
```

#### `label_layout_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| name | VARCHAR(100) | NOT NULL | EN |
| name_ar | VARCHAR(100) | NOT NULL | AR |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | e.g. `supermarket_barcode_40x30`, `jewelry_price_tag` |
| label_type | VARCHAR(20) | NOT NULL | barcode / price / shelf / jewelry / pharmacy |
| label_width_mm | INT | NOT NULL | Physical label width in mm |
| label_height_mm | INT | NOT NULL | Physical label height in mm |
| barcode_type | VARCHAR(15) | DEFAULT 'CODE128' | CODE128 / EAN13 / EAN8 / QR / Code39 |
| barcode_position | JSONB | NULLABLE | `{"x":10,"y":5,"w":80,"h":30}` — percentages |
| show_barcode_number | BOOLEAN | DEFAULT TRUE | |
| field_layout | JSONB | NOT NULL | `[{"field_key":"product_name","label_en":"Product","label_ar":"المنتج","position":{"x":5,"y":40,"w":90,"h":15},"font_size":"medium","is_bold":true,"alignment":"center"}, …]` |
| font_family | VARCHAR(50) | DEFAULT 'system' | |
| default_font_size | VARCHAR(10) | DEFAULT 'small' | small / medium / large |
| show_border | BOOLEAN | DEFAULT FALSE | |
| border_style | VARCHAR(10) | DEFAULT 'solid' | solid / dashed |
| background_color | VARCHAR(7) | DEFAULT '#FFFFFF' | |
| preview_image_url | TEXT | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE label_layout_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label_type VARCHAR(20) NOT NULL,
    label_width_mm INT NOT NULL,
    label_height_mm INT NOT NULL,
    barcode_type VARCHAR(15) DEFAULT 'CODE128',
    barcode_position JSONB,
    show_barcode_number BOOLEAN DEFAULT TRUE,
    field_layout JSONB NOT NULL DEFAULT '[]',
    font_family VARCHAR(50) DEFAULT 'system',
    default_font_size VARCHAR(10) DEFAULT 'small',
    show_border BOOLEAN DEFAULT FALSE,
    border_style VARCHAR(10) DEFAULT 'solid',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    preview_image_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `label_template_business_types` (Join Table — templates available per business type)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| label_layout_template_id | UUID | FK → label_layout_templates(id) ON DELETE CASCADE | |
| business_type_id | UUID | FK → business_types(id) ON DELETE CASCADE | |

```sql
CREATE TABLE label_template_business_types (
    label_layout_template_id UUID NOT NULL REFERENCES label_layout_templates(id) ON DELETE CASCADE,
    business_type_id UUID NOT NULL REFERENCES business_types(id) ON DELETE CASCADE,
    PRIMARY KEY (label_layout_template_id, business_type_id)
);
```

#### `label_template_package_visibility` (Join Table)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| label_layout_template_id | UUID | FK → label_layout_templates(id) ON DELETE CASCADE | |
| subscription_plan_id | UUID | FK → subscription_plans(id) ON DELETE CASCADE | |

```sql
CREATE TABLE label_template_package_visibility (
    label_layout_template_id UUID NOT NULL REFERENCES label_layout_templates(id) ON DELETE CASCADE,
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
    PRIMARY KEY (label_layout_template_id, subscription_plan_id)
);
```

#### `layout_widgets` (Widget Catalog)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | e.g. `product_grid`, `cart_panel`, `numpad` |
| name | VARCHAR(100) | NOT NULL | English name |
| name_ar | VARCHAR(100) | NOT NULL | Arabic name |
| description | TEXT | NULLABLE | |
| category | VARCHAR(20) | NOT NULL | core / commerce / display / utility / custom (enum: WidgetCategory) |
| icon | VARCHAR(50) | NULLABLE | Heroicon identifier |
| default_width | INT | NOT NULL DEFAULT 6 | Default grid width when placed |
| default_height | INT | NOT NULL DEFAULT 4 | Default grid height when placed |
| min_width | INT | NOT NULL DEFAULT 2 | Minimum resize width |
| min_height | INT | NOT NULL DEFAULT 2 | Minimum resize height |
| max_width | INT | NOT NULL DEFAULT 24 | Maximum resize width |
| max_height | INT | NOT NULL DEFAULT 16 | Maximum resize height |
| is_resizable | BOOLEAN | DEFAULT TRUE | Whether the widget can be resized |
| is_required | BOOLEAN | DEFAULT FALSE | Required widgets must be placed on every template |
| properties_schema | JSONB | DEFAULT '{}' | JSON schema defining configurable properties (e.g., `{"columns": {"type": "integer", "default": 4}}`) |
| default_properties | JSONB | DEFAULT '{}' | Default values for the properties schema |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE layout_widgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(20) NOT NULL,
    icon VARCHAR(50),
    default_width INT NOT NULL DEFAULT 6,
    default_height INT NOT NULL DEFAULT 4,
    min_width INT NOT NULL DEFAULT 2,
    min_height INT NOT NULL DEFAULT 2,
    max_width INT NOT NULL DEFAULT 24,
    max_height INT NOT NULL DEFAULT 16,
    is_resizable BOOLEAN DEFAULT TRUE,
    is_required BOOLEAN DEFAULT FALSE,
    properties_schema JSONB DEFAULT '{}',
    default_properties JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `layout_widget_placements` (Widget instances on a template canvas)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| pos_layout_template_id | UUID | FK → pos_layout_templates(id) ON DELETE CASCADE | |
| layout_widget_id | UUID | FK → layout_widgets(id) ON DELETE CASCADE | |
| instance_key | VARCHAR(100) | NOT NULL | Unique per template, e.g. `product_grid_1` |
| grid_x | INT | NOT NULL DEFAULT 0 | Column position on canvas |
| grid_y | INT | NOT NULL DEFAULT 0 | Row position on canvas |
| grid_w | INT | NOT NULL | Width in grid units |
| grid_h | INT | NOT NULL | Height in grid units |
| z_index | INT | DEFAULT 0 | Stacking order for overlapping widgets |
| properties | JSONB | DEFAULT '{}' | Instance-specific property overrides |
| is_visible | BOOLEAN | DEFAULT TRUE | Toggle widget visibility without removing |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE layout_widget_placements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pos_layout_template_id UUID NOT NULL REFERENCES pos_layout_templates(id) ON DELETE CASCADE,
    layout_widget_id UUID NOT NULL REFERENCES layout_widgets(id) ON DELETE CASCADE,
    instance_key VARCHAR(100) NOT NULL,
    grid_x INT NOT NULL DEFAULT 0,
    grid_y INT NOT NULL DEFAULT 0,
    grid_w INT NOT NULL,
    grid_h INT NOT NULL,
    z_index INT DEFAULT 0,
    properties JSONB DEFAULT '{}',
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(pos_layout_template_id, instance_key)
);
```

#### `widget_theme_overrides` (Per-widget CSS variable overrides)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| layout_widget_placement_id | UUID | FK → layout_widget_placements(id) ON DELETE CASCADE | |
| variable_key | VARCHAR(100) | NOT NULL | CSS variable name, e.g. `--bg-color`, `--font-size` |
| value | VARCHAR(255) | NOT NULL | Override value, e.g. `#FF0000`, `14px` |

```sql
CREATE TABLE widget_theme_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    layout_widget_placement_id UUID NOT NULL REFERENCES layout_widget_placements(id) ON DELETE CASCADE,
    variable_key VARCHAR(100) NOT NULL,
    value VARCHAR(255) NOT NULL,
    UNIQUE(layout_widget_placement_id, variable_key)
);
```

#### `template_versions` (Version snapshots)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| pos_layout_template_id | UUID | FK → pos_layout_templates(id) ON DELETE CASCADE | |
| version_number | VARCHAR(20) | NOT NULL | Semantic version, e.g. `1.2.0` |
| changelog | TEXT | NULLABLE | Description of changes in this version |
| canvas_snapshot | JSONB | NOT NULL | Complete canvas config at time of snapshot |
| theme_snapshot | JSONB | DEFAULT '{}' | Theme config at time of snapshot |
| widget_placements_snapshot | JSONB | NOT NULL | Array of all placement data with properties |
| published_by | UUID | NULLABLE | User who published this version |
| published_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE template_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pos_layout_template_id UUID NOT NULL REFERENCES pos_layout_templates(id) ON DELETE CASCADE,
    version_number VARCHAR(20) NOT NULL,
    changelog TEXT,
    canvas_snapshot JSONB NOT NULL,
    theme_snapshot JSONB DEFAULT '{}',
    widget_placements_snapshot JSONB NOT NULL,
    published_by UUID,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `marketplace_categories` (Marketplace category hierarchy)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| parent_id | UUID | NULLABLE FK → marketplace_categories(id) | Self-referencing for hierarchy |
| name | VARCHAR(100) | NOT NULL | English name |
| name_ar | VARCHAR(100) | NOT NULL | Arabic name |
| slug | VARCHAR(50) | NOT NULL, UNIQUE | e.g. `retail`, `restaurant`, `minimal` |
| description | TEXT | NULLABLE | |
| icon | VARCHAR(50) | NULLABLE | Heroicon identifier |
| is_active | BOOLEAN | DEFAULT TRUE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE marketplace_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_id UUID REFERENCES marketplace_categories(id),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `template_marketplace_listings` (Templates listed on the marketplace)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| pos_layout_template_id | UUID | FK → pos_layout_templates(id), UNIQUE | One listing per template |
| bundled_theme_id | UUID | NULLABLE FK → themes(id) | Optional theme sold with the template |
| category_id | UUID | NULLABLE FK → marketplace_categories(id) | |
| publisher_name | VARCHAR(100) | NOT NULL | |
| publisher_url | TEXT | NULLABLE | |
| title | VARCHAR(200) | NOT NULL | English title |
| title_ar | VARCHAR(200) | NOT NULL | Arabic title |
| description | TEXT | NOT NULL | English description |
| description_ar | TEXT | NOT NULL | Arabic description |
| pricing_type | VARCHAR(20) | NOT NULL | free / one_time / subscription (enum: MarketplacePricingType) |
| price_amount | DECIMAL(10,2) | DEFAULT 0.00 | |
| subscription_interval | VARCHAR(10) | NULLABLE | monthly / yearly (enum: SubscriptionInterval) |
| status | VARCHAR(20) | NOT NULL DEFAULT 'draft' | draft / pending_review / approved / rejected / suspended (enum: MarketplaceListingStatus) |
| preview_images | JSONB | DEFAULT '[]' | Array of image URLs |
| tags | JSONB | DEFAULT '[]' | Searchable tag array |
| is_featured | BOOLEAN | DEFAULT FALSE | Featured listing promotion |
| download_count | INT | DEFAULT 0 | Number of purchases/downloads |
| average_rating | DECIMAL(3,1) | DEFAULT 0.0 | Calculated average from reviews |
| review_count | INT | DEFAULT 0 | Number of reviews |
| approved_by | UUID | NULLABLE | Admin who approved |
| approved_at | TIMESTAMP | NULLABLE | |
| rejection_reason | TEXT | NULLABLE | Reason if rejected |
| published_at | TIMESTAMP | NULLABLE | When listing went live |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE template_marketplace_listings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pos_layout_template_id UUID NOT NULL UNIQUE REFERENCES pos_layout_templates(id),
    bundled_theme_id UUID REFERENCES themes(id),
    category_id UUID REFERENCES marketplace_categories(id),
    publisher_name VARCHAR(100) NOT NULL,
    publisher_url TEXT,
    title VARCHAR(200) NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    pricing_type VARCHAR(20) NOT NULL,
    price_amount DECIMAL(10,2) DEFAULT 0.00,
    subscription_interval VARCHAR(10),
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    preview_images JSONB DEFAULT '[]',
    tags JSONB DEFAULT '[]',
    is_featured BOOLEAN DEFAULT FALSE,
    download_count INT DEFAULT 0,
    average_rating DECIMAL(3,1) DEFAULT 0.0,
    review_count INT DEFAULT 0,
    approved_by UUID,
    approved_at TIMESTAMP,
    rejection_reason TEXT,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `template_purchases` (Store purchases of marketplace templates)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) ON DELETE CASCADE | Purchasing store |
| marketplace_listing_id | UUID | FK → template_marketplace_listings(id) | |
| purchase_type | VARCHAR(20) | NOT NULL | one_time / subscription (enum: PurchaseType) |
| amount_paid | DECIMAL(10,2) | DEFAULT 0.00 | |
| payment_reference | VARCHAR(255) | NULLABLE | External payment system reference |
| payment_gateway | VARCHAR(50) | NULLABLE | e.g. stripe, paypal |
| subscription_starts_at | TIMESTAMP | NULLABLE | |
| subscription_expires_at | TIMESTAMP | NULLABLE | |
| auto_renew | BOOLEAN | DEFAULT TRUE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| cancelled_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE template_purchases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    marketplace_listing_id UUID NOT NULL REFERENCES template_marketplace_listings(id),
    purchase_type VARCHAR(20) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    payment_reference VARCHAR(255),
    payment_gateway VARCHAR(50),
    subscription_starts_at TIMESTAMP,
    subscription_expires_at TIMESTAMP,
    auto_renew BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    cancelled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `template_reviews` (User reviews of marketplace listings)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| marketplace_listing_id | UUID | FK → template_marketplace_listings(id) ON DELETE CASCADE | |
| store_id | UUID | FK → stores(id) ON DELETE CASCADE | |
| user_id | UUID | FK → users(id) ON DELETE CASCADE | |
| rating | INT | NOT NULL | 1-5 stars |
| title | VARCHAR(200) | NULLABLE | |
| body | TEXT | NULLABLE | |
| is_verified_purchase | BOOLEAN | DEFAULT FALSE | True if reviewer has purchased the template |
| is_published | BOOLEAN | DEFAULT TRUE | |
| admin_response | TEXT | NULLABLE | Admin/publisher reply |
| admin_responded_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE template_reviews (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    marketplace_listing_id UUID NOT NULL REFERENCES template_marketplace_listings(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    body TEXT,
    is_verified_purchase BOOLEAN DEFAULT FALSE,
    is_published BOOLEAN DEFAULT TRUE,
    admin_response TEXT,
    admin_responded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(marketplace_listing_id, user_id)
);
```

#### `theme_variables` (Granular CSS variables per theme)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| theme_id | UUID | FK → themes(id) ON DELETE CASCADE | |
| variable_key | VARCHAR(100) | NOT NULL | e.g. `--primary-color`, `--font-size-base` |
| variable_value | VARCHAR(255) | NOT NULL | e.g. `#FD8209`, `16px` |
| variable_type | VARCHAR(20) | NOT NULL | color / size / font / spacing / opacity / shadow / border_radius (enum: ThemeVariableType) |
| category | VARCHAR(20) | NOT NULL | typography / colors / spacing / borders / shadows / animations (enum: ThemeVariableCategory) |
| description | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE theme_variables (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    theme_id UUID NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
    variable_key VARCHAR(100) NOT NULL,
    variable_value VARCHAR(255) NOT NULL,
    variable_type VARCHAR(20) NOT NULL,
    category VARCHAR(20) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(theme_id, variable_key)
);
```

#### Cross-Referenced Provider-Side Table: `user_preferences`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| user_id | UUID | FK → users(id) ON DELETE CASCADE, UNIQUE | One row per user |
| pos_handedness | VARCHAR(10) | NULLABLE | right / left / center |
| font_size | VARCHAR(15) | NULLABLE | small / medium / large / extra-large |
| theme | VARCHAR(50) | NULLABLE | theme slug |
| pos_layout_id | UUID | NULLABLE FK → pos_layout_templates(id) | |

```sql
CREATE TABLE user_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    pos_handedness VARCHAR(10),
    font_size VARCHAR(15),
    theme VARCHAR(50),
    pos_layout_id UUID REFERENCES pos_layout_templates(id)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `business_types_slug_unique` | slug | UNIQUE | Lookup by slug |
| `pos_layout_templates_layout_key` | layout_key | UNIQUE | Lookup by key |
| `pos_layout_templates_bt_default` | (business_type_id, is_default) | B-TREE | Find default layout per business type |
| `pos_layout_templates_clone_source` | clone_source_id | B-TREE | Find clones of a template |
| `themes_slug_unique` | slug | UNIQUE | Lookup by slug |
| `theme_package_visibility_pk` | (theme_id, subscription_plan_id) | UNIQUE PK | Prevent duplicates |
| `layout_package_visibility_pk` | (pos_layout_template_id, subscription_plan_id) | UNIQUE PK | Prevent duplicates |
| `receipt_layout_templates_slug` | slug | UNIQUE | Lookup by slug |
| `receipt_template_pkg_vis_pk` | (receipt_layout_template_id, subscription_plan_id) | UNIQUE PK | Prevent duplicates |
| `cfd_themes_slug` | slug | UNIQUE | Lookup by slug |
| `cfd_theme_pkg_vis_pk` | (cfd_theme_id, subscription_plan_id) | UNIQUE PK | Prevent duplicates |
| `signage_templates_slug` | slug | UNIQUE | Lookup by slug |
| `signage_templates_type` | template_type | B-TREE | Filter by type |
| `signage_tmpl_business_types_pk` | (signage_template_id, business_type_id) | UNIQUE PK | Prevent duplicates |
| `signage_tmpl_pkg_vis_pk` | (signage_template_id, subscription_plan_id) | UNIQUE PK | Prevent duplicates |
| `label_layout_templates_slug` | slug | UNIQUE | Lookup by slug |
| `label_layout_templates_type` | label_type | B-TREE | Filter by label type |
| `label_tmpl_business_types_pk` | (label_layout_template_id, business_type_id) | UNIQUE PK | Prevent duplicates |
| `label_tmpl_pkg_vis_pk` | (label_layout_template_id, subscription_plan_id) | UNIQUE PK | Prevent duplicates |
| `layout_widgets_slug_unique` | slug | UNIQUE | Lookup widget by slug |
| `layout_widgets_category` | category | B-TREE | Filter widgets by category |
| `layout_widget_placements_template` | pos_layout_template_id | B-TREE | Fast lookup of all placements for a template |
| `layout_widget_placements_instance` | (pos_layout_template_id, instance_key) | UNIQUE | Prevent duplicate instance keys per template |
| `widget_theme_overrides_placement` | layout_widget_placement_id | B-TREE | Fast lookup of overrides for a placement |
| `widget_theme_overrides_unique` | (layout_widget_placement_id, variable_key) | UNIQUE | Prevent duplicate variable overrides per placement |
| `template_versions_template` | pos_layout_template_id | B-TREE | List versions for a template |
| `marketplace_categories_slug` | slug | UNIQUE | Lookup category by slug |
| `marketplace_categories_parent` | parent_id | B-TREE | Find child categories |
| `marketplace_listings_template` | pos_layout_template_id | UNIQUE | One listing per template |
| `marketplace_listings_status` | status | B-TREE | Filter listings by approval status |
| `marketplace_listings_category` | category_id | B-TREE | Filter listings by category |
| `template_purchases_store` | store_id | B-TREE | List purchases for a store |
| `template_purchases_listing` | marketplace_listing_id | B-TREE | List purchases for a listing |
| `template_reviews_listing` | marketplace_listing_id | B-TREE | List reviews for a listing |
| `template_reviews_unique` | (marketplace_listing_id, user_id) | UNIQUE | One review per user per listing |
| `theme_variables_theme` | theme_id | B-TREE | List variables for a theme |
| `theme_variables_unique` | (theme_id, variable_key) | UNIQUE | Prevent duplicate variable keys per theme |

### 6.3 Relationships Diagram
```
business_types ──1:N──▶ pos_layout_templates
pos_layout_templates ──M:N──▶ subscription_plans  (via layout_package_visibility)
pos_layout_templates ──1:N──▶ layout_widget_placements
pos_layout_templates ──1:N──▶ template_versions
pos_layout_templates ──1:1──▶ template_marketplace_listings
pos_layout_templates ──0:1──▶ pos_layout_templates  (clone_source_id self-reference)

themes ──M:N──▶ subscription_plans  (via theme_package_visibility)
themes ──1:N──▶ theme_variables
themes ──1:N──▶ template_marketplace_listings  (bundled_theme_id)

layout_widgets ──1:N──▶ layout_widget_placements
layout_widget_placements ──1:N──▶ widget_theme_overrides

marketplace_categories ──0:1──▶ marketplace_categories  (parent_id self-reference)
marketplace_categories ──1:N──▶ template_marketplace_listings
template_marketplace_listings ──1:N──▶ template_purchases
template_marketplace_listings ──1:N──▶ template_reviews

receipt_layout_templates ──M:N──▶ subscription_plans  (via receipt_template_package_visibility)
cfd_themes ──M:N──▶ subscription_plans  (via cfd_theme_package_visibility)
signage_templates ──M:N──▶ business_types  (via signage_template_business_types)
signage_templates ──M:N──▶ subscription_plans  (via signage_template_package_visibility)

label_layout_templates ──M:N──▶ business_types  (via label_template_business_types)
label_layout_templates ──M:N──▶ subscription_plans  (via label_template_package_visibility)

stores ──1:N──▶ template_purchases
stores ──1:N──▶ template_reviews
stores.business_type_id ──FK──▶ business_types
stores.pos_layout_id ──FK──▶ pos_layout_templates  (store default)
stores.receipt_layout_template_id ──FK──▶ receipt_layout_templates  (store receipt selection)
stores.cfd_theme_id ──FK──▶ cfd_themes  (store CFD selection)
stores.label_layout_template_id ──FK──▶ label_layout_templates  (store default label template)

users ──1:1──▶ user_preferences (per-user override)
users ──1:N──▶ template_reviews
platform_ui_defaults  (global fallback, key-value)
```

---

## 7. Business Rules

1. **Only one default layout per business type** — setting a layout as default un-defaults the previous one
2. **Cascade resolution** — user preference > store default > platform default > hardcoded fallback
3. **Package visibility gating** — a provider on Starter plan only sees themes/layouts assigned to Starter; Enterprise sees all
4. **RTL handedness flip** — when the UI language is Arabic (RTL), "right-handed" places the action area on the physical right side by flipping the CSS direction; the system auto-detects locale
5. **Font size applies proportionally** — product names, prices, cart lines, buttons, and numpad all scale by the chosen factor
6. **System themes are undeletable** — themes marked `is_system = true` can be deactivated but not deleted
7. **JSONB config validation** — layout `config` JSONB is validated against a schema on save to ensure all required keys exist
8. **Offline-available** — layout config, theme, and preferences are synced to the Flutter POS local SQLite (Drift) so the POS works offline with the user's last-known preferences
9. **Receipt template selection cascade** — when a store is created, the business-type receipt default from `business_type_receipt_templates` (Content & Onboarding) is used as the initial config. The store owner can then select a different receipt layout template from `receipt_layout_templates` (this feature) for more refined visual control. The two work together: onboarding defaults define sections enabled; layout templates define visual styling.
10. **CFD theme package gating** — CFD themes are gated by subscription plan via `cfd_theme_package_visibility`; if a store's plan is downgraded, their selected CFD theme reverts to the first available theme for their new plan.
11. **Signage template scoping** — signage templates are doubly scoped: by business type (a menu board template only appears for Restaurant/Café business types) AND by subscription plan (signage features are typically Professional tier and above).
12. **Receipt template preview** — the admin preview panel renders a mock receipt using sample data (fictitious store, product list, ZATCA QR) to show exactly how the template looks on 58mm and 80mm paper.
13. **CFD themes are independent of POS themes** — a store can have a dark POS theme but a light CFD theme. They are separately configured.
14. **Signage template regions** — the `layout_config` JSONB defines rectangular regions on the display canvas. Each region has a type (image, text, product_grid, video, clock) and position coordinates in percentages. Providers populate regions with their own content using the region IDs as keys.
15. **Label template scoping** — label templates are doubly scoped: by business type (a jewelry label template only appears for Jewelry business types) AND by subscription plan (advanced label printing is typically Professional tier and above).
16. **Label template field keys** — the `field_layout` JSONB references predefined field keys (`product_name`, `sku`, `barcode`, `price`, `weight`, `expiry_date`, etc.). The POS label printing engine maps these keys to actual product data when rendering labels. Unknown field keys are ignored.
17. **Label size matching** — the POS printer setup detects the loaded label size and filters available templates to matching dimensions. A template designed for 40×30mm labels won't appear if the printer is loaded with 60×40mm labels (±2mm tolerance).
18. **Label template preview** — the admin preview panel renders a mock label using sample product data (fictitious product name, barcode, price, weight) to show exact field positioning on the specified label size.

### Layout Builder Business Rules
19. **Canvas grid system** — the layout builder uses a configurable grid canvas (default 24 columns × 16 rows). All widget placements snap to grid coordinates. The admin can adjust canvas dimensions (1-48 columns, 1-32 rows), gap (0-32px), and padding (0-64px).
20. **Widget size constraints** — each widget in the catalog defines min/max width and height. When a placement is updated, the service enforces these constraints by clamping values: if the requested size is below minimum it's raised to minimum, if above maximum it's lowered to maximum.
21. **Required widgets** — widgets marked `is_required = true` (e.g., product_grid, cart_panel, payment_buttons) must be present on every active template. The system allows creating templates without them but they should be treated as incomplete.
22. **Locked templates** — templates with `is_locked = true` cannot have their canvas config, widget placements, or theme overrides modified via the API. This protects published/marketplace templates from accidental changes.
23. **Template cloning** — cloning creates a deep copy of the source template including all widget placements and their per-widget theme overrides. The clone receives a unique auto-generated `layout_key`, `is_locked = false`, `is_default = false`, and records the `clone_source_id` for traceability. The clone starts at version `1.0.0`.
24. **Version snapshots** — creating a version captures the complete state: canvas config, theme config (if associated), and all widget placements with their properties. This is a point-in-time snapshot stored as JSONB. The template's `version` field is updated to match the new version number.
25. **Widget instance keys** — each placement has a unique `instance_key` per template (e.g., `product_grid_1`). This is auto-generated on placement creation and used to identify specific widget instances in the Flutter renderer.
26. **Per-widget theme overrides** — CSS variable overrides set on a specific widget placement take precedence over the global theme values. Overrides use an upsert pattern: setting an existing key updates it, setting a new key adds it.

### Marketplace Business Rules
27. **Listing approval workflow** — marketplace listings follow a strict state machine: `Draft → Pending Review → Approved | Rejected | Suspended`. Only `Draft` listings can be submitted for review. Only `Pending Review` listings can be approved or rejected. Any approved listing can be suspended. Rejected listings must be edited and resubmitted.
28. **One listing per template** — a template can only have one marketplace listing (enforced by UNIQUE constraint on `pos_layout_template_id`). To list a variant, the template must be cloned first.
29. **Pricing type protection** — once a listing has active purchases, its `pricing_type` cannot be changed. This prevents breaking existing subscriptions or invalidating one-time purchases.
30. **Subscription expiry** — subscription purchases calculate expiry based on `subscription_interval`: monthly adds 30 days, yearly adds 365 days from the purchase date. Access checks verify both `is_active = true` and `subscription_expires_at > now()`.
31. **Auto-renewal** — subscriptions default to `auto_renew = true`. Cancellation sets `auto_renew = false` and records `cancelled_at` but does not immediately revoke access — the subscription remains active until `subscription_expires_at`.
32. **Duplicate purchase prevention** — a store cannot purchase the same listing twice if they already have an active purchase (one-time) or an active non-expired subscription.
33. **Free template access** — free listings (`pricing_type = free`) create a purchase record with `amount_paid = 0.00` and `purchase_type = one_time` for tracking. No payment reference is required.
34. **Review system** — one review per user per listing (enforced by UNIQUE constraint). Reviews automatically check if the reviewer has purchased the template and set `is_verified_purchase` accordingly. Creating, updating, or deleting a review triggers automatic recalculation of the listing's `average_rating` and `review_count`.
35. **Rating calculation** — `average_rating` is recalculated as `AVG(rating)` of all published reviews for the listing. `review_count` is the total count of published reviews. Both are stored denormalized on the listing for fast query filtering.
36. **Browse filtering** — the marketplace browse API only returns `Approved` listings. Supports filtering by search text (ILIKE on title/description), category, pricing type, featured status, minimum rating, and sorting by newest, most popular (download count), highest rated, price ascending/descending.
37. **Download count** — incremented by 1 on each successful purchase. Used for "most popular" sorting in the marketplace.
38. **Admin review response** — admins can respond to reviews via `admin_response` with a timestamp. This appears alongside the user review in the marketplace.
