# Backup & Recovery — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Local Database Backup, Cloud Backup, Point-in-Time Recovery, Data Export  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11 · DigitalOcean Spaces (S3-compatible)  

---

## 1. Feature Overview

Backup & Recovery ensures that POS data is protected against hardware failure, corruption, accidental deletion, and ransomware. The system performs automatic local and cloud backups at configurable intervals, and provides tools to restore data to any backup point.

### What This Feature Does
- **Automatic local backup** — scheduled SQLite database backup to a local directory; configurable frequency (hourly, daily)
- **Cloud backup** — encrypted SQLite backup uploaded to DigitalOcean Spaces via Laravel backend
- **Manual backup** — one-click "Backup Now" from settings
- **Point-in-time restore** — restore from any backup in the backup history list
- **Backup verification** — automatic integrity check (SHA-256 checksum) on each backup
- **Backup rotation** — automatic cleanup of old backups based on retention policy
- **Export data** — export all store data as structured files (CSV/JSON) for portability
- **Recovery wizard** — step-by-step guide for restoring from backup on a new machine
- **Backup notifications** — alert on backup failure; daily summary of backup status
- **Pre-update backup** — automatic backup created before any app update

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Offline/Online Sync** | Cloud backup requires connectivity |
| **Hardware Support** | Local storage for backup files |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **Auto-Updates** | Pre-update backup |
| **All Features** | Data protection and recovery |

### Features to Review After Changing This Feature
1. **Drift database schema** — schema changes affect backup compatibility
2. **Auto-Updates** — pre-update backup trigger

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — source database for backup |
| **sqflite_common_ffi** | Low-level SQLite access for raw backup operations |
| **path_provider** | Get platform-specific backup directory |
| **archive** / **dart:io** | Gzip compression for backup files |
| **pointycastle** / **encrypt** | AES-256 encryption for cloud backups |
| **crypto** | SHA-256 checksum for integrity verification |
| **dio** | HTTP client for cloud backup upload/download |

### 3.2 Technologies
- **SQLite `.backup()` API** — native SQLite online backup API for consistent database copy
- **AES-256-GCM encryption** — backup files encrypted before cloud upload; encryption key derived from store secret
- **Gzip compression** — backups compressed before encryption; typical compression ratio 60-70%
- **DigitalOcean Spaces** — cloud backup storage (S3-compatible); organized by `store_id/terminal_id/date/`
- **SHA-256 checksum** — computed before upload and verified on download; stored alongside backup metadata
- **Retention policy** — keep last 24 hourly backups, last 30 daily backups, last 12 monthly backups

---

## 4. Screens

### 4.1 Backup Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/backup` |
| **Purpose** | Configure backup settings |
| **Layout** | Auto-backup toggle, Frequency (hourly/daily), Local backup directory, Cloud backup toggle, Retention policy display, Last backup status |
| **Actions** | Backup Now, Change Directory, View Backup History |
| **Access** | `settings.backup` permission (Owner, Manager) |

### 4.2 Backup History Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/backup/history` |
| **Purpose** | View all backups and restore from one |
| **Layout** | Data table — backup date/time, type (auto/manual/pre-update), size, location (local/cloud), status (verified/corrupted), Restore button |
| **Actions** | Restore from backup, Delete backup, Download cloud backup, Verify integrity |
| **Access** | `settings.backup` permission |

### 4.3 Restore Wizard
| Field | Detail |
|---|---|
| **Route** | `/settings/backup/restore` |
| **Purpose** | Step-by-step data restoration |
| **Steps** | 1. Select backup (local or cloud) → 2. Verify integrity → 3. Confirm (warning: current data will be replaced) → 4. Restore progress → 5. Verification → 6. Complete |
| **Warning** | Bold warning that restoration replaces current database; option to backup current state first |
| **Access** | Owner only |

