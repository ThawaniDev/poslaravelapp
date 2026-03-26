# Infrastructure & Operations — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Queue Health, Background Jobs, Cache Management, Storage, Database Backups, Server Metrics  
> **Tech Stack:** Laravel 11 + Filament v3 · Laravel Horizon · Redis · DigitalOcean Spaces · PostgreSQL  

---

## 1. Feature Overview

Infrastructure & Operations gives the Thawani DevOps and engineering team visibility into the health of the backend systems that power the POS platform. It surfaces queue worker status, failed job management, Redis cache controls, storage utilisation, automated database backup status, and basic server health metrics — all from within the Filament admin panel, reducing the need to SSH into servers for routine operational checks.

### What This Feature Does
- Queue worker health dashboard (Laravel Horizon)
- Background job failure alerts and manual retry controls
- Cache management (Redis flush, key inspection)
- Storage management: product images, receipts, backup files
- Automated database backup status, schedule, and restore controls
- Server health metrics: CPU, memory, disk, queue depth
- **Provider backup health monitoring** — aggregated dashboard showing the backup status of all provider stores' local POS data (last successful sync, missed backups, failed backups), so platform admins can proactively contact stores with data integrity risks
- **Cloud storage quota management** — per-plan storage quota definitions and usage tracking for providers; prevents stores from exceeding their plan's allocated storage for images, receipts, and backup files

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **All Features** | Every feature dispatches queued jobs (invoice generation, notifications, sync, reports); infra health determines job processing speed |
| **Billing & Finance** | Payment retry jobs, invoice PDF generation depend on healthy queue workers |
| **Notification Templates** | Push/SMS/email dispatch runs through queues |
| **Backup & Recovery (Provider)** | Provider backup health data is reported here; cloud storage quotas gate provider backup uploads |
| **Package & Subscription Mgmt** | Storage quotas are defined per subscription plan via `plan_limits` |
| **App Update Management** | Auto-rollback and adoption stat aggregation are queued jobs |
| **Analytics & Reporting** | Nightly aggregation jobs must complete for dashboard accuracy |
| **Security & Audit** | Security alert dispatch and audit log archival are queued |

### Features to Review After Changing This Feature
1. **System Configuration** — Redis connection settings are configured there; changes affect cache and queue
2. **Security & Audit** — infrastructure incidents (mass failures, disk full) should trigger security alerts
3. **Analytics & Reporting** — if backup or infra jobs fail, the nightly stats may be stale

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **laravel/horizon** | Redis queue dashboard, worker supervision, metrics |
| **filament/filament** v3 | Custom infra pages, backup resource |
| **spatie/laravel-backup** | Database + file backup with DigitalOcean Spaces upload (S3-compatible) |
| **spatie/laravel-permission** | `infrastructure.view`, `infrastructure.manage`, `infrastructure.backups` |
| **spatie/laravel-activitylog** | Audit trail for manual cache flushes, backup triggers, job retries |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | Queue system, Artisan commands, Scheduler, Health checks |
| **Filament v3** | Admin UI (custom pages + embedded Horizon iframe or widgets) |
| **Redis** | Queue backend, cache store, session store |
| **PostgreSQL** | `failed_jobs` table (Laravel built-in), `database_backups` table |
| **DigitalOcean Spaces** | Backup file storage, product image storage (S3-compatible API) |
| **Laravel Horizon** | Queue worker monitoring, auto-scaling, metrics |

### 3.3 Monitoring Architecture
```
┌─────────────────────────────────────────────────┐
│              Filament Admin Panel                │
│  ┌───────────┐ ┌───────────┐ ┌───────────────┐  │
│  │ Queue     │ │ Cache     │ │ Backup        │  │
│  │ Dashboard │ │ Manager   │ │ Status        │  │
│  └─────┬─────┘ └─────┬─────┘ └──────┬────────┘  │
│        │              │               │          │
│  ┌─────▼──────────────▼───────────────▼────────┐ │
│  │            Laravel Horizon API               │ │
│  │            Redis INFO command                │ │
│  │            spatie/laravel-backup              │ │
│  │            Server health (exec / HTTP)       │ │
│  └─────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────┘
```

