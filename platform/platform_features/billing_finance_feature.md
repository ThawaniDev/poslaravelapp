# Billing & Finance Admin — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Subscriptions, Invoices, Payments, Refunds, Hardware Sales & Implementation Fees  
> **Tech Stack:** Laravel 11 + Filament v3 · Payment Gateway SDKs · DomPDF  

---

## Implemented — Admin Panel (Filament)

### SubscriptionPlanResource (Enhanced)
- Tabbed form: General / Pricing / Features / Limits
- Feature toggle repeater (27 features: pos_basic, inventory, delivery_integration, zatca_compliance, etc.)
- Plan limits repeater (11 resource types: products, staff_members, transactions_month, etc.)
- Infolist view page with grouped feature/limit display
- StoreSubscriptionsRelationManager showing active subscribers
- Table with subscriber count badges, status filters, toggle/duplicate actions
- Navigation badge showing total plan count

### StoreSubscriptionResource (Enhanced)
- Color-coded status badges (active=success, trial=info, grace=warning, cancelled=danger, expired=gray)
- Filters: status, billing_cycle, plan, expiring_soon (next 7 days)
- Actions: change_plan (via BillingService), apply_credit, cancel, resume
- Full infolist: subscription overview, credits section, cancellation history
- InvoicesRelationManager with mark_paid and view_pdf actions
- SubscriptionCreditsRelationManager with inline credit creation

### InvoiceResource (Enhanced)
- Line items repeater with reactive total calculation
- Mark Paid action (billing.edit permission)
- Refund action with amount/reason form (billing.refund permission)
- Download PDF action
- Overdue filter, date range filter, status filter
- Full infolist with line items display and status badges
- Navigation badge showing pending invoice count
- Create/Edit/View pages

### SubscriptionDiscountResource (Fixed & Enhanced)
- Fixed field names: type (was discount_type), value (was discount_value)
- Added applicable_plan_ids multi-select
- Added valid_from / valid_to date pickers
- DiscountType enum integration (Percentage/Fixed)
- Usage tracking display (times_used / max_uses)
- Active/expired filters, duplicate action
- Infolist view with applicable plans display

### PaymentGatewayConfigResource (New)
- GatewayName enum (ThawaniPay, Stripe, Moyasar) with color-coded badges
- GatewayEnvironment enum (Sandbox, Production)
- KeyValue credentials editor
- Webhook URL configuration
- Toggle active/deactivate action
- Filters: gateway_name, environment, active status
- Full CRUD with List/Create/View/Edit pages

### HardwareSaleResource (New)
- Store select with search
- HardwareSaleItemType enum (Terminal, Printer, Scanner, Other)
- Serial number tracking with copy
- Amount in SAR with sold_by admin reference
- Date range filter, store filter, item type filter
- Infolist view with sale details and notes
- Full CRUD with List/Create/View/Edit pages

### ImplementationFeeResource (New)
- Store select with search
- ImplementationFeeType enum (Setup, Training, CustomDev)
- ImplementationFeeStatus enum (Invoiced, Paid) with color badges
- Mark Paid action for invoiced fees
- Filters: fee_type, status, store
- Infolist view
- Full CRUD with List/Create/View/Edit pages

### PlanAddOnResource (Enhanced)
- Added description_ar support
- Store add-ons count badge in table
- Toggle active/deactivate action
- StoreAddOnsRelationManager showing subscribed stores
- Infolist view page
- Active status filter

### PlatformStatsWidget (Enhanced)
- MRR (Monthly Recurring Revenue)
- ARR (Annual Recurring Revenue)
- New Signups this month
- Trial Stores count
- Churned subscriptions this month
- Overdue Invoices count + outstanding amount
- Hardware Revenue this month
- Open Tickets count

---

## 1. Feature Overview

Billing & Finance Admin gives the Thawani finance team full control over subscription billing, invoice management, payment processing, refunds, failed-payment handling, and ancillary revenue streams (hardware sales, implementation/training fees). It shares core billing data with the Package & Subscription Management feature but adds finance-specific workflows.

### What This Feature Does
- View all subscriptions: active, trial, grace period, cancelled
- Invoice history per provider, with manual invoice generation
- Process refunds and credits
- Failed payment handling and retry rules configuration
- Revenue breakdown dashboard by package tier
- Upcoming renewals list
- Payment gateway credentials management (Thawani Pay / Stripe / etc.)
- Subscription discount codes and coupon management for plans
- Add-on management: paid extras with independent pricing
- Hardware sales tracking (POS terminals, printers, scanners)
- Implementation / training fee tracking per store

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Package & Subscription Mgmt** | Core billing tables (`invoices`, `subscription_discounts`, `subscription_credits`, `store_add_ons`) are shared |
| **Provider Management** | Store detail → billing tab shows invoices, plan, payment status |
| **Analytics & Reporting** | Revenue and billing metrics derive from billing tables |
| **Platform Announcements** | Payment reminders reference subscription + invoice data |
| **Notification Templates** | `system.license_expiring`, `finance.*` templates reference billing events |

