# POS Terminal (Cashier) — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Core Sales & Transaction Processing  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · ESC/POS Printing · pointycastle (ZATCA) · NearPay · Dio  

---

## 1. Feature Overview

The POS Terminal is the **primary cashier-facing interface** of the Thawani POS system. It is the screen that store employees interact with for the majority of their workday — scanning products, building a cart, accepting payments, printing receipts, and generating ZATCA-compliant e-invoices. Everything runs offline-first: the cashier can complete a full sale, print a signed receipt, and open the cash drawer even with zero internet connectivity.

### What This Feature Does
- **Product lookup** — barcode scan (USB HID), manual search by name/SKU, and quick-category grid taps
- **Weighable items** — reads weight from RS-232 / USB serial scale and calculates price-by-weight
- **Cart management** — add, change quantity, remove, apply item-level or cart-level discounts, add notes/instructions per item
- **Open tickets / open tabs** — keep an order open and add items over time (dine-in, bar tab)
- **Hold & recall** — park the current cart and recall it later (or on another register)
- **Payments** — cash (with change calculation), card (via NearPay payment terminal), split payment (cash + card, etc.)
- **Tip entry** — optional tip field on the payment screen; configurable per business type
- **Cash drawer management** — opening cash amount, mid-shift cash drops, close-shift cash count, Z-report
- **Discounts** — percentage or fixed-amount, item-level or cart-level; manager-PIN override when discount exceeds cashier threshold
- **Void & return** — full or partial voids, returns with or without receipt, exchange processing (return + new sale in one transaction)
- **Refunds** — to original payment method (cash refund, card reversal)
- **Tax-exempt toggle** — requires customer tax ID or exemption certificate; removes VAT from the sale
- **Age verification** — prompt for restricted products (tobacco, pharmacy items, etc.) — configurable per product
- **Customer attachment** — link a customer profile to the sale for loyalty points, receipt delivery, and purchase history
- **ZATCA Phase 2** — every completed transaction generates a signed e-invoice (B2C simplified or B2B standard) with a ZATCA-compliant QR code
- **Offline-first** — full sales processing without internet; sync queue pushes transactions to the cloud when connectivity restores
- **Offline ZATCA signing** — B2C invoices signed locally with 24-hour tolerance; B2B invoices queued until online
- **Receipts** — thermal ESC/POS print, or digital delivery via WhatsApp / SMS / email
- **Customer-facing display** — secondary screen shows item-by-item total and QR code

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Product & Catalog Management** | Product lookup, pricing, variants, modifiers, tax category, age-restricted flag |
| **Inventory Management** | Stock decrement on sale, stock increment on return; availability check |
| **ZATCA Compliance** | Invoice signing keys, QR code generation, device onboarding status |
| **Offline/Online Sync** | Sync queue for transactions, offline DB for catalog and stock |
| **Hardware Support** | Printer, scanner, scale, cash drawer, customer display connections |
| **Roles & Permissions** | Cashier can sell; discount above threshold requires manager PIN |
| **Payments & Finance** | Payment methods, gift card redemption, store credit, coupon application |
| **Customer Management** | Link customer to sale, loyalty earn, digital receipt |
| **POS Interface Customization** | Layout variant, theme, handedness, font size |
| **Language & Localization** | AR/EN strings, RTL layout, numeral format, currency format |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Reports & Analytics** | All revenue/sales data originates from transactions created here |
| **Order Management** | POS-originated orders feed into the unified order screen |
| **Payments & Finance** | Shift Z-report, accounting summary, expense tracking tied to pos_sessions |
| **Promotions & Coupons** | Promotions and coupons are applied during cart building here |
| **Staff & User Management** | Shift history, commissions, tips — all calculated from POS terminal activity |
| **ZATCA Compliance** | ZATCA invoice records are created at sale completion |
| **Thawani Integration** | Thawani delivery orders can be fulfilled through this terminal |
| **Delivery Integrations** | External platform orders accepted and completed here |

