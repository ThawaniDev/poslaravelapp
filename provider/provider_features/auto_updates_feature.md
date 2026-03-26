# Auto-Updates — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Application Updates, Delta Updates, Scheduled Updates, Rollback  
> **Tech Stack:** Flutter 3.x Desktop (Windows) · MSIX / Squirrel.Windows · Laravel 11  

---

## 1. Feature Overview

Auto-Updates ensures the POS application stays current with the latest features, bug fixes, and security patches without requiring manual intervention from the store owner. Updates are downloaded in the background, verified, and applied during non-business hours or at the owner's convenience.

### What This Feature Does
- **Update check** — periodic check for new versions (every 6 hours) against update server
- **Background download** — updates downloaded in the background without interrupting POS operations
- **Staged rollout** — platform can release updates to a percentage of stores (canary, then full rollout)
- **Scheduled install** — updates install during configured maintenance window (default: 2-4 AM)
- **Pre-update backup** — automatic database backup before applying update
- **Delta updates** — only changed components are downloaded, reducing bandwidth
- **Rollback** — if update causes errors, automatic rollback to previous version
- **Force update** — platform can flag critical updates that must be installed within 24 hours
- **Update notifications** — notify store owner about available and scheduled updates
- **Version pinning** — platform can pin a store to a specific version (for troubleshooting)
- **Changelog display** — show what's new in each version before and after update

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Backup & Recovery** | Pre-update backup |
| **Offline/Online Sync** | Network connectivity for update download |
| **Notifications** | Update notifications to store owner |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **All Features** | Receive feature additions and bug fixes |
| **Security** | Security patch delivery |

### Features to Review After Changing This Feature
1. **Backup & Recovery** — pre-update backup trigger
2. **Platform Admin Panel** — update rollout management

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **msix** | MSIX packaging for Windows app distribution |
| **auto_updater** / custom updater | Check, download, and apply updates |
| **crypto** | SHA-256 checksum for update file verification |
| **archive** | Extract update packages |
| **dio** | HTTP client for update server communication |
| **path_provider** | Temp directory for update downloads |
| **shared_preferences** | Store current version, last update check, update preferences |

### 3.2 Technologies
- **MSIX** — Windows app packaging format; supports auto-update via App Installer or custom updater
- **Squirrel.Windows** — alternative Windows installer/updater with delta update support
- **Update server** — Laravel backend serves update manifests and binaries; CDN for binary delivery
- **Code signing** — all update binaries are signed; signature verified before installation
- **Delta compression** — binary diff between versions; only changed files downloaded
- **Semver** — semantic versioning (MAJOR.MINOR.PATCH) for update compatibility checks

---

## 4. Screens

### 4.1 Update Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/updates` |
| **Purpose** | Configure update preferences |
| **Layout** | Current version display, Auto-update toggle, Maintenance window (start/end time), Update channel (stable/beta), Last update check time |
| **Actions** | Check Now, View Changelog, Install Update (when available) |
| **Access** | `settings.updates` permission (Owner) |

### 4.2 Update Available Dialog
| Field | Detail |
|---|---|
| **Route** | Modal dialog (triggered by update detection) |
| **Purpose** | Inform user about available update |
| **Layout** | New version number, changelog highlights, "Install Now" / "Schedule for Tonight" / "Remind Later" |
| **Force Update** | If critical, "Remind Later" is replaced with countdown timer |

