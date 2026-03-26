# Reports & Analytics — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Sales Reports, Inventory Reports, Staff Reports, Financial Reports, Dashboards  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Riverpod/Bloc · Dio · Laravel 11 REST API · Chart Libraries  

---

## 1. Feature Overview

Reports & Analytics transforms raw transaction, inventory, and financial data into actionable business insights. Reports are available on both the POS Desktop app (for daily operations) and the Store Owner Web Dashboard (for strategic overview). The system uses pre-computed summary cache tables on the cloud for fast report generation, while the POS can generate local reports even when offline.

### What This Feature Does
- **Sales dashboard** — today's revenue, transaction count, average basket size, top products, hourly sales graph
- **Sales reports** — revenue by day/week/month/year, by product, by category, by cashier, by payment method, by order source
- **Product performance** — best sellers, slow movers, margin analysis, category contribution
- **Inventory reports** — stock valuation, stock turnover, shrinkage/waste analysis, expiry report, low stock report
- **Staff performance** — transactions per cashier, average transaction value, void/return rate, shift reports
- **Financial reports** — daily P&L, cash flow, expense summary, payment method breakdown, delivery platform commission reconciliation
- **Customer reports** — top customers, repeat purchase rate, loyalty points summary, customer acquisition trend
- **Custom date ranges** — all reports support custom date range, comparison to previous period, and branch filtering
- **Export** — PDF and CSV/Excel export for all reports
- **Scheduled reports** — auto-generate and email daily/weekly/monthly summary reports
- **Real-time KPI tiles** — live dashboard tiles showing current-day metrics with auto-refresh

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **POS Terminal** | Transaction data is the primary source for sales reports |
| **Order Management** | Order data, returns, exchanges feed revenue and refund reports |
| **Payments & Finance** | Payment method breakdown, cash variance, financial reconciliation |
| **Inventory Management** | Stock levels, movements, valuations, waste data |
| **Customer Management** | Customer purchase history, loyalty data |
| **Staff & User Management** | Staff IDs for per-cashier reports |
| **Delivery Integrations** | Delivery platform revenue and commission data |
| **Promotions & Coupons** | Discount amounts, promotion effectiveness data |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Store Owner Web Dashboard** | Dashboard widgets pull from report aggregation APIs |
| **Notifications** | Daily summary notification content comes from report data |

### Features to Review After Changing This Feature
1. **Store Owner Web Dashboard** — dashboard tiles call the same report APIs
2. **Notifications** — automated report emails reference report generation logic

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **fl_chart** | Rich charting library for Flutter — bar, line, pie, scatter charts |
| **syncfusion_flutter_charts** (alternative) | Advanced charting with area, stacked bar, waterfall charts |
| **drift** | SQLite ORM — local report queries on offline transaction data |
| **riverpod** / **flutter_bloc** | State management for report filters, date ranges, chart data |
| **dio** | HTTP client for cloud report API calls |
| **pdf** (pub.dev) | Generate PDF report exports |
| **excel** / **csv** | Generate CSV/Excel exports |
| **intl** | Currency and date formatting for report values |

### 3.2 Technologies
- **Flutter 3.x Desktop** — report screens with interactive charts and data tables
- **Dart** — aggregation logic for local offline reports
- **SQLite (via Drift)** — local report queries; used when offline
- **PostgreSQL** — cloud report queries; materialized views for fast aggregation
- **Laravel 11 REST API** — report endpoints with parameterised date ranges and filters
- **Materialized Views / Summary Tables** — `product_sales_summary` and `daily_sales_summary` pre-computed nightly (or on demand) for fast report generation
- **Laravel Queued Jobs** — scheduled report generation (daily/weekly/monthly email)

---

## 4. Screens

### 4.1 Sales Dashboard Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/dashboard` |
| **Purpose** | At-a-glance overview of today's and recent performance |
| **KPI Tiles** | Today's revenue, Transaction count, Average basket, Refund total, Top product, Growth vs yesterday (%) |
| **Charts** | Hourly sales bar chart, Revenue trend line (last 7/30 days), Payment method pie chart, Top 5 categories horizontal bar |
| **Auto-Refresh** | KPI tiles refresh every 60 seconds |
| **Access** | `reports.view` |

### 4.2 Sales Reports Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/sales` |
| **Purpose** | Detailed sales analysis with drill-down |
| **Group By** | Day, Week, Month, Product, Category, Cashier, Payment method, Order source |
| **Filters** | Date range, Branch, Category, Cashier, Payment method, Order source |
| **Display** | Data table + chart (user toggles). Table columns: Period/Item, Transactions, Qty sold, Revenue, Discount, Net revenue, Tax, Avg ticket |
| **Comparison** | Toggle "Compare to previous period" overlay on chart |
| **Export** | PDF report, CSV/Excel |
| **Access** | `reports.view` |

