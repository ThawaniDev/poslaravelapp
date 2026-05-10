# Wameed POS — Product Overview Document

> **Version:** May 2026  
> **Product:** Wameed POS — Saudi-first Cloud SaaS Point-of-Sale Platform  
> **Operator:** Thawani (Internal Platform Team)  
> **Target Market:** Saudi Arabia (SAR currency, ZATCA Phase 2 compliance, Arabic-first)

---

## 1. What Is Wameed POS?

Wameed POS is a **multi-tenant SaaS Point-of-Sale platform** purpose-built for the Saudi Arabian market. It enables retail and food-service businesses of all sizes — from a single-cashier mini market to a multi-branch restaurant chain — to run their day-to-day operations from a single system.

The product has two distinct halves:

| Half | Who Uses It | Technology |
|---|---|---|
| **Store (Provider) Side** | Store owners, managers, cashiers, kitchen staff | Flutter 3.x Desktop app (Windows) + Store Owner Web Dashboard |
| **Platform (Admin) Side** | Thawani internal team only | Laravel 11 + Filament v3 admin panel |

The two halves talk through a **Laravel 11 REST API** backed by **PostgreSQL** on the cloud, with a **SQLite (Drift)** local database on each POS terminal for full offline operation.

---

## 2. Core Purpose

### Why it exists
Saudi businesses face a combination of compliance obligations (ZATCA Phase 2 e-invoicing), operational complexity (multi-branch chains, delivery platform proliferation), and a lack of Arabic-native tooling. Wameed POS solves all three:

1. **Compliance by default** — every completed sale automatically produces a ZATCA-compliant signed e-invoice with QR code, even offline.
2. **Operations in one place** — one screen handles walk-in sales, delivery platform orders (HungerStation, Jahez, Keeta, etc.), inventory, staff, and finances.
3. **Arabic-first design** — full RTL support, Arabic product names, Hijri calendar, Arabic-Indic numerals, and Arabic ZATCA invoices out of the box.

### Who it serves
- **Cashiers** — fast, ergonomic POS terminal optimised for their specific business type.
- **Store owners / managers** — back-office web dashboard for reports, staff, inventory, and integrations.
- **Thawani platform team** — admin panel to manage all stores, billing, packages, and platform configuration without code deployment.

---

## 3. Two Sides of the Platform

### 3.1 Provider Side (Store Operations)

Everything a store runs day-to-day.

#### POS Terminal
The cashier's primary screen. Runs as a native Flutter desktop app on Windows.

- **Product lookup**: barcode scan (USB HID), manual name/SKU search, quick-category grid
- **Weighable items**: reads weight from RS-232/USB scale, calculates price-by-weight
- **Cart management**: add, edit quantity, remove items; item-level and cart-level discounts; item notes/instructions
- **Open tickets / tabs**: keep orders open over time (dine-in tables, bar tabs)
- **Hold & recall**: park the current cart and resume it later (or on another register)
- **Payments**: cash (with change calculation), card (NearPay — tap/chip/swipe, supports Mada/Visa/Mastercard), split across multiple methods
- **Tip entry**: optional tip field on payment screen (configurable per business type)
- **Discounts**: percentage or fixed-amount, with manager-PIN override above cashier threshold
- **Void & return**: full or partial; with or without original receipt; exchange (return + new sale in one transaction)
- **Refunds**: to original payment method, cash, or store credit
- **Tax-exempt toggle**: requires customer tax ID or exemption certificate
- **Age verification**: prompt for restricted products (tobacco, pharmacy items) — configurable per product
- **Customer attachment**: link a customer profile for loyalty points, digital receipt, and purchase history
- **ZATCA Phase 2**: every completed sale generates a signed UBL 2.1 e-invoice with TLV QR code — B2C simplified invoice or B2B standard invoice
- **Offline-first**: full sales processing with zero internet; signed B2C invoices locally with 24-hour tolerance; B2B invoices queued until online
- **Receipts**: thermal ESC/POS print (Bixolon, Epson, Star, Xprinter) or digital delivery via WhatsApp / SMS / email
- **Customer-facing display**: secondary screen shows item list, total, and ZATCA QR code

