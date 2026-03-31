# App & Update Management — Comprehensive Feature Documentation

> **Scope:** Platform (Thawani Internal Admin Panel)  
> **Module:** Flutter Desktop & Mobile Release Versioning, Staged Rollouts, Force-Update, Auto-Rollback, Update Statistics  
> **Tech Stack:** Laravel 11 + Filament v3 · DigitalOcean Spaces (binary hosting) · Redis (rollout cache) · App Store Connect / Google Play Console (mobile)  

---

## 1. Feature Overview

App & Update Management controls the lifecycle of the Wameed POS Flutter Desktop application (Windows and macOS) **and the Mobile Companion App (iOS and Android)**. It lets the platform team publish new versions, enforce minimum version requirements, stage rollouts by percentage, push force-update prompts, and monitor adoption across all stores. Auto-rollback logic protects providers from crash-loop releases. For mobile apps, actual deployment goes through App Store / Google Play, but version bookkeeping, force-update flags, and adoption tracking are managed here.

### What This Feature Does
- Manage Flutter desktop app release versions (Windows, macOS)
- **Manage Mobile Companion App release versions (iOS, Android)** — version tracking, minimum version enforcement, staged rollouts; app store submission is external but version bookkeeping and force-update flags are managed here
- Minimum supported version setting (force-update below this version)
- Force-update flag per version
- Staged rollout percentage per version
- Release notes / changelog (AR / EN) shown in-app on update prompt
- Download URL management per platform per version
- Update channel management: stable, beta
- Rollback capability: revert stores to a previous version
- Auto-rollback trigger on crash-loop detection
- Update statistics: adoption rate, pending updates, failed installs

---

## 2. Affected Features & Dependencies

### Features That Depend on This Feature
| Feature | How It's Affected |
|---|---|
| **Provider Management** | Store detail shows current app version and update status per terminal |
| **Analytics & Reporting** | Hardware type breakdown and update adoption rate metrics |
| **Platform Announcements** | "Update" announcement type links to a release |
| **Infrastructure & Operations** | Failed installs appear in job failure monitoring |
| **POS Layout Mgmt** | New layout features require minimum app version to render correctly |
| **System Configuration** | Feature flags may gate features available only in newer app versions |

### Features to Review After Changing This Feature
1. **Provider Management** — store terminal list view references `registers.app_version`; update status shown from `app_update_stats`
2. **Analytics** — update adoption dashboard draws from `app_update_stats`
3. **Platform Announcements** — must coordinate update-type announcement with release creation

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **filament/filament** v3 | Resources for Releases, Update Stats; custom dashboard widgets |
| **spatie/laravel-permission** | `updates.view`, `updates.manage`, `updates.rollback` |
| **spatie/laravel-activitylog** | Audit trail for publish, rollback, force-update changes |
| **maatwebsite/laravel-excel** | Export update stats |

### 3.2 Technologies
| Technology | Role |
|---|---|
| **Laravel 11** | Models, Jobs (rollout, auto-rollback), Notifications |
| **Filament v3** | Admin UI |
| **PostgreSQL** | Releases + update stats |
| **Redis** | Cached "latest release" per platform+channel; rollout bucket assignments |
| **DigitalOcean Spaces + CDN** | Binary hosting (`.exe`, `.dmg`, `.zip`) — S3-compatible API |
| **Flutter Desktop (client side)** | Auto-update check on launch + periodic polling (every 30 min) |

### 3.3 Auto-Update Flow (Client ↔ Server)

#### Desktop (Windows / macOS)
```
Flutter App → GET /api/v1/updates/check?platform=windows&channel=stable&current_version=1.2.3
             ← { "update_available": true, "version": "1.3.0", "is_force": false, "download_url": "…", "release_notes": "…" }
Flutter App → (user accepts or force) → downloads binary → installs → reports status
             → POST /api/v1/updates/report { "release_id": "…", "status": "installed" }
```

#### Mobile (iOS / Android)
```
Mobile App → GET /api/v1/updates/check?platform=ios&channel=stable&current_version=2.1.0
           ← { "update_available": true, "version": "2.2.0", "is_force": true, "store_url": "…", "release_notes": "…" }
Mobile App → (user tapped update or force) → opens App Store / Google Play → user installs externally
Mobile App → (on next launch with new version) → POST /api/v1/updates/report { "release_id": "…", "status": "installed" }
```
Mobile binaries are NOT hosted on DigitalOcean Spaces — they go through Apple TestFlight / App Store / Google Play Internal Testing / Production. The platform tracks versions, enforces min-version checks, and monitors adoption, but the actual install is handled by the respective app store.

### 3.4 Rollout Percentage Logic
Each store is assigned a deterministic bucket: `CRC32(store_id) % 100`. A release with `rollout_percentage = 25` is offered only to stores whose bucket is `< 25`. The admin can increase the percentage over days to ramp up.

