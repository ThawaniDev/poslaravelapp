# Accounting Integration — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level)  
> **Module:** QuickBooks, Xero, Qoyod Integration · Automatic & Manual Export · Journal Entry Mapping  
> **Tech Stack:** Flutter Desktop · Laravel 11 API · OAuth2 · Background Jobs · PostgreSQL  

---

## 1. Feature Overview

Accounting Integration enables stores to connect their POS to external accounting software (QuickBooks Online, Xero, or Qoyod for Saudi businesses). Once connected, the system can automatically export sales summaries, payment reconciliation, tax reports, and expense data to the provider's accounting system, eliminating manual data entry and ensuring books stay synchronized.

### What This Feature Does
- **Connect to accounting providers** via OAuth2 (QuickBooks Online, Xero, Qoyod)
- View connection status and last sync time
- **Manual export** — trigger export of a date range on demand
- **Automatic scheduled export** — configurable daily/weekly/monthly export
- **Export types:**
  - Daily sales summary (revenue, tax collected, discounts, refunds)
  - Payment method breakdown (cash, card, store credit, gift cards)
  - Product category breakdown (optional)
  - Expense entries from purchase orders (if inventory module enabled)
  - Payroll summaries from staff commissions and hours
- **Account mapping** — map POS accounts to accounting chart of accounts
- **Export history** — view past exports with status and download journal entry files
- **Retry failed exports** — manually retry failed sync attempts
- **CSV fallback** — generate CSV export if accounting integration is not connected
- Disconnect accounting integration
- Multi-store consolidation (Enterprise tier) — export all stores to single accounting file

### Feature Dependencies (other provider-side features)
| Feature | Relationship |
|---|---|
| **POS Terminal** | Daily sales data exported |
| **Payments & Finance** | Payment method breakdown, settlement data |
| **Order Management** | Refunds, voids, returns impact export figures |
| **Inventory Management** | Purchase orders exported as expenses (if enabled) |
| **Staff & User Mgmt** | Commission payouts and payroll data |
| **Reports & Analytics** | Export triggers respect same date ranges |
| **Subscription & Billing** | `accounting_export` feature toggle gates access |

### Platform Dependencies
| Feature | Relationship |
|---|---|
| **System Configuration** | OAuth app credentials for QuickBooks/Xero/Qoyod stored at platform level |
| **Package & Subscription Mgmt** | `accounting_export` feature toggle; tier limits on auto-export frequency |

---

## 2. User Roles & Permissions

| Role | Permissions |
|---|---|
| **Store Owner** | Full access — connect, disconnect, configure mapping, manual export, view history |
| **Store Manager** | View connection status, manual export, view history |
| **Accountant** | Full access (delegated role specifically for accounting tasks) |
| **Cashier / Staff** | No access |

### Permission Keys
- `accounting.connect` — Connect/disconnect accounting integration
- `accounting.configure` — Configure account mapping and auto-export settings
- `accounting.export` — Trigger manual exports
- `accounting.view_history` — View export history

---

## 3. User Journeys

### 3.1 Connect to QuickBooks Online

```
Store Owner → Settings → Integrations → Accounting
→ Click "Connect QuickBooks"
→ Redirected to QuickBooks OAuth consent screen
→ Approve permissions (Company, Journal Entries, Accounts)
→ Redirected back to POS with success message
→ Connection status shows "Connected" with company name
→ Initial account mapping wizard appears
```

### 3.2 Configure Account Mapping

```
Store Owner → Settings → Integrations → Accounting → Configure Mapping
→ System shows mapping table:
  | POS Account           | Accounting Account |
  |-----------------------|-------------------|
  | Sales Revenue         | [dropdown: Income accounts] |
  | Cash Payments         | [dropdown: Bank/Cash accounts] |
  | Card Payments         | [dropdown: Bank accounts] |
  | Store Credit Issued   | [dropdown: Liability accounts] |
  | VAT Collected         | [dropdown: Tax Liability accounts] |
  | Discounts Given       | [dropdown: Expense/Contra-Revenue] |
  | Refunds               | [dropdown: Income/Contra accounts] |
  | Cost of Goods Sold    | [dropdown: Expense accounts] |
  | Staff Commissions     | [dropdown: Expense accounts] |
→ Select accounts from accounting provider's chart of accounts
→ Save mapping
→ System validates all required accounts are mapped
```

