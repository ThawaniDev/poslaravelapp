# Wameed POS — Provider Features (Short Reference)

> Features available to store owners, managers, cashiers, and other provider-side roles.  
> Availability depends on the active subscription package.

---

## 🖥️ POS Terminal (Cashier)
- Barcode scan, manual search, and quick-category product lookup
- Weighable items (scale integration, price-by-weight)
- Cart management: add, edit qty, remove, hold & recall
- Open tickets / open tabs: keep an order open and add items over time (e.g. dine-in, bar tab)
- Multiple payment methods: cash, card, split payment
- Tip entry on payment screen (optional; configurable per business type)
- Cash drawer management with opening amount & cash count
- Discount application (amount or %, with manager-PIN override above threshold)
- Void & return transactions (full or partial)
- Returns without receipt (configurable policy: refund to store credit, exchange only, or deny)
- Exchange processing: return + new sale combined in one transaction
- Refunds to original payment method
- Tax-exempt transaction toggle (requires customer tax ID / exemption certificate)
- Age verification prompt for restricted products (e.g. tobacco, pharmacy items — configurable per product)
- Custom item notes / special instructions
- Attach customer to sale (loyalty, receipt delivery)
- ZATCA Phase 2 e-invoice generation with QR code (B2B & B2C)
- Offline-first: full sales processing without internet
- Automatic sync queue when connectivity restores
- Receipt print (thermal ESC/POS) and digital receipt (WhatsApp / SMS / email)
- Offline ZATCA signing: B2C invoices signed locally (24 h tolerance); B2B queued until online
- Customer-facing display support

---

## 📦 Product & Catalog Management
- Product creation: name (AR/EN), SKU, multiple barcodes, images, description
- Category hierarchy (unlimited depth)
- Unit types: piece, kg, litre, custom
- Store-specific pricing overrides over org-level prices
- Bulk product import (CSV / Excel)
- Product variants (size, colour, etc.) and matrix items (size × colour grid)
- Combo / bundle products
- **Product modifiers & add-ons**: required and optional modifier groups per product (e.g. burger size, sauce choice, extra cheese +3 SAR)
- Weighable products with tare support
- Product availability toggle per branch
- Expiry date tracking per product
- Tax category assignment per product (standard VAT, zero-rated, exempt)
- Cost price tracking for margin / profit analysis
- Supplier assignment per product (linked to supplier database)
- Auto-generate internal barcodes for unlabelled items (200-prefix)
- Age-restriction flag per product (triggers verification prompt at POS)

---

## 🏷️ Barcode Label Printing
- Print labels for any product from POS terminal or back-office
- Label templates: price tag, shelf label, product barcode
- Custom label designer (logo, AR/EN name, price, barcode, expiry)
- Batch printing (product range, qty per label)
- Weighable-item barcodes (21/22-prefix with embedded weight)
- Price-embedded barcodes (23/24-prefix with embedded price)
- Print history and audit trail (who printed, when, product, quantity)
- PDF export for label sheets (print on any regular printer)
- Supported printers: Zebra ZD-series, TSC, Bixolon, Xprinter
- Barcode types: Code128, EAN-13, QR, DataMatrix

---

## 📊 Inventory Management
- Real-time stock levels per branch
- Low-stock, out-of-stock, and excess-stock alerts (configurable thresholds)
- Expiry warning alerts (configurable days before expiry)
- Manual stock adjustments with reason codes
- Stock transfers between branches with approval workflow (request → approve → ship → receive)
- Purchase orders: create, send, receive goods; optional approval workflow for large orders
- Full inventory count (physical vs system reconciliation)
- Partial / cyclical counts (by category or section)
- Stock movement history log
- Serial number and batch number tracking per stock item / transaction
- Supplier management: supplier database with contacts, linked products, and order history
- Inventory valuation report
- Recipe / ingredient-level inventory (Bill of Materials): define ingredients per menu item, auto-deduct on sale *(restaurant / food-service)*
- Waste & spoilage tracking with reason codes and cost impact report

---

## 📋 Order Management
- Unified order screen: POS sales + delivery platform orders + Thawani orders
- Order statuses: new → preparing → ready → completed / cancelled
- Accept, reject, or flag incoming external orders
- Kitchen Display System (KDS): order queue for kitchen staff by status column
- Kitchen station routing: send specific items to specific prep stations (grill, fryer, drinks, etc.)
- Table management for dine-in: floor map (multiple floors/zones), table occupancy status, seating capacity, timer-per-table
- Table reservation system: date/time, party size, customer info, auto-release on no-show
- Waitlist management: queue customers when tables are full, SMS/push notification when table ready
- Split bill / merge tables / transfer table between servers
- Takeaway / pickup order flow (separate from dine-in)
- Pre-orders and scheduled orders (future date/time fulfilment)
- Partial fulfilment and item substitutions
- Print kitchen tickets per order

