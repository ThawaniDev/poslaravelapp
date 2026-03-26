# Notification Template Management — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Notification Templates, Event Catalog & Channel Configuration  
> **Tech Stack:** Laravel 11 + Filament v3 · Laravel Notifications · FCM · Unifonic / Taqnyat / Msegat  

---

## 1. Feature Overview

Notification Template Management gives Thawani admin centralised control over **every message the system sends** to providers' staff, owner accounts, and end-customers. Templates are bilingual (Arabic / English), variable-driven, and shared across all providers on the platform.

### What This Feature Does
- Manage notification **message templates** for all system events, in both Arabic and English
- Define **available variable tokens** per template (e.g. `{{platform}}`, `{{order_id}}`, `{{total}}`, `{{store_name}}`)
- Cover all event categories: order events, inventory events, finance events, system events, staff events
- Support multiple channels: in-app, push (FCM/APNs), SMS, email, WhatsApp Business API
- Templates apply platform-wide — all providers receive notifications rendered from the same templates
- **Preview** rendered notification before saving
- Toggle templates active/inactive per event + channel combination

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Third-Party Delivery Platform Mgmt** | `order.new_external` template uses `{{platform}}` sourced from delivery platform name |
| **Package & Subscription Mgmt** | `system.license_expiring` template uses `{{plan_name}}`, `{{expiry_date}}` |
| **Support Ticket System** | Ticket status-change notifications use templates |
| **Platform Announcements** | Announcement notifications may reuse template infrastructure |
| **Billing & Finance** | `finance.daily_summary`, payment failure, invoice ready templates |
| **App & Update Management** | `system.update_available` template |
| **Every Provider Feature** | All provider-side notifications (orders, inventory, shifts) are rendered through these templates |

### Features to Review After Changing This Feature
1. **Laravel Notification classes** — each class calls `NotificationTemplate::render(event_key, channel, variables)`
2. **Provider notification preferences** — preference matrix references the same `event_key` values
3. **SMS / email / WhatsApp provider config** (System Configuration) — channel credentials must remain valid
4. **Analytics** — notification delivery stats reference `event_key` + channel

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resource for NotificationTemplate CRUD |
| **laravel/framework** (Notifications) | Multi-channel notification dispatch |
| **kreait/laravel-firebase** or **laravel-notification-channels/fcm** | FCM push notifications |
| **unifonic or taqnyat SDK** | SMS delivery (Saudi providers) |
| **mailgun/ses** (via Laravel Mail) | Email delivery |
| **netflie/laravel-notification-channels-whatsapp** *(or custom)* | WhatsApp Business API |
| **spatie/laravel-activitylog** | Audit template changes |

### 3.2 Technologies
- **Laravel 11** — Notifications, Channels, Queues, Blade (for template rendering)
- **Filament v3** — Admin CRUD UI
- **PostgreSQL** — template storage
- **Redis** — queue for notification dispatch (async)
- **Blade / Mustache-style** — variable interpolation (`{{variable}}`) in template bodies

---

## 4. Pages

### 4.1 Notification Templates List
| Field | Detail |
|---|---|
| **Route** | `/admin/notifications/templates` |
| **Filament Resource** | `NotificationTemplateResource` |
| **Table Columns** | Event Key, Channel badge, Title (EN), Title (AR), Is Active badge, Last Updated |
| **Filters** | Channel (in_app / push / sms / email / whatsapp), Event category (order / inventory / finance / system / staff), Active/Inactive |
| **Search** | By event_key, title |
| **Row Actions** | Edit, Preview, Toggle Active |
| **Access** | `notifications.manage` |

### 4.2 Template Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/notifications/templates/create` · `/admin/notifications/templates/{id}/edit` |
| **Form Sections** | |
| — Event & Channel | Event Key select (grouped by category: Order Events, Inventory Events, Finance Events, System Events, Staff Events), Channel select (in_app / push / sms / email / whatsapp) |
| — English Content | Title (EN), Body (EN) — textarea with variable token buttons |
| — Arabic Content | Title (AR), Body (AR) — textarea with variable token buttons |
| — Available Variables | Read-only display of valid tokens for the selected event (e.g. `{{order_id}}`, `{{total}}`, `{{store_name}}`, `{{platform}}`). Defined in `available_variables` JSONB |
| — Settings | Is Active toggle |
| **Validation** | (event_key, channel) must be unique; title required in both languages; body required |
| **Access** | `notifications.manage` |

