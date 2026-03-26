# Nice-to-Have Features — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Laravel Backend)  
> **Module:** Customer-Facing Display, Digital Signage, Appointment Booking, Gift Registry, Wishlist, Loyalty Gamification  
> **Tech Stack:** Flutter 3.x Desktop · Flutter Web · Laravel 11 · WebSocket  

---

## 1. Feature Overview

Nice-to-Have Features are secondary enhancements that increase the value proposition of the POS system but are not required for core operations. These features are implemented as modular add-ons that can be enabled/disabled per store and are typically gated behind higher subscription tiers.

### What This Feature Includes

#### 1.1 Customer-Facing Display (CFD)
A secondary screen (or window) facing the customer that shows the current cart, item-by-item breakdown, promotional messages, and total. Can be used on a dedicated monitor or a tablet connected to the POS machine.

#### 1.2 Digital Signage
Transform idle POS screens or dedicated screens into digital signage displaying product promotions, menu boards (restaurants/bakeries), or branded content. Runs as a Flutter web app or a dedicated display mode in the desktop app.

#### 1.3 Appointment Booking
Allows service-based businesses (salons, clinics, repair shops) to manage appointments — scheduling, calendar view, reminders, walk-in queuing.

#### 1.4 Gift Registry
Customers can create wishlists/gift registries (e.g., wedding registry, baby shower) that others can purchase from. The store tracks fulfillment.

#### 1.5 Wishlist
Customers can save products to a wishlist for later purchase. Shared with the Mobile Companion App if the store has customer-facing features.

#### 1.6 Loyalty Gamification
Extends the basic loyalty system with gamification elements: badges, challenges (e.g., "Buy 5 coffees, get 1 free"), progress bars, tier milestones, seasonal campaigns.

---

## 2. Affected Features & Dependencies

### Features That Nice-to-Have Features Depend On
| Feature | Dependency |
|---|---|
| **POS Terminal** | Cart data for CFD; transaction data for loyalty gamification |
| **Customer Management** | Customer profiles for wishlists, gift registries, loyalty |
| **Product Catalog** | Products for signage, wishlists, gift registries |
| **Promotions & Coupons** | Promotional content for signage and CFD |
| **Subscription & Billing** | Feature gating by subscription tier |
| **Hardware Support** | Secondary display connection for CFD |
| **Language & Localization** | Bilingual content on CFD and signage |

### Features That Depend on Nice-to-Have Features
| Feature | Dependency |
|---|---|
| **Mobile Companion App** | Customer wishlist view, appointment booking |
| **Reports & Analytics** | Appointment utilization reports, gamification engagement metrics |

### Features to Review After Changing This Feature
1. **POS Terminal** — CFD integration with cart updates
2. **Customer Management** — wishlist and registry linked to customer profiles
3. **Hardware Support** — secondary display detection for CFD

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **window_manager** | Manage secondary windows for CFD on multi-monitor setups |
| **screen_retriever** | Detect connected monitors for CFD placement |
| **table_calendar** | Calendar widget for appointment booking |
| **flutter_animate** | Smooth animations for CFD transitions and signage |
| **carousel_slider** | Rotating promotional content on CFD and signage |
| **confetti_widget** | Gamification celebration effects (badge earned, tier upgrade) |
| **lottie** | Animated reward illustrations for gamification |
| **flutter_local_notifications** | Appointment reminders |
| **web_socket_channel** | Real-time cart updates to CFD |

### 3.2 Technologies
- **Multi-window Flutter** — customer-facing display runs as a separate Flutter window on a secondary monitor
- **Flutter Web** — digital signage can be a Flutter web app served by the POS or a remote URL
- **WebSocket** — real-time push of cart changes and signage content updates
- **iCal (RFC 5545)** — appointment export format for calendar integration
- **Deep linking** — customer wishlist and gift registry accessible via shareable links

---

## 4. Screens

### 4.1 Customer-Facing Display (CFD) Window
| Field | Detail |
|---|---|
| **Route** | Separate Flutter window (not a route in main app) |
| **Purpose** | Show customers their order in real time |
| **Layout** | Left panel: item list updated live as cashier scans. Right panel: running total, promotional banner rotation. Bottom: store logo, "Thank you" message after sale completes. Idle mode: promotional slideshow |
| **Actions** | Automatic — no user interaction on CFD |
| **Access** | Public-facing; no authentication |

### 4.2 CFD Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/cfd` |
| **Purpose** | Configure customer-facing display |
| **Layout** | Toggle CFD on/off, Select target monitor, Idle content: slideshow images, promotional text, rotation interval. Theme: colors, font size, logo |
| **Actions** | Save, Preview, Upload idle content |
| **Access** | Owner, Manager |

