# Offline / Online Sync — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Bidirectional Data Sync, Offline-First Architecture, Conflict Resolution, Sync Queue  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11 · PostgreSQL · WebSocket  

---

## 1. Feature Overview

Offline/Online Sync is the backbone of the POS system's reliability. The POS operates fully offline using a local SQLite database (via Drift) and syncs data bidirectionally with the cloud PostgreSQL database when connectivity is available. This ensures zero downtime during internet outages — every sale, every inventory update, and every customer interaction is captured locally and synced later.

### What This Feature Does
- **Offline-first architecture** — all POS operations work without internet; local SQLite is the source of truth during operation
- **Bidirectional sync** — local changes push to cloud; cloud changes (from dashboard, other terminals) pull to local
- **Sync queue** — all local mutations are queued with timestamps; queue processes FIFO when online
- **Conflict resolution** — last-write-wins with timestamp comparison; critical conflicts (e.g., simultaneous stock edits) flagged for manual resolution
- **Delta sync** — only changed records since last sync are transferred; full sync on first setup or data corruption
- **Real-time push** — WebSocket connection for instant cloud-to-local updates (new orders from delivery, price changes from dashboard)
- **Sync status indicator** — persistent status bar showing: synced, syncing, X items pending, offline
- **Heartbeat** — periodic connection check (every 30 seconds) to detect connectivity changes
- **Data priority** — transactions sync first (highest priority), then inventory, then catalog, then settings
- **Bandwidth-aware** — sync adapts to connection speed; large payloads are chunked

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Hardware Support** | Network connectivity detection |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **All Features** | Every feature reads/writes through the sync layer |
| **POS Terminal** | Transaction data sync |
| **Inventory Management** | Stock level sync across terminals |
| **Product Catalog** | Catalog updates from dashboard |
| **Order Management** | Order sync with delivery platforms |
| **Customer Management** | Customer data sync |
| **Subscription & Billing** | Entitlement cache refresh |
| **Reports & Analytics** | Aggregated data for dashboard reports |

### Features to Review After Changing This Feature
1. **Every Drift model** — schema changes must be mirrored in sync serialization
2. **API versioning** — sync payload format changes require versioned endpoints

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local database with migration support |
| **dio** | HTTP client for REST sync endpoints |
| **web_socket_channel** | WebSocket for real-time cloud-to-local push |
| **connectivity_plus** | Network connectivity detection |
| **riverpod** / **flutter_bloc** | State management for sync status, online/offline state |
| **hive** / **shared_preferences** | Key-value store for sync metadata (last sync timestamp, sync token) |
| **uuid** | UUID generation for locally-created records |
| **crypto** | SHA-256 hashing for data integrity verification |

### 3.2 Technologies
- **Drift (SQLite)** — local relational database; mirrors cloud schema with additional sync metadata columns
- **PostgreSQL** — cloud database (Supabase/AWS RDS); authoritative for multi-terminal and dashboard data
- **REST API Sync** — chunked POST/GET endpoints for batch sync; supports pagination and delta queries
- **WebSocket (Pusher/Soketi)** — real-time event channel; cloud pushes events like `order.created`, `product.updated`
- **UUID v4** — all records created locally use UUIDs to avoid ID conflicts between terminals
- **Conflict resolution** — timestamp-based last-write-wins; `updated_at` column on every synced table; vector clocks for multi-terminal scenarios
- **Sync token** — opaque cursor token returned by server; client sends it on next sync request to get only newer changes

---

## 4. Screens

### 4.1 Sync Status Bar (Persistent)
| Field | Detail |
|---|---|
| **Location** | Bottom status bar of POS window |
| **Purpose** | Show current sync status |
| **States** | ✅ Synced (green), 🔄 Syncing (blue spinner + "Syncing X items..."), ⏳ Pending (amber + "X items pending"), 🔴 Offline (red + "Offline") |
| **Click Action** | Opens Sync Details panel |

