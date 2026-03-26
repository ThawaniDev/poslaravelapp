# Staff & User Management — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Staff Accounts, Shifts & Attendance, Clock-In/Out, Commissions, Training Mode  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · Laravel 11 · Biometric Auth  

---

## 1. Feature Overview

Staff & User Management handles the complete lifecycle of store employees — from onboarding to daily attendance tracking, shift scheduling, performance monitoring, and commission calculation. It integrates tightly with Roles & Permissions for access control and with POS Terminal for cashier session management.

### What This Feature Does
- **Staff profiles** — full employee record: name, photo, contact, hire date, national ID, employment type
- **Clock-in / Clock-out** — staff authenticate via PIN, NFC badge, or biometric to start/end shifts
- **Shift scheduling** — define recurring shifts per staff member; track actual vs scheduled hours
- **Break tracking** — clock-in/out for breaks; overtime calculation considers breaks
- **Time & attendance reports** — late arrivals, early departures, total hours, overtime
- **Commission rules** — percentage-based or tiered commissions on sales per staff member
- **Training mode** — sandbox POS mode where new staff practice without affecting real data
- **Multi-branch staff** — staff can be assigned to multiple branches with different roles per branch
- **Staff activity log** — all POS actions tied to the staff member who performed them
- **Payroll export** — generate attendance summary for external payroll systems (CSV/Excel)

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Roles & Permissions** | Role assignment for each staff member |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Cashier login, fast user switching |
| **Payments & Finance** | Commission calculation, cash session assignment |
| **Reports & Analytics** | Staff performance reports, sales per cashier |
| **Notifications** | Clock-in/out notifications, shift reminders |
| **Order Management** | Order attributed to serving cashier |
| **Customer Management** | Sales attribution for loyalty/commission |

### Features to Review After Changing This Feature
1. **POS Terminal** — fast user switch flow
2. **Roles & Permissions** — role assignment during staff creation
3. **Reports & Analytics** — staff performance report fields

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **drift** | SQLite ORM — local staff records, attendance logs |
| **riverpod** / **flutter_bloc** | State management for current user, shift state |
| **local_auth** | Biometric (fingerprint, face) authentication on supported devices |
| **nfc_manager** | NFC badge tap for clock-in/out |
| **flutter_secure_storage** | Staff PIN hashes, session tokens |
| **dio** | HTTP client for staff management API |
| **excel** / **csv** | Payroll export file generation |
| **image_picker** | Staff photo capture/upload |

### 3.2 Technologies
- **PIN authentication** — 4-6 digit PIN per staff; bcrypt hashed; verified locally for offline support
- **NFC badge** — NFC tag UID mapped to staff member for contactless clock-in
- **Biometric auth** — Windows Hello or USB fingerprint scanner via `local_auth`
- **Fast user switch** — POS returns to lock screen; new cashier taps badge or enters PIN; sub-second switch
- **Training mode isolation** — separate SQLite database file for training; no sync to cloud
- **Multi-branch** — `staff_branch_assignments` pivot table; user sees only assigned branches

---

## 4. Screens

### 4.1 Staff List Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/staff` |
| **Purpose** | View all staff members |
| **Layout** | Data table — photo, name, role, branch(es), status (active/inactive), last clock-in |
| **Actions** | Add Staff, Edit, Deactivate, View Attendance, View Activity Log |
| **Filters** | Branch, Role, Status (Active / Inactive / On Leave) |
| **Access** | `staff.view` permission |

### 4.2 Staff Profile / Edit Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/staff/{id}` |
| **Purpose** | View and edit staff details |
| **Sections** | Personal Info (name, photo, phone, email, national ID), Employment (hire date, employment type, salary type), Role Assignment (per branch), Commission Config, PIN Management |
| **PIN Management** | Set / Reset PIN, Enable/Disable biometric, Assign NFC badge UID |
| **Access** | `staff.edit` permission |

### 4.3 Clock-In / Clock-Out Screen
| Field | Detail |
|---|---|
| **Route** | POS lock screen overlay or dedicated `/clock` route |
| **Purpose** | Staff authenticate to start/end shift |
| **Layout** | Large clock display, staff name/photo upon auth, buttons: Clock In, Clock Out, Start Break, End Break |
| **Auth Methods** | PIN pad, NFC badge tap, Biometric button |
| **Access** | All staff |

