# Payments & Finance — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Payment Processing, Cash Management, Card Payments, Financial Reconciliation  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · NearPay SDK · Riverpod/Bloc · Dio · Laravel 11 REST API  

---

## 1. Feature Overview

Payments & Finance handles every monetary aspect of POS operations: accepting payments via multiple methods, managing cash drawer operations, tracking daily revenue, and providing financial reconciliation tools. The system supports cash, card (via NearPay terminal integration), store credit, split payments, and partial payments. The cash management subsystem tracks every open/close cycle of the cash drawer with expected vs actual amounts for loss prevention.

### What This Feature Does
- **Multi-method payment** — cash, card (debit/credit via NearPay), store credit, gift card, mobile payment
- **Split payments** — single transaction paid across multiple methods (e.g. 50 SAR cash + 100 SAR card)
- **Card payment integration** — NearPay SDK for tap-to-pay, chip, and swipe transactions; supports Mada, Visa, Mastercard
- **Cash management** — opening float, close-of-day count, cash-in/cash-out events, expected vs actual variance
- **Cash drawer control** — programmatic cash drawer open command via receipt printer kick connector
- **Refund processing** — refunds to original payment method, cash, or store credit
- **Daily financial summary** — end-of-day report showing total revenue by payment method, refunds, net revenue, cash variance
- **Currency handling** — SAR with halalas; rounding rules for cash (nearest 0.25 SAR)
- **Payment receipt** — printed receipt showing payment method, card last-4 digits, change due
- **Tip support** — optional tip entry for restaurant/service businesses
- **Expense tracking** — record petty cash expenses (cash-out) with category and receipt photo
- **Gift cards** — issue and redeem store gift cards with balance tracking

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **POS Terminal** | Payment is the final step of every POS transaction |
| **Hardware Support** | NearPay terminal, cash drawer, receipt printer for payment slip |
| **Customer Management** | Store credit balance linked to customer record |
| **Order Management** | Payments are linked to orders |
| **Roles & Permissions** | `payments.process`, `payments.refund`, `cash.manage` permissions |
| **Offline/Online Sync** | Payment records sync to cloud after offline transactions |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Reports & Analytics** | Revenue reports, payment method breakdown, cash variance reports |
| **ZATCA Compliance** | Payment method must be recorded on ZATCA invoices |
| **Order Management** | Order status depends on payment status (paid/unpaid/partial) |
| **Customer Management** | Store credit balance affected by payments and refunds |
| **Delivery Integrations** | Commission reconciliation requires delivery order payment data |

### Features to Review After Changing This Feature
1. **POS Terminal** — payment flow is embedded in the POS transaction screen
2. **Reports & Analytics** — financial reports query payment tables directly
3. **ZATCA Compliance** — payment method mapped to ZATCA invoice payment codes
4. **Cash Management** — cash drawer operations tightly coupled to payment processing

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **nearpay_flutter** | NearPay SDK integration for card payment terminal (tap/chip/swipe) |
| **drift** | SQLite ORM — local payment records, cash events, expense tracking |
| **riverpod** / **flutter_bloc** | State management for payment flow, cash management |
| **dio** | HTTP client for payment sync, NearPay reconciliation APIs |
| **intl** | Currency formatting (SAR, halalas), number localisation |
| **uuid** | Generate UUIDs for payment records |
| **flutter_secure_storage** | Secure storage for NearPay terminal credentials |

### 3.2 Technologies
- **NearPay SDK** — Saudi-approved card payment terminal SDK; supports Mada debit, Visa, Mastercard; handles PCI-compliant card processing on the terminal device; POS communicates via Bluetooth or USB
- **Flutter 3.x Desktop** — payment screens, cash management UI
- **Dart** — payment calculation logic, split payment resolution, cash rounding
- **SQLite (via Drift)** — offline payment storage; all payments processable without internet
- **PostgreSQL** — cloud payment master records for financial reporting
- **Laravel 11 REST API** — payment sync, reconciliation, financial reports
- **Cash Drawer RJ-12 Kick** — receipt printer sends electronic pulse to open cash drawer via RJ-12 connector

---

## 4. Screens

### 4.1 Payment Screen (POS Checkout)
| Field | Detail |
|---|---|
| **Route** | Overlay/dialog within POS Terminal `/pos` |
| **Purpose** | Accept payment for current transaction |
| **Layout** | Left: order summary (items, subtotal, tax, discount, total). Right: payment method selection and amount entry |
| **Payment Methods** | Cash (with quick denomination buttons: 1, 5, 10, 20, 50, 100, 200, 500 SAR), Card (triggers NearPay), Store credit, Gift card (scan/enter code), Split payment |
| **Cash Flow** | Enter amount tendered → system calculates change → print receipt → open cash drawer |
| **Card Flow** | Tap "Card" → NearPay SDK launches on terminal → customer taps/inserts/swipes → approval/decline → receipt includes card last-4 |
| **Split Flow** | Add multiple payment lines → allocate amount per line → total allocated must equal order total |
| **Access** | `payments.process` (Cashier and above) |