### Features to Review After Changing This Feature
1. **Package & Subscription Mgmt** — shared tables; schema changes affect both features
2. **Analytics** — MRR/ARR calculations depend on invoice + subscription data model
3. **Notification templates** — payment failure / renewal reminder templates must match billing events
4. **Provider portal** — provider sees invoice list; invoice formatting changes affect their view

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for Invoices, Hardware Sales, Fees, Gateway Config |
| **barryvdh/laravel-dompdf** or **spatie/laravel-pdf** | Invoice PDF generation |
| **maatwebsite/laravel-excel** | Billing data export |
| **spatie/laravel-permission** | Access: `billing.view`, `billing.edit`, `billing.invoices`, `billing.refund` |
| **spatie/laravel-activitylog** | Audit log for refunds, credits, manual adjustments |

### 3.2 Technologies
- **Laravel 11** — Eloquent, Jobs (invoice generation, payment retry), Events, Notifications
- **Filament v3** — Admin UI
- **PostgreSQL** — billing data
- **Redis** — queue for payment retry jobs, invoice PDF generation
- **Payment Gateway SDK** — Thawani Pay / Stripe / Moyasar API for charge + refund processing
- **DigitalOcean Spaces** — invoice PDF file storage (S3-compatible)

---

## 4. Pages

### 4.1 Subscriptions List (cross-link from Package feature)
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/subscriptions` |
| **Access** | `billing.view` |
| **Note** | Same as Package & Subscription Mgmt Subscriptions List; accessible from both navigation groups |

### 4.2 Invoice List
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/invoices` |
| **Filament Resource** | `InvoiceResource` |
| **Table Columns** | Invoice Number, Store Name, Amount (SAR), Tax, Total, Status badge (draft/pending/paid/failed/refunded), Due Date, Paid At |
| **Filters** | Status, Date range, Plan, Amount range |
| **Search** | By invoice number, store name |
| **Row Actions** | View, Download PDF, Mark as Paid, Process Refund |
| **Header Action** | Generate Manual Invoice |
| **Access** | `billing.invoices` |

### 4.3 Invoice Detail
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/invoices/{id}` |
| **Sections** | Invoice header (number, store, org, dates), Line items table, Totals (subtotal, tax, total), Payment info (method, gateway txn ID, paid_at), Status timeline |
| **Actions** | Download PDF, Mark Paid, Process Refund (partial or full), Add Credit Note |
| **Access** | `billing.invoices` (refund requires `billing.refund`) |

### 4.4 Failed Payments
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/failed-payments` |
| **Purpose** | List invoices with status=failed, grouped by retry attempt count |
| **Table Columns** | Invoice Number, Store Name, Amount, Failure Reason, Retry Count, Next Retry At, Last Attempt At |
| **Row Actions** | Retry Now, Mark as Paid (manual), Cancel Subscription |
| **Access** | `billing.edit` |

### 4.5 Payment Retry Rules
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/retry-rules` |
| **Purpose** | Configure automatic payment retry behaviour |
| **Form Fields** | Max Retries (int), Retry Interval Hours (int), Grace Period After Failure Days (int) |
| **Access** | `billing.edit` |

### 4.6 Revenue Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/revenue` |
| **Widgets** | MRR card, ARR card, Revenue by Plan (bar chart), Revenue by Add-On, Monthly revenue trend (line), Upcoming renewals (next 7 days table), Hardware/implementation revenue breakdown |
| **Access** | `billing.view` |

### 4.7 Payment Gateway Settings
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/gateways` |
| **Filament Resource** | `PaymentGatewayConfigResource` |
| **Table Columns** | Gateway Name, Environment badge (sandbox/production), Is Active, Webhook URL |
| **Row Actions** | Edit, Test Connection |
| **Form** | Gateway Name, Credentials (encrypted JSONB — key/secret/merchant_id fields), Webhook URL, Environment select, Is Active toggle |
| **Access** | `billing.edit` (Super Admin only for credentials) |

### 4.8 Hardware Sales
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/hardware-sales` |
| **Filament Resource** | `HardwareSaleResource` |
| **Table Columns** | Store Name, Item Type, Description, Serial Number, Amount (SAR), Sold By, Sold At |
| **Row Actions** | Edit, Delete |
| **Create Form** | Store select, Item Type (terminal/printer/scanner/other), Description, Serial Number, Amount, Notes |
| **Access** | `billing.edit` |

### 4.9 Implementation / Training Fees
| Field | Detail |
|---|---|
| **Route** | `/admin/billing/implementation-fees` |
| **Filament Resource** | `ImplementationFeeResource` |
| **Table Columns** | Store Name, Fee Type, Amount, Status badge (invoiced/paid), Notes, Created At |
| **Create Form** | Store select, Fee Type (setup/training/custom_dev), Amount, Status, Notes |
| **Access** | `billing.edit` |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD for billing resources.