#### Product & Catalog Management
- Products with name (AR/EN), SKU, multiple barcodes, images, description
- Category hierarchy (unlimited depth)
- Unit types: piece, kg, litre, custom
- Store-specific pricing overrides over organization-level prices
- Bulk import via CSV/Excel
- Variants (size, colour) and matrix items (size × colour grid)
- Combo/bundle products
- Product modifiers and add-ons (required/optional modifier groups, e.g. burger size, sauce choice, extra cheese)
- Weighable products with tare support
- Per-branch availability toggle
- Expiry date tracking
- Tax category per product (standard VAT 15%, zero-rated, exempt)
- Cost price tracking for margin analysis
- Supplier assignment per product
- Auto-generated internal barcodes (200-prefix) for unlabelled items
- Age-restriction flag per product

#### Inventory Management
- Real-time stock levels per branch (quantity + weighted-average cost)
- Goods receipt: supplier, invoice number, cost price, batch number, expiry date
- Manual stock adjustments with reason codes (damage, theft, count correction, sample)
- Stock transfers between branches: request → approve → ship → receive workflow
- Purchase orders: create POs, link to goods receipt on arrival
- Full and partial stocktake: create count → staff counts → compare expected vs actual → generate adjustments
- Low-stock, out-of-stock, and excess-stock alerts (configurable thresholds per product per branch)
- Expiry alerts (configurable days-before-expiry)
- Recipes / Bill of Materials: define raw ingredient requirements per finished product; auto-deduct ingredient stock on sale (for food-service)
- Waste tracking: record spoiled/expired/broken items with reason and cost impact
- Batch/lot tracking with FEFO (first-expiry-first-out) logic
- Stock movement history log (full audit trail)
- Multi-unit conversions (e.g. case → piece, kg → gram)
- Stock valuation report

#### Order Management
- Orders created automatically on POS sale completion; manual for phone/web orders
- Order lifecycle: new → preparing → ready → dispatched → delivered / picked_up / completed
- Kitchen Display System (KDS): order queue for kitchen staff by status column
- Kitchen station routing: route specific items to specific prep stations (grill, fryer, drinks, etc.)
- Table management for dine-in: floor map with multiple floors/zones, occupancy status, seating capacity, timer-per-table
- Table reservation system: date/time, party size, customer info, auto-release on no-show
- Waitlist management: SMS/push notification when table is ready
- Split bill / merge tables / transfer between servers
- Takeaway / pickup order flow
- Pre-orders and scheduled orders (future date/time fulfilment)
- Return processing: full or partial returns against a previous order; restores stock, issues refund
- Exchange processing: return + replacement in a single transaction
- Order source tracking: POS, Thawani app, HungerStation, Jahez, phone, web
- Receipt reprint for any historical order

#### Payments & Finance
- Cash with change calculation and SAR rounding (nearest 0.25 SAR)
- Card payments via NearPay terminal (Mada, Visa, Mastercard — tap/chip/swipe)
- Split payments across multiple methods
- Gift card issue and redemption
- Store credit: issue, redeem, balance inquiry
- Customer deposits / down payments on special orders or layaway
- Loyalty points: earn on purchase, redeem as partial payment
- Coupon and voucher redemption
- Cash drawer management: opening float, mid-shift cash drops, close-of-day count, expected vs actual variance, Z-report
- Expense tracking: petty cash and store expenses by category
- End-of-day accounting summary (cash, card, credit, expenses, net revenue)
- VAT breakdown on all receipts and reports
- Export to accounting software: QuickBooks, Xero, Qoyod (Saudi), or CSV

