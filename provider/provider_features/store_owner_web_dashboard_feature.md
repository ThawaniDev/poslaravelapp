# Store Owner Web Dashboard — Comprehensive Feature Documentation

> **Scope:** Provider (Laravel Web Application — Store Owner Portal)  
> **Module:** Web-Based Dashboard for Remote Store Management, Analytics, Settings  
> **Tech Stack:** Laravel 11 · Filament 3 / Livewire 3 · Tailwind CSS · Alpine.js · Chart.js  

---

## 1. Feature Overview

The Store Owner Web Dashboard is a Laravel-powered web application that gives store owners remote access to their business operations from any browser. Unlike the POS Desktop app (Flutter), this is a server-rendered web portal focused on high-level management: reviewing sales analytics, managing staff, adjusting product pricing and inventory, configuring store settings, and monitoring compliance — all without being physically at the POS terminal.

### What This Feature Does
- **Sales overview** — real-time and historical sales summaries, revenue charts, top products, hourly heatmaps
- **Inventory monitoring** — low-stock alerts, stock levels, product catalog management (add/edit products via web)
- **Staff management** — add/remove staff, assign roles, view attendance, review activity logs
- **Order history** — view all orders, filter by date/status/payment method, export to CSV/Excel
- **Financial reports** — daily/weekly/monthly P&L summaries, tax reports (including ZATCA compliance status for Saudi stores)
- **Store settings** — business details, operating hours, payment configuration, receipt templates
- **Multi-branch overview** — store owners with multiple branches see consolidated and per-branch dashboards
- **Notification center** — web-based notifications mirroring Mobile Companion App alerts (low stock, large orders, shift clock-ins)
- **Product catalog management** — add/edit/delete products, manage categories, set pricing, upload images
- **Coupon & promotion management** — create/edit/disable coupons and promotions remotely

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **All Provider Features** | The web dashboard is a read/write consumer of all store data |
| **Offline/Online Sync** | Data arrives from POS via sync; dashboard reads cloud DB |
| **Subscription & Billing** | Access level determined by subscription tier |
| **Roles & Permissions** | Role-based access (Owner full access, Accountant limited) |
| **Thawani Integration** | Store synchronized from Thawani platform |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Mobile Companion App** | Shares same Laravel API endpoints |
| **POS Terminal** | Settings changed on web sync down to POS |

### Features to Review After Changing This Feature
1. **Mobile Companion App** — shares API endpoints; changes affect both
2. **POS Terminal** — settings modified on dashboard propagate to POS
3. **Product Catalog** — products added on web dashboard sync to POS

---

## 3. Technical Documentation

### 3.1 Packages & Plugins (Laravel / PHP)
| Package | Purpose |
|---|---|
| **filament/filament** | Admin panel framework — tables, forms, widgets, navigation |
| **livewire/livewire** | Reactive server-rendered components |
| **laravel/sanctum** | API token authentication (shared with Mobile Companion App) |
| **spatie/laravel-permission** | Role & permission management |
| **maatwebsite/excel** | Export data to Excel/CSV |
| **barryvdh/laravel-dompdf** | PDF report generation |
| **flowframe/laravel-trend** | Aggregate data trends for charts |
| **filament/widgets** | Dashboard stat cards, chart widgets |

### 3.2 Frontend Technologies
| Technology | Purpose |
|---|---|
| **Tailwind CSS** | Utility-first CSS framework for responsive layout |
| **Alpine.js** | Lightweight JS for interactivity (dropdowns, modals) |
| **Chart.js** | Sales charts, inventory charts, heatmaps |
| **ApexCharts** | Advanced charting (via Filament ApexCharts plugin) |

### 3.3 Technologies
- **Laravel 11 + Filament 3** — Server-rendered admin panel; no SPA frontend needed
- **Livewire 3** — reactive components for search, filtering, real-time updates
- **Pusher / Soketi (WebSocket)** — real-time notification delivery to the dashboard
- **Redis** — caching dashboard aggregations and session management
- **PostgreSQL** — reads from the same cloud database that POS syncs to
- **DigitalOcean Spaces** — product image storage (S3-compatible; uploaded via dashboard or POS)