### 5.2 Provider-Facing APIs
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/billing/invoices` | GET | List invoices for current store | Store API token |
| `GET /api/v1/billing/invoices/{id}/pdf` | GET | Download invoice PDF | Store API token |
| `GET /api/v1/billing/subscription` | GET | Current subscription details | Store API token |
| `POST /api/v1/billing/pay/{invoice_id}` | POST | Initiate payment for an invoice | Store API token |

### 5.3 Internal Jobs
| Job | Purpose |
|---|---|
| `GenerateRenewalInvoices` | Runs daily; creates next-period invoices for subscriptions renewing within 3 days |
| `RetryFailedPayments` | Runs hourly; retries failed invoices based on retry rules |
| `GenerateInvoicePdf` | Queued; generates PDF and stores URL in `invoices.pdf_url` |

---

## 6. Full Database Schema

> Core billing tables (`invoices`, `invoice_line_items`, `subscription_discounts`, `subscription_credits`, `plan_add_ons`, `store_add_ons`) are defined in [Package & Subscription Management](package_subscription_management_feature.md). This feature adds the following finance-specific tables:

### 6.1 Additional Tables

#### `payment_gateway_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| gateway_name | VARCHAR(50) | NOT NULL | thawani_pay / stripe / moyasar |
| credentials_encrypted | JSONB | NOT NULL | Encrypted: `{"key":"…","secret":"…","merchant_id":"…"}` |
| webhook_url | TEXT | NULLABLE | |
| environment | VARCHAR(20) | NOT NULL | sandbox / production |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE payment_gateway_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gateway_name VARCHAR(50) NOT NULL,
    credentials_encrypted JSONB NOT NULL,
    webhook_url TEXT,
    environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (gateway_name, environment)
);
```

#### `payment_retry_rules`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| max_retries | INT | NOT NULL DEFAULT 3 | |
| retry_interval_hours | INT | NOT NULL DEFAULT 24 | |
| grace_period_after_failure_days | INT | NOT NULL DEFAULT 7 | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE payment_retry_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    max_retries INT NOT NULL DEFAULT 3,
    retry_interval_hours INT NOT NULL DEFAULT 24,
    grace_period_after_failure_days INT NOT NULL DEFAULT 7,
    updated_at TIMESTAMP DEFAULT NOW()
);
-- Single-row config table
```

#### `hardware_sales`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) | |
| sold_by | UUID | FK → admin_users(id) | Admin who recorded the sale |
| item_type | VARCHAR(50) | NOT NULL | terminal / printer / scanner / other |
| item_description | VARCHAR(255) | NULLABLE | |
| serial_number | VARCHAR(100) | NULLABLE | |
| amount | DECIMAL(10,2) | NOT NULL | SAR |
| notes | TEXT | NULLABLE | |
| sold_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE hardware_sales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    sold_by UUID NOT NULL REFERENCES admin_users(id),
    item_type VARCHAR(50) NOT NULL,
    item_description VARCHAR(255),
    serial_number VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    notes TEXT,
    sold_at TIMESTAMP DEFAULT NOW()
);
```

#### `implementation_fees`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) | |
| fee_type | VARCHAR(20) | NOT NULL | setup / training / custom_dev |
| amount | DECIMAL(10,2) | NOT NULL | |
| status | VARCHAR(20) | NOT NULL DEFAULT 'invoiced' | invoiced / paid |
| notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE implementation_fees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    fee_type VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'invoiced',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `payment_gateway_configs_name_env` | (gateway_name, environment) | UNIQUE | One config per gateway per env |
| `hardware_sales_store_sold` | (store_id, sold_at) | B-TREE | Per-store hardware history |
| `hardware_sales_serial` | serial_number | B-TREE | Serial lookup |
| `implementation_fees_store_type` | (store_id, fee_type) | B-TREE | Per-store fee lookup |

### 6.3 Shared Tables (from Package & Subscription feature)
- `invoices` — billing invoice records
- `invoice_line_items` — line item breakdown
- `subscription_discounts` — coupon codes
- `subscription_credits` — manual credits
- `plan_add_ons` — paid extras definitions
- `store_add_ons` — activated add-ons per store
- `store_subscriptions` — subscription records

---

## 7. Business Rules

1. **Invoice auto-generation** — `GenerateRenewalInvoices` creates invoices 3 days before renewal; line items include plan subscription + active add-ons
2. **Payment retry** — failed invoices are retried automatically per `payment_retry_rules` (default: 3 retries, 24h apart, 7-day grace)
3. **Grace period** — after all retries fail, subscription enters `grace` status; after grace period expires, subscription moves to `expired`
4. **Manual override** — Finance Admin can manually mark an invoice as paid (e.g. bank transfer received)
5. **Refund processing** — refunds create a new invoice_line_item with negative amount; partial refunds supported
6. **Gateway credential encryption** — `credentials_encrypted` stored via Laravel `encrypt()`, only decrypted at payment processing time
7. **Hardware sales are one-time** — not recurring; tracked separately from subscription billing
8. **Implementation fees** — tracked per store, can be marked as paid once invoiced and payment received
9. **PDF generation** — invoice PDF generated asynchronously via queued job; stored in DigitalOcean Spaces; URL saved in `invoices.pdf_url`
10. **Audit trail** — all refunds, credits, manual payment marks, and gateway config changes are logged via `admin_activity_logs`