### 4.2 Sync Details Panel
| Field | Detail |
|---|---|
| **Route** | Slide-out panel from status bar click |
| **Purpose** | Detailed sync status and manual controls |
| **Layout** | Last sync time, items pending by category (Transactions, Inventory, Catalog, Customers), Sync errors (if any), Force Sync button, Reset Sync button (with confirmation) |
| **Sync Errors** | List of failed items with error message and retry button |
| **Access** | `settings.sync` permission (Manager+) |

### 4.3 Initial Sync Screen
| Field | Detail |
|---|---|
| **Route** | Shown on first launch or after reset |
| **Purpose** | Full data download from cloud |
| **Layout** | Progress bar with percentage, category being synced (Catalog, Inventory, Customers, Settings...), estimated time remaining |
| **Cancel** | Can cancel and resume later; partial data is retained |

### 4.4 Conflict Resolution Screen
| Field | Detail |
|---|---|
| **Route** | `/sync/conflicts` (notification-triggered) |
| **Purpose** | Manually resolve sync conflicts |
| **Layout** | List of conflicting records; side-by-side comparison (local version vs cloud version); choose Local, Cloud, or Merge |
| **Access** | `settings.sync` permission |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/sync/push` | POST | Push local changes to cloud (batch) | Bearer token |
| `GET /api/sync/pull` | GET | Pull cloud changes since last sync token | Bearer token |
| `GET /api/sync/full` | GET | Full data download (initial sync / reset) | Bearer token |
| `GET /api/sync/status` | GET | Sync health check and server timestamp | Bearer token |
| `POST /api/sync/resolve-conflict` | POST | Submit conflict resolution decision | Bearer token |
| `GET /api/sync/conflicts` | GET | List unresolved conflicts | Bearer token |
| `POST /api/sync/heartbeat` | POST | Connectivity heartbeat + sync push for small changes | Bearer token |
| `WS /ws/store/{store_id}` | WebSocket | Real-time event channel | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `SyncEngine` | Orchestrates the entire sync lifecycle — queue processing, push, pull, conflict detection |
| `SyncQueueManager` | Manages the local mutation queue (FIFO); tracks pending items by priority |
| `DeltaSyncService` | Handles incremental sync using sync tokens; serializes/deserializes changed records |
| `FullSyncService` | Full data download for initial setup or reset; progress reporting |
| `ConflictResolver` | Detects conflicts (same record updated on two terminals); presents resolution UI or auto-resolves |
| `ConnectivityService` | Monitors internet connectivity; triggers sync when connection is restored |
| `WebSocketService` | Manages persistent WebSocket connection; dispatches incoming events to relevant services |
| `SyncStatusService` | Reactive sync status state — broadcasts to UI for status bar |
| `SyncRetryService` | Retries failed sync items with exponential backoff |
| `DataIntegrityService` | SHA-256 checksums on sync payloads; detects corruption |

---

## 6. Full Database Schema

### 6.1 Tables

#### `sync_queue` (Local SQLite only)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INTEGER | PK, AUTO_INCREMENT | Local only |
| table_name | TEXT | NOT NULL | e.g., "transactions", "products" |
| record_id | TEXT | NOT NULL | UUID of the changed record |
| operation | TEXT | NOT NULL | insert, update, delete |
| payload_json | TEXT | NOT NULL | Full record data as JSON |
| priority | INTEGER | NOT NULL | 1=transactions, 2=inventory, 3=catalog, 4=customers, 5=settings |
| created_at | TEXT | NOT NULL | ISO 8601 timestamp |
| status | TEXT | DEFAULT 'pending' | pending, syncing, synced, failed |
| retry_count | INTEGER | DEFAULT 0 | |
| error_message | TEXT | NULLABLE | Last error if failed |

> Drift (SQLite) only — not in PostgreSQL.

#### `sync_metadata` (Local SQLite only)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| key | TEXT | PK | e.g., "last_sync_token", "last_full_sync", "server_time_offset" |
| value | TEXT | NOT NULL | |
| updated_at | TEXT | NOT NULL | |

> Drift (SQLite) only — not in PostgreSQL.

#### `sync_conflicts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| table_name | VARCHAR(100) | NOT NULL | |
| record_id | UUID | NOT NULL | |
| local_data | JSONB | NOT NULL | Local version at conflict time |
| cloud_data | JSONB | NOT NULL | Cloud version at conflict time |
| resolution | VARCHAR(20) | NULLABLE | local_wins, cloud_wins, merged, NULL if unresolved |
| resolved_by | UUID | FK → users(id), NULLABLE | |
| detected_at | TIMESTAMP | DEFAULT NOW() | |
| resolved_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE sync_conflicts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    table_name VARCHAR(100) NOT NULL,
    record_id UUID NOT NULL,
    local_data JSONB NOT NULL,
    cloud_data JSONB NOT NULL,
    resolution VARCHAR(20),
    resolved_by UUID REFERENCES users(id),
    detected_at TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP
);
```

#### `sync_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| terminal_id | UUID | NOT NULL | |
| direction | VARCHAR(10) | NOT NULL | push, pull, full |
| records_count | INTEGER | NOT NULL | Number of records synced |
| duration_ms | INTEGER | NOT NULL | Sync duration in milliseconds |
| status | VARCHAR(20) | NOT NULL | success, partial, failed |
| error_message | TEXT | NULLABLE | |
| started_at | TIMESTAMP | NOT NULL | |
| completed_at | TIMESTAMP | NULLABLE | |

