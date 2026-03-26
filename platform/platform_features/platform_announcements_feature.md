# Platform Announcements — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Provider-Facing Announcements, Payment Reminders, Maintenance Notices, Scheduled Banners  
> **Tech Stack:** Laravel 11 + Filament v3 · Spatie Permission · Spatie ActivityLog · Queue (Redis)  

---

## 1. Feature Overview

Platform Announcements lets the Thawani team broadcast messages to providers through in-app banners, push notifications, and email. Announcements can target all stores or be filtered by plan, region, or specific store IDs. The feature also automates payment reminders for upcoming and overdue subscription renewals and pushes ZATCA deadline alerts.

### What This Feature Does
- Send announcements to all stores or filtered by plan / region
- Announcement types: info, warning, maintenance, update
- System maintenance notifications with scheduled time window
- Payment reminder automation for upcoming / overdue renewals
- ZATCA deadline alerts
- In-app banner display with start / end date scheduling

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Billing & Finance** | Payment reminders reference subscription + invoice data |
| **Notification Templates** | Announcement dispatch may use notification templates for email/push channel |
| **System Configuration** | Maintenance mode toggle generates a maintenance-type announcement automatically |
| **App Update Management** | Update-type announcements link to new release info |
| **Provider Management** | Active banners visible in provider detail view |

### Features to Review After Changing This Feature
1. **Notification Templates** — announcement email/push channels consume templates; template changes may affect rendering
2. **Billing & Finance** — payment reminder logic references `store_subscriptions` and `invoices` tables
3. **System Configuration** — maintenance mode toggle should auto-create/end a maintenance announcement

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for Announcements, Payment Reminders |
| **spatie/laravel-permission** | `announcements.view`, `announcements.manage`, `announcements.send` |
| **spatie/laravel-activitylog** | Audit trail for announcement creation and deletion |
| **laravel/notifications** | Multi-channel dispatch (in-app, push, email) |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | Eloquent, Scheduled Commands (reminder automation), Notifications, Queue |
| **Filament v3** | Admin UI |
| **PostgreSQL** | Announcements + reminders storage |
| **Redis** | Queue for batch notification dispatch; active announcement cache (60s TTL) |
| **FCM** | Push notification delivery for announcements |

### 3.3 Announcement Display Flow
```
Provider App (Flutter or Web Portal)
  → GET /api/v1/announcements/active
  ← Returns announcements where NOW() BETWEEN display_start_at AND display_end_at
     AND (target_filter matches store's plan/region OR target_filter = all)
  → Render as in-app banner (dismissible for info/update; persistent for warning/maintenance)
```

---

## 4. Pages

### 4.1 Announcements List
| Field | Detail |
|---|---|
| **Route** | `/admin/announcements` |
| **Filament Resource** | `PlatformAnnouncementResource` |
| **Table Columns** | Type badge (info/warning/maintenance/update), Title, Target (All / Plan:X / Region:Y), Display Start, Display End, Is Banner, Created By, Created At |
| **Filters** | Type, Is Active (now within display window), Created date range |
| **Search** | By title |
| **Row Actions** | View, Edit, Delete, Duplicate |
| **Header Actions** | Create Announcement |
| **Access** | `announcements.view` |

### 4.2 Create / Edit Announcement
| Field | Detail |
|---|---|
| **Route** | `/admin/announcements/create` or `/{id}/edit` |
| **Form Fields** | Type (select: info/warning/maintenance/update), Title (EN), Title (AR), Body (EN, rich text), Body (AR, rich text), Target Filter section: Target Scope (all / by_plan / by_region / by_stores), Plan IDs (multi-select, shown if by_plan), Region (text, shown if by_region), Store IDs (searchable multi-select, shown if by_stores), Display Start At (datetime), Display End At (datetime), Is Banner toggle, Send Push Notification toggle, Send Email toggle |
| **Validation** | Display End must be after Display Start; Body max 2000 chars |
| **Access** | `announcements.manage` |

### 4.3 Announcement Detail
| Field | Detail |
|---|---|
| **Route** | `/admin/announcements/{id}` |
| **Sections** | Full announcement content (EN + AR), Target filter details, Display window, Delivery stats (if push/email sent): sent count, delivered count, opened count |
| **Access** | `announcements.view` |

### 4.4 Payment Reminders List
| Field | Detail |
|---|---|
| **Route** | `/admin/announcements/payment-reminders` |
| **Filament Resource** | `PaymentReminderResource` (read-only) |
| **Table Columns** | Store Name, Subscription Plan, Reminder Type badge (upcoming/overdue), Channel (email/sms/push), Sent At |
| **Filters** | Reminder type, Channel, Date range |
| **Note** | Reminders are auto-generated by scheduled jobs; this page is for monitoring/audit |
| **Access** | `announcements.view` |

### 4.5 Reminder Configuration (sub-page or settings tab)
| Field | Detail |
|---|---|
| **Route** | `/admin/announcements/reminder-settings` |
| **Purpose** | Configure when automatic reminders fire |
| **Form Fields** | Days Before Renewal for "Upcoming" reminder (default: 3), Days After Due Date for "Overdue" reminder (default: 1, 3, 7), Channels (multi-select: email, sms, push), Enable/Disable automation toggle |
| **Access** | `announcements.manage` |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
CRUD for announcements; read-only for payment reminders.