### 4.3 Digital Signage Manager
| Field | Detail |
|---|---|
| **Route** | `/signage` |
| **Purpose** | Manage digital signage playlists |
| **Layout** | Playlist list: Name, Screens assigned, Duration, Status. Playlist editor: drag-and-drop slides (image, video, product grid, menu board, text). Schedule: time-of-day playlists (e.g., breakfast menu vs lunch menu). Preview panel |
| **Actions** | Create playlist, Edit, Assign to screen, Schedule, Preview |
| **Access** | Owner, Manager |

### 4.4 Signage Display Screen
| Field | Detail |
|---|---|
| **Route** | `/signage/display/{playlistId}` (Flutter Web or desktop fullscreen) |
| **Purpose** | Render signage content on a display |
| **Layout** | Full-screen slideshow with transitions, product grids with live prices, or restaurant menu board layout |

### 4.5 Appointment Calendar
| Field | Detail |
|---|---|
| **Route** | `/appointments` |
| **Purpose** | Manage service appointments |
| **Layout** | Calendar views: Day, Week, Month. Time slot grid. Color-coded by service type. Sidebar: upcoming appointments, walk-in queue |
| **Actions** | Create appointment, Drag to reschedule, Mark completed/no-show, Send reminder (SMS/push), Export to iCal |
| **Access** | Owner, Manager, Staff (own calendar) |

### 4.6 Appointment Booking Form
| Field | Detail |
|---|---|
| **Route** | `/appointments/new` |
| **Purpose** | Create a new appointment |
| **Layout** | Customer search/selection, Service selection (from products tagged as services), Staff selection, Date/time picker (available slots only), Notes, Duration (auto from service type) |

### 4.7 Gift Registry Manager
| Field | Detail |
|---|---|
| **Route** | `/gift-registries` |
| **Purpose** | Manage customer gift registries |
| **Layout** | Registry list: Customer name, Event (wedding, birthday), Date, Products count, Fulfillment %. Detail: product list with fulfillment status (purchased/pending). Shareable link |
| **Actions** | Create registry, Add/remove products, Mark purchased, Share link |
| **Access** | Owner, Manager, Staff |

### 4.8 Customer Wishlist View (Staff side)
| Field | Detail |
|---|---|
| **Route** | `/customers/{id}/wishlist` |
| **Purpose** | View a customer's saved wishlist |
| **Layout** | Product list with images, prices, availability. Add-to-cart button for quick fulfillment |
| **Actions** | Add to current cart, Remove item, Notify customer of price drop |
| **Access** | Owner, Manager, Staff |