### 4.4 Shift Schedule Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/shifts` |
| **Purpose** | Define and view weekly shift schedules |
| **Layout** | Calendar/grid view — rows = staff, columns = days; drag to assign shifts; color-coded by shift type (morning, afternoon, evening) |
| **Actions** | Create Shift Template, Assign Shift, Swap Shift, View Conflicts |
| **Access** | `staff.manage_shifts` permission |

### 4.5 Attendance Report Screen
| Field | Detail |
|---|---|
| **Route** | `/reports/attendance` |
| **Purpose** | View attendance summary |
| **Layout** | Data table — staff name, scheduled hours, actual hours, overtime, late count, absence count |
| **Filters** | Date range, Branch, Staff member |
| **Export** | CSV / Excel for payroll |
| **Access** | `reports.attendance` permission |

### 4.6 Training Mode Screen
| Field | Detail |
|---|---|
| **Route** | `/training` |
| **Purpose** | Sandbox POS environment for new staff |
| **Layout** | Full POS interface with "TRAINING MODE" watermark banner; all actions are non-persistent |
| **Data** | Uses sample product catalog and mock payment methods |
| **Access** | `staff.training_mode` permission |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/staff` | GET | List all staff for store | Bearer token, `staff.view` |
| `POST /api/staff` | POST | Create staff member | Bearer token, `staff.create` |
| `GET /api/staff/{id}` | GET | Get staff profile | Bearer token, `staff.view` |
| `PUT /api/staff/{id}` | PUT | Update staff profile | Bearer token, `staff.edit` |
| `DELETE /api/staff/{id}` | DELETE | Deactivate staff (soft delete) | Bearer token, `staff.delete` |
| `POST /api/staff/{id}/pin` | POST | Set / reset staff PIN | Bearer token, `staff.manage_pin` |
| `POST /api/staff/{id}/nfc` | POST | Register NFC badge UID | Bearer token, `staff.edit` |
| `GET /api/attendance` | GET | Attendance records (paginated) | Bearer token, `reports.attendance` |
| `POST /api/attendance/clock` | POST | Clock in/out/break (from POS) | Bearer token |
| `GET /api/shifts` | GET | List shift schedules | Bearer token, `staff.view` |
| `POST /api/shifts` | POST | Create/assign shift | Bearer token, `staff.manage_shifts` |
| `PUT /api/shifts/{id}` | PUT | Update shift | Bearer token, `staff.manage_shifts` |
| `GET /api/staff/{id}/commissions` | GET | Commission summary for staff | Bearer token, `finance.commissions` |
| `PUT /api/staff/{id}/commission-config` | PUT | Set commission rules for staff | Bearer token, `staff.edit` |
| `GET /api/staff/{id}/activity-log` | GET | Activity log for staff member | Bearer token, `staff.view` |
| `GET /api/attendance/export` | GET | Export attendance as CSV/Excel | Bearer token, `reports.attendance` |
| `GET /api/sync/staff` | GET | Full staff data for offline sync | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `StaffRepository` | Local Drift storage for staff records; CRUD + sync |
| `AuthenticationService` | PIN / NFC / Biometric verification for clock-in and POS login |
| `ClockService` | Manages clock-in/out/break state; persists locally and syncs to cloud |
| `ShiftScheduleService` | Local shift schedule management; conflict detection |
| `CommissionCalculator` | Calculates commissions based on sales data and commission rules |
| `TrainingModeService` | Manages training database isolation; switches between real and training DB |
| `FastUserSwitchService` | Handles sub-second cashier switching; caches previous user state |
| `AttendanceExportService` | Generates CSV/Excel payroll files from attendance data |
| `StaffActivityLogger` | Records all POS actions with staff user ID attribution |

---

## 6. Full Database Schema

### 6.1 Tables

#### `staff_users`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | Primary store |
| first_name | VARCHAR(100) | NOT NULL | |
| last_name | VARCHAR(100) | NOT NULL | |
| email | VARCHAR(255) | NULLABLE, UNIQUE | |
| phone | VARCHAR(20) | NULLABLE | |
| photo_url | VARCHAR(500) | NULLABLE | |
| national_id | VARCHAR(50) | NULLABLE | |
| pin_hash | VARCHAR(255) | NOT NULL | bcrypt hash of PIN |
| nfc_badge_uid | VARCHAR(50) | NULLABLE, UNIQUE | |
| biometric_enabled | BOOLEAN | DEFAULT FALSE | |
| employment_type | VARCHAR(20) | NOT NULL | full_time, part_time, contractor |
| salary_type | VARCHAR(20) | NOT NULL | hourly, monthly, commission_only |
| hourly_rate | DECIMAL(10,2) | NULLABLE | |
| hire_date | DATE | NOT NULL | |
| termination_date | DATE | NULLABLE | |
| status | VARCHAR(20) | DEFAULT 'active' | active, inactive, on_leave |
| language_preference | VARCHAR(5) | DEFAULT 'ar' | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE staff_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    photo_url VARCHAR(500),
    national_id VARCHAR(50),
    pin_hash VARCHAR(255) NOT NULL,
    nfc_badge_uid VARCHAR(50) UNIQUE,
    biometric_enabled BOOLEAN DEFAULT FALSE,
    employment_type VARCHAR(20) NOT NULL DEFAULT 'full_time',
    salary_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
    hourly_rate DECIMAL(10,2),
    hire_date DATE NOT NULL DEFAULT CURRENT_DATE,
    termination_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    language_preference VARCHAR(5) DEFAULT 'ar',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `staff_branch_assignments`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| branch_id | UUID | FK → stores(id), NOT NULL | Branch/location |
| role_id | BIGINT | FK → roles(id), NOT NULL | Role at this branch |
| is_primary | BOOLEAN | DEFAULT FALSE | Primary branch |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE staff_branch_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    branch_id UUID NOT NULL REFERENCES stores(id),
    role_id BIGINT NOT NULL REFERENCES roles(id),
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(staff_user_id, branch_id)
);
```