---

## 4. Screens (Filament Resources & Pages)

### 4.1 Main Dashboard
| Field | Detail |
|---|---|
| **Route** | `/dashboard` |
| **Purpose** | High-level business overview |
| **Layout** | Stat cards row: Today's Sales, Orders Count, Average Order Value, Active Staff. Below: Sales trend chart (7/30/90 days), Top 5 products, Hourly sales heatmap, Recent orders table. Sidebar: branch selector (multi-branch owners) |
| **Actions** | Switch date range, Switch branch, Quick links to Reports |
| **Access** | Owner, Manager (branch-filtered), Accountant (financial only) |

### 4.2 Orders Resource
| Field | Detail |
|---|---|
| **Route** | `/orders` |
| **Purpose** | Browse, filter, and export order history |
| **Layout** | Filterable table: Order #, Date, Customer, Items, Total, Payment Method, Status. Detail view: line items, payment breakdown, timeline. |
| **Actions** | View details, Export CSV/Excel, Print invoice PDF, Issue refund (if permitted) |
| **Access** | Owner, Manager, Accountant |

### 4.3 Products Resource
| Field | Detail |
|---|---|
| **Route** | `/products` |
| **Purpose** | Manage product catalog remotely |
| **Layout** | Table: Image, Name (AR/EN), SKU, Barcode, Category, Price, Stock, Status. Form: full product details including variants, pricing tiers, images, category assignment |
| **Actions** | Create, Edit, Delete (soft), Bulk import (Excel), Bulk price update |
| **Access** | Owner, Manager |

### 4.4 Inventory Resource
| Field | Detail |
|---|---|
| **Route** | `/inventory` |
| **Purpose** | Stock levels and movement history |
| **Layout** | Low stock alerts panel. Stock level table: Product, Current Stock, Reorder Point, Last Movement. Stock movement log: Date, Type (received/sold/adjusted/transfer), Qty, Reference |
| **Actions** | Create stock adjustment, Transfer between branches, Export stock report |
| **Access** | Owner, Manager |

### 4.5 Staff Resource
| Field | Detail |
|---|---|
| **Route** | `/staff` |
| **Purpose** | Manage staff members, roles, and attendance |
| **Layout** | Staff list table: Name, Role, Branch, Status, Last Active. Detail: profile, role assignment, attendance history, activity log |
| **Actions** | Add staff, Edit, Suspend/Reactivate, Assign role, View attendance report |
| **Access** | Owner |

### 4.6 Customers Resource
| Field | Detail |
|---|---|
| **Route** | `/customers` |
| **Purpose** | View customer profiles and purchase history |
| **Layout** | Customer list: Name, Phone, Loyalty Points, Total Spent, Last Visit. Detail: purchase history, loyalty transactions |
| **Actions** | View details, Adjust loyalty points, Export customer list |
| **Access** | Owner, Manager |

### 4.7 Promotions & Coupons Resource
| Field | Detail |
|---|---|
| **Route** | `/promotions` |
| **Purpose** | Create and manage promotions and coupons |
| **Layout** | Active promotions list. Coupon list: Code, Discount, Usage Count, Expiry, Status. Form: coupon creation/editing |
| **Actions** | Create coupon, Edit, Activate/Deactivate, View usage analytics |
| **Access** | Owner, Manager |

### 4.8 Financial Reports Page
| Field | Detail |
|---|---|
| **Route** | `/reports/financial` |
| **Purpose** | P&L statements, tax summaries, revenue breakdowns |
| **Layout** | Period selector. Revenue breakdown card. Expense categories. Tax collected summary. Payment method breakdown. Exportable P&L table |
| **Actions** | Select period, Export PDF/Excel, Print |
| **Access** | Owner, Accountant |

### 4.9 ZATCA Compliance Page (Saudi stores only)
| Field | Detail |
|---|---|
| **Route** | `/reports/zatca` |
| **Purpose** | Monitor ZATCA e-invoice compliance |
| **Layout** | Compliance score, Pending invoices count, Rejected invoices list with error details, Certificate status and expiry |
| **Actions** | Retry failed submissions, View invoice XML |
| **Access** | Owner, Accountant |