#### Reports & Analytics
- Sales dashboard: today's revenue, transaction count, average basket, top products, hourly graph
- Sales reports by day/week/month/custom range, by product, category, cashier, payment method, order source
- Product performance: best sellers, slow movers, margin analysis, category contribution
- Inventory reports: stock valuation, turnover, shrinkage, waste analysis, expiry, low stock
- Staff performance: transactions per cashier, average value, void/return rate, shift summaries
- Financial reports: daily P&L, cash flow, expense summary, payment method breakdown, delivery commission reconciliation
- Customer reports: top customers, repeat purchase rate, loyalty summary, acquisition trend
- Tip report: tips collected per employee, pooling breakdown, payout
- Commission report: commission earned per role/product/category
- VAT report (ready for GAZT submission)
- ZATCA e-invoice log and compliance report
- Custom date ranges with period-over-period comparison and branch filtering
- Export: PDF, CSV, Excel
- Scheduled auto-email reports (daily/weekly/monthly)

#### Customer Management
- Customer profiles: name, phone, email, address, loyalty balance, store credit balance
- Full purchase history per customer
- Loyalty points earn/redeem
- Customer groups for targeted promotions
- Digital receipts via WhatsApp or email
- Promotional messages to customer segments
- Customer notes (internal notes on preferences, allergies, etc.)
- Post-sale satisfaction rating (optional, via digital receipt link)

#### Promotions & Coupons *(package-dependent)*
- Percentage or fixed-amount discounts
- Buy-X-get-Y promotions
- Minimum cart value promotions
- Time-limited and date-range promotions
- Happy hour / time-based automatic pricing (e.g. 20% off pastries after 8 PM)
- Product-specific and category-specific deals
- Coupon codes (single-use or multi-use)
- Bundle pricing
- Flash sale toggle
- Promo stacking rules and usage cap per coupon
- Menu scheduling: auto-switch active menu by time of day (breakfast/lunch/dinner)

#### Third-Party Delivery Integrations
- Connect to: HungerStation, Keeta, Jahez, Noon Food, Ninja, Mrsool, The Chefz, Talabat, ToYou
- API credentials stored per platform (AES-256 encrypted)
- Toggle each platform on/off independently
- Outbound sync: product additions, updates, deletions, and bulk menu push auto-sent to all enabled platforms
- Inbound orders: received via auto-generated API key + webhook endpoint (unique per provider per platform)
- Real-time sync log with status and error details
- Manual force-sync / full menu push button

#### ZATCA Phase 2 Compliance
- Full UBL 2.1 XML invoice generation (B2B standard, B2C simplified)
- ECDSA cryptographic signing with XAdES-BES
- 9-tag TLV QR code encoding
- Invoice hash chain (PIH — previous invoice hash)
- Real-time clearance for B2B invoices; near-real-time reporting for B2C
- EGS device onboarding and certificate management
- Offline B2C signing with 24-hour tolerance; B2B queue synced when online
- ZATCA compliance report and audit log

#### Staff & User Management
- Invite staff by phone/email; assign role and branch
- PIN login for fast POS access; biometric login (hardware-dependent)
- Active/suspended account states; usage meter (e.g. "8/10 cashier slots used")
- Activity log and shift history per staff member
- Employee time clock: clock in/clock out with total hours
- Employee scheduling: weekly shift roster, availability, shift swap requests
- Employee commissions: configurable rules per role, product, or category
- Tip management: tip entry at payment, tip pooling configuration (equal split, weighted, front-of-house only), per-employee payout report

#### Roles & Permissions

| Role | Key Access |
|---|---|
| **Owner** | Full store access, billing, integrations |
| **Chain Manager** | All branches read + limited write |
| **Branch Manager** | Full access to assigned branch |
| **Cashier** | POS terminal, own shift only |
| **Inventory Clerk** | Products and stock management |
| **Accountant** | Reports and finance, no sales entry |
| **Kitchen Staff** | Kitchen display view only |
| **Custom Role** | Provider-defined permission combination *(Professional/Enterprise)* |

- Custom Role Builder: create unlimited custom roles with granular permission combinations *(Professional/Enterprise)*
- Cashier discount limit: override requires manager PIN
- All sensitive actions audit-logged (actor, role, IP, timestamp)

