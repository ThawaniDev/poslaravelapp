# Support Ticket System — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Tickets, Canned Responses, Knowledge Base & SLA Tracking  
> **Tech Stack:** Laravel 11 + Filament v3 · Livewire 3 · Spatie Permission · Redis  

---

## 1. Feature Overview

The Support Ticket System provides a full helpdesk within the admin panel. Providers submit tickets; Thawani support agents view, manage, assign, and resolve them. The system includes internal notes, SLA tracking, canned responses, a knowledge base, and support analytics.

### What This Feature Does
- View and manage all provider-submitted support tickets
- Assign tickets to support agents
- Internal notes (not visible to provider)
- Status workflow: open → in_progress → resolved → closed
- Priority levels: low, medium, high, critical
- Full ticket history per provider
- SLA tracking per ticket (first response, resolution)
- Canned responses library with shortcuts (e.g. `/greeting`)
- Knowledge base / help articles management
- Live chat integration for real-time support
- Support analytics: ticket volume, response times, resolution rates, agent performance

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Provider Management** | Store detail → support history tab shows tickets for that store |
| **Analytics & Reporting** | Support analytics dashboard (volume, SLA, resolution, agent performance) |
| **Notification Templates** | Ticket status change notifications use templates |
| **Platform Announcements** | Maintenance announcements may reduce ticket volume |
| **Content & Onboarding** | Knowledge base articles shared with onboarding help |

### Features to Review After Changing This Feature
1. **Notification templates** — if ticket status values change, notification event keys must update
2. **Provider portal** — provider-side ticket submission and viewing must match admin-side status values
3. **Analytics** — support metrics depend on `created_at`, `first_response_at`, `resolved_at` timestamps
4. **Impersonate** — remote access action dispatches from ticket detail to Provider Management impersonate flow

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources: SupportTicket, CannedResponse, KnowledgeBaseArticle |
| **spatie/laravel-permission** | Access: `tickets.view`, `tickets.respond`, `tickets.assign`, `kb.manage` |
| **spatie/laravel-activitylog** | Audit log for ticket assignments and status changes |
| **lab404/laravel-impersonate** | "Investigate" action from ticket → impersonate store |
| **maatwebsite/laravel-excel** | Export ticket data |

### 3.2 Technologies
- **Laravel 11** — Eloquent, Events, Notifications, Policies
- **Filament v3** — Custom ticket detail page with conversation view, RelationManagers for messages
- **Livewire 3** — Real-time conversation updates
- **PostgreSQL** — relational data
- **Redis** — cache ticket counts for navigation badge (open ticket count per agent)
- **DigitalOcean Spaces** — attachment file storage (S3-compatible)

---

## 4. Pages

### 4.1 Ticket Inbox
| Field | Detail |
|---|---|
| **Route** | `/admin/support/tickets` |
| **Filament Resource** | `SupportTicketResource` |
| **Table Columns** | Ticket Number, Subject, Store Name, Priority badge (colour-coded), Status badge, Assigned To, SLA Deadline, Created At |
| **Filters** | Status (open / in_progress / resolved / closed), Priority, Assigned To (agent select), Category, Date range |
| **Search** | By ticket number, subject, store name |
| **Bulk Actions** | Assign to Agent, Change Priority, Close |
| **Navigation Badge** | Open ticket count (cached in Redis, refreshed on status change) |
| **Access** | `tickets.view` |

### 4.2 Ticket Detail
| Field | Detail |
|---|---|
| **Route** | `/admin/support/tickets/{id}` |
| **Sections** | |
| — Header | Ticket #, Subject, Status badge, Priority badge, SLA countdown, Store name (link to store detail), Assigned Agent |
| — Conversation Thread | Chronological messages (provider + admin), internal notes highlighted in yellow, attachments inline |
| — Reply Box | Rich-text editor, attachment upload, "Internal Note" toggle, Canned Response shortcut (`/` trigger), Send button |
| — Sidebar | Store info summary, Subscription plan, Recent tickets for this store, Quick actions: Assign, Change Priority, Change Status, Impersonate Store |
| **Actions** | Reply, Add Internal Note, Assign, Escalate (set critical priority), Resolve, Close, Impersonate Store |
| **Access** | `tickets.view` (respond requires `tickets.respond`) |

