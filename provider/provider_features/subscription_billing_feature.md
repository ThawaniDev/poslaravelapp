# Subscription & Billing — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Subscription Plans, Feature Gates, Usage Metering, Invoice Display  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11 · Thawani Pay  

---

## 1. Feature Overview

Subscription & Billing on the provider side is primarily a **read-only consumer** of the platform-managed subscription system. The store owner views their current plan, feature entitlements, usage metrics, and billing history. Plan upgrades/downgrades are initiated from the Store Owner Web Dashboard or Mobile App and processed by the platform. The POS Desktop enforces feature gates based on the subscription tier.

### What This Feature Does
- **Plan display** — current plan name, tier, renewal date, price shown on POS settings and Web Dashboard
- **Feature gates** — features locked/unlocked based on subscription tier (e.g., multi-branch requires Pro plan)
- **Usage metering display** — show current usage vs plan limits (products, staff, branches, transactions/month)
- **Billing history** — list of past invoices with download links (PDF)
- **Plan upgrade prompts** — when user hits a limit, show upgrade CTA with plan comparison
- **Grace period handling** — POS continues operating during payment grace period with warnings
- **Offline entitlement cache** — subscription entitlements cached locally; POS doesn't need internet to check features

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Platform Subscription Management** | Plan definitions, pricing, invoices managed on platform side |
| **Thawani Integration** | Payment processing for subscription |
| **Offline/Online Sync** | Subscription status synced to local cache |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **All Features** | Feature gates determine which modules are accessible |
| **Product Catalog** | Product count limit |
| **Staff & User Management** | Staff count limit |
| **Reports & Analytics** | Advanced reports locked to higher tiers |
| **Multi-Branch** | Branch count limit |
| **Delivery Integrations** | Locked to higher tiers |

### Features to Review After Changing This Feature
1. **All feature gate checks** — when plan limits change, every gated feature must be reviewed
2. **Offline sync** — entitlement cache format changes affect sync

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local subscription entitlement cache |
| **riverpod** / **flutter_bloc** | State management for current plan, feature gate checks |
| **dio** | HTTP client for subscription API |
| **url_launcher** | Open payment portal in browser from POS |

### 3.2 Technologies
- **Feature flag system** — local map of `feature_code → bool` derived from subscription tier; checked by `FeatureGateService`
- **Usage metering** — local counts (products, staff, branches) compared against plan limits
- **Thawani Pay** — all subscription payments processed via Thawani payment gateway (managed by platform)
- **Webhook-driven updates** — platform receives Thawani webhook → updates subscription status → POS syncs on next heartbeat

---

## 4. Screens

### 4.1 Subscription Status Screen (POS Settings)
| Field | Detail |
|---|---|
| **Route** | `/settings/subscription` |
| **Purpose** | View current subscription status |
| **Layout** | Card showing: plan name, tier badge, renewal date, days remaining, usage bars (products used/limit, staff used/limit, branches used/limit) |
| **Actions** | "Manage Subscription" button → opens Web Dashboard in browser |
| **Warnings** | Amber banner if nearing limits; Red banner if expired/grace period |
| **Access** | `settings.view` permission (Owner, Branch Manager) |

### 4.2 Feature Gate Prompt Dialog
| Field | Detail |
|---|---|
| **Route** | Modal dialog (triggered when hitting a limit) |
| **Purpose** | Inform user that a feature requires upgrade |
| **Layout** | Feature name, current plan, required plan, plan comparison mini-table, "Upgrade Now" button |
| **Action** | Upgrade button opens Web Dashboard billing page |