### 4.9 Loyalty Gamification Dashboard
| Field | Detail |
|---|---|
| **Route** | `/loyalty/gamification` |
| **Purpose** | Configure and monitor loyalty gamification |
| **Layout** | Active challenges list. Badge definitions. Tier configuration (Bronze/Silver/Gold/Platinum). Campaign builder: seasonal campaigns with timeframe. Engagement metrics: participation rate, redemption rate |
| **Actions** | Create challenge, Create badge, Edit tier thresholds, Start/stop campaign |
| **Access** | Owner |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| **Customer-Facing Display** | | | |
| `GET /api/v2/cfd/config` | GET | Get CFD configuration (theme, idle content) | Bearer token |
| `POST /api/v2/cfd/config` | POST | Save CFD settings | Bearer token, Owner/Manager |
| **Digital Signage** | | | |
| `GET /api/v2/signage/playlists` | GET | List signage playlists | Bearer token |
| `POST /api/v2/signage/playlists` | POST | Create playlist | Bearer token, Owner/Manager |
| `PUT /api/v2/signage/playlists/{id}` | PUT | Update playlist | Bearer token, Owner/Manager |
| `GET /api/v2/signage/playlists/{id}/render` | GET | Get rendered playlist data for display | API key (no login) |
| **Appointments** | | | |
| `GET /api/v2/appointments` | GET | List appointments with filters | Bearer token |
| `POST /api/v2/appointments` | POST | Create appointment | Bearer token |
| `PUT /api/v2/appointments/{id}` | PUT | Update / reschedule appointment | Bearer token |
| `DELETE /api/v2/appointments/{id}` | DELETE | Cancel appointment | Bearer token |
| `GET /api/v2/appointments/available-slots` | GET | Get available time slots for date + service | Bearer token |
| **Gift Registry** | | | |
| `GET /api/v2/gift-registries` | GET | List registries | Bearer token |
| `POST /api/v2/gift-registries` | POST | Create registry | Bearer token |
| `PUT /api/v2/gift-registries/{id}` | PUT | Update registry | Bearer token |
| `GET /api/v2/gift-registries/{shareCode}` | GET | Public registry view (via share link) | Public |
| `POST /api/v2/gift-registries/{id}/purchase` | POST | Mark item as purchased | Bearer token |
| **Wishlist** | | | |
| `GET /api/v2/customers/{id}/wishlist` | GET | Get customer's wishlist | Bearer token |
| `POST /api/v2/customers/{id}/wishlist` | POST | Add product to wishlist | Bearer token |
| `DELETE /api/v2/customers/{id}/wishlist/{productId}` | DELETE | Remove from wishlist | Bearer token |
| **Loyalty Gamification** | | | |
| `GET /api/v2/loyalty/challenges` | GET | List active challenges | Bearer token |
| `POST /api/v2/loyalty/challenges` | POST | Create challenge | Bearer token, Owner |
| `PUT /api/v2/loyalty/challenges/{id}` | PUT | Update challenge | Bearer token, Owner |
| `GET /api/v2/loyalty/badges` | GET | List badge definitions | Bearer token |
| `POST /api/v2/loyalty/badges` | POST | Create badge | Bearer token, Owner |
| `GET /api/v2/loyalty/tiers` | GET | Get tier configuration | Bearer token |
| `PUT /api/v2/loyalty/tiers` | PUT | Update tier thresholds | Bearer token, Owner |
| `GET /api/v2/customers/{id}/gamification` | GET | Customer's badges, challenges, tier status | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `CfdWindowService` | Manage secondary window lifecycle, push cart updates, idle slideshow |
| `CfdCartBroadcastService` | Broadcast real-time cart changes to the CFD window via local event bus |
| `SignagePlaylistService` | Fetch and render signage playlists, manage transitions and scheduling |
| `AppointmentService` | CRUD operations on appointments, conflict detection, slot availability |
| `AppointmentReminderService` | Schedule and send appointment reminders (push / SMS) |
| `GiftRegistryService` | Registry CRUD, share link generation, fulfillment tracking |
| `WishlistService` | Manage customer wishlists, add-to-cart integration |
| `LoyaltyGamificationService` | Challenge evaluation, badge award logic, tier calculation |
| `LoyaltyProgressTracker` | Real-time progress bars and milestone notifications |

---

## 6. Full Database Schema

### 6.1 Tables

#### `cfd_configurations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL, UNIQUE | One CFD config per store |
| is_enabled | BOOLEAN | DEFAULT false | |
| target_monitor | INTEGER | DEFAULT 1 | Monitor index (0 = primary, 1 = secondary) |
| theme_config | JSONB | DEFAULT '{}' | Colors, font sizes, logo path |
| idle_content | JSONB | DEFAULT '[]' | Array of {type, url, duration_seconds} |
| idle_rotation_seconds | INTEGER | DEFAULT 10 | |
| updated_at | TIMESTAMP | | |