### 4.3 Product Performance Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/products` |
| **Purpose** | Best/worst sellers, margin analysis |
| **Tabs** | Best Sellers, Slow Movers, Margin Analysis, Category Contribution |
| **Best Sellers** | Top N products by revenue or quantity; configurable N |
| **Slow Movers** | Products with lowest sales in period; candidates for markdown or removal |
| **Margin Analysis** | Revenue, Cost, Margin (%), Markup (%) per product — requires `reports.view_margin` permission |
| **Access** | `reports.view` (margin tab: `reports.view_margin`) |

### 4.4 Inventory Reports Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/inventory` |
| **Purpose** | Stock health and valuation reports |
| **Sub-reports** | Stock Valuation (total stock value at WAC), Stock Turnover (inventory turnover ratio), Shrinkage Report (adjustments with reason breakdown), Expiry Report (by date range), Low Stock Report (products below reorder point) |
| **Access** | `reports.view` + `inventory.view` |

### 4.5 Staff Performance Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/staff` |
| **Purpose** | Cashier and staff performance metrics |
| **Table** | Staff name, Transactions, Revenue, Avg transaction, Void count, Return count, Void rate (%), Hours worked |
| **Charts** | Revenue per cashier bar chart, Shift timeline |
| **Access** | `reports.view` + `staff.view` |

### 4.6 Financial Reports Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/financial` |
| **Purpose** | Revenue, expenses, net profit, cash flow |
| **Sub-reports** | Daily P&L, Expense breakdown by category, Payment method summary, Delivery commission reconciliation, Cash variance history |
| **Access** | `reports.view_financial` |