---

## 4. Pages

### 4.1 Infrastructure Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure` |
| **Purpose** | At-a-glance health overview |
| **Widgets** | Queue status card (running/paused/failed count), Redis memory usage card, Disk usage card (data + backups), Last backup status card (time + size), Failed jobs count (last 24h), Server uptime |
| **Access** | `infrastructure.view` |

### 4.2 Queue Health (Horizon)
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/queues` |
| **Implementation** | Embedded Laravel Horizon dashboard via iframe, or custom Filament page that queries Horizon's API for: active workers, job throughput, wait times per queue, recent failures |
| **Queues Monitored** | `default`, `high`, `notifications`, `billing`, `sync`, `reports` |
| **Actions** | Pause queue, Resume queue (via Horizon API) |
| **Access** | `infrastructure.manage` |

### 4.3 Failed Jobs
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/failed-jobs` |
| **Source** | Laravel `failed_jobs` table |
| **Table Columns** | ID, Connection, Queue, Job Class (parsed from payload), Exception (truncated), Failed At |
| **Filters** | Queue, Date range |
| **Search** | By job class or exception text |
| **Row Actions** | View full exception, Retry, Delete |
| **Bulk Actions** | Retry All, Delete All |
| **Access** | `infrastructure.manage` |

### 4.4 Cache Management
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/cache` |
| **Purpose** | Redis cache inspection and flush controls |
| **Widgets** | Redis memory usage, Key count, Hit/miss ratio (from Redis INFO) |
| **Actions** | Flush All Cache (confirmation required), Flush by tag/prefix (e.g. `settings:*`, `feature_flags:*`, `permissions:*`), View sample keys (first 50 matching a prefix) |
| **Access** | `infrastructure.manage` |

### 4.5 Storage Overview
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/storage` |
| **Purpose** | View disk and DigitalOcean Spaces usage breakdown |
| **Widgets** | Local disk usage (total/used/free), Spaces bucket size, Breakdown by type: product images, receipt PDFs, invoice PDFs, backup files |
| **Actions** | Cleanup stale temp files (older than 7 days) |
| **Access** | `infrastructure.view` |

### 4.6 Database Backups
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/backups` |
| **Filament Resource** | `DatabaseBackupResource` (read-only + actions) |
| **Table Columns** | Backup Type (auto_daily / auto_weekly / manual), File Path, File Size, Status badge (completed / failed / in_progress), Started At, Completed At |
| **Filters** | Backup type, Status, Date range |
| **Row Actions** | Download, Restore (Super Admin only, with double-confirmation) |
| **Header Actions** | Trigger Manual Backup |
| **Access** | `infrastructure.backups` |

### 4.7 Server Health
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/server` |
| **Purpose** | Basic server metrics (from Laravel health check or custom endpoint) |
| **Widgets** | CPU usage %, Memory usage %, Disk I/O, Active DB connections, Queue depth per queue |
| **Refresh** | Auto-refresh every 30 seconds |
| **Access** | `infrastructure.view` |

### 4.8 Provider Backup Health Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/provider-backups` |
| **Purpose** | Monitor the backup health of all provider POS terminals across the platform |
| **Widgets** | Total stores count, Stores with successful backup in last 24h (green), Stores with backup older than 48h (yellow warning), Stores with no backup in 7+ days (red critical), Backup storage total usage |
| **Table** | Store Name, Organisation, Last Successful Sync, Last Cloud Backup, Backup Age (hours), Status badge (healthy / warning / critical), Storage Used (MB) |
| **Filters** | Status (healthy / warning / critical), Organisation, Date range |
| **Row Actions** | View store detail, Send backup reminder notification, Force sync request |
| **Note** | Data comes from provider-side `backup_logs` rows synced to the platform's `provider_backup_status` table via the store API. Each POS terminal reports its last backup timestamp during delta sync. |
| **Access** | `infrastructure.view` |