### 4.4 Data Export Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/backup/export` |
| **Purpose** | Export data for portability |
| **Layout** | Checkboxes per data category (Products, Customers, Orders, Inventory, Settings), Format (CSV/JSON), Export button |
| **Access** | Owner only |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/backup/upload` | POST | Upload encrypted backup to cloud | Bearer token, Owner |
| `GET /api/backup/list` | GET | List cloud backups for this store | Bearer token, Owner |
| `GET /api/backup/download/{id}` | GET | Download a cloud backup | Bearer token, Owner |
| `DELETE /api/backup/{id}` | DELETE | Delete a cloud backup | Bearer token, Owner |
| `GET /api/backup/status` | GET | Backup health status (last backup, next scheduled) | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `BackupScheduler` | Manages automatic backup scheduling (cron-like intervals) |
| `LocalBackupService` | Creates SQLite backup copy; compresses; stores locally |
| `CloudBackupService` | Encrypts and uploads backup to cloud; downloads for restore |
| `BackupEncryptionService` | AES-256-GCM encryption/decryption of backup files |
| `BackupIntegrityService` | SHA-256 checksum generation and verification |
| `RestoreService` | Restores database from backup file; handles schema migration if needed |
| `DataExportService` | Exports data as CSV/JSON files per category |
| `BackupRetentionService` | Cleans up old backups according to retention policy |

---

## 6. Full Database Schema

### 6.1 Tables

#### `backup_history` (Local SQLite + Cloud PostgreSQL)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| terminal_id | UUID | NOT NULL | |
| backup_type | VARCHAR(20) | NOT NULL | auto, manual, pre_update |
| storage_location | VARCHAR(20) | NOT NULL | local, cloud, both |
| local_path | TEXT | NULLABLE | Local file path |
| cloud_key | VARCHAR(500) | NULLABLE | Spaces key / cloud path |
| file_size_bytes | BIGINT | NOT NULL | |
| checksum | VARCHAR(64) | NOT NULL | SHA-256 |
| db_version | INTEGER | NOT NULL | Drift schema version at backup time |
| records_count | INTEGER | NULLABLE | Total records backed up |
| is_verified | BOOLEAN | DEFAULT FALSE | |
| is_encrypted | BOOLEAN | DEFAULT TRUE | |
| status | VARCHAR(20) | DEFAULT 'completed' | completed, failed, corrupted |
| error_message | TEXT | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE backup_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    backup_type VARCHAR(20) NOT NULL,
    storage_location VARCHAR(20) NOT NULL,
    local_path TEXT,
    cloud_key VARCHAR(500),
    file_size_bytes BIGINT NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    db_version INTEGER NOT NULL,
    records_count INTEGER,
    is_verified BOOLEAN DEFAULT FALSE,
    is_encrypted BOOLEAN DEFAULT TRUE,
    status VARCHAR(20) DEFAULT 'completed',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `backup_store_date` | (store_id, created_at) | B-TREE | Backup history listing |
| `backup_terminal_date` | (terminal_id, created_at) | B-TREE | Per-terminal backup history |
| `backup_checksum` | checksum | B-TREE | Integrity lookup |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ backup_history
backup_history ── scoped by terminal_id
```

---

## 7. Business Rules

1. **Automatic backup frequency** — default: local backup every 4 hours during business hours; cloud backup once daily at end of day
2. **Pre-update backup mandatory** — before any app update is applied, an automatic backup is created; if backup fails, update is blocked
3. **Backup during idle** — backups are scheduled during idle periods (no active transaction in progress); if POS is busy, backup waits up to 5 minutes then forces
4. **Cloud backup encryption** — all cloud backups are encrypted with AES-256-GCM before upload; decryption key is derived from the store's secret (never uploaded)
5. **Backup verification** — after each backup, a verification read is performed (open backup database, count tables and rows); if verification fails, the backup is marked as corrupted and a notification is sent
6. **Retention policy** — keep: last 7 local backups, last 30 cloud daily backups, last 12 cloud monthly backups; older backups auto-deleted
7. **Restore replaces current DB** — restoring from backup completely replaces the current local database; a backup of the current state is automatically created before restore
8. **Schema migration on restore** — if restoring a backup from an older Drift schema version, migrations are automatically applied after restore
9. **Export data format** — CSV exports use UTF-8 with BOM for Arabic text compatibility; JSON exports use standard JSON with UTF-8
10. **Concurrent backup prevention** — only one backup operation can run at a time per terminal; concurrent requests are queued