#### Barcode Label Printing
- Print labels from POS terminal or back-office
- Custom label designer: logo, AR/EN name, price, barcode, expiry
- Batch printing by product range or quantity
- Weighable-item barcodes (21/22-prefix with embedded weight)
- Price-embedded barcodes (23/24-prefix with embedded price)
- PDF export for label sheets
- Supported printers: Zebra ZD-series, TSC, Bixolon, Xprinter
- Barcode types: Code128, EAN-13, QR, DataMatrix
- Print history and audit trail

#### POS Interface Customization
- Layout variants per business type (Supermarket: 5 layouts; Restaurant: 3 layouts; etc.)
- Handedness: right-hand / left-hand / centered — action area moves to dominant-hand side
- RTL-aware: Arabic locale automatically mirrors handedness
- Font sizes: small (0.85×), medium (1×), large (1.2×), extra-large (1.5×)
- Themes: Light Classic, Dark Mode, High Contrast, Thawani Brand, Custom (provider hex colours)
- Custom branding: logo, primary/accent colours, receipt header/footer text
- Settings cascade: user preference → store default → platform default

#### Language & Localization
- Full Arabic and English with one-tap language toggle
- RTL/LTR interface switching
- Arabic product names, categories, and receipts
- SAR currency formatting (3 decimal places, symbol ﷼)
- Arabic-Indic numeral option (١٢٣ vs 123)
- Hijri / Gregorian calendar selector
- Arabic ZATCA-compliant e-invoices

#### Hardware Support
- Receipt printers: Bixolon, Epson, Star, Xprinter — USB, Serial, Network, Bluetooth
- Barcode scanners: USB HID (plug-and-play), Bluetooth
- Label printers: Zebra ZD-series, TSC, Bixolon
- Cash drawers (RJ11 kick via printer)
- Weighing scales: Mettler-Toledo, Dibal — RS-232 / USB serial
- Customer pole displays
- Self-checkout kiosk mode (touch-only, no cashier)

#### Offline / Online Sync
- Full POS operation with zero internet (sales, returns, ZATCA signing, printing)
- Local SQLite database mirrors full catalog, inventory, and transactions
- Sync priority queue: transactions → inventory → products → settings
- Conflict resolution: last-write-wins with server authority for master data
- Exponential backoff retry on sync failures
- Visual online/offline indicator with pending sync count

#### Business Type Onboarding
- Choose business type on first setup: Supermarket, Mini Market, Restaurant, Café, Fast Food, Bakery, Pharmacy, Cosmetics, Flower Shop, Gift Shop, Electronics, Jewellery, Clothing, Mobile Phone, Pet Store, Bookstore, and more
- Selection determines: default POS layout, enabled features, category templates, receipt format, and specialised workflows
- Business type can be changed later from settings

#### Industry-Specific Workflows
- **Pharmacy**: prescription vs OTC separation, insurance claim integration, drug interaction warnings, controlled substance tracking, expiry display, medication label printing, age verification
- **Jewellery**: live gold rate feed, weight-based pricing per karat, making charge + stone value breakdown, GIA certification management, buy-back tracking, appraisal, layaway
- **Mobile Phone Shop**: IMEI/serial number tracking per unit, warranty management, trade-in flow, instalment options, accessory bundling, repair work orders (intake → diagnose → repair → test → return)
- **Flower Shop**: occasion-based browsing, build-your-own arrangement, greeting card message, add-ons, delivery scheduling, freshness tracking

#### Thawani Marketplace Integration
- Receive and fulfil Thawani delivery orders directly in the POS
- Two-way product/price/stock sync with Thawani marketplace
- Incoming Thawani orders appear in the unified order screen
- Available as a paid add-on