#### `attendance_records`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| clock_in_at | TIMESTAMP | NOT NULL | |
| clock_out_at | TIMESTAMP | NULLABLE | NULL if still clocked in |
| break_minutes | INTEGER | DEFAULT 0 | Total break time in minutes |
| scheduled_shift_id | UUID | FK → shift_schedules(id), NULLABLE | Linked scheduled shift |
| overtime_minutes | INTEGER | DEFAULT 0 | Calculated overtime |
| notes | TEXT | NULLABLE | Manager notes |
| auth_method | VARCHAR(20) | NOT NULL | pin, nfc, biometric |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE attendance_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    clock_in_at TIMESTAMP NOT NULL,
    clock_out_at TIMESTAMP,
    break_minutes INTEGER DEFAULT 0,
    scheduled_shift_id UUID,
    overtime_minutes INTEGER DEFAULT 0,
    notes TEXT,
    auth_method VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `break_records`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| attendance_record_id | UUID | FK → attendance_records(id), NOT NULL | |
| break_start | TIMESTAMP | NOT NULL | |
| break_end | TIMESTAMP | NULLABLE | NULL if currently on break |

```sql
CREATE TABLE break_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    attendance_record_id UUID NOT NULL REFERENCES attendance_records(id) ON DELETE CASCADE,
    break_start TIMESTAMP NOT NULL,
    break_end TIMESTAMP
);
```

#### `shift_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| name | VARCHAR(100) | NOT NULL | e.g., "Morning Shift" |
| start_time | TIME | NOT NULL | |
| end_time | TIME | NOT NULL | |
| color | VARCHAR(7) | DEFAULT '#4CAF50' | Hex color for calendar display |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE shift_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    color VARCHAR(7) DEFAULT '#4CAF50',
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `shift_schedules`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| shift_template_id | UUID | FK → shift_templates(id), NOT NULL | |
| date | DATE | NOT NULL | |
| actual_start | TIMESTAMP | NULLABLE | Filled from attendance |
| actual_end | TIMESTAMP | NULLABLE | |
| status | VARCHAR(20) | DEFAULT 'scheduled' | scheduled, completed, missed, swapped |
| swapped_with_id | UUID | FK → staff_users(id), NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE shift_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    shift_template_id UUID NOT NULL REFERENCES shift_templates(id),
    date DATE NOT NULL,
    actual_start TIMESTAMP,
    actual_end TIMESTAMP,
    status VARCHAR(20) DEFAULT 'scheduled',
    swapped_with_id UUID REFERENCES staff_users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(staff_user_id, date, shift_template_id)
);
```

#### `commission_rules`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| staff_user_id | UUID | FK → staff_users(id), NULLABLE | NULL = store-wide default |
| type | VARCHAR(20) | NOT NULL | flat_percentage, tiered, per_item |
| percentage | DECIMAL(5,2) | NULLABLE | For flat_percentage: e.g., 2.50 |
| tiers_json | JSONB | NULLABLE | For tiered: [{"min": 0, "max": 1000, "pct": 1}, {"min": 1001, "max": 5000, "pct": 2}] |
| product_category_id | UUID | FK → categories(id), NULLABLE | If commission varies by category |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE commission_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    staff_user_id UUID REFERENCES staff_users(id),
    type VARCHAR(20) NOT NULL DEFAULT 'flat_percentage',
    percentage DECIMAL(5,2),
    tiers_json JSONB,
    product_category_id UUID,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `commission_earnings`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| order_id | UUID | FK → orders(id), NOT NULL | |
| commission_rule_id | UUID | FK → commission_rules(id), NOT NULL | |
| order_total | DECIMAL(12,3) | NOT NULL | |
| commission_amount | DECIMAL(12,3) | NOT NULL | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE commission_earnings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    commission_rule_id UUID NOT NULL REFERENCES commission_rules(id),
    order_total DECIMAL(12,3) NOT NULL,
    commission_amount DECIMAL(12,3) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `staff_activity_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| action | VARCHAR(100) | NOT NULL | e.g., "create_order", "void_item", "apply_discount" |