### 3.3 Manual Export

```
Store Owner → Settings → Integrations → Accounting → Export
→ Select date range (Today / Yesterday / This Week / This Month / Custom)
→ Select export type:
  ☑ Daily Sales Summary
  ☑ Payment Breakdown
  ☑ Category Breakdown (optional)
  ☐ Expense Entries (from POs)
  ☐ Payroll Summary
→ Click "Export to QuickBooks"
→ Loading indicator with progress
→ Success: "23 journal entries created in QuickBooks"
→ Link to view in QuickBooks
```

### 3.4 View Export History

```
Store Owner → Settings → Integrations → Accounting → History
→ Table:
  | Date       | Type          | Status  | Entries | Actions |
  |------------|---------------|---------|---------|---------|
  | 2026-03-07 | Daily Summary | ✅ Success | 15   | View Details |
  | 2026-03-06 | Daily Summary | ✅ Success | 12   | View Details |
  | 2026-03-05 | Daily Summary | ❌ Failed  | 0    | Retry / View Error |
→ Click "View Details" → Modal showing journal entries exported
→ Click "Retry" → Re-attempts export with same parameters
```

### 3.5 Automatic Export Setup

```
Store Owner → Settings → Integrations → Accounting → Auto-Export
→ Toggle "Enable Automatic Export" ON
→ Configure:
  - Frequency: Daily at [time] / Weekly on [day] / Monthly on [date]
  - Export Types: [same checkboxes as manual]
  - Notification: Email when export completes ☑
→ Save
→ System schedules daily job at configured time
```

---

## 4. Technical Documentation

### 4.1 Packages & Plugins (Laravel API)
| Package | Purpose |
|---|---|
| **league/oauth2-client** | OAuth2 flow for all providers |
| **quickbooks/v3-php-sdk** | QuickBooks Online API |
| **xero-php** (webfluent) | Xero API |
| **Custom Qoyod Client** | Qoyod REST API (no official SDK) |
| **spatie/laravel-permission** | Provider permission gating |

### 4.2 Flutter Desktop Components
| Component | Purpose |
|---|---|
| `AccountingSettingsPage` | Main settings screen |
| `AccountingConnectionCard` | Shows connection status widget |
| `AccountMappingForm` | Chart of accounts mapping UI |
| `ExportWizard` | Manual export flow |
| `ExportHistoryTable` | Historical exports list |

### 4.3 OAuth2 Flow

```
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│   POS App     │    │  Laravel API  │    │   QuickBooks  │
└───────┬───────┘    └───────┬───────┘    └───────┬───────┘
        │                    │                    │
        │ GET /accounting/connect/quickbooks      │
        │───────────────────▶│                    │
        │                    │ Generate state, PKCE code_verifier
        │ 302 Redirect       │                    │
        │◀───────────────────│                    │
        │                    │                    │
        │ ═══════════════════════════════════════▶│
        │             User authorizes in browser  │
        │ ◀══════════════════════════════════════│
        │                    │                    │
        │ Callback with auth_code                 │
        │───────────────────▶│                    │
        │                    │ Exchange code for tokens
        │                    │───────────────────▶│
        │                    │◀───────────────────│
        │                    │ Store encrypted tokens
        │ 200 OK + connected │                    │
        │◀───────────────────│                    │
```

### 4.4 Account Mapping Architecture

The POS uses a standardized internal account schema. Each accounting provider's chart of accounts is fetched and presented for mapping.

