# Wameed POS — Technologies & Technical Decisions

> Comprehensive technical reference covering every technology choice, package, service, infrastructure component, and tooling decision for the Wameed POS ecosystem.

---

## 📐 Architecture Overview

The system is divided into two scopes with distinct tech stacks:

| Scope | Description | Primary Stack |
|---|---|---|
| **Provider** | POS desktop app, tablet POS, mobile companion, store owner web dashboard | Flutter (Dart) + Laravel API |
| **Platform** | Thawani internal super admin panel | Laravel + Filament v3 |

Both scopes share a single Laravel backend API and PostgreSQL cloud database.

---

## 🖥️ Provider Side — Flutter

### Phase Strategy

| Phase | Platforms | Notes |
|---|---|---|
| **Phase 1 (MVP)** | Windows Desktop + Flutter Web | Windows is primary POS target; web serves as store owner dashboard and backup POS | Android Tablet | Same Flutter codebase, responsive layout for tablet POS
| **Phase 2** | + iOS / Android Mobile | Manager-on-the-go companion app (reports, inventory, alerts) |
| **Phase 3 (if needed)** | + macOS / Linux Desktop | Same codebase; Windows covers 95 %+ of Saudi retail |

### Framework & Language

| Component | Choice | Version | Notes |
|---|---|---|---|
| **Framework** | Flutter | 3.x (latest stable) | Desktop + Web + Mobile from one codebase |
| **Language** | Dart | 3.x | Null-safe, AOT-compiled for desktop |
| **Rendering** | Skia / Impeller | — | Hardware-accelerated; touch-optimised |

### State Management

| Option | When to Use |
|---|---|
| **Riverpod** *(recommended)* | Primary state management — providers for all app-wide and feature-scoped state (cart, auth, sync status, products, inventory) |
| **Bloc** *(alternative)* | If team prefers event-driven pattern; interchangeable with Riverpod at project start — pick one |
| **flutter_hooks** | Optional companion to Riverpod for concise widget-level state |

> **Decision**: Choose Riverpod **or** Bloc at project kickoff and standardise across the entire app. Do not mix.

### Local Database (Offline-First)

| Component | Choice | Purpose |
|---|---|---|
| **ORM** | Drift (formerly Moor) | Type-safe SQLite ORM for Dart — migrations, DAOs, reactive streams |
| **Engine** | SQLite | Embedded relational DB; zero-config; no server; full SQL support |
| **Usage** | Full local copy of catalog, inventory, transactions, sync queue, ZATCA invoices, audit logs, settings |

**Why Drift over raw sqflite:**
- Compile-time SQL verification
- Auto-generated Dart model classes
- Reactive query streams (UI updates automatically when data changes)
- Built-in migration support
- Works on all Flutter platforms (desktop, web, mobile)

### HTTP & Networking

| Component | Choice | Purpose |
|---|---|---|
| **HTTP Client** | Dio | REST API calls, file uploads, interceptors for auth token refresh, retry logic |
| **Connectivity** | connectivity_plus | Detect online/offline state; trigger sync on reconnection |
| **Sync Protocol** | REST API + polling | Bidirectional sync with Laravel backend (not WebSocket — low volume ~50 updates/day) |
| **Polling Interval** | 5 minutes | Fetch new orders, process offline queue; configurable |

### Printing

| Component | Choice | Purpose |
|---|---|---|
| **Thermal Receipt** | esc_pos_printer + esc_pos_utils | ESC/POS command generation for thermal printers (Bixolon, Epson, Star, Xprinter) |
| **Multi-brand** | flutter_thermal_printer | Fallback / alternative for additional printer brands |
| **System Printing** | printing (package) | PDF-based printing to standard OS printers; used for reports, label sheets |
| **PDF Generation** | pdf (package) | Generate PDF receipts, reports, barcode label sheets for regular printers |
| **Label Printing** | Raw ZPL / TSPL via network socket | Direct command-language output to Zebra (ZPL), TSC (TSPL), Bixolon (SLCS/ZPL), Godex (EZPL) label printers over TCP port 9100 |