### 4.3 Update Progress Screen
| Field | Detail |
|---|---|
| **Route** | Full-screen during update |
| **Purpose** | Show update progress |
| **Layout** | Progress bar, current step (Backing up → Downloading → Verifying → Installing → Migrating DB → Complete), cancel option (not available during install phase) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/updates/check` | GET | Check for available updates | Bearer token |
| `GET /api/updates/manifest/{version}` | GET | Get update manifest (files, checksums, delta info) | Bearer token |
| `GET /api/updates/download/{version}` | GET | Download update binary | Bearer token |
| `POST /api/updates/report` | POST | Report update result (success/failure) | Bearer token |
| `GET /api/updates/changelog/{version}` | GET | Get changelog for version | Bearer token |
| `GET /api/updates/rollout-status` | GET | Check if this store is in current rollout wave | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `UpdateChecker` | Periodic version check against update server |
| `UpdateDownloader` | Background download with progress tracking; resume support |
| `UpdateVerifier` | SHA-256 checksum and code signature verification |
| `UpdateInstaller` | Applies update; triggers backup first; handles MSIX/Squirrel install |
| `RollbackService` | Detects post-update errors; reverts to previous version |
| `UpdateScheduler` | Schedules update installation during maintenance window |
| `ChangelogService` | Fetches and displays version changelog |

---

## 6. Full Database Schema

### 6.1 Tables

#### `update_history` (Local SQLite only)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | INTEGER | PK, AUTO_INCREMENT | Local only |
| from_version | TEXT | NOT NULL | Previous version |
| to_version | TEXT | NOT NULL | New version |
| update_type | TEXT | NOT NULL | full, delta, rollback |
| status | TEXT | NOT NULL | downloaded, installing, completed, failed, rolled_back |
| download_size_bytes | INTEGER | NULLABLE | |
| backup_id | TEXT | NULLABLE | Pre-update backup UUID |
| error_message | TEXT | NULLABLE | |
| started_at | TEXT | NOT NULL | |
| completed_at | TEXT | NULLABLE | |

> Drift (SQLite) only.

#### `update_preferences` (Local SQLite only)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| key | TEXT | PK | auto_update_enabled, maintenance_start, maintenance_end, update_channel |
| value | TEXT | NOT NULL | |

> Drift (SQLite) only.

#### `update_rollouts` (Cloud PostgreSQL — platform managed)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| version | VARCHAR(20) | NOT NULL | |
| rollout_percentage | INTEGER | NOT NULL | 0-100 |
| is_critical | BOOLEAN | DEFAULT FALSE | |
| target_stores | JSONB | NULLABLE | Specific store IDs, or NULL for all |
| pinned_stores | JSONB | NULLABLE | Stores pinned to previous versions |
| release_notes | TEXT | NOT NULL | |
| released_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE update_rollouts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version VARCHAR(20) NOT NULL,
    rollout_percentage INTEGER NOT NULL DEFAULT 0,
    is_critical BOOLEAN DEFAULT FALSE,
    target_stores JSONB,
    pinned_stores JSONB,
    release_notes TEXT NOT NULL,
    released_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `rollouts_version` | version | B-TREE | Version lookup |
| `rollouts_released` | released_at | B-TREE | Chronological listing |

### 6.3 Relationships Diagram
```
Local SQLite: update_history (independent)
Local SQLite: update_preferences (key-value)
Cloud: update_rollouts (managed by platform)
```

---

## 7. Business Rules

1. **Non-disruptive downloads** — updates are downloaded in the background; POS operations are never interrupted during download
2. **Maintenance window** — updates are applied only during the configured maintenance window (default 2:00-4:00 AM); if POS is in use during window, update defers to next window
3. **Pre-update backup required** — update installation is blocked if the pre-update backup fails
4. **Post-update health check** — after update, the app runs a health check (DB access, hardware connectivity, critical service checks); if any fail, automatic rollback is triggered
5. **Force update deadline** — critical updates show a countdown; if not installed within 24 hours, POS shows a blocking overlay requiring update before operations resume
6. **Rollback limit** — only the most recent update can be rolled back; rolling back twice is not supported (would require restore from backup)
7. **Update channel** — "stable" (default) receives thoroughly tested updates; "beta" receives earlier access to new features with opt-in acknowledgment
8. **Bandwidth management** — update downloads are throttled to 50% of available bandwidth during business hours; full speed during off-hours
9. **Version compatibility** — update server checks that the store's database schema version is compatible with the new app version; if not, a migration path is provided
10. **Update reporting** — after each update (success or failure), a report is sent to the platform for monitoring rollout health