### 3.5 Auto-Rollback
If `> X%` (configurable, default 10%) of stores that install a release report `status = failed` within the first 24 hours, the system automatically:
1. Sets `is_active = false` on the problematic release
2. Creates a `security_alerts` row (type = `app_crash_loop`, severity = `critical`)
3. Notifies Super Admins via push + email
4. Affected stores fall back to the previous active release on next update check

---

## 4. Pages

### 4.1 Releases List
| Field | Detail |
|---|---|
| **Route** | `/admin/updates/releases` |
| **Filament Resource** | `AppReleaseResource` |
| **Table Columns** | Version Number, Platform badge (windows/macos/ios/android), Channel badge (stable/beta/testflight/internal_test), Force Update badge, Rollout %, Submission Status badge (mobile only), Download URL (truncated), Is Active badge, Released At |
| **Filters** | Platform, Channel, Is Active, Force Update, Submission Status |
| **Search** | By version number |
| **Row Actions** | Edit, Deactivate, View Stats, Rollback |
| **Header Actions** | Create Release |
| **Access** | `updates.view` |

### 4.2 Create / Edit Release
| Field | Detail |
|---|---|
| **Route** | `/admin/updates/releases/create` or `/admin/updates/releases/{id}/edit` |
| **Form Fields** | Version Number (semver validated), Platform (select: windows / macos / ios / android), Channel (select: stable / beta / testflight / internal_test), Download URL (S3 for desktop; store link for mobile), Store URL (conditional: shown only for ios/android — App Store or Play Store listing URL), Build Number (conditional: shown only for ios/android — CFBundleVersion or versionCode), Submission Status (conditional: shown only for ios/android — not_applicable/submitted/in_review/approved/rejected/live), Release Notes (EN), Release Notes (AR), Is Force Update toggle, Min Supported Version (semver), Rollout Percentage slider (0–100), Is Active toggle |
| **Validation** | Version must be unique per (platform, channel). Rollout % starts at 0 on create. For mobile platforms, store_url is required. |
| **Access** | `updates.manage` |

### 4.3 Release Detail / Stats
| Field | Detail |
|---|---|
| **Route** | `/admin/updates/releases/{id}` |
| **Sections** | Release info card, Adoption funnel (pending → downloading → downloaded → installed / failed), Timeline chart of installs per hour, Store list table (store name, status, last updated) |
| **Access** | `updates.view` |

### 4.4 Update Dashboard
| Field | Detail |
|---|---|
| **Route** | `/admin/updates` |
| **Widgets** | Latest stable version card (per platform — Windows, macOS, iOS, Android), Stores on latest %, Stores on outdated versions count, Pending updates count, Failed installs count (last 7 days), Version distribution pie chart, Mobile submission pipeline (submitted → in_review → approved → live) |
| **Access** | `updates.view` |

### 4.5 Rollback Confirmation
| Field | Detail |
|---|---|
| **Route** | Modal on Release Detail |
| **Purpose** | Confirm rollback — deactivates release, logs reason |
| **Fields** | Rollback Reason (text), Confirm checkbox |
| **Access** | `updates.rollback` (Super Admin) |

---

## 5. APIs

### 5.1 Internal Livewire (Filament auto-generated)
Standard CRUD for `AppReleaseResource`.

### 5.2 Provider-Facing APIs (consumed by Flutter Desktop client)
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/v1/updates/check` | GET | Check for available update | Store API token |
| **Query Params** | | `platform` (windows/macos/ios/android), `channel` (stable/beta/testflight/internal_test), `current_version`, `build_number?` (mobile) | |
| **Response** | | `{ update_available, version, is_force, download_url, store_url?, release_notes, release_notes_ar, min_supported_version }` | |
| `POST /api/v1/updates/report` | POST | Report update status | Store API token |
| **Body** | | `{ release_id, status (pending/downloading/downloaded/installed/failed), error_message? }` | |

### 5.3 Internal Jobs
| Job | Purpose |
|---|---|
| `CheckAutoRollback` | Runs every 15 min; checks failure rate for releases active < 24h; triggers rollback if threshold exceeded |
| `CacheLatestRelease` | Rebuilds Redis cache of current active release per (platform, channel) on release create/edit |

---

## 6. Full Database Schema

### 6.1 Tables

#### `app_releases`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| version_number | VARCHAR(20) | NOT NULL | Semantic versioning: `1.3.0`, `2.0.0-beta.1` |
| platform | VARCHAR(10) | NOT NULL | windows / macos / ios / android |
| channel | VARCHAR(10) | NOT NULL | stable / beta / testflight / internal_test |
| download_url | TEXT | NOT NULL | DigitalOcean Spaces/CDN URL for desktop; App Store / Play Store URL for mobile |
| store_url | TEXT | NULLABLE | App Store / Google Play listing URL (mobile only; NULL for desktop) |
| build_number | VARCHAR(20) | NULLABLE | iOS CFBundleVersion / Android versionCode — used for precise version matching on mobile |
| submission_status | VARCHAR(20) | DEFAULT 'not_applicable' | For mobile: not_applicable / submitted / in_review / approved / rejected / live |
| release_notes | TEXT | NULLABLE | EN |
| release_notes_ar | TEXT | NULLABLE | AR |
| is_force_update | BOOLEAN | DEFAULT FALSE | |
| min_supported_version | VARCHAR(20) | NULLABLE | Versions below this must update |
| rollout_percentage | INT | NOT NULL DEFAULT 0 | 0–100 |
| is_active | BOOLEAN | DEFAULT TRUE | |
| released_at | TIMESTAMP | DEFAULT NOW() | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE app_releases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version_number VARCHAR(20) NOT NULL,
    platform VARCHAR(10) NOT NULL,
    channel VARCHAR(10) NOT NULL DEFAULT 'stable',
    download_url TEXT NOT NULL,
    store_url TEXT,
    build_number VARCHAR(20),
    submission_status VARCHAR(20) DEFAULT 'not_applicable',
    release_notes TEXT,
    release_notes_ar TEXT,
    is_force_update BOOLEAN DEFAULT FALSE,
    min_supported_version VARCHAR(20),
    rollout_percentage INT NOT NULL DEFAULT 0 CHECK (rollout_percentage BETWEEN 0 AND 100),
    is_active BOOLEAN DEFAULT TRUE,
    released_at TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (platform, channel, version_number)
);

CREATE INDEX idx_app_releases_active ON app_releases (platform, channel, is_active);
```