**Connection Methods (in preference order):**
1. Network / Ethernet (port 9100) — recommended for POS environments
2. USB — via printer driver or raw USB
3. Bluetooth — via flutter_blue_plus (mobile / tablet)
4. Serial RS-232 — legacy printers

### Barcode & QR

| Component | Choice | Purpose |
|---|---|---|
| **Barcode Generation** | barcode (package) | Generate barcode images: EAN-13, EAN-8, Code128, UPC-A |
| **QR Generation** | qr_flutter | QR codes for ZATCA e-invoices and digital receipts |
| **Barcode Scanning** | Keyboard emulation (USB HID) | Desktop: scanner acts as keyboard input — zero integration needed |
| **Mobile Scanning** | mobile_scanner or camera + ML | Mobile companion app: camera-based barcode scanning |

### ZATCA Phase 2 Cryptography

| Component | Choice | Purpose |
|---|---|---|
| **Crypto Library** | pointycastle | Pure-Dart cryptography: ECDSA (secp256k1) signing, SHA-256 hashing, X.509 certificate handling |
| **Fallback** | dart:ffi → OpenSSL | If pointycastle performance is insufficient, call native OpenSSL via FFI |
| **TLV Encoding** | Custom (Dart) | Tag-Length-Value encoding for ZATCA QR data (Tags 1-9) |
| **XML** | xml (package) | Build UBL 2.1 XML invoices for ZATCA reporting |
| **CSR Generation** | pointycastle + asn1lib | Generate Certificate Signing Requests for ZATCA device onboarding |

**ZATCA Signing Flow (all local, offline-capable):**
1. Build UBL 2.1 XML invoice
2. Canonicalise and hash (SHA-256)
3. Sign hash with ECDSA private key (secp256k1)
4. Generate TLV QR data (seller, VAT number, timestamp, total, VAT, hash, signature, public key)
5. Base64-encode QR data for receipt printing
6. Queue signed invoice XML for backend submission when online

### Hardware Integration Packages

| Hardware | Package / Approach | Connection |
|---|---|---|
| **Thermal Printers** | esc_pos_printer, flutter_thermal_printer | Network (9100), USB, Bluetooth |
| **Barcode Scanners** | Keyboard emulation (built-in OS support) | USB HID — plug-and-play |
| **Cash Drawers** | ESC/POS kick command via receipt printer (RJ11 connected to printer) | Triggered through printer package |
| **Weighing Scales** | flutter_libserialport or usb_serial | RS-232 serial / USB-serial adapter |
| **Customer Display** | Second Flutter window (multi-window) | HDMI / DisplayPort second monitor |
| **Label Printers** | Raw TCP socket (ZPL/TSPL) | Network (9100), USB |
| **Payment Terminals** | NearPay SDK / Nexo protocol | NearPay (Saudi-approved); terminal handles all card data via P2PE |

### UI & Design Packages

| Component | Choice | Purpose |
|---|---|---|
| **Adaptive Layout** | flutter_adaptive_scaffold | Responsive layouts across desktop, tablet, mobile |
| **RTL / Arabic** | Built-in Flutter Directionality | Full RTL support; automatic mirroring; Arabic locales |
| **Localisation** | flutter_localizations + intl | AR/EN strings, number/date/currency formatting, Arabic-Indic numerals (٠١٢٣), Hijri calendar |
| **Icons** | Material Icons + Heroicons | Consistent icon set |
| **Charts** | fl_chart or syncfusion_flutter_charts | Reports and analytics dashboards |
| **Animations** | Built-in Flutter animations | Smooth transitions for POS interactions |

### Storage & Preferences

| Component | Choice | Purpose |
|---|---|---|
| **Structured Data** | Drift (SQLite) | Products, transactions, inventory, sync queue |
| **Key-Value Settings** | shared_preferences | User preferences (theme, language, font size, handedness) |
| **Secure Storage** | flutter_secure_storage | ZATCA private keys, API tokens, third-party credentials (AES-256) |
| **File Storage** | path_provider + dart:io | Local backups, cached images, export files |

