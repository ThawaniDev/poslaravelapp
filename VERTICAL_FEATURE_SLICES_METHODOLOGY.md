# Vertical Feature Slices — Build Methodology

> **Wameed POS System**
> Last Updated: 9 March 2026
> Status: Phase 0 (Scaffolding) — In Progress

---

## Table of Contents

1. [Philosophy](#1-philosophy)
2. [Architecture Overview](#2-architecture-overview)
3. [Phase 0: Project Scaffolding](#3-phase-0-project-scaffolding)
4. [Phase 1: Foundation Features](#4-phase-1-foundation-features)
5. [Phase 2: Core POS Features](#5-phase-2-core-pos-features)
6. [Phase 3: Business Features](#6-phase-3-business-features)
7. [Phase 4: Platform Admin Features](#7-phase-4-platform-admin-features)
8. [Phase 5: Polish & Scale](#8-phase-5-polish--scale)
9. [Per-Feature Workflow (Inner Loop)](#9-per-feature-workflow-inner-loop)
10. [Feature Build Order — Provider Track](#10-feature-build-order--provider-track)
11. [Feature Build Order — Platform Admin Track](#11-feature-build-order--platform-admin-track)
12. [Dependency Graph](#12-dependency-graph)
13. [Quality Gates](#13-quality-gates)
14. [Definition of Done](#14-definition-of-done)
15. [Progress Tracker](#15-progress-tracker)

---

## 1. Philosophy

### Why Vertical Slices?

Traditional "horizontal" approaches (build all DB first → all backend → all frontend) create these problems:

| Horizontal (Waterfall)             | Vertical (Feature Slices)           |
|------------------------------------|-------------------------------------|
| Nothing works until everything works | Each feature works end-to-end      |
| Massive integration risk at the end | Integration tested continuously     |
| Can't demo or get feedback early   | Demoable after every feature        |
| Hard to estimate remaining work    | Clear progress per feature          |
| Broken imports/references pile up  | Each slice is self-contained        |
| Tests written long after code      | Tests written alongside code        |

### The Rule

> **Build one feature end-to-end (DB → Laravel → Tests → API Docs → Flutter → Tests) before moving to the next.**

Every feature goes through the full stack before the next feature begins. No partial implementations. No "I'll add tests later." No orphan code.

### What This Means in Practice

```
Feature: Product Catalog
│
├── Day 1-2: DB migration + Laravel models/enums/services
├── Day 3-4: API controllers + Filament admin + Form Requests
├── Day 5:   Laravel tests (unit + feature) — MUST PASS
├── Day 6:   Update FEATURE_PRODUCT_CATALOG.md with API specs
├── Day 7-8: Flutter repository + providers + UI pages
├── Day 9:   Flutter tests — MUST PASS
├── Day 10:  Integration test across the stack
│
└── ✅ DONE — move to next feature
```

---

## 2. Architecture Overview

### Technology Stack

| Layer              | Technology                                              |
|--------------------|---------------------------------------------------------|
| **Database**       | PostgreSQL 15+ (Supabase)                               |
| **Backend**        | Laravel 12, PHP 8.4                                     |
| **Admin Panel**    | Filament v3.3                                           |
| **Auth**           | Laravel Sanctum (API tokens)                            |
| **Permissions**    | Spatie Laravel Permission + Filament Shield              |
| **Frontend**       | Flutter 3.38+ (Android, iOS, macOS, Windows, Web)       |
| **State Mgmt**     | Riverpod (flutter_riverpod)                             |
| **HTTP Client**    | Dio                                                     |
| **Routing**        | GoRouter                                                |
| **Local DB**       | Drift (SQLite) — for offline sync                       |
| **Storage**        | DigitalOcean Spaces (S3-compatible)                     |
| **Monitoring**     | Sentry (Laravel + Flutter)                              |
| **CI/CD**          | GitHub Actions                                          |

### Project Locations

```
POS/
├── poslaravelapp/       ← Laravel 12 backend
│   └── app/Domain/      ← 39 feature domains
├── posflutterapp/       ← Flutter multi-platform app
│   └── lib/features/    ← 31 feature modules
├── database_schema.sql  ← 255 tables, master reference
├── models/              ← 254 PHP model source files
├── enums/               ← 162 PHP enum source files
├── flutter/models/      ← 254 Dart model source files
├── flutter/enums/       ← 162 Dart enum source files
└── docs/                ← 47 feature documentation files
```

### Laravel Domain Structure (per feature)

```
app/Domain/{FeatureName}/
├── Models/          ← Eloquent models
├── Enums/           ← PHP enums (backed enums)
├── Controllers/
│   └── Api/         ← REST API controllers
├── Requests/        ← Form Request validation
├── Resources/       ← API Resource transformers
├── Services/        ← Business logic services
├── Actions/         ← Single-purpose action classes
├── DTOs/            ← Data Transfer Objects
├── Events/          ← Domain events
├── Listeners/       ← Event listeners
├── Jobs/            ← Queue jobs
├── Policies/        ← Authorization policies
├── Observers/       ← Model observers
├── Scopes/          ← Query scopes
└── Exceptions/      ← Domain-specific exceptions
```

### Flutter Feature Structure (per feature)

```
lib/features/{feature_name}/
├── models/              ← Data classes
├── enums/               ← Dart enums
├── data/
│   ├── local/
│   │   ├── tables/      ← Drift table definitions
│   │   └── daos/        ← Drift DAOs
│   └── remote/          ← API data sources (Dio)
├── repositories/        ← Repository pattern (local + remote)
├── providers/           ← Riverpod providers
├── pages/               ← Screen widgets
├── widgets/             ← Feature-specific widgets
└── utils/               ← Feature-specific helpers
```

### UI Component Library & Design System Guidelines

> **Every feature screen MUST use the shared design system.** Never hardcode colors,
> font sizes, padding values, or shadow definitions inline. Always import from
> the core theme and widget library.

#### Flutter — Import Patterns

```dart
// Theme tokens (colors, spacing, typography, radius, shadows, sizes)
import 'package:posflutterapp/core/theme/theme.dart';

// Widgets (buttons, cards, inputs, dialogs, badges, scaffold, table)
import 'package:posflutterapp/core/widgets/widgets.dart';

// Formatters (currency, dates, numbers)
import 'package:posflutterapp/core/utils/formatters.dart';
```

#### Flutter — Component Quick Reference

| Need                  | Use                                                |
|-----------------------|----------------------------------------------------|
| Primary CTA button    | `PosButton(label:, onPressed:)`                    |
| Soft / tonal button   | `PosButton(variant: PosButtonVariant.soft)`         |
| Danger / delete       | `PosButton(variant: PosButtonVariant.danger)`       |
| Category pill         | `PosButton.pill(label:, isSelected:)`              |
| Icon FAB              | `PosButton.icon(icon:)`                             |
| Text input            | `PosTextField(label:, hint:)`                       |
| Search bar            | `PosSearchField(onChanged:)`                        |
| Dropdown              | `PosDropdown(items:, value:, onChanged:)`           |
| Toggle switch         | `PosToggle(value:, onChanged:, label:)`             |
| Qty stepper           | `PosNumericCounter(value:, onChanged:)`             |
| App bar               | `PosAppBar(title:, showNotification: true)`         |
| Sidebar (desktop)     | `PosSidebar(currentRoute:, onItemTap:)`             |
| Responsive layout     | `PosResponsiveScaffold(body:, sidebar:)`            |
| Bottom nav (mobile)   | `PosBottomNav(currentIndex:, onTap:)`               |
| Base card             | `PosCard(child:)`                                   |
| KPI card              | `PosKpiCard(label:, value:, trend:)`                |
| Product card (grid)   | `PosProductCard(name:, price:)`                     |
| Product card (list)   | `PosProductListCard(name:, price:)`                 |
| Settings row          | `PosSettingsCard(title:, icon:)`                    |
| Staff card            | `PosStaffCard(name:, role:)`                        |
| Subscription card     | `PosSubscriptionCard(planName:, price:)`            |
| Data table            | `PosDataTable(columns:, rows:)`                     |
| Status badge          | `PosBadge(label:, variant: PosBadgeVariant.success)`|
| Trend indicator       | `PosTrendBadge(value: 12.5)`                        |
| Stock dot             | `PosStockDot(status: 'in_stock')`                   |
| Confirm dialog        | `showPosConfirmDialog(context, title:)`             |
| Bottom sheet          | `showPosBottomSheet(context, builder:)`             |
| Success snackbar      | `showPosSuccessSnackbar(context, 'Saved!')`         |
| Error snackbar        | `showPosErrorSnackbar(context, 'Failed')`           |
| Loading spinner       | `PosLoading(message:)`                              |
| Empty state           | `PosEmptyState(title:, icon:)`                      |
| Section header        | `PosSection(title:, child:)`                        |
| Page wrapper          | `PosPageContainer(child:)`                          |
| Step indicator        | `PosStepIndicator(totalSteps:, currentStep:)`       |
| Progress bar          | `PosProgressBar(value: 0.75, label:)`               |
| Divider with text     | `PosDivider(label: 'OR')`                           |
| Avatar                | `PosAvatar(name:, imageUrl:, showStatus: true)`     |

#### Flutter — Spacing Rules

- **Use `AppSpacing.gapH*` / `gapW*`** for gaps between elements (not raw `SizedBox`)
- **Use `AppSpacing.paddingAll*` / `paddingH*` / `paddingV*`** for padding
- **Use `AppRadius.border*`** for border-radius (never `BorderRadius.circular(x)` inline)
- **Use `AppShadows.*`** for box shadows
- **Use `AppSizes.*`** for icon/avatar/button sizing

#### Flutter — Color Rules

- Never use `Colors.orange`, `Color(0xFF...)`, or raw hex. Always `AppColors.*`.
- Primary actions = `AppColors.primary`
- Success/Warning/Error/Info = `AppColors.success` / `.warning` / `.error` / `.info`
- Text = `AppColors.textPrimaryLight` (or `Dark`), `.textSecondary*`, `.textMuted*`
- Borders = `AppColors.borderLight` / `borderDark`

#### Laravel — Import Patterns

```php
use App\Support\DesignTokens;   // Color/spacing/date constants
use App\Support\Formatters;     // Currency, date, number formatters
```

#### Laravel — Blade Component Usage

```blade
<x-pos.badge variant="success" label="Active" />
<x-pos.card padding="p-6"> ... </x-pos.card>
<x-pos.kpi-card label="Revenue" value="ر.ع. 12,345" :trend="12.5" />
<x-pos.section title="Recent Orders"> ... </x-pos.section>
<x-pos.empty-state title="No products yet" icon="heroicon-o-cube" />
<x-pos.progress-bar :value="75" label="Sync Progress" />
```

#### Formatter Parity

Both Flutter and Laravel have identical formatter APIs:

| Method             | Flutter                          | Laravel                          |
|--------------------|----------------------------------|----------------------------------|
| Currency           | `Formatters.currency(12.5)`      | `Formatters::currency(12.5)`     |
| Short currency     | `Formatters.currencyShort(12.5)` | `Formatters::currencyShort(12.5)`|
| Date display       | `Formatters.date(dt)`            | `Formatters::date($dt)`          |
| Date ISO           | `Formatters.dateIso(dt)`         | `Formatters::dateIso($dt)`       |
| Relative time      | `Formatters.timeAgo(dt)`         | `Formatters::timeAgo($dt)`       |
| Percent            | `Formatters.percent(0.85)`       | `Formatters::percent(0.85)`      |
| Oman phone         | `Formatters.omanPhone('9612..')` | `Formatters::omanPhone('9612..')`|
| File size          | `Formatters.fileSize(1536)`      | `Formatters::fileSize(1536)`     |

---

## 3. Phase 0: Project Scaffolding

> **Goal:** Both projects compile, connect to Supabase, and have the foundation for feature development.

### Checklist

| #   | Task                                                            | Status |
|-----|-----------------------------------------------------------------|--------|
| 0.1 | **Laravel project created** (`poslaravelapp/`)                  | ✅ Done |
|     | — Laravel 12.53.0, PHP 8.4.12                                  | ✅ Done |
|     | — Feature-based Domain structure (39 domains, 585 subdirs)      | ✅ Done |
|     | — 254 PHP models placed with correct `App\Domain\*` namespaces | ✅ Done |
|     | — 162 PHP enums placed with correct `App\Domain\*` namespaces  | ✅ Done |
|     | — All `use` import statements fixed (186 cross-domain refs)     | ✅ Done |
|     | — Composer packages installed (113 packages)                    | ✅ Done |
|     | — Filament admin panel registered at `/admin`                   | ✅ Done |
|     | — Sanctum API authentication configured                         | ✅ Done |
|     | — Spatie Permission + Filament Shield configured                | ✅ Done |
|     | — Custom configs: supabase.php, pos.php, thawani.php, zatca.php | ✅ Done |
|     | — API routes auto-loader (22 feature route stubs)               | ✅ Done |
|     | — BaseApiController with JSON response helpers                  | ✅ Done |
|     | — AppServiceProvider with strict mode / prevent lazy loading    | ✅ Done |
|     | — `bootstrap/app.php` with API routing + stateful middleware    | ✅ Done |
|     | — `php artisan about` / `config:cache` / `route:list` all pass | ✅ Done |
| 0.2 | **Flutter project created** (`posflutterapp/`)                  | ✅ Done |
|     | — Flutter 3.38.5, Dart 3.10.4                                  | ✅ Done |
|     | — All platforms: Android, iOS, macOS, Windows, Web              | ✅ Done |
|     | — Feature-based structure (31 features, 300+ subdirs)           | ✅ Done |
|     | — 254 Dart models placed in feature folders                     | ✅ Done |
|     | — 162 Dart enums placed in feature/core folders                 | ✅ Done |
|     | — All cross-feature imports fixed (184 import rewrites)         | ✅ Done |
|     | — Flutter packages installed (125 dependencies)                 | ✅ Done |
|     | — Core infrastructure: main.dart, app.dart                      | ✅ Done |
|     | — Riverpod ProviderScope + GoRouter + Material3 theme           | ✅ Done |
|     | — Dio HTTP client with base configuration                       | ✅ Done |
|     | — App constants (Supabase URL, API endpoints, POS defaults)     | ✅ Done |
|     | — Error handling (AppException hierarchy)                       | ✅ Done |
|     | — Utility classes (formatters, validators, logger)              | ✅ Done |
|     | — Localization setup (EN + AR, 32 strings each)                 | ✅ Done |
|     | — analysis_options.yaml with strict lint rules                  | ✅ Done |
|     | — `flutter analyze`: 0 errors, 0 warnings                      | ✅ Done |
| 0.3 | **Supabase project configured**                                 | ✅ Done |
|     | — PostgreSQL database provisioned                               | ✅ Done |
|     | — `database_schema.sql` loaded (255 tables)                     | ✅ Done |
|     | — Laravel `.env` configured with Supabase connection            | ✅ Done |
|     | — SSL mode enabled (`DB_SSLMODE=require`)                       | ✅ Done |
|     | — Storage bucket 'POS' created                                  | ✅ Done |
|     | — Realtime channels configuration                               | ✅ Done |
|     | — Row Level Security (RLS) policies                             | ✅ Done |
| 0.4 | **Version control & CI/CD**                                     | 🟡 Partial |
|     | — GitHub repository initialized (poslaravelapp)                 | ✅ Done |
|     | — GitHub repository initialized (posflutterapp)                 | ✅ Done |
|     | — POS parent folder added to .gitignore                         | ✅ Done |
|     | — GitHub Actions: Laravel tests workflow                        | ⬜ TODO (defer to Phase 1) |
|     | — GitHub Actions: Flutter tests workflow                        | ⬜ TODO (defer to Phase 1) |
|     | — GitHub Actions: lint + static analysis                        | ⬜ TODO (defer to Phase 1) |
|     | — Branch protection rules (main, develop)                       | ⬜ TODO (defer to Phase 1) |
|     | — Environment secrets configured                                | ⬜ TODO (defer to Phase 1) |
| 0.5 | **Shared tooling**                                              | ⏭️ Skipped |
|     | — _Deferred: will add tooling incrementally as features need it_ | ⏭️ Skipped |
| 0.6 | **Sync Laravel migration system with existing Supabase tables** | ✅ Done |
|     |                                                                 |        |
|     | _Since `database_schema.sql` was loaded directly into Supabase,_ |        |
|     | _all 255 tables already exist. Laravel's migration system needs_ |        |
|     | _to be brought into sync so it doesn't try to recreate them._   |        |
|     |                                                                 |        |
|     | — Install Laravel migrations table (`php artisan migrate:install`) | ✅ Done |
|     | — Publish Spatie Permission migrations (`vendor:publish`)       | ✅ Done |
|     | — Publish Spatie Activity Log migrations (`vendor:publish`)     | ✅ Done |
|     | — Publish Spatie Media Library migrations (`vendor:publish`)    | ✅ Done |
|     | — Publish Sanctum migrations (`vendor:publish`)                 | ✅ Done |
|     | — Create Laravel migration files for all 255 tables             | ✅ Done (9 vendor + 44 schema = 53 files, 249 custom tables) |
|     | — Run `php artisan migrate --pretend` to verify SQL matches     | ✅ Done |
|     | — Fake-run all migrations (inserted into migrations table)      | ✅ Done |
|     | — Verify: `php artisan migrate:status` shows all as Ran         | ✅ Done (53/53 Ran, batch 1) |
| 0.7 | **UI Component Library & Design System**                        | ✅ Done |
|     |                                                                 |        |
|     | _Before building individual features, a comprehensive design_   |        |
|     | _system and reusable component library was created for BOTH_    |        |
|     | _Flutter and Laravel to ensure visual consistency._             |        |
|     |                                                                 |        |
|     | **Design Reference:**                                           |        |
|     | — 52 stitch HTML prototypes analyzed (`POS/stitch/`)            | ✅ Done |
|     | — Full design token extraction (~600 lines of specs)            | ✅ Done |
|     |                                                                 |        |
|     | **Flutter Design Tokens** (`lib/core/theme/`):                  |        |
|     | — `app_colors.dart` — Brand (#FD8209, #FFBF0D), semantic,      | ✅ Done |
|     |   surface, text, border, gradient, MaterialColor swatch         |        |
|     | — `app_spacing.dart` — 4px grid scale, SizedBox helpers,       | ✅ Done |
|     |   EdgeInsets presets, AppRadius, AppShadows, AppSizes           |        |
|     | — `app_typography.dart` — Cairo font, 19 named styles,         | ✅ Done |
|     |   textTheme builder for light/dark                              |        |
|     | — `app_theme.dart` — Full Material3 ThemeData (light+dark)     | ✅ Done |
|     |   with 20+ component theme overrides                            |        |
|     | — `theme.dart` — Barrel export                                  | ✅ Done |
|     |                                                                 |        |
|     | **Flutter Widgets** (`lib/core/widgets/`):                      |        |
|     | — `pos_button.dart` — 7 variants, 4 sizes, icon, pill, loading | ✅ Done |
|     | — `pos_app_bar.dart` — Sticky bar, search, notification badge  | ✅ Done |
|     | — `pos_sidebar.dart` — 31 features as nav items, collapsible   | ✅ Done |
|     | — `pos_card.dart` — Base, KPI, Product, ProductList, Settings, | ✅ Done |
|     |   Staff, Subscription cards                                     |        |
|     | — `pos_dialog.dart` — Confirm dialog, bottom sheet, full-screen| ✅ Done |
|     |   dialog, snackbar helpers (success/error)                      |        |
|     | — `pos_input.dart` — TextField, SearchField, Dropdown,         | ✅ Done |
|     |   Toggle, CheckboxTile, NumericCounter                          |        |
|     | — `pos_badge.dart` — Status badge (6 variants), TrendBadge,    | ✅ Done |
|     |   StockDot, CountBadge                                          |        |
|     | — `pos_table.dart` — Styled DataTable + pagination             | ✅ Done |
|     | — `pos_scaffold.dart` — Loading, Shimmer, EmptyState, Avatar,  | ✅ Done |
|     |   Divider, ProgressBar, Section, PageContainer, BottomNav,     |        |
|     |   StepIndicator, ResponsiveScaffold                             |        |
|     | — `widgets.dart` — Barrel export for all widgets               | ✅ Done |
|     |                                                                 |        |
|     | **Flutter Formatters** (`lib/core/utils/formatters.dart`):      |        |
|     | — Currency (SAR 2-decimal, short, compact, raw price)           | ✅ Done |
|     | — Dates (display, ISO, full, medium, short, dateTime)           | ✅ Done |
|     | — Time (24h, 12h, full), relative time (ago, until)             | ✅ Done |
|     | — Numbers (comma, decimal, compact, percent, ordinal)           | ✅ Done |
|     | — File size, Oman phone, duration                               | ✅ Done |
|     |                                                                 |        |
|     | **Flutter Fonts:**                                              |        |
|     | — 6 Cairo TTF weights (200–900) in `assets/fonts/`             | ✅ Done |
|     | — `pubspec.yaml` Cairo font family registered                  | ✅ Done |
|     |                                                                 |        |
|     | **Laravel Filament Theme:**                                     |        |
|     | — `AdminPanelProvider.php` — brand colors (#FD8209), Cairo     | ✅ Done |
|     |   font, nav groups, sidebar, vite theme, global search          |        |
|     | — `resources/css/filament/admin/theme.css` — Filament preset   | ✅ Done |
|     | — `resources/css/filament/admin/tailwind.config.js` — brand    | ✅ Done |
|     |   colors, Cairo font, border-radius tokens                      |        |
|     | — `vite.config.js` — added Filament theme entry                | ✅ Done |
|     | — `resources/css/app.css` — Cairo font, brand CSS vars         | ✅ Done |
|     |                                                                 |        |
|     | **Laravel Support Classes:**                                    |        |
|     | — `app/Support/DesignTokens.php` — Colors, status maps,        | ✅ Done |
|     |   spacing, breakpoints, currency/date format constants          |        |
|     | — `app/Support/Formatters.php` — Currency, dates, numbers,     | ✅ Done |
|     |   percent, fileSize, omanPhone, ordinal (mirrors Flutter)       |        |
|     |                                                                 |        |
|     | **Laravel Blade Components** (`views/components/pos/`):         |        |
|     | — `badge.blade.php` — Status badge (6 variants)                | ✅ Done |
|     | — `card.blade.php` — Base card wrapper                         | ✅ Done |
|     | — `kpi-card.blade.php` — KPI stat card with trend              | ✅ Done |
|     | — `section.blade.php` — Section container with title           | ✅ Done |
|     | — `empty-state.blade.php` — No-data placeholder                | ✅ Done |
|     | — `progress-bar.blade.php` — Styled progress bar               | ✅ Done |

### Pre-built Assets (from prior sessions)

These were generated before project scaffolding and served as input:

| Asset                          | Count / Size   | Status |
|--------------------------------|----------------|--------|
| Feature documentation          | 47 docs        | ✅ Done |
| Database schema SQL            | 255 tables, 3,648 lines | ✅ Done |
| PHP model files                | 254 files      | ✅ Done |
| PHP enum files                 | 162 files      | ✅ Done |
| Dart model files               | 254 files      | ✅ Done |
| Dart enum files                | 162 files      | ✅ Done |
| Laravel project structure doc  | 37 domains     | ✅ Done |
| Flutter project structure doc  | 30 features    | ✅ Done |
| Laravel types reference        | 922 lines, 213 enum fields | ✅ Done |

---

## 4. Phase 1: Foundation Features

> **Goal:** Users can authenticate, set up a store, manage roles, and subscribe. These four features block everything else.

### Feature 1: Auth & User Management

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `users`, `password_resets`, `personal_access_tokens`, `otp_verifications`, `user_devices` | ✅ DONE |
| 2 | Laravel Models — User, OtpVerification, UserDevice (already in `Domain/Auth/Models/`) | ✅ DONE |
| 3 | Laravel Enums — AuthMethod, UserStatus, OtpChannel (already in `Domain/Auth/Enums/`) | ✅ DONE |
| 4 | Service Layer — AuthService, OtpService, TokenService, PinOverrideService | ✅ DONE |
| 5 | API Controllers — AuthController (register, login, loginByPin, profile, password, pin, refresh, logout) | ✅ DONE |
| 6 | Form Requests — RegisterRequest, LoginRequest, UpdateProfileRequest | ✅ DONE |
| 7 | API Resources — UserResource, AuthTokenResource | ✅ DONE |
| 8 | Filament Pages — User management (platform admin) | ⬜ TODO |
| 9 | Laravel Tests — AuthApiTest (16), AuthEdgeCasesApiTest (25), AuthServiceTest (20) | ✅ DONE |
| 10 | API Docs — Update FEATURE_AUTH_USER_MANAGEMENT.md with full specs | ⬜ TODO |
| 11 | Flutter Models — User, AuthToken, AuthResponse in `features/auth/models/` | ✅ DONE |
| 12 | Flutter Enums — UserRole in `features/auth/enums/` | ✅ DONE |
| 13 | Repository — AuthRepository (Dio API calls + secure storage) | ✅ DONE |
| 14 | Providers — authProvider, currentUserProvider, authStateProvider | ✅ DONE |
| 15 | UI Pages — LoginPage, RegisterPage, OtpVerificationPage, ProfilePage | ✅ DONE |
| 16 | Flutter Tests — auth_models_test.dart (model parsing, enum, round-trip) | ✅ DONE |

### Feature 2: Roles & Permissions

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | ✅ DONE |
| 2 | Laravel Models — Role, Permission (Spatie models with customization) | ✅ DONE |
| 3 | Service Layer — RoleService, PermissionService, PinOverrideService | ✅ DONE |
| 4 | API Controllers — RoleController, PermissionController, PinOverrideController | ✅ DONE |
| 5 | Filament Pages — Role/Permission CRUD with Shield integration | ⬜ TODO |
| 6 | Laravel Tests — RolesPermissionsApiTest (15), RolesPermissionsEdgeCasesApiTest (20), RoleServiceTest (15) | ✅ DONE |
| 7 | API Docs — Update FEATURE_ROLES_PERMISSIONS.md | ⬜ TODO |
| 8 | Flutter — Role/Permission models + staff_models_test.dart | ✅ DONE |
| 9 | Flutter Tests — staff_models_test.dart (role/permission parsing, int IDs, nested permissions) | ✅ DONE |

### Feature 3: Store Setup & Business Type Onboarding

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `stores`, `store_settings`, `business_types`, `branches`, `store_working_hours` | ✅ DONE |
| 2 | Laravel Models — Store, StoreSettings, OnboardingProgress, etc. | ✅ DONE |
| 3 | Laravel Enums — BusinessType, OnboardingStep | ✅ DONE |
| 4 | Service Layer — StoreService, OnboardingService | ✅ DONE |
| 5 | API Controllers — StoreController, OnboardingController | ✅ DONE |
| 6 | Filament Pages — Store management, business type config | ⬜ TODO |
| 7 | Laravel Tests — StoreOnboardingApiTest (23), StoreOnboardingEdgeCasesApiTest (25), StoreOnboardingServiceTest (15) | ✅ DONE |
| 8 | API Docs — Update FEATURE_STORE_SETUP.md | ⬜ TODO |
| 9 | Flutter — Onboarding models, StoreSettings, OnboardingProgress | ✅ DONE |
| 10 | Flutter Tests — onboarding_models_test.dart (settings, progress, step enums) | ✅ DONE |

### Feature 4: Subscription & Billing

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `subscription_plans`, `store_subscriptions`, `subscription_invoices`, `subscription_features` | ✅ DONE |
| 2 | Laravel Models — SubscriptionPlan, StoreSubscription, Invoice, etc. | ✅ DONE |
| 3 | Laravel Enums — SubscriptionStatus, BillingCycle, InvoiceStatus | ✅ DONE |
| 4 | Service Layer — SubscriptionService, BillingService, PlanEnforcementService | ✅ DONE |
| 5 | API Controllers — PlanController, SubscriptionController, InvoiceController (routes/api/subscription.php) | ✅ DONE |
| 6 | Filament Pages — Plan management, subscription overview | ⬜ TODO |
| 7 | Laravel Tests — PlanApiTest (20), SubscriptionApiTest (35), InvoiceApiTest (14), PlanEnforcementTest (30), SubscriptionServiceTest (20) | ✅ DONE |
| 8 | API Docs — Update FEATURE_SUBSCRIPTION_BILLING.md | ⬜ TODO |
| 9 | Flutter — Full subscription feature: API service, repository, providers, 3 pages, 5 widgets, routes | ✅ DONE |
| 10 | Flutter Tests — subscription_models_test.dart, subscription_state_test.dart, subscription_pages_test.dart | ✅ DONE |

---

## 5. Phase 2: Core POS Features

> **Goal:** The actual POS functionality — products, terminal UI, orders, payments, customers, barcodes.

### Feature 5: Product Catalog
**Depends on:** #3 (Store Setup)

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `products`, `product_variants`, `categories`, `product_images`, `product_tags`, `product_units` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/Catalog/Models/` | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/Catalog/Enums/` | ✅ Placed |
| 4 | Service Layer — ProductService, CategoryService, VariantService, ImportService | ⬜ TODO |
| 5 | API Controllers — ProductController, CategoryController, VariantController | ⬜ TODO |
| 6 | Filament Pages — Product CRUD, bulk import, category tree | ⬜ TODO |
| 7 | Laravel Tests — CRUD, search, filtering, variant logic | ⬜ TODO |
| 8 | API Docs — Update FEATURE_PRODUCT_CATALOG.md | ⬜ TODO |
| 9 | Flutter — Product list, product detail, category browser, search | ⬜ TODO |
| 10 | Flutter Tests — Product listing and detail tests | ⬜ TODO |

### Feature 6: Inventory Management
**Depends on:** #5 (Product Catalog)

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `inventory_items`, `stock_movements`, `stock_alerts`, `stock_counts`, `warehouses` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/Inventory/Models/` | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/Inventory/Enums/` | ✅ Placed |
| 4 | Service Layer — InventoryService, StockMovementService, AlertService | ⬜ TODO |
| 5 | API + Filament + Tests + Docs | ⬜ TODO |
| 6 | Flutter — Stock overview, movement history, alerts, stock count | ⬜ TODO |
| 7 | Flutter Tests | ⬜ TODO |

### Feature 7: POS Terminal & Interface
**Depends on:** #5, #6

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `pos_sessions`, `pos_terminals`, `cash_drawers`, `pos_layouts`, `quick_access_items` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/POS/Models/` | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/POS/Enums/` | ✅ Placed |
| 4 | Service Layer — POSSessionService, CashDrawerService, TerminalService | ⬜ TODO |
| 5 | API + Filament + Tests + Docs | ⬜ TODO |
| 6 | Flutter — POS terminal screen, product grid, cart, numpad, session mgmt | ⬜ TODO |
| 7 | Flutter Tests | ⬜ TODO |

### Feature 8: Order Management
**Depends on:** #7 (POS Terminal)

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `orders`, `order_items`, `order_status_history`, `returns`, `return_items` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/Order/Models/` | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/Order/Enums/` | ✅ Placed |
| 4 | Service Layer — OrderService, ReturnService, OrderStatusService | ⬜ TODO |
| 5 | API + Filament + Tests + Docs | ⬜ TODO |
| 6 | Flutter — Order history, order detail, returns, receipt | ⬜ TODO |
| 7 | Flutter Tests | ⬜ TODO |

### Feature 9: Payments & Finance
**Depends on:** #8 (Order Management)

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `payments`, `payment_methods`, `refunds`, `cash_movements`, `daily_settlements` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/Payment/Models/` | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/Payment/Enums/` | ✅ Placed |
| 4 | Service Layer — PaymentService, RefundService, SettlementService | ⬜ TODO |
| 5 | API + Filament + Tests + Docs | ⬜ TODO |
| 6 | Flutter — Payment screen, split payments, receipt printing, settlement | ⬜ TODO |
| 7 | Flutter Tests | ⬜ TODO |

### Feature 10: Customer Management
**Depends on:** #3 (Store Setup)

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `customers`, `customer_addresses`, `loyalty_points`, `customer_groups`, `customer_notes` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/Customer/Models/` | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/Customer/Enums/` | ✅ Placed |
| 4 | Service Layer — CustomerService, LoyaltyService, CustomerGroupService | ⬜ TODO |
| 5 | API + Filament + Tests + Docs | ⬜ TODO |
| 6 | Flutter — Customer list, customer detail, loyalty, quick-add at POS | ⬜ TODO |
| 7 | Flutter Tests | ⬜ TODO |

### Feature 11: Barcode & Label Printing
**Depends on:** #5 (Product Catalog)

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `barcode_configs`, `label_templates`, `print_jobs` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/Label/Models/` | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/Label/Enums/` | ✅ Placed |
| 4 | Service Layer — BarcodeService, LabelService, PrintService | ⬜ TODO |
| 5 | API + Tests + Docs | ⬜ TODO |
| 6 | Flutter — Barcode scanner, label designer, print preview | ⬜ TODO |
| 7 | Flutter Tests | ⬜ TODO |

---

## 6. Phase 3: Business Features

> **Goal:** Value-add features that enhance the POS — promotions, reports, staff, notifications, delivery, accounting.

### Feature 12: Promotions & Coupons
**Depends on:** #5, #8

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `promotions`, `coupons`, `coupon_usages`, `bundle_deals`, `flash_sales` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Service Layer — PromotionEngine, CouponValidator, DiscountCalculator | ⬜ TODO |
| 4 | API + Filament + Tests + Docs | ⬜ TODO |
| 5 | Flutter — Promotion list, coupon entry at POS, discount display | ⬜ TODO |
| 6 | Flutter Tests | ⬜ TODO |

### Feature 13: Staff Management
**Depends on:** #2, #3

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `staff`, `staff_schedules`, `attendance_logs`, `commissions` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Service Layer + API + Tests + Docs | ⬜ TODO |
| 4 | Flutter + Tests | ⬜ TODO |

### Feature 14: Reports & Analytics
**Depends on:** #8, #9

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `report_configs`, `report_schedules`, `daily_summaries`, `analytics_snapshots` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Service Layer — ReportGenerator, AnalyticsAggregator, ExportService | ⬜ TODO |
| 4 | API + Filament + Tests + Docs | ⬜ TODO |
| 5 | Flutter — Dashboard charts (fl_chart), report viewer, PDF export | ⬜ TODO |
| 6 | Flutter Tests | ⬜ TODO |

### Feature 15: Notifications
**Depends on:** #1

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `notifications`, `notification_templates`, `notification_logs`, `fcm_tokens` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Service Layer — NotificationService, FCMService, TemplateRenderer | ⬜ TODO |
| 4 | API + Tests + Docs | ⬜ TODO |
| 5 | Flutter — Notification center, push handling, preferences | ⬜ TODO |
| 6 | Flutter Tests | ⬜ TODO |

### Feature 16: Accounting Integration
**Depends on:** #9

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `journal_entries`, `chart_of_accounts`, `fiscal_periods`, `tax_reports` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Service Layer + API + Tests + Docs | ⬜ TODO |
| 4 | Flutter + Tests | ⬜ TODO |

### Feature 17: Delivery Integrations
**Depends on:** #8

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `delivery_orders`, `delivery_providers`, `driver_assignments`, `tracking_events` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Service Layer + API + Tests + Docs | ⬜ TODO |
| 4 | Flutter + Tests | ⬜ TODO |

### Feature 18: Thawani Gateway Integration
**Depends on:** #9

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `thawani_configs`, `thawani_transactions`, `thawani_settlements` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Service Layer + API + Tests + Docs | ⬜ TODO |
| 4 | Flutter + Tests | ⬜ TODO |

### Feature 31: Industry-Specific Workflows
**Depends on:** #5 (Product Catalog), #7 (POS Terminal)
**Doc:** `provider/provider_features/industry_specific_workflows_feature.md`

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `industry_configs`, `pharmacy_prescriptions`, `jewelry_appraisals`, `bakery_recipes`, `restaurant_tables`, `florist_arrangements`, `electronics_warranties`, `device_imei_records` | ⬜ TODO |
| 2 | Laravel Models — Already in `Domain/Industry*/Models/` (Pharmacy, Jewelry, Electronics, Florist, Bakery, Restaurant) | ✅ Placed |
| 3 | Laravel Enums — Already in `Domain/Industry*/Enums/` | ✅ Placed |
| 4 | Service Layer — IndustryConfigService, per-vertical workflow services (PharmacyService, JewelryService, etc.) | ⬜ TODO |
| 5 | API Controllers — Industry-specific endpoints per vertical | ⬜ TODO |
| 6 | Filament Pages — Industry template config, workflow rules | ⬜ TODO |
| 7 | Laravel Tests — Per-vertical workflow tests | ⬜ TODO |
| 8 | API Docs — Update FEATURE_INDUSTRY_SPECIFIC_WORKFLOWS.md | ⬜ TODO |
| 9 | Flutter Models — Already in `features/industry_*/models/` (6 verticals) | ✅ Placed |
| 10 | Flutter Enums — Already in `features/industry_*/enums/` | ✅ Placed |
| 11 | Repository + Providers — Per-vertical repositories and providers | ⬜ TODO |
| 12 | UI Pages — Per-vertical POS customizations, workflow screens | ⬜ TODO |
| 13 | Flutter Tests | ⬜ TODO |

**Supported Verticals:**
- 🛒 Supermarket — weight-based products, scale integration, age verification
- 🏬 Hypermarket — supplier management, bulk pricing, branches, pallet tracking
- 🏥 Pharmacy — prescription tracking, controlled substance logs, drug interaction checks
- 💎 Jewelry — appraisals, custom orders, stone tracking, certification
- 📱 Electronics — IMEI tracking, warranty management, repair tickets
- 💐 Florist — arrangement builder, delivery scheduling, freshness tracking
- 🍰 Bakery — recipe management, production planning, ingredient scaling
- 🍽️ Restaurant — table management, KDS integration, course firing, split checks


---

## 7. Phase 4: Platform Admin Features

> **Goal:** Admin panel features for managing providers, analytics, support, and system configuration. Runs as a **parallel track** after Feature #4 is done.

### P1: Provider Management
**Depends on:** #1, #3

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `providers`, `provider_stores`, `provider_settings` | ⬜ TODO |
| 2 | Filament Pages — Provider CRUD, store assignment, status management | ⬜ TODO |
| 3 | Tests + Docs | ⬜ TODO |

### P2: Platform Roles
**Depends on:** #1, #2

| Step | Task | Status |
|------|------|--------|
| 1 | Shield configuration — Platform-specific roles and permissions | ⬜ TODO |
| 2 | Filament Pages — Platform role CRUD, assignment UI | ⬜ TODO |
| 3 | Tests + Docs | ⬜ TODO |

### P3: Package & Subscription Management
**Depends on:** #4

| Step | Task | Status |
|------|------|--------|
| 1 | Filament Pages — Plan builder, feature toggles, pricing tiers | ⬜ TODO |
| 2 | Tests + Docs | ⬜ TODO |

### P4: User Management (Platform)
**Depends on:** #1, #2

| Step | Task | Status |
|------|------|--------|
| 1 | Filament Pages — User search, impersonation, ban/unban | ⬜ TODO |
| 2 | Tests + Docs | ⬜ TODO |

### P5: Billing & Finance (Platform)
**Depends on:** #4, #9

| Step | Task | Status |
|------|------|--------|
| 1 | Filament Pages — Revenue dashboard, invoice management, payout tracking | ⬜ TODO |
| 2 | Tests + Docs | ⬜ TODO |

### P6: Analytics & Reporting (Platform)
**Depends on:** #14

| Step | Task | Status |
|------|------|--------|
| 1 | Filament Pages — Platform-wide metrics, provider comparisons, growth charts | ⬜ TODO |
| 2 | Tests + Docs | ⬜ TODO |

### P7: Support Tickets
**Depends on:** #1

| Step | Task | Status |
|------|------|--------|
| 1 | DB Migration — `support_tickets`, `ticket_messages`, `ticket_categories` | ⬜ TODO |
| 2 | Laravel — Models ✅ Placed, Enums ✅ Placed | ✅ Placed |
| 3 | Filament Pages + API + Tests + Docs | ⬜ TODO |
| 4 | Flutter — Ticket submission, conversation UI | ⬜ TODO |

### P8: System Configuration
**Depends on:** #1

| Step | Task | Status |
|------|------|--------|
| 1 | Filament Pages — Global settings, feature flags, maintenance mode | ⬜ TODO |
| 2 | Tests + Docs | ⬜ TODO |

### P9–P17: Remaining Platform Features

| #   | Feature                       | Depends On    | Status |
|-----|-------------------------------|---------------|--------|
| P9  | Notification Templates        | #15           | ⬜ TODO |
| P10 | POS Layout Management         | #7, #27       | ⬜ TODO |
| P11 | Content & Onboarding          | #3            | ⬜ TODO |
| P12 | Platform Announcements        | #15           | ⬜ TODO |
| P13 | Delivery Platform Management  | #17           | ⬜ TODO |
| P14 | App Update Management         | #28           | ⬜ TODO |
| P15 | Security & Audit              | #24           | ⬜ TODO |
| P16 | Infrastructure & Operations   | all           | ⬜ TODO |
| P17 | Provider Roles & Permissions  | #2, P1        | ⬜ TODO |

---

## 8. Phase 5: Polish & Scale

> **Goal:** Production-readiness — offline sync, hardware support, compliance, localization, accessibility.

| #   | Feature                     | Depends On | Status |
|-----|-----------------------------|------------|--------|
| 19  | Store Owner Web Dashboard   | #3, #14    | ⬜ TODO |
| 20  | ZATCA Compliance            | #9         | ⬜ TODO |
| 21  | Offline/Online Sync         | #7         | ⬜ TODO |
| 22  | Hardware Support            | #7, #11    | ⬜ TODO |
| 23  | Language & Localization     | #1         | ⬜ TODO |
| 24  | Security (Provider)         | #1, #2     | ⬜ TODO |
| 25  | Backup & Recovery           | #3         | ⬜ TODO |
| 26  | Mobile Companion App        | #7         | ⬜ TODO |
| 27  | POS Customization           | #7         | ⬜ TODO |
| 28  | Auto Updates                | #1         | ⬜ TODO |
| 29  | Accessibility               | all        | ⬜ TODO |
| 30  | Nice-to-Have Features       | varies     | ⬜ TODO |
| 31  | Industry-Specific Workflows | #5, #7     | ⬜ TODO |

---

## 9. Per-Feature Workflow (Inner Loop)

> This is the exact sequence followed for **every** feature. No steps are skipped.

Note: each feature you can find it's relative details at POS/platform/platform_features or POS/provider/provider_features depending on the feature type (platform vs provider).

```
┌─────────────────────────────────────────────────────────────────────┐
│                    FEATURE: {Feature Name}                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─── BACKEND (Laravel) ──────────────────────────────────────┐    │
│  │                                                             │    │
|  |  Step 0   Review platform_features or provider_features     │    │ 
│  │  Step 1   DB Migration                                      │    │
│  │           Create ONLY this feature's tables.                │    │
│  │           Run: php artisan migrate                          │    │
│  │           Verify: tables exist in Supabase                  │    │
│  │                                                             │    │
│  │  Step 2   Laravel Models                                    │    │
│  │           Already in app/Domain/{Feature}/Models/           │    │
│  │           Review: relationships, casts, fillable, scopes    │    │
│  │           Add: factory, seeder                              │    │
│  │                                                             │    │
│  │  Step 3   Laravel Enums                                     │    │
│  │           Already in app/Domain/{Feature}/Enums/            │    │
│  │           Review: all values match DB column types           │    │
│  │                                                             │    │
│  │  Step 4   Service Layer                                     │    │
│  │           Create: Services (business logic)                 │    │
│  │           Create: Actions (single-purpose operations)       │    │
│  │           Create: DTOs (data transfer objects)              │    │
│  │           Create: Events + Listeners (side effects)         │    │
│  │           Create: Jobs (async work)                         │    │
│  │           Create: Policies (authorization)                  │    │
│  │           Create: Observers (model lifecycle hooks)         │    │
│  │           ⚠ MANDATORY: Every Policy must check auth user    │    │
│  │           ⚠ MANDATORY: Register Spatie permissions for      │    │
│  │             every action (view, create, update, delete, etc)│    │
│  │           ⚠ MANDATORY: Check provider's active subscription │    │
│  │             plan includes this feature before allowing use  │    │
│  │                                                             │    │
│  │  Step 5   API Controllers                                   │    │
│  │           Create: Controller in Controllers/Api/            │    │
│  │           Create: Form Requests (validation)                │    │
│  │           Create: API Resources (response transformation)   │    │
│  │           Register: Routes in routes/api/{feature}.php      │    │
│  │           ⚠ MANDATORY: Wrap routes in auth:sanctum middleware│    │
│  │           ⚠ MANDATORY: Add $this->authorize() or Policy     │    │
│  │             gate check in every controller method           │    │
│  │           ⚠ MANDATORY: Scope queries to auth user's store   │    │
│  │             (never expose another store's data)             │    │
│  │           ⚠ MANDATORY: Check store's subscription plan      │    │
│  │             supports this feature (return 403 if not)       │    │
│  │           Test: Every endpoint via Postman / curl            │    │
│  │                                                             │    │
│  │  Step 6   Filament Pages (if admin-facing)                  │    │
│  │           Create: Resource pages (List, Create, Edit, View) │    │
│  │           Create: Custom pages (dashboards, wizards)        │    │
│  │           Create: Widgets (stats, charts)                   │    │
│  │           Create: Relation managers                         │    │
│  │           ⚠ MANDATORY: Apply Filament Shield permissions    │    │
│  │             on every Resource (HasShieldPermissions trait)   │    │
│  │           ⚠ MANDATORY: Scope Filament queries to auth       │    │
│  │             user's tenant / store context                   │    │
│  │           ⚠ MANDATORY: Hide/disable Filament nav items &   │    │
│  │             resources not included in provider's package    │    │
│  │                                                             │    │
│  │  Step 7   Laravel Tests                                     │    │
│  │           Write: Feature tests (every API endpoint)         │    │
│  │           Write: Unit tests (services, actions, DTOs)       │    │
│  │           Run: php artisan test --filter={Feature}          │    │
│  │           Verify: ALL tests pass                            │    │
│  │           Target: ≥90% coverage for this feature            │    │
│  │                                                             │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                     │
│  ┌─── DOCUMENTATION ──────────────────────────────────────────┐    │
│  │                                                             │    │
│  │  Step 8   Update Feature Doc                                │    │
│  │           File: POS/docs/FEATURE_{NAME}.md                  │    │
│  │           Add: Full API endpoint specifications             │    │
│  │           Add: Request / response samples (JSON)            │    │
│  │           Add: Error codes and handling                     │    │
│  │           Add: Authentication requirements                  │    │
│  │           Add: Rate limiting info                           │    │
│  │           Add: Pagination details                           │    │
│  │                                                             │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                     │
│  ┌─── FRONTEND (Flutter) ─────────────────────────────────────┐    │
│  │                                                             │    │
│  │  Step 9   Flutter Models                                    │    │
│  │           Already in lib/features/{feature}/models/         │    │
│  │           Review: fromJson, toJson, copyWith                │    │
│  │           Verify: matches API response structure             │    │
│  │                                                             │    │
│  │  Step 10  Flutter Enums                                     │    │
│  │           Already in lib/features/{feature}/enums/          │    │
│  │           Verify: matches Laravel enum values                │    │
│  │                                                             │    │
│  │  Step 11  Repository Layer                                  │    │
│  │           Create: Remote data source (Dio API calls)        │    │
│  │           Create: Local data source (Drift tables + DAOs)   │    │
│  │           Create: Repository (orchestrates remote + local)  │    │
│  │                                                             │    │
│  │  Step 12  State Management                                  │    │
│  │           Create: Riverpod providers                        │    │
│  │           Create: StateNotifiers for complex state          │    │
│  │           Create: FutureProviders for async data            │    │
│  │           ⚠ MANDATORY: Create permission-aware providers    │    │
│  │             that check user roles before exposing data      │    │
│  │           ⚠ MANDATORY: Check subscription plan includes     │    │
│  │             this feature — show upgrade prompt if not       │    │
│  │                                                             │    │
│  │  Step 13  UI Pages                                          │    │
│  │           Create: Page widgets (screens)                    │    │
│  │           Create: Feature-specific widgets                  │    │
│  │           Register: Routes in GoRouter                      │    │
│  │           ⚠ MANDATORY: Add GoRouter redirect guard —        │    │
│  │             unauthenticated users → login page              │    │
│  │           ⚠ MANDATORY: Hide/disable UI elements the        │    │
│  │             current user lacks permission for               │    │
│  │           ⚠ MANDATORY: Gate feature screens behind          │    │
│  │             subscription check — redirect to upgrade page   │    │
│  │             if provider's package doesn't include feature   │    │
│  │           Test: Visual review on all target platforms        │    │
│  │                                                             │    │
│  │  Step 14  Flutter Tests                                     │    │
│  │           Write: Widget tests (UI components)               │    │
│  │           Write: Unit tests (providers, repositories)       │    │
│  │           Write: Integration tests (full flows)             │    │
│  │           Run: flutter test                                 │    │
│  │           Verify: ALL tests pass                            │    │
│  │                                                             │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                     │
│  ┌─── QUALITY GATE ───────────────────────────────────────────┐    │
│  │  □ All Laravel tests pass                                   │    │
│  │  □ All Flutter tests pass                                   │    │
│  │  □ flutter analyze: 0 errors, 0 warnings                   │    │
│  │  □ API docs updated with working examples                   │    │
│  │  □ Feature demo-able end-to-end                             │    │
│  │  □ Code reviewed and committed                              │    │
│  │  □ Auth: unauthenticated requests return 401                │    │
│  │  □ Auth: unauthorized requests return 403                   │    │
│  │  □ Auth: all queries scoped to auth user's store            │    │
│  │  □ Auth: Spatie permissions registered & seeded             │    │
│  │  □ Auth: Filament Shield applied to every Resource          │    │
│  │  □ Auth: Flutter routes redirect if not logged in           │    │
│  │  □ Auth: Flutter UI hides forbidden actions                 │    │
│  │  □ Subscription: API returns 403 if plan lacks feature      │    │
│  │  □ Subscription: Flutter shows upgrade prompt if blocked    │    │
│  │  □ Subscription: Filament hides nav items not in plan       │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                     │
│  ✅ FEATURE COMPLETE — move to next feature                        │
└─────────────────────────────────────────────────────────────────────┘
```

### Step Details and Standards

#### Step 0: Planning & Design
Read the feature file at `POS/provider/provider_features/{feature_name}_feature.md` or `POS/platform/platform_features/{feature_name}_feature.md` for detailed requirements, API specs, and data models before starting.

Please Note that the feature can have slightly different name and can be found in the respective folder under provider_features or platform_features depending on the feature type.

#### Step 1: DB Migration

```bash
# Generate migration
php artisan make:migration create_{table_name}_table

# Reference: database_schema.sql for exact column definitions
# Include: indexes, foreign keys, constraints, defaults
# Include: created_at, updated_at, deleted_at (if soft-deletable)
# Include: UUID primary keys (use $table->uuid('id')->primary())
```

**Standards:**
- One migration per logical table group (e.g. `create_products_and_variants_tables`)
- Always reference `database_schema.sql` as the source of truth
- Foreign keys must reference existing tables (respect dependency order)
- Use `$table->uuid()` for all primary keys
- Add indexes for columns used in WHERE, ORDER BY, JOIN

#### Step 4: Service Layer

```
Domain/{Feature}/
├── Services/
│   └── {Feature}Service.php       ← Main business logic
├── Actions/
│   ├── Create{Entity}Action.php   ← Single-purpose create
│   ├── Update{Entity}Action.php   ← Single-purpose update
│   └── Delete{Entity}Action.php   ← Single-purpose delete
├── DTOs/
│   ├── Create{Entity}Data.php     ← Validated input data
│   └── Update{Entity}Data.php     ← Validated input data
├── Events/
│   └── {Entity}Created.php        ← Domain event
├── Listeners/
│   └── Send{Entity}Notification.php
├── Jobs/
│   └── Process{Entity}Job.php     ← Async work
├── Policies/
│   └── {Entity}Policy.php         ← Authorization
└── Observers/
    └── {Entity}Observer.php       ← Model lifecycle
```

**Standards:**
- Services contain business logic, NOT controllers
- Actions are single-purpose (one public `execute()` method)
- DTOs are immutable data containers (use `readonly` properties)
- Events fire after state changes (past tense: `OrderCreated`)
- Policies check authorization (return `bool` or `Response`)

**Auth & Permissions Requirements (MANDATORY for every feature):**
- Create a `{Entity}Policy.php` for every primary model in the feature
- Every Policy method receives the authenticated `User $user` as first argument
- Register Spatie permissions using naming convention: `{feature}_{action}` (e.g. `product_view`, `product_create`, `product_update`, `product_delete`, `product_export`)
- Create a permission seeder: `{Feature}PermissionSeeder.php` that registers all permissions and assigns defaults to roles
- Policy methods must check `$user->hasPermissionTo('{feature}_{action}')` or use `$user->can()`
- Services must accept the authenticated user and validate ownership / store scope:
  ```php
  // ALWAYS scope queries to the authenticated user's store
  $products = Product::where('store_id', $user->current_store_id)->get();
  // NEVER return data from other stores
  ```
- Global scopes recommended for multi-tenant models:
  ```php
  // In the model boot method or via an Observer
  static::addGlobalScope('store', function ($query) {
      if (auth()->check()) {
          $query->where('store_id', auth()->user()->current_store_id);
      }
  });
  ```

**Subscription / Package Feature Check (MANDATORY for provider-facing features):**
- Before executing any feature logic, verify the provider's active subscription plan includes the feature:
  ```php
  // In a middleware or at the top of the service method
  $store = $user->currentStore;
  if (!$store->subscription->plan->hasFeature('product_catalog')) {
      abort(403, 'Your current plan does not include this feature. Please upgrade.');
  }
  ```
- Create a reusable middleware `EnsureFeatureEnabled` to gate entire route groups:
  ```php
  // routes/api/catalog.php
  Route::middleware(['auth:sanctum', 'feature:product_catalog'])->group(function () {
      Route::apiResource('products', ProductController::class);
  });
  ```
- Each feature must declare its `feature_key` (e.g. `product_catalog`, `inventory`, `reports_analytics`, `delivery`) that maps to `subscription_features.feature_key` in the DB
- The `subscription_plans` ↔ `subscription_features` relationship determines which features each plan unlocks
- Foundation features (#1 Auth, #2 Roles, #3 Store Setup, #4 Subscription) are always available regardless of plan — they are not gated

#### Step 5: API Controllers

```php
// Standard controller structure
class ProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        // Paginated list with filtering
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        // Validate → Action → Resource → Response
    }

    public function show(Product $product): JsonResponse
    {
        // Route model binding → Resource → Response
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        // Validate → Action → Resource → Response
    }

    public function destroy(Product $product): JsonResponse
    {
        // Authorize → Delete → Response
    }
}
```

**Standards:**
- Controllers are thin — delegate to services/actions
- Always use Form Requests for validation
- Always use API Resources for response transformation
- Always return consistent JSON structure via `BaseApiController` helpers
- RESTful naming: index, store, show, update, destroy
- API versioning: all routes under `/api/v2/`

**Auth & Permissions Requirements (MANDATORY for every API route):**
- ALL feature routes MUST be wrapped in `auth:sanctum` middleware:
  ```php
  Route::middleware('auth:sanctum')->group(function () {
      Route::apiResource('products', ProductController::class);
  });
  ```
- Every controller method MUST authorize the action before proceeding:
  ```php
  public function index(Request $request): JsonResponse
  {
      $this->authorize('viewAny', Product::class);
      // ... scoped query
  }

  public function store(CreateProductRequest $request): JsonResponse
  {
      $this->authorize('create', Product::class);
      // ... create logic
  }

  public function show(Product $product): JsonResponse
  {
      $this->authorize('view', $product);
      // ... return resource
  }
  ```
- Queries MUST be scoped to the authenticated user's store — never return another store's data
- Use `$request->user()` (not `auth()->user()`) in controllers for testability
- Form Requests should implement `authorize()` method returning `bool` for additional validation
- Add Spatie permission middleware on routes where fine-grained control is needed:
  ```php
  Route::middleware(['auth:sanctum', 'permission:product_export'])
      ->get('products/export', [ProductController::class, 'export']);
  ```

#### Step 7: Laravel Tests

```
tests/
├── Feature/
│   └── Api/
│       └── {Feature}/
│           ├── {Entity}IndexTest.php
│           ├── {Entity}StoreTest.php
│           ├── {Entity}ShowTest.php
│           ├── {Entity}UpdateTest.php
│           ├── {Entity}DestroyTest.php
│           └── {Entity}AuthorizationTest.php
└── Unit/
    └── Domain/
        └── {Feature}/
            ├── Services/
            │   └── {Feature}ServiceTest.php
            ├── Actions/
            │   └── Create{Entity}ActionTest.php
            └── DTOs/
                └── Create{Entity}DataTest.php
```

**Standards:**
- Feature tests: test every endpoint (happy path + error cases)
- Unit tests: test services, actions, DTOs in isolation
- Use factories for test data
- Test authentication (unauthenticated → 401)
- Test authorization (unauthorized → 403)
- Test validation (invalid input → 422 with error messages)
- Target: ≥ 90% code coverage per feature

**Auth & Permissions Test Cases (MANDATORY for every feature):**
- Every endpoint must have these auth test cases at minimum:
  ```php
  // 1. Unauthenticated → 401
  public function test_unauthenticated_user_gets_401(): void
  {
      $this->getJson('/api/v2/products')->assertUnauthorized();
  }

  // 2. Unauthorized (wrong role/permission) → 403
  public function test_user_without_permission_gets_403(): void
  {
      $user = User::factory()->create(); // no permissions
      $this->actingAs($user)->getJson('/api/v2/products')->assertForbidden();
  }

  // 3. Cross-store access denied → 403 or 404
  public function test_user_cannot_access_other_store_data(): void
  {
      $otherStore = Store::factory()->create();
      $product = Product::factory()->for($otherStore)->create();
      $this->actingAs($this->user)
           ->getJson("/api/v2/products/{$product->id}")
           ->assertNotFound(); // scoped query returns 404
  }

  // 4. Authorized user → 200
  public function test_authorized_user_can_access(): void
  {
      $this->user->givePermissionTo('product_view');
      $this->actingAs($this->user)->getJson('/api/v2/products')->assertOk();
  }
  ```
- Create a dedicated `{Entity}AuthorizationTest.php` file per feature
- Test that Filament Resources respect Shield permissions (admin can't access resources without assigned permissions)

#### Step 11: Flutter Repository Layer

```dart
// Remote data source
class ProductRemoteDataSource {
  final Dio dio;

  Future<List<ProductModel>> getProducts({int page = 1}) async {
    final response = await dio.get('/api/v2/products', queryParameters: {'page': page});
    return (response.data['data'] as List).map((e) => ProductModel.fromJson(e)).toList();
  }
}

// Repository (orchestrates remote + local)
class ProductRepository {
  final ProductRemoteDataSource remote;
  final ProductLocalDataSource local; // Drift

  Future<List<ProductModel>> getProducts({bool forceRefresh = false}) async {
    if (forceRefresh || !await local.hasData()) {
      final products = await remote.getProducts();
      await local.cacheProducts(products);
      return products;
    }
    return local.getProducts();
  }
}
```

#### Step 12: State Management

```dart
// Riverpod providers
final productRepositoryProvider = Provider<ProductRepository>((ref) {
  return ProductRepository(
    remote: ProductRemoteDataSource(ref.read(dioProvider)),
    local: ProductLocalDataSource(ref.read(driftDatabaseProvider)),
  );
});

final productsProvider = FutureProvider.autoDispose<List<ProductModel>>((ref) {
  return ref.read(productRepositoryProvider).getProducts();
});

final productDetailProvider = FutureProvider.autoDispose.family<ProductModel, String>((ref, id) {
  return ref.read(productRepositoryProvider).getProduct(id);
});
```

---

## 10. Feature Build Order — Provider Track

The provider track covers all store-owner-facing features (31 features).

```
 #  Feature                          Depends On           Priority
────────────────────────────────────────────────────────────────────
 1  Auth & Users                     nothing              🔴 Critical
 2  Roles & Permissions              #1                   🔴 Critical
 3  Store Setup / Onboarding         #1, #2               🔴 Critical
 4  Subscription & Billing           #1, #3               🔴 Critical
 5  Product Catalog                  #3                   🔴 Critical
 6  Inventory Management             #5                   🔴 Critical
 7  POS Terminal / Interface         #5, #6               🔴 Critical
 8  Order Management                 #7                   🔴 Critical
 9  Payments & Finance               #8                   🔴 Critical
────────────────────────────────────────────────────────────────────
10  Customer Management              #3                   🟡 High
11  Barcode & Label Printing         #5                   🟡 High
12  Promotions & Coupons             #5, #8               🟡 High
13  Staff Management                 #2, #3               🟡 High
14  Reports & Analytics              #8, #9               🟡 High
15  Notifications                    #1                   🟡 High
────────────────────────────────────────────────────────────────────
16  Accounting Integration           #9                   🟢 Medium
17  Delivery Integrations            #8                   🟢 Medium
18  Thawani Gateway Integration      #9                   🟢 Medium
19  Store Owner Web Dashboard        #3, #14              🟢 Medium
20  ZATCA Compliance                 #9                   🟢 Medium
21  Offline/Online Sync              #7                   🟢 Medium
22  Hardware Support                 #7, #11              🟢 Medium
23  Language & Localization          #1                   🟢 Medium
24  Security (Provider)              #1, #2               🟢 Medium
25  Backup & Recovery                #3                   🟢 Medium
31  Industry-Specific Workflows      #5, #7               🟢 Medium
────────────────────────────────────────────────────────────────────
26  Mobile Companion App             #7                   🔵 Low
27  POS Customization                #7                   🔵 Low
28  Auto Updates                     #1                   🔵 Low
29  Accessibility                    all                  🔵 Low
30  Nice-to-Have                     varies               🔵 Low
```

### Priority Legend

| Icon | Priority     | Meaning                                                |
|------|--------------|--------------------------------------------------------|
| 🔴   | **Critical** | Must be built first. Everything else depends on these. |
| 🟡   | **High**     | Core business value. Build immediately after critical. |
| 🟢   | **Medium**   | Important but can wait. Build in Phase 3–4.            |
| 🔵   | **Low**      | Nice-to-have. Build in Phase 5 or post-launch.         |

### Parallelization Opportunities

Some features can be built in parallel if you have multiple developers:

```
Track A (Core POS):     1 → 2 → 3 → 5 → 6 → 7 → 8 → 9
Track B (Customers):    ──────── 3 → 10 ──────────────────
Track C (Staff):        ──── 2 → 3 → 13 ──────────────────
Track D (Subscription): ──────── 3 → 4 ───────────────────
Track E (Notifications): 1 → 15 ──────────────────────────
```

---

## 11. Feature Build Order — Platform Admin Track

The platform track runs in **parallel** with the provider track, starting after Feature #4 is complete.

```
 #   Feature                       Depends On    Priority
─────────────────────────────────────────────────────────────
 P1  Provider Management           #1, #3        🔴 Critical
 P2  Platform Roles                #1, #2        🔴 Critical
 P3  Package & Subscription Mgmt   #4            🔴 Critical
─────────────────────────────────────────────────────────────
 P4  User Management               #1, #2        🟡 High
 P5  Billing & Finance             #4, #9        🟡 High
 P6  Analytics & Reporting         #14           🟡 High
 P7  Support Tickets               #1            🟡 High
 P8  System Configuration          #1            🟡 High
 P17 Provider Roles & Permissions  #2, P1        🟡 High
─────────────────────────────────────────────────────────────
 P9  Notification Templates        #15           🟢 Medium
 P10 POS Layout Management         #7, #27       🟢 Medium
 P11 Content & Onboarding          #3            🟢 Medium
 P12 Platform Announcements        #15           🟢 Medium
 P13 Delivery Platform Management  #17           🟢 Medium
 P15 Security & Audit              #24           🟢 Medium
 P16 Infrastructure & Operations   all           🟢 Medium
─────────────────────────────────────────────────────────────
 P14 App Update Management         #28           🔵 Low
```

### Platform Track Timeline

```
Provider Track:  [#1] [#2] [#3] [#4] [#5] [#6] [#7] [#8] [#9] ...
                  │    │    │    │
Platform Track:   │    │    │    └──→ [P3] → [P5]
                  │    │    └──────→ [P1] → [P17]
                  │    └───────→ [P2] → [P4]
                  └────────────→ [P7] [P8]
```

---

## 12. Dependency Graph

```
                    ┌──────────────┐
                    │   #1 Auth    │
                    │   & Users    │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┬──────────┐
              │            │            │          │
       ┌──────▼──────┐    │     ┌──────▼──┐  ┌───▼────┐
       │  #2 Roles & │    │     │ #15     │  │ P7     │
       │  Permissions │    │     │ Notify  │  │ Support│
       └──────┬───────┘    │     └────┬────┘  └────────┘
              │            │          │
       ┌──────▼────────────▼──┐   ┌──▼───┐
       │  #3 Store Setup /    │   │ P9   │
       │  Onboarding          │   │ P12  │
       └──────┬───────────────┘   └──────┘
              │
    ┌─────────┼──────────┬──────────┬──────────┐
    │         │          │          │          │
┌───▼───┐ ┌──▼───┐  ┌───▼───┐ ┌───▼───┐  ┌──▼──┐
│ #4    │ │ #5   │  │ #10   │ │ #13   │  │ #25 │
│ Subs  │ │ Cat  │  │ Cust  │ │ Staff │  │ Bkup│
└───┬───┘ └──┬───┘  └───────┘ └───────┘  └─────┘
    │        │
    │   ┌────┼──────────┐
    │   │    │          │
    │ ┌─▼──┐ │    ┌─────▼──┐
    │ │ #6 │ │    │ #11    │
    │ │ Inv│ │    │ Barcode│
    │ └─┬──┘ │    └────┬───┘
    │   │    │         │
    │ ┌─▼────▼──┐      │
    │ │ #7 POS  │◄─────┘ (#22 Hardware)
    │ │ Terminal │
    │ └────┬────┘
    │      │
    │ ┌────▼─────┐
    │ │ #8       │
    │ │ Orders   │──────→ #12 Promos
    │ └────┬─────┘──────→ #17 Delivery
    │      │
    │ ┌────▼──────┐
    │ │ #9        │
    │ │ Payments  │──→ #16 Acct ──→ #18 Thawani ──→ #20 ZATCA
    │ └───────────┘
    │
    └──→ P3 (Package Mgmt) ──→ P5 (Platform Billing)
```

---

## 13. Quality Gates

Every feature must pass these gates before being considered complete.

### Gate 1: Backend Complete

| Check | Criteria |
|-------|----------|
| Migrations | `php artisan migrate` passes with no errors |
| Models | All relationships defined and tested |
| Enums | All enum values match `database_schema.sql` |
| Services | Business logic is in services/actions, not controllers |
| API | All endpoints return correct status codes + JSON structure |
| Filament | Admin pages render and CRUD operations work |
| Tests | `php artisan test --filter={Feature}` — all pass |
| Coverage | ≥ 90% code coverage for this feature's namespace |

### Gate 2: Documentation Complete

| Check | Criteria |
|-------|----------|
| API Specs | Every endpoint documented with method, path, auth, params |
| Samples | Request + response JSON examples for every endpoint |
| Errors | Error codes and messages documented |
| Changes | Any deviations from original feature doc noted |

### Gate 3: Frontend Complete

| Check | Criteria |
|-------|----------|
| Models | `fromJson`/`toJson` match actual API responses |
| Repository | API calls + local caching work correctly |
| Providers | State management covers all UI needs |
| UI | All screens implemented and functional |
| Platforms | Tested on at least 2 platforms (web + desktop or mobile) |
| Tests | `flutter test` — all pass for this feature |
| Analysis | `flutter analyze` — 0 errors, 0 warnings |

### Gate 4: Integration

| Check | Criteria |
|-------|----------|
| E2E Flow | Feature works end-to-end: UI → API → DB → Response → UI |
| Cross-feature | No regressions in previously completed features |
| Offline | (Phase 5) Works correctly in offline mode |

---

## 14. Definition of Done

A feature is **DONE** when:

- [x] All database tables created and migrated
- [x] All Laravel models reviewed with correct relationships
- [x] All Laravel enums verified against schema
- [x] Service layer implemented (services, actions, DTOs)
- [x] API controllers with Form Requests and Resources
- [x] Filament admin pages (if applicable)
- [x] Laravel tests written and passing (≥ 90% coverage)
- [x] Feature documentation updated with API specs
- [x] Flutter models verified against API responses
- [x] Flutter repository with remote + local data sources
- [x] Riverpod providers for state management
- [x] UI pages and widgets implemented
- [x] Flutter tests written and passing
- [x] `flutter analyze` — 0 errors, 0 warnings
- [x] Code committed with descriptive message
- [x] No regressions in other features

---

## 15. Progress Tracker

### Overall Progress

```
Phase 0: Scaffolding        ██████████████████░░  95%  (0.1-0.3 done, 0.4 partial, 0.5 skipped, 0.6 done)
Phase 1: Foundation         ████████████████░░░░  80%  (F1-F4 impl + tests done, Filament + API docs pending)
Phase 2: Core POS           ░░░░░░░░░░░░░░░░░░░░   0%  (not started)
Phase 3: Business           ░░░░░░░░░░░░░░░░░░░░   0%  (not started)
Phase 4: Platform Admin     ██████████████████░░  90%  (P1-P17 controllers + Flutter data layer + tests done, UI pages pending)
Phase 5: Polish & Scale     ░░░░░░░░░░░░░░░░░░░░   0%  (not started)
```

### Feature Completion Matrix

| # | Feature | Migration | Laravel | Tests | Docs | Flutter | Tests | Status |
|---|---------|-----------|---------|-------|------|---------|-------|--------|
| 1 | Auth & Users | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 2 | Roles & Permissions | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 3 | Store Setup | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 4 | Subscription | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 5 | Product Catalog | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 6 | Inventory | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 7 | POS Terminal | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 8 | Orders | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 9 | Payments | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 10 | Customers | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 11 | Barcode/Labels | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 12 | Promotions | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 13 | Staff | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 14 | Reports | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 15 | Notifications | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 16 | Accounting | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 17 | Delivery | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 18 | Thawani Gateway | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 19 | Web Dashboard | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 20 | ZATCA | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 21 | Offline Sync | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 22 | Hardware | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 23 | Localization | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 24 | Security | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 25 | Backup | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 26 | Companion App | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 27 | POS Custom | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 28 | Auto Updates | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 29 | Accessibility | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 30 | Nice-to-Have | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |
| 31 | Industry Workflows | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ Not Started |

### Platform Admin Features

| # | Feature | Filament | Tests | Docs | Status |
|---|---------|----------|-------|------|--------|
| P1 | Provider Mgmt | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P2 | Platform Roles | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P3 | Package Mgmt | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P4 | User Mgmt | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P5 | Billing | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P6 | Analytics | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P7 | Support | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P8 | Sys Config | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P9 | Logs & Monitoring | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P10 | Support Tickets | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P11 | Marketplace | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P12 | Deployment | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P13 | Data Management | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P14 | Security Center | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P15 | Financial Ops | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done (53 methods, 109 tests) |
| P16 | Infrastructure | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |
| P17 | Provider Roles | ✅ | ✅ | ⬜ | ✅ Backend+Flutter Done |

---

## TL;DR — Revised Plan

```
 1. ✅ Database schema designed (255 tables, 3,648 lines)
 2. ✅ Feature documentation written (47 docs)
 3. ✅ Laravel models generated (254 PHP models)
 4. ✅ Laravel enums generated (162 PHP enums)
 5. ✅ Flutter models generated (254 Dart models)
 6. ✅ Flutter enums generated (162 Dart enums)
 7. ✅ Laravel project scaffolded (12.53.0, 39 domains, 113 packages)
 8. ✅ Flutter project scaffolded (3.38.5, 31 features, 125 packages, 5 platforms)
 9. ✅ All models/enums moved to feature-based structures
10. ✅ All namespaces and imports fixed
11. ✅ Both projects compile (0 errors)
12. ✅ Supabase fully configured (storage bucket, realtime, RLS)
13. ✅ Both projects pushed to separate GitHub repos
14. ⏭️ Shared tooling skipped (will add incrementally)
15. ✅ Sync Laravel migrations with existing Supabase tables (53 migrations, all Ran)
16. ⬜ For each feature in dependency order:
    a. Create DB migration (only this feature's tables)
    b. Build Laravel: services, controllers, APIs, Filament pages + TESTS
    c. Update feature doc with API specs + samples
    d. Build Flutter: repositories, providers, pages + TESTS
17. ⬜ Integration testing across features as they connect
18. ⬜ Polish: offline sync, hardware, localization, accessibility
```

### Estimated Timeline (Solo Developer)

| Phase | Features | Estimated Duration |
|-------|----------|--------------------|
| Phase 0 | Scaffolding (remaining: 0.6 only) | ~1 day |
| Phase 1 | 4 foundation features | 3–4 weeks |
| Phase 2 | 7 core POS features | 6–8 weeks |
| Phase 3 | 8 business features | 5–7 weeks |
| Phase 4 | 17 platform features | 4–6 weeks |
| Phase 5 | 11 polish features | 4–6 weeks |
| **Total** | **47 features** | **~23–33 weeks** |

### Next Action

> **Complete Phase 0.6: Sync Laravel Migrations**
>
> 1. `php artisan migrate:install` — create the migrations table
> 2. Publish all package migrations (Spatie, Sanctum, etc.)
> 3. Create migration files for all 255 schema tables
> 4. `php artisan migrate --pretend` — verify SQL matches existing schema
> 5. `php artisan migrate --fake` — mark all as "already run"
> 6. Verify: `php artisan migrate:status` shows all Ran
>
> **Then start Feature #1: Auth & User Management**
>
> 1. Review `Domain/Auth/Models/User.php` — ensure relationships, casts, fillable
> 2. Build `AuthService`, `RegisterAction`, `LoginAction`
> 3. Build auth API endpoints + Form Requests + Resources
> 4. Build Filament user management pages
> 5. Write Laravel tests
> 6. Build Flutter auth flow (repository, providers, pages)
> 7. Write Flutter tests
> 8. Ship it. Move to Feature #2.

---

## 16. Testing Capability & Strategy

### Overview

Every vertical feature slice includes comprehensive tests on both the Laravel API and Flutter frontend. Tests are organized into three tiers: **Feature Tests** (API integration), **Unit Tests** (service logic), and **Flutter Tests** (model parsing, state, widgets).

### Laravel Test Structure

```
poslaravelapp/tests/
├── Feature/                              # API integration tests (HTTP requests)
│   ├── Auth/
│   │   ├── AuthApiTest.php              # 16 tests: registration, login, PIN, profile, password, token
│   │   └── AuthEdgeCasesApiTest.php     # 25 tests: edge cases, Arabic, multi-user, OTP
│   ├── RolesPermissionsApiTest.php      # 15 tests: CRUD, assignment, effective permissions, PIN override
│   ├── RolesPermissionsEdgeCasesApiTest.php  # 20 tests: cross-store, predefined, merged permissions
│   ├── StoreOnboardingApiTest.php       # 23 tests: store CRUD, settings, working hours, onboarding
│   ├── StoreOnboardingEdgeCasesApiTest.php   # 25 tests: Arabic, tax, 24hr hours, business types
│   └── Subscription/
│       ├── PlanApiTest.php              # 20 tests: list/show/create/update/toggle/delete plans
│       ├── SubscriptionApiTest.php      # 35 tests: subscribe/change/cancel/resume lifecycle
│       ├── InvoiceApiTest.php           # 14 tests: invoice listing, detail, pagination
│       └── PlanEnforcementTest.php      # 30 tests: feature checks, limits, usage tracking
│
└── Unit/                                 # Service-level unit tests (mocked dependencies)
    └── Services/
        ├── AuthServiceTest.php          # 20 tests: register, login, PIN, profile, token abilities
        ├── RoleServiceTest.php          # 15 tests: CRUD, permissions, predefined, audit
        ├── StoreOnboardingServiceTest.php   # 15 tests: store, settings, onboarding lifecycle
        └── SubscriptionServiceTest.php  # 20 tests: plan CRUD, billing, enforcement
```

**Total: ~293 Laravel tests across 15 test files**

### Laravel Testing Patterns

| Pattern | Implementation |
|---------|---------------|
| **Database** | SQLite `:memory:` + `RefreshDatabase` trait — each test gets a clean DB |
| **Authentication** | `$user->createToken('test')->plainTextToken` → `$this->withToken($token)` |
| **API Envelope** | All responses assert `{ success, message, data }` structure |
| **Guards** | Tests verify both `sanctum` (user) and `admin` (admin_users) guards reject unauthenticated |
| **Validation** | Each endpoint's required/invalid fields tested for 422 responses |
| **Edge Cases** | Arabic text, zero values, boundary conditions, idempotent operations |
| **Error Messages** | Every error response asserts specific `message` text for clear debugging |

### Laravel Test Execution

```bash
# Run all tests
php artisan test

# Run specific feature
php artisan test --filter=Auth
php artisan test --filter=Subscription

# Run with coverage
php artisan test --coverage --min=90

# Run single test file
php artisan test tests/Feature/Subscription/PlanApiTest.php
```

### Flutter Test Structure

```
posflutterapp/test/
├── widget_test.dart                           # App smoke test
├── core/
│   ├── utils/validators_test.dart            # Validators: required, email, phone, minLength, pin
│   ├── network/api_response_test.dart        # ApiResponse generic parsing, error handling
│   └── errors/app_exception_test.dart        # Exception hierarchy, catch semantics
├── features/
│   ├── auth/
│   │   └── auth_models_test.dart            # User (20+ fields, nested), AuthToken, AuthResponse, UserRole
│   ├── staff/
│   │   └── staff_models_test.dart           # Role (int ID!), Permission, Arabic, round-trip
│   ├── onboarding/
│   │   └── onboarding_models_test.dart      # StoreSettings (27 fields, defaults), OnboardingProgress, steps
│   └── subscription/
│       ├── subscription_models_test.dart    # SubscriptionPlan, StoreSubscription, Invoice, enums
│       ├── subscription_state_test.dart     # Sealed class states: Plans, Subscription, Invoices, Usage
│       └── subscription_pages_test.dart     # Widget tests: loading, error, loaded states for 3 pages
├── golden/.gitkeep
└── integration/.gitkeep
```

**Total: ~160 Flutter test cases across 9 test files**

### Flutter Testing Patterns

| Pattern | Implementation |
|---------|---------------|
| **Model Parsing** | `fromJson` with full payload, null fields, unexpected values, int→double casts |
| **Enum Safety** | `fromValue()` throws `ArgumentError` for invalid, `tryFromValue()` returns null |
| **Round-Trip** | `fromJson(toJson(model))` preserves all data |
| **ID Types** | Role/Permission use `int` IDs (not String) — verified in tests |
| **Defaults** | StoreSettings defaults (taxRate=15, currencyCode=SAR, timeout=480) explicitly tested |
| **State Classes** | Sealed class exhaustive switch verification via Dart 3 pattern matching |
| **Widget Tests** | `ProviderScope` + `overrideWith` mock notifiers for each state variant |
| **API Response** | Generic `T` parsing with `fromData` callback, null data, error map handling |

### Flutter Test Execution

```bash
# Run all tests
flutter test

# Run specific test file
flutter test test/features/subscription/subscription_models_test.dart

# Run with coverage
flutter test --coverage

# Run with verbose output
flutter test --reporter expanded
```

### Key Test Scenarios by Feature

#### Feature 1: Auth & User Management (61 Laravel + 22 Flutter tests)
- Registration with valid/invalid/minimal/Arabic data
- Login by email+password and by store PIN
- Device token management (revoke old, register new)
- Profile update (only allowed fields, rejects others)
- Password change (revokes other tokens, requires current password)
- Token refresh (old revoked, new issued)
- OTP send/verify with cooldown and attempt limits
- User model: nested store/org parsing, storeId resolution, role tryFromValue

#### Feature 2: Roles & Permissions (35 Laravel + 15 Flutter tests)
- Role CRUD with permission sync
- Predefined roles cannot be edited/deleted
- Cross-store isolation (same name allowed in different stores)
- PIN override authorization (PIN hash matching + permission check)
- Effective permissions merge from multiple roles
- Role model: int IDs, nested permissions array, guard_name defaults
- Permission model: requiresPin flag, module field

#### Feature 3: Store & Onboarding (48 Laravel + 18 Flutter tests)
- Store CRUD with organization scope
- Settings with defaults, zero-value tax, negative tax rejection
- Working hours (all closed, 24-hour, partial updates)
- Business type templates (active-only filtering)
- Onboarding step progression (idempotent, out-of-order, skip+resume, reset)
- Checklist items (timestamps, uncheck, dismiss persistence)
- StoreSettings model: 27 fields, toJson omits id/storeId, extra map
- OnboardingProgress model: step enum parsing, completed steps filtering

#### Feature 4: Subscription & Billing (119 Laravel + 70 Flutter tests)
- Plan CRUD with features/limits sync
- Subscription lifecycle: trial → active → cancelled → resumed
- Plan changes with same-plan rejection and proration
- Invoice generation with 15% VAT calculation
- Plan enforcement: feature checks, limit checks, admin overrides
- Usage tracking and quota calculations
- Widget tests: loading/error/loaded states for all 3 pages
- State classes: sealed class exhaustive coverage
- All 3 enums: SubscriptionStatus, BillingCycle, SubscriptionPaymentMethod

### Test Totals

| Category | Files | Approximate Tests |
|----------|-------|-------------------|
| Laravel Feature Tests | 11 | ~223 |
| Laravel Unit Tests | 4 | ~70 |
| Flutter Model Tests | 4 | ~95 |
| Flutter Core Tests | 3 | ~40 |
| Flutter Widget Tests | 1 | ~15 |
| Flutter State Tests | 1 | ~20 |
| **Total** | **24** | **~453** |
