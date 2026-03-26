# Platform Analytics & Reporting — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Dashboards, KPI Cards, Charts, Pre-Aggregated Stats & Data Export  
> **Tech Stack:** Laravel 11 + Filament v3 · Filament Widgets · Redis Cache · Scheduled Jobs  

---

## 1. Feature Overview

Platform Analytics & Reporting provides Thawani's internal team with **real-time and historical visibility** into every dimension of the SaaS business: revenue, subscriptions, store health, feature adoption, support quality, ZATCA compliance, and infrastructure status.

### What This Feature Does
- **Revenue metrics** — MRR, ARR, revenue by package tier, revenue trends
- **Subscription lifecycle** — active stores, trials, churn rate, growth
- **Provider insights** — new registrations trend, top stores by GMV, geographic distribution
- **Operational metrics** — platform-wide orders processed, delivery sync errors, support ticket volume
- **Feature adoption** — which features are used and by how many stores
- **Compliance & health** — ZATCA compliance rate, system health, error/crash aggregation
- **API metrics** — request volume, latency, error rate
- **Real-time feed** — recent signups, payments, tickets
- **Notification analytics** — sent, delivered, opened rates per template
- **Speed-of-service benchmarking** across restaurant stores
- **Waste cost aggregation** across stores (opt-in)
- **Tip and commission totals** (anonymised trends)

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Billing & Finance** | Revenue dashboard shares data sources with billing |
| **Support Ticket System** | Support analytics (volume, SLA, resolution) are surfaced here |
| **All other features** | Analytics aggregates data from nearly every table in the system |

### Data Sources (tables read by analytics)
| Source Table(s) | Metrics Derived |
|---|---|
| `store_subscriptions`, `subscription_plans` | MRR, ARR, plan distribution, churn |
| `invoices`, `invoice_line_items` | Revenue, failed payments, refunds |
| `stores`, `organizations` | Total stores, geographic distribution, business type breakdown |
| `provider_registrations` | New registrations trend |
| `transactions`, `orders` (provider-side) | Platform-wide GMV, order volume, top stores |
| `store_delivery_platforms` | Delivery integration usage, sync error rates |
| `support_tickets` | Ticket volume, SLA, resolution time, agent performance |
| `registers` | Hardware type breakdown, terminal count |
| `zatca_device_config` (provider-side) | ZATCA compliance rate |
| `admin_activity_logs` | Admin action frequency |
| `notification_templates` + notification logs | Delivery analytics |
| `feature_adoption_stats` | Feature usage |
| `app_update_stats`, `app_releases` | Update adoption |

### Features to Review After Changing This Feature
1. **Materialised view / cache jobs** — any schema change to source tables requires update to aggregation queries
2. **Permissions** — viewer role may need scoped access to specific dashboards
3. **Performance** — large aggregation queries should always read from pre-aggregated tables, not live data

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Dashboard pages, StatsOverviewWidget, ChartWidget |
| **flowframe/laravel-trend** *(or custom)* | Trend data generation for line charts |
| **maatwebsite/laravel-excel** | Export reports to XLSX/CSV |
| **barryvdh/laravel-dompdf** or **spatie/laravel-pdf** | Export dashboard PDF snapshots |
| **spatie/laravel-permission** | Access: `analytics.view`, `analytics.export` |

### 3.2 Technologies
- **Laravel 11** — Scheduled jobs (nightly aggregation), Eloquent, raw SQL for complex aggregations
- **Filament v3** — StatsOverviewWidget (KPI cards), ChartWidget (line/bar/pie/doughnut), TableWidget (top stores, recent events)
- **PostgreSQL** — window functions, CTEs, materialised views for aggregation
- **Redis** — cache dashboard query results (5-minute TTL for dashboards, nightly refresh for daily stats)
- **Laravel Scheduler** — `platform:aggregate-daily-stats` runs at 02:00 daily

---

## 4. Pages