**Internal POS Accounts:**
| Account Key | Description | Direction |
|---|---|---|
| `sales_revenue` | Total sales (net of refunds, discounts) | Credit |
| `cash_received` | Cash payments | Debit |
| `card_received` | Card payments (Mada, Visa, MC) | Debit |
| `store_credit_issued` | Store credit given to customers | Credit (Liability) |
| `store_credit_redeemed` | Store credit used | Debit (Liability) |
| `gift_card_issued` | Gift card sales | Credit (Liability) |
| `gift_card_redeemed` | Gift card redemptions | Debit (Liability) |
| `vat_collected` | VAT on sales | Credit (Liability) |
| `discounts` | Discount amount | Debit (Contra-Revenue) |
| `refunds` | Refund amount | Debit (Contra-Revenue) |
| `cogs` | Cost of goods sold (inventory) | Debit (Expense) |
| `staff_commissions` | Commission payouts | Debit (Expense) |
| `tips_collected` | Tips on transactions | Credit (Liability - owed to staff) |

### 4.5 Journal Entry Generation

For a daily sales summary export, the system generates a journal entry like:

```
Date: 2026-03-07
Memo: Wameed POS Daily Sales - Store "Al Manar Coffee"

  Debit:  Cash Received           SAR 3,500.00
  Debit:  Card Received           SAR 8,200.00
  Credit: Sales Revenue           SAR 10,000.00
  Credit: VAT Collected           SAR 1,500.00
  Credit: Discounts Given         SAR   200.00
  Debit:  Gift Cards Redeemed     SAR   150.00
  Credit: Gift Cards Issued       SAR   250.00
  Debit:  Refunds                 SAR   300.00
  Debit:  COGS                    SAR 4,000.00
  Credit: Inventory               SAR 4,000.00
```

### 4.6 Export Types Detail

| Export Type | Data Included | Frequency Options |
|---|---|---|
| **Daily Sales Summary** | Aggregated sales, payments, tax, refunds | Daily / On-demand |
| **Payment Breakdown** | Per-payment-method totals | Daily / On-demand |
| **Category Breakdown** | Sales by product category | Daily / Weekly |
| **Expense Entries** | Purchase orders → journal entries | Weekly / Monthly |
| **Payroll Summary** | Staff hours, commissions, tips | Weekly / Bi-weekly / Monthly |
| **Full Reconciliation** | Complete day-end with drawer counts | Daily |

---

## 5. Screens & UI

### 5.1 Accounting Integration Settings Screen
**Location:** Settings → Integrations → Accounting

| Section | Elements |
|---|---|
| **Connection Status** | Provider logo, Company name (from API), "Connected since" date, Last sync time, Sync health badge (healthy/warning/error) |
| **Actions** | "Disconnect" button (with confirmation), "Test Connection" button, "Refresh Access" (re-authenticate if token expired) |
| **Quick Export** | Date picker, Export button |

### 5.2 Connect Provider Modal
| Element | Detail |
|---|---|
| **Provider Selection** | Cards for QuickBooks (blue), Xero (navy), Qoyod (green) |
| **Info Text** | "You'll be redirected to [Provider] to authorize access. We request read/write access to create journal entries." |
| **Action** | "Connect to [Provider]" button |

### 5.3 Account Mapping Screen
| Section | Elements |
|---|---|
| **POS Account List** | Left column — internal account names |
| **Mapped To** | Right column — dropdown populated from provider's chart of accounts |
| **Status Icons** | ✅ Mapped, ⚠️ Unmapped (required), ➖ Unmapped (optional) |
| **Fetch Accounts** | "Refresh Chart of Accounts" button |
| **Save** | Validates all required accounts are mapped |

### 5.4 Export History Screen
| Column | Detail |
|---|---|
| Date | Export date |
| Range | Date range exported (e.g., "2026-03-01 to 2026-03-07") |
| Type | Daily Summary / Payment Breakdown / etc. |
| Entries | Number of journal entries created |
| Status | Success ✅ / Failed ❌ / Pending ⏳ |
| Actions | View Details, Download CSV, Retry (if failed) |

### 5.5 Auto-Export Settings Screen
| Field | Detail |
|---|---|
| Enable Auto-Export | Toggle |
| Frequency | Select: Daily at [time] / Weekly on [day+time] / Monthly on [date+time] |
| Export Types | Multi-select checkboxes |
| Email Notification | Toggle + email address |
| Retry on Failure | Toggle — auto-retry once after 1 hour if initial export fails |

---

## 6. API Specification