```sql
CREATE TABLE sync_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    direction VARCHAR(10) NOT NULL,
    records_count INTEGER NOT NULL DEFAULT 0,
    duration_ms INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    error_message TEXT,
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMP
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `sync_queue_status_priority` | (status, priority) | B-TREE (local) | Queue processing order |
| `sync_conflicts_store_unresolved` | (store_id, resolution) WHERE resolution IS NULL | B-TREE PARTIAL | Unresolved conflicts |
| `sync_log_store_date` | (store_id, started_at) | B-TREE | Sync history |
| `sync_log_terminal` | (terminal_id, started_at) | B-TREE | Per-terminal sync history |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ sync_conflicts
stores ──1:N──▶ sync_log
Local SQLite: sync_queue (independent)
Local SQLite: sync_metadata (key-value)
```

---

## 7. Business Rules

1. **Offline-first guarantee** — POS must function 100% for all core operations (sales, payments, receipts) without internet; no feature degrades during offline operation except features requiring real-time cloud data (delivery order acceptance)
2. **Sync queue priority** — transactions (priority 1) sync before all other data; ensures financial data reaches the cloud ASAP
3. **Conflict detection** — if the same record (same UUID) has been modified both locally and in the cloud since the last sync, a conflict is detected; the `updated_at` timestamps are compared
4. **Auto-resolve simple conflicts** — for non-critical data (e.g., product description change), last-write-wins based on `updated_at`; critical data (stock levels, financial records) flagged for manual resolution
5. **Initial sync required** — POS cannot enter operational mode until the initial full sync completes; minimum: catalog, inventory, and staff data
6. **Sync batch size** — push operations send a maximum of 100 records per API call; larger batches are chunked automatically
7. **Exponential backoff** — failed sync items retry with exponential backoff: 5s, 15s, 45s, 2min, 5min, up to 30min max
8. **Data integrity** — every sync payload includes a SHA-256 checksum; mismatches trigger re-sync of affected records
9. **Sync token persistence** — the last sync token is stored locally; if lost, a full re-sync is triggered (only changed records since store creation)
10. **WebSocket reconnection** — if the WebSocket connection drops, the client attempts reconnection with exponential backoff; during disconnection, periodic pull-sync compensates
11. **Clock skew handling** — the server returns its current timestamp on every sync response; the client calculates and stores the offset between local and server time to correctly order events
12. **Sync pause during transactions** — sync pull operations are paused while a transaction is in progress to avoid data changes mid-sale; push operations continue normally