### 5.2 Provider-Facing APIs
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/announcements/active` | GET | Returns active announcements for the calling store (filtered by plan/region + within display window) | Store API token |
| `POST /api/v1/announcements/{id}/dismiss` | POST | Mark announcement as dismissed for this store (won't show again) | Store API token |

### 5.3 Internal Jobs
| Job | Purpose | Schedule |
|---|---|---|
| `SendUpcomingRenewalReminders` | Finds subscriptions renewing within N days, sends reminder if not already sent | Daily at 09:00 |
| `SendOverduePaymentReminders` | Finds invoices past due by N days, sends overdue reminder | Daily at 09:00 |
| `ExpireAnnouncements` | Sets announcements past `display_end_at` to inactive (optional cleanup) | Hourly |
| `DispatchAnnouncementNotifications` | Queued: sends push/email for a newly created announcement | On announcement create (if push/email toggled) |

---

## 6. Full Database Schema

### 6.1 Tables

#### `platform_announcements`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| type | VARCHAR(20) | NOT NULL | info / warning / maintenance / update |
| title | VARCHAR(200) | NOT NULL | EN |
| title_ar | VARCHAR(200) | NOT NULL | AR |
| body | TEXT | NOT NULL | EN, supports Markdown/HTML |
| body_ar | TEXT | NOT NULL | AR |
| target_filter | JSONB | NOT NULL DEFAULT '{"scope":"all"}' | `{"scope":"all"}` or `{"scope":"by_plan","plan_ids":["…"]}` or `{"scope":"by_region","region":"Riyadh"}` or `{"scope":"by_stores","store_ids":["…"]}` |
| display_start_at | TIMESTAMP | NOT NULL | |
| display_end_at | TIMESTAMP | NOT NULL | |
| is_banner | BOOLEAN | DEFAULT FALSE | Show as persistent top banner in provider app |
| send_push | BOOLEAN | DEFAULT FALSE | Dispatch push notifications on create |
| send_email | BOOLEAN | DEFAULT FALSE | Dispatch email on create |
| created_by | UUID | FK → admin_users(id) | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE platform_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type VARCHAR(20) NOT NULL,
    title VARCHAR(200) NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    target_filter JSONB NOT NULL DEFAULT '{"scope":"all"}',
    display_start_at TIMESTAMP NOT NULL,
    display_end_at TIMESTAMP NOT NULL,
    is_banner BOOLEAN DEFAULT FALSE,
    send_push BOOLEAN DEFAULT FALSE,
    send_email BOOLEAN DEFAULT FALSE,
    created_by UUID NOT NULL REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_announcements_display ON platform_announcements (display_start_at, display_end_at);
CREATE INDEX idx_announcements_type ON platform_announcements (type);
```

#### `platform_announcement_dismissals` (optional — track per-store dismissal)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| announcement_id | UUID | FK → platform_announcements(id) ON DELETE CASCADE | |
| store_id | UUID | FK → stores(id) | |
| dismissed_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE platform_announcement_dismissals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    announcement_id UUID NOT NULL REFERENCES platform_announcements(id) ON DELETE CASCADE,
    store_id UUID NOT NULL REFERENCES stores(id),
    dismissed_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (announcement_id, store_id)
);
```

#### `payment_reminders`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_subscription_id | UUID | FK → store_subscriptions(id) | |
| reminder_type | VARCHAR(20) | NOT NULL | upcoming / overdue |
| channel | VARCHAR(10) | NOT NULL | email / sms / push |
| sent_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE payment_reminders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_subscription_id UUID NOT NULL REFERENCES store_subscriptions(id),
    reminder_type VARCHAR(20) NOT NULL,
    channel VARCHAR(10) NOT NULL,
    sent_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_payment_reminders_sub_type ON payment_reminders (store_subscription_id, reminder_type);
CREATE INDEX idx_payment_reminders_sent ON payment_reminders (sent_at);
```

### 6.2 Indexes Summary

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `idx_announcements_display` | (display_start_at, display_end_at) | B-TREE | Active announcement queries |
| `idx_announcements_type` | type | B-TREE | Filter by type |
| `announcement_dismissals_composite` | (announcement_id, store_id) | UNIQUE | One dismissal per store per announcement |
| `idx_payment_reminders_sub_type` | (store_subscription_id, reminder_type) | B-TREE | Prevent duplicate reminders |
| `idx_payment_reminders_sent` | sent_at | B-TREE | Date range audit queries |

---

## 7. Business Rules

1. **Display window** — announcements only returned by the API when `NOW() BETWEEN display_start_at AND display_end_at`
2. **Target filtering** — API resolves target by checking store's plan and region against `target_filter` JSONB; scope `all` matches every store
3. **Dismissal** — info and update announcements can be dismissed per store; warning and maintenance announcements are non-dismissible (persistent banner)
4. **Maintenance auto-link** — enabling maintenance mode in System Configuration auto-creates a maintenance-type announcement with the banner message and expected end time
5. **Payment reminder deduplication** — before sending a reminder, the job checks if a row with the same `(store_subscription_id, reminder_type, channel)` exists within the last 24 hours; skip if already sent
6. **Overdue escalation** — overdue reminders are sent at 1, 3, and 7 days past due; each is a separate `payment_reminders` row
7. **Push/email is optional** — creating an announcement with `send_push = false` and `send_email = false` means it's banner-only (shown when the provider app fetches active announcements)
8. **Batch dispatch** — when push or email is enabled, `DispatchAnnouncementNotifications` chunks target stores into batches of 100 to avoid queue overload
9. **Audit** — announcement creation, edit, and deletion are logged in `admin_activity_logs`
10. **ZATCA deadline alerts** — implemented as a warning-type announcement with a target of `all`; admin creates manually or via a scheduled template