### 4.10 Store Settings Page
| Field | Detail |
|---|---|
| **Route** | `/settings` |
| **Purpose** | Configure store business details and preferences |
| **Layout** | Tabs: Business Details (name, address, VAT, CR), Operating Hours, Payment Methods, Receipt Template, Notification Preferences |
| **Actions** | Save settings (syncs to POS on next sync cycle) |
| **Access** | Owner |

---

## 5. APIs

### 5.1 Laravel Backend Endpoints (Shared with Mobile Companion App)

> The web dashboard primarily uses Filament's built-in Eloquent queries. The following dedicated API endpoints are used by BOTH the web dashboard (via AJAX/Livewire) and the Mobile Companion App.

| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v2/dashboard/stats` | GET | Dashboard stat cards (today sales, orders, etc.) | Sanctum |
| `GET /api/v2/dashboard/sales-trend` | GET | Sales chart data for period | Sanctum |
| `GET /api/v2/dashboard/top-products` | GET | Top N products by revenue/qty | Sanctum |
| `GET /api/v2/orders` | GET | Paginated order listing with filters | Sanctum |
| `GET /api/v2/orders/{id}` | GET | Order detail | Sanctum |
| `POST /api/v2/orders/{id}/refund` | POST | Issue refund | Sanctum, Owner/Manager |
| `GET /api/v2/products` | GET | Product listing with search/filter | Sanctum |
| `POST /api/v2/products` | POST | Create product | Sanctum, Owner/Manager |
| `PUT /api/v2/products/{id}` | PUT | Update product | Sanctum, Owner/Manager |
| `DELETE /api/v2/products/{id}` | DELETE | Soft-delete product | Sanctum, Owner |
| `POST /api/v2/products/bulk-import` | POST | Bulk import from Excel | Sanctum, Owner |
| `GET /api/v2/inventory/stock-levels` | GET | Current stock levels | Sanctum |
| `POST /api/v2/inventory/adjustments` | POST | Create stock adjustment | Sanctum, Owner/Manager |
| `GET /api/v2/staff` | GET | Staff listing | Sanctum, Owner |
| `POST /api/v2/staff` | POST | Create staff member | Sanctum, Owner |
| `PUT /api/v2/staff/{id}` | PUT | Update staff | Sanctum, Owner |
| `GET /api/v2/staff/{id}/attendance` | GET | Staff attendance records | Sanctum, Owner |
| `GET /api/v2/customers` | GET | Customer listing | Sanctum |
| `GET /api/v2/reports/financial` | GET | Financial report data | Sanctum, Owner/Accountant |
| `GET /api/v2/reports/tax-summary` | GET | Tax report (VAT, ZATCA) | Sanctum, Owner/Accountant |
| `POST /api/v2/promotions` | POST | Create promotion/coupon | Sanctum, Owner/Manager |
| `PUT /api/v2/promotions/{id}` | PUT | Update promotion | Sanctum, Owner/Manager |
| `PUT /api/v2/settings/store` | PUT | Update store settings | Sanctum, Owner |

### 5.2 Filament Resources (Server-Side)
| Resource / Page | Purpose |
|---|---|
| `OrderResource` | CRUD + table + filters for orders, with export action |
| `ProductResource` | CRUD for products, with image upload, variant management |
| `InventoryResource` | Stock levels table, adjustment form, transfer wizard |
| `StaffResource` | CRUD for staff, relation manager for attendance |
| `CustomerResource` | Read + loyalty adjustment for customers |
| `PromotionResource` | CRUD for promotions and coupons |
| `DashboardPage` | Custom Filament page with stat widgets and chart widgets |
| `FinancialReportPage` | Custom page with Livewire chart components |
| `ZatcaCompliancePage` | Custom page for Saudi ZATCA monitoring |
| `StoreSettingsPage` | Custom page with tabbed settings form |

---

## 6. Full Database Schema

> **The Store Owner Web Dashboard does NOT introduce new database tables**. It reads and writes to the same PostgreSQL cloud database that the POS Desktop app syncs to. All tables (orders, products, inventory, staff, etc.) are defined in their respective feature documentation files.

### 6.1 Dashboard-Specific Views (Optional — for Performance)

These materialized views / cached queries may be created for dashboard performance:

#### `mv_daily_sales_summary` (Materialized View)
```sql
CREATE MATERIALIZED VIEW mv_daily_sales_summary AS
SELECT
    store_id,
    DATE(created_at) AS sale_date,
    COUNT(*) AS order_count,
    SUM(total_amount) AS total_revenue,
    SUM(vat_amount) AS total_vat,
    AVG(total_amount) AS avg_order_value
