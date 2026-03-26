# Permissions & Roles — Complete Extraction Report

> **Source:** All 30 feature documentation files in `provider_features/`  
> **Generated:** Comprehensive extraction of every permission key and predefined role  

---

## Table of Contents

1. [Permissions Per File](#1-permissions-per-file)
2. [Consolidated Unique Permissions (by Module)](#2-consolidated-unique-permissions-by-module)
3. [Predefined Roles](#3-predefined-roles)
4. [Role ↔ Permission Matrix (Documented Defaults)](#4-role--permission-matrix)
5. [Permission Naming Pattern](#5-permission-naming-pattern)
6. [Observations — Missing Permissions](#6-observations--missing-permissions)

---

## 1. Permissions Per File

### 1.1 `accessibility_feature.md`
| Permission | Context |
|---|---|
| *(none)* | All accessibility settings are personal preferences available to all users |

### 1.2 `accounting_integration_feature.md`
| Permission | Context |
|---|---|
| `accounting.connect` | Connect/disconnect accounting integration |
| `accounting.configure` | Configure account mapping and auto-export settings |
| `accounting.export` | Trigger manual exports |
| `accounting.view_history` | View export history |

**Roles mentioned:** Store Owner (full access), Store Manager (view + manual export + history), Accountant (full access), Cashier/Staff (no access)

### 1.3 `auto_updates_feature.md`
| Permission | Context |
|---|---|
| `settings.updates` | Update settings screen access |

**Roles mentioned:** Owner

### 1.4 `backup_recovery_feature.md`
| Permission | Context |
|---|---|
| `settings.backup` | Backup settings screen access |

**Roles mentioned:** Owner, Manager (backup settings); Owner only (restore wizard, data export)

### 1.5 `barcode_label_printing_feature.md`
| Permission | Context |
|---|---|
| `labels.manage` | Label template designer access |
| `labels.view` | View label templates |
| `labels.print` | Print labels |

**Roles mentioned:** Cashier (`labels.print`), Inventory Clerk (`labels.print`), Branch Manager (`labels.manage`, `labels.print`), Owner (`labels.manage`, `labels.print`)

### 1.6 `business_type_onboarding_feature.md`
| Permission | Context |
|---|---|
| *(none explicitly defined)* | All APIs gated by "Bearer token, Owner" — no permission key strings |

**Roles mentioned:** Owner (for all onboarding steps)

### 1.7 `customer_management_feature.md`
| Permission | Context |
|---|---|
| `customers.view` | View customer directory, profiles, history, POS lookup |
| `customers.manage` | Create, edit, delete customers; adjust loyalty; top up store credit |
| `settings.manage` | Loyalty configuration (Owner) |

**Roles mentioned:** All cashiers (`customers.view` at POS), Owner (`settings.manage` for loyalty)

### 1.8 `delivery_integrations_feature.md`
| Permission | Context |
|---|---|
| `delivery.manage` | Configuration of delivery platforms, menu sync |
| `orders.manage` | Order handling/status push |
| `orders.view` | View delivery orders dashboard |

**Roles mentioned:** Owner, Branch Manager (for `delivery.manage`)

### 1.9 `hardware_support_feature.md`
| Permission | Context |
|---|---|
| `settings.hardware` | Hardware configuration access |

**Roles mentioned:** Not explicitly listed per role

### 1.10 `industry_specific_workflows_feature.md`
| Permission | Context |
|---|---|
| *(none)* | No permission keys defined. All APIs use "Bearer token" only |

**Roles mentioned:** None explicitly

### 1.11 `inventory_management_feature.md`
| Permission | Context |
|---|---|
| `inventory.view` | View stock levels, movements, expiry dashboard |
| `inventory.manage` | Goods receipt, purchase orders, recipes |
| `inventory.adjust` | Stock adjustments, waste recording |
| `inventory.transfer` | Stock transfers (create, approve, receive) |
| `inventory.stocktake` | Physical count workflow |

**Roles mentioned:** Referenced in dependencies but not explicitly mapped per role in this file

### 1.12 `language_localization_feature.md`
| Permission | Context |
|---|---|
| *(none explicitly defined)* | Translation override API gated by Owner role; language preference is per-user |

**Roles mentioned:** Owner (for overrides), all users (personal language preference)

### 1.13 `mobile_companion_app_feature.md`
| Permission | Context |
|---|---|
| *(none explicitly defined)* | App respects same permissions as POS Desktop |

**Roles mentioned:** Owner, Manager only (cashiers cannot access mobile app — business rule #4)

### 1.14 `nice_to_have_features_feature.md`
| Permission | Context |
|---|---|
| *(none explicitly defined)* | Multiple sub-features (CFD, signage, appointments, gift registry, wishlist, gamification) with no permission key strings |

**Roles mentioned:** Owner, Manager, Staff (for various screens)

### 1.15 `notifications_feature.md`
| Permission | Context |
|---|---|
| `settings.manage` | Store-wide notification defaults |

**Roles mentioned:** Role-based notification routing (order → Cashier+; inventory → Inventory Clerk, Branch Manager, Owner; financial → Manager, Owner)

### 1.16 `offline_online_sync_feature.md`
| Permission | Context |
|---|---|
| `settings.sync` | Sync details panel and conflict resolution (Manager+) |

**Roles mentioned:** Manager and above

### 1.17 `order_management_feature.md`
| Permission | Context |
|---|---|
| `orders.view` | View order queue, history, detail |
| `orders.manage` | Create orders, update status, add notes |
| `orders.return` | Process returns and exchanges |
| `orders.void` | Void/cancel orders (manager approval after grace period) |

**Roles mentioned:** Referenced via permission assignments

### 1.18 `payments_finance_feature.md`
| Permission | Context |
|---|---|
| `payments.process` | Accept payments, issue gift cards (Cashier+) |
| `payments.refund` | Process refunds |
| `cash.manage` | Cash management (open/close sessions, cash-in/out, expenses) |
| `reports.view_financial` | Financial reconciliation, payment listings |

**Roles mentioned:** Cashier (own drawer), Manager (all drawers), Owner, Accountant

### 1.19 `pos_interface_customization_feature.md`
| Permission | Context |
|---|---|
| `settings.manage` | Store-level POS customization, receipt templates, quick-access grid |

**Roles mentioned:** All users (own terminal preferences), `settings.manage` holders (store-wide defaults)

### 1.20 `pos_terminal_feature.md`
| Permission | Context |
|---|---|
| `pos.sell` | POS Main Screen, Payment Screen, Hold/Recall (Cashier, Branch Manager, Owner) |
| `pos.shift_open` | Open a cash shift |
| `pos.shift_close` | Close a cash shift |
| `pos.return` | Process returns/refunds (Branch Manager+; Cashier excluded by default) |
| `pos.tax_exempt` | Tax-exempt sale toggle (Branch Manager+) |
| `pos.approve_discount` | Manager PIN override for discounts above cashier threshold (business rule #2) |

**Roles mentioned:** Cashier (`pos.sell`), Branch Manager (`pos.sell`, `pos.return`, `pos.tax_exempt`), Owner (all)

### 1.21 `product_catalog_feature.md`
| Permission | Context |
|---|---|
| `products.view` | Product List Screen (all staff except Kitchen) |
| `products.manage` | Product Create/Edit, Bulk Import, Category Mgmt, Barcode generation, Supplier CRUD |
| `reports.view_margin` | Cost price and margin visibility (business rule #11) |
| `inventory.view` | Supplier Directory Screen |

**Roles mentioned:** Inventory Clerk, Branch Manager, Owner (`products.manage`)

### 1.22 `promotions_coupons_feature.md`
| Permission | Context |
|---|---|
| `promotions.manage` | Promotion/coupon CRUD, enable/disable, batch coupon generation |
| `promotions.apply_manual` | Manually apply/remove promotions at POS (business rule #8) |
| `reports.view` | Promotion analytics (alongside `promotions.manage`) |

**Roles mentioned:** Referenced via permission assignments

### 1.23 `reports_analytics_feature.md`
| Permission | Context |
|---|---|
| `reports.view` | Sales Dashboard, Sales Reports, Product Performance, Customer Reports, Inventory Reports, Scheduled reports, Exports |
| `reports.view_margin` | Product Margin Analysis tab |
| `reports.view_financial` | Financial Reports Screen (daily P&L, expense, payment method breakdown) |
| `inventory.view` | Inventory Reports Screen (combined with `reports.view`) |
| `staff.view` | Staff Performance Screen (combined with `reports.view`) |
| `customers.view` | Customer Reports Screen (combined with `reports.view`) |

**Roles mentioned:** Referenced via permission gates on APIs/screens

### 1.24 `roles_permissions_feature.md` ⭐ KEY FILE
| Permission | Context |
|---|---|
| `roles.view` | Roles List Screen |
| `roles.edit` | Role Editor Screen — edit permissions for a role |
| `roles.create` | Create custom role / duplicate a role |
| `roles.delete` | Delete custom role (only if no users assigned) |
| `roles.audit` | Permission Audit Log Screen (Owner only by default) |

**Predefined roles seeded on store creation:** Owner, Branch Manager, Cashier, Inventory Clerk, Accountant, Viewer  
**Permission modules:** POS, Inventory, Finance, Reports, Settings  
**Pattern examples:** `pos.void_transaction`, `inventory.adjust_stock`, `finance.view_reports`

### 1.25 `security_provider_feature.md`
| Permission | Context |
|---|---|
| *(no new permission key strings)* | Security is a cross-cutting concern; Owner-only for security settings, audit logs, device management |

**Roles mentioned:** Owner (security settings, audit log, device management, 2FA, remote wipe)

### 1.26 `staff_user_management_feature.md`
| Permission | Context |
|---|---|
| `staff.view` | Staff List Screen, Staff Profile viewing, Activity Log |
| `staff.edit` | Staff Profile editing, NFC badge registration, Commission config |
| `staff.create` | Create staff member |
| `staff.delete` | Deactivate staff (soft delete) |
| `staff.manage_pin` | Set/reset staff PIN |
| `staff.manage_shifts` | Shift Schedule Screen management |
| `staff.training_mode` | Training Mode Screen access |
| `reports.attendance` | Attendance Report Screen, Export |
| `finance.commissions` | Commission summary viewing |

**Roles mentioned:** Referenced via permission gates on APIs/screens

### 1.27 `store_owner_web_dashboard_feature.md`
| Permission | Context |
|---|---|
| *(no new permission keys)* | Re-uses existing permissions; access filtered by role |

**Roles mentioned:** Owner (full), Manager (orders, products, staff — branch-filtered), Accountant (orders, financial/ZATCA — read-only)

### 1.28 `subscription_billing_feature.md`
| Permission | Context |
|---|---|
| `settings.view` | Subscription Status Screen (Owner, Branch Manager) |

**Roles mentioned:** Owner (billing management), Branch Manager (view status)

### 1.29 `thawani_integration_feature.md`
| Permission | Context |
|---|---|
| `settings.thawani` | Thawani Connection Settings (Owner) |
| `thawani.menu` | Online Menu Management, product publishing, store availability |
| `finance.settlements` | Settlement Reports Screen |

**Roles mentioned:** Owner (`settings.thawani`), Accountant (`finance.settlements`), all POS staff (Thawani order queue)

### 1.30 `zatca_compliance_feature.md`
| Permission | Context |
|---|---|
| *(no new permission key strings)* | Owner only for ZATCA settings/certificate; Owner + Accountant for compliance dashboard |

**Roles mentioned:** Owner, Accountant

---

## 2. Consolidated Unique Permissions (by Module)

### POS Module (6 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `pos.sell` | Process sales, take payments, hold/recall carts | pos_terminal |
| `pos.shift_open` | Open a cash shift | pos_terminal |
| `pos.shift_close` | Close a cash shift | pos_terminal |
| `pos.return` | Process returns and refunds | pos_terminal |
| `pos.tax_exempt` | Apply tax exemption to sales | pos_terminal |
| `pos.approve_discount` | Approve discount above cashier threshold (PIN) | pos_terminal |
| `pos.void_transaction` | Void a transaction (PIN) | roles_permissions (example) |

### Products Module (2 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `products.view` | View product catalog | product_catalog |
| `products.manage` | Create/edit/delete products, categories, barcodes, suppliers | product_catalog |

### Inventory Module (5 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `inventory.view` | View stock levels, supplier directory | inventory_management, product_catalog, reports_analytics |
| `inventory.manage` | Goods receipt, purchase orders, recipes | inventory_management |
| `inventory.adjust` | Stock adjustments, waste recording | inventory_management |
| `inventory.transfer` | Stock transfers (create/approve/receive) | inventory_management |
| `inventory.stocktake` | Physical count workflow | inventory_management |

> **Note:** `inventory.adjust_stock` is referenced in roles_permissions business rule #12 as an example, likely an alias/alternate form of `inventory.adjust`.

### Orders Module (4 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `orders.view` | View order queue, history, detail | order_management, delivery_integrations |
| `orders.manage` | Create orders, update status, add notes | order_management, delivery_integrations |
| `orders.return` | Process returns and exchanges | order_management |
| `orders.void` | Void/cancel orders | order_management |

### Customers Module (2 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `customers.view` | View customer directory, profiles, history | customer_management, reports_analytics |
| `customers.manage` | Create/edit/delete customers; adjust loyalty; top up credit | customer_management |

### Payments / Finance Module (5 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `payments.process` | Accept payments, issue gift cards | payments_finance |
| `payments.refund` | Process refunds | payments_finance |
| `cash.manage` | Cash management (sessions, cash-in/out, expenses) | payments_finance |
| `finance.commissions` | View staff commission data | staff_user_management |
| `finance.settlements` | View Thawani settlement reports | thawani_integration |

> **Note:** `finance.view_reports` is mentioned in roles_permissions business rule #12 as an example — possibly an alias for `reports.view_financial`.

### Reports Module (4 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `reports.view` | Sales dashboard, sales reports, product performance, customer/inventory overview | reports_analytics, promotions_coupons |
| `reports.view_margin` | Cost price and margin data visibility | product_catalog, reports_analytics |
| `reports.view_financial` | Financial reports (P&L, expense, payment breakdown) | payments_finance, reports_analytics |
| `reports.attendance` | Attendance reports and export | staff_user_management |

### Promotions Module (2 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `promotions.manage` | CRUD promotions/coupons, batch generation | promotions_coupons |
| `promotions.apply_manual` | Manually apply/remove promotions at POS | promotions_coupons |

### Labels Module (3 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `labels.manage` | Label template designer | barcode_label_printing |
| `labels.view` | View label templates | barcode_label_printing |
| `labels.print` | Print labels | barcode_label_printing |

### Accounting Module (4 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `accounting.connect` | Connect/disconnect accounting integration | accounting_integration |
| `accounting.configure` | Configure account mapping, auto-export settings | accounting_integration |
| `accounting.export` | Trigger manual exports | accounting_integration |
| `accounting.view_history` | View export history | accounting_integration |

### Staff Module (7 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `staff.view` | View staff list, profiles, activity log | staff_user_management, reports_analytics |
| `staff.edit` | Edit staff profiles, NFC badge, commission config | staff_user_management |
| `staff.create` | Create staff members | staff_user_management |
| `staff.delete` | Deactivate staff (soft delete) | staff_user_management |
| `staff.manage_pin` | Set/reset staff PIN | staff_user_management |
| `staff.manage_shifts` | Manage shift schedules | staff_user_management |
| `staff.training_mode` | Access training mode | staff_user_management |

### Roles Module (5 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `roles.view` | View roles list | roles_permissions |
| `roles.edit` | Edit role permissions | roles_permissions |
| `roles.create` | Create/duplicate custom role | roles_permissions |
| `roles.delete` | Delete custom role | roles_permissions |
| `roles.audit` | View permission audit log | roles_permissions |

### Delivery Module (1 permission)
| Permission | Description | Source File(s) |
|---|---|---|
| `delivery.manage` | Configure delivery platforms, menu sync | delivery_integrations |

### Settings Module (7 permissions)
| Permission | Description | Source File(s) |
|---|---|---|
| `settings.manage` | Store-level settings, notifications, POS customization, loyalty config, receipt templates | customer_management, notifications, pos_interface_customization |
| `settings.view` | View settings (subscription status) | subscription_billing |
| `settings.updates` | Update settings | auto_updates |
| `settings.backup` | Backup settings | backup_recovery |
| `settings.hardware` | Hardware configuration | hardware_support |
| `settings.sync` | Sync management panel | offline_online_sync |
| `settings.thawani` | Thawani connection settings | thawani_integration |

### Thawani Module (1 permission)
| Permission | Description | Source File(s) |
|---|---|---|
| `thawani.menu` | Online menu management, product publishing, store availability | thawani_integration |

---

### Total: **58 unique permission keys** across 14 modules

---

## 3. Predefined Roles

From `roles_permissions_feature.md` (business rule #1), these roles are seeded on store creation with `is_predefined = true`:

| # | Role | Internal Name | Description |
|---|---|---|---|
| 1 | **Owner** | `owner` | Full access to everything. Permissions are immutable — cannot be restricted. Every store must have at least one Owner. |
| 2 | **Branch Manager** | `branch_manager` | Broad operational access: sales, returns, discounts, inventory, staff scheduling, delivery management. Can PIN-override restricted actions for cashiers. |
| 3 | **Cashier** | `cashier` | POS sales operations: sell, hold/recall, basic customer lookup. Cannot return, void, or apply discounts above threshold without manager PIN. Cannot access mobile app. |
| 4 | **Inventory Clerk** | `inventory_clerk` | Inventory-focused: stock adjustments, goods receipt, stocktake, label printing. Product catalog management. |
| 5 | **Accountant** | `accountant` | Financial read/write: accounting integration, financial reports, ZATCA compliance, settlement reconciliation. No POS sales access. |
| 6 | **Viewer** | `viewer` | Read-only access across permitted modules. Cannot create, edit, or delete anything. |

> **Custom roles** can also be created by the store owner with hand-picked permissions.

---

## 4. Role ↔ Permission Matrix

Based on documented access control across all 30 files:

| Permission | Owner | Branch Mgr | Cashier | Inv. Clerk | Accountant | Viewer |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **POS** | | | | | | |
| `pos.sell` | ✅ | ✅ | ✅ | — | — | — |
| `pos.shift_open` | ✅ | ✅ | ✅ | — | — | — |
| `pos.shift_close` | ✅ | ✅ | ✅ | — | — | — |
| `pos.return` | ✅ | ✅ | — | — | — | — |
| `pos.tax_exempt` | ✅ | ✅ | — | — | — | — |
| `pos.approve_discount` | ✅ | ✅ | — | — | — | — |
| `pos.void_transaction` | ✅ | ✅ | — | — | — | — |
| **Products** | | | | | | |
| `products.view` | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| `products.manage` | ✅ | ✅ | — | ✅ | — | — |
| **Inventory** | | | | | | |
| `inventory.view` | ✅ | ✅ | — | ✅ | — | ✅ |
| `inventory.manage` | ✅ | ✅ | — | ✅ | — | — |
| `inventory.adjust` | ✅ | ✅ | — | ✅ | — | — |
| `inventory.transfer` | ✅ | ✅ | — | ✅ | — | — |
| `inventory.stocktake` | ✅ | ✅ | — | ✅ | — | — |
| **Orders** | | | | | | |
| `orders.view` | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| `orders.manage` | ✅ | ✅ | ✅ | — | — | — |
| `orders.return` | ✅ | ✅ | — | — | — | — |
| `orders.void` | ✅ | ✅ | — | — | — | — |
| **Customers** | | | | | | |
| `customers.view` | ✅ | ✅ | ✅ | — | — | ✅ |
| `customers.manage` | ✅ | ✅ | — | — | — | — |
| **Payments / Finance** | | | | | | |
| `payments.process` | ✅ | ✅ | ✅ | — | — | — |
| `payments.refund` | ✅ | ✅ | — | — | — | — |
| `cash.manage` | ✅ | ✅ | ✅* | — | — | — |
| `finance.commissions` | ✅ | ✅ | — | — | ✅ | — |
| `finance.settlements` | ✅ | — | — | — | ✅ | — |
| **Reports** | | | | | | |
| `reports.view` | ✅ | ✅ | — | — | ✅ | ✅ |
| `reports.view_margin` | ✅ | — | — | — | ✅ | — |
| `reports.view_financial` | ✅ | — | — | — | ✅ | — |
| `reports.attendance` | ✅ | ✅ | — | — | — | — |
| **Promotions** | | | | | | |
| `promotions.manage` | ✅ | ✅ | — | — | — | — |
| `promotions.apply_manual` | ✅ | ✅ | — | — | — | — |
| **Labels** | | | | | | |
| `labels.manage` | ✅ | ✅ | — | — | — | — |
| `labels.view` | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| `labels.print` | ✅ | ✅ | ✅ | ✅ | — | — |
| **Accounting** | | | | | | |
| `accounting.connect` | ✅ | — | — | — | ✅ | — |
| `accounting.configure` | ✅ | — | — | — | ✅ | — |
| `accounting.export` | ✅ | ✅ | — | — | ✅ | — |
| `accounting.view_history` | ✅ | ✅ | — | — | ✅ | — |
| **Staff** | | | | | | |
| `staff.view` | ✅ | ✅ | — | — | — | — |
| `staff.edit` | ✅ | — | — | — | — | — |
| `staff.create` | ✅ | — | — | — | — | — |
| `staff.delete` | ✅ | — | — | — | — | — |
| `staff.manage_pin` | ✅ | — | — | — | — | — |
| `staff.manage_shifts` | ✅ | ✅ | — | — | — | — |
| `staff.training_mode` | ✅ | ✅ | ✅ | — | — | — |
| **Roles** | | | | | | |
| `roles.view` | ✅ | ✅ | — | — | — | — |
| `roles.edit` | ✅ | — | — | — | — | — |
| `roles.create` | ✅ | — | — | — | — | — |
| `roles.delete` | ✅ | — | — | — | — | — |
| `roles.audit` | ✅ | — | — | — | — | — |
| **Delivery** | | | | | | |
| `delivery.manage` | ✅ | ✅ | — | — | — | — |
| **Settings** | | | | | | |
| `settings.manage` | ✅ | — | — | — | — | — |
| `settings.view` | ✅ | ✅ | — | — | — | — |
| `settings.updates` | ✅ | — | — | — | — | — |
| `settings.backup` | ✅ | — | — | — | — | — |
| `settings.hardware` | ✅ | ✅ | — | — | — | — |
| `settings.sync` | ✅ | ✅ | — | — | — | — |
| `settings.thawani` | ✅ | — | — | — | — | — |
| **Thawani** | | | | | | |
| `thawani.menu` | ✅ | ✅ | — | — | — | — |

> \* Cashier has `cash.manage` scoped to **own drawer only**; Manager has access to **all drawers**.

---

## 5. Permission Naming Pattern

From `roles_permissions_feature.md` business rule #12:

```
{module}.{action}
```

### Modules
| Module Code | Domain |
|---|---|
| `pos` | POS Terminal operations |
| `products` | Product catalog management |
| `inventory` | Stock and warehouse operations |
| `orders` | Order processing |
| `customers` | Customer CRM |
| `payments` | Payment processing |
| `cash` | Cash drawer management |
| `finance` | Financial operations (commissions, settlements) |
| `reports` | Reporting and analytics |
| `promotions` | Promotions and coupons |
| `labels` | Barcode label printing |
| `accounting` | Accounting integration |
| `staff` | Staff/user management |
| `roles` | Role and permission management |
| `delivery` | Delivery platform integration |
| `settings` | System configuration |
| `thawani` | Thawani marketplace integration |

### Action patterns
| Action Pattern | Meaning |
|---|---|
| `.view` | Read-only access |
| `.manage` | Full CRUD (create/read/update/delete) |
| `.create` | Create only |
| `.edit` | Edit only |
| `.delete` | Delete/deactivate |
| `.{specific_action}` | Granular action (e.g., `sell`, `return`, `void_transaction`, `apply_manual`) |
| `.view_{sub}` | Scoped read access (e.g., `view_margin`, `view_financial`) |

### Wildcard inheritance
Per business rule #7 in roles_permissions: granting `{module}.*` automatically grants all child permissions in that module (e.g., `inventory.*` grants `inventory.view`, `inventory.manage`, `inventory.adjust`, `inventory.transfer`, `inventory.stocktake`).

---

## 6. Observations — Missing Permissions

Features that describe actions and screens but **do not define explicit permission key strings**:

| File | Actions Without Explicit Permissions | Recommendation |
|---|---|---|
| `accessibility_feature.md` | Accessibility settings (font size, contrast, etc.) | Intentional — personal preferences for all users. No permission needed. |
| `business_type_onboarding_feature.md` | Business type selection, category seed, store profile setup | Should define `onboarding.manage` or rely on Owner-only gating at route level. |
| `industry_specific_workflows_feature.md` | Pharmacy prescriptions, jewelry serial tracking, mobile IMEI, flower orders, bakery production, restaurant tables/KDS | **Major gap** — this is a large feature file with no permissions. Should define permissions like `workflows.pharmacy`, `workflows.jewelry`, etc., or at minimum `industry.manage`. |
| `language_localization_feature.md` | Translation overrides, language switching | Could define `settings.translations` for the override capability. Language preference is personal — no permission needed. |
| `mobile_companion_app_feature.md` | All mobile actions | Relies on same permissions as POS Desktop. No mobile-specific permissions — this is correct. |
| `nice_to_have_features_feature.md` | CFD settings, digital signage, appointments, gift registry, wishlist, gamification | Each sub-feature should define its own permission when implemented (e.g., `signage.manage`, `appointments.manage`, `gift_registry.manage`). |
| `security_provider_feature.md` | Security settings, audit log viewing, device management, remote wipe | Could define `security.manage`, `security.audit_view`, `security.device_manage`. Currently gated by Owner role only. |
| `zatca_compliance_feature.md` | Certificate enrollment, invoice submission retry, compliance dashboard, VAT report | Could define `zatca.manage` (certificate operations), `zatca.view` (compliance dashboard). Currently gated by Owner/Accountant roles. |
| `store_owner_web_dashboard_feature.md` | All dashboard actions | Re-uses existing permissions — no new ones needed. Correct approach. |
| `subscription_billing_feature.md` | Plan upgrade request, billing history, invoice download | Could define `billing.view`, `billing.manage_upgrade`. Currently gated by Owner role. |

### PIN-Override Protected Actions
These actions can optionally require manager PIN (configured in `security_policies` table):

| Action | Permission | Default PIN Required |
|---|---|---|
| Void transaction | `pos.void_transaction` | Yes |
| Process return | `pos.return` | Yes |
| Discount above threshold | `pos.approve_discount` | Yes (threshold configurable, default 20%) |
| Cash drawer open (manual) | — | Configured per store |
| Price override | — | Configured per store |
| Return without receipt | — | Yes |

### Potential Missing Permissions (not documented anywhere)
| Suggested Permission | Rationale |
|---|---|
| `pos.open_drawer` | Manual cash drawer open — mentioned in security_provider as PIN-protectable but no permission key defined |
| `pos.price_override` | Manual price override at POS — mentioned in security PIN triggers but no permission key |
| `products.delete` | Separate from `products.manage` for stricter control |
| `orders.export` | Export order data — currently lumped with `reports.view` |
| `customers.export` | Export customer data — privacy-sensitive, should be separate |
| `customers.delete` | GDPR/data regulation compliance — separate delete permission |
| `inventory.approve_po` | Purchase order approval — separate from `inventory.manage` |
| `reports.export` | Export reports — could be separated from `reports.view` |
| `staff.view_activity` | View staff activity log — currently lumped with `staff.view` |
| `settings.receipt_template` | Receipt template management — currently lumped with `settings.manage` |

---

## Summary Statistics

| Metric | Count |
|---|---|
| Total files analyzed | 30 |
| Files with explicit permissions | 20 |
| Files with NO explicit permissions | 10 |
| Total unique permission keys | 58 |
| Permission modules | 17 |
| Predefined roles | 6 |
| PIN-protectable actions | 6+ |
| Suggested missing permissions | 10 |