### 4.1 Main Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin` (dashboard home) |
| **Widgets** | |
| — KPI Row | Total Active Stores, MRR (SAR), New Signups (this month), Churn Rate %, Open Support Tickets, ZATCA Compliance % |
| — Revenue Trend | Line chart: monthly revenue over past 12 months |
| — Plan Distribution | Pie chart: active stores per plan |
| — Recent Activity Feed | Table: last 20 events (signup, payment, ticket, plan change) with timestamp |
| **Access** | All admin roles (read-only) |

### 4.2 Revenue & Billing Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/analytics/revenue` |
| **Widgets** | MRR card, ARR card, Total Revenue bar chart (monthly), Revenue by Plan pie, Revenue by Add-On, Upcoming Renewals list, Failed Payments count + list |
| **Filters** | Date range, Plan |
| **Export** | XLSX, PDF |
| **Access** | `analytics.view` |

### 4.3 Subscription Lifecycle Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/analytics/subscriptions` |
| **Widgets** | Active vs Trial vs Grace vs Cancelled (stacked area chart over time), Churn funnel (cancellation reasons pie), Net new stores per month, Average subscription age, Trial-to-paid conversion rate |
| **Filters** | Date range, Plan |
| **Access** | `analytics.view` |

### 4.4 Store Performance Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/analytics/stores` |
| **Widgets** | Top 20 stores by GMV table, Geographic map (stores by city), Business type breakdown pie, Avg orders per store per day, Hardware type breakdown (printer/scanner models) |
| **Access** | `analytics.view` |

### 4.5 Feature Adoption Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/analytics/features` |
| **Widgets** | Feature adoption table (feature_key, stores using, % of total, trend), Integration usage per platform (bar chart), Kitchen display adoption, Loyalty program adoption |
| **Access** | `analytics.view` |

### 4.6 Support Analytics Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/analytics/support` |
| **Widgets** | Open ticket count, Avg first response time, Avg resolution time, SLA compliance %, Ticket volume trend (daily), Category breakdown, Agent performance table |
| **Access** | `analytics.view` |

### 4.7 System Health Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/analytics/health` |
| **Widgets** | API request volume (per minute), API average latency, API error rate %, Queue depth, Failed jobs count, Error/crash report aggregation (top errors), Update adoption rate |
| **Auto-refresh** | Every 30 seconds via Livewire polling |
| **Access** | `analytics.view` |

### 4.8 Notification Analytics
| Field | Detail |
|---|---|
| **Route** | `/admin/analytics/notifications` |
| **Widgets** | Sent/delivered/opened rates per template (table), Channel breakdown (push vs SMS vs email), Delivery failure rate |
| **Access** | `analytics.view` |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Dashboard widgets use Filament's widget system — no external APIs needed.

### 5.2 Export Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /admin/analytics/export/revenue` | POST | Export revenue report (XLSX) | Admin session + `analytics.export` |
| `POST /admin/analytics/export/subscriptions` | POST | Export subscription data (XLSX) | Admin session + `analytics.export` |
| `POST /admin/analytics/export/stores` | POST | Export store performance (XLSX) | Admin session + `analytics.export` |

---

## 6. Full Database Schema

> Analytics are primarily **read-only aggregation queries** across existing tables. The following are materialised/cache tables for dashboard performance.

### 6.1 Tables

#### `platform_daily_stats`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| date | DATE | NOT NULL, UNIQUE | One row per day |
| total_active_stores | INT | NOT NULL | |
| new_registrations | INT | NOT NULL | |
| total_orders | INT | NOT NULL | Platform-wide |
| total_gmv | DECIMAL(14,2) | NOT NULL | Gross merchandise value |
| total_mrr | DECIMAL(12,2) | NOT NULL | Monthly recurring revenue |
| churn_count | INT | NOT NULL | Subscriptions cancelled that day |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE platform_daily_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    date DATE NOT NULL UNIQUE,
    total_active_stores INT NOT NULL DEFAULT 0,
    new_registrations INT NOT NULL DEFAULT 0,
    total_orders INT NOT NULL DEFAULT 0,
    total_gmv DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_mrr DECIMAL(12,2) NOT NULL DEFAULT 0,
    churn_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `platform_plan_stats`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| subscription_plan_id | UUID | FK → subscription_plans(id) | |