### Features to Review After Changing This Feature
1. **Reports & Analytics** — any change to transaction schema or payment flow affects report queries
2. **ZATCA Compliance** — invoice generation must remain valid if transaction structure changes
3. **Offline/Online Sync** — transaction serialisation format must stay consistent with sync engine
4. **Inventory Management** — stock decrement/increment logic is tightly coupled to sale/return
5. **Payments & Finance** — shift totals and Z-report derive from payment records
6. **Promotions & Coupons** — discount application logic must be resolved before total calculation

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | Local SQLite ORM — offline storage of catalog, stock, transactions, sync queue |
| **riverpod** / **flutter_bloc** | State management — cart state, session state, payment flow state |
| **dio** | HTTP client for REST API sync with server (Laravel backend) |
| **esc_pos_printer** | ESC/POS command generation for thermal receipt printers |
| **flutter_thermal_printer** | Additional thermal printer driver support (Bixolon, Xprinter) |
| **pointycastle** | ECDSA signing (ZATCA Phase 2) — pure Dart cryptographic library |
| **qr_flutter** | QR code widget for ZATCA TLV-encoded QR on screen and receipt |
| **flutter_libserialport** | Serial port communication for weighing scales (RS-232 / USB-serial) |
| **nearpay_flutter** (or NearPay SDK) | Card payment terminal integration (tap-to-pay, chip, swipe) |
| **flutter_secure_storage** | Secure storage for ZATCA private keys, session tokens, PINs |
| **intl** | Number formatting (SAR currency), date formatting, Arabic numeral conversion |
| **uuid** | UUID generation for transaction IDs, ZATCA invoice UUIDs |
| **archive** | ZIP compression for backup operations |
| **cryptography** | AES-256 encryption/decryption for sensitive fields, Argon2 for PIN hashing |

### 3.2 Technologies
- **Flutter 3.x Desktop (Windows primary)** — the POS terminal is a native Windows desktop app
- **Dart** — business logic, price calculations, VAT computation, cart management
- **SQLite (via Drift)** — local offline database; all products, inventory, transactions stored locally
- **ESC/POS Protocol** — thermal receipt printer command language (Bixolon, Epson, Star, Xprinter)
- **ECDSA (secp256k1)** — ZATCA Phase 2 invoice digital signing
- **TLV (Tag-Length-Value) encoding** — ZATCA QR code data format
- **REST API (JSON over HTTPS)** — sync with Laravel backend; Dio client with offline queue
- **NearPay** — Saudi-approved payment terminal SDK for card payments
- **USB HID** — barcode scanner input (keyboard emulation — no special driver)
- **RS-232 / USB-serial** — weighing scale data reading via flutter_libserialport
- **RJ11 kick** — cash drawer open command routed through receipt printer port

---

## 4. Screens

### 4.1 POS Main Screen (Cashier)
| Field | Detail |
|---|---|
| **Route** | `/pos` (default after PIN login) |
| **Purpose** | Primary sales interface — product lookup, cart, payment |
| **Layout Zones** | *Adapts per business type (see POS Interface Customization feature)* |
| — Left/Right (configurable by handedness) | **Product area**: barcode input field, category tabs, product grid/list |
| — Right/Left (opposite) | **Cart area**: item list (name, qty, price, discount, notes), subtotal, VAT, total, action buttons |
| — Bottom bar | Quick actions: Hold (F8), Recall (F9), Discount, Customer, New Sale (F2), Pay (F4) |
| **Barcode Input** | Auto-focused text field; scanner input triggers lookup. Manual entry + Enter also works. If product has variants or modifiers, a selection popup appears before adding to cart |
| **Product Grid** | Filterable by category; shows image, name (AR/EN based on locale), price. Tap to add. Long-press for detail |
| **Cart List** | Scrollable item list; swipe to remove; tap qty to edit. Each row shows: product name, qty × unit price, line total, discount badge, notes icon |
| **Totals Panel** | Subtotal, Discount total, VAT (15%), Grand Total. Updated in real-time as cart changes |
| **Customer Badge** | If customer attached: shows name, loyalty points, tier. Tap to change or remove |
| **Offline Badge** | If offline: amber chip "Offline — N pending sync" |
| **Keyboard Shortcuts** | F1 Help, F2 New Sale, F3 Search, F4 Pay, F8 Hold, F9 Recall, Esc Cancel, +/− qty, Del remove item |
| **Access** | `pos.sell` permission (Cashier, Branch Manager, Owner) |