#### Other Provider Features
- **Accounting Integration**: export to QuickBooks, Xero, Qoyod (Saudi), or CSV
- **Backup & Recovery**: scheduled automated backups; full restore capability
- **Auto-Updates**: in-app update prompt with release notes; staged rollouts from platform
- **Accessibility**: font size scaling, high-contrast mode, screen reader support, keyboard navigation
- **Mobile Companion App**: store owners can view sales, inventory, and alerts on mobile
- **Store Owner Web Dashboard**: full browser-based back-office — reports, staff, inventory, settings — without opening the desktop POS
- **Subscription & Billing (self-service)**: view current plan, usage meters, upgrade/downgrade, invoice history, contextual upgrade prompts when limits are reached
- **Notifications**: in-app popup, push (FCM/APNs), SMS, email, WhatsApp Business, webhook; configurable per event and per user; quiet hours with critical-alert override

---

### 3.2 Platform Side (Thawani Internal Admin)

Everything the Thawani team uses to operate the SaaS platform itself.

#### Platform Roles

| Role | Key Access |
|---|---|
| **Super Admin** | Full platform control — stores, billing, infrastructure, all settings |
| **Platform Manager** | Manage providers, approve registrations, assign packages |
| **Support Agent** | Read-only view of any provider account, respond to tickets |
| **Finance Admin** | Billing, subscriptions, invoices, package pricing |
| **Integration Manager** | Create/edit third-party platform configs, test connections |
| **Sales** | View store pipeline, create trial accounts, apply discounts within limits |
| **Viewer** | Read-only dashboard and reports |
| **Custom Role** | Admin-defined permission combination |

All role changes are audit-logged. Two-factor authentication is enforced for all platform admin accounts.

#### Provider Management
- List all registered stores/chains with search and filter
- View full store profile: owner details, active plan, branches, staff count, usage
- Approve or reject new provider registrations
- Suspend / reactivate any provider account
- Impersonate a store account for support investigation
- Live usage metrics per store (cashiers, products, orders, sync status)
- Assign or change subscription package; apply per-store limit overrides
- Internal notes and support history per provider
- Manual store onboarding on behalf of a provider
- Cancellation reason tracking

#### Package & Subscription Management
- **Package Builder** — create/edit subscription plans with:
  - Name (AR/EN), slug, monthly price, annual price
  - Feature toggles: kitchen display, advanced analytics, custom themes, full API access, white-label, multi-branch, loyalty program, advanced coupons, third-party integrations, inventory expiry tracking, recipe inventory, modifier groups, employee scheduling, accounting export, tip management, reservation system, etc.
  - Hard limits: max cashiers, max terminals, max products, max branches, max delivery platforms, price-per-extra-unit above limit
  - "Most Popular" badge, sort order for pricing page
- Grace period and trial period configuration per plan
- Feature-level sub-limits (e.g. max 3 delivery platforms on Professional)
- View and manage all active subscriptions; change plans, apply credits, cancel, resume
- Manual invoice generation and refund processing
- Subscription discount codes and coupon management
- Add-on management: Thawani integration, white-label, API access, accounting export, reservation system
- Pricing page content driven by package data

#### Third-Party Delivery Platform Management
- Add any new delivery platform **without code deployment**
- Per-platform configuration: name, logo, slug, auth method, custom key field definitions, operation endpoints, inbound webhook path, request field mapping
- Enable/disable platforms globally
- Test connectivity to any platform endpoint
- Monitor platform-wide sync health and error rates

#### Notification Template Management
- Manage notification message templates for all system events in Arabic and English
- Variable tokens per template (e.g. `{{platform}}`, `{{order_id}}`, `{{total}}`, `{{store_name}}`)
- Preview rendered notification before saving
- Templates apply across all providers on the platform

#### POS Layout & Theme Management
- Manage layout templates per business type (add, edit, reorder, disable)
- Set default layout per business type
- Manage handedness and font size platform-wide defaults
- Theme management: create, edit, deactivate theme presets; add new custom themes
- Control which customisation options are visible per package tier

