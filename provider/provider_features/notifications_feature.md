# Notifications — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Mobile Companion + Store Owner Web Dashboard)  
> **Module:** In-App Notifications, Push Notifications, Email Alerts, Sound Alerts  
> **Tech Stack:** Flutter 3.x Desktop · Firebase Cloud Messaging · Laravel 11 · WebSocket · Email  

---

## 1. Feature Overview

The Notification system delivers timely alerts about business events to store owners, managers, and staff across all provider touchpoints — POS Desktop, Mobile Companion App, Web Dashboard, and email. Notifications cover order events, inventory alerts, financial alerts, staff actions, system events, and delivery platform updates.

### What This Feature Does
- **In-app notifications** — notification bell with unread count on POS Desktop and Web Dashboard
- **Push notifications** — Firebase Cloud Messaging (FCM) to Mobile Companion App
- **Email notifications** — configurable email alerts for critical events (daily summary, low stock, large refunds)
- **Sound alerts** — audio alerts on POS for new delivery orders, low stock warnings, and errors
- **Notification categories** — orders, inventory, finance, staff, system, delivery
- **Notification preferences** — per-user, per-channel (in-app, push, email, sound) enable/disable per category
- **Quiet hours** — suppresses non-critical notifications during configured hours
- **Delivery order alerts** — high-priority sound and visual alert for incoming delivery platform orders
- **Mark as read** — individual and bulk mark-as-read
- **Notification history** — scrollable list of all past notifications with links to relevant screens

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Order Management** | Order status changes trigger notifications |
| **Inventory Management** | Low stock and expiry alerts |
| **Payments & Finance** | Large refund alerts, cash variance alerts |
| **Delivery Integrations** | New delivery order alerts |
| **Staff & User Management** | Clock-in/out notifications, new staff alerts |
| **Roles & Permissions** | Notification routing based on user role |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Notification bell icon and sound alerts |
| **Mobile Companion App** | Push notifications |
| **Store Owner Web Dashboard** | Notification panel |
| **Reports & Analytics** | Daily summary email |

### Features to Review After Changing This Feature
1. **Mobile Companion App** — FCM token registration and push handling
2. **POS Terminal** — notification bell rendering and sound playback

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **firebase_messaging** | FCM push notifications for Mobile Companion App |
| **flutter_local_notifications** | Local notification display on POS Desktop (system tray) |
| **audioplayers** | Play sound alerts (new order chime, error beep) |
| **drift** | SQLite ORM — local notification storage and read state |
| **riverpod** / **flutter_bloc** | State management for unread count, notification list |
| **dio** | HTTP client for notification preference API and history fetch |
| **web_socket_channel** | Real-time notification delivery to POS Desktop |

### 3.2 Technologies
- **Firebase Cloud Messaging (FCM)** — push notifications to Mobile Companion App (Android/iOS)
- **WebSocket** — real-time notification delivery to POS Desktop (Laravel WebSocket server via Pusher/Soketi)
- **Laravel 11 Notification System** — `Notification` classes with multiple channels (database, FCM, mail)
- **Laravel Events + Listeners** — business events trigger notification dispatch
- **Email (SMTP / Mailgun)** — email notifications for configured events
- **Flutter Desktop system tray** — show notification count badge in Windows system tray

---

## 4. Screens

### 4.1 Notification Centre (POS / Web Dashboard)
| Field | Detail |
|---|---|
| **Route** | Slide-out panel from notification bell icon |
| **Purpose** | View recent notifications |
| **Layout** | Scrollable list; grouped by date; each item: icon, title, message, time ago, read/unread indicator |
| **Actions** | Mark as read (single), Mark all as read, Click to navigate to related screen |
| **Unread Badge** | Bell icon shows unread count; number resets on mark-as-read |
| **Categories** | Tabs or filter: All, Orders, Inventory, Finance, Staff, System |
| **Access** | All authenticated users (see own notifications) |