### Testing

| Layer | Tool | Coverage Target |
|---|---|---|
| **Unit Tests** | flutter_test | ≥ 90 % — business logic, calculations (VAT, cart totals, discounts, ZATCA QR) |
| **Widget Tests** | flutter_test | ≥ 70 % — UI components, POS screen interactions |
| **Integration Tests** | integration_test (Flutter) | Key flows: complete sale, offline sync, ZATCA signing, receipt printing |
| **Mocking** | mockito + build_runner | Mock API, database, and hardware services for isolated tests |
| **Golden Tests** | flutter_test (goldens) | Receipt layout, RTL rendering, theme consistency |

### Platform Channels / FFI

| Use Case | Approach |
|---|---|
| **Native crypto** (if needed) | dart:ffi → call OpenSSL C library |
| **Serial port** (scales) | flutter_libserialport (wraps libserialport via FFI) |
| **USB raw access** (if needed) | Platform channel → native Windows code |
| **Window management** (customer display) | window_manager package for multi-window on desktop |
| **System tray** | system_tray package — POS stays resident |
| **Auto-start** | Windows registry / startup folder — POS launches on boot |

### Build & Distribution (Desktop)

| Component | Choice | Notes |
|---|---|---|
| **Build** | flutter build windows | Produces .exe + DLLs |
| **Installer** | Inno Setup or MSIX | Inno Setup for traditional installer; MSIX for Windows Store |
| **Auto-Update** | Custom updater service | Check version on startup + every 4 h; download in background; verify signature; pre-update backup; rollback on crash loop |
| **Code Signing** | Windows Authenticode certificate | Signed .exe to avoid SmartScreen warnings |
| **CI/CD** | GitHub Actions | Build, test, sign, package, distribute per commit / release tag |

### Estimated App Size

| Platform | Size | Acceptable for POS? |
|---|---|---|
| Windows Desktop | 50–100 MB | ✅ Yes — installed app on dedicated POS hardware |
| Android/iOS | 30–60 MB | ✅ Yes — companion app |
| Web | ~15 MB initial load | ✅ Yes — dashboard / management only |

---

## 🏛️ Platform Side — Laravel + Filament

### Framework & Language

| Component | Choice | Version | Notes |
|---|---|---|---|
| **Framework** | Laravel | 11.x | Backend API + Super Admin Panel |
| **Language** | PHP | 8.2+ | |
| **Admin Panel** | Filament | v3 | Pre-built admin UI: resources, tables, forms, actions, widgets, dashboards |
| **Reactive UI** | Livewire | 3.x | Server-rendered reactive components (used by Filament under the hood) |
| **JS Interactivity** | Alpine.js | 3.x | Minimal JS for dropdown/modal behavior (bundled with Filament) |
| **CSS** | Tailwind CSS | 3.x | Utility-first styling (bundled with Filament) |

### Why Filament for Super Admin

- ~45 admin pages can be scaffolded rapidly (CRUD resources, custom pages, dashboard widgets)
- Built-in: data tables with search/filter/sort/export, form builder, role-based navigation, action modals, notification toasts
- Saves estimated ~50,000 SAR and 2–3 months of development vs. building admin UI from scratch
- Internal tool only — no need for Flutter's cross-platform consumer-grade UI

### Store Owner Dashboard (separate from Super Admin)

| Option | Approach | Notes |
|---|---|---|
| **Option A** *(recommended start)* | Laravel + Livewire 3 + Tailwind | Fast to build; same codebase as API; works in any browser |
| **Option B** *(later)* | Flutter Web | Share code with desktop POS; richer UX; more dev effort |

> The store owner dashboard provides: product management, inventory overview, sales reports, staff management, multi-store view. It is a management companion — not a POS replacement.

### Authentication & Authorisation