---

## 🚚 Third-Party Delivery Integrations
- Connect to: HungerStation, Keeta, Jahez, Noon Food, Ninja, Mrsool, The Chefz, Talabat, ToYou (and any future platform added by admin)
- Enter and store API credentials per platform (AES-256 encrypted)
- Toggle each platform on/off independently
- **Outbound sync**: product additions, updates, deletions, and bulk menu push auto-sent to all enabled platforms
- **Inbound orders**: received via auto-generated API key + webhook endpoint (unique per provider per platform)
- Simple integration guide shown per platform (endpoint URL, API key, sample request body)
- Real-time sync log with status (✅ OK / ❌ Error) and error details
- Manual force-sync / full menu push button

---

## 💳 Payments & Finance
- Cash with change calculation
- Card (tap-to-pay, chip, swipe) via payment terminal with NearPay
- Split payment across multiple methods
- Coupon and voucher redemption
- Loyalty points earn and redemption
- Gift card issue and redemption
- Customer store credit: issue, redeem, balance inquiry
- Customer deposits / down payments on special orders or layaway
- Payment terminal integration (Nexo-standard Saudi-approved terminals)
- Shift open / close with cash count and Z-report
- Cash discrepancy alerts
- VAT breakdown on receipts and all reports
- Expense tracking: record petty cash, store expenses by category (rent, utilities, supplies, etc.)
- End-of-day accounting summary (cash, card, credit, expenses, net)
- Export to accounting software: QuickBooks, Xero, Qoyod (Saudi), or CSV

---

## 📈 Reports & Analytics
- Daily / weekly / monthly / custom-range sales reports
- Revenue by product, category, branch, cashier
- Best-selling and slow-moving product reports
- Inventory valuation and stock-on-hand report
- Waste & spoilage report (total cost of waste by reason code)
- ZATCA e-invoice log and compliance report
- VAT report (ready for GAZT submission)
- Employee performance: sales per cashier, shift summaries, commissions earned
- Speed-of-service metrics: average time from order to completion (per order type, per station)
- Coupon and discount usage report
- Delivery platform order breakdown by source
- Cash flow report
- Tip report: tips collected, tip pooling breakdown, payout per employee
- Export to PDF, Excel, CSV

---

## 👥 Customer Management
- Customer profiles: name, phone, email, address, loyalty balance, store credit balance
- Full purchase history per customer
- Loyalty points earn / redeem
- Customer groups for targeted promotions
- Digital receipts via WhatsApp or email
- Promotional messages to customer segments
- Customer credit account / store credit: issue credit, track balance, use as payment method
- Customer deposits on special orders or layaway
- Customer feedback: post-sale satisfaction rating (optional, via digital receipt link)
- Customer notes (internal notes on preferences, allergies, etc.)

---

## 🎟️ Promotions & Coupons *(package-dependent)*
- Percentage or fixed-amount discounts
- Buy-X-get-Y promotions
- Minimum cart value promotions
- Time-limited and date-range promotions
- Happy hour / time-based automatic pricing (e.g. 20 % off pastries after 8 PM)
- Product-specific and category-specific deals
- Coupon codes (single-use or multi-use)
- Bundle pricing
- Flash sale toggle
- Promo stacking rules and usage cap per coupon
- Menu scheduling: auto-switch active menu by time of day (breakfast / lunch / dinner) *(restaurant/café)*

---

## 🖐️ POS Interface Customization
- **Layout variants** per business type:
  - Supermarket: barcode-scan optimised, touch-grid, split view, express checkout, self-checkout kiosk
  - Restaurant: table management, quick-order, kitchen display integration
  - Pharmacy, bakery, flower shop, gift shop, mobile phone shop, jewellery store
- **Handedness**: right-hand / left-hand / centered — action area moves to dominant hand side
- **RTL-aware**: Arabic locale automatically mirrors handedness correctly
- **Font sizes**: small (0.85×), medium (1×), large (1.2×), extra-large (1.5×)
- **Themes**: Light Classic, Dark Mode, High Contrast, Thawani Brand, Custom (provider hex colours)
- **Custom branding**: upload logo, set primary & accent colours, receipt header/footer text
- Settings cascade: user preference → store default → platform default

---