```sql
CREATE TABLE cfd_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL UNIQUE REFERENCES stores(id),
    is_enabled BOOLEAN DEFAULT false,
    target_monitor INTEGER DEFAULT 1,
    theme_config JSONB DEFAULT '{}',
    idle_content JSONB DEFAULT '[]',
    idle_rotation_seconds INTEGER DEFAULT 10,
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `signage_playlists`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| name | VARCHAR(100) | NOT NULL | |
| slides | JSONB | NOT NULL | Array of slide objects |
| schedule | JSONB | NULLABLE | Time-of-day scheduling rules |
| is_active | BOOLEAN | DEFAULT true | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | | |

```sql
CREATE TABLE signage_playlists (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(100) NOT NULL,
    slides JSONB NOT NULL,
    schedule JSONB,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `appointments`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NULLABLE | Walk-in may not have customer record |
| staff_id | UUID | FK → staff_users(id), NULLABLE | Assigned staff |
| service_product_id | UUID | FK → products(id), NOT NULL | Service product being booked |
| appointment_date | DATE | NOT NULL | |
| start_time | TIME | NOT NULL | |
| end_time | TIME | NOT NULL | |
| status | VARCHAR(20) | DEFAULT 'scheduled' | scheduled, confirmed, in_progress, completed, cancelled, no_show |
| notes | TEXT | NULLABLE | |
| reminder_sent | BOOLEAN | DEFAULT false | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | | |

```sql
CREATE TABLE appointments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID REFERENCES customers(id),
    staff_id UUID REFERENCES staff_users(id),
    service_product_id UUID NOT NULL REFERENCES products(id),
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(20) DEFAULT 'scheduled',
    notes TEXT,
    reminder_sent BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `gift_registries`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NOT NULL | Registry owner |
| name | VARCHAR(100) | NOT NULL | e.g., "Sarah's Wedding" |
| event_type | VARCHAR(30) | NOT NULL | wedding, birthday, baby_shower, other |
| event_date | DATE | NULLABLE | |
| share_code | VARCHAR(20) | NOT NULL, UNIQUE | Short code for shareable link |
| is_active | BOOLEAN | DEFAULT true | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE gift_registries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    name VARCHAR(100) NOT NULL,
    event_type VARCHAR(30) NOT NULL,
    event_date DATE,
    share_code VARCHAR(20) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `gift_registry_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| registry_id | UUID | FK → gift_registries(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| quantity_desired | INTEGER | DEFAULT 1 | |
| quantity_purchased | INTEGER | DEFAULT 0 | |
| purchased_by_name | VARCHAR(100) | NULLABLE | Name of the gifter |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE gift_registry_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    registry_id UUID NOT NULL REFERENCES gift_registries(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id),
    quantity_desired INTEGER DEFAULT 1,
    quantity_purchased INTEGER DEFAULT 0,
    purchased_by_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `wishlists`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| customer_id | UUID | FK → customers(id), NOT NULL | |
| product_id | UUID | FK → products(id), NOT NULL | |
| added_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE wishlists (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    customer_id UUID NOT NULL REFERENCES customers(id),
    product_id UUID NOT NULL REFERENCES products(id),
    added_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, product_id)
);
```

#### `loyalty_challenges`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| name_ar | VARCHAR(100) | NOT NULL | |
| name_en | VARCHAR(100) | NOT NULL | |
| description_ar | TEXT | NULLABLE | |
| description_en | TEXT | NULLABLE | |
| challenge_type | VARCHAR(30) | NOT NULL | purchase_count, spend_amount, category_explore, streak |
| target_value | DECIMAL(12,2) | NOT NULL | e.g., buy 5, spend 100 |
| reward_type | VARCHAR(20) | NOT NULL | points, discount_coupon, free_item, badge |
| reward_value | DECIMAL(12,2) | NULLABLE | |
| reward_badge_id | UUID | FK → loyalty_badges(id), NULLABLE | |
| start_date | DATE | NOT NULL | |
| end_date | DATE | NULLABLE | NULL = ongoing |
| is_active | BOOLEAN | DEFAULT true | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE loyalty_challenges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    challenge_type VARCHAR(30) NOT NULL,
    target_value DECIMAL(12,2) NOT NULL,
    reward_type VARCHAR(20) NOT NULL,
    reward_value DECIMAL(12,2),
    reward_badge_id UUID,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `loyalty_badges`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| name_ar | VARCHAR(50) | NOT NULL | |
| name_en | VARCHAR(50) | NOT NULL | |
| icon_url | VARCHAR(500) | NULLABLE | Badge icon |
| description_ar | TEXT | NULLABLE | |
| description_en | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE loyalty_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name_ar VARCHAR(50) NOT NULL,
    name_en VARCHAR(50) NOT NULL,
    icon_url VARCHAR(500),
    description_ar TEXT,
    description_en TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Add FK after both tables exist
ALTER TABLE loyalty_challenges
    ADD CONSTRAINT fk_challenge_badge
    FOREIGN KEY (reward_badge_id) REFERENCES loyalty_badges(id);
```

#### `loyalty_tiers`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| tier_name_ar | VARCHAR(50) | NOT NULL | e.g., "برونزي" |
| tier_name_en | VARCHAR(50) | NOT NULL | e.g., "Bronze" |
| tier_order | INTEGER | NOT NULL | 1, 2, 3, 4 |
| min_points | INTEGER | NOT NULL | Min points to qualify |
| benefits | JSONB | DEFAULT '{}' | discount_percent, free_delivery, etc. |
| icon_url | VARCHAR(500) | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE loyalty_tiers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    tier_name_ar VARCHAR(50) NOT NULL,
    tier_name_en VARCHAR(50) NOT NULL,
    tier_order INTEGER NOT NULL,
    min_points INTEGER NOT NULL,
    benefits JSONB DEFAULT '{}',
    icon_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, tier_order)
);
```

#### `customer_challenge_progress`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| customer_id | UUID | FK → customers(id), NOT NULL | |
| challenge_id | UUID | FK → loyalty_challenges(id), NOT NULL | |
| current_value | DECIMAL(12,2) | DEFAULT 0 | Progress toward target |
| is_completed | BOOLEAN | DEFAULT false | |
| completed_at | TIMESTAMP | NULLABLE | |
| reward_claimed | BOOLEAN | DEFAULT false | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | | |

```sql
CREATE TABLE customer_challenge_progress (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    challenge_id UUID NOT NULL REFERENCES loyalty_challenges(id),
    current_value DECIMAL(12,2) DEFAULT 0,
    is_completed BOOLEAN DEFAULT false,
    completed_at TIMESTAMP,
    reward_claimed BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, challenge_id)
);
```

#### `customer_badges`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| customer_id | UUID | FK → customers(id), NOT NULL | |
| badge_id | UUID | FK → loyalty_badges(id), NOT NULL | |
| earned_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE customer_badges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    badge_id UUID NOT NULL REFERENCES loyalty_badges(id),
    earned_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, badge_id)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `appointments_store_date` | (store_id, appointment_date) | B-TREE | Calendar view |
| `appointments_staff_date` | (staff_id, appointment_date) | B-TREE | Staff calendar view |
| `appointments_customer` | customer_id | B-TREE | Customer appointment history |
| `appointments_status` | (store_id, status) | B-TREE | Status filtering |
| `gift_registries_share` | share_code | B-TREE UNIQUE | Public share link lookup |
| `gift_registries_customer` | customer_id | B-TREE | Customer's registries |
| `gift_registry_items_registry` | registry_id | B-TREE | Items in a registry |
| `wishlists_customer` | (customer_id, product_id) | B-TREE UNIQUE | Customer wishlist (enforced in SQL) |
| `wishlists_store_product` | (store_id, product_id) | B-TREE | "N customers want this product" metrics |
| `loyalty_challenges_store` | (store_id, is_active) | B-TREE | Active challenges |
| `customer_challenge_progress_cust` | (customer_id, is_completed) | B-TREE | Customer's active challenges |
| `customer_badges_customer` | customer_id | B-TREE | Customer's badge collection |
| `signage_playlists_store` | (store_id, is_active) | B-TREE | Active playlists |

### 6.3 Relationships Diagram
```
stores ──1:1──▶ cfd_configurations
stores ──1:N──▶ signage_playlists
stores ──1:N──▶ appointments ◀──N:1── customers
                              ◀──N:1── staff_users
                              ◀──N:1── products