### 4.9 Cloud Storage Quota Management
| Field | Detail |
|---|---|
| **Route** | `/admin/infrastructure/storage-quotas` |
| **Purpose** | View and manage per-store cloud storage usage against plan-based quotas |
| **Widgets** | Total platform storage usage, Average usage per store, Stores over 80% quota (warning list), Stores over 100% quota (blocked list) |
| **Table** | Store Name, Plan, Quota (GB from `plan_limits.max_storage_gb`), Used (GB), Usage %, Status badge (ok / warning / exceeded) |
| **Filters** | Plan, Status, Usage % range |
| **Row Actions** | View storage breakdown (images / receipts / backups), Increase quota override (temporary), Send cleanup notification |
| **Note** | Storage quotas are defined as a `plan_limits` entry with `limit_key = 'max_storage_gb'`. Providers exceeding their quota are blocked from uploading new images/files until cleanup or upgrade. |
| **Access** | `infrastructure.view` |

### 5.1 Internal Livewire (Filament)
Cache flush actions, backup trigger, failed job retry — all via Livewire action methods.

### 5.2 Internal Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /admin/infrastructure/cache/flush` | POST | Flush all Redis cache | `infrastructure.manage` |
| `POST /admin/infrastructure/cache/flush-prefix` | POST | Flush keys by prefix | `infrastructure.manage` |
| `POST /admin/infrastructure/backups/trigger` | POST | Trigger manual backup job | `infrastructure.backups` |
| `POST /admin/infrastructure/backups/{id}/restore` | POST | Restore from backup (Super Admin) | `infrastructure.backups` + Super Admin |
| `POST /admin/infrastructure/failed-jobs/{id}/retry` | POST | Retry a single failed job | `infrastructure.manage` |
| `POST /admin/infrastructure/failed-jobs/retry-all` | POST | Retry all failed jobs | `infrastructure.manage` |

### 5.3 Scheduled Commands
| Command | Schedule | Purpose |
|---|---|---|
| `backup:run --only-db` | Daily at 02:00 | Automated daily database backup |
| `backup:run --only-db` | Weekly (Sunday 01:00) | Automated weekly full backup |
| `backup:clean` | Daily at 03:00 | Remove backups older than retention period |
| `horizon:snapshot` | Every 5 min | Capture Horizon metrics for dashboard |
| `queue:flush` (stale) | Daily at 04:00 | Clear jobs stuck in reserved state > 24h |

---

## 6. Full Database Schema

### 6.1 Tables

#### `failed_jobs` (Laravel built-in)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | BIGINT | PK, auto-increment | |
| uuid | VARCHAR(255) | UNIQUE | |
| connection | TEXT | NOT NULL | redis / database |
| queue | TEXT | NOT NULL | Queue name |
| payload | LONGTEXT | NOT NULL | Serialised job |
| exception | LONGTEXT | NOT NULL | Full exception trace |
| failed_at | TIMESTAMP | DEFAULT NOW() | |

```sql
-- Laravel's default migration (included for reference)
CREATE TABLE failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_failed_jobs_failed_at ON failed_jobs (failed_at);
```

#### `database_backups`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| backup_type | VARCHAR(20) | NOT NULL | auto_daily / auto_weekly / manual |
| file_path | TEXT | NOT NULL | Spaces key or local path |
| file_size_bytes | BIGINT | NULLABLE | Populated on completion |
| status | VARCHAR(20) | NOT NULL DEFAULT 'in_progress' | in_progress / completed / failed |
| error_message | TEXT | NULLABLE | Populated on failure |
| started_at | TIMESTAMP | DEFAULT NOW() | |
| completed_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE database_backups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    backup_type VARCHAR(20) NOT NULL,
    file_path TEXT NOT NULL,
    file_size_bytes BIGINT,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    error_message TEXT,
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE INDEX idx_database_backups_type_started ON database_backups (backup_type, started_at);
```

#### `provider_backup_status` (aggregated from provider sync reports)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| terminal_id | UUID | NOT NULL | POS terminal that reported |
| last_successful_sync | TIMESTAMP | NULLABLE | Last time data synced to cloud |
| last_cloud_backup | TIMESTAMP | NULLABLE | Last time a full cloud backup completed |
| storage_used_bytes | BIGINT | DEFAULT 0 | Total storage used by this store |
| status | VARCHAR(20) | DEFAULT 'unknown' | healthy / warning / critical / unknown |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE provider_backup_status (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    last_successful_sync TIMESTAMP,
    last_cloud_backup TIMESTAMP,
    storage_used_bytes BIGINT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'unknown',
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, terminal_id)
);

CREATE INDEX idx_provider_backup_status_status ON provider_backup_status (status);
CREATE INDEX idx_provider_backup_status_store ON provider_backup_status (store_id);
```