### 6.1 OAuth Connection
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `GET /api/v1/accounting/connect/{provider}` | GET | Initiates OAuth flow, returns redirect URL | Store token |
| `GET /api/v1/accounting/callback/{provider}` | GET | OAuth callback handler, exchanges code for tokens | State validation |
| `POST /api/v1/accounting/disconnect` | POST | Disconnects integration, revokes tokens | Store token |
| `POST /api/v1/accounting/refresh-token` | POST | Refreshes expired access token using refresh token | Store token |

### 6.2 Account Mapping
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `GET /api/v1/accounting/chart-of-accounts` | GET | Fetches chart of accounts from connected provider | Store token |
| `GET /api/v1/accounting/mapping` | GET | Returns current account mapping | Store token |
| `PUT /api/v1/accounting/mapping` | PUT | Saves account mapping | Store token |

### 6.3 Export Operations
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `POST /api/v1/accounting/export` | POST | Trigger manual export | Store token |
| Body | | `{ "start_date": "2026-03-01", "end_date": "2026-03-07", "export_types": ["daily_summary", "payment_breakdown"], "format": "api" or "csv" }` | |
| `GET /api/v1/accounting/exports` | GET | List export history | Store token |
| `GET /api/v1/accounting/exports/{id}` | GET | Get export details including journal entries | Store token |
| `POST /api/v1/accounting/exports/{id}/retry` | POST | Retry failed export | Store token |
| `GET /api/v1/accounting/exports/{id}/download` | GET | Download CSV of export | Store token |

### 6.4 Auto-Export Settings
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `GET /api/v1/accounting/auto-export` | GET | Get auto-export configuration | Store token |
| `PUT /api/v1/accounting/auto-export` | PUT | Save auto-export configuration | Store token |
| Body | | `{ "enabled": true, "frequency": "daily", "time": "23:00", "export_types": ["daily_summary"], "notify_email": "owner@store.com" }` | |

### 6.5 Status & Health
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `GET /api/v1/accounting/status` | GET | Returns connection status, last sync, health | Store token |
| Response | | `{ "connected": true, "provider": "quickbooks", "company_name": "Al Manar Co", "connected_at": "...", "last_sync": "...", "token_expires_at": "...", "health": "healthy" }` | |

---

## 7. Database Schema

### 7.1 Provider-Side Tables (Drift/SQLite)

#### `accounting_connection_cache`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INTEGER | PK | |
| provider | TEXT | NOT NULL | quickbooks / xero / qoyod |
| company_name | TEXT | NULL | |
| connected_at | INTEGER | NULL | Timestamp |
| last_sync_at | INTEGER | NULL | Timestamp |
| is_connected | INTEGER | NOT NULL DEFAULT 0 | Boolean |
| mapping_complete | INTEGER | NOT NULL DEFAULT 0 | Boolean |

### 7.2 Server-Side Tables (PostgreSQL)

#### `store_accounting_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), UNIQUE | One config per store |
| provider | VARCHAR(20) | NOT NULL | quickbooks / xero / qoyod |
| access_token_encrypted | TEXT | NOT NULL | Laravel encrypt() |
| refresh_token_encrypted | TEXT | NOT NULL | |
| token_expires_at | TIMESTAMP | NOT NULL | |
| realm_id | VARCHAR(50) | NULL | QuickBooks realm ID |
| tenant_id | VARCHAR(50) | NULL | Xero tenant ID |
| company_name | VARCHAR(255) | NULL | Fetched from provider API |
| connected_at | TIMESTAMP | DEFAULT NOW() | |
| last_sync_at | TIMESTAMP | NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE store_accounting_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id) ON DELETE CASCADE,
    provider VARCHAR(20) NOT NULL,
    access_token_encrypted TEXT NOT NULL,
    refresh_token_encrypted TEXT NOT NULL,
    token_expires_at TIMESTAMP NOT NULL,
    realm_id VARCHAR(50),
    tenant_id VARCHAR(50),
    company_name VARCHAR(255),
    connected_at TIMESTAMP DEFAULT NOW(),
    last_sync_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `account_mappings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) | |
| pos_account_key | VARCHAR(50) | NOT NULL | sales_revenue, cash_received, etc. |
| provider_account_id | VARCHAR(100) | NOT NULL | ID from QuickBooks/Xero/Qoyod |
| provider_account_name | VARCHAR(255) | NOT NULL | Display name |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE account_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    pos_account_key VARCHAR(50) NOT NULL,
    provider_account_id VARCHAR(100) NOT NULL,
    provider_account_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (store_id, pos_account_key)
);
```