| entity_type | VARCHAR(50) | NULLABLE | order, product, customer |
| entity_id | UUID | NULLABLE | |
| details | JSONB | NULLABLE | Context-specific data |
| ip_address | VARCHAR(45) | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE staff_activity_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    details JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `training_sessions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| started_at | TIMESTAMP | NOT NULL | |
| ended_at | TIMESTAMP | NULLABLE | |
| transactions_count | INTEGER | DEFAULT 0 | Number of practice transactions |
| notes | TEXT | NULLABLE | Manager notes on performance |

```sql
CREATE TABLE training_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    store_id UUID NOT NULL REFERENCES stores(id),
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    ended_at TIMESTAMP,
    transactions_count INTEGER DEFAULT 0,
    notes TEXT
);
```

#### `staff_documents`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| staff_user_id | UUID | FK → staff_users(id), NOT NULL | |
| document_type | VARCHAR(50) | NOT NULL | national_id, contract, certificate, visa |
| file_url | VARCHAR(500) | NOT NULL | |
| expiry_date | DATE | NULLABLE | For documents that expire |
| uploaded_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE staff_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    staff_user_id UUID NOT NULL REFERENCES staff_users(id),
    document_type VARCHAR(50) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    expiry_date DATE,
    uploaded_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `staff_store_status` | (store_id, status) | B-TREE | Active staff per store |
| `staff_nfc` | nfc_badge_uid | B-TREE UNIQUE | NFC badge lookup |
| `attendance_staff_date` | (staff_user_id, clock_in_at) | B-TREE | Attendance queries |
| `attendance_store_date` | (store_id, clock_in_at) | B-TREE | Store-wide attendance |
| `shift_schedule_date` | (store_id, date) | B-TREE | Daily schedule view |
| `commission_earnings_staff` | (staff_user_id, created_at) | B-TREE | Commission reports |
| `activity_log_staff_date` | (staff_user_id, created_at) | B-TREE | Activity log queries |
| `activity_log_store_date` | (store_id, created_at) | B-TREE | Store-wide activity |
| `staff_branch_staff` | staff_user_id | B-TREE | Branches per staff |
| `staff_documents_staff` | staff_user_id | B-TREE | Documents per staff |
| `staff_documents_expiry` | expiry_date | B-TREE | Expiring document alerts |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ staff_users
staff_users ──1:N──▶ staff_branch_assignments ◀──N:1── stores (branches)
staff_users ──1:N──▶ attendance_records
attendance_records ──1:N──▶ break_records
stores ──1:N──▶ shift_templates
staff_users ──1:N──▶ shift_schedules ◀──N:1── shift_templates
stores ──1:N──▶ commission_rules
staff_users ──1:N──▶ commission_earnings ◀──N:1── orders
staff_users ──1:N──▶ staff_activity_log
staff_users ──1:N──▶ training_sessions
staff_users ──1:N──▶ staff_documents
```

---

## 7. Business Rules