stores ──1:N──▶ gift_registries ──1:N──▶ gift_registry_items ◀──N:1── products
                ◀──N:1── customers
stores ──1:N──▶ wishlists ◀──N:1── customers
                          ◀──N:1── products
stores ──1:N──▶ loyalty_challenges ◀──N:N──▶ customer_challenge_progress ◀──N:1── customers
stores ──1:N──▶ loyalty_badges ◀──N:N──▶ customer_badges ◀──N:1── customers
stores ──1:N──▶ loyalty_tiers
```

---

## 7. Business Rules

1. **Feature gating** — each nice-to-have feature is independently toggleable; a store can enable CFD without signage, or appointments without gamification; gated by subscription tier (Basic: no nice-to-have; Professional: CFD + signage; Enterprise: all)
2. **CFD auto-detect** — when a secondary monitor is detected, the system prompts to enable CFD; if the monitor disconnects, CFD gracefully falls back and does not crash the main POS
3. **CFD real-time sync** — cart updates appear on CFD within 100ms of the cashier's action; uses local IPC (inter-process communication) not network
4. **Appointment conflict detection** — the system prevents double-booking the same staff member at the same time; overlapping appointments are rejected at both UI and API level
5. **Appointment reminders** — 24 hours and 1 hour before the appointment, a reminder is sent via push notification (if customer has the app) or SMS
6. **Gift registry sharing** — each registry has a unique short URL (e.g., `store.com/g/ABC123`); the public page shows products and fulfillment status without requiring login
7. **Wishlist limit** — a customer can have max 50 items in their wishlist per store; oldest items are prompted for removal when the limit is reached
8. **Challenge auto-evaluation** — after each completed purchase, the system checks all active challenges for the customer and updates progress; if target is met, the reward is auto-applied
9. **Tier recalculation** — loyalty tiers are recalculated monthly based on the customer's rolling 12-month point balance; tier downgrade is deferred by 1 month (grace period)
10. **Signage scheduling** — playlists can be scheduled by time of day (e.g., breakfast menu 6AM–11AM, lunch menu 11AM–4PM); if no schedule matches, the default playlist is shown
11. **Badge uniqueness** — a customer can earn each badge only once; duplicate awards are silently ignored
12. **Offline support** — CFD works fully offline (local IPC); appointment booking works offline with sync; wishlists and gamification require synced data