| Component | Choice | Purpose |
|---|---|---|
| **API Auth** | Laravel Sanctum | Token-based auth for POS app ↔ API; SPA cookie-based auth for web dashboard |
| **Admin Auth** | Filament Shield + Spatie Permission | Role-based access: super_admin, admin, support, sales, finance, viewer |
| **Provider Auth** | Laravel Sanctum + custom guards | Provider (store) scoped tokens with store_id claim |
| **Password Hashing** | bcrypt (Laravel default) | |
| **2FA** | Laravel Fortify or Filament 2FA plugin | Mandatory for all platform admin accounts |

### Queue & Background Jobs

| Component | Choice | Purpose |
|---|---|---|
| **Queue Driver** | Redis | Fast, reliable job queue |
| **Dashboard** | Laravel Horizon | Real-time queue monitoring, metrics, failed job management |
| **Jobs** | Laravel Queued Jobs | Sync processing, ZATCA submission, notification dispatch, report generation, backup |
| **Scheduler** | Laravel Task Scheduling (cron) | Recurring tasks: daily cloud backup, subscription renewal checks, expiry alerts, sync health checks |

### Notifications

| Channel | Service / Package | Notes |
|---|---|---|
| **SMS** | Unifonic / Taqnyat / Msegat | Saudi SMS providers; configurable in platform settings |
| **Email** | Laravel Mail (SMTP / Mailgun / SES) | Transactional emails, invoices, reports |
| **Push (FCM)** | laravel-notification-channels/fcm | Firebase Cloud Messaging for Android/iOS companion app |
| **Push (APNs)** | laravel-notification-channels/apn | Apple Push Notification service |
| **WhatsApp** | WhatsApp Business API (official) | Digital receipt delivery, order notifications |
| **Webhook** | Custom HTTP dispatcher | Notify external systems of events |
| **In-App** | Laravel database notifications | Bell icon inbox; read/unread; used in both web dashboard and Flutter app (via API) |

### File Storage

| Component | Choice | Purpose |
|---|---|---|
| **Driver** | Laravel Filesystem (Flysystem) | Abstracted storage — swap providers without code changes |
| **Start** | DigitalOcean Spaces | S3-compatible object storage; product images, receipts, backups, export files |
| **Scale** | AWS S3 | Migrate when moving to AWS infrastructure |

### Payment Gateway Integration

| Gateway | Use Case | Notes |
|---|---|---|
| **Thawani Pay** | Subscription billing for providers | Primary |
| **Stripe** | International fallback | If needed |
| **Tap Payments** | Saudi/Gulf alternative | Mada support |
| **Moyasar** | Saudi alternative | Mada, Apple Pay |
| **HyperPay** | Saudi alternative | Broad card support |

> The POS itself does **not** process card payments — the payment terminal (NearPay / Nexo) handles all card data directly. The gateways above are for **subscription billing** only.

### API Design

| Aspect | Choice |
|---|---|
| **Style** | RESTful JSON API |
| **Versioning** | URL-prefix: `/api/v2/...` |
| **Rate Limiting** | Laravel built-in throttle middleware |
| **Request Validation** | Laravel Form Requests with server-side recalculation (prevents price manipulation) |
| **Response Format** | JSON:API-inspired (data, meta, errors) |
| **Documentation** | Scribe (auto-generated from routes + annotations) |
| **CORS** | Laravel CORS middleware — allow POS app and web dashboard origins |

---

## 🗄️ Database

### Cloud Database

| Component | Choice | Notes |
|---|---|---|
| **Engine** | PostgreSQL | Relational, JSONB support, excellent for multi-tenant SaaS |
| **Phase 1** (0–500 stores) | Supabase Pro ($25/mo) | Managed PostgreSQL; built-in connection pooling (PgBouncer); team already familiar; real-time subscriptions for dashboard |
| **Phase 2** (500–2 000 stores) | Supabase Team ($599/mo) | Larger instance; still managed |
| **Phase 3** (2 000–10 000 stores) | AWS RDS (Bahrain region) | Self-managed migration via `pg_dump`/`pg_restore`; read replicas; PgBouncer; closer to Saudi users |
| **Phase 4** (10 000+ stores) | AWS RDS Multi-AZ + regional shards | Table partitioning by date; database sharding by region (Riyadh, Jeddah, Dammam) |