#### `accounting_exports`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) | |
| provider | VARCHAR(20) | NOT NULL | |
| start_date | DATE | NOT NULL | |
| end_date | DATE | NOT NULL | |
| export_types | JSONB | NOT NULL | ["daily_summary", "payment_breakdown"] |
| status | VARCHAR(20) | NOT NULL DEFAULT 'pending' | pending / processing / success / failed |
| entries_count | INT | DEFAULT 0 | Number of journal entries created |
| error_message | TEXT | NULL | Populated on failure |
| journal_entry_ids | JSONB | DEFAULT '[]' | IDs from accounting provider |
| csv_url | TEXT | NULL | DigitalOcean Spaces URL if CSV generated |
| triggered_by | VARCHAR(20) | NOT NULL | manual / scheduled |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| completed_at | TIMESTAMP | NULL | |

```sql
CREATE TABLE accounting_exports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    provider VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    export_types JSONB NOT NULL DEFAULT '[]',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    entries_count INT DEFAULT 0,
    error_message TEXT,
    journal_entry_ids JSONB DEFAULT '[]',
    csv_url TEXT,
    triggered_by VARCHAR(20) NOT NULL DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE INDEX idx_accounting_exports_store_date ON accounting_exports (store_id, created_at DESC);
CREATE INDEX idx_accounting_exports_status ON accounting_exports (status) WHERE status IN ('pending', 'processing');
```

#### `auto_export_configs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), UNIQUE | |
| enabled | BOOLEAN | DEFAULT FALSE | |
| frequency | VARCHAR(20) | NOT NULL DEFAULT 'daily' | daily / weekly / monthly |
| day_of_week | INT | NULL | 0-6 for weekly |
| day_of_month | INT | NULL | 1-28 for monthly |
| time | TIME | DEFAULT '23:00' | Export time |
| export_types | JSONB | NOT NULL DEFAULT '["daily_summary"]' | |
| notify_email | VARCHAR(255) | NULL | |
| retry_on_failure | BOOLEAN | DEFAULT TRUE | |
| last_run_at | TIMESTAMP | NULL | |
| next_run_at | TIMESTAMP | NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE auto_export_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id) ON DELETE CASCADE,
    enabled BOOLEAN DEFAULT FALSE,
    frequency VARCHAR(20) NOT NULL DEFAULT 'daily',
    day_of_week INT,
    day_of_month INT,
    "time" TIME DEFAULT '23:00',
    export_types JSONB NOT NULL DEFAULT '["daily_summary"]',
    notify_email VARCHAR(255),
    retry_on_failure BOOLEAN DEFAULT TRUE,
    last_run_at TIMESTAMP,
    next_run_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_auto_export_next_run ON auto_export_configs (next_run_at) WHERE enabled = TRUE;