#### Platform Analytics & Reporting
- MRR, ARR, and revenue by package tier
- Total active stores, trials, churn rate, subscription lifecycle metrics
- Platform-wide orders processed (daily/monthly/yearly)
- Top-performing stores by GMV
- Feature adoption rates
- Third-party integration usage and delivery sync error rates
- Support ticket volume and resolution time
- Geographic distribution of stores (map view)
- ZATCA compliance rate across all stores
- System health status dashboard
- Error/crash report aggregation
- API usage metrics (requests, latency, error rate)
- Real-time activity feed (recent signups, payments, tickets)
- Notification delivery analytics (sent, delivered, opened per template)

#### Support Ticket System
- View and manage all provider-submitted tickets
- Assign tickets to support agents; internal notes (not visible to provider)
- Status workflow: open → in-progress → resolved → closed
- Priority levels: low, medium, high, critical
- SLA tracking per ticket
- Canned responses library with shortcuts (e.g. `/greeting`)
- Knowledge base / help articles management
- Support analytics: ticket volume, response times, resolution rates, agent performance
- Store remote access for troubleshooting (impersonate + device view)

#### Billing & Finance Admin
- View all subscriptions: active, trial, grace period, cancelled
- Invoice history per provider with manual invoice generation
- Process refunds and credits
- Failed payment handling and retry rules
- Revenue breakdown dashboard by package tier
- Upcoming renewals list
- Payment gateway credentials management (Thawani Pay / Stripe)
- Hardware sales tracking (POS terminals, printers, scanners)
- Implementation / training fee tracking per store

#### Security & Audit (Platform)
- All admin actions logged: actor, role, IP, action, timestamp
- Two-factor authentication enforced for all admin accounts
- IP allowlist for admin panel access
- Suspicious activity alerts (brute-force logins, bulk data exports)
- IP blocklist management
- Device trust management (trusted devices per admin account)
- Security event investigation and resolution workflow
- View and revoke active platform admin sessions
- Encrypted storage of all platform-level secrets

#### System Configuration
- ZATCA environment toggle (sandbox / production) and API credentials
- Payment gateway credentials and webhook URL management
- SMS provider settings (Unifonic / Taqnyat / Msegat)
- Email provider settings (SMTP / Mailgun / SES)
- FCM / APNs push notification credentials
- WhatsApp Business API configuration
- Maintenance mode toggle with provider-facing banner message
- Feature flags for gradual rollouts to provider subsets
- Global VAT rate and currency/locale defaults
- Accounting integration settings (QuickBooks, Xero, Qoyod)
- Tax exemption category management
- Age-restricted product category management

#### App & Update Management
- Manage Flutter desktop app release versions (Windows, macOS)
- Minimum supported version setting (force-update below this version)
- Force-update flag per version; staged rollout percentage
- Release notes / changelog (AR/EN) shown in-app on update prompt
- Update channel management: stable, beta
- Rollback capability: revert stores to a previous version
- Auto-rollback trigger on crash-loop detection
- Update statistics: adoption rate, pending updates, failed installs

#### User Management (Cross-Store)
- List all users across all stores by email/phone
- Reset passwords / force password change
- Disable/enable individual user accounts
- View user activity logs
- Super admin team management (invite, roles, deactivate)

#### Provider Roles & Permissions Management
- Define the master list of provider-side permissions (e.g. `pos.sell`, `pos.void`, `pos.discount`, `inventory.adjust`, `reports.view`)
- Manage default role templates shipped to every new provider
- Enable/disable the Custom Role feature per package tier
- Set maximum number of custom roles per package tier
- Audit log of all role and permission changes across providers

#### Platform Announcements
- Send announcements to all stores or filtered by plan/region
- Types: info, warning, maintenance, update
- Scheduled start/end date with in-app banner display
- Payment reminder automation for upcoming/overdue renewals
- ZATCA deadline alerts

#### Infrastructure & Operations
- Queue worker health dashboard (Laravel Horizon)
- Background job failure alerts and manual retry controls
- Cache management (Redis flush, key inspection)
- Storage management: product images, receipts, backup files
- Automated database backup status, schedule, and restore controls
- Server health metrics: CPU, memory, disk, queue depth