## 🔔 Notifications
- **Channels**: In-App popup, Push (FCM/APNs), SMS, Email, WhatsApp Business, Webhook
- **Events configurable per user / role:**
  - Order: new order, new external order, status changed, completed, cancelled, refund requested/approved, payment failed
  - Inventory: low stock, out of stock, expiry warning, excess stock, adjustment
  - Finance: daily summary, shift closed, cash discrepancy, large transaction, coupon overuse
  - System: went offline, sync failed, printer error, update available, license expiring, backup failed
  - Staff: login, unauthorised access attempt, discount applied, void transaction
- **Quiet hours**: define do-not-disturb window; critical alerts can override
- **Per-user preferences**: choose enabled channels and events independently
- **Configurable thresholds**: low-stock qty, large-transaction amount, expiry warning days
- Notification inbox (bell icon) with read/unread and history

---

## 🛡️ Roles & Permissions

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

- **Custom Role Builder** *(Professional / Enterprise package)*:
  - Create unlimited custom roles with a name and description
  - Pick individual permissions from a granular checklist grouped by category (POS, Inventory, Reports, Settings, Staff, etc.)
  - Assign custom roles to any staff member alongside branch assignment
  - Edit or delete custom roles at any time; changes propagate immediately to assigned users
  - Maximum number of custom roles governed by package tier