#### `app_update_stats`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id) | |
| app_release_id | UUID | FK → app_releases(id) | |
| status | VARCHAR(20) | NOT NULL | pending / downloading / downloaded / installed / failed |
| error_message | TEXT | NULLABLE | Populated on failure |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE app_update_stats (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    app_release_id UUID NOT NULL REFERENCES app_releases(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT,
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_update_stats_release_status ON app_update_stats (app_release_id, status);
CREATE INDEX idx_update_stats_store ON app_update_stats (store_id);
```

### 6.2 Indexes Summary

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `app_releases_platform_channel_version` | (platform, channel, version_number) | UNIQUE | One version entry per platform per channel |
| `app_releases_active` | (platform, channel, is_active) | B-TREE | Fast latest-release lookup |
| `app_update_stats_release_status` | (app_release_id, status) | B-TREE | Adoption funnel aggregation |
| `app_update_stats_store` | store_id | B-TREE | Per-store update history |

### 6.3 Related Tables
- `registers` — POS terminals; `app_version` column updated when store reports `installed`
- `stores` — join for store name and plan info in stats views

---

## 7. Business Rules

1. **Semver uniqueness** — each (platform, channel, version_number) combination is unique; creating a duplicate fails with validation error
2. **Rollout ramp-up** — new releases start at 0% rollout; admin manually increases over time; jumping to 100% is allowed but discouraged for major versions
3. **Force update** — when `is_force_update = true` the Flutter client blocks the POS UI until the user installs the update; reserve for critical security patches
4. **Min supported version** — if a store's `current_version` is below `min_supported_version` of any active release, the update check response includes `is_force = true` regardless of the release's own force flag
5. **Auto-rollback threshold** — configurable via `system_settings` key `updates.auto_rollback_failure_percent` (default 10%); checked by `CheckAutoRollback` job
6. **Rollback** — sets `is_active = false`, audit-logged with reason; stores fall back to the previous active release on next check
7. **Beta channel** — only stores flagged as beta testers (via store metadata or feature flag) receive beta releases
8. **Update report idempotency** — `POST /api/v1/updates/report` upserts on (store_id, app_release_id); status transitions must be forward only (pending → downloading → … → installed/failed)
9. **Download URL** — must point to DigitalOcean Spaces/CDN; admin panel validates URL format but does not verify file existence (fast-fail would block workflow)
10. **Release notes** — displayed in-app; supports Markdown formatting; both AR and EN required for user-facing releases
11. **Mobile store submission tracking** — for iOS/Android releases, the `submission_status` field tracks the app review pipeline (submitted → in_review → approved → live or rejected). This is manually updated by the admin (or via future App Store Connect / Google Play API integration). A release with `submission_status = rejected` is automatically set to `is_active = false`.
12. **Mobile rollout via stores** — mobile rollout percentage is advisory/informational; actual staged rollout is controlled via App Store Connect / Google Play Console. The platform's rollout_percentage mirrors the external setting for dashboard accuracy.
13. **TestFlight / Internal Testing channels** — `testflight` (iOS) and `internal_test` (Android) channels are available for beta testing; only stores flagged as beta testers receive these in the update check response, same logic as the `beta` channel.
14. **Mobile download URL** — for ios/android platforms, `download_url` contains the direct store link (e.g. `itms-apps://` for iOS, `market://` for Android) and `store_url` contains the web-accessible listing URL. Desktop releases use DigitalOcean Spaces/CDN URLs in `download_url` and `store_url` is NULL.
15. **Build number matching** — mobile update checks include `build_number` (CFBundleVersion / versionCode) for precise version comparison, since mobile versioning may differ from semver display versions.