### 4.2 Notification Preferences Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/notifications` |
| **Purpose** | Configure which notifications to receive and on which channels |
| **Layout** | Matrix: rows = notification types, columns = channels (In-app, Push, Email, Sound) |
| **Notification Types** | New delivery order, Order status change, Low stock alert, Product expiry alert, Large refund, Cash variance alert, Daily summary, New staff member, System update available, Backup completed |
| **Quiet Hours** | Start time, End time — suppresses non-critical notifications |
| **Access** | All users (own preferences), `settings.manage` (store defaults) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/notifications` | GET | Paginated notification list | Bearer token |
| `PUT /api/notifications/{id}/read` | PUT | Mark single notification as read | Bearer token |
| `PUT /api/notifications/read-all` | PUT | Mark all as read | Bearer token |
| `GET /api/notifications/unread-count` | GET | Unread notification count | Bearer token |
| `GET /api/notification-preferences` | GET | Get user's notification preferences | Bearer token |
| `PUT /api/notification-preferences` | PUT | Update notification preferences | Bearer token |
| `POST /api/fcm-tokens` | POST | Register FCM token (Mobile App) | Bearer token |
| `DELETE /api/fcm-tokens/{token}` | DELETE | Unregister FCM token | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `NotificationRepository` | Local notification storage (Drift); manages read/unread state |
| `NotificationStreamService` | WebSocket listener for real-time notifications on POS Desktop |
| `NotificationSoundService` | Plays audio alerts based on notification category |
| `NotificationPreferenceService` | Manages per-user channel preferences |
| `PushNotificationService` | FCM token management and push handler (Mobile App) |
| `UnreadCountService` | Reactive unread count for badge display |

---

## 6. Full Database Schema

### 6.1 Tables

#### `notifications`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| user_id | UUID | FK → users(id), NOT NULL | Recipient |
| store_id | UUID | FK → stores(id), NULLABLE | |
| category | VARCHAR(30) | NOT NULL | orders, inventory, finance, staff, system, delivery |
| title | VARCHAR(255) | NOT NULL | |
| message | TEXT | NOT NULL | |
| action_url | VARCHAR(500) | NULLABLE | Deep link to related screen |
| reference_type | VARCHAR(50) | NULLABLE | order, product, transfer, etc. |
| reference_id | UUID | NULLABLE | ID of related entity |
| is_read | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    store_id UUID REFERENCES stores(id),
    category VARCHAR(30) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500),
    reference_type VARCHAR(50),
    reference_id UUID,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `notification_preferences`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| user_id | UUID | FK → users(id), NOT NULL, UNIQUE | |
| preferences_json | JSONB | NOT NULL | { "new_delivery_order": {"in_app": true, "push": true, "email": false, "sound": true}, ... } |
| quiet_hours_start | TIME | NULLABLE | |
| quiet_hours_end | TIME | NULLABLE | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE notification_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE REFERENCES users(id),
    preferences_json JSONB NOT NULL DEFAULT '{}',
    quiet_hours_start TIME,
    quiet_hours_end TIME,
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `fcm_tokens`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| user_id | UUID | FK → users(id), NOT NULL | |
| token | TEXT | NOT NULL | FCM device token |
| device_type | VARCHAR(20) | NOT NULL | android, ios |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE fcm_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    token TEXT NOT NULL,
    device_type VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `notification_events_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| notification_id | UUID | FK → notifications(id), NOT NULL | |
| channel | VARCHAR(20) | NOT NULL | in_app, push, email, sound |
| status | VARCHAR(20) | NOT NULL | sent, delivered, failed |
| error_message | TEXT | NULLABLE | For failed deliveries |
| sent_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE notification_events_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    notification_id UUID NOT NULL REFERENCES notifications(id),
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `notifications_user_read` | (user_id, is_read) | B-TREE | Unread count and listing |
| `notifications_user_created` | (user_id, created_at) | B-TREE | Chronological listing |
| `notifications_category` | category | B-TREE | Category filter |
| `fcm_tokens_user` | user_id | B-TREE | Tokens per user |
| `notification_events_notification` | notification_id | B-TREE | Delivery tracking |

### 6.3 Relationships Diagram
```
users ──1:N──▶ notifications
users ──1:1──▶ notification_preferences
users ──1:N──▶ fcm_tokens
notifications ──1:N──▶ notification_events_log
```

---

## 7. Business Rules

1. **Notification routing by role** — order notifications go to Cashier and above; inventory alerts go to Inventory Clerk, Branch Manager, Owner; financial alerts go to Manager and Owner only
2. **Quiet hours enforcement** — during quiet hours, only "critical" notifications are delivered (new delivery order with accept timer, system errors); all others are queued and delivered after quiet hours end
3. **Delivery order priority** — new delivery order notifications always play sound regardless of quiet hours setting; they have highest visual priority in the notification list
4. **FCM token cleanup** — tokens not refreshed within 30 days are automatically deleted; FCM errors (invalid token) trigger immediate deletion
5. **Notification retention** — notifications older than 90 days are soft-deleted; delivery is permanent in the events log for 1 year
6. **Unread count cap** — unread badge displays up to 99; above that, shows "99+"
7. **Email throttling** — maximum 10 email notifications per user per hour to prevent spam; excess emails are batched into a digest
8. **Sound cooldown** — after playing a sound alert, no new sound plays for at least 3 seconds to prevent audio overlap
9. **Batch notification** — if multiple similar events occur in quick succession (e.g. 5 low stock alerts), they are grouped into a single notification: "5 products are running low on stock"
10. **Default preferences** — new users get all in-app and sound notifications enabled; push and email are disabled by default (opt-in)