### 4.3 Template Preview
| Field | Detail |
|---|---|
| **Route** | Modal from Edit page |
| **Purpose** | Render the template with sample variable values and show how it will appear per channel |
| **Sample Data** | Auto-generated or admin-editable sample values for each variable |
| **Display** | Side-by-side EN / AR preview; push notification mockup, SMS text, email HTML |

### 4.4 Notification Event Catalog (read-only reference)
| Field | Detail |
|---|---|
| **Route** | `/admin/notifications/events` |
| **Purpose** | Reference page listing all supported events with their descriptions and available variables |
| **Table Columns** | Event Key, Category, Description, Default Recipients, Available Variables |
| **Access** | `notifications.manage` (read-only) |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD on `NotificationTemplateResource`.

### 5.2 Internal Service Layer (not exposed as external API)
| Service Method | Purpose |
|---|---|
| `NotificationTemplateService::render(string $eventKey, string $channel, array $variables, string $locale): RenderedNotification` | Fetches template, interpolates variables, returns title + body in requested locale |
| `NotificationTemplateService::getAvailableVariables(string $eventKey): array` | Returns the variable tokens available for a given event key |

### 5.3 Provider-Facing APIs (notification preferences, not templates)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/notifications/preferences` | GET | Get current user's notification preferences (which events on which channels) | User token |
| `PUT /api/v1/notifications/preferences` | PUT | Update notification preferences | User token |
| `GET /api/v1/notifications` | GET | List in-app notifications for current user (paginated) | User token |
| `POST /api/v1/notifications/{id}/read` | POST | Mark notification as read | User token |

---

## 6. Full Database Schema

### 6.1 Tables

#### `notification_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| event_key | VARCHAR(50) | NOT NULL | e.g. `order.new`, `inventory.low_stock`, `finance.daily_summary` |
| channel | VARCHAR(20) | NOT NULL | in_app / push / sms / email / whatsapp |
| title | VARCHAR(255) | NOT NULL | English title with `{{variable}}` tokens |
| title_ar | VARCHAR(255) | NOT NULL | Arabic title with `{{variable}}` tokens |
| body | TEXT | NOT NULL | English body with `{{variable}}` tokens |
| body_ar | TEXT | NOT NULL | Arabic body with `{{variable}}` tokens |
| available_variables | JSONB | NOT NULL | Array of valid variable names: `["order_id","total","store_name","platform"]` |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE notification_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    available_variables JSONB NOT NULL DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (event_key, channel)
);
```

#### Cross-Referenced Provider-Side Tables

**`notification_preferences`** (provider-side, referenced for context)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| user_id | UUID | FK → users(id) ON DELETE CASCADE | |
| event_key | VARCHAR(50) | NOT NULL | Same keys as templates |
| channel | VARCHAR(20) | NOT NULL | |
| is_enabled | BOOLEAN | DEFAULT TRUE | |

```sql
CREATE TABLE notification_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    UNIQUE (user_id, event_key, channel)
);
```

**`notifications`** (provider-side, Laravel default + extended)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| type | VARCHAR(255) | NOT NULL | Laravel notification class name |
| notifiable_type | VARCHAR(255) | NOT NULL | `App\Models\User` |
| notifiable_id | UUID | NOT NULL | |
| data | JSONB | NOT NULL | Contains rendered title, body, event_key, metadata |
| read_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id UUID NOT NULL,
    data JSONB NOT NULL,
    read_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX notifications_notifiable ON notifications (notifiable_type, notifiable_id);
CREATE INDEX notifications_read_at ON notifications (read_at);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `notification_templates_event_channel` | (event_key, channel) | UNIQUE | One template per event per channel |
| `notification_templates_event` | event_key | B-TREE | Filter by event |
| `notification_templates_channel` | channel | B-TREE | Filter by channel |
| `notification_preferences_user_event_channel` | (user_id, event_key, channel) | UNIQUE | One preference per user per event per channel |

### 6.3 Relationships Diagram
```
notification_templates ──(event_key)──▶ notification_preferences (same event_key)
notification_templates ──renders──▶ notifications (data JSONB contains rendered output)