```

---

## 8. Background Jobs

| Job | Schedule | Purpose |
|---|---|---|
| `ProcessAccountingExport` | Queue (on-demand) | Processes a single export request — fetches POS data, generates journal entries, sends to provider API |
| `RefreshAccountingTokens` | Every 30 min | Checks for tokens expiring within 1 hour, refreshes proactively |
| `ScheduledAccountingExport` | Every minute | Checks `auto_export_configs.next_run_at`, triggers exports, updates next_run_at |
| `RetryFailedExports` | Every hour | Finds exports with `status = failed` and `retry_on_failure = true`, retries once |

---

## 9. Business Rules

1. **Single provider per store** — a store can only connect to one accounting provider at a time. Connecting to a new provider disconnects the existing one (with confirmation).
2. **Token refresh** — access tokens are refreshed proactively before expiration. If refresh fails, connection status changes to "warning" and owner is notified.
3. **Required account mapping** — exports cannot run until all required POS accounts (sales_revenue, cash_received, card_received, vat_collected) are mapped to provider accounts.
4. **Export date limits** — exports are limited to the past 12 months of data. Older data requires contacting support.
5. **Rate limiting** — manual exports are limited to 10 per hour per store to prevent API abuse. Auto-exports are exempt.
6. **Export idempotency** — re-exporting the same date range updates existing journal entries instead of creating duplicates (where provider API supports it).
7. **CSV fallback** — if the accounting integration API fails, the export can be downloaded as CSV for manual import.
8. **Feature gating** — accounting export is gated by `accounting_export` feature toggle in subscription plan. Starter plan: CSV only. Professional/Enterprise: full API integration.
9. **Multi-store consolidation** — Enterprise tier can export multiple stores into a single accounting entity with store-level department/class tagging.
10. **Disconnection** — disconnecting revokes tokens at the provider and deletes local tokens. Export history is retained.
11. **Error categorization** — export failures are categorized: `auth_error` (token invalid), `mapping_error` (account not found), `api_error` (provider API 5xx), `data_error` (invalid journal entry). Each has different retry behavior.
12. **Audit logging** — all connect, disconnect, export, and mapping changes are logged to `activity_logs`.

---

## 10. Error Handling

| Error Type | User Message | System Action |
|---|---|---|
| Token Expired | "Your QuickBooks connection needs to be refreshed. Click here to re-authorize." | Mark connection as warning, prompt re-auth |
| Token Revoked | "QuickBooks access was revoked. Please reconnect your account." | Mark as disconnected, clear tokens |
| Account Not Found | "The mapped account '[name]' no longer exists in QuickBooks. Please update your mapping." | Mark mapping as invalid, block exports |
| API Rate Limited | "QuickBooks is temporarily unavailable. Your export will be retried automatically." | Queue retry in 30 min |
| API Server Error | "QuickBooks is experiencing issues. Your export will be retried automatically." | Queue retry with exponential backoff |
| Invalid Data | "Export failed: [specific error]. Please contact support if this persists." | Log error, don't retry |
| Network Error | "Unable to reach QuickBooks. Please check your internet connection." | Show retry button |

---

## 11. Security Considerations

1. **Token encryption** — all OAuth tokens stored with Laravel `encrypt()` using app key
2. **Minimal scopes** — OAuth requests only the scopes needed (create journal entries, read chart of accounts)
3. **No customer PII** — exports contain only aggregate financial data, not individual customer information
4. **Token rotation** — refresh tokens used once and new refresh token stored
5. **Disconnect cleanup** — on disconnect, tokens are revoked at provider API and deleted locally
6. **Audit trail** — all accounting operations logged for compliance

---

## 12. Provider-Specific Notes

### QuickBooks Online
- Uses OAuth2 with PKCE
- Realm ID required for API calls
- Journal entries created via `/v3/company/{realmId}/journalentry`
- Chart of accounts via `/v3/company/{realmId}/query?query=SELECT * FROM Account`
- Token refresh via `/oauth2/v1/tokens/bearer`

### Xero
- Uses OAuth2 with PKCE
- Tenant ID (organization ID) required
- Journal entries via `/api.xro/2.0/ManualJournals`
- Chart of accounts via `/api.xro/2.0/Accounts`
- Xero requires explicit tenant selection if user has multiple organizations

### Qoyod (Saudi)
- Uses API key authentication (simpler than OAuth)
- Journal entries via `/api/journal_entries`
- Chart of accounts via `/api/accounts`
- All amounts must be in SAR
- Arabic support for memo/descriptions
- Designed for Saudi businesses with ZATCA integration in mind

---

## 13. Testing Checklist

- [ ] Connect to QuickBooks sandbox
- [ ] Connect to Xero demo company
- [ ] Connect to Qoyod sandbox
- [ ] Test token refresh flow
- [ ] Test token revocation handling
- [ ] Configure account mapping
- [ ] Manual export with all types
- [ ] Verify journal entries in accounting software
- [ ] Scheduled export execution
- [ ] Export retry on failure
- [ ] CSV download fallback
- [ ] Disconnect flow
- [ ] Multi-store consolidation (Enterprise)