1. **Unique PIN per store** — no two staff members in the same store can have the same PIN (validated on set/reset)
2. **Clock-in required before POS access** — a staff member must be clocked in to process transactions; POS blocks access otherwise
3. **Auto clock-out** — if a staff member does not clock out and 16 hours have passed since clock-in, the system auto-clocks out with a flag for manager review
4. **Break deduction** — overtime is calculated as (actual_hours - scheduled_hours - break_minutes); negative values indicate under-hours
5. **Commission on completed orders only** — commissions are calculated only for orders with status `completed`; refunded orders deduct the commission
6. **Training mode isolation** — training mode uses a separate local SQLite database; no data syncs to cloud; a "TRAINING MODE" watermark is shown on all screens
7. **Fast user switch** — when switching users on POS, the current user's session is suspended (not ended); the new user's session begins; the suspended session can be resumed
8. **Shift conflict detection** — the system prevents assigning overlapping shifts to the same staff member
9. **Minimum staffing alert** — if a branch has fewer than the configured minimum staff scheduled for a day, a notification is sent to the Branch Manager
10. **Document expiry alerts** — 30 days before a staff document (visa, certificate) expires, a notification is sent to the Owner
11. **Deactivation cascade** — deactivating a staff member: revokes POS access, ends active clock-in, removes from future shift schedules, disables FCM tokens; does not delete historical data
12. **Activity log immutability** — records in `staff_activity_log` cannot be edited or deleted; they are append-only

---

## 8. Payroll & Wage Protection System (Mudad) Scope

### 8.1 POS System Responsibilities

The POS system handles **time and attendance tracking** and **commission calculation**:

| Function | POS Responsibility | External Responsibility |
|---|---|---|
| **Clock in/out** | ✅ Records timestamps | — |
| **Break tracking** | ✅ Records break duration | — |
| **Shift scheduling** | ✅ Creates and manages | — |
| **Hours worked calculation** | ✅ Calculates automatically | — |
| **Commission calculation** | ✅ Based on sales data | — |
| **Payroll export** | ✅ Generates CSV/Excel | — |
| **Actual salary payment** | ❌ | External payroll system |
| **Bank transfer to employee** | ❌ | Employer's bank |
| **Mudad compliance** | ❌ | Payroll system + bank |
| **GOSI/Social insurance** | ❌ | Payroll system |
| **Tax withholding** | ❌ | Payroll system |

### 8.2 Integration with External Payroll Systems

The POS generates payroll-ready exports that can be imported into external systems:

**Export Format: Attendance Summary (CSV)**
```csv
employee_id,employee_name,national_id,start_date,end_date,scheduled_hours,actual_hours,overtime_hours,breaks_mins,late_mins,early_departure_mins,days_present,days_absent
ST001,Ahmed Al-Ahmad,1012345678,2026-03-01,2026-03-31,176.00,182.50,6.50,450,30,0,22,0
```

**Export Format: Commission Summary (CSV)**
```csv
employee_id,employee_name,period_start,period_end,total_sales,commission_rate,commission_earned,adjustments,net_commission
ST001,Ahmed Al-Ahmad,2026-03-01,2026-03-31,45000.00,0.03,1350.00,0.00,1350.00
```

### 8.3 Mudad (Wage Protection System) Clarification

> **Mudad is out of scope for the POS system.**

Mudad is Saudi Arabia's Wage Protection System that requires employers to pay salaries through approved banking channels. Compliance with Mudad requires:

1. **Bank integration** — Salary file transfer to bank in WPS format
2. **GOSI registration** — Employee social insurance enrollment
3. **Ministry of Labor reporting** — Employment contract registration

These functions are handled by:
- **Dedicated payroll software** (e.g., Qoyod Payroll, Zoho Payroll, SAP SuccessFactors)
- **Bank payroll services** (most Saudi banks offer WPS-compliant salary disbursement)
- **HR management systems** with Mudad integration

### 8.4 Recommended Workflow

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   POS System    │    │ Payroll System  │    │ Bank / Mudad    │
└────────┬────────┘    └────────┬────────┘    └────────┬────────┘
         │                      │                      │
         │ Export attendance    │                      │
         │ + commissions (CSV)  │                      │
         │─────────────────────▶│                      │
         │                      │ Calculate final pay  │
         │                      │ + deductions (GOSI)  │
         │                      │                      │
         │                      │ Generate WPS file    │
         │                      │─────────────────────▶│
         │                      │                      │
         │                      │   Mudad-compliant    │
         │                      │    salary payment    │
         │                      │◀─────────────────────│
```

### 8.5 Future Integration Considerations

If direct Mudad integration is required in the future, it would involve:

1. **Bank API integration** — Connect to bank's WPS API (each bank has different APIs)
2. **GOSI API integration** — For social insurance calculations
3. **Employee bank account storage** — Securely store IBAN for direct deposit
4. **Tax calculation engine** — For expat tax or future income tax

This would be a separate "Payroll Integration" feature, not part of the core POS Staff Management module.