### 4.3 Ticket Create (on behalf)
| Field | Detail |
|---|---|
| **Route** | `/admin/support/tickets/create` |
| **Purpose** | Admin creates a ticket on behalf of a provider (e.g. from phone call) |
| **Form** | Store select (searchable), Category, Priority, Subject, Description, Assign To |
| **Access** | `tickets.respond` |

### 4.4 Canned Responses List
| Field | Detail |
|---|---|
| **Route** | `/admin/support/canned-responses` |
| **Filament Resource** | `CannedResponseResource` |
| **Table Columns** | Title, Shortcut, Category, Is Active |
| **Row Actions** | Edit, Deactivate |
| **Access** | `kb.manage` |

### 4.5 Canned Response Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/support/canned-responses/create` · `…/{id}/edit` |
| **Form** | Title, Shortcut (e.g. `/greeting`, `/billing_help`), Body (EN), Body (AR), Category, Is Active |
| **Access** | `kb.manage` |

### 4.6 Knowledge Base Articles
| Field | Detail |
|---|---|
| **Route** | `/admin/support/knowledge-base` |
| **Filament Resource** | `KnowledgeBaseArticleResource` |
| **Table Columns** | Title, Category, Is Published badge, Sort Order, Updated At |
| **Row Actions** | Edit, Publish/Unpublish, Delete |
| **Access** | `kb.manage` |

### 4.7 Article Create / Edit
| Field | Detail |
|---|---|
| **Route** | `/admin/support/knowledge-base/create` · `…/{id}/edit` |
| **Form** | Title (EN), Title (AR), Slug (auto), Body (EN, rich-text), Body (AR, rich-text), Category, Is Published, Sort Order |
| **Access** | `kb.manage` |

### 4.8 Support Analytics
| Field | Detail |
|---|---|
| **Route** | `/admin/support/analytics` |
| **Widgets** | Open tickets count, Avg first response time, Avg resolution time, SLA compliance %, Ticket volume trend (daily line chart), Category breakdown pie, Agent performance table (tickets handled, avg resolution, satisfaction) |
| **Filters** | Date range, Agent, Category |
| **Access** | `analytics.view` |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD for `SupportTicketResource`, `CannedResponseResource`, `KnowledgeBaseArticleResource`.

### 5.2 Provider-Facing APIs
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/support/tickets` | GET | List tickets for current store (paginated) | Store API token |
| `GET /api/v1/support/tickets/{id}` | GET | Get ticket detail with messages (excluding internal notes) | Store API token |
| `POST /api/v1/support/tickets` | POST | Create new ticket (subject, description, category, priority) | Store API token |
| `POST /api/v1/support/tickets/{id}/reply` | POST | Provider replies to ticket | Store API token |
| `GET /api/v1/support/articles` | GET | List published knowledge base articles | Public or Store API token |
| `GET /api/v1/support/articles/{slug}` | GET | Get single article | Public or Store API token |

---

## 6. Full Database Schema

### 6.1 Tables

#### `support_tickets`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| ticket_number | VARCHAR(20) | NOT NULL, UNIQUE | Auto-generated: TKT-2026-0001 |
| organization_id | UUID | FK → organizations(id) | |
| store_id | UUID | FK → stores(id), NULLABLE | Specific branch if applicable |
| user_id | UUID | FK → users(id), NULLABLE | Provider user who submitted |
| assigned_to | UUID | FK → admin_users(id), NULLABLE | Support agent |
| category | VARCHAR(50) | NOT NULL | billing / technical / zatca / feature_request / general |
| priority | VARCHAR(10) | NOT NULL DEFAULT 'medium' | low / medium / high / critical |
| status | VARCHAR(20) | NOT NULL DEFAULT 'open' | open / in_progress / resolved / closed |
| subject | VARCHAR(255) | NOT NULL | |
| description | TEXT | NOT NULL | Initial message |
| sla_deadline_at | TIMESTAMP | NULLABLE | Auto-calculated based on priority |
| first_response_at | TIMESTAMP | NULLABLE | Set when admin first replies |
| resolved_at | TIMESTAMP | NULLABLE | |
| closed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE support_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    organization_id UUID NOT NULL REFERENCES organizations(id),
    store_id UUID REFERENCES stores(id),
    user_id UUID REFERENCES users(id),
    assigned_to UUID REFERENCES admin_users(id),
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(10) NOT NULL DEFAULT 'medium',
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    sla_deadline_at TIMESTAMP,
    first_response_at TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `support_ticket_messages`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| support_ticket_id | UUID | FK → support_tickets(id) ON DELETE CASCADE | |
| sender_type | VARCHAR(10) | NOT NULL | provider / admin |
| sender_id | UUID | NOT NULL | user_id or admin_user_id |
| message_text | TEXT | NOT NULL | |
| attachments | JSONB | NULLABLE | `[{"url":"…","filename":"…","size":1234}]` |
| is_internal_note | BOOLEAN | DEFAULT FALSE | Not shown to provider |
| sent_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE support_ticket_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    support_ticket_id UUID NOT NULL REFERENCES support_tickets(id) ON DELETE CASCADE,
    sender_type VARCHAR(10) NOT NULL,
    sender_id UUID NOT NULL,
    message_text TEXT NOT NULL,
    attachments JSONB,
    is_internal_note BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT NOW()
);
```