### 4.2 Cash Management Screen
| Field | Detail |
|---|---|
| **Route** | `/cash-management` |
| **Purpose** | Manage cash drawer: opening float, close count, cash-in/cash-out |
| **Sections** | Current session: opening float, current expected cash (calculated from sales), Cash-in events, Cash-out events |
| **Opening Float** | Enter starting cash amount at beginning of shift |
| **Close Count** | Enter denomination counts (coins and notes) → system calculates total → compares to expected → shows variance |
| **Cash In/Out** | Record non-sale cash movements: reason (petty cash, supplier payment, bank deposit, tips collected), amount, notes |
| **Variance Alert** | If variance > configurable threshold (default 5 SAR), requires manager note |
| **Access** | `cash.manage` (Cashier for own drawer, Manager for all) |

### 4.3 Expense Tracking Screen
| Field | Detail |
|---|---|
| **Route** | `/expenses` |
| **Purpose** | Record and track petty cash expenses |
| **Form** | Amount, Category (supplies, food, transport, maintenance, other), Description, Receipt photo (camera/file), Date |
| **List** | Date, Category, Amount, Description, Recorded by |
| **Access** | `cash.manage` |

### 4.4 Gift Card Management Screen
| Field | Detail |
|---|---|
| **Route** | `/gift-cards` |
| **Purpose** | Issue and manage store gift cards |
| **Issue Form** | Amount, Recipient name (optional), Generate code/barcode |
| **List** | Code, Amount, Balance remaining, Issued date, Expiry, Status (active/redeemed/expired) |
| **Actions** | Issue new, Check balance, Deactivate |
| **Access** | `payments.process` (issue), `cash.manage` (manage) |

### 4.5 Financial Reconciliation Screen
| Field | Detail |
|---|---|
| **Route** | `/finance/reconciliation` |
| **Purpose** | End-of-day financial summary and reconciliation |
| **Sections** | Revenue by payment method, Refunds summary, Net revenue, Cash variance, Card settlement reconciliation (NearPay batches vs POS records), Delivery platform commissions |
| **Date Range** | Select day or range |
| **Export** | PDF report, CSV data |
| **Access** | `reports.view_financial` (Owner, Accountant) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/payments` | POST | Sync payment record to cloud | Bearer token |
| `GET /api/payments` | GET | List payments (paginated, filterable) | Bearer token + `reports.view_financial` |
| `POST /api/payments/refund` | POST | Process refund | Bearer token + `payments.refund` |
| `GET /api/cash-sessions` | GET | List cash management sessions | Bearer token + `cash.manage` |
| `POST /api/cash-sessions/open` | POST | Open cash session with float | Bearer token + `cash.manage` |
| `POST /api/cash-sessions/close` | POST | Close session with count | Bearer token + `cash.manage` |
| `POST /api/cash-events` | POST | Record cash-in or cash-out | Bearer token + `cash.manage` |
| `GET /api/expenses` | GET | List expenses | Bearer token + `cash.manage` |
| `POST /api/expenses` | POST | Create expense | Bearer token + `cash.manage` |
| `POST /api/gift-cards` | POST | Issue gift card | Bearer token + `payments.process` |
| `GET /api/gift-cards/{code}/balance` | GET | Check gift card balance | Bearer token |
| `POST /api/gift-cards/{code}/redeem` | POST | Redeem gift card (partial or full) | Bearer token |
| `GET /api/finance/daily-summary` | GET | Daily financial summary | Bearer token + `reports.view_financial` |
| `GET /api/pos/payments/sync?since={ts}` | GET | Delta sync of payment data for POS | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `PaymentService` | Orchestrates payment flow: method selection, amount validation, payment creation |
| `CashPaymentHandler` | Cash-specific logic: denomination, change calculation, drawer open command |
| `CardPaymentHandler` | NearPay SDK integration: initiates transaction, polls result, handles timeout |
| `SplitPaymentService` | Manages split payment allocation across multiple methods |
| `CashManagementService` | Opening float, close count, variance calculation, cash-in/out events |
| `CashDrawerService` | Sends open command to cash drawer via receipt printer ESC/POS kick pulse |
| `GiftCardService` | Issue, check balance, redeem gift cards |
| `ExpenseService` | CRUD on expense records |
| `PaymentSyncService` | Delta sync of payment records with cloud |
| `RefundService` | Calculates refund amounts, processes refund by method, integrates with NearPay for card refunds |
| `FinancialReconciliationService` | Aggregates payment data for daily summary; card batch reconciliation |

---

## 6. Full Database Schema

### 6.1 Tables

#### `payments`
(Already defined in POS Terminal feature; referenced here for completeness)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| transaction_id | UUID | FK → transactions(id), NOT NULL | |
| method | VARCHAR(30) | NOT NULL | cash, card_mada, card_visa, card_mastercard, store_credit, gift_card, mobile_payment |
| amount | DECIMAL(12,2) | NOT NULL | |
| reference_number | VARCHAR(100) | NULLABLE | Card auth code, gift card code |
| card_last_four | VARCHAR(4) | NULLABLE | |
| card_brand | VARCHAR(30) | NULLABLE | mada, visa, mastercard |
| change_amount | DECIMAL(12,2) | DEFAULT 0 | For cash payments |
| tip_amount | DECIMAL(12,2) | DEFAULT 0 | |
| status | VARCHAR(20) | DEFAULT 'completed' | completed, pending, failed, refunded |
| nearpay_transaction_id | VARCHAR(100) | NULLABLE | NearPay reference |
| sync_version | INT | DEFAULT 1 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id),
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference_number VARCHAR(100),
    card_last_four VARCHAR(4),
    card_brand VARCHAR(30),
    change_amount DECIMAL(12,2) DEFAULT 0,
    tip_amount DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'completed',
    nearpay_transaction_id VARCHAR(100),
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `cash_sessions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| terminal_id | UUID | NULLABLE | If tracked per terminal |
| opened_by | UUID | FK → users(id), NOT NULL | |
| closed_by | UUID | FK → users(id), NULLABLE | |
| opening_float | DECIMAL(12,2) | NOT NULL | Starting cash |
| expected_cash | DECIMAL(12,2) | NULLABLE | Calculated at close |
| actual_cash | DECIMAL(12,2) | NULLABLE | Counted at close |
| variance | DECIMAL(12,2) | NULLABLE | actual − expected |
| status | VARCHAR(20) | DEFAULT 'open' | open, closed |
| opened_at | TIMESTAMP | DEFAULT NOW() | |
| closed_at | TIMESTAMP | NULLABLE | |
| close_notes | TEXT | NULLABLE | Manager note if variance |

