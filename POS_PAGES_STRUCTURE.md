# POS System - Complete Pages Structure

> **Document Purpose**: List of all pages/screens in the POS system
> **Created**: January 31, 2026
> **Type**: Page inventory (not detailed wireframes)

---

## 📋 Table of Contents

1. [Authentication & Onboarding](#1-authentication--onboarding)
2. [Dashboard](#2-dashboard)
3. [POS Terminal (Sales)](#3-pos-terminal-sales)
4. [Products Management](#4-products-management)
5. [Inventory Management](#5-inventory-management)
6. [Orders Management](#6-orders-management)
7. [Thawani Integration](#7-thawani-integration)
8. [Customers Management](#8-customers-management)
9. [Users & Permissions](#9-users--permissions)
10. [Branch Management (Multi-Store)](#10-branch-management-multi-store)
11. [Reports & Analytics](#11-reports--analytics)
12. [Financial Management](#12-financial-management)
13. [Invoices & ZATCA](#13-invoices--zatca)
14. [Subscription & Billing](#14-subscription--billing)
15. [Settings](#15-settings)
16. [Support & Help](#16-support--help)

---

## 1. Authentication & Onboarding

### 1.1 Authentication Pages
| Page | Description | Access |
|------|-------------|--------|
| `login` | Email/phone + password login | Public |
| `login/pin` | Quick PIN login for cashiers | Public |
| `forgot-password` | Password reset request | Public |
| `reset-password` | Set new password | Public |
| `two-factor` | 2FA verification (if enabled) | Public |
| `session-expired` | Session timeout notification | Public |

### 1.2 Onboarding Pages (First Time Setup)
| Page | Description | Access |
|------|-------------|--------|
| `onboarding/welcome` | Welcome screen, choose plan | New User |
| `onboarding/business-info` | Store name, CR number, VAT number | New User |
| `onboarding/business-type` | Supermarket, Restaurant, Retail, etc. | New User |
| `onboarding/branches` | Single store or multi-branch setup | New User |
| `onboarding/zatca-setup` | ZATCA device registration | New User |
| `onboarding/printer-setup` | Configure receipt printer | New User |
| `onboarding/import-products` | Bulk import or start fresh | New User |
| `onboarding/thawani-connect` | Link to Thawani delivery platform | New User |
| `onboarding/invite-staff` | Add cashiers and managers | New User |
| `onboarding/complete` | Setup complete, go to POS | New User |

---

## 2. Dashboard

### 2.1 Main Dashboard
| Page | Description | Access |
|------|-------------|--------|
| `dashboard` | Main overview (today's sales, orders, alerts) | All Staff |
| `dashboard/owner` | Owner view (all branches, financials) | Owner |
| `dashboard/manager` | Manager view (single branch focus) | Manager |
| `dashboard/cashier` | Cashier view (shift summary, quick actions) | Cashier |

### 2.2 Quick Stats Widgets (on Dashboard)
- Today's Sales (POS + Thawani)
- Pending Delivery Orders
- Low Stock Alerts
- Top Selling Products
- Recent Transactions
- Shift Status
- ZATCA Sync Status
- Thawani Connection Status

---

## 3. POS Terminal (Sales)

### 3.1 Main POS Screen
| Page | Description | Access |
|------|-------------|--------|
| `pos` | Main POS terminal (product grid + cart) | Cashier+ |
| `pos/search` | Product search overlay | Cashier+ |
| `pos/barcode` | Barcode scanner mode | Cashier+ |
| `pos/categories` | Browse by category | Cashier+ |
| `pos/favorites` | Quick access products | Cashier+ |

### 3.2 Cart & Checkout
| Page | Description | Access |
|------|-------------|--------|
| `pos/cart` | Current cart/basket view | Cashier+ |
| `pos/cart/discount` | Apply discount (item or total) | Cashier+ |
| `pos/cart/customer` | Attach customer to sale | Cashier+ |
| `pos/cart/notes` | Add notes to sale | Cashier+ |
| `pos/checkout` | Payment selection screen | Cashier+ |
| `pos/checkout/cash` | Cash payment (amount, change) | Cashier+ |
| `pos/checkout/card` | Card payment (Mada terminal) | Cashier+ |
| `pos/checkout/split` | Split payment (cash + card) | Cashier+ |
| `pos/checkout/credit` | Credit sale (pay later) | Manager+ |
| `pos/checkout/complete` | Sale complete, print receipt | Cashier+ |

### 3.3 Special Sales
| Page | Description | Access |
|------|-------------|--------|
| `pos/hold` | Hold/park current sale | Cashier+ |
| `pos/held-sales` | List of held sales | Cashier+ |
| `pos/refund` | Process refund | Manager+ |
| `pos/refund/select-sale` | Find original sale for refund | Manager+ |
| `pos/exchange` | Product exchange | Manager+ |
| `pos/void` | Void current transaction | Manager+ |
| `pos/price-check` | Check price without selling | Cashier+ |

### 3.4 Shift Management
| Page | Description | Access |
|------|-------------|--------|
| `pos/shift/open` | Open cash drawer, enter float | Cashier+ |
| `pos/shift/close` | Close shift, count cash | Cashier+ |
| `pos/shift/summary` | Shift report before closing | Cashier+ |
| `pos/shift/add-cash` | Add cash to drawer (pay-in) | Manager+ |
| `pos/shift/remove-cash` | Remove cash from drawer (pay-out) | Manager+ |
| `pos/shift/history` | View past shifts | Manager+ |

---

## 4. Products Management

### 4.1 Product List
| Page | Description | Access |
|------|-------------|--------|
| `products` | All products list (table view) | Manager+ |
| `products/grid` | Products grid view with images | Manager+ |
| `products/search` | Advanced product search | Manager+ |
| `products/filter` | Filter by category, status, stock | Manager+ |
| `products/low-stock` | Low stock products only | Manager+ |
| `products/out-of-stock` | Out of stock products | Manager+ |
| `products/inactive` | Inactive/hidden products | Manager+ |

### 4.2 Product CRUD
| Page | Description | Access |
|------|-------------|--------|
| `products/add` | Add new product | Manager+ |
| `products/{id}` | View product details | Manager+ |
| `products/{id}/edit` | Edit product | Manager+ |
| `products/{id}/delete` | Delete confirmation | Manager+ |
| `products/{id}/duplicate` | Duplicate product | Manager+ |
| `products/{id}/history` | Price & stock change history | Manager+ |

### 4.3 Product Details Tabs
| Page | Description | Access |
|------|-------------|--------|
| `products/{id}/general` | Name, description, images | Manager+ |
| `products/{id}/pricing` | Price, cost, tax settings | Manager+ |
| `products/{id}/inventory` | Stock levels per branch | Manager+ |
| `products/{id}/variants` | Size/color variants | Manager+ |
| `products/{id}/barcode` | Barcode assignment | Manager+ |
| `products/{id}/thawani` | Thawani sync settings | Manager+ |
| `products/{id}/analytics` | Sales performance | Manager+ |

### 4.4 Categories
| Page | Description | Access |
|------|-------------|--------|
| `products/categories` | All categories list | Manager+ |
| `products/categories/add` | Add new category | Manager+ |
| `products/categories/{id}/edit` | Edit category | Manager+ |
| `products/categories/reorder` | Drag & drop ordering | Manager+ |

### 4.5 Bulk Operations
| Page | Description | Access |
|------|-------------|--------|
| `products/import` | Import from CSV/Excel | Manager+ |
| `products/import/mapping` | Map columns to fields | Manager+ |
| `products/import/preview` | Preview before import | Manager+ |
| `products/import/progress` | Import progress | Manager+ |
| `products/export` | Export products to file | Manager+ |
| `products/bulk-edit` | Edit multiple products | Manager+ |
| `products/bulk-price` | Bulk price update | Manager+ |
| `products/print-labels` | Print barcode labels | Manager+ |

---

## 5. Inventory Management

### 5.1 Stock Overview
| Page | Description | Access |
|------|-------------|--------|
| `inventory` | Stock overview dashboard | Manager+ |
| `inventory/by-branch` | Stock levels per branch | Manager+ |
| `inventory/alerts` | Low stock alerts | Manager+ |
| `inventory/valuation` | Total inventory value | Owner |

### 5.2 Stock Adjustments
| Page | Description | Access |
|------|-------------|--------|
| `inventory/adjust` | Manual stock adjustment | Manager+ |
| `inventory/adjust/add` | Add stock (received goods) | Manager+ |
| `inventory/adjust/remove` | Remove stock (damage, theft) | Manager+ |
| `inventory/adjust/count` | Physical stock count | Manager+ |
| `inventory/adjust/history` | Adjustment history | Manager+ |

### 5.3 Stock Count (Physical Inventory)
| Page | Description | Access |
|------|-------------|--------|
| `inventory/count` | Stock count sessions | Manager+ |
| `inventory/count/new` | Start new count | Manager+ |
| `inventory/count/{id}` | Count in progress | Manager+ |
| `inventory/count/{id}/scan` | Scan items for count | Manager+ |
| `inventory/count/{id}/discrepancies` | Review differences | Manager+ |
| `inventory/count/{id}/approve` | Approve count adjustments | Owner |
| `inventory/count/history` | Past count sessions | Manager+ |

### 5.4 Stock Transfers (Multi-Branch)
| Page | Description | Access |
|------|-------------|--------|
| `inventory/transfers` | All transfers list | Manager+ |
| `inventory/transfers/new` | Create transfer request | Manager+ |
| `inventory/transfers/{id}` | Transfer details | Manager+ |
| `inventory/transfers/{id}/approve` | Approve transfer | Manager+ |
| `inventory/transfers/{id}/receive` | Receive transferred stock | Manager+ |
| `inventory/transfers/pending` | Pending transfers | Manager+ |

### 5.5 Purchase Orders (Optional)
| Page | Description | Access |
|------|-------------|--------|
| `inventory/purchase-orders` | All POs list | Manager+ |
| `inventory/purchase-orders/new` | Create new PO | Manager+ |
| `inventory/purchase-orders/{id}` | PO details | Manager+ |
| `inventory/purchase-orders/{id}/receive` | Receive goods | Manager+ |
| `inventory/suppliers` | Supplier list | Manager+ |
| `inventory/suppliers/add` | Add supplier | Manager+ |

---

## 6. Orders Management

### 6.1 POS Orders (In-Store Sales)
| Page | Description | Access |
|------|-------------|--------|
| `orders/pos` | All POS sales list | Manager+ |
| `orders/pos/today` | Today's sales | Cashier+ |
| `orders/pos/{id}` | Sale details | Manager+ |
| `orders/pos/{id}/receipt` | View/reprint receipt | Cashier+ |
| `orders/pos/{id}/refund` | Process refund | Manager+ |
| `orders/pos/search` | Search past orders | Manager+ |

### 6.2 Delivery Orders (from Thawani)
| Page | Description | Access |
|------|-------------|--------|
| `orders/delivery` | All delivery orders | Manager+ |
| `orders/delivery/pending` | Pending orders (needs action) | Cashier+ |
| `orders/delivery/preparing` | Being prepared | Cashier+ |
| `orders/delivery/ready` | Ready for pickup | Cashier+ |
| `orders/delivery/completed` | Completed orders | Manager+ |
| `orders/delivery/cancelled` | Cancelled orders | Manager+ |
| `orders/delivery/{id}` | Delivery order details | Cashier+ |
| `orders/delivery/{id}/accept` | Accept order | Cashier+ |
| `orders/delivery/{id}/reject` | Reject order (with reason) | Manager+ |
| `orders/delivery/{id}/ready` | Mark as ready | Cashier+ |
| `orders/delivery/{id}/print` | Print order ticket | Cashier+ |

### 6.3 Order Notifications
| Page | Description | Access |
|------|-------------|--------|
| `orders/notifications` | Order notification settings | Manager+ |
| `orders/notifications/sound` | Notification sounds | Manager+ |
| `orders/notifications/auto-print` | Auto-print settings | Manager+ |

---

## 7. Thawani Integration

### 7.1 Connection & Status
| Page | Description | Access |
|------|-------------|--------|
| `thawani` | Thawani integration dashboard | Owner |
| `thawani/connect` | Connect to Thawani account | Owner |
| `thawani/disconnect` | Disconnect from Thawani | Owner |
| `thawani/status` | Connection status & health | Manager+ |

### 7.2 Product Sync
| Page | Description | Access |
|------|-------------|--------|
| `thawani/products` | Synced products overview | Manager+ |
| `thawani/products/pending` | Products pending sync | Manager+ |
| `thawani/products/failed` | Failed syncs | Manager+ |
| `thawani/products/settings` | Sync settings (auto/manual) | Manager+ |
| `thawani/products/sync-now` | Manual sync trigger | Manager+ |
| `thawani/products/mapping` | Product field mapping | Manager+ |

### 7.3 Stock Sync
| Page | Description | Access |
|------|-------------|--------|
| `thawani/stock` | Stock sync status | Manager+ |
| `thawani/stock/log` | Stock sync history | Manager+ |
| `thawani/stock/conflicts` | Sync conflicts | Manager+ |

### 7.4 Delivery Settings
| Page | Description | Access |
|------|-------------|--------|
| `thawani/delivery` | Delivery settings | Owner |
| `thawani/delivery/areas` | Delivery areas | Owner |
| `thawani/delivery/fees` | Delivery fees | Owner |
| `thawani/delivery/hours` | Operating hours | Owner |
| `thawani/delivery/minimum` | Minimum order amount | Owner |

### 7.5 Settlements
| Page | Description | Access |
|------|-------------|--------|
| `thawani/settlements` | All settlements list | Owner |
| `thawani/settlements/{id}` | Settlement details | Owner |
| `thawani/settlements/pending` | Pending settlements | Owner |
| `thawani/settlements/history` | Settlement history | Owner |
| `thawani/settlements/bank` | Bank account settings | Owner |

---

## 8. Customers Management

### 8.1 Customer List
| Page | Description | Access |
|------|-------------|--------|
| `customers` | All customers list | Manager+ |
| `customers/search` | Search customers | Cashier+ |
| `customers/add` | Add new customer | Cashier+ |
| `customers/{id}` | Customer profile | Manager+ |
| `customers/{id}/edit` | Edit customer | Manager+ |

### 8.2 Customer Details
| Page | Description | Access |
|------|-------------|--------|
| `customers/{id}/orders` | Customer order history | Manager+ |
| `customers/{id}/credit` | Credit balance | Manager+ |
| `customers/{id}/credit/add` | Add credit | Manager+ |
| `customers/{id}/credit/history` | Credit history | Manager+ |
| `customers/{id}/notes` | Customer notes | Manager+ |

### 8.3 Loyalty Program (Optional)
| Page | Description | Access |
|------|-------------|--------|
| `customers/loyalty` | Loyalty program settings | Owner |
| `customers/loyalty/points` | Points configuration | Owner |
| `customers/loyalty/rewards` | Available rewards | Owner |
| `customers/loyalty/tiers` | Customer tiers | Owner |

---

## 9. Users & Permissions

### 9.1 User List
| Page | Description | Access |
|------|-------------|--------|
| `users` | All users list | Owner |
| `users/active` | Active users | Owner |
| `users/inactive` | Deactivated users | Owner |
| `users/add` | Add new user | Owner |
| `users/{id}` | User profile | Owner |
| `users/{id}/edit` | Edit user | Owner |
| `users/{id}/deactivate` | Deactivate user | Owner |
| `users/{id}/reset-password` | Reset user password | Owner |
| `users/{id}/reset-pin` | Reset cashier PIN | Owner |

### 9.2 User Details
| Page | Description | Access |
|------|-------------|--------|
| `users/{id}/permissions` | User permissions | Owner |
| `users/{id}/branches` | Assigned branches | Owner |
| `users/{id}/activity` | Activity log | Owner |
| `users/{id}/shifts` | Shift history | Owner |
| `users/{id}/sales` | Sales performance | Owner |

### 9.3 Roles & Permissions
| Page | Description | Access |
|------|-------------|--------|
| `users/roles` | All roles list | Owner |
| `users/roles/add` | Create new role | Owner |
| `users/roles/{id}` | Role details | Owner |
| `users/roles/{id}/edit` | Edit role permissions | Owner |
| `users/roles/{id}/users` | Users with this role | Owner |

### 9.4 Default Roles
| Role | Description |
|------|-------------|
| Owner | Full access, billing, multi-branch |
| Manager | Branch management, reports, refunds |
| Cashier | POS only, basic functions |
| Inventory | Stock management focus |
| Viewer | Read-only access to reports |

---

## 10. Branch Management (Multi-Store)

### 10.1 Branch List
| Page | Description | Access |
|------|-------------|--------|
| `branches` | All branches list | Owner |
| `branches/add` | Add new branch | Owner |
| `branches/{id}` | Branch details | Owner |
| `branches/{id}/edit` | Edit branch | Owner |
| `branches/{id}/deactivate` | Deactivate branch | Owner |

### 10.2 Branch Details
| Page | Description | Access |
|------|-------------|--------|
| `branches/{id}/general` | Name, address, contact | Owner |
| `branches/{id}/hours` | Operating hours | Owner |
| `branches/{id}/staff` | Assigned staff | Owner |
| `branches/{id}/devices` | Registered POS devices | Owner |
| `branches/{id}/printers` | Printer configuration | Owner |
| `branches/{id}/zatca` | ZATCA device for branch | Owner |

### 10.3 Branch Analytics
| Page | Description | Access |
|------|-------------|--------|
| `branches/{id}/dashboard` | Branch dashboard | Manager+ |
| `branches/{id}/sales` | Branch sales report | Manager+ |
| `branches/{id}/inventory` | Branch inventory | Manager+ |
| `branches/{id}/performance` | Branch performance | Owner |

### 10.4 Multi-Branch Reports
| Page | Description | Access |
|------|-------------|--------|
| `branches/compare` | Compare branches | Owner |
| `branches/consolidated` | Consolidated report | Owner |
| `branches/rankings` | Branch rankings | Owner |

---

## 11. Reports & Analytics

### 11.1 Sales Reports
| Page | Description | Access |
|------|-------------|--------|
| `reports/sales` | Sales overview | Manager+ |
| `reports/sales/daily` | Daily sales report | Manager+ |
| `reports/sales/weekly` | Weekly sales report | Manager+ |
| `reports/sales/monthly` | Monthly sales report | Manager+ |
| `reports/sales/yearly` | Yearly sales report | Owner |
| `reports/sales/custom` | Custom date range | Manager+ |
| `reports/sales/by-hour` | Sales by hour of day | Manager+ |
| `reports/sales/by-day` | Sales by day of week | Manager+ |
| `reports/sales/by-payment` | Sales by payment method | Manager+ |
| `reports/sales/by-cashier` | Sales by cashier | Manager+ |
| `reports/sales/by-category` | Sales by category | Manager+ |

### 11.2 Product Reports
| Page | Description | Access |
|------|-------------|--------|
| `reports/products` | Product performance | Manager+ |
| `reports/products/top-selling` | Best sellers | Manager+ |
| `reports/products/low-selling` | Slow movers | Manager+ |
| `reports/products/profit-margin` | Profit margins | Owner |
| `reports/products/category-performance` | By category | Manager+ |

### 11.3 Inventory Reports
| Page | Description | Access |
|------|-------------|--------|
| `reports/inventory` | Inventory overview | Manager+ |
| `reports/inventory/valuation` | Stock valuation | Owner |
| `reports/inventory/movement` | Stock movement | Manager+ |
| `reports/inventory/low-stock` | Low stock report | Manager+ |
| `reports/inventory/expiring` | Expiring products | Manager+ |
| `reports/inventory/dead-stock` | Dead stock | Manager+ |

### 11.4 Statistics Dashboards

#### POS Statistics
| Page | Description | Access |
|------|-------------|--------|
| `stats/pos` | POS statistics dashboard | Manager+ |
| `stats/pos/transactions` | Transaction count trends | Manager+ |
| `stats/pos/average-basket` | Average basket size | Manager+ |
| `stats/pos/items-per-sale` | Items per transaction | Manager+ |
| `stats/pos/peak-hours` | Peak hours analysis | Manager+ |
| `stats/pos/refunds` | Refund statistics | Manager+ |
| `stats/pos/discounts` | Discount usage | Manager+ |
| `stats/pos/payment-methods` | Payment method breakdown | Manager+ |

#### Thawani/Delivery Statistics
| Page | Description | Access |
|------|-------------|--------|
| `stats/thawani` | Thawani orders dashboard | Manager+ |
| `stats/thawani/orders` | Order volume trends | Manager+ |
| `stats/thawani/revenue` | Delivery revenue | Manager+ |
| `stats/thawani/average-order` | Average order value | Manager+ |
| `stats/thawani/popular-items` | Popular delivery items | Manager+ |
| `stats/thawani/peak-times` | Peak ordering times | Manager+ |
| `stats/thawani/areas` | Orders by delivery area | Manager+ |
| `stats/thawani/cancellations` | Cancellation analysis | Manager+ |
| `stats/thawani/preparation-time` | Order prep time | Manager+ |

#### Combined Statistics
| Page | Description | Access |
|------|-------------|--------|
| `stats/combined` | POS + Thawani combined | Owner |
| `stats/combined/revenue` | Total revenue (all channels) | Owner |
| `stats/combined/channel-split` | POS vs Delivery split | Owner |
| `stats/combined/growth` | Growth comparison | Owner |

### 11.5 Staff Reports
| Page | Description | Access |
|------|-------------|--------|
| `reports/staff` | Staff performance | Owner |
| `reports/staff/{id}` | Individual performance | Owner |
| `reports/staff/attendance` | Attendance (shift times) | Owner |
| `reports/staff/sales-ranking` | Sales leaderboard | Manager+ |

### 11.6 Export & Schedule
| Page | Description | Access |
|------|-------------|--------|
| `reports/export` | Export any report | Manager+ |
| `reports/schedule` | Schedule automated reports | Owner |
| `reports/schedule/add` | Add scheduled report | Owner |
| `reports/schedule/{id}/edit` | Edit schedule | Owner |

---

## 12. Financial Management

### 12.1 Financial Overview
| Page | Description | Access |
|------|-------------|--------|
| `finance` | Financial dashboard | Owner |
| `finance/summary` | P&L summary | Owner |
| `finance/cash-flow` | Cash flow overview | Owner |

### 12.2 Cash Management
| Page | Description | Access |
|------|-------------|--------|
| `finance/cash` | Cash management | Owner |
| `finance/cash/drawer` | Cash drawer status | Manager+ |
| `finance/cash/transactions` | Cash transactions log | Owner |
| `finance/cash/reconciliation` | Cash reconciliation | Owner |

### 12.3 Payment Methods
| Page | Description | Access |
|------|-------------|--------|
| `finance/payments` | Payment summary | Owner |
| `finance/payments/cash` | Cash payments | Owner |
| `finance/payments/card` | Card payments | Owner |
| `finance/payments/credit` | Credit sales | Owner |
| `finance/payments/outstanding` | Outstanding balances | Owner |

### 12.4 Financial Reports
| Page | Description | Access |
|------|-------------|--------|
| `finance/reports` | Financial reports hub | Owner |
| `finance/reports/revenue` | Revenue report | Owner |
| `finance/reports/profit-loss` | P&L statement | Owner |
| `finance/reports/expenses` | Expenses breakdown | Owner |
| `finance/reports/tax` | Tax report (VAT) | Owner |
| `finance/reports/daily-summary` | Daily financial summary | Owner |
| `finance/reports/monthly-summary` | Monthly summary | Owner |

### 12.5 Thawani Settlements
| Page | Description | Access |
|------|-------------|--------|
| `finance/settlements` | Thawani settlements | Owner |
| `finance/settlements/pending` | Pending payouts | Owner |
| `finance/settlements/completed` | Completed payouts | Owner |
| `finance/settlements/{id}` | Settlement details | Owner |
| `finance/settlements/{id}/orders` | Orders in settlement | Owner |
| `finance/settlements/reconcile` | Reconcile with bank | Owner |

---

## 13. Invoices & ZATCA

### 13.1 Invoice List
| Page | Description | Access |
|------|-------------|--------|
| `invoices` | All invoices list | Manager+ |
| `invoices/search` | Search invoices | Manager+ |
| `invoices/today` | Today's invoices | Cashier+ |
| `invoices/pending` | Pending ZATCA submission | Manager+ |
| `invoices/failed` | Failed submissions | Manager+ |

### 13.2 Invoice Details
| Page | Description | Access |
|------|-------------|--------|
| `invoices/{id}` | Invoice details | Cashier+ |
| `invoices/{id}/pdf` | View PDF | Cashier+ |
| `invoices/{id}/print` | Print invoice | Cashier+ |
| `invoices/{id}/email` | Email to customer | Cashier+ |
| `invoices/{id}/zatca` | ZATCA submission status | Manager+ |
| `invoices/{id}/qr` | View ZATCA QR code | Cashier+ |

### 13.3 Credit Notes (Refunds)
| Page | Description | Access |
|------|-------------|--------|
| `invoices/credit-notes` | All credit notes | Manager+ |
| `invoices/credit-notes/{id}` | Credit note details | Manager+ |

### 13.4 ZATCA Integration
| Page | Description | Access |
|------|-------------|--------|
| `zatca` | ZATCA dashboard | Owner |
| `zatca/status` | Compliance status | Owner |
| `zatca/device` | Device registration | Owner |
| `zatca/device/register` | Register new device | Owner |
| `zatca/device/renew` | Renew certificate | Owner |
| `zatca/certificates` | Certificates management | Owner |

### 13.5 ZATCA Submissions
| Page | Description | Access |
|------|-------------|--------|
| `zatca/submissions` | All submissions | Owner |
| `zatca/submissions/pending` | Pending queue | Owner |
| `zatca/submissions/cleared` | Cleared invoices | Owner |
| `zatca/submissions/reported` | Reported invoices | Owner |
| `zatca/submissions/rejected` | Rejected (needs fix) | Owner |
| `zatca/submissions/{id}` | Submission details | Owner |
| `zatca/submissions/retry` | Retry failed | Owner |

### 13.6 ZATCA Reports
| Page | Description | Access |
|------|-------------|--------|
| `zatca/reports` | ZATCA reports | Owner |
| `zatca/reports/summary` | Submission summary | Owner |
| `zatca/reports/errors` | Error analysis | Owner |
| `zatca/reports/compliance` | Compliance report | Owner |

---

## 14. Subscription & Billing

### 14.1 Subscription Overview
| Page | Description | Access |
|------|-------------|--------|
| `subscription` | Current subscription | Owner |
| `subscription/plan` | Plan details | Owner |
| `subscription/usage` | Usage statistics | Owner |

### 14.2 Plan Management
| Page | Description | Access |
|------|-------------|--------|
| `subscription/plans` | Available plans | Owner |
| `subscription/upgrade` | Upgrade plan | Owner |
| `subscription/downgrade` | Downgrade plan | Owner |
| `subscription/addons` | Add-ons (extra branches, etc.) | Owner |
| `subscription/cancel` | Cancel subscription | Owner |

### 14.3 Billing
| Page | Description | Access |
|------|-------------|--------|
| `subscription/billing` | Billing overview | Owner |
| `subscription/billing/invoices` | Billing invoices | Owner |
| `subscription/billing/invoices/{id}` | Invoice details | Owner |
| `subscription/billing/payment-method` | Payment method | Owner |
| `subscription/billing/payment-method/add` | Add payment method | Owner |
| `subscription/billing/history` | Payment history | Owner |

### 14.4 Subscription Tiers
| Tier | Features | Price |
|------|----------|-------|
| Starter | 1 branch, 1 user, basic POS | 149 SAR/mo |
| Professional | 1 branch, 5 users, + Thawani | 249 SAR/mo |
| Business | 3 branches, 15 users, full features | 449 SAR/mo |
| Enterprise | Unlimited, API access, priority support | Custom |

---

## 15. Settings

### 15.1 General Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings` | Settings hub | Manager+ |
| `settings/general` | General settings | Owner |
| `settings/general/business` | Business info | Owner |
| `settings/general/locale` | Language, currency, timezone | Owner |
| `settings/general/branding` | Logo, colors | Owner |

### 15.2 POS Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/pos` | POS configuration | Manager+ |
| `settings/pos/display` | Grid/list, categories | Manager+ |
| `settings/pos/quick-keys` | Quick access products | Manager+ |
| `settings/pos/defaults` | Default settings | Manager+ |
| `settings/pos/sounds` | Sound settings | Manager+ |

### 15.3 Receipt Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/receipt` | Receipt configuration | Manager+ |
| `settings/receipt/template` | Receipt template | Manager+ |
| `settings/receipt/header` | Header text/logo | Manager+ |
| `settings/receipt/footer` | Footer text | Manager+ |
| `settings/receipt/preview` | Preview receipt | Manager+ |

### 15.4 Printer Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/printers` | Printer configuration | Manager+ |
| `settings/printers/add` | Add printer | Manager+ |
| `settings/printers/{id}` | Printer settings | Manager+ |
| `settings/printers/{id}/test` | Test print | Manager+ |
| `settings/printers/receipt` | Receipt printer | Manager+ |
| `settings/printers/kitchen` | Kitchen printer (orders) | Manager+ |
| `settings/printers/label` | Label printer | Manager+ |

### 15.5 Payment Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/payments` | Payment methods | Owner |
| `settings/payments/cash` | Cash settings | Owner |
| `settings/payments/card` | Card/Mada settings | Owner |
| `settings/payments/credit` | Credit sale settings | Owner |
| `settings/payments/rounding` | Rounding rules | Owner |

### 15.6 Tax Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/tax` | Tax configuration | Owner |
| `settings/tax/vat` | VAT settings (15%) | Owner |
| `settings/tax/inclusive` | Tax inclusive pricing | Owner |

### 15.7 Notification Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/notifications` | Notification preferences | Manager+ |
| `settings/notifications/orders` | Order notifications | Manager+ |
| `settings/notifications/stock` | Stock alerts | Manager+ |
| `settings/notifications/reports` | Report emails | Owner |

### 15.8 Integration Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/integrations` | All integrations | Owner |
| `settings/integrations/thawani` | Thawani settings | Owner |
| `settings/integrations/zatca` | ZATCA settings | Owner |
| `settings/integrations/accounting` | Accounting export | Owner |

### 15.9 Security Settings
| Page | Description | Access |
|------|-------------|--------|
| `settings/security` | Security settings | Owner |
| `settings/security/password` | Password policy | Owner |
| `settings/security/2fa` | Two-factor auth | Owner |
| `settings/security/sessions` | Active sessions | Owner |
| `settings/security/api-keys` | API keys | Owner |

### 15.10 Backup & Data
| Page | Description | Access |
|------|-------------|--------|
| `settings/backup` | Backup settings | Owner |
| `settings/backup/export` | Export all data | Owner |
| `settings/backup/history` | Backup history | Owner |
| `settings/data/delete` | Delete account | Owner |

---

## 16. Support & Help

### 16.1 Help Center
| Page | Description | Access |
|------|-------------|--------|
| `help` | Help center home | All |
| `help/getting-started` | Getting started guide | All |
| `help/articles` | Help articles | All |
| `help/articles/{slug}` | Single article | All |
| `help/search` | Search help | All |
| `help/videos` | Video tutorials | All |

### 16.2 Support Tickets
| Page | Description | Access |
|------|-------------|--------|
| `support` | Support hub | All |
| `support/tickets` | My tickets | All |
| `support/tickets/new` | Create new ticket | All |
| `support/tickets/{id}` | Ticket details | All |
| `support/tickets/{id}/reply` | Reply to ticket | All |
| `support/tickets/closed` | Closed tickets | All |

### 16.3 Contact & Feedback
| Page | Description | Access |
|------|-------------|--------|
| `support/contact` | Contact support | All |
| `support/chat` | Live chat (if available) | All |
| `support/feedback` | Send feedback | All |
| `support/feature-request` | Request feature | All |

### 16.4 System Status
| Page | Description | Access |
|------|-------------|--------|
| `support/status` | System status | All |
| `support/status/incidents` | Active incidents | All |
| `support/status/maintenance` | Scheduled maintenance | All |

### 16.5 About & Legal
| Page | Description | Access |
|------|-------------|--------|
| `about` | About the app | All |
| `about/version` | Version info | All |
| `about/changelog` | What's new | All |
| `legal/terms` | Terms of service | All |
| `legal/privacy` | Privacy policy | All |

---

## 📊 Page Count Summary

| Section | Page Count |
|---------|------------|
| Authentication & Onboarding | 17 |
| Dashboard | 4 |
| POS Terminal | 24 |
| Products Management | 28 |
| Inventory Management | 26 |
| Orders Management | 17 |
| Thawani Integration | 20 |
| Customers Management | 15 |
| Users & Permissions | 18 |
| Branch Management | 14 |
| Reports & Analytics | 45 |
| Financial Management | 22 |
| Invoices & ZATCA | 25 |
| Subscription & Billing | 14 |
| Settings | 35 |
| Support & Help | 18 |
| **TOTAL** | **~342 pages** |

---

## 🔐 Access Level Legend

| Level | Description |
|-------|-------------|
| Public | No login required |
| All | Any logged-in user |
| Cashier+ | Cashier and above |
| Manager+ | Manager and above |
| Owner | Owner only |

---

## 📱 Responsive Considerations

| Page Type | Desktop | Tablet | Mobile |
|-----------|---------|--------|--------|
| POS Terminal | ✅ Full | ✅ Full | ⚠️ Limited |
| Dashboard | ✅ Full | ✅ Full | ✅ Adapted |
| Reports | ✅ Full | ✅ Full | ⚠️ Simplified |
| Settings | ✅ Full | ✅ Full | ✅ Full |
| Products | ✅ Full | ✅ Full | ✅ List only |
| Orders | ✅ Full | ✅ Full | ✅ Full |

---

## 🚀 MVP vs Full Version

### MVP (Phase 1) - Core Pages Only
- Authentication (login, PIN)
- Basic onboarding
- POS terminal (core sales)
- Basic products management
- Simple inventory
- POS orders
- Basic reports (daily, sales)
- Simple settings
- ZATCA basic compliance

### Full Version (Phase 2+)
- All pages listed above
- Multi-branch
- Full Thawani integration
- Advanced analytics
- Loyalty program
- Purchase orders
- Scheduled reports
- API access