### 4.2 Payment Screen
| Field | Detail |
|---|---|
| **Route** | Modal/overlay from POS Main (F4 or Pay button) |
| **Purpose** | Select payment method(s), enter tendered amount, complete the sale |
| **Sections** | |
| — Summary | Items count, Subtotal, Discount, VAT, Total |
| — Payment Methods | Cash, Card, Split, Gift Card, Store Credit, Loyalty Points, Voucher. Each is a tab/button |
| — Cash Tab | Numpad for tendered amount; quick buttons (exact, +5, +10, +20, +50, +100, +200, +500); Change amount shown immediately |
| — Card Tab | "Send to terminal" button → NearPay SDK initiates payment; shows waiting spinner; result returned (approved/declined) |
| — Split Tab | Add multiple payment legs; each with method + amount; remaining balance shown |
| — Tip Entry | Optional tip field (shown when enabled in store settings); added to card amount or recorded separately for cash |
| — Coupon/Voucher | Input field to enter coupon code; validates and applies discount to cart |
| — Gift Card | Scan or enter gift card code; shows balance; apply partial or full |
| — Store Credit | Shows customer credit balance (requires customer attached); apply amount |
| — Loyalty Redeem | Shows loyalty points balance; enter points to redeem → SAR conversion shown |
| **Complete Button** | Finalises the transaction: creates transaction record, decrements stock, generates ZATCA invoice, triggers receipt print, opens cash drawer (if cash), awards loyalty points |
| **Access** | `pos.sell` |

### 4.3 Shift Management Screen
| Field | Detail |
|---|---|
| **Route** | `/pos/shift` (automatically shown if no open session for this register) |
| **Purpose** | Open and close shifts; cash counting |
| **Open Shift** | Enter opening cash amount → creates pos_session record → navigates to POS Main |
| **Close Shift** | Triggered from POS Main menu → shows: expected cash (system-calculated), actual cash (manual count per denomination), difference; card total, split total; total sales, returns, voids; prints Z-report |
| **Z-Report Print** | Auto-prints shift summary on receipt printer: shift number, cashier, date/time, sales breakdown by payment method, voids, returns, net sales, VAT collected, cash expected vs counted |
| **Access** | `pos.shift_open`, `pos.shift_close` |

### 4.4 Return / Refund Screen
| Field | Detail |
|---|---|
| **Route** | Modal from POS Main → "Return" action |
| **Purpose** | Process full or partial return of a previous sale |
| **Lookup** | Enter receipt number or scan receipt barcode → loads original transaction items |
| **Return Without Receipt** | Configurable policy: refund to store credit only, exchange only, or deny. Enabled from store settings |
| **Item Selection** | Checkboxes to select items and quantity to return |
| **Refund Method** | Original payment method (auto-selected); override option for cash refund on card sale (requires manager PIN) |
| **Exchange Mode** | Return selected items → immediately opens new sale with return credit applied as payment |
| **Completion** | Creates return transaction (negative), generates ZATCA credit note, increments stock, triggers refund on card terminal if applicable |
| **Access** | `pos.return` (Branch Manager+); Cashier cannot return by default |