users ──1:N──▶ notification_preferences
users ──1:N──▶ notifications
```

---

## 7. Notification Event Catalog

### Order Events
| Event Key | Description | Variables |
|---|---|---|
| `order.new` | New order received | `order_id`, `total`, `store_name`, `branch_name` |
| `order.new_external` | New order from delivery platform | `order_id`, `total`, `platform`, `customer_name`, `item_count`, `store_name` |
| `order.status_changed` | Order status transition | `order_id`, `old_status`, `new_status`, `store_name` |
| `order.completed` | Order fulfilled | `order_id`, `total`, `store_name` |
| `order.cancelled` | Order cancelled | `order_id`, `total`, `reason`, `store_name` |
| `order.refund_requested` | Refund requested | `order_id`, `amount`, `store_name` |
| `order.refund_approved` | Refund processed | `order_id`, `amount`, `store_name` |
| `order.payment_failed` | Payment failed | `order_id`, `total`, `store_name` |

### Inventory Events
| Event Key | Description | Variables |
|---|---|---|
| `inventory.low_stock` | Product below reorder point | `product_name`, `current_qty`, `reorder_point`, `store_name` |
| `inventory.out_of_stock` | Product at zero | `product_name`, `store_name` |
| `inventory.expiry_warning` | Product expiring soon | `product_name`, `expiry_date`, `days_remaining`, `store_name` |
| `inventory.excess_stock` | Stock above max threshold | `product_name`, `current_qty`, `max_threshold`, `store_name` |
| `inventory.adjustment` | Manual stock change | `product_name`, `old_qty`, `new_qty`, `adjusted_by`, `store_name` |

### Finance Events
| Event Key | Description | Variables |
|---|---|---|
| `finance.daily_summary` | End-of-day summary | `date`, `total_sales`, `total_transactions`, `store_name` |
| `finance.shift_closed` | Shift end report | `cashier_name`, `total_sales`, `cash_expected`, `cash_actual`, `store_name` |
| `finance.cash_discrepancy` | Cash doesn't match | `cashier_name`, `expected`, `actual`, `difference`, `store_name` |
| `finance.large_transaction` | High-value transaction | `transaction_id`, `amount`, `cashier_name`, `store_name` |

### System Events
| Event Key | Description | Variables |
|---|---|---|
| `system.offline_mode` | POS went offline | `device_name`, `store_name` |
| `system.sync_failed` | Sync error | `platform`, `error_message`, `store_name` |
| `system.printer_error` | Printer offline | `printer_name`, `store_name` |
| `system.update_available` | New POS version | `version`, `release_notes_summary` |
| `system.license_expiring` | Subscription expiring | `plan_name`, `expiry_date`, `days_remaining` |

### Staff Events
| Event Key | Description | Variables |
|---|---|---|
| `staff.login` | Employee login | `user_name`, `device_name`, `store_name` |
| `staff.unauthorized_access` | Access denied | `user_name`, `attempted_action`, `store_name` |
| `staff.discount_applied` | Discount above threshold | `user_name`, `discount_pct`, `order_id`, `store_name` |
| `staff.void_transaction` | Transaction voided | `user_name`, `transaction_id`, `amount`, `store_name` |

---

## 8. Business Rules

1. **One template per (event_key, channel)** — enforced by unique constraint; admin edits the single template
2. **Platform-wide application** — templates apply to all providers; no per-provider template overrides
3. **Variable interpolation** — `{{variable}}` tokens are replaced at dispatch time; undefined variables render as empty string
4. **Provider controls preferences, not templates** — providers can turn events on/off per channel via `notification_preferences`; they cannot edit the message content
5. **Quiet hours respected** — if a provider user has quiet hours configured and the event is not critical, the notification is deferred until quiet hours end
6. **Critical events override quiet hours** — events like `system.offline_mode`, `order.payment_failed`, `staff.unauthorized_access` always deliver immediately
7. **Channel availability depends on System Configuration** — SMS only sends if SMS provider credentials are configured; WhatsApp only if WhatsApp Business API is active
8. **Preview uses sample data** — admin can preview rendered template with editable sample variable values before saving

---

## 9. SMS / Email Provider Fallback Behavior

The notification system supports multiple providers for SMS and email delivery with automatic fallback when the primary provider fails.

### 9.1 Supported Providers

| Channel | Primary Options | Fallback Options | Notes |
|---|---|---|---|
| **SMS** | Unifonic, Taqnyat, Msegat | Any other configured | Saudi-focused providers |
| **Email** | Mailgun, Amazon SES, SMTP | Any other configured | Standard email providers |
| **WhatsApp** | WhatsApp Business API | SMS (if configured) | Falls back to SMS if WhatsApp fails |
| **Push** | FCM (Android+Web), APNs (iOS) | No fallback | Platform-specific, no cross-fallback |

### 9.2 Fallback Configuration

Provider priority and fallback is configured in `System Configuration → Notification Channels`:

```
SMS Priority:
  1. Unifonic (primary)
  2. Taqnyat (fallback)
  3. Msegat (fallback)