### 6.2 Indexes Summary

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `failed_jobs_failed_at` | failed_at | B-TREE | Date-range queries on failed jobs |
| `failed_jobs_uuid` | uuid | UNIQUE | Individual job lookup |
| `database_backups_type_started` | (backup_type, started_at) | B-TREE | Backup history per type |
| `provider_backup_status_pk` | (store_id, terminal_id) | UNIQUE | One status per terminal |
| `provider_backup_status_status` | status | B-TREE | Filter by health status |

### 6.3 External State (not in PostgreSQL)
- **Redis** — `queues:*`, `horizon:*` keys managed by Horizon; cache keys managed by Laravel Cache
- **DigitalOcean Spaces** — backup files stored under prefix `backups/`; product images under `images/products/`
- **Horizon Dashboard** — persists metrics in Redis with configurable retention (default 3 hours)

---

## 7. Business Rules

1. **Daily backups** — `spatie/laravel-backup` runs daily at 02:00; creates a `database_backups` row with status `in_progress`, updates to `completed` or `failed`
2. **Backup retention** — daily backups kept for 14 days; weekly backups kept for 90 days; `backup:clean` enforces this
3. **Manual backup** — admin can trigger a manual backup at any time; limited to 3 manual backups per day to prevent abuse
4. **Restore** — restore action is Super Admin only; requires typing "RESTORE" in confirmation modal; the restore job runs in a separate queue to avoid blocking other operations
5. **Failed job retry** — retrying a job re-dispatches it to the same queue; if it fails again, the failure count increments; after 3 total failures across retries, the job is permanently deleted
6. **Cache flush audit** — every cache flush (full or prefix) is logged in `admin_activity_logs` with the flushed prefix/scope
7. **Queue pause** — pausing a queue via Horizon is an emergency action; it stops all job processing on that queue; audit-logged
8. **Server metrics source** — CPU/memory/disk are fetched from a `/health` endpoint on the application server (Laravel Health Check or custom shell exec); not accurate when behind a load balancer (shows one instance only)
9. **Alert on failure threshold** — if `failed_jobs` count exceeds 50 in the last hour, a `security_alerts` row is created (type = `job_failure_spike`, severity = `high`)
10. **Horizon access** — the Horizon web dashboard (if exposed at `/horizon`) is restricted to Super Admin; the Filament infra page provides a sufficient summary for other admins
11. **Provider backup status classification** — `healthy` = last sync < 24h ago; `warning` = last sync 24–72h ago; `critical` = last sync > 72h ago or never synced. Calculated by a scheduled job running every hour.
12. **Provider backup status source** — each POS terminal includes its last backup timestamp in the delta sync heartbeat (`POST /api/v1/sync/heartbeat`). The platform updates `provider_backup_status` on each heartbeat.
13. **Storage quota enforcement** — when a store's `storage_used_bytes` exceeds the plan's `max_storage_gb` limit (from `plan_limits`), the provider-facing upload APIs return 413 (Payload Too Large) with a message to upgrade or free space.
14. **Storage quota overrides** — platform admins can temporarily increase a store's quota via `provider_limit_overrides` (defined in Package & Subscription Mgmt) without requiring a plan change. The override has an expiry date.
15. **Backup reminder notification** — when a store enters "critical" backup status, an automatic notification is sent to the store owner via push/email (using the Notification Templates system). Admins can also manually trigger reminders.
16. **Storage breakdown** — the storage used by each store is broken down into categories: `product_images`, `receipt_pdfs`, `invoice_pdfs`, `backup_files`, `other`. This helps store owners identify what to clean up.