### 4.5 Hold / Recall Screen
| Field | Detail |
|---|---|
| **Route** | Hold: F8 from POS Main; Recall: F9 → modal listing held carts |
| **Purpose** | Temporarily park a sale and serve another customer |
| **Hold** | Saves current cart to `held_carts` table with register_id and cashier_id; clears POS Main for new sale |
| **Recall List** | Shows all held carts for this store: customer name (if attached), item count, total, held time, who held it. Select to recall → restores cart to POS Main |
| **Auto-expiry** | Held carts older than 24 hours are auto-deleted by background task |
| **Access** | `pos.sell` |

### 4.6 Tax-Exempt Sale Dialog
| Field | Detail |
|---|---|
| **Route** | Modal triggered by "Tax Exempt" toggle on POS Main |
| **Purpose** | Remove VAT from the sale; capture exemption documentation |
| **Fields** | Exemption type (diplomatic, government, export, registered charity), Customer Tax ID, Certificate Number, Notes |
| **Effect** | All items in cart recalculated at 0% VAT; `tax_exemptions` record created; ZATCA invoice tagged as exempt |
| **Access** | `pos.tax_exempt` (Branch Manager+) |

### 4.7 Age Verification Prompt
| Field | Detail |
|---|---|
| **Route** | Auto-popup when an age-restricted product is scanned |
| **Purpose** | Confirm that customer meets age requirement before adding to cart |
| **Content** | "This product requires age verification. Is the customer 18+ years old?" with Confirm / Cancel buttons |
| **Behaviour** | If confirmed, product added to cart normally. If cancelled, product not added. A flag is stored on the transaction_item |
| **Access** | Automatic, all POS users |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints (called by Flutter POS via Dio)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/pos/sessions` | POST | Open a new shift (pos_session) | Bearer token (store-scoped) |
| `PUT /api/pos/sessions/{id}/close` | PUT | Close shift with cash count data | Bearer token |
| `POST /api/pos/transactions` | POST | Sync a completed transaction to cloud | Bearer token |
| `POST /api/pos/transactions/batch` | POST | Batch-sync multiple transactions (offline queue flush) | Bearer token |
| `GET /api/pos/transactions/{id}` | GET | Fetch transaction by ID (for receipt reprint) | Bearer token |
| `GET /api/pos/transactions/by-number/{number}` | GET | Lookup by receipt number (for returns) | Bearer token |
| `POST /api/pos/returns` | POST | Sync a return transaction | Bearer token |
| `POST /api/pos/payments` | POST | Sync payment record(s) | Bearer token |
| `GET /api/pos/products/catalog` | GET | Fetch full product catalog for local DB | Bearer token |
| `GET /api/pos/products/changes?since={timestamp}` | GET | Delta product changes since last sync | Bearer token |
| `GET /api/pos/inventory/{store_id}` | GET | Fetch current stock levels for local DB | Bearer token |
| `POST /api/pos/inventory/adjustments` | POST | Sync stock adjustments (sale/return decrements/increments) | Bearer token |
| `GET /api/pos/customers/search?q={query}` | GET | Search customers by phone/name | Bearer token |
| `POST /api/pos/customers` | POST | Create new customer (quick-add at POS) | Bearer token |
| `POST /api/zatca/invoices` | POST | Submit ZATCA invoice to cloud for clearance/reporting | Bearer token |
| `POST /api/zatca/invoices/batch` | POST | Batch submit queued ZATCA invoices | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `CartService` / `CartNotifier` | Manages cart state: add/remove/edit items, calculate totals, apply discounts, resolve modifiers |
| `TransactionService` | Creates transaction + transaction_items + payments records in local DB, triggers ZATCA signing, triggers receipt print, queues sync |
| `PaymentService` | Orchestrates payment flow: cash change calculation, NearPay card terminal communication, split payment resolution |
| `ShiftService` | Opens/closes pos_sessions, calculates expected cash, generates Z-report data |
| `ReturnService` | Handles return/refund/exchange logic: loads original transaction, creates return transaction, processes refund |
| `ZatcaInvoiceService` | Signs invoice with ECDSA, generates TLV QR code, stores in `zatca_invoices`, queues for cloud submission |
| `ReceiptPrinterService` | Formats and sends ESC/POS commands to thermal printer; handles Arabic text via image rendering |
| `BarcodeService` | Resolves barcode to product: checks `product_barcodes`, handles weighable prefix (21/22), price-embedded prefix (23/24) |
| `ScaleService` | Reads weight from serial scale via flutter_libserialport; parses protocol response |
| `CashDrawerService` | Sends RJ11 kick command (ESC p) through the receipt printer to open the drawer |
| `HeldCartService` | Saves and recalls held carts to/from `held_carts` table |
| `CustomerLookupService` | Searches customers locally (Drift query) or via API, attaches to sale |
| `LoyaltyService` | Calculates points earned per transaction, redeems points as payment |

---

## 6. Full Database Schema

### 6.1 Tables

#### `pos_sessions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| register_id | UUID | FK → registers(id), NOT NULL | |
| cashier_id | UUID | FK → users(id), NOT NULL | |
| status | VARCHAR(20) | NOT NULL, DEFAULT 'open' | open / closed |
| opening_cash | DECIMAL(12,2) | NOT NULL | Cash in drawer at shift start |
| closing_cash | DECIMAL(12,2) | NULLABLE | Actual counted cash at close |
| expected_cash | DECIMAL(12,2) | NULLABLE | System-calculated expected cash |
| cash_difference | DECIMAL(12,2) | NULLABLE | closing_cash − expected_cash |
| total_cash_sales | DECIMAL(12,2) | DEFAULT 0 | |
| total_card_sales | DECIMAL(12,2) | DEFAULT 0 | |
| total_other_sales | DECIMAL(12,2) | DEFAULT 0 | Gift card, credit, etc. |
| total_refunds | DECIMAL(12,2) | DEFAULT 0 | |
| total_voids | DECIMAL(12,2) | DEFAULT 0 | |
| transaction_count | INT | DEFAULT 0 | |
| opened_at | TIMESTAMP | DEFAULT NOW() | |
| closed_at | TIMESTAMP | NULLABLE | |
| z_report_printed | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE pos_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    opening_cash DECIMAL(12,2) NOT NULL,
    closing_cash DECIMAL(12,2),
    expected_cash DECIMAL(12,2),
    cash_difference DECIMAL(12,2),
    total_cash_sales DECIMAL(12,2) DEFAULT 0,
    total_card_sales DECIMAL(12,2) DEFAULT 0,
    total_other_sales DECIMAL(12,2) DEFAULT 0,
    total_refunds DECIMAL(12,2) DEFAULT 0,
    total_voids DECIMAL(12,2) DEFAULT 0,
    transaction_count INT DEFAULT 0,
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    z_report_printed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `transactions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | Tenant isolation |
| store_id | UUID | FK → stores(id), NOT NULL | |
| register_id | UUID | FK → registers(id), NOT NULL | |
| pos_session_id | UUID | FK → pos_sessions(id), NOT NULL | |
| cashier_id | UUID | FK → users(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | Attached customer |
| transaction_number | VARCHAR(50) | NOT NULL, UNIQUE | Human-readable receipt number |
| type | VARCHAR(20) | NOT NULL, DEFAULT 'sale' | sale / return / void / exchange |
| status | VARCHAR(20) | NOT NULL, DEFAULT 'completed' | completed / voided / pending |
| subtotal | DECIMAL(12,2) | NOT NULL | Before tax and discount |
| discount_amount | DECIMAL(12,2) | DEFAULT 0 | Total discount applied |
| tax_amount | DECIMAL(12,2) | NOT NULL | VAT amount |
| tip_amount | DECIMAL(12,2) | DEFAULT 0 | |
| total_amount | DECIMAL(12,2) | NOT NULL | Grand total (subtotal − discount + tax + tip) |
| is_tax_exempt | BOOLEAN | DEFAULT FALSE | |
| return_transaction_id | UUID | FK → transactions(id), NULLABLE | For returns: references the original sale |
| external_type | VARCHAR(30) | NULLABLE | thawani / hungerstation / keeta / etc. |
| external_id | VARCHAR(100) | NULLABLE | External platform order ID |
| notes | TEXT | NULLABLE | |
| zatca_uuid | UUID | UNIQUE, NULLABLE | ZATCA invoice UUID |
| zatca_hash | TEXT | NULLABLE | Invoice hash (SHA-256) |
| zatca_qr_code | TEXT | NULLABLE | Base64-encoded TLV QR data |
| zatca_status | VARCHAR(20) | DEFAULT 'pending' | pending / submitted / accepted / rejected |
| sync_status | VARCHAR(20) | DEFAULT 'pending' | pending / synced / failed |
| sync_version | INT | DEFAULT 1 | Optimistic locking for conflict resolution |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete |

```sql
CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    pos_session_id UUID NOT NULL REFERENCES pos_sessions(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    customer_id UUID REFERENCES customers(id),
    transaction_number VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'sale',
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL,
    tip_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    is_tax_exempt BOOLEAN DEFAULT FALSE,
    return_transaction_id UUID REFERENCES transactions(id),
    external_type VARCHAR(30),
    external_id VARCHAR(100),
    notes TEXT,
    zatca_uuid UUID UNIQUE,
    zatca_hash TEXT,
    zatca_qr_code TEXT,
    zatca_status VARCHAR(20) DEFAULT 'pending',
    sync_status VARCHAR(20) DEFAULT 'pending',
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);
```

#### `transaction_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| transaction_id | UUID | FK → transactions(id) ON DELETE CASCADE, NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| barcode | VARCHAR(50) | NULLABLE | Barcode used at scan time |
| product_name | VARCHAR(255) | NOT NULL | Snapshot at sale time |
| product_name_ar | VARCHAR(255) | NULLABLE | |
| quantity | DECIMAL(12,3) | NOT NULL | Supports fractional (weighable) |
| unit_price | DECIMAL(12,2) | NOT NULL | Price at sale time |
| cost_price | DECIMAL(12,2) | NULLABLE | Cost at sale time (for margin report) |
| discount_amount | DECIMAL(12,2) | DEFAULT 0 | Item-level discount |
| discount_type | VARCHAR(20) | NULLABLE | percentage / fixed |
| discount_value | DECIMAL(12,2) | NULLABLE | The percentage or fixed value |
| tax_rate | DECIMAL(5,2) | DEFAULT 15.00 | VAT rate (15%, 0%, exempt) |
| tax_amount | DECIMAL(12,2) | NOT NULL | |
| line_total | DECIMAL(12,2) | NOT NULL | (qty × unit_price) − discount + tax |
| serial_number | VARCHAR(100) | NULLABLE | For serialised items (phones, electronics) |
| batch_number | VARCHAR(100) | NULLABLE | Batch tracking |
| expiry_date | DATE | NULLABLE | Expiry tracking |
| modifier_selections | JSONB | NULLABLE | Selected modifier options with prices |
| notes | TEXT | NULLABLE | Special instructions per item |
| is_return_item | BOOLEAN | DEFAULT FALSE | True for return line items |
| age_verified | BOOLEAN | DEFAULT FALSE | True if age verification was performed |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE transaction_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    barcode VARCHAR(50),
    product_name VARCHAR(255) NOT NULL,
    product_name_ar VARCHAR(255),
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2),
    discount_amount DECIMAL(12,2) DEFAULT 0,
    discount_type VARCHAR(20),
    discount_value DECIMAL(12,2),
    tax_rate DECIMAL(5,2) DEFAULT 15.00,
    tax_amount DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    serial_number VARCHAR(100),
    batch_number VARCHAR(100),
    expiry_date DATE,
    modifier_selections JSONB,
    notes TEXT,
    is_return_item BOOLEAN DEFAULT FALSE,
    age_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `payments`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| transaction_id | UUID | FK → transactions(id) ON DELETE CASCADE, NOT NULL | |