### 4.3 Billing History Screen (Web Dashboard)
| Field | Detail |
|---|---|
| **Route** | `/dashboard/billing` |
| **Purpose** | View past invoices and payment history |
| **Layout** | Data table — invoice number, date, amount, status (paid, pending, failed), download PDF link |
| **Actions** | Download Invoice, Retry Payment (for failed) |
| **Access** | Owner only |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/subscription` | GET | Current subscription status and entitlements | Bearer token |
| `GET /api/subscription/usage` | GET | Current usage vs plan limits | Bearer token |
| `GET /api/subscription/invoices` | GET | Billing history (paginated) | Bearer token, Owner |
| `GET /api/subscription/invoices/{id}/pdf` | GET | Download invoice PDF | Bearer token, Owner |
| `POST /api/subscription/upgrade-request` | POST | Request plan upgrade (initiates payment flow) | Bearer token, Owner |
| `GET /api/sync/subscription` | GET | Subscription entitlements for offline cache | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `SubscriptionRepository` | Local Drift storage for subscription entitlements cache |
| `FeatureGateService` | Central feature gate checker — `isFeatureEnabled(String code)`, `getLimit(String resource)` |
| `UsageMeterService` | Counts local entities and compares against plan limits |
| `SubscriptionSyncService` | Syncs subscription status from cloud on each heartbeat |
| `UpgradePromptService` | Shows upgrade dialogs with plan comparison |

---

## 6. Full Database Schema

> **Note:** The primary subscription tables (`subscription_plans`, `store_subscriptions`, `subscription_invoices`) are managed on the **platform side**. The provider side reads these via API and caches entitlements locally.

### 6.1 Tables (Provider-Side Local/Cloud)

#### `subscription_entitlement_cache` (Local SQLite only)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INTEGER | PK, AUTO_INCREMENT | Local only |
| store_id | UUID | NOT NULL | |
| plan_code | VARCHAR(50) | NOT NULL | e.g., "starter", "pro", "enterprise" |
| plan_name | VARCHAR(100) | NOT NULL | |
| features_json | TEXT | NOT NULL | JSON map of feature_code → enabled/limit |
| expires_at | TIMESTAMP | NOT NULL | Subscription expiry |
| grace_period_ends_at | TIMESTAMP | NULLABLE | |
| synced_at | TIMESTAMP | NOT NULL | Last sync from cloud |

> This is a **Drift (SQLite) only** table — not in PostgreSQL.

#### `subscription_usage_snapshots`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| resource_type | VARCHAR(50) | NOT NULL | products, staff, branches, transactions_month |
| current_count | INTEGER | NOT NULL | |
| plan_limit | INTEGER | NOT NULL | -1 = unlimited |
| snapshot_date | DATE | NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE subscription_usage_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    resource_type VARCHAR(50) NOT NULL,
    current_count INTEGER NOT NULL,
    plan_limit INTEGER NOT NULL,
    snapshot_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(organization_id, resource_type, snapshot_date)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `usage_snapshots_org_date` | (organization_id, snapshot_date) | B-TREE | Usage trend queries |
| `usage_snapshots_org_resource` | (organization_id, resource_type) | B-TREE | Per-resource queries |

### 6.3 Relationships Diagram
```
organizations ──1:N──▶ subscription_usage_snapshots
stores ──1:1──▶ subscription_entitlement_cache (local SQLite)

Platform-managed (read-only from provider):
    subscription_plans ──1:N──▶ store_subscriptions
    store_subscriptions ──1:N──▶ subscription_invoices
```

---

## 7. Business Rules

1. **Feature gate enforcement** — if a feature is gated and the store's plan doesn't include it, the UI element is hidden and the API returns 403; existing data is preserved but read-only
2. **Soft limits** — when approaching a limit (e.g., 90% of product count), show amber warning; at 100%, block creation with upgrade prompt
3. **Grace period** — after subscription expiry, a 7-day grace period allows continued use with a persistent banner; after grace period, POS enters read-only mode (can view but not create transactions)
4. **Offline entitlement cache** — POS trusts local cache for up to 30 days without sync; after 30 days without internet, POS enters degraded mode
5. **Plan downgrade data preservation** — if a store downgrades and exceeds the new plan's limits, existing data is preserved but they cannot create new items until usage is under the limit
6. **Transaction limit** — monthly transaction count resets on the first day of each billing cycle; if exceeded, POS shows warning but continues to operate (no hard block on sales)
7. **Invoice auto-generation** — invoices are generated on the platform side; provider can only view and download
8. **Trial period** — new stores get a 14-day trial of Pro features; after trial, they default to Starter plan unless they upgrade
9. **Multi-branch gate** — branch count is strictly enforced; cannot create more branches than the plan allows
10. **Upgrade effective immediately** — plan upgrades take effect immediately after payment; new entitlements are synced within 60 seconds