FROM orders
WHERE status = 'completed'
GROUP BY store_id, DATE(created_at);

CREATE UNIQUE INDEX mv_daily_sales_store_date
    ON mv_daily_sales_summary (store_id, sale_date);
```

#### `mv_product_performance` (Materialized View)
```sql
CREATE MATERIALIZED VIEW mv_product_performance AS
SELECT
    p.store_id,
    p.id AS product_id,
    p.name_ar,
    p.name_en,
    SUM(oi.quantity) AS total_qty_sold,
    SUM(oi.line_total) AS total_revenue,
    COUNT(DISTINCT oi.order_id) AS order_count
FROM products p
JOIN order_items oi ON oi.product_id = p.id
JOIN orders o ON o.id = oi.order_id
WHERE o.status = 'completed'
GROUP BY p.store_id, p.id, p.name_ar, p.name_en;

CREATE UNIQUE INDEX mv_product_perf_store_product
    ON mv_product_performance (store_id, product_id);
```

### 6.2 Indexes (Additional for Dashboard Queries)

| Index | Table | Columns | Purpose |
|---|---|---|---|
| `orders_store_status_date` | orders | (store_id, status, created_at) | Dashboard sales queries |
| `order_items_product_id` | order_items | product_id | Top products aggregation |
| `staff_users_store_active` | staff_users | (store_id, is_active) | Staff listing |

### 6.3 Relationships
```
The web dashboard is a READ/WRITE portal into the same database:
stores ──1:N──▶ orders
stores ──1:N──▶ products
stores ──1:N──▶ staff_users
stores ──1:N──▶ customers
stores ──1:N──▶ promotions
stores ──1:N──▶ zatca_invoices (Saudi only)
```

---

## 7. Business Rules

1. **Data isolation** — every query on the dashboard is scoped to the authenticated store owner's store(s); multi-tenant isolation is enforced at the Eloquent global scope level
2. **Role-based access** — Filament navigation and resources are filtered by role: Owner sees everything; Manager sees orders, products, staff (branch-filtered); Accountant sees orders, financial reports, ZATCA (read-only)
3. **Sync latency** — dashboard data may lag behind POS by the sync interval (typically 30s–5min); a "Last synced" indicator is shown
4. **Settings propagation** — settings changed on the dashboard (store hours, receipt template, payment config) are picked up by POS on the next sync cycle; critical changes (like payment disabling) trigger a push notification to the POS
5. **Multi-branch filtering** — owners with multiple branches see a branch selector; "All Branches" mode aggregates data across branches; individual branch mode filters all data
6. **Export limits** — CSV/Excel exports are limited to 50,000 rows per export to prevent server memory issues; larger exports are processed as background jobs with download links sent via notification
7. **Web session security** — sessions expire after 2 hours of inactivity; simultaneous login from multiple devices is allowed (each gets its own session)
8. **Responsive design** — the dashboard uses Filament's responsive layout; usable on tablets but optimized for desktop browsers (1024px+ breakpoint)
9. **Arabic-first layout** — when the user's language is Arabic, the entire dashboard renders RTL (right-to-left); Filament supports RTL natively
10. **Materialized view refresh** — `mv_daily_sales_summary` and `mv_product_performance` are refreshed every 15 minutes via a Laravel scheduled command; not refreshed on every page load