**Migration is straightforward**: it's all standard PostgreSQL. Logical replication enables zero-downtime cutover.

### Local Database (on POS device)

| Component | Choice | Notes |
|---|---|---|
| **Engine** | SQLite (via Drift) | Full local copy: products, categories, inventory, transactions, sync queue, ZATCA invoices, settings, audit logs |
| **Sync** | REST API with offline queue | Priority: transactions → inventory → products → settings |
| **Conflict Resolution** | server_wins for master data; client_wins for transactions; last_write_wins for inventory | Configurable in platform settings |
| **Backup** | Local: automatic every 4 h (7-day retention); Cloud: daily at closing (90-day retention) |

### Connection Pooling

| Stage | Approach |
|---|---|
| Supabase | Built-in PgBouncer (included) |
| AWS RDS | Self-hosted PgBouncer sidecar |

### Key Schema Design Principles (implemented from Day 1)

- **UUID primary keys** on all tables (ready for distributed / sharded future)
- **`store_id`** on all tenant tables (shard key)
- **`organization_id`** for chain/multi-store isolation
- **Timestamps with timezone** (`TIMESTAMPTZ`) for multi-region readiness
- **Soft deletes** (`deleted_at`) on all business tables
- **JSONB columns** for flexible metadata (feature flags, custom fields)
- **Composite indexes** on `(store_id, created_at)` for future partitioning

---

## ☁️ Cloud Infrastructure

### Hosting

| Component | Phase 1 (Startup) | Phase 3+ (Scale) |
|---|---|---|
| **API Servers** | DigitalOcean Droplet | AWS ECS / Kubernetes |
| **Database** | Supabase (managed PostgreSQL) | AWS RDS (Bahrain region) |
| **Cache** | Redis (single instance on same server) | AWS ElastiCache (Redis cluster) |
| **File Storage** | DigitalOcean Spaces | AWS S3 |
| **CDN** | Cloudflare (free tier) | Cloudflare Pro / AWS CloudFront |
| **DNS** | Cloudflare | Cloudflare |
| **SSL** | Let's Encrypt (via Cloudflare) | AWS ACM |
| **Load Balancer** | — (single server) | AWS ALB |

### Cost Projections

| Stage | Stores | Monthly Infra Cost |
|---|---|---|
| Startup | 0–500 | ~$50 (Supabase $25 + DO $25) |
| Growth | 500–2 000 | ~$800 |
| Scale | 2 000–10 000 | ~$5,000 |
| Enterprise | 10 000–25 000 | ~$50,000 (0.8 % of revenue) |

### CI/CD Pipeline

| Component | Choice | Purpose |
|---|---|---|
| **Repository** | GitHub (private) | Monorepo or multi-repo |
| **CI** | GitHub Actions | Automated: lint, test, build, sign, package on every push |
| **CD (API)** | GitHub Actions → deploy to DO / AWS | Zero-downtime deploy (rolling) |
| **CD (Desktop)** | GitHub Actions → build → sign → upload | Installer artifacts uploaded to DigitalOcean Spaces (S3-compatible API) |
| **CD (Admin)** | GitHub Actions → deploy to same server as API | Filament panel deployed alongside Laravel |

### Monitoring & Observability

| Component | Choice | Purpose |
|---|---|---|
| **Error Tracking** | Sentry | Crash reports from Flutter app + Laravel API |
| **APM** | Laravel Telescope (dev) + Sentry Performance (prod) | API response times, slow queries, queue metrics |
| **Uptime** | UptimeRobot or Better Stack | API endpoint monitoring, alerting |
| **Logs** | Laravel Log (daily files) → centralized logging (Papertrail / CloudWatch Logs at scale) | Application logs |
| **Queue Monitoring** | Laravel Horizon | Real-time queue worker health, throughput, failed jobs |
| **Server Metrics** | DO Monitoring / AWS CloudWatch | CPU, memory, disk, network |

---

## 🔒 Security Stack