#### Content & Onboarding Management
- Manage business type options shown during onboarding
- Manage POS layout templates per business type
- Manage onboarding flow steps and instructions
- Help articles and integration guides per third-party platform
- Public pricing page content (package names, feature bullet lists, FAQs)

---

## 4. How It Works — End-to-End Flow

### 4.1 A Cashier Completes a Sale

```
1. Cashier opens their shift (enters opening float)
2. Cashier scans or searches for products → items added to cart
3. System checks stock availability (local SQLite) and applies promotions/discounts automatically
4. Cashier attaches customer profile (optional) and applies any discount or coupon
5. Cashier selects payment method(s) → cash/card/split
   - Card: NearPay SDK sends payment to terminal → approved → transaction continues
   - Cash: system calculates change due
6. On payment confirmation:
   a. Transaction record saved to local SQLite (offline-safe)
   b. Stock decremented in local SQLite
   c. ZATCA invoice generated and signed locally (ECDSA)
   d. Receipt printed (thermal) or sent digitally (WhatsApp/SMS/email)
   e. Cash drawer kicked open
7. Transaction added to sync queue → uploaded to cloud PostgreSQL when online
8. Loyalty points credited to customer (if attached)
```

### 4.2 A Delivery Order Arrives

```
1. Third-party platform (HungerStation/Jahez/etc.) posts order to webhook endpoint
   (unique URL per provider per platform, authenticated by API key)
2. Laravel receives and validates order → saves to database → creates Order record
3. POS desktop app receives order via real-time push or polling
4. Order appears in unified order screen alongside walk-in sales
5. Cashier (or kitchen staff on KDS) accepts or rejects the order
6. Kitchen prepares → order status updated → platform notified via API
7. On completion: stock decremented, ZATCA invoice generated
```

### 4.3 A New Store Onboards

```
1. Store owner signs up → registration pending approval in admin panel
2. Platform Manager reviews and approves → assigns subscription package
3. Store owner selects their business type (Restaurant, Pharmacy, Supermarket, etc.)
4. System pre-configures: POS layout, category templates, default receipt format
5. Owner invites staff, assigns roles; sets up hardware
6. Owner enters ZATCA credentials → EGS device provisioned with ZATCA API
7. Store goes live; feature access controlled by their subscription package
```

### 4.4 Platform Team Manages the Business

```
- Admin panel (Laravel + Filament) gives full visibility over all stores
- Finance Admin reviews MRR, ARR, invoices, failed payments
- Support Agent opens a store's account in read-only mode, responds to tickets
- Integration Manager adds a new delivery platform by filling out a form — no code
- Platform Manager adjusts package limits, approves/suspends stores
- System Configuration controls ZATCA environment, SMS/email providers, feature flags
```

---

## 5. Technology Stack

### Provider Side (Store)
| Layer | Technology |
|---|---|
| **POS Desktop App** | Flutter 3.x (Windows primary, macOS secondary) |
| **State Management** | Riverpod / flutter_bloc |
| **Local Database** | SQLite via Drift ORM |
| **HTTP Client** | Dio |
| **Card Payments** | NearPay SDK (tap/chip/swipe, Mada/Visa/Mastercard) |
| **Receipt Printing** | ESC/POS protocol (flutter_thermal_printer / esc_pos_printer) |
| **Scale Communication** | RS-232 / USB serial (flutter_libserialport) |
| **ZATCA Signing** | ECDSA secp256k1 via pointycastle (pure Dart) |
| **QR Code** | qr_flutter (TLV-encoded ZATCA QR) |
| **Security** | flutter_secure_storage (keys, PINs); AES-256 encryption |
| **Internationalization** | flutter_localizations + intl |

### Backend (API)
| Layer | Technology |
|---|---|
| **Framework** | Laravel 11 |
| **Database** | PostgreSQL (Supabase) |
| **Queue** | Laravel Horizon (Redis) |
| **Authentication** | Sanctum (API tokens) + Spatie Permission (RBAC) |
| **Admin Panel** | Filament v3 |
| **File Storage** | S3-compatible object storage |