```sql
CREATE TABLE cash_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID,
    opened_by UUID NOT NULL REFERENCES users(id),
    closed_by UUID REFERENCES users(id),
    opening_float DECIMAL(12,2) NOT NULL,
    expected_cash DECIMAL(12,2),
    actual_cash DECIMAL(12,2),
    variance DECIMAL(12,2),
    status VARCHAR(20) DEFAULT 'open',
    opened_at TIMESTAMP DEFAULT NOW(),
    closed_at TIMESTAMP,
    close_notes TEXT
);
```

#### `cash_events`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| cash_session_id | UUID | FK → cash_sessions(id), NOT NULL | |
| type | VARCHAR(20) | NOT NULL | cash_in, cash_out |
| amount | DECIMAL(12,2) | NOT NULL | |
| reason | VARCHAR(100) | NOT NULL | petty_cash, supplier_payment, bank_deposit, tips, other |
| notes | TEXT | NULLABLE | |
| performed_by | UUID | FK → users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE cash_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cash_session_id UUID NOT NULL REFERENCES cash_sessions(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    notes TEXT,
    performed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `expenses`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| cash_session_id | UUID | FK → cash_sessions(id), NULLABLE | |
| amount | DECIMAL(12,2) | NOT NULL | |
| category | VARCHAR(50) | NOT NULL | supplies, food, transport, maintenance, utility, other |
| description | TEXT | NULLABLE | |
| receipt_image_url | TEXT | NULLABLE | |
| recorded_by | UUID | FK → users(id), NOT NULL | |
| expense_date | DATE | NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    cash_session_id UUID REFERENCES cash_sessions(id),
    amount DECIMAL(12,2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    receipt_image_url TEXT,
    recorded_by UUID NOT NULL REFERENCES users(id),
    expense_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `gift_cards`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| code | VARCHAR(20) | NOT NULL, UNIQUE | Scannable code |
| barcode | VARCHAR(50) | NULLABLE | EAN-13 or Code128 |
| initial_amount | DECIMAL(12,2) | NOT NULL | |
| balance | DECIMAL(12,2) | NOT NULL | Remaining balance |
| recipient_name | VARCHAR(255) | NULLABLE | |
| status | VARCHAR(20) | DEFAULT 'active' | active, redeemed, expired, deactivated |
| issued_by | UUID | FK → users(id), NOT NULL | |
| issued_at_store | UUID | FK → stores(id), NOT NULL | |
| expires_at | DATE | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE gift_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    code VARCHAR(20) NOT NULL UNIQUE,
    barcode VARCHAR(50),
    initial_amount DECIMAL(12,2) NOT NULL,
    balance DECIMAL(12,2) NOT NULL,
    recipient_name VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active',
    issued_by UUID NOT NULL REFERENCES users(id),
    issued_at_store UUID NOT NULL REFERENCES stores(id),
    expires_at DATE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `gift_card_transactions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| gift_card_id | UUID | FK → gift_cards(id), NOT NULL | |
| type | VARCHAR(20) | NOT NULL | redemption, top_up, refund |
| amount | DECIMAL(12,2) | NOT NULL | |
| balance_after | DECIMAL(12,2) | NOT NULL | |
| payment_id | UUID | FK → payments(id), NULLABLE | Linked POS payment |
| store_id | UUID | FK → stores(id), NOT NULL | |
| performed_by | UUID | FK → users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE gift_card_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gift_card_id UUID NOT NULL REFERENCES gift_cards(id),
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    payment_id UUID REFERENCES payments(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    performed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `refunds`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| return_id | UUID | FK → returns(id), NOT NULL | |
| payment_id | UUID | FK → payments(id), NULLABLE | Original payment being refunded |
| method | VARCHAR(30) | NOT NULL | Same options as payments.method |
| amount | DECIMAL(12,2) | NOT NULL | |
| reference_number | VARCHAR(100) | NULLABLE | NearPay refund ref or store credit ref |
| status | VARCHAR(20) | DEFAULT 'completed' | completed, pending, failed |
| processed_by | UUID | FK → users(id), NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE refunds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    return_id UUID NOT NULL REFERENCES returns(id),
    payment_id UUID REFERENCES payments(id),
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'completed',
    processed_by UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `payments_transaction` | transaction_id | B-TREE | Payments per transaction |
| `payments_method_date` | (method, created_at) | B-TREE | Revenue by method queries |
| `cash_sessions_store_status` | (store_id, status) | B-TREE | Find open session |
| `cash_events_session` | cash_session_id | B-TREE | Events per session |
| `expenses_store_date` | (store_id, expense_date) | B-TREE | Expense reports |
| `gift_cards_code` | code | UNIQUE | Gift card lookup |
| `gift_card_txn_card` | gift_card_id | B-TREE | Transaction history per card |
| `refunds_return` | return_id | B-TREE | Refunds per return |

### 6.3 Relationships Diagram
```
transactions ──1:N──▶ payments
stores ──1:N──▶ cash_sessions
cash_sessions ──1:N──▶ cash_events
cash_sessions ──1:N──▶ expenses (optional link)
stores ──1:N──▶ expenses
organizations ──1:N──▶ gift_cards
gift_cards ──1:N──▶ gift_card_transactions
payments ──1:N──▶ gift_card_transactions (redemption)
returns ──1:N──▶ refunds
payments ──1:N──▶ refunds (original payment)
users ──1:N──▶ cash_sessions (opened_by, closed_by)
users ──1:N──▶ cash_events (performed_by)
```

---

## 7. Business Rules

1. **Cash rounding** — cash payments are rounded to the nearest 0.25 SAR (Saudi common practice); card payments use exact halalas
2. **Split payment must balance** — the sum of all split payment amounts must exactly equal the transaction total; the system will not allow under- or over-payment
3. **One open cash session per terminal** — a cash session must be closed before a new one can be opened on the same terminal; orphaned sessions are auto-closed at midnight with a variance flag
4. **Cash variance threshold** — if the absolute value of cash variance exceeds the store-configured threshold (default 5 SAR), the cashier must enter a note and a notification is sent to the branch manager
5. **Card refund to original card** — NearPay card refunds are processed to the same card used for the original transaction; if the card is unavailable, the refund falls back to cash
6. **Gift card cross-branch** — gift cards issued at any branch of the same organisation can be redeemed at any other branch
7. **Gift card expiry** — gift cards expire after 12 months by default (configurable); expired cards cannot be redeemed; remaining balance is forfeited
8. **NearPay timeout** — if the NearPay terminal does not respond within 60 seconds, the card payment attempt is marked as failed and the cashier is prompted to retry or choose another method
9. **Offline card payments** — card payments require the NearPay terminal to be connected; if the terminal is unreachable, only cash and store credit are available
10. **Expected cash calculation** — expected_cash = opening_float + cash_sales − cash_refunds + cash_in − cash_out; this is calculated automatically at session close
11. **Tip recording** — tips are recorded on the payment record but are not included in the order total or tax calculation; tips are tracked separately in financial reports
12. **Duplicate payment prevention** — the system disables the "Pay" button immediately after click and checks for duplicate transaction IDs to prevent double-charging