Email Priority:
  1. Amazon SES (primary)
  2. Mailgun (fallback)
  3. SMTP (emergency fallback)
```

### 9.3 Fallback Logic

```
┌──────────────────────────────────────────────────────────┐
│              SMS Dispatch Flow                            │
├──────────────────────────────────────────────────────────┤
│ 1. Attempt primary provider (e.g., Unifonic)             │
│    ├─ Success → Log delivery, end                        │
│    └─ Failure (timeout/5xx/invalid response)             │
│         │                                                │
│ 2. Attempt fallback #1 (e.g., Taqnyat)                   │
│    ├─ Success → Log delivery with fallback flag, end     │
│    └─ Failure                                            │
│         │                                                │
│ 3. Attempt fallback #2 (e.g., Msegat)                    │
│    ├─ Success → Log delivery with fallback flag, end     │
│    └─ Failure                                            │
│         │                                                │
│ 4. All providers failed                                  │
│    → Log failure, increment failed_attempts count        │
│    → Fire NotificationDeliveryFailed event               │
│    → Admin notification if threshold reached             │
└──────────────────────────────────────────────────────────┘
```

### 9.4 Retry Behavior

| Scenario | Behavior |
|---|---|
| **Transient error** (timeout, 5xx) | Retry same provider once after 30s, then move to fallback |
| **Hard failure** (invalid credentials, account suspended) | Immediately move to fallback, disable provider |
| **Rate limit** | Wait for rate limit window, then retry same provider |
| **All providers exhausted** | Log failure, notify platform admin, queue for manual retry |

### 9.5 Provider Health Monitoring

The system tracks provider health and can automatically demote unhealthy providers:

| Metric | Threshold | Action |
|---|---|---|
| Failure rate | >20% over 100 messages | Demote to fallback position |
| Average latency | >10s response time | Demote to fallback position |
| Account error | Invalid credentials | Disable provider, alert admin |
| Balance warning | Low credits (OTP providers) | Alert admin, continue sending |

### 9.6 Database: Provider Status Tracking

#### `notification_provider_status`
| Column | Type | Notes |
|---|---|---|
| id | UUID | PK |
| provider | VARCHAR(50) | unifonic, taqnyat, msegat, mailgun, ses, smtp |
| channel | VARCHAR(20) | sms / email |
| is_enabled | BOOLEAN | Admin toggle |
| priority | INT | 1 = primary, 2+ = fallback |
| is_healthy | BOOLEAN | Auto-set based on recent delivery stats |
| last_success_at | TIMESTAMP | |
| last_failure_at | TIMESTAMP | |
| failure_count_24h | INT | Rolling 24h failure count |
| success_count_24h | INT | Rolling 24h success count |
| avg_latency_ms | INT | Rolling average response time |
| disabled_reason | TEXT | NULL or reason for disabling |
| updated_at | TIMESTAMP | |

```sql
CREATE TABLE notification_provider_status (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 1,
    is_healthy BOOLEAN DEFAULT TRUE,
    last_success_at TIMESTAMP,
    last_failure_at TIMESTAMP,
    failure_count_24h INT DEFAULT 0,
    success_count_24h INT DEFAULT 0,
    avg_latency_ms INT,
    disabled_reason TEXT,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (provider, channel)
);
```

### 9.7 Notification Delivery Logs

All delivery attempts are logged for debugging and analytics:

#### `notification_delivery_logs`
| Column | Type | Notes |
|---|---|---|
| id | UUID | PK |
| notification_id | UUID | FK → notifications |
| channel | VARCHAR(20) | sms / email / push / whatsapp |
| provider | VARCHAR(50) | Which provider was used |
| recipient | VARCHAR(255) | Phone/email/device_token |
| status | VARCHAR(20) | pending / sent / delivered / failed |
| provider_message_id | VARCHAR(100) | ID returned by provider |
| error_message | TEXT | NULL or error details |
| latency_ms | INT | Time to send |
| is_fallback | BOOLEAN | TRUE if fallback provider was used |
| attempted_providers | JSONB | ["unifonic", "taqnyat"] — providers attempted |
| created_at | TIMESTAMP | |

This ensures complete visibility into notification delivery including fallback behavior.