#### `canned_responses`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| title | VARCHAR(255) | NOT NULL | |
| shortcut | VARCHAR(50) | UNIQUE | e.g. `/greeting`, `/billing_help` |
| body | TEXT | NOT NULL | English |
| body_ar | TEXT | NOT NULL | Arabic |
| category | VARCHAR(50) | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_by | UUID | FK → admin_users(id) | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE canned_responses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    shortcut VARCHAR(50) UNIQUE,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_by UUID REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `knowledge_base_articles`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| title | VARCHAR(255) | NOT NULL | |
| title_ar | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(100) | NOT NULL, UNIQUE | URL-friendly |
| body | TEXT | NOT NULL | Rich-text HTML |
| body_ar | TEXT | NOT NULL | |
| category | VARCHAR(50) | NULLABLE | |
| is_published | BOOLEAN | DEFAULT FALSE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE knowledge_base_articles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    category VARCHAR(50),
    is_published BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `support_tickets_number` | ticket_number | UNIQUE | Fast lookup by ticket # |
| `support_tickets_status_priority` | (status, priority) | B-TREE | Inbox filtering |
| `support_tickets_organization` | organization_id | B-TREE | Per-provider ticket list |
| `support_tickets_assigned` | assigned_to | B-TREE | Agent workload |
| `support_tickets_created` | created_at | B-TREE | Time-range queries |
| `support_ticket_messages_ticket` | support_ticket_id | B-TREE | Thread loading |
| `support_ticket_messages_sent` | sent_at | B-TREE | Chronological order |
| `canned_responses_shortcut` | shortcut | UNIQUE | Shortcut lookup |
| `kb_articles_slug` | slug | UNIQUE | URL lookup |
| `kb_articles_published_category` | (is_published, category) | B-TREE | Published articles by category |

### 6.3 Relationships Diagram
```
organizations ──1:N──▶ support_tickets
stores ──1:N──▶ support_tickets
users (provider) ──1:N──▶ support_tickets (submitter)
admin_users ──1:N──▶ support_tickets (assigned_to)

support_tickets ──1:N──▶ support_ticket_messages
admin_users ──1:N──▶ canned_responses (created_by)
```

---

## 7. SLA Configuration

| Priority | First Response Target | Resolution Target |
|---|---|---|
| **Critical** | 30 minutes | 4 hours |
| **High** | 2 hours | 24 hours |
| **Medium** | 8 hours | 48 hours |
| **Low** | 24 hours | 5 business days |

SLA deadline is auto-calculated from `created_at` + priority target. Breached SLAs are highlighted in red on the inbox.

---

## 8. Business Rules

1. **Auto-assign ticket number** — sequential format `TKT-{year}-{sequence}` generated on creation
2. **SLA auto-calculation** — `sla_deadline_at` set based on priority; breached tickets highlighted in inbox
3. **First response tracking** — `first_response_at` set when the first non-internal-note admin reply is sent
4. **Internal notes hidden from provider** — messages with `is_internal_note = true` are never returned by provider-facing APIs
5. **Canned response insertion** — typing `/shortcut` in the reply box auto-expands to the canned response body
6. **Status transitions** — only valid transitions: open → in_progress, in_progress → resolved, resolved → closed, any → closed (force close)
7. **Assignment triggers notification** — admin who is assigned receives in-app + email notification
8. **Provider notification on reply** — when admin replies (non-internal), provider receives push + email notification via notification templates
9. **Impersonate from ticket** — "Investigate" button on ticket detail dispatches to Provider Management impersonate flow, pre-scoped to the ticket's store
10. **Knowledge base articles** — published articles are accessible via public API (no auth) and in-app help within the POS