| Concern | Technology / Approach |
|---|---|
| **Transport Encryption** | TLS 1.2+ (HTTPS only) for all API communication |
| **Data at Rest** | AES-256 encryption for sensitive fields (third-party credentials, ZATCA private keys) |
| **Secure Device Storage** | flutter_secure_storage (keychain / keystore) — ZATCA private key never exportable |
| **Password Policy** | Minimum 8 chars, complexity rules, bcrypt hashing |
| **Session Management** | Laravel Sanctum tokens with expiry; auto-lock POS on inactivity |
| **PCI DSS** | POS never stores full card PAN / CVV / PIN; payment terminal handles via P2PE |
| **SQL Injection** | Laravel Eloquent parameterised queries; no raw interpolated SQL |
| **XSS** | Blade template auto-escaping; Filament built-in sanitisation |
| **CSRF** | Laravel CSRF middleware on all web routes |
| **Rate Limiting** | Laravel throttle middleware on API endpoints |
| **Audit Trail** | Chained audit log (SHA-256 integrity chain) on all sensitive actions |
| **IP Allowlist** | Platform admin panel restricted to approved IPs |
| **2FA** | Mandatory for all platform admin accounts |

---

## 🌍 Localisation Stack

| Aspect | Implementation |
|---|---|
| **Languages** | Arabic (primary) + English |
| **Direction** | RTL (Arabic) / LTR (English) — automatic mirroring via Flutter `Directionality` |
| **String Management** | ARB files (Flutter) / Laravel lang files (PHP) |
| **Number Formatting** | intl package — Arabic-Indic numerals (٠١٢٣٤٥٦٧٨٩) toggle |
| **Currency** | Saudi Riyal (SAR / ر.س) with 2 decimal places |
| **Calendar** | Gregorian (default) + Hijri option |
| **Date/Time** | intl DateFormat — locale-aware |
| **Receipt Text** | Arabic rendered as image for ESC/POS printers (thermal printers lack native Arabic font support) |

---

## 📱 Platform-by-Platform Summary

### Windows Desktop POS (Phase 1 — Primary)

| Layer | Technology |
|---|---|
| UI | Flutter Desktop (Windows) |
| State | Riverpod or Bloc |
| Local DB | Drift (SQLite) |
| Printing | esc_pos_printer (network), ZPL/TSPL (labels) |
| Scanning | Keyboard emulation (USB HID) |
| Crypto | pointycastle (ZATCA ECDSA) |
| Auto-Update | Custom updater (check, download, verify, install, rollback) |
| Installer | Inno Setup / MSIX |

### Flutter Web — Store Owner Dashboard (Phase 1)

| Layer | Technology |
|---|---|
| UI | Flutter Web (same codebase, responsive layout) |
| State | Riverpod or Bloc (shared) |
| Data | Drift (IndexedDB backend on web) or direct API calls |
| Auth | Laravel Sanctum (SPA cookie-based) |
| Features | Product management, reports, inventory, staff, multi-store |

### Android Tablet POS (Phase 2)

| Layer | Technology |
|---|---|
| UI | Flutter (Android) — shared 80–90 % with desktop |
| State | Riverpod or Bloc (shared) |
| Local DB | Drift (SQLite on Android) |
| Printing | flutter_thermal_printer (Bluetooth) or network |
| Scanning | Camera-based (mobile_scanner) or Bluetooth scanner |
| Distribution | Google Play Store or direct APK |

### iOS / Android Mobile Companion (Phase 3)

| Layer | Technology |
|---|---|
| UI | Flutter (iOS + Android) — shared 70 %+ with desktop |
| Features | Reports, inventory check, push alerts, camera barcode scan |
| Offline | Drift (SQLite) for cached data; read-mostly |
| Push | FCM (Android) / APNs (iOS) |
| Distribution | App Store + Google Play |

### Super Admin Panel (Platform — Phase 1)