### 4.7 Customer Reports Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/customers` |
| **Purpose** | Customer behaviour and loyalty analysis |
| **Sub-reports** | Top customers (by spend), Repeat purchase rate, New vs returning customers, Loyalty points issued vs redeemed |
| **Access** | `reports.view` + `customers.view` |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/reports/dashboard` | GET | Dashboard KPI data | Bearer token + `reports.view` |
| `GET /api/reports/sales` | GET | Sales report with group-by and filters | Bearer token + `reports.view` |
| `GET /api/reports/products/best-sellers` | GET | Top selling products | Bearer token + `reports.view` |
| `GET /api/reports/products/slow-movers` | GET | Lowest selling products | Bearer token + `reports.view` |
| `GET /api/reports/products/margin` | GET | Product margin analysis | Bearer token + `reports.view_margin` |
| `GET /api/reports/inventory/valuation` | GET | Stock valuation report | Bearer token + `reports.view` |
| `GET /api/reports/inventory/turnover` | GET | Stock turnover ratios | Bearer token + `reports.view` |
| `GET /api/reports/staff/performance` | GET | Staff performance metrics | Bearer token + `reports.view` |
| `GET /api/reports/financial/daily-pl` | GET | Daily P&L report | Bearer token + `reports.view_financial` |
| `GET /api/reports/financial/payment-methods` | GET | Payment method breakdown | Bearer token + `reports.view_financial` |
| `GET /api/reports/customers/top` | GET | Top customers | Bearer token + `reports.view` |
| `GET /api/reports/customers/retention` | GET | Repeat purchase / retention | Bearer token + `reports.view` |
| `POST /api/reports/export` | POST | Generate PDF/CSV export for a report | Bearer token + `reports.view` |
| `POST /api/reports/schedule` | POST | Create scheduled report | Bearer token + `reports.view` |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `DashboardService` | Fetches KPI data for dashboard tiles; auto-refresh timer |
| `SalesReportService` | Queries sales data locally (offline) or from API; aggregation logic |
| `ProductReportService` | Best sellers, slow movers, margin analysis queries |
| `InventoryReportService` | Stock valuation, turnover, shrinkage calculations |
| `StaffReportService` | Per-cashier performance aggregation |
| `FinancialReportService` | P&L, payment method breakdown, cash variance |
| `CustomerReportService` | Top customers, retention metrics |
| `ReportExportService` | Generates PDF (via `pdf` package) and CSV locally; or triggers cloud export |
| `LocalReportEngine` | Runs SQL aggregation queries on local Drift DB for offline report generation |

---

## 6. Full Database Schema

### 6.1 Tables

#### `product_sales_summary`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| date | DATE | NOT NULL | Aggregation date |
| quantity_sold | DECIMAL(12,3) | DEFAULT 0 | |
| revenue | DECIMAL(14,2) | DEFAULT 0 | |
| cost | DECIMAL(14,2) | DEFAULT 0 | Revenue at cost price |
| discount_amount | DECIMAL(12,2) | DEFAULT 0 | |
| tax_amount | DECIMAL(12,2) | DEFAULT 0 | |
| return_quantity | DECIMAL(12,3) | DEFAULT 0 | |
| return_amount | DECIMAL(12,2) | DEFAULT 0 | |

```sql
CREATE TABLE product_sales_summary (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    product_id UUID NOT NULL REFERENCES products(id),
    date DATE NOT NULL,
    quantity_sold DECIMAL(12,3) DEFAULT 0,
    revenue DECIMAL(14,2) DEFAULT 0,
    cost DECIMAL(14,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    return_quantity DECIMAL(12,3) DEFAULT 0,
    return_amount DECIMAL(12,2) DEFAULT 0,
    UNIQUE (store_id, product_id, date)
);
```

#### `daily_sales_summary`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| date | DATE | NOT NULL | |
| total_transactions | INT | DEFAULT 0 | |
| total_revenue | DECIMAL(14,2) | DEFAULT 0 | |
| total_cost | DECIMAL(14,2) | DEFAULT 0 | |
| total_discount | DECIMAL(12,2) | DEFAULT 0 | |
| total_tax | DECIMAL(12,2) | DEFAULT 0 | |
| total_refunds | DECIMAL(12,2) | DEFAULT 0 | |
| net_revenue | DECIMAL(14,2) | DEFAULT 0 | revenue − refunds |
| cash_revenue | DECIMAL(14,2) | DEFAULT 0 | |
| card_revenue | DECIMAL(14,2) | DEFAULT 0 | |
| other_revenue | DECIMAL(14,2) | DEFAULT 0 | Store credit, gift card, mobile |
| avg_basket_size | DECIMAL(12,2) | DEFAULT 0 | |
| unique_customers | INT | DEFAULT 0 | |

```sql
CREATE TABLE daily_sales_summary (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    date DATE NOT NULL,
    total_transactions INT DEFAULT 0,
    total_revenue DECIMAL(14,2) DEFAULT 0,
    total_cost DECIMAL(14,2) DEFAULT 0,
    total_discount DECIMAL(12,2) DEFAULT 0,
    total_tax DECIMAL(12,2) DEFAULT 0,
    total_refunds DECIMAL(12,2) DEFAULT 0,
    net_revenue DECIMAL(14,2) DEFAULT 0,
    cash_revenue DECIMAL(14,2) DEFAULT 0,
    card_revenue DECIMAL(14,2) DEFAULT 0,
    other_revenue DECIMAL(14,2) DEFAULT 0,
    avg_basket_size DECIMAL(12,2) DEFAULT 0,
    unique_customers INT DEFAULT 0,
    UNIQUE (store_id, date)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `product_sales_summary_store_date` | (store_id, date) | B-TREE | Date-range product reports |
| `product_sales_summary_product_date` | (product_id, date) | B-TREE | Per-product trend |
| `daily_sales_summary_store_date` | (store_id, date) | UNIQUE | Daily aggregate lookup |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ product_sales_summary
stores ──1:N──▶ daily_sales_summary
products ──1:N──▶ product_sales_summary
```

> **Note:** Reports primarily read from `transactions`, `order_items`, `payments`, `stock_movements`, `expenses`, and other operational tables. The summary tables are **cache / materialised views** that speed up common report queries. They are rebuilt nightly and on-demand.

---

## 7. Business Rules

1. **Summary refresh schedule** — `daily_sales_summary` and `product_sales_summary` are rebuilt every night at 02:00 local time via a Laravel scheduled job; they can also be refreshed on-demand from the Reports dashboard
2. **Offline report capability** — the POS can generate sales, transaction, and basic inventory reports locally from the SQLite database without internet; data is limited to what has been synced
3. **Margin visibility** — cost price and margin data are only visible to users with `reports.view_margin` permission; all other users see revenue but not cost or margin columns
4. **Date timezone** — all report dates use the store's configured timezone (default: Asia/Riyadh, UTC+3); the server stores UTC but converts for display
5. **Previous period comparison** — when comparing periods, the system shows absolute difference and percentage change; negative changes are highlighted in red
6. **Export file naming** — exported files are named: `{report_type}_{store_name}_{date_range}.{pdf|csv}`
7. **Scheduled report delivery** — scheduled reports are emailed as PDF attachments to configured recipients; if email delivery fails, the report is still available for download in the Reports section
8. **Data retention** — summary tables retain data indefinitely; raw transaction data is retained for 5 years (configurable); older data can be archived
9. **Real-time vs cached** — dashboard KPI tiles query live data (today's transactions); historical reports use cached summary tables for performance
10. **Branch aggregation** — multi-branch owners can view per-branch or aggregated (all branches) reports; aggregation sums all branches belonging to the organisation