### Infrastructure
| Concern | Approach |
|---|---|
| **Offline-first** | Full SQLite mirror on each POS terminal; sync queue with exponential backoff |
| **Multi-tenancy** | All store-level data scoped by `store_id`; org-level by `organization_id` |
| **Feature Gating** | Middleware checks `plan_feature_toggles` per request |
| **ZATCA** | Hybrid: local signing on POS; cloud submission + certificate management via API |

---

## 6. Subscription Tiers

Exact plan names and pricing are configured in the admin panel, but the gating model follows these tiers (indicative):

| Capability | Starter | Professional | Enterprise |
|---|---|---|---|
| Cashier accounts | Limited (e.g. 2) | More (e.g. 10) | Unlimited |
| Branches | 1 | Multiple | Unlimited |
| Custom roles | ✗ | ✓ | ✓ |
| Advanced analytics | ✗ | ✓ | ✓ |
| Delivery platform integrations | Limited | Up to N | Unlimited |
| Kitchen Display System | ✗ | ✓ | ✓ |
| Recipe / BOM inventory | ✗ | ✓ | ✓ |
| Employee scheduling | ✗ | ✓ | ✓ |
| Accounting export | ✗ | ✓ | ✓ |
| White-label | ✗ | ✗ | ✓ |
| Full API access | ✗ | ✗ | ✓ |

Features greyed-out at the limit show a contextual upgrade tooltip. Store owners self-serve upgrades/downgrades from their dashboard. The Thawani team can apply one-off overrides, credits, and discounts from the admin panel.

---

## 7. Key Design Decisions

| Decision | Reasoning |
|---|---|
| **Offline-first architecture** | Saudi stores frequently face connectivity issues; cashiers must never be blocked from completing a sale |
| **ZATCA on-device signing** | B2C invoices must be signed within 24 hours; waiting for the cloud is too risky in offline scenarios |
| **Flutter desktop (Windows)** | Single codebase for the POS app; hardware APIs (serial ports, HID) work natively on Windows; most POS hardware runs Windows |
| **Multi-tenant PostgreSQL** | All stores share a single database with row-level tenant scoping for cost efficiency and operational simplicity |
| **Filament v3 for admin panel** | Rapid admin UI development without a separate frontend project; Filament's resource model maps directly to the domain |
| **No-code delivery platform configuration** | New platforms (government mandated or market entrants) can be onboarded by the Integration Manager without software release |
| **Business type selection at onboarding** | A pharmacy and a flower shop need fundamentally different POS layouts and features; the selection gates irrelevant features from day one |
| **SAR with 3 decimal places** | Matches Saudi currency standard; ZATCA invoices require halala-level precision |

---

## 8. Compliance & Security

- **ZATCA Phase 2**: full UBL 2.1 e-invoicing with ECDSA signing, hash chain, real-time clearance (B2B), and near-real-time reporting (B2C)
- **VAT**: 15% standard rate enforced on all taxable transactions; zero-rated and exempt categories configurable
- **Data isolation**: every store's data is isolated by `store_id` / `organization_id`; API endpoints validate ownership on every request
- **Encryption**: AES-256 for delivery platform API credentials; flutter_secure_storage for ZATCA private keys and PIN hashes
- **RBAC**: Spatie Permission on the backend; per-route middleware; permission checks in every service method
- **Audit logging**: all sensitive actions logged with actor, role, IP, and timestamp — on both provider and platform sides
- **Admin panel hardening**: 2FA enforced, IP allowlist, session revocation, IP blocklist for known threats
- **OWASP Top 10**: multi-tenant IDOR prevention via explicit tenant scoping; no raw SQL; parameterised queries via Eloquent

---

## 9. Summary

Wameed POS is a vertically integrated SaaS platform that gives Saudi businesses a single system to sell, track, comply, and grow. The store side delivers a fast, offline-capable POS that generates ZATCA-compliant invoices, manages inventory, and integrates with every major Saudi delivery platform. The platform side gives the Thawani team complete control over every store, every subscription, and every platform capability — with no code deployment required for most operational changes.