| Layer | Technology |
|---|---|
| Framework | Laravel 11 + Filament v3 |
| UI | Filament (Livewire 3 + Alpine.js + Tailwind) |
| DB | PostgreSQL (same as API) |
| Auth | Filament Shield + Spatie Permission; Sanctum tokens; 2FA enforced |
| Hosting | Same server as Laravel API |
| Pages | ~45 admin pages (resources, custom pages, dashboard widgets) |

---

## 📦 Key Dart/Flutter Packages Summary

| Package | Purpose | Category |
|---|---|---|
| **drift** | SQLite ORM (offline database) | Data |
| **dio** | HTTP client | Networking |
| **connectivity_plus** | Online/offline detection | Networking |
| **riverpod** / **flutter_bloc** | State management | State |
| **esc_pos_printer** | ESC/POS thermal printing | Hardware |
| **esc_pos_utils** | ESC/POS command builder | Hardware |
| **flutter_thermal_printer** | Multi-brand printer support | Hardware |
| **flutter_blue_plus** | Bluetooth communication | Hardware |
| **flutter_libserialport** | Serial port (scales) | Hardware |
| **usb_serial** | USB serial (scales, legacy) | Hardware |
| **window_manager** | Multi-window (customer display) | Desktop |
| **system_tray** | System tray integration | Desktop |
| **pointycastle** | ECDSA, SHA-256 (ZATCA) | Crypto |
| **asn1lib** | ASN.1 / X.509 (ZATCA CSR) | Crypto |
| **xml** | UBL 2.1 XML (ZATCA invoices) | Data |
| **barcode** | Barcode image generation | Barcode |
| **qr_flutter** | QR code generation | Barcode |
| **mobile_scanner** | Camera barcode scanning | Barcode |
| **pdf** | PDF generation | Documents |
| **printing** | System printer support | Documents |
| **flutter_adaptive_scaffold** | Responsive layouts | UI |
| **fl_chart** | Charts and graphs | UI |
| **intl** | i18n, date/number formatting | Localisation |
| **flutter_localizations** | Built-in locale delegates | Localisation |
| **shared_preferences** | Key-value settings | Storage |
| **flutter_secure_storage** | Encrypted secure storage | Storage |
| **path_provider** | File system paths | Storage |
| **mockito** | Test mocking | Testing |
| **integration_test** | E2E integration tests | Testing |
| **sentry_flutter** | Crash reporting | Monitoring |

---

## 📦 Key Laravel / PHP Packages Summary

| Package | Purpose | Category |
|---|---|---|
| **filament/filament** | Admin panel (Super Admin) | Admin |
| **filament/tables** | Data tables | Admin |
| **filament/forms** | Form builder | Admin |
| **filament/notifications** | Toast notifications | Admin |
| **spatie/laravel-permission** | Roles & permissions | Auth |
| **bezhansalleh/filament-shield** | Filament role-based access | Auth |
| **laravel/sanctum** | API token + SPA auth | Auth |
| **laravel/horizon** | Redis queue dashboard | Queue |
| **spatie/laravel-activitylog** | Audit logging | Security |
| **sentry/sentry-laravel** | Error tracking | Monitoring |
| **laravel/telescope** | Debug assistant (dev) | Monitoring |
| **maatwebsite/excel** | Excel/CSV import/export | Data |
| **barryvdh/laravel-dompdf** | PDF generation (invoices, reports) | Documents |
| **knuckleswtf/scribe** | API documentation generator | Docs |
| **spatie/laravel-backup** | Automated database backups | Ops |
| **spatie/laravel-media-library** | File/image management | Storage |
| **laravel-notification-channels/fcm** | Firebase push notifications | Notifications |
| **laravel-notification-channels/apn** | Apple push notifications | Notifications |

---

## 🔧 Development Tooling