| method | VARCHAR(30) | NOT NULL | cash / card / gift_card / store_credit / loyalty / voucher |
| amount | DECIMAL(12,2) | NOT NULL | Amount paid via this method |
| cash_tendered | DECIMAL(12,2) | NULLABLE | Only for cash payments |
| change_given | DECIMAL(12,2) | NULLABLE | Only for cash payments |
| tip_amount | DECIMAL(12,2) | DEFAULT 0 | Tip portion of this payment leg |
| card_brand | VARCHAR(30) | NULLABLE | VISA / MADA / MC / AMEX |
| card_last_four | VARCHAR(4) | NULLABLE | (encrypted at rest) |
| card_auth_code | VARCHAR(50) | NULLABLE | Authorization code from terminal |
| card_reference | VARCHAR(100) | NULLABLE | Terminal transaction reference |
| gift_card_code | VARCHAR(50) | NULLABLE | If gift card payment |
| coupon_code | VARCHAR(50) | NULLABLE | If coupon applied |
| loyalty_points_used | INT | NULLABLE | If loyalty redemption |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    cash_tendered DECIMAL(12,2),
    change_given DECIMAL(12,2),
    tip_amount DECIMAL(12,2) DEFAULT 0,
    card_brand VARCHAR(30),
    card_last_four VARCHAR(4),
    card_auth_code VARCHAR(50),
    card_reference VARCHAR(100),
    gift_card_code VARCHAR(50),
    coupon_code VARCHAR(50),
    loyalty_points_used INT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `held_carts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| register_id | UUID | FK → registers(id), NOT NULL | |
| cashier_id | UUID | FK → users(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | If customer was attached |
| cart_data | JSONB | NOT NULL | Full cart serialisation (items, qtys, prices, discounts, notes) |
| label | VARCHAR(100) | NULLABLE | Optional name/reference for the held cart |
| held_at | TIMESTAMP | DEFAULT NOW() | |
| recalled_at | TIMESTAMP | NULLABLE | When recalled (NULL = still held) |
| recalled_by | UUID | FK → users(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE held_carts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    cashier_id UUID NOT NULL REFERENCES users(id),
    customer_id UUID REFERENCES customers(id),
    cart_data JSONB NOT NULL,
    label VARCHAR(100),
    held_at TIMESTAMP DEFAULT NOW(),
    recalled_at TIMESTAMP,
    recalled_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `exchange_transactions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| return_transaction_id | UUID | FK → transactions(id), NOT NULL | The return leg |
