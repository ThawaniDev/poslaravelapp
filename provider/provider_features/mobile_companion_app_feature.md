# Mobile Companion App — Comprehensive Feature Documentation

> **Scope:** Provider (Store Owner & Manager Mobile App — iOS + Android)  
> **Module:** Remote Monitoring, Push Notifications, Quick Actions, Real-Time Dashboard  
> **Tech Stack:** Flutter 3.x Mobile (iOS/Android) · Firebase · Laravel 11 · REST API  

---

## 1. Feature Overview

The Mobile Companion App is a lightweight Flutter mobile application for store owners and managers to monitor their business, receive critical alerts, and perform quick actions from their phone — without needing to be physically at the POS terminal.

### What This Feature Does
- **Real-time dashboard** — today's sales, orders, revenue at a glance; live transaction feed
- **Push notifications** — critical alerts (new delivery orders, large refunds, low stock, cash variance)
- **Sales reports** — daily/weekly/monthly sales charts on mobile
- **Order monitoring** — view active and recent orders; see order queue status
- **Staff monitoring** — who's clocked in now; attendance summary
- **Inventory alerts** — low stock and expiry notifications; approve purchase orders
- **Remote store control** — toggle store online/offline for delivery platforms; set operating hours
- **Cash session viewer** — view active cash sessions; see expected vs actual cash
- **Multi-branch overview** — switch between branches; aggregated view across all branches
- **Quick approve/reject** — approve/reject delivery orders, stock transfers, refund requests from mobile

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **All Provider Features** | Reads data from all modules via API |
| **Notifications** | Push notification delivery via FCM |
| **Offline/Online Sync** | Data freshness from cloud |
| **Roles & Permissions** | Feature access based on user role |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| — | No features depend on this (it's a consumer, not a producer) |

### Features to Review After Changing This Feature
1. **API endpoints** — mobile app shares API with POS Desktop; changes must be compatible
2. **Notifications** — FCM token lifecycle

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **flutter** | Cross-platform mobile app (iOS/Android) |
| **firebase_messaging** | FCM push notifications |
| **firebase_analytics** | App usage analytics |
| **flutter_local_notifications** | Local notification display |
| **dio** | HTTP client for REST API |
| **riverpod** / **flutter_bloc** | State management |
| **fl_chart** | Sales charts on mobile |
| **pull_to_refresh** | Pull-to-refresh UI pattern |
| **cached_network_image** | Image caching for products/staff photos |
| **flutter_secure_storage** | Secure token storage |
| **local_auth** | Biometric app lock (fingerprint / Face ID) |

### 3.2 Technologies
- **Flutter 3.x Mobile** — single codebase for iOS and Android
- **Firebase Cloud Messaging** — push notification delivery
- **REST API** — same Laravel backend as POS Desktop; mobile uses a subset of endpoints
- **JWT Authentication** — Bearer token auth; refresh token for seamless session
- **Biometric Lock** — optional app lock with fingerprint or Face ID
- **Deep Linking** — push notifications deep-link to relevant screen in the app

---

## 4. Screens

### 4.1 Home Dashboard
| Field | Detail |
|---|---|
| **Route** | `/home` |
| **Purpose** | Business overview at a glance |
| **Layout** | Cards: Today's Revenue, Orders Count, Average Order Value, Active Cashiers. Mini chart: Hourly sales trend. Recent transactions list (last 10). Branch selector (top). |
| **Pull-to-refresh** | Refreshes all dashboard data |

### 4.2 Sales Report Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/sales` |
| **Purpose** | Sales analytics |
| **Layout** | Period selector (today/week/month/custom), revenue chart, top selling products, sales by payment method, sales by cashier |

### 4.3 Orders Screen
| Field | Detail |
|---|---|
| **Route** | `/orders` |
| **Purpose** | View orders |
| **Layout** | Filterable list — status tabs (Active, Completed, Cancelled), order card (number, total, items, time, source), tap for detail |
| **Actions** | View detail, approve/reject delivery orders |

### 4.4 Inventory Alerts Screen
| Field | Detail |
|---|---|
| **Route** | `/inventory/alerts` |
| **Purpose** | Low stock and expiry alerts |
| **Layout** | List of alert cards — product name, current stock, reorder level, expiry date. Action buttons: Approve PO, Dismiss alert |

### 4.5 Staff Screen
| Field | Detail |
|---|---|
| **Route** | `/staff` |
| **Purpose** | Staff attendance monitoring |
| **Layout** | Currently clocked-in staff list, today's attendance summary (present/absent/late), tap for staff detail |

### 4.6 Notifications Screen
| Field | Detail |
|---|---|
| **Route** | `/notifications` |
| **Purpose** | Notification history |
| **Layout** | Chronological notification list with category icons; tap to deep-link to related screen |

### 4.7 Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings` |
| **Purpose** | App settings |
| **Layout** | Notification preferences, biometric lock toggle, default branch, language, logout |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints (Mobile-Specific)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/mobile/dashboard` | GET | Aggregated dashboard data | Bearer token |
| `GET /api/mobile/dashboard/branches` | GET | Per-branch summary for multi-branch overview | Bearer token |
| `GET /api/mobile/sales/summary` | GET | Sales summary by period | Bearer token |
| `GET /api/mobile/orders/active` | GET | Active orders across sources | Bearer token |
| `GET /api/mobile/inventory/alerts` | GET | Low stock and expiry alerts | Bearer token |
| `GET /api/mobile/staff/active` | GET | Currently clocked-in staff | Bearer token |
| `POST /api/mobile/orders/{id}/approve` | POST | Approve delivery order from mobile | Bearer token |
| `POST /api/mobile/orders/{id}/reject` | POST | Reject delivery order from mobile | Bearer token |
| `PUT /api/mobile/store/availability` | PUT | Toggle store online/offline | Bearer token |
| `POST /api/fcm-tokens` | POST | Register FCM token | Bearer token |
| `DELETE /api/fcm-tokens/{token}` | DELETE | Unregister FCM token | Bearer token |

> Most other endpoints are shared with the POS Desktop — see individual feature docs.

### 5.2 Flutter-Side Services (Mobile App)
| Service Class | Purpose |
|---|---|
| `MobileDashboardService` | Fetches and caches dashboard data |
| `MobileAuthService` | Login, JWT management, biometric lock |
| `PushNotificationService` | FCM token registration, notification handling, deep linking |
| `BranchSelectorService` | Multi-branch state management |
| `MobileReportService` | Sales report data fetching and chart preparation |
| `QuickActionService` | Approve/reject actions from push notifications |

---

## 6. Full Database Schema

> **Note:** The Mobile Companion App does **not** have its own database tables. It consumes existing data via the REST API. Local storage is limited to:
> - **Secure storage** — JWT tokens, biometric secrets
> - **Local cache** — dashboard data cache for offline viewing (last fetched data)
> - **SharedPreferences** — settings, selected branch, notification preferences

### 6.1 Local Storage Schema (SharedPreferences/SecureStorage)

```json
{
  "auth_token": "jwt...",
  "refresh_token": "jwt...",
  "selected_branch_id": "uuid",
  "biometric_enabled": true,
  "notification_prefs": {
    "new_delivery_order": true,
    "large_refund": true,
    "low_stock": true,
    "daily_summary": true
  },
  "cached_dashboard": { /* last fetched dashboard data */ },
  "cached_at": "2024-01-15T10:30:00Z"
}
```

### 6.2 Relationships Diagram
```
Mobile App ──REST API──▶ Laravel Backend ──▶ PostgreSQL (all existing tables)
Mobile App ──FCM──▶ Firebase ──▶ Push Notifications
```

---

## 7. Business Rules

1. **Read-heavy, write-light** — the mobile app is primarily for monitoring; write operations are limited to approve/reject, store toggle, and notification management
2. **Real-time data freshness** — dashboard data auto-refreshes every 60 seconds when app is in foreground; manual pull-to-refresh available
3. **Multi-branch default** — on first login, the user sees an aggregated view across all assigned branches; can switch to single branch
4. **Role-based access** — mobile app respects the same roles/permissions as POS Desktop; cashiers cannot access the mobile app (only Owner and Manager roles)
5. **Offline cache** — when offline, the app displays the last fetched data with a "Last updated X minutes ago" indicator; no actions can be performed offline
6. **Biometric lock timeout** — if biometric lock is enabled, the app requires re-authentication after 5 minutes of inactivity
7. **Push notification permission** — app requests push notification permission on first login; if denied, shows in-app notification center only
8. **Delivery order push priority** — new delivery order push notifications are sent as high-priority (wake device, play sound) even in Do Not Disturb mode (if OS allows)
9. **Session management** — mobile JWT expires after 30 days; refresh token extends automatically; if both expire, user must re-login
10. **Deep link routing** — tapping a push notification opens the relevant screen (e.g., "New delivery order" opens order detail); if the app is not logged in, deep link is queued until after login