- Cashier discount limit: override requires manager PIN
- Cashier account count enforced by package limit (suspended accounts don't count)
- All sensitive actions audit-logged (actor, role, IP, timestamp)

---

## 👤 Staff & User Management
- Invite staff by phone/email; assign role and branch
- PIN login for fast POS access; biometric login (hardware-dependent)
- Active / suspended account states
- Usage indicator: e.g. "8 / 10 cashier slots used"
- Activity log and shift history per staff member
- Employee time clock: clock in / clock out per shift with total hours tracked
- Employee scheduling: weekly shift roster, availability management, shift swap requests
- Employee commissions: configurable commission rules per role, product, or category; commission earned report
- Tip management: tip entry at payment, tip pooling configuration (equal split, weighted, front-of-house only), per-employee tip payout report

---

## 💳 Subscription & Billing *(self-service)*
- View current plan, renewal date, monthly price
- Usage meters: cashiers, terminals, products, branches, platforms used
- Upgrade / downgrade plan
- Invoice history and PDF download
- Contextual upgrade prompts when a limit is reached
- Locked features shown greyed-out with plan upgrade tooltip

---

## 🌍 Language & Localization
- Full Arabic and English support with one-tap language toggle
- RTL / LTR interface switching
- Arabic product names, categories, and receipts
- Saudi Riyal (SAR) currency formatting
- Arabic-Indic numeral display option (١٢٣ vs 123)
- Hijri / Gregorian calendar selector
- Arabic ZATCA-compliant e-invoices

---

## 🖨️ Hardware Support
- Receipt printers: Bixolon, Epson, Star, Xprinter — USB, Serial, Network, Bluetooth
- Barcode scanners: USB HID (plug-and-play), Bluetooth
- Label printers: Zebra ZD-series, TSC, Bixolon
- Cash drawers (RJ11 kick via printer)
- Weighing scales: Mettler-Toledo, Dibal — RS-232 / USB serial
- Customer pole displays
- Self-checkout kiosk mode (touch-only, no cashier)

---

## ☁️ Offline / Online Sync
- Full POS operation with zero internet (sales, returns, ZATCA QR, printing)
- Local SQLite database stores full catalog, inventory, and transactions
- Sync priority queue: transactions → inventory → products → settings
- Conflict resolution: last-write-wins with server authority for master data
- Exponential backoff retry on sync failures
- Visual online/offline indicator; pending sync count displayed

---

## � Thawani Marketplace Integration
- Receive and fulfil Thawani delivery orders directly in POS
- Two-way product/price/stock sync with Thawani marketplace
- Incoming Thawani orders appear in unified order screen alongside POS sales
- Stock levels pushed to Thawani automatically on inventory change
- Available as a paid add-on (+100 SAR/mo)

---

## 🏪 Business Type Selection (Onboarding)
- Choose business type on first setup: Supermarket, Mini Market, Restaurant, Cafe, Fast Food, Bakery, Pharmacy, Cosmetics, Flower Shop, Gift Shop, Electronics, Jewelry, Clothing, Mobile Phone, Pet Store, Bookstore, and more
- Selection determines: default POS layout, enabled features, category templates, receipt format, and specialised workflows
- Business type can be changed later from settings

---

## 🏥 Industry-Specific Workflows *(per business type)*
- **Pharmacy**: prescription vs OTC separation, insurance claim integration, drug interaction warnings, controlled substance tracking, expiry display, medication label printing, age verification for restricted items
- **Jewelry**: live gold rate feed, weight-based pricing per karat, making charge + stone value breakdown, certification management (GIA), buy-back tracking, appraisal, layaway plans
- **Mobile Phone Shop**: IMEI / serial number tracking per unit, warranty management, trade-in flow, finance/instalment options, accessory bundling, data transfer service, work orders / repair tracking (intake → diagnose → repair → test → return)
- **Flower Shop**: occasion-based browsing, build-your-own arrangement, greeting card message, add-ons (chocolate, teddy, wrapping), delivery scheduling, freshness tracking
- **Bakery**: fresh-baked time indicators, remaining stock display, weighable items, custom cake orders, daily production tracking, wastage management, bulk orders
- **Gift Shop**: gift finder (recipient / occasion / budget), gift wrapping options, greeting cards, gift receipt (no price), seasonal themes
- **Restaurant / Café / Fast Food**: course-based ordering, kitchen fire timing, kitchen station routing, modifier groups (required / optional per item), 86 (out-of-stock) item flagging, rush orders, split bill / merge tables, table reservation, waitlist management, menu scheduling (breakfast / lunch / dinner), server assignment per table, tip management, drive-through mode *(fast food)*, speed-of-service metrics per station, takeaway / pickup flow, online order display

---

## 💾 Backup & Recovery
- **Local backup**: automatic every 4 hours; stored in app data; last 7 days retained
- **Cloud backup**: automatic daily at closing time; full weekly backup; 90-day retention
- **Pre-update backup**: always created before any app update
- **Manual backup**: on-demand from settings
- **Restore**: restore from any local or cloud backup from within the app
- **Crash recovery**: on restart, incomplete transactions detected and offered complete / void / hold actions
- **Database integrity check**: runs on startup; auto-fixes safe issues; flags critical issues for manual review

---

## 🔄 Auto-Updates
- Background update check on startup and every 4 hours
- Update channels: Stable (recommended) and Beta (early access)
- Update types: Critical (auto-install next launch), Major (user confirms), Minor (off-hours auto), Patch (silent)
- Download in background; signature verified before install
- Pre-update backup automatically created
- Rollback: last 3 versions kept; auto-rollback on crash loop; manual rollback available
- Release notes shown in-app (AR / EN)

---

## ♿ Accessibility
- **Keyboard shortcuts**: F1 Help, F2 New Sale, F3 Search, F4 Payment, F8 Hold, F9 Recall, Esc Cancel
- Full keyboard navigation for all POS functions
- High contrast mode
- Large text support (up to 200%)
- Large touch targets (minimum 44×44 px)
- Screen reader compatibility (semantic labels on all controls)
- Colour-blind friendly palette
- Reduced motion option
- Sticky keys support
- Clear, simple language; consistent navigation; visible focus indicators

---

## 📱 Mobile Companion App *(Manager On-the-Go)*
- Flutter app sharing 70 %+ code with desktop POS
- View sales reports and dashboards remotely
- Check real-time inventory levels
- Receive push alerts (low stock, large transactions, shift issues)
- Camera-based barcode scanning (inventory lookup / count)

---

## 🧾 ZATCA e-Invoicing Compliance
- Device onboarding flow: CSR generation → OTP verification → Compliance CSID → Production CSID
- Automatic invoice signing (ECDSA) with QR code on every sale
- Offline signing for B2C (24 h tolerance); B2B invoices queued until online
- ZATCA invoice log with per-invoice submission status (pending / submitted / accepted / rejected)
- Automatic retry with back-off for failed ZATCA submissions
- Sandbox ↔ production environment toggle

---

## 🌐 Store Owner Web Dashboard
- Browser-based management portal (accessible from any device)
- Product and category management
- Inventory overview and stock adjustments
- Sales reports and financial dashboards
- Staff management and role assignment
- Multi-store overview for chain owners
- Operates alongside desktop POS (not a replacement)

---

## 💱 Additional Nice-to-Have Features
- Multi-currency support (accept and display prices in multiple currencies)
- Customer loyalty tiers: Bronze → Silver (1 000 pts) → Gold (5 000 pts) → Platinum (10 000 pts) with tier-based earning multipliers
- Surcharge management: configurable surcharges per payment method (e.g. card surcharge)
- Price book management: named price lists (wholesale, retail, VIP) assignable to customer groups
- Appointment / booking system for service businesses (salon, barber, repair shop)

---

## �🔐 Security (Provider Side)
- Role-based access control on all screens and APIs
- PIN / biometric login per session with auto-lock on inactivity
- Audit log for all sensitive actions
- AES-256 encrypted storage of third-party credentials
- HTTPS-only API communication
- ZATCA private key stored in secure device storage (not exportable)