| sale_transaction_id | UUID | FK → transactions(id), NOT NULL | The new sale leg |
| net_amount | DECIMAL(12,2) | NOT NULL | Difference (positive = customer pays more; negative = refund due) |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE exchange_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_transaction_id UUID NOT NULL REFERENCES transactions(id),
    sale_transaction_id UUID NOT NULL REFERENCES transactions(id),
    net_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `tax_exemptions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| transaction_id | UUID | FK → transactions(id) ON DELETE CASCADE, NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | |
| exemption_type | VARCHAR(30) | NOT NULL | diplomatic / government / export / charity |
| customer_tax_id | VARCHAR(50) | NULLABLE | |
| certificate_number | VARCHAR(100) | NULLABLE | |
| notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE tax_exemptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(id),
    exemption_type VARCHAR(30) NOT NULL,
    customer_tax_id VARCHAR(50),
    certificate_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `pos_sessions_store_status` | (store_id, status) | B-TREE | Find open sessions per store |
| `pos_sessions_cashier` | cashier_id | B-TREE | Sessions by cashier |
| `transactions_number` | transaction_number | UNIQUE | Receipt lookup |
| `transactions_store_created` | (store_id, created_at) | B-TREE | Daily report queries |
| `transactions_store_status` | (store_id, status) | B-TREE | Filter by status |
| `transactions_customer` | customer_id | B-TREE | Customer purchase history |
| `transactions_zatca_uuid` | zatca_uuid | UNIQUE | ZATCA invoice reference |
| `transactions_external` | external_id | B-TREE | Delivery order lookup |
| `transactions_sync` | (store_id, sync_status) | B-TREE | Pending sync items |
| `transaction_items_txn` | transaction_id | B-TREE | Items per transaction |
| `transaction_items_product` | product_id | B-TREE | Product sales history |
| `transaction_items_barcode` | barcode | B-TREE | Barcode-based queries |
| `payments_txn` | transaction_id | B-TREE | Payments per transaction |
| `held_carts_store_register` | (store_id, register_id) | B-TREE | Recall by register |
| `held_carts_active` | (store_id, recalled_at) | B-TREE (partial, WHERE recalled_at IS NULL) | Active held carts |
| `tax_exemptions_txn` | transaction_id | B-TREE | Exemption per transaction |

### 6.3 Relationships Diagram
```
organizations ──1:N──▶ transactions
stores ──1:N──▶ pos_sessions
stores ──1:N──▶ transactions
stores ──1:N──▶ held_carts
registers ──1:N──▶ pos_sessions
registers ──1:N──▶ transactions
users (cashier) ──1:N──▶ pos_sessions
users (cashier) ──1:N──▶ transactions
users (cashier) ──1:N──▶ held_carts
customers ──1:N──▶ transactions
pos_sessions ──1:N──▶ transactions
transactions ──1:N──▶ transaction_items
transactions ──1:N──▶ payments
transactions ──1:1──▶ tax_exemptions
transactions ──1:1──▶ zatca_invoices (see ZATCA feature)
transactions ──0:1──▶ exchange_transactions (return leg)
transactions ──0:1──▶ exchange_transactions (sale leg)
products ──1:N──▶ transaction_items
```

---

## 7. Business Rules

1. **One open session per register** — a register cannot have two open `pos_sessions` simultaneously; if the previous cashier did not close, the new cashier must close the orphaned session first
2. **Discount threshold** — cashiers can only apply discounts up to `store.cashier_discount_limit` (e.g. 10%); above that, a manager PIN is required (the PIN is verified against a user with `pos.approve_discount` permission)
3. **Return without receipt** — behaviour is store-configurable: `refund_to_credit` (always), `exchange_only`, or `deny`. Defaults to `deny`
4. **Exchange net amount** — if the new sale total > return total, customer pays the difference; if less, the balance is refunded (or credited)
5. **ZATCA invoice** — every completed sale and credit note must have a ZATCA record. B2C invoices are signed locally (offline-capable); B2B invoices require online submission and are queued until connectivity restores
6. **Stock decrement** — inventory quantity is decremented immediately on transaction completion (locally in SQLite); the decrement is synced to the cloud via the sync queue
7. **Held cart expiry** — held carts not recalled within 24 hours are automatically purged by a background timer
8. **Cash drawer auto-open** — the cash drawer opens automatically on cash payment completion via the ESC/POS `ESC p` command (routed through the receipt printer)
9. **Transaction immutability** — once a transaction is completed, it cannot be edited; corrections must go through the return/void flow
10. **Offline transaction numbering** — transaction numbers include the register_id prefix to avoid collisions between registers operating offline simultaneously (e.g. `REG01-20260203-0001`)
11. **Tax-exempt sales** require either `pos.tax_exempt` permission or manager PIN approval; exemption data is stored for ZATCA audit
12. **Age-restricted products** — the POS will not allow adding the product to the cart until the cashier confirms age verification; this confirmation is recorded on the `transaction_items` row
13. **Void requires reason** — voiding a transaction requires a mandatory reason text and is audit-logged with the cashier and approver
14. **Tip allocation** — tips are recorded on the `payments` row; tip pooling and distribution are handled by the Staff & User Management feature
15. **Customer-facing display** — if a secondary display device is connected, the cart contents and running total are mirrored in real-time; the ZATCA QR code is shown after payment