| Tool | Purpose |
|---|---|
| **VS Code** | Primary IDE (Flutter + Dart + PHP extensions) |
| **Android Studio** | Alternative IDE; Android emulator for tablet testing |
| **Flutter DevTools** | Performance profiling, widget inspector, network inspector |
| **Postman / Hoppscotch** | API testing and documentation |
| **GitHub** | Source control, code review, issue tracking |
| **GitHub Actions** | CI/CD pipelines |
| **Docker** | Local development environment for Laravel + PostgreSQL + Redis |
| **TablePlus / pgAdmin** | Database GUI for PostgreSQL |
| **Redis Insight** | Redis GUI for cache/queue inspection |
| **Figma** | UI/UX design and prototyping |
| **Sentry** | Error tracking and performance monitoring (prod) |

---

## ⚡ Performance Targets

| Operation | Target |
|---|---|
| Barcode lookup | < 50 ms |
| Add item to cart | < 30 ms |
| Calculate total | < 10 ms |
| Complete transaction (save + sign + print) | < 200 ms |
| Print receipt | < 2 s |
| App cold start to usable | < 3 s |
| App warm start (resume) | < 500 ms |
| Product search (10 000 products) | < 100 ms |
| Transaction insert (local DB) | < 50 ms |
| Daily report query (local DB) | < 2 s |
| Idle memory | < 200 MB |
| Active (50 cart items) memory | < 300 MB |
| Peak (reporting) memory | < 500 MB |

Achieved via: in-memory product cache (top 500–1 000 products), search index, lazy loading, paginated queries, Drift reactive streams.

---

## 🏗️ Architecture Patterns

| Pattern | Where | Purpose |
|---|---|---|
| **Offline-First** | Flutter POS app | Full operation without internet; sync when online |
| **Repository Pattern** | Laravel API | Abstract database access; swap Supabase → RDS without business logic changes |
| **Service Layer** | Flutter + Laravel | Business logic isolated from UI and data layers |
| **Event-Driven** | Laravel Events + Listeners | Decouple side effects (notifications, audit logs, sync triggers) |
| **Queue-Based Processing** | Laravel Horizon (Redis) | Async: ZATCA submission, backup, report generation, notification dispatch |
| **Multi-Tenant** | Laravel middleware + `store_id` scoping | All queries scoped to authenticated store; row-level isolation |
| **Feature Flags** | Platform settings (JSONB) | Gradual rollouts; A/B testing; package-tier gating |
| **Config-Driven DB** | Laravel `config/database.php` | Switch database connections (Supabase ↔ RDS) via env variable, zero code change |

---

## 🗺️ Technology Decision Summary

```
Provider (Store-Facing)
├── Flutter Desktop (Windows) ──── Phase 1
├── Flutter Web ─────────────────── Phase 1 (store owner dashboard)
├── Flutter Android (Tablet) ────── Phase 2
├── Flutter iOS + Android (Mobile) ─ Phase 3
├── Drift (SQLite) ─────────────── Offline local DB
├── Riverpod or Bloc ───────────── State management
├── Dio ─────────────────────────── HTTP client
├── pointycastle ────────────────── ZATCA crypto
├── esc_pos_printer ─────────────── Receipt printing
└── REST API sync (5-min polling) ── Cloud sync

Platform (Thawani Internal)
├── Laravel 11 ──────────────────── Backend API + business logic
├── Filament v3 ─────────────────── Super Admin Panel (~45 pages)
├── Livewire 3 ──────────────────── Reactive UI (under Filament)
├── Laravel Sanctum ─────────────── Auth (API tokens + SPA cookies)
├── Laravel Horizon ─────────────── Queue monitoring
├── Spatie Permission ───────────── Roles & access control
└── PostgreSQL ──────────────────── Cloud database

Infrastructure
├── Supabase (start) → AWS RDS (scale) ── Database
├── DigitalOcean (start) → AWS (scale) ── API hosting
├── Redis ───────────────────────── Cache + queue
├── DigitalOcean Spaces ─────────── File storage (S3-compatible)
├── Cloudflare ──────────────────── CDN + DNS + SSL
├── GitHub Actions ──────────────── CI/CD
└── Sentry ──────────────────────── Error tracking
```

---

*Document Version: 1.0*
*Created: 7 March 2026*
*Source: POS_SYSTEM_BRAINSTORM.md, provider_features_in_short.md, platform_features_in_short.md*