| date | DATE | NOT NULL | |
| active_count | INT | NOT NULL | Active subs on this plan |
| trial_count | INT | NOT NULL | Trials on this plan |
| churned_count | INT | NOT NULL | Churned from this plan today |
| mrr | DECIMAL(12,2) | NOT NULL | MRR from this plan |

```sql
CREATE TABLE platform_plan_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_plan_id UUID NOT NULL REFERENCES subscription_plans(id),
    date DATE NOT NULL,
    active_count INT NOT NULL DEFAULT 0,
    trial_count INT NOT NULL DEFAULT 0,
    churned_count INT NOT NULL DEFAULT 0,
    mrr DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE (subscription_plan_id, date)
);
```

#### `feature_adoption_stats`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| feature_key | VARCHAR(50) | NOT NULL | Same keys as plan_feature_toggles |
| date | DATE | NOT NULL | |
| stores_using_count | INT | NOT NULL | Stores with this feature active AND used |
| total_events | INT | NOT NULL | Usage events recorded |

```sql
CREATE TABLE feature_adoption_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    feature_key VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    stores_using_count INT NOT NULL DEFAULT 0,
    total_events INT NOT NULL DEFAULT 0,
    UNIQUE (feature_key, date)
);
```

#### `store_health_snapshots`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) | |
| date | DATE | NOT NULL | |
| sync_status | VARCHAR(10) | | ok / error / pending |
| zatca_compliance | BOOLEAN | | |
| error_count | INT | DEFAULT 0 | Errors in the last 24h |
| last_activity_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE store_health_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    date DATE NOT NULL,
    sync_status VARCHAR(10),
    zatca_compliance BOOLEAN,
    error_count INT DEFAULT 0,
    last_activity_at TIMESTAMP,
    UNIQUE (store_id, date)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `platform_daily_stats_date` | date | UNIQUE | One row per day, fast range query |
| `platform_plan_stats_plan_date` | (subscription_plan_id, date) | UNIQUE | Per-plan per-day lookup |
| `feature_adoption_stats_key_date` | (feature_key, date) | UNIQUE | Per-feature per-day lookup |
| `store_health_snapshots_store_date` | (store_id, date) | UNIQUE | Per-store per-day lookup |

### 6.3 Aggregation Job
```
Job: platform:aggregate-daily-stats
Schedule: Daily at 02:00 UTC (via Laravel Scheduler)
Action:
  1. Query store_subscriptions → count active, trial, grace, cancelled per plan
  2. Query invoices → sum paid amounts for MRR
  3. Query transactions → count + sum for GMV
  4. Query provider_registrations → count new approvals
  5. Query plan_feature_toggles + usage logs → feature adoption
  6. Query stores → sync status, ZATCA status, error counts
  7. INSERT/UPSERT into platform_daily_stats, platform_plan_stats, feature_adoption_stats, store_health_snapshots
```

---

## 7. Business Rules

1. **Pre-aggregated data only** — dashboards never query live transaction/order tables directly; always read from `platform_daily_stats` et al.
2. **Nightly refresh** — aggregation job runs at 02:00; dashboards show data up to yesterday for historical metrics
3. **Real-time widgets** — some widgets (system health, recent activity feed) query live data but with tight limits (last 20 events, 30-second auto-refresh)
4. **Redis caching** — dashboard widget results cached in Redis for 5 minutes to avoid redundant queries across concurrent admin sessions
5. **Export access controlled** — only admins with `analytics.export` permission can download XLSX/PDF reports
6. **Anonymised provider data** — tip/commission totals and waste cost aggregations are anonymised (aggregated across stores, no individual store attribution) for privacy
7. **Date range filters** — all time-series dashboards support date range filter defaulting to last 30 days
8. **Viewer role** — stakeholders/investors get read-only access to analytics dashboards via the Viewer admin role
