# POS System Brainstorm - Standalone Product for Saudi Market

## 🎯 Vision Statement
**Build a complete, standalone POS system for Saudi supermarkets that works online/offline, as desktop app and web, with ZATCA compliance built-in, and optional integration with Thawani delivery platform.**

---

## �️ Technology Decision: Flutter Desktop

> **CONFIRMED TECHNOLOGY STACK:**
> - **Desktop App**: Flutter Desktop (Windows primary)
> - **Tablet/Mobile Apps**: Flutter (iOS, Android)
> - **State Management**: Riverpod or Bloc
> - **Local Database**: Drift (SQLite ORM)
> - **Printing**: esc_pos_printer, flutter_thermal_printer
> - **ZATCA Crypto**: pointycastle (ECDSA, SHA-256)
> - **HTTP Client**: Dio
> - **Backend API**: Laravel (existing Thawani infrastructure)
> - **Super Admin Panel**: Laravel + Filament (internal Thawani management)
> - **Store Owner Dashboard**: Laravel + Livewire OR Flutter Web
> - **Sync**: REST API with offline queue (not WebSocket - low volume ~50 updates/day)
> 
> ⚠️ **No Next.js** - Using only technologies the team already knows (Flutter + Laravel)

---

## 📋 Table of Contents
1. [Product Vision & Scope](#product-vision--scope)
2. [Market Analysis](#market-analysis)
3. [Technical Architecture](#technical-architecture)
4. [Offline-First Design](#offline-first-design)
5. [Desktop App Technologies](#desktop-app-technologies)
6. [ZATCA Phase 2 Compliance](#zatca-phase-2-compliance)
7. [Core Features](#core-features)
8. [Industry-Specific POS Views](#industry-specific-pos-views)
9. [Integration APIs](#integration-apis)
10. [Super Admin Panel](#super-admin-panel-thawani-internal)
11. [🏷️ Barcode Label Printing](#barcode-label-printing)
12. [� Third-Party Delivery Platform Integrations](#third-party-delivery-platform-integrations)
13. [🔔 Full Notifications Settings](#full-notifications-settings)
14. [🖐️ POS View Customization (Handedness, Fonts, Themes)](#pos-view-customization-handedness-fonts-themes)
15. [📦 Packages & Subscription Management](#packages--subscription-management)
16. [🛡️ Full Roles, Permissions & User Management](#full-roles-permissions--user-management)
17. [🔐 Security Architecture](#security-architecture)
18. [🧪 Testing Strategy](#testing-strategy)
19. [🔧 Error Handling & Recovery](#error-handling--recovery)
20. [💾 Backup & Disaster Recovery](#backup--disaster-recovery)
21. [🌍 Localization & i18n](#localization--i18n)
22. [⚡ Performance Optimization](#performance-optimization)
23. [👥 Customer Features](#customer-features)
24. [🚀 Deployment & Auto-Updates](#deployment--auto-updates)
25. [♿ Accessibility](#accessibility)
26. [💰 Business Model](#business-model)
27. [Build vs Open Source](#build-vs-open-source)
28. [Technology Stack Decision](#technology-stack-decision)
29. [☁️ Cloud Infrastructure & Scaling Strategy](#cloud-infrastructure--scaling-strategy)
30. [Implementation Roadmap](#implementation-roadmap)
31. [Cost & Resource Planning](#cost--resource-planning)
32. [💳 SoftPOS & NFC Payment Integration (NearPay)](#softpos--nfc-payment-integration-nearpay)

---

## 🎯 Product Vision & Scope

### What We're Building
A **complete, commercial POS system** for Saudi supermarkets that can be:
- Sold as a standalone product
- Licensed to stores (SaaS or perpetual)
- Integrated with Thawani for delivery orders
- Integrated with other delivery platforms

### Key Requirements

| Requirement | Description |
|-------------|-------------|
| **Desktop App** | Native Windows/Mac application for cashier terminals |
| **Web App** | Browser-based for management, reports, and backup access |
| **Offline Mode** | Full functionality without internet |
| **Online Sync** | Real-time sync when connected |
| **ZATCA Compliant** | Phase 2 e-invoicing for KSA |
| **Multi-Store** | Support chain stores with central management |
| **Arabic/English** | Full RTL support |
| **Thawani Integration** | Connect delivery orders to POS |

### Product Positioning
```
┌─────────────────────────────────────────────────────────────────┐
│                     THAWANI POS                                 │
│              "Saudi's Complete Retail Solution"                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐           │
│  │  DESKTOP    │   │    WEB      │   │   MOBILE    │           │
│  │    APP      │   │   PORTAL    │   │    APP      │           │
│  │ (Cashier)   │   │ (Manager)   │   │ (Inventory) │           │
│  └──────┬──────┘   └──────┬──────┘   └──────┬──────┘           │
│         │                 │                 │                   │
│         └─────────────────┼─────────────────┘                   │
│                           │                                     │
│                    ┌──────▼──────┐                              │
│                    │  SYNC HUB   │                              │
│                    │  (Local +   │                              │
│                    │   Cloud)    │                              │
│                    └──────┬──────┘                              │
│                           │                                     │
│         ┌─────────────────┼─────────────────┐                   │
│         │                 │                 │                   │
│    ┌────▼────┐      ┌─────▼─────┐     ┌────▼────┐              │
│    │  ZATCA  │      │  THAWANI  │     │  OTHER  │              │
│    │   API   │      │   API     │     │  APIs   │              │
│    └─────────┘      └───────────┘     └─────────┘              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📊 Market Analysis

### Saudi POS Market Opportunity

#### Market Size
- **400,000+** retail outlets in Saudi Arabia
- **~50,000** supermarkets and grocery stores
- **70%** still using basic/outdated POS systems
- **100%** must comply with ZATCA by 2025-2026

#### Competition Analysis

| Competitor | Strengths | Weaknesses | Price (SAR/month) |
|------------|-----------|------------|-------------------|
| **Foodics** | Well-known, restaurant-focused | Expensive, complex | 500-2000 |
| **Marn** | Local, ZATCA ready | Basic features | 200-500 |
| **Salla POS** | E-commerce integration | Limited offline | 300-800 |
| **Odoo** | Full ERP | Complex, needs customization | 400-1500 |
| **Qoyod** | Accounting focused | Not retail-optimized | 150-400 |

#### Our Competitive Advantage
1. **Built for Saudi Supermarkets** - Not adapted, designed from scratch
2. **True Offline Mode** - Works without internet (critical for many areas)
3. **ZATCA Native** - Phase 2 from day one
4. **Thawani Integration** - Delivery orders built-in
5. **Affordable** - Target: 150-400 SAR/month

### Target Customer Segments

```
Segment 1: Small Groceries (بقالة)
├── 1-2 registers
├── < 1000 SKUs
├── Price: 150 SAR/month
└── Volume: ~30,000 stores

Segment 2: Mini Markets
├── 2-5 registers
├── 1000-5000 SKUs
├── Price: 300 SAR/month
└── Volume: ~15,000 stores

Segment 3: Supermarkets
├── 5-20 registers
├── 5000-30,000 SKUs
├── Price: 500-1500 SAR/month
└── Volume: ~4,000 stores

Segment 4: Chains
├── Multi-location
├── Central management
├── Custom pricing
└── Volume: ~500 chains
```

---

## 🏗️ Technical Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐     │
│  │  DESKTOP APP    │  │    WEB APP      │  │   MOBILE APP    │     │
│  │   (Flutter)     │  │(Laravel+Livewire│  │   (Flutter)     │     │
│  │                 │  │  or Flutter Web)│  │                 │     │
│  │                 │  │                 │  │                 │     │
│  │                 │  │                 │  │                 │     │
│  │ • Cashier UI    │  │ • Dashboard     │  │ • Stock check   │     │
│  │ • Barcode scan  │  │ • Reports       │  │ • Price lookup  │     │
│  │ • Receipt print │  │ • Product mgmt  │  │ • Inventory     │     │
│  │ • Offline mode  │  │ • User mgmt     │  │ • Receive goods │     │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘     │
│           │                    │                    │               │
│           └────────────────────┼────────────────────┘               │
│                                │                                    │
└────────────────────────────────┼────────────────────────────────────┘
                                 │
┌────────────────────────────────┼────────────────────────────────────┐
│                         DATA LAYER                                  │
├────────────────────────────────┼────────────────────────────────────┤
│                                │                                    │
│    ┌───────────────────────────▼───────────────────────────┐       │
│    │              LOCAL DATABASE (SQLite)                   │       │
│    │   • Products, Prices, Stock                           │       │
│    │   • Transactions (offline queue)                      │       │
│    │   • ZATCA invoices (pending sync)                     │       │
│    │   • User sessions                                     │       │
│    └───────────────────────────┬───────────────────────────┘       │
│                                │                                    │
│                         SYNC ENGINE                                 │
│                    (Bidirectional Sync)                            │
│                                │                                    │
│    ┌───────────────────────────▼───────────────────────────┐       │
│    │              CLOUD DATABASE (PostgreSQL)               │       │
│    │   • Master data                                       │       │
│    │   • Centralized reporting                             │       │
│    │   • Multi-store sync                                  │       │
│    └───────────────────────────────────────────────────────┘       │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
                                 │
┌────────────────────────────────┼────────────────────────────────────┐
│                     INTEGRATION LAYER                               │
├────────────────────────────────┼────────────────────────────────────┤
│                                │                                    │
│    ┌──────────┐  ┌─────────────▼───────────┐  ┌──────────┐         │
│    │  ZATCA   │  │     API GATEWAY         │  │ THAWANI  │         │
│    │  E-INV   │◄─┤  (Authentication,       ├─►│   API    │         │
│    │  API     │  │   Rate Limiting,        │  │          │         │
│    └──────────┘  │   Request Routing)      │  └──────────┘         │
│                  └─────────────────────────┘                        │
│                           │                                         │
│              ┌────────────┼────────────┐                           │
│              │            │            │                           │
│         ┌────▼────┐  ┌────▼────┐  ┌────▼────┐                      │
│         │ Payment │  │ Loyalty │  │ Other   │                      │
│         │ Gateway │  │ Program │  │ Delivery│                      │
│         └─────────┘  └─────────┘  └─────────┘                      │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Database Schema (Core Tables)

```sql
-- =====================================================
-- STORE & ORGANIZATION
-- =====================================================

-- Organizations (for multi-store chains)
CREATE TABLE organizations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    tax_number VARCHAR(20) NOT NULL, -- VAT Registration
    cr_number VARCHAR(20), -- Commercial Registration
    logo_url TEXT,
    settings JSONB DEFAULT '{}',
    subscription_plan VARCHAR(50),
    subscription_expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Stores/Branches
CREATE TABLE stores (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id),
    code VARCHAR(20) UNIQUE NOT NULL, -- Store code: STR-001
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    address TEXT,
    address_ar TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    phone VARCHAR(20),
    email VARCHAR(255),
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    timezone VARCHAR(50) DEFAULT 'Asia/Riyadh',
    is_active BOOLEAN DEFAULT TRUE,
    settings JSONB DEFAULT '{}',
    -- ZATCA specific
    zatca_device_id VARCHAR(100),
    zatca_otp VARCHAR(10),
    zatca_csr TEXT,
    zatca_compliance_request_id VARCHAR(100),
    zatca_production_csid TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- POS Terminals/Registers
CREATE TABLE registers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    register_number VARCHAR(20) NOT NULL,
    name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP,
    -- Hardware info
    device_id VARCHAR(255), -- Unique device identifier
    device_type VARCHAR(50), -- desktop, tablet
    os_info VARCHAR(100),
    app_version VARCHAR(20),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, register_number)
);

-- =====================================================
-- USERS & PERMISSIONS
-- =====================================================

CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id),
    store_id UUID REFERENCES stores(id), -- NULL = org-level access
    employee_id VARCHAR(50),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL, -- bcrypt/argon2 hashed
    pin_hash VARCHAR(255), -- PBKDF2 hashed PIN for quick register login
    role VARCHAR(50) NOT NULL, -- owner, manager, cashier, inventory_clerk
    permissions JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    -- Security fields
    two_factor_secret VARCHAR(255), -- TOTP secret (encrypted)
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP,
    password_changed_at TIMESTAMP,
    force_password_change BOOLEAN DEFAULT FALSE,
    last_login_at TIMESTAMP,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- =====================================================
-- PRODUCTS & INVENTORY
-- =====================================================

-- Product Categories
CREATE TABLE categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id),
    parent_id UUID REFERENCES categories(id),
    code VARCHAR(50),
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    image_url TEXT,
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Products
CREATE TABLE products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID REFERENCES organizations(id),
    category_id UUID REFERENCES categories(id),
    sku VARCHAR(100), -- Internal SKU
    barcode VARCHAR(100), -- Primary barcode (EAN-13, UPC, etc.)
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255),
    description TEXT,
    description_ar TEXT,
    unit VARCHAR(50) DEFAULT 'piece', -- piece, kg, liter
    -- Pricing
    cost_price DECIMAL(12, 3) DEFAULT 0, -- Purchase cost
    sell_price DECIMAL(12, 3) NOT NULL, -- Selling price
    tax_rate DECIMAL(5, 2) DEFAULT 15.00, -- VAT rate (15% in KSA)
    is_tax_inclusive BOOLEAN DEFAULT TRUE, -- Price includes VAT
    -- Stock
    track_stock BOOLEAN DEFAULT TRUE,
    min_stock_level INTEGER DEFAULT 0,
    -- Flags
    is_active BOOLEAN DEFAULT TRUE,
    is_weighable BOOLEAN DEFAULT FALSE, -- Sold by weight
    allow_decimal_qty BOOLEAN DEFAULT FALSE,
    -- Images
    image_url TEXT,
    thumbnail_url TEXT,
    -- Metadata
    brand VARCHAR(100),
    supplier_id UUID,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP, -- Soft delete
    -- Sync tracking
    sync_version BIGINT DEFAULT 1,
    last_synced_at TIMESTAMP
);

-- Product Barcodes (multiple barcodes per product)
CREATE TABLE product_barcodes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID REFERENCES products(id) ON DELETE CASCADE,
    barcode VARCHAR(100) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(barcode)
);

-- Store-specific pricing (override org-level)
CREATE TABLE store_prices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    product_id UUID REFERENCES products(id),
    sell_price DECIMAL(12, 3) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, product_id)
);

-- Inventory per store
CREATE TABLE inventory (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    product_id UUID REFERENCES products(id),
    quantity DECIMAL(12, 3) DEFAULT 0,
    reserved_quantity DECIMAL(12, 3) DEFAULT 0, -- For pending orders
    last_counted_at TIMESTAMP,
    last_received_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    sync_version BIGINT DEFAULT 1,
    UNIQUE(store_id, product_id)
);

-- Stock movements
CREATE TABLE stock_movements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    product_id UUID REFERENCES products(id),
    type VARCHAR(50) NOT NULL, -- sale, purchase, adjustment, transfer, return, damage
    quantity DECIMAL(12, 3) NOT NULL, -- Positive or negative
    quantity_before DECIMAL(12, 3),
    quantity_after DECIMAL(12, 3),
    reference_type VARCHAR(50), -- transaction, purchase_order, adjustment
    reference_id UUID,
    notes TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

-- =====================================================
-- SALES TRANSACTIONS
-- =====================================================

-- POS Sessions (shift management)
CREATE TABLE pos_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    register_id UUID REFERENCES registers(id),
    cashier_id UUID REFERENCES users(id),
    session_number VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'open', -- open, closed, suspended
    opened_at TIMESTAMP NOT NULL DEFAULT NOW(),
    closed_at TIMESTAMP,
    -- Cash management
    opening_cash DECIMAL(12, 2) DEFAULT 0,
    closing_cash DECIMAL(12, 2),
    expected_cash DECIMAL(12, 2),
    cash_difference DECIMAL(12, 2),
    -- Totals
    total_sales DECIMAL(12, 2) DEFAULT 0,
    total_returns DECIMAL(12, 2) DEFAULT 0,
    total_discounts DECIMAL(12, 2) DEFAULT 0,
    transaction_count INTEGER DEFAULT 0,
    -- Payment breakdown
    cash_payments DECIMAL(12, 2) DEFAULT 0,
    card_payments DECIMAL(12, 2) DEFAULT 0,
    other_payments DECIMAL(12, 2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Sales transactions
CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    -- Location
    organization_id UUID REFERENCES organizations(id),
    store_id UUID REFERENCES stores(id),
    register_id UUID REFERENCES registers(id),
    session_id UUID REFERENCES pos_sessions(id),
    -- Transaction info
    transaction_number VARCHAR(50) NOT NULL, -- TRX-001-20260122-0001
    type VARCHAR(20) NOT NULL, -- sale, return, void
    status VARCHAR(20) DEFAULT 'completed', -- pending, completed, voided
    -- Customer (optional)
    customer_id UUID,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(20),
    customer_tax_number VARCHAR(20), -- For B2B invoices
    -- External reference
    external_type VARCHAR(50), -- thawani_order, hungerstation, etc.
    external_id VARCHAR(100), -- Order ID from external system
    external_data JSONB, -- Full external order data
    -- Amounts
    subtotal DECIMAL(12, 2) NOT NULL,
    discount_amount DECIMAL(12, 2) DEFAULT 0,
    discount_type VARCHAR(20), -- percentage, fixed
    discount_reason VARCHAR(255),
    tax_amount DECIMAL(12, 2) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    -- ZATCA e-invoice
    zatca_uuid UUID,
    zatca_hash VARCHAR(64),
    zatca_qr_code TEXT,
    zatca_status VARCHAR(20), -- pending, reported, cleared, rejected
    zatca_response JSONB,
    zatca_invoice_type VARCHAR(20), -- simplified (B2C), standard (B2B)
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    voided_at TIMESTAMP,
    voided_by UUID REFERENCES users(id),
    void_reason VARCHAR(255),
    -- Sync
    is_synced BOOLEAN DEFAULT FALSE,
    synced_at TIMESTAMP,
    sync_version BIGINT DEFAULT 1,
    -- Created by
    created_by UUID REFERENCES users(id)
);

-- Transaction items
CREATE TABLE transaction_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID REFERENCES transactions(id) ON DELETE CASCADE,
    product_id UUID REFERENCES products(id),
    -- Snapshot at time of sale
    barcode VARCHAR(100),
    product_name VARCHAR(255) NOT NULL,
    product_name_ar VARCHAR(255),
    -- Quantities & pricing
    quantity DECIMAL(12, 3) NOT NULL,
    unit_price DECIMAL(12, 3) NOT NULL,
    cost_price DECIMAL(12, 3),
    discount_amount DECIMAL(12, 2) DEFAULT 0,
    tax_rate DECIMAL(5, 2) DEFAULT 15.00,
    tax_amount DECIMAL(12, 2) NOT NULL,
    line_total DECIMAL(12, 2) NOT NULL, -- After discount, before tax
    -- For returns
    is_returned BOOLEAN DEFAULT FALSE,
    returned_quantity DECIMAL(12, 3) DEFAULT 0,
    return_transaction_id UUID,
    -- Serial/batch tracking (if needed)
    serial_number VARCHAR(100),
    batch_number VARCHAR(100),
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Payments
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID REFERENCES transactions(id) ON DELETE CASCADE,
    method VARCHAR(50) NOT NULL, -- cash, card, wallet, bank_transfer
    amount DECIMAL(12, 2) NOT NULL,
    -- Cash specific
    cash_tendered DECIMAL(12, 2),
    change_given DECIMAL(12, 2),
    -- Card specific
    card_type VARCHAR(50), -- visa, mastercard, mada
    card_last_four VARCHAR(4),
    authorization_code VARCHAR(50),
    terminal_id VARCHAR(50),
    -- Reference
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- =====================================================
-- OFFLINE SYNC QUEUE
-- =====================================================

CREATE TABLE sync_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    register_id UUID REFERENCES registers(id),
    entity_type VARCHAR(50) NOT NULL, -- transaction, inventory, product
    entity_id UUID NOT NULL,
    action VARCHAR(20) NOT NULL, -- create, update, delete
    payload JSONB NOT NULL,
    priority INTEGER DEFAULT 5, -- 1-10, higher = more urgent
    status VARCHAR(20) DEFAULT 'pending', -- pending, processing, completed, failed
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 5,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    processed_at TIMESTAMP
);

-- =====================================================
-- INDEXES
-- =====================================================

CREATE INDEX idx_products_barcode ON products(barcode);
CREATE INDEX idx_products_organization ON products(organization_id);
CREATE INDEX idx_products_sync ON products(organization_id, sync_version);
CREATE INDEX idx_product_barcodes_barcode ON product_barcodes(barcode);
CREATE INDEX idx_inventory_store_product ON inventory(store_id, product_id);
CREATE INDEX idx_transactions_store_date ON transactions(store_id, created_at);
CREATE INDEX idx_transactions_external ON transactions(external_type, external_id);
CREATE INDEX idx_transactions_zatca ON transactions(zatca_status) WHERE zatca_status IS NOT NULL;
CREATE INDEX idx_sync_queue_pending ON sync_queue(status, priority) WHERE status = 'pending';
```

---

## 📴 Offline-First Design

### Why Offline-First is Critical

1. **Internet Reliability**: Many Saudi areas have unstable internet
2. **Business Continuity**: Store can't stop selling if internet goes down
3. **Speed**: Local operations are faster
4. **ZATCA Tolerance**: Phase 2 allows 24h reporting delay for B2C

### Offline Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     POS DESKTOP APP                             │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                    APPLICATION LAYER                      │  │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐        │  │
│  │  │ Sales   │ │Inventory│ │ Reports │ │ Sync    │        │  │
│  │  │ Module  │ │ Module  │ │ Module  │ │ Manager │        │  │
│  │  └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘        │  │
│  │       └───────────┴───────────┴───────────┘              │  │
│  │                          │                               │  │
│  └──────────────────────────┼───────────────────────────────┘  │
│                             │                                   │
│  ┌──────────────────────────▼───────────────────────────────┐  │
│  │                    DATA LAYER                             │  │
│  │                                                          │  │
│  │  ┌─────────────────────────────────────────────────┐    │  │
│  │  │              SQLite Database                     │    │  │
│  │  │                                                  │    │  │
│  │  │  • Products (full catalog)                      │    │  │
│  │  │  • Inventory (store-specific)                   │    │  │
│  │  │  • Transactions (local)                         │    │  │
│  │  │  • ZATCA invoices (pending)                     │    │  │
│  │  │  • Sync queue                                   │    │  │
│  │  │                                                  │    │  │
│  │  └─────────────────────────────────────────────────┘    │  │
│  │                          │                               │  │
│  │  ┌───────────────────────▼─────────────────────────┐    │  │
│  │  │            SYNC ENGINE                           │    │  │
│  │  │                                                  │    │  │
│  │  │  • Conflict Resolution (Last-Write-Wins +       │    │  │
│  │  │    Server Authority for master data)            │    │  │
│  │  │  • Delta Sync (only changes)                    │    │  │
│  │  │  • Retry Logic with Exponential Backoff        │    │  │
│  │  │  • Priority Queue (Transactions > Inventory)    │    │  │
│  │  │                                                  │    │  │
│  │  └───────────────────────┬─────────────────────────┘    │  │
│  │                          │                               │  │
│  └──────────────────────────┼───────────────────────────────┘  │
│                             │                                   │
└─────────────────────────────┼───────────────────────────────────┘
                              │
            ┌─────────────────▼─────────────────┐
            │         WHEN ONLINE               │
            │                                   │
            │   • Sync transactions            │
            │   • Download product updates     │
            │   • Upload inventory changes     │
            │   • Report to ZATCA              │
            │   • Receive Thawani orders       │
            │                                   │
            └───────────────────────────────────┘
```

### Sync Strategy

```javascript
// Sync Priority Order
const SYNC_PRIORITIES = {
    TRANSACTION: 1,      // Highest - money movement
    ZATCA_INVOICE: 2,    // Legal requirement
    INVENTORY: 3,        // Stock updates
    PRODUCT_UPDATE: 4,   // Price/info changes
    PRODUCT_NEW: 5,      // New products (can wait)
    SETTINGS: 6,         // Lowest priority
};

// Conflict Resolution Rules
const CONFLICT_RULES = {
    // Server wins for master data
    products: 'server_wins',
    categories: 'server_wins',
    users: 'server_wins',
    
    // Client wins for transactions (they happened locally)
    transactions: 'client_wins',
    
    // Last-write-wins for inventory (with merge logic)
    inventory: 'last_write_wins_with_merge',
};
```

### Offline Capabilities

| Feature | Online | Offline | Notes |
|---------|--------|---------|-------|
| Process Sales | ✅ | ✅ | Full functionality |
| Print Receipts | ✅ | ✅ | Local printing |
| ZATCA QR Code | ✅ | ✅ | Generated locally |
| Barcode Lookup | ✅ | ✅ | Local database |
| Price Check | ✅ | ✅ | Local cache |
| Stock Check | ✅ | ✅ | Local inventory |
| Returns | ✅ | ✅ | Local processing |
| New Products | ✅ | ❌ | Requires sync |
| Price Updates | ✅ | ❌ | Requires sync |
| ZATCA Reporting | ✅ | ⏳ | Queued for sync |
| Thawani Orders | ✅ | ❌ | Requires connection |
| Reports (local) | ✅ | ✅ | Store-level only |
| Reports (chain) | ✅ | ❌ | Requires connection |

---

## 💻 Desktop App Technologies

### Option 1: Electron (Recommended for Speed-to-Market)

**What is it?** JavaScript/HTML/CSS wrapped in Chromium + Node.js

```
┌─────────────────────────────────────────┐
│              ELECTRON APP               │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │         RENDERER PROCESS          │ │
│  │         (Chromium/React)          │ │
│  │                                    │ │
│  │  • POS UI                         │ │
│  │  • React/Vue components           │ │
│  │  • Local state management         │ │
│  └─────────────┬─────────────────────┘ │
│                │ IPC                    │
│  ┌─────────────▼─────────────────────┐ │
│  │          MAIN PROCESS             │ │
│  │           (Node.js)               │ │
│  │                                    │ │
│  │  • SQLite operations              │ │
│  │  • Hardware integration           │ │
│  │  • File system                    │ │
│  │  • System tray                    │ │
│  │  • Auto-updates                   │ │
│  └───────────────────────────────────┘ │
│                                         │
└─────────────────────────────────────────┘
```

**Pros:**
- ✅ Same codebase as web app (React/Vue)
- ✅ Fast development
- ✅ Rich ecosystem
- ✅ Good SQLite support (better-sqlite3)
- ✅ Easy hardware integration
- ✅ Cross-platform (Windows, Mac, Linux)

**Cons:**
- ❌ Large app size (~150-200MB)
- ❌ Higher memory usage
- ❌ Not truly native

**Popular Examples:** VS Code, Slack, Discord

---

### Option 2: Tauri (Recommended for Performance)

**What is it?** Rust backend + Web frontend (uses system WebView)

```
┌─────────────────────────────────────────┐
│               TAURI APP                 │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │           WEBVIEW                  │ │
│  │      (System WebView2/WebKit)     │ │
│  │                                    │ │
│  │  • React/Vue UI                   │ │
│  │  • Lightweight frontend           │ │
│  └─────────────┬─────────────────────┘ │
│                │ Commands              │
│  ┌─────────────▼─────────────────────┐ │
│  │          RUST CORE                │ │
│  │                                    │ │
│  │  • SQLite (rusqlite)              │ │
│  │  • System APIs                    │ │
│  │  • File handling                  │ │
│  │  • Cryptography (ZATCA signing)   │ │
│  │  • Serial ports (hardware)        │ │
│  └───────────────────────────────────┘ │
│                                         │
└─────────────────────────────────────────┘
```

**Pros:**
- ✅ Tiny app size (~10-30MB)
- ✅ Low memory usage
- ✅ Better performance
- ✅ More secure
- ✅ Native feel

**Cons:**
- ❌ Rust learning curve
- ❌ Smaller ecosystem
- ❌ Newer (less proven)
- ❌ WebView differences across OS

---

### Option 3: Flutter Desktop ⭐ RECOMMENDED

**What is it?** Dart framework, compiles to native code

```
┌────────────────────────────────────────┐
│            FLUTTER DESKTOP APP         │
│                                        │
│  ┌───────────────────────────────────┐ │
│  │          FLUTTER UI               │ │
│  │       (Native Rendering)          │ │
│  │                                   │ │
│  │  • POS Screen                     │ │
│  │  • Touch-optimized widgets        │ │
│  │  • RTL/Arabic support             │ │
│  │  • Hot reload development         │ │
│  └─────────────┬─────────────────────┘ │
│                │                       │
│  ┌─────────────▼─────────────────────┐ │
│  │         DART BUSINESS LOGIC       │ │
│  │                                   │ │
│  │  • Drift (SQLite ORM)             │ │
│  │  • ZATCA Service (pointycastle)   │ │
│  │  • Sync Engine                    │ │
│  │  • Printer Integration            │ │
│  └───────────────────────────────────┘ │
│                                        │
└────────────────────────────────────────┘
```

**Pros:**
- ✅ **You already use Flutter** (Thawani mobile apps)
- ✅ Single codebase for desktop + tablet + mobile
- ✅ Beautiful, touch-optimized UI
- ✅ Excellent Arabic/RTL support built-in
- ✅ Good performance (native compilation)
- ✅ Hot reload for fast development
- ✅ Strong SQLite support (Drift package)
- ✅ Easier to hire Flutter developers
- ✅ Google backing and active community

**Considerations:**
- ⚠️ Desktop is newer than mobile (but production-ready)
- ⚠️ App size ~50-100MB (acceptable for installed POS)
- ⚠️ Some hardware access needs platform channels

---

### Option 4: .NET MAUI / WPF (Windows Only Focus)

**Pros:**
- ✅ Best Windows integration
- ✅ Mature ecosystem
- ✅ Great for enterprise

**Cons:**
- ❌ Windows-only (MAUI is cross but new)
- ❌ Different stack from web
- ❌ Licensing considerations

---

### Recommendation Matrix

| Factor | Flutter | Electron | Tauri | .NET |
|--------|---------|----------|-------|------|
| Development Speed | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ |
| Performance | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| App Size | ⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| Code Sharing (Mobile) | ⭐⭐⭐⭐⭐ | ⭐ | ⭐ | ⭐ |
| Offline/SQLite | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Hardware | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Team Experience | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐ | ⭐⭐ |
| Arabic/RTL | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |

### 🎯 Final Decision: **Flutter Desktop**

For YOUR situation, Flutter is the clear winner:
- **Framework**: Flutter 3.x for Desktop (Windows primary)
- **State**: Riverpod or Bloc
- **Database**: Drift (SQLite ORM)
- **Printing**: esc_pos_printer package
- **Crypto**: pointycastle for ZATCA
- **Super Admin**: Laravel + Filament (internal Thawani management)
- **Store Dashboard**: Laravel + Livewire OR Flutter Web
- **Backend API**: Laravel (your existing expertise)

**Why Flutter for Your POS?**
1. **You already know it** - No learning curve, start immediately
2. **Single codebase** - Desktop + Tablet + Mobile companion app
3. **Touch-optimized** - Perfect for POS touchscreens
4. **Arabic/RTL built-in** - Critical for Saudi market
5. **Faster time to market** - Leverage existing Flutter skills
6. **Easier hiring** - Flutter devs easier to find than Rust devs

---

## 📜 ZATCA Phase 2 Compliance

### ZATCA Phase 2 Full Requirements

Since this is a standalone commercial product, ZATCA Phase 2 compliance is **mandatory**.

---

## 🔄 Open Source POS Analysis

### Your Question: Should You Use Open Source?

You mentioned **Store-POS** and similar projects. Let me give you a comprehensive analysis of the best open source options and their limitations for your Saudi supermarket use case.

### Top Open Source POS Options Analyzed

#### 1. OpenSourcePOS (OSPOS) ⭐⭐⭐⭐
**GitHub**: [opensourcepos/opensourcepos](https://github.com/opensourcepos/opensourcepos)
**Language**: PHP (CodeIgniter 4)
**Stars**: 4,000+
**License**: MIT (with branding requirement)

```
┌─────────────────────────────────────────────────────────────────┐
│                    OPENSOURCEPOS OVERVIEW                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PROS                           │  CONS                         │
│  ─────────────────────────────  │  ─────────────────────────── │
│  ✅ Mature (10+ years)          │  ❌ Web-only (no desktop)     │
│  ✅ Active development          │  ❌ Requires internet*        │
│  ✅ PHP (familiar to you)       │  ❌ No ZATCA support          │
│  ✅ Stock management            │  ❌ Branding must stay        │
│  ✅ Multi-language              │  ❌ Not designed for offline  │
│  ✅ Receipt printing            │  ❌ Arabic RTL needs work     │
│  ✅ Barcode support             │  ❌ No delivery integration   │
│  ✅ Good documentation          │  ❌ Basic UI                  │
│                                                                 │
│  *PWA mode exists but limited                                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Features:**
- Stock management with extensible attributes
- VAT, GST, multi-tier taxation
- Sales register with transaction logging
- Barcode generation and printing
- Customer/supplier database
- Multi-user with permissions
- Sales reports

**Critical Limitations for Saudi Market:**
1. **No Offline Mode**: Web-based only, internet required
2. **No ZATCA**: Would need complete rewrite for Phase 2
3. **License Restriction**: Footer must stay visible
4. **No Desktop App**: Can't work if browser crashes

---

#### 2. Floreant POS ⭐⭐⭐
**GitHub**: [niclas3332/floreantpos](https://github.com/floreantpos/floreantpos)
**Language**: Java (Swing)
**License**: Custom (NCFHL)

```
PROS                              CONS
─────────────────────────────     ─────────────────────────────
✅ True desktop application       ❌ Java (heavy, slow startup)
✅ Works offline                  ❌ Restaurant-focused, not retail
✅ Touch screen optimized         ❌ Dated UI (Java Swing)
✅ Kitchen display system         ❌ No ZATCA support
✅ Table management               ❌ No web portal
                                  ❌ Small community
                                  ❌ Complex to modify
```

**Verdict**: Designed for restaurants, not supermarkets.

---

#### 3. UniCenta oPOS ⭐⭐⭐
**Website**: unicenta.com
**Language**: Java
**License**: GPL v3

```
PROS                              CONS
─────────────────────────────     ─────────────────────────────
✅ Mature software                ❌ Java (resource heavy)
✅ Works offline                  ❌ Dated interface
✅ Plugin system                  ❌ No web companion
✅ Multi-language                 ❌ No ZATCA
✅ Good reports                   ❌ Complex installation
                                  ❌ Declining community
```

---

#### 4. Odoo POS ⭐⭐⭐⭐
**GitHub**: [odoo/odoo](https://github.com/odoo/odoo)
**Language**: Python
**License**: LGPL v3 (Community) / Proprietary (Enterprise)

```
PROS                              CONS
─────────────────────────────     ─────────────────────────────
✅ Modern web UI                  ❌ Complex (full ERP)
✅ Full ERP integration           ❌ Enterprise features cost $$
✅ Arabic/RTL built-in            ❌ Resource intensive
✅ Good API                       ❌ Steep learning curve
✅ Saudi ZATCA modules exist      ❌ Python (different stack)
✅ Large community                ❌ Offline is limited
✅ Inventory management           ❌ Vendor lock-in risk
```

**ZATCA Status**: Third-party Saudi ZATCA modules available, but quality varies.

---

#### 5. ERPNext POS ⭐⭐⭐⭐⭐
**GitHub**: [frappe/erpnext](https://github.com/frappe/erpnext)
**Language**: Python (Frappe Framework)
**License**: GPL v3

```
PROS                              CONS
─────────────────────────────     ─────────────────────────────
✅ Modern, polished UI            ❌ Full ERP (overkill?)
✅ Excellent Arabic support       ❌ Python/Frappe stack
✅ ZATCA Phase 2 OFFICIAL         ❌ Complex deployment
✅ Active Saudi community         ❌ Resource intensive
✅ Good documentation             ❌ Learning curve
✅ REST API                       ❌ Offline is complex
✅ Mobile responsive              
```

**ZATCA Status**: ✅ Official ZATCA Phase 2 support with device registration, clearance, reporting.

---

### Comparison Matrix

| Feature | OSPOS | Floreant | UniCenta | Odoo | ERPNext | Custom Build |
|---------|-------|----------|----------|------|---------|--------------|
| **Offline Mode** | ❌ | ✅ | ✅ | ⚠️ | ⚠️ | ✅ |
| **Desktop App** | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ |
| **Web Portal** | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ |
| **ZATCA Phase 2** | ❌ | ❌ | ❌ | ⚠️ | ✅ | ✅ |
| **Arabic RTL** | ⚠️ | ❌ | ⚠️ | ✅ | ✅ | ✅ |
| **Supermarket Focus** | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ |
| **Modern UI** | ⚠️ | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Customization** | ⚠️ | ❌ | ⚠️ | ⚠️ | ⚠️ | ✅ |
| **Branding Freedom** | ❌ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ |
| **Thawani Integration** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Time to Market** | ⭐⭐⭐ | ⭐⭐ | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | ⭐ |

---

### Open Source Limitations Summary

```
┌─────────────────────────────────────────────────────────────────┐
│          WHY OPEN SOURCE MIGHT NOT WORK FOR YOU                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. ZATCA COMPLIANCE                                           │
│     ─────────────────────────────────────────────────────────  │
│     • Phase 2 requires device-level cryptographic signing      │
│     • Most open source POS have NO ZATCA support               │
│     • Adding it means rewriting core invoice logic             │
│     • ERPNext has it, but it's a full ERP (overkill)          │
│                                                                 │
│  2. OFFLINE-FIRST ARCHITECTURE                                 │
│     ─────────────────────────────────────────────────────────  │
│     • Web-based POS (OSPOS, Odoo) need internet               │
│     • True offline needs local database + sync                 │
│     • Most don't have proper conflict resolution               │
│     • Retrofitting offline is VERY hard                        │
│                                                                 │
│  3. DESKTOP APPLICATION                                        │
│     ─────────────────────────────────────────────────────────  │
│     • Java apps (Floreant, UniCenta) are heavy & dated        │
│     • Web apps can't work if browser crashes                   │
│     • Need native hardware access for printers/scanners        │
│     • Wrapping web in Electron adds its own problems          │
│                                                                 │
│  4. SELLING AS YOUR PRODUCT                                    │
│     ─────────────────────────────────────────────────────────  │
│     • GPL: Must share YOUR modifications                       │
│     • MIT with attribution: Competitor can copy                │
│     • Branding restrictions (OSPOS footer required)            │
│     • Hard to differentiate                                    │
│                                                                 │
│  5. THAWANI INTEGRATION                                        │
│     ─────────────────────────────────────────────────────────  │
│     • No open source POS has delivery platform integration     │
│     • Would need to build webhook/API system anyway           │
│     • Stock sync logic must be custom                          │
│                                                                 │
│  6. EXISTING HARDWARE ENVIRONMENT                              │
│     ─────────────────────────────────────────────────────────  │
│     • Open source rarely supports Saudi-common hardware        │
│     • Bixolon, specific printer models need drivers           │
│     • May conflict with existing store software                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🖨️ Hardware Integration (Critical Section)

### The Reality of Saudi Supermarket Environments

You mentioned that you'll deploy on TOP of existing environments. This is crucial:

```
┌─────────────────────────────────────────────────────────────────┐
│              TYPICAL SAUDI SUPERMARKET SETUP                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  EXISTING HARDWARE (You must integrate with):                   │
│  ───────────────────────────────────────────                   │
│                                                                 │
│  • Receipt Printers: Bixolon, Epson TM, Star TSP               │
│  • Barcode Scanners: Honeywell, Zebra, Datalogic               │
│  • Cash Drawers: Connected via printer (RJ11/RJ12)             │
│  • Customer Displays: Serial or USB                            │
│  • Label Printers: Zebra, TSC (for weighable items)            │
│  • Weighing Scales: CAS, Dibal (serial interface)              │
│  • Payment Terminals: Mada (STC Pay, etc.)                     │
│                                                                 │
│  EXISTING SOFTWARE (Potential conflicts):                       │
│  ─────────────────────────────────────────                     │
│                                                                 │
│  • Existing POS software (may have exclusive device access)    │
│  • Windows printer drivers                                     │
│  • Scale management software                                   │
│  • Payment terminal middleware                                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Printer Integration Deep Dive

#### ESC/POS Protocol
Most thermal receipt printers support **ESC/POS** (Epson Standard Code for Point of Sale):

```
┌─────────────────────────────────────────────────────────────────┐
│                    ESC/POS PRINTER SUPPORT                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  FULLY COMPATIBLE (ESC/POS):                                   │
│  • Epson TM-T20, TM-T88 series                                 │
│  • Bixolon SRP-350 series ✅                                   │
│  • Star TSP100/650 series                                      │
│  • Citizen CT-S310 series                                      │
│  • SNBC printers                                               │
│  • Most Chinese OEM thermal printers                           │
│                                                                 │
│  PARTIALLY COMPATIBLE:                                         │
│  • Some Samsung/Bixolon need specific modes                    │
│  • Star printers have StarPRNT mode (different commands)       │
│                                                                 │
│  CONNECTION TYPES:                                             │
│  • USB (most common) - Appears as virtual COM or HID           │
│  • Serial (RS-232) - Legacy but reliable                       │
│  • Network/Ethernet - IP-based printing                        │
│  • Bluetooth - Mobile printing                                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Bixolon Specific Integration

```javascript
// Bixolon printers are ESC/POS compatible
// Connection example using node-escpos (for reference - Flutter uses esc_pos_printer)

const escpos = require('escpos');
escpos.USB = require('escpos-usb');

// For Bixolon USB
const device = new escpos.USB(0x1504, 0x0001); // Bixolon vendor ID

// For Bixolon Network
const device = new escpos.Network('192.168.1.100', 9100);

// For Bixolon Serial
const device = new escpos.Serial('/dev/ttyUSB0', {
    baudRate: 115200,
    autoOpen: false
});
```

#### Printing Libraries Comparison

| Library | Language | Offline | USB | Network | Serial | Arabic |
|---------|----------|---------|-----|---------|--------|--------|
| **node-escpos** | JS/TS | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **escpos-php** | PHP | ❌ | ✅ | ✅ | ✅ | ⚠️ |
| **python-escpos** | Python | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **escpos-rs** (Rust) | Rust | ✅ | ✅ | ✅ | ✅ | ✅ |
| **jzebra/QZ Tray** | Java | ✅ | ✅ | ✅ | ✅ | ✅ |

**Note on Arabic**: ESC/POS has limited Arabic support. Options:
1. Print as image (bitmap) - Works but slower
2. Use printer's built-in Arabic font (if available)
3. Pre-render receipt as image

#### Arabic Receipt Printing Solution (Flutter/Dart)

```dart
// For Arabic receipts, best approach is image-based
// Render receipt using Flutter's Canvas, then convert to image

import 'dart:ui' as ui;
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:esc_pos_printer/esc_pos_printer.dart';
import 'package:esc_pos_utils/esc_pos_utils.dart';
import 'package:image/image.dart' as img;

class ArabicReceiptPrinter {
  final NetworkPrinter printer;
  
  ArabicReceiptPrinter(this.printer);
  
  /// Print Arabic receipt by rendering as image
  Future<void> printArabicReceipt(Receipt receipt) async {
    // Render receipt as image using Flutter canvas
    final image = await _renderReceiptAsImage(receipt);
    
    // Convert ui.Image to img.Image for printer
    final printerImage = await _convertToPrinterImage(image);
    
    // Print the image
    printer.image(printerImage);
    printer.cut();
  }
  
  /// Render receipt content as image using Flutter's PictureRecorder
  Future<ui.Image> _renderReceiptAsImage(Receipt receipt) async {
    const double width = 576; // 80mm at 203 DPI
    double height = _calculateReceiptHeight(receipt);
    
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(
      recorder,
      Rect.fromLTWH(0, 0, width, height),
    );
    
    // White background
    canvas.drawRect(
      Rect.fromLTWH(0, 0, width, height),
      Paint()..color = Colors.white,
    );
    
    // Arabic text painter
    final arabicStyle = TextStyle(
      fontFamily: 'Noto Sans Arabic',
      fontSize: 24,
      color: Colors.black,
    );
    
    double y = 20;
    
    // Store name (Arabic, centered)
    final storeNamePainter = TextPainter(
      text: TextSpan(text: receipt.storeNameAr, style: arabicStyle),
      textDirection: TextDirection.rtl,
      textAlign: TextAlign.center,
    );
    storeNamePainter.layout(maxWidth: width - 40);
    storeNamePainter.paint(canvas, Offset((width - storeNamePainter.width) / 2, y));
    y += 40;
    
    // Separator line
    canvas.drawLine(
      Offset(20, y),
      Offset(width - 20, y),
      Paint()..strokeWidth = 1,
    );
    y += 20;
    
    // Items
    for (final item in receipt.items) {
      // Item name (Arabic, right-aligned)
      final itemPainter = TextPainter(
        text: TextSpan(text: item.nameAr, style: arabicStyle),
        textDirection: TextDirection.rtl,
      );
      itemPainter.layout(maxWidth: width - 200);
      itemPainter.paint(canvas, Offset(width - 20 - itemPainter.width, y));
      
      // Quantity and price (left side)
      final qtyPricePainter = TextPainter(
        text: TextSpan(
          text: '${item.quantity} × ${item.price.toStringAsFixed(2)}',
          style: arabicStyle.copyWith(fontSize: 20),
        ),
        textDirection: TextDirection.ltr,
      );
      qtyPricePainter.layout();
      qtyPricePainter.paint(canvas, Offset(20, y));
      
      y += 35;
    }
    
    // Total
    y += 20;
    final totalPainter = TextPainter(
      text: TextSpan(
        text: 'المجموع: ${receipt.total.toStringAsFixed(2)} ريال',
        style: arabicStyle.copyWith(fontWeight: FontWeight.bold),
      ),
      textDirection: TextDirection.rtl,
    );
    totalPainter.layout();
    totalPainter.paint(canvas, Offset(width - 20 - totalPainter.width, y));
    
    // Convert to image
    final picture = recorder.endRecording();
    return picture.toImage(width.toInt(), height.toInt());
  }
  
  /// Convert Flutter ui.Image to printer-compatible img.Image
  Future<img.Image> _convertToPrinterImage(ui.Image image) async {
    final byteData = await image.toByteData(format: ui.ImageByteFormat.rawRgba);
    final bytes = byteData!.buffer.asUint8List();
    
    // Create img.Image from RGBA bytes
    final printerImage = img.Image.fromBytes(
      width: image.width,
      height: image.height,
      bytes: bytes.buffer,
      format: img.Format.uint8,
      numChannels: 4,
    );
    
    return printerImage;
  }
  
  double _calculateReceiptHeight(Receipt receipt) {
    // Base height + items + QR code
    return 200 + (receipt.items.length * 35) + 250;
  }
}
```

### Barcode Scanner Integration

Most barcode scanners work as **keyboard emulators** (HID):

```
┌─────────────────────────────────────────────────────────────────┐
│                    BARCODE SCANNER MODES                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  MODE 1: Keyboard Emulation (HID) - Most Common                │
│  ──────────────────────────────────────────────                │
│  • Scanner types barcode as if keyboard input                  │
│  • Works with ANY software                                     │
│  • No driver needed                                            │
│  • Just focus on input field and scan                          │
│                                                                 │
│  MODE 2: Serial/COM Port                                       │
│  ────────────────────────                                      │
│  • Raw data via serial connection                              │
│  • Need to listen on COM port                                  │
│  • More control but more complex                               │
│                                                                 │
│  MODE 3: USB HID (Raw)                                         │
│  ─────────────────────                                         │
│  • Direct USB communication                                    │
│  • Need libusb/node-hid                                        │
│  • Maximum control                                             │
│                                                                 │
│  RECOMMENDATION: Use keyboard emulation mode                   │
│  • Works 99% of the time                                       │
│  • No special integration needed                               │
│  • Scanner adds Enter key after barcode                        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Desktop App Barcode Handling (Flutter)

```dart
// Flutter widget for barcode scanner input (keyboard emulation mode)
class BarcodeScannerListener extends StatefulWidget {
  final Function(String barcode) onScan;
  final Widget child;
  
  const BarcodeScannerListener({
    required this.onScan,
    required this.child,
    Key? key,
  }) : super(key: key);
  
  @override
  State<BarcodeScannerListener> createState() => _BarcodeScannerListenerState();
}

class _BarcodeScannerListenerState extends State<BarcodeScannerListener> {
  String _buffer = '';
  DateTime _lastKeyTime = DateTime.now();
  final FocusNode _focusNode = FocusNode();
  
  @override
  Widget build(BuildContext context) {
    return RawKeyboardListener(
      focusNode: _focusNode,
      autofocus: true,
      onKey: _handleKeyEvent,
      child: widget.child,
    );
  }
  
  void _handleKeyEvent(RawKeyEvent event) {
    if (event is! RawKeyDownEvent) return;
    
    final now = DateTime.now();
    
    // Scanner types fast (< 50ms between keys)
    // Human types slow (> 100ms between keys)
    if (now.difference(_lastKeyTime).inMilliseconds > 100) {
      _buffer = ''; // Reset buffer if too slow
    }
    _lastKeyTime = now;
    
    final key = event.logicalKey;
    
    if (key == LogicalKeyboardKey.enter) {
      if (_buffer.length >= 8) { // Valid barcode length
        widget.onScan(_buffer);
      }
      _buffer = '';
    } else {
      // Check if it's a digit
      final char = event.character;
      if (char != null && RegExp(r'^[0-9]$').hasMatch(char)) {
        _buffer += char;
      }
    }
  }
  
  @override
  void dispose() {
    _focusNode.dispose();
    super.dispose();
  }
}

// Usage in POS screen:
class POSScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return BarcodeScannerListener(
      onScan: (barcode) {
        // Look up product by barcode and add to cart
        context.read<CartProvider>().addByBarcode(barcode);
      },
      child: Scaffold(
        body: Row(
          children: [
            ProductGrid(),
            CartPanel(),
          ],
        ),
      ),
    );
  }
}
```

### Cash Drawer Integration

Cash drawers are typically connected to the receipt printer via RJ11/RJ12:

```dart
// Open cash drawer via printer kick command (Flutter)
import 'package:esc_pos_printer/esc_pos_printer.dart';

Future<void> openCashDrawer(NetworkPrinter printer) async {
  // ESC/POS command to kick drawer
  // Most drawers use Pin 2
  printer.drawer(pin: PosDrawer.pin2);
}

// Or with raw bytes if needed:
Future<void> openCashDrawerRaw(NetworkPrinter printer) async {
  // Pin 2: ESC p 0 25 250
  final List<int> kickDrawerPin2 = [0x1B, 0x70, 0x00, 0x19, 0xFA];
  printer.rawBytes(kickDrawerPin2);
}
```

### Weighing Scale Integration

For weighable products (fruits, vegetables, deli):

```dart
// Serial port communication with scale (Flutter)
import 'package:flutter_libserialport/flutter_libserialport.dart';

class WeighingScale {
  SerialPort? _port;
  SerialPortReader? _reader;
  
  Future<void> connect(String portName) async {
    // portName: '/dev/ttyUSB0' on Linux, 'COM3' on Windows
    _port = SerialPort(portName);
    
    final config = SerialPortConfig()
      ..baudRate = 9600
      ..bits = 8
      ..parity = SerialPortParity.none
      ..stopBits = 1;
    
    _port!.config = config;
    
    if (!_port!.openReadWrite()) {
      throw Exception('Failed to open serial port');
    }
    
    _reader = SerialPortReader(_port!);
  }
  
  // Read weight from scale
  Future<double> readWeight() async {
    if (_port == null || !_port!.isOpen) {
      throw Exception('Scale not connected');
    }
    
    // Send weight request command (varies by scale model)
    _port!.write(Uint8List.fromList([0x05])); // ENQ
    
    // Read response
    final response = await _reader!.stream.first;
    final responseStr = String.fromCharCodes(response);
    
    // Parse response (format varies by scale)
    // Example: "ST,GS,  0.500kg"
    final match = RegExp(r'[\d.]+').firstMatch(responseStr);
    return double.tryParse(match?.group(0) ?? '0') ?? 0.0;
  }
  
  // Stream weight updates (for live display)
  Stream<double> get weightStream async* {
    if (_reader == null) return;
    
    await for (final data in _reader!.stream) {
      final responseStr = String.fromCharCodes(data);
      final match = RegExp(r'[\d.]+').firstMatch(responseStr);
      final weight = double.tryParse(match?.group(0) ?? '0') ?? 0.0;
      yield weight;
    }
  }
  
  void dispose() {
    _reader?.close();
    _port?.close();
  }
}

// Usage in POS:
class WeighableProductDialog extends StatelessWidget {
  final Product product;
  final WeighingScale scale;
  
  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('${product.nameAr} - بالوزن'),
      content: StreamBuilder<double>(
        stream: scale.weightStream,
        builder: (context, snapshot) {
          final weight = snapshot.data ?? 0.0;
          final total = weight * product.price;
          
          return Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '${weight.toStringAsFixed(3)} kg',
                style: TextStyle(fontSize: 48, fontWeight: FontWeight.bold),
              ),
              SizedBox(height: 16),
              Text(
                '${total.toStringAsFixed(2)} SAR',
                style: TextStyle(fontSize: 32, color: Colors.green),
              ),
            ],
          );
        },
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text('إلغاء'),
        ),
        ElevatedButton(
          onPressed: () async {
            final weight = await scale.readWeight();
            Navigator.pop(context, weight);
          },
          child: Text('إضافة للسلة'),
        ),
      ],
    );
  }
}
```

### Hardware Integration Summary

```
┌─────────────────────────────────────────────────────────────────┐
│              HARDWARE INTEGRATION (FLUTTER)                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  FOR YOUR FLUTTER DESKTOP APP:                                 │
│                                                                 │
│  RECEIPT PRINTER (Bixolon, Epson, etc.)                        │
│  ├── Package: esc_pos_printer, flutter_thermal_printer        │
│  ├── Connection: Network preferred (most Bixolon support it)  │
│  ├── Arabic: Render as image using Canvas                      │
│  └── ZATCA QR: qr_flutter package                              │
│                                                                 │
│  BARCODE SCANNER                                               │
│  ├── Mode: Keyboard emulation (HID) - works automatically     │
│  ├── Integration: RawKeyboardListener or FocusNode            │
│  ├── Detection: Speed-based (scanner types fast)              │
│  └── No special packages needed                                │
│                                                                 │
│  CASH DRAWER                                                   │
│  ├── Connection: Via receipt printer (RJ11/RJ12)               │
│  ├── Command: ESC/POS kick drawer via printer package         │
│  └── Triggered: After successful payment                       │
│                                                                 │
│  WEIGHING SCALE (if needed)                                    │
│  ├── Connection: Serial (RS-232)                               │
│  ├── Package: flutter_libserialport                           │
│  └── Protocol: Depends on scale brand                          │
│                                                                 │
│  CUSTOMER DISPLAY (if needed)                                  │
│  ├── Option 1: Second Flutter window (multi-window)           │
│  ├── Option 2: Serial display via libserialport               │
│  └── Alternative: Second screen via display_api               │
│                                                                 │
│  PAYMENT TERMINAL (Mada)                                       │
│  ├── Integration: Usually separate, manual amount entry        │
│  ├── Future: Consider STC Pay API integration                  │
│  └── Receipt: Often prints own receipt                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## �️ Barcode Label Printing

### Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                BARCODE LABEL PRINTING SYSTEM                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  USE CASES:                                                    │
│  ──────────                                                    │
│  • New products received without barcodes                      │
│  • In-house products (bakery, deli, prepared foods)            │
│  • Reprinting damaged/missing labels                           │
│  • Shelf labels with price                                     │
│  • Bulk label printing for inventory                           │
│  • Promotional labels (sale prices)                            │
│  • Weighable product labels (with weight + price)              │
│                                                                 │
│  LABEL TYPES:                                                  │
│  ────────────                                                  │
│  • Product barcode labels (small, stick on product)            │
│  • Shelf labels (price tags for shelf edge)                    │
│  • Weighable labels (from scale, includes weight/price)        │
│  • Promotional labels (SALE, discount percentage)              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Supported Label Printers

```
┌─────────────────────────────────────────────────────────────────┐
│                 LABEL PRINTER COMPATIBILITY                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ZEBRA PRINTERS (Industry Standard)                            │
│  ──────────────────────────────────                            │
│  • Models: ZD220, ZD420, ZD620, GK420, GC420                   │
│  • Language: ZPL (Zebra Programming Language)                  │
│  • Connection: USB, Network, Bluetooth                         │
│  • Label sizes: 25mm - 100mm width                             │
│  • Resolution: 203/300 DPI                                     │
│                                                                 │
│  TSC PRINTERS (Cost-Effective)                                 │
│  ─────────────────────────────                                 │
│  • Models: TTP-244, TE200, TE300, DA200                        │
│  • Language: TSPL (TSC Programming Language)                   │
│  • Connection: USB, Network                                    │
│  • Popular in Saudi market (good price)                        │
│                                                                 │
│  BIXOLON LABEL PRINTERS                                        │
│  ──────────────────────────                                    │
│  • Models: SLP-DX220, SLP-TX400                                │
│  • Language: SLCS (Bixolon) or ZPL compatible                  │
│  • Often bundled with receipt printers                         │
│                                                                 │
│  GODEX PRINTERS                                                │
│  ──────────────                                                │
│  • Models: G500, G530, EZ-series                               │
│  • Language: EZPL (Godex) or ZPL compatible                    │
│  • Good budget option                                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Label Design Templates

```
┌─────────────────────────────────────────────────────────────────┐
│               STANDARD LABEL TEMPLATES                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  TEMPLATE 1: Basic Product Label (40mm x 30mm)                 │
│  ┌─────────────────────────────────────┐                       │
│  │  Product Name (Arabic)              │                       │
│  │  المنتج الاسم                        │                       │
│  │  ║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║   │                       │
│  │       6281234567890                  │                       │
│  │  SAR 12.50          EXP: 2026-03-15 │                       │
│  └─────────────────────────────────────┘                       │
│                                                                 │
│  TEMPLATE 2: Shelf Label (50mm x 30mm)                         │
│  ┌─────────────────────────────────────┐                       │
│  │  Coca Cola 330ml                    │                       │
│  │  كوكا كولا ٣٣٠ مل                    │                       │
│  │  ┌─────────────────┐                │                       │
│  │  │   SAR 2.50      │   ║║║║║║║║║   │                       │
│  │  │   ر.س ٢.٥٠      │   6281234     │                       │
│  │  └─────────────────┘                │                       │
│  └─────────────────────────────────────┘                       │
│                                                                 │
│  TEMPLATE 3: Weighable Product Label (60mm x 40mm)             │
│  ┌─────────────────────────────────────┐                       │
│  │  تفاح أحمر - Red Apple              │                       │
│  │  ─────────────────────────────────  │                       │
│  │  Weight: 1.250 KG    الوزن          │                       │
│  │  Price/KG: SAR 8.00  سعر/كجم        │                       │
│  │  ─────────────────────────────────  │                       │
│  │  ║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║   │                       │
│  │       2812345012506                  │                       │
│  │  ─────────────────────────────────  │                       │
│  │  TOTAL: SAR 10.00    المجموع        │                       │
│  │  Date: 2026-02-03                   │                       │
│  └─────────────────────────────────────┘                       │
│                                                                 │
│  TEMPLATE 4: Promotional Label (40mm x 25mm)                   │
│  ┌─────────────────────────────────────┐                       │
│  │   🏷️ SALE - تخفيض                   │                       │
│  │  ┌───────┐  ┌───────┐               │                       │
│  │  │ WAS   │  │ NOW   │               │                       │
│  │  │ 15.00 │  │ 9.99  │               │                       │
│  │  └───────┘  └───────┘               │                       │
│  │       -33% OFF                      │                       │
│  └─────────────────────────────────────┘                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Barcode Label Printing Implementation (Flutter)

```dart
// lib/services/label_printer_service.dart
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:barcode/barcode.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

/// Supported label printer languages
enum LabelLanguage { zpl, tspl, slcs, escLabel }

/// Label template types
enum LabelTemplate {
  productBasic,
  shelfPrice,
  weighable,
  promotional,
  custom,
}

/// Label size configuration
class LabelSize {
  final double widthMm;
  final double heightMm;
  final int dpi;
  
  const LabelSize({
    required this.widthMm,
    required this.heightMm,
    this.dpi = 203,
  });
  
  int get widthDots => (widthMm * dpi / 25.4).round();
  int get heightDots => (heightMm * dpi / 25.4).round();
  
  static const product40x30 = LabelSize(widthMm: 40, heightMm: 30);
  static const shelf50x30 = LabelSize(widthMm: 50, heightMm: 30);
  static const weighable60x40 = LabelSize(widthMm: 60, heightMm: 40);
  static const small30x20 = LabelSize(widthMm: 30, heightMm: 20);
}

/// Product label data
class LabelData {
  final String productName;
  final String productNameAr;
  final String barcode;
  final double price;
  final double? originalPrice; // For promotions
  final double? weight; // For weighable products
  final double? pricePerKg;
  final DateTime? expiryDate;
  final String? batchNumber;
  
  LabelData({
    required this.productName,
    required this.productNameAr,
    required this.barcode,
    required this.price,
    this.originalPrice,
    this.weight,
    this.pricePerKg,
    this.expiryDate,
    this.batchNumber,
  });
}

/// Label printer service
class LabelPrinterService {
  final LabelLanguage language;
  final LabelSize defaultSize;
  String? printerIp;
  int printerPort;
  
  LabelPrinterService({
    required this.language,
    this.defaultSize = LabelSize.product40x30,
    this.printerIp,
    this.printerPort = 9100,
  });
  
  /// Generate ZPL code for Zebra printers
  String generateZPL(LabelData data, {LabelTemplate template = LabelTemplate.productBasic}) {
    final size = _getSizeForTemplate(template);
    
    switch (template) {
      case LabelTemplate.productBasic:
        return _generateBasicProductZPL(data, size);
      case LabelTemplate.shelfPrice:
        return _generateShelfLabelZPL(data, size);
      case LabelTemplate.weighable:
        return _generateWeighableLabelZPL(data, size);
      case LabelTemplate.promotional:
        return _generatePromotionalLabelZPL(data, size);
      default:
        return _generateBasicProductZPL(data, size);
    }
  }
  
  String _generateBasicProductZPL(LabelData data, LabelSize size) {
    return '''
^XA
^PW${size.widthDots}
^LL${size.heightDots}
^CF0,25
^FO20,10^FD${data.productNameAr}^FS
^CF0,20
^FO20,40^FD${data.productName}^FS
^BY2,2,50
^FO20,70^BC^FD${data.barcode}^FS
^CF0,22
^FO20,140^FDSAR ${data.price.toStringAsFixed(2)}^FS
${data.expiryDate != null ? '^FO150,140^FDEXP: ${_formatDate(data.expiryDate!)}^FS' : ''}
^XZ
''';
  }
  
  String _generateShelfLabelZPL(LabelData data, LabelSize size) {
    return '''
^XA
^PW${size.widthDots}
^LL${size.heightDots}
^CF0,22
^FO10,10^FD${data.productName}^FS
^FO10,35^FD${data.productNameAr}^FS
^CF0,40
^FO10,65^FDSAR ${data.price.toStringAsFixed(2)}^FS
^BY2,2,40
^FO200,60^BC^FD${data.barcode.substring(0, 7)}^FS
^XZ
''';
  }
  
  String _generateWeighableLabelZPL(LabelData data, LabelSize size) {
    final total = (data.weight ?? 0) * (data.pricePerKg ?? data.price);
    
    return '''
^XA
^PW${size.widthDots}
^LL${size.heightDots}
^CF0,25
^FO20,10^FD${data.productNameAr} - ${data.productName}^FS
^FO20,40^GB350,1,1^FS
^CF0,20
^FO20,50^FDWeight: ${data.weight?.toStringAsFixed(3)} KG^FS
^FO200,50^FDالوزن^FS
^FO20,75^FDPrice/KG: SAR ${data.pricePerKg?.toStringAsFixed(2)}^FS
^FO200,75^FDسعر/كجم^FS
^FO20,100^GB350,1,1^FS
^BY2,2,50
^FO50,110^BC^FD${data.barcode}^FS
^FO20,175^GB350,1,1^FS
^CF0,28
^FO20,185^FDTOTAL: SAR ${total.toStringAsFixed(2)}^FS
^FO250,185^FDالمجموع^FS
^CF0,18
^FO20,220^FDDate: ${_formatDate(DateTime.now())}^FS
^XZ
''';
  }
  
  String _generatePromotionalLabelZPL(LabelData data, LabelSize size) {
    final discount = data.originalPrice != null 
        ? ((data.originalPrice! - data.price) / data.originalPrice! * 100).round()
        : 0;
    
    return '''
^XA
^PW${size.widthDots}
^LL${size.heightDots}
^CF0,30
^FO50,10^FDSALE - تخفيض^FS
^CF0,18
^FO20,45^FDWAS: ${data.originalPrice?.toStringAsFixed(2)}^FS
^FO150,45^FDNOW: ${data.price.toStringAsFixed(2)}^FS
^CF0,25
^FO80,75^FD-${discount}% OFF^FS
^XZ
''';
  }
  
  /// Generate TSPL code for TSC printers
  String generateTSPL(LabelData data, {LabelTemplate template = LabelTemplate.productBasic}) {
    final size = _getSizeForTemplate(template);
    
    return '''
SIZE ${size.widthMm} mm, ${size.heightMm} mm
GAP 2 mm, 0 mm
DIRECTION 1
CLS
TEXT 20,10,"3",0,1,1,"${data.productNameAr}"
TEXT 20,40,"2",0,1,1,"${data.productName}"
BARCODE 20,70,"128",50,1,0,2,2,"${data.barcode}"
TEXT 20,140,"3",0,1,1,"SAR ${data.price.toStringAsFixed(2)}"
PRINT 1
''';
  }
  
  LabelSize _getSizeForTemplate(LabelTemplate template) {
    switch (template) {
      case LabelTemplate.productBasic:
        return LabelSize.product40x30;
      case LabelTemplate.shelfPrice:
        return LabelSize.shelf50x30;
      case LabelTemplate.weighable:
        return LabelSize.weighable60x40;
      case LabelTemplate.promotional:
        return LabelSize.small30x20;
      default:
        return defaultSize;
    }
  }
  
  String _formatDate(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }
  
  /// Print labels via network
  Future<bool> printLabel(String labelCode, {int copies = 1}) async {
    if (printerIp == null) {
      throw Exception('Printer IP not configured');
    }
    
    try {
      final socket = await Socket.connect(printerIp!, printerPort);
      
      for (int i = 0; i < copies; i++) {
        socket.write(labelCode);
      }
      
      await socket.flush();
      await socket.close();
      return true;
    } catch (e) {
      print('Label print error: $e');
      return false;
    }
  }
  
  /// Print multiple different labels (batch)
  Future<bool> printBatch(List<LabelData> labels, {LabelTemplate template = LabelTemplate.productBasic}) async {
    final buffer = StringBuffer();
    
    for (final label in labels) {
      switch (language) {
        case LabelLanguage.zpl:
          buffer.write(generateZPL(label, template: template));
          break;
        case LabelLanguage.tspl:
          buffer.write(generateTSPL(label, template: template));
          break;
        default:
          buffer.write(generateZPL(label, template: template));
      }
    }
    
    return printLabel(buffer.toString());
  }
}
```

### Barcode Label Printing UI (Flutter)

```dart
// lib/screens/barcode_printing_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class BarcodePrintingScreen extends ConsumerStatefulWidget {
  @override
  ConsumerState<BarcodePrintingScreen> createState() => _BarcodePrintingScreenState();
}

class _BarcodePrintingScreenState extends ConsumerState<BarcodePrintingScreen> {
  final List<ProductForPrinting> _selectedProducts = [];
  LabelTemplate _selectedTemplate = LabelTemplate.productBasic;
  int _copies = 1;
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('طباعة الباركود - Barcode Printing'),
        actions: [
          IconButton(
            icon: Icon(Icons.print),
            onPressed: _selectedProducts.isNotEmpty ? _printSelected : null,
            tooltip: 'Print Selected',
          ),
          IconButton(
            icon: Icon(Icons.select_all),
            onPressed: _selectAll,
            tooltip: 'Select All',
          ),
          IconButton(
            icon: Icon(Icons.clear_all),
            onPressed: _clearSelection,
            tooltip: 'Clear Selection',
          ),
        ],
      ),
      body: Row(
        children: [
          // Product List (Left Panel)
          Expanded(
            flex: 2,
            child: _buildProductList(),
          ),
          
          // Divider
          VerticalDivider(width: 1),
          
          // Print Settings & Preview (Right Panel)
          Expanded(
            flex: 1,
            child: _buildPrintSettings(),
          ),
        ],
      ),
    );
  }
  
  Widget _buildProductList() {
    final products = ref.watch(productsProvider);
    
    return Column(
      children: [
        // Search Bar
        Padding(
          padding: EdgeInsets.all(16),
          child: TextField(
            decoration: InputDecoration(
              hintText: 'Search products / بحث المنتجات',
              prefixIcon: Icon(Icons.search),
              border: OutlineInputBorder(),
            ),
            onChanged: (value) {
              ref.read(productSearchProvider.notifier).state = value;
            },
          ),
        ),
        
        // Category Filter
        SizedBox(
          height: 50,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: EdgeInsets.symmetric(horizontal: 16),
            children: [
              FilterChip(
                label: Text('All'),
                selected: true,
                onSelected: (_) {},
              ),
              SizedBox(width: 8),
              FilterChip(
                label: Text('No Barcode'),
                selected: false,
                onSelected: (_) {},
              ),
              SizedBox(width: 8),
              FilterChip(
                label: Text('Recently Added'),
                selected: false,
                onSelected: (_) {},
              ),
            ],
          ),
        ),
        
        // Product Grid
        Expanded(
          child: products.when(
            data: (productList) => GridView.builder(
              padding: EdgeInsets.all(16),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 4,
                childAspectRatio: 0.8,
                crossAxisSpacing: 8,
                mainAxisSpacing: 8,
              ),
              itemCount: productList.length,
              itemBuilder: (context, index) {
                final product = productList[index];
                final isSelected = _selectedProducts.any((p) => p.id == product.id);
                
                return _buildProductCard(product, isSelected);
              },
            ),
            loading: () => Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('Error: $e')),
          ),
        ),
      ],
    );
  }
  
  Widget _buildProductCard(Product product, bool isSelected) {
    return Card(
      color: isSelected ? Colors.blue.shade50 : null,
      child: InkWell(
        onTap: () => _toggleSelection(product),
        child: Stack(
          children: [
            Padding(
              padding: EdgeInsets.all(8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Product Image
                  Expanded(
                    child: Center(
                      child: product.imageUrl != null
                          ? Image.network(product.imageUrl!)
                          : Icon(Icons.inventory, size: 48, color: Colors.grey),
                    ),
                  ),
                  SizedBox(height: 8),
                  // Product Name
                  Text(
                    product.nameAr,
                    style: TextStyle(fontWeight: FontWeight.bold),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    product.name,
                    style: TextStyle(fontSize: 12, color: Colors.grey),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  SizedBox(height: 4),
                  // Barcode
                  Text(
                    product.barcode ?? 'No barcode',
                    style: TextStyle(fontSize: 10, fontFamily: 'monospace'),
                  ),
                  // Price
                  Text(
                    'SAR ${product.price.toStringAsFixed(2)}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Colors.green.shade700,
                    ),
                  ),
                ],
              ),
            ),
            // Selection checkbox
            if (isSelected)
              Positioned(
                top: 4,
                right: 4,
                child: CircleAvatar(
                  radius: 12,
                  backgroundColor: Colors.blue,
                  child: Icon(Icons.check, size: 16, color: Colors.white),
                ),
              ),
            // Quantity badge for multiple copies
            if (isSelected)
              Positioned(
                bottom: 4,
                right: 4,
                child: _buildCopiesSelector(product),
              ),
          ],
        ),
      ),
    );
  }
  
  Widget _buildCopiesSelector(Product product) {
    final item = _selectedProducts.firstWhere((p) => p.id == product.id);
    
    return Container(
      decoration: BoxDecoration(
        color: Colors.blue,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: Icon(Icons.remove, size: 16, color: Colors.white),
            onPressed: () => _updateCopies(product.id, item.copies - 1),
            constraints: BoxConstraints(minWidth: 24, minHeight: 24),
            padding: EdgeInsets.zero,
          ),
          Text(
            '${item.copies}',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
          ),
          IconButton(
            icon: Icon(Icons.add, size: 16, color: Colors.white),
            onPressed: () => _updateCopies(product.id, item.copies + 1),
            constraints: BoxConstraints(minWidth: 24, minHeight: 24),
            padding: EdgeInsets.zero,
          ),
        ],
      ),
    );
  }
  
  Widget _buildPrintSettings() {
    return SingleChildScrollView(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Selection Summary
          Card(
            color: Colors.blue.shade50,
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Selected Products',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  SizedBox(height: 8),
                  Text('${_selectedProducts.length} products'),
                  Text('${_getTotalLabels()} total labels'),
                ],
              ),
            ),
          ),
          
          SizedBox(height: 24),
          
          // Label Template Selection
          Text(
            'Label Template',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          SizedBox(height: 8),
          
          _buildTemplateOption(
            LabelTemplate.productBasic,
            'Basic Product',
            '40mm x 30mm',
            Icons.label,
          ),
          _buildTemplateOption(
            LabelTemplate.shelfPrice,
            'Shelf Price',
            '50mm x 30mm',
            Icons.price_check,
          ),
          _buildTemplateOption(
            LabelTemplate.weighable,
            'Weighable Product',
            '60mm x 40mm',
            Icons.scale,
          ),
          _buildTemplateOption(
            LabelTemplate.promotional,
            'Promotional',
            '40mm x 25mm',
            Icons.local_offer,
          ),
          
          SizedBox(height: 24),
          
          // Default Copies
          Text(
            'Default Copies per Product',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          SizedBox(height: 8),
          Row(
            children: [
              IconButton(
                icon: Icon(Icons.remove_circle),
                onPressed: _copies > 1 ? () => setState(() => _copies--) : null,
              ),
              Text(
                '$_copies',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              ),
              IconButton(
                icon: Icon(Icons.add_circle),
                onPressed: () => setState(() => _copies++),
              ),
            ],
          ),
          
          SizedBox(height: 24),
          
          // Label Preview
          Text(
            'Label Preview',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          SizedBox(height: 8),
          Container(
            width: double.infinity,
            height: 150,
            decoration: BoxDecoration(
              border: Border.all(color: Colors.grey),
              borderRadius: BorderRadius.circular(8),
            ),
            child: _buildLabelPreview(),
          ),
          
          SizedBox(height: 24),
          
          // Print Button
          SizedBox(
            width: double.infinity,
            height: 56,
            child: ElevatedButton.icon(
              icon: Icon(Icons.print, size: 28),
              label: Text(
                'PRINT ${_getTotalLabels()} LABELS',
                style: TextStyle(fontSize: 18),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                foregroundColor: Colors.white,
              ),
              onPressed: _selectedProducts.isNotEmpty ? _printSelected : null,
            ),
          ),
          
          SizedBox(height: 16),
          
          // Print to PDF (backup option)
          OutlinedButton.icon(
            icon: Icon(Icons.picture_as_pdf),
            label: Text('Export to PDF'),
            onPressed: _selectedProducts.isNotEmpty ? _exportToPdf : null,
          ),
        ],
      ),
    );
  }
  
  Widget _buildTemplateOption(
    LabelTemplate template,
    String name,
    String size,
    IconData icon,
  ) {
    final isSelected = _selectedTemplate == template;
    
    return Card(
      color: isSelected ? Colors.blue.shade100 : null,
      child: ListTile(
        leading: Icon(icon, color: isSelected ? Colors.blue : null),
        title: Text(name),
        subtitle: Text(size),
        trailing: isSelected ? Icon(Icons.check_circle, color: Colors.blue) : null,
        onTap: () => setState(() => _selectedTemplate = template),
      ),
    );
  }
  
  Widget _buildLabelPreview() {
    if (_selectedProducts.isEmpty) {
      return Center(
        child: Text('Select products to preview', style: TextStyle(color: Colors.grey)),
      );
    }
    
    final firstProduct = _selectedProducts.first;
    
    return CustomPaint(
      painter: LabelPreviewPainter(
        productName: firstProduct.name,
        productNameAr: firstProduct.nameAr,
        barcode: firstProduct.barcode ?? '0000000000000',
        price: firstProduct.price,
        template: _selectedTemplate,
      ),
    );
  }
  
  void _toggleSelection(Product product) {
    setState(() {
      final existingIndex = _selectedProducts.indexWhere((p) => p.id == product.id);
      
      if (existingIndex >= 0) {
        _selectedProducts.removeAt(existingIndex);
      } else {
        _selectedProducts.add(ProductForPrinting(
          id: product.id,
          name: product.name,
          nameAr: product.nameAr,
          barcode: product.barcode,
          price: product.price,
          copies: _copies,
        ));
      }
    });
  }
  
  void _updateCopies(String productId, int copies) {
    if (copies < 1) return;
    
    setState(() {
      final index = _selectedProducts.indexWhere((p) => p.id == productId);
      if (index >= 0) {
        _selectedProducts[index] = _selectedProducts[index].copyWith(copies: copies);
      }
    });
  }
  
  int _getTotalLabels() {
    return _selectedProducts.fold(0, (sum, p) => sum + p.copies);
  }
  
  void _selectAll() {
    // Implementation: select all visible products
  }
  
  void _clearSelection() {
    setState(() => _selectedProducts.clear());
  }
  
  Future<void> _printSelected() async {
    final labelService = ref.read(labelPrinterServiceProvider);
    
    // Show printing dialog
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('Printing ${_getTotalLabels()} labels...'),
          ],
        ),
      ),
    );
    
    try {
      final labels = <LabelData>[];
      
      for (final product in _selectedProducts) {
        for (int i = 0; i < product.copies; i++) {
          labels.add(LabelData(
            productName: product.name,
            productNameAr: product.nameAr,
            barcode: product.barcode ?? '',
            price: product.price,
          ));
        }
      }
      
      final success = await labelService.printBatch(labels, template: _selectedTemplate);
      
      Navigator.pop(context); // Close dialog
      
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('✅ Printed ${labels.length} labels successfully'),
            backgroundColor: Colors.green,
          ),
        );
        _clearSelection();
      } else {
        throw Exception('Print failed');
      }
    } catch (e) {
      Navigator.pop(context); // Close dialog
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('❌ Print failed: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }
  
  Future<void> _exportToPdf() async {
    // Generate PDF with all labels for printing via standard printer
  }
}

/// Product selected for printing
class ProductForPrinting {
  final String id;
  final String name;
  final String nameAr;
  final String? barcode;
  final double price;
  final int copies;
  
  ProductForPrinting({
    required this.id,
    required this.name,
    required this.nameAr,
    this.barcode,
    required this.price,
    this.copies = 1,
  });
  
  ProductForPrinting copyWith({int? copies}) {
    return ProductForPrinting(
      id: id,
      name: name,
      nameAr: nameAr,
      barcode: barcode,
      price: price,
      copies: copies ?? this.copies,
    );
  }
}
```

### Barcode Generation for New Products

```dart
// lib/services/barcode_generator_service.dart

/// Generate internal barcodes for products without manufacturer barcodes
class BarcodeGeneratorService {
  static const String prefix = '200'; // Internal barcode prefix (2 = in-store use)
  
  /// Generate EAN-13 barcode for internal use
  /// Format: 200-SSSSS-PPPPP-C
  /// 200 = Internal prefix
  /// SSSSS = Store ID (5 digits)
  /// PPPPP = Product sequence (5 digits)
  /// C = Check digit
  String generateInternalBarcode(String storeId, int productSequence) {
    final storeCode = storeId.hashCode.abs().toString().padLeft(5, '0').substring(0, 5);
    final productCode = productSequence.toString().padLeft(5, '0');
    
    final barcodeWithoutCheck = '$prefix$storeCode$productCode';
    final checkDigit = _calculateEAN13CheckDigit(barcodeWithoutCheck);
    
    return '$barcodeWithoutCheck$checkDigit';
  }
  
  /// Generate barcode for weighable products (price embedded)
  /// Format: 2P-PPPPP-WWWWW-C
  /// 2 = Weighable prefix
  /// P = Price lookup code
  /// PPPPP = Product PLU (5 digits)
  /// WWWWW = Weight in grams (5 digits, divided by 1000 = KG)
  /// C = Check digit
  String generateWeighableBarcode(String plu, double weightKg) {
    final pluCode = plu.padLeft(5, '0');
    final weightGrams = (weightKg * 1000).round().toString().padLeft(5, '0');
    
    final barcodeWithoutCheck = '21$pluCode$weightGrams';
    final checkDigit = _calculateEAN13CheckDigit(barcodeWithoutCheck);
    
    return '$barcodeWithoutCheck$checkDigit';
  }
  
  /// Calculate EAN-13 check digit
  int _calculateEAN13CheckDigit(String barcode) {
    int sum = 0;
    for (int i = 0; i < 12; i++) {
      final digit = int.parse(barcode[i]);
      sum += digit * (i.isEven ? 1 : 3);
    }
    return (10 - (sum % 10)) % 10;
  }
  
  /// Parse weighable barcode to extract weight
  double? parseWeightFromBarcode(String barcode) {
    if (!barcode.startsWith('21') && !barcode.startsWith('22')) {
      return null; // Not a weighable barcode
    }
    
    try {
      final weightGrams = int.parse(barcode.substring(7, 12));
      return weightGrams / 1000; // Convert to KG
    } catch (e) {
      return null;
    }
  }
  
  /// Parse price-embedded barcode
  double? parsePriceFromBarcode(String barcode) {
    if (!barcode.startsWith('23') && !barcode.startsWith('24')) {
      return null; // Not a price-embedded barcode
    }
    
    try {
      final priceInHalalas = int.parse(barcode.substring(7, 12));
      return priceInHalalas / 100; // Convert to SAR
    } catch (e) {
      return null;
    }
  }
}
```

### Database Schema for Barcode Management

```sql
-- Barcode templates table
CREATE TABLE barcode_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100),
    template_type VARCHAR(50) NOT NULL, -- 'product', 'shelf', 'weighable', 'promotional'
    width_mm DECIMAL(5,2) NOT NULL,
    height_mm DECIMAL(5,2) NOT NULL,
    zpl_template TEXT,
    tspl_template TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Barcode print history (for audit)
CREATE TABLE barcode_print_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    user_id UUID REFERENCES users(id),
    product_id UUID REFERENCES products(id),
    barcode VARCHAR(50),
    template_id UUID REFERENCES barcode_templates(id),
    copies_printed INT NOT NULL,
    printer_name VARCHAR(100),
    printed_at TIMESTAMPTZ DEFAULT NOW()
);

-- Internal barcode sequence (for generating store barcodes)
CREATE TABLE internal_barcode_sequence (
    store_id UUID PRIMARY KEY REFERENCES stores(id),
    last_sequence INT NOT NULL DEFAULT 0,
    prefix VARCHAR(10) DEFAULT '200',
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Index for fast barcode lookups
CREATE INDEX idx_print_history_store_date ON barcode_print_history(store_id, printed_at);
CREATE INDEX idx_print_history_product ON barcode_print_history(product_id);
```

### Label Printer Configuration UI

```dart
// lib/screens/settings/label_printer_settings.dart
class LabelPrinterSettingsScreen extends StatefulWidget {
  @override
  State<LabelPrinterSettingsScreen> createState() => _LabelPrinterSettingsScreenState();
}

class _LabelPrinterSettingsScreenState extends State<LabelPrinterSettingsScreen> {
  final _ipController = TextEditingController();
  final _portController = TextEditingController(text: '9100');
  LabelLanguage _selectedLanguage = LabelLanguage.zpl;
  bool _isTestingConnection = false;
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Label Printer Settings / إعدادات طابعة الملصقات'),
      ),
      body: SingleChildScrollView(
        padding: EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Printer Type
            Text('Printer Type / نوع الطابعة', style: TextStyle(fontWeight: FontWeight.bold)),
            SizedBox(height: 8),
            DropdownButtonFormField<LabelLanguage>(
              value: _selectedLanguage,
              items: [
                DropdownMenuItem(value: LabelLanguage.zpl, child: Text('Zebra (ZPL)')),
                DropdownMenuItem(value: LabelLanguage.tspl, child: Text('TSC (TSPL)')),
                DropdownMenuItem(value: LabelLanguage.slcs, child: Text('Bixolon (SLCS)')),
              ],
              onChanged: (value) => setState(() => _selectedLanguage = value!),
              decoration: InputDecoration(border: OutlineInputBorder()),
            ),
            
            SizedBox(height: 24),
            
            // IP Address
            Text('Printer IP Address / عنوان IP', style: TextStyle(fontWeight: FontWeight.bold)),
            SizedBox(height: 8),
            TextField(
              controller: _ipController,
              decoration: InputDecoration(
                hintText: '192.168.1.100',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.number,
            ),
            
            SizedBox(height: 16),
            
            // Port
            Text('Port / المنفذ', style: TextStyle(fontWeight: FontWeight.bold)),
            SizedBox(height: 8),
            TextField(
              controller: _portController,
              decoration: InputDecoration(
                hintText: '9100',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.number,
            ),
            
            SizedBox(height: 24),
            
            // Test Connection
            Row(
              children: [
                ElevatedButton.icon(
                  icon: _isTestingConnection 
                      ? SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : Icon(Icons.wifi_tethering),
                  label: Text('Test Connection'),
                  onPressed: _isTestingConnection ? null : _testConnection,
                ),
                SizedBox(width: 16),
                ElevatedButton.icon(
                  icon: Icon(Icons.print),
                  label: Text('Print Test Label'),
                  onPressed: _printTestLabel,
                ),
              ],
            ),
            
            SizedBox(height: 32),
            
            // Default Label Settings
            Text('Default Label Settings', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            SizedBox(height: 16),
            
            _buildLabelSizeSelector(),
            
            SizedBox(height: 32),
            
            // Save Button
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                onPressed: _saveSettings,
                child: Text('Save Settings / حفظ الإعدادات'),
              ),
            ),
          ],
        ),
      ),
    );
  }
  
  Widget _buildLabelSizeSelector() {
    return Wrap(
      spacing: 16,
      runSpacing: 16,
      children: [
        _buildSizeOption('30x20mm', 30, 20),
        _buildSizeOption('40x30mm', 40, 30),
        _buildSizeOption('50x30mm', 50, 30),
        _buildSizeOption('60x40mm', 60, 40),
        _buildSizeOption('Custom', 0, 0),
      ],
    );
  }
  
  Widget _buildSizeOption(String label, double width, double height) {
    return ChoiceChip(
      label: Text(label),
      selected: false,
      onSelected: (_) {},
    );
  }
  
  Future<void> _testConnection() async {
    setState(() => _isTestingConnection = true);
    
    try {
      final socket = await Socket.connect(
        _ipController.text,
        int.parse(_portController.text),
        timeout: Duration(seconds: 5),
      );
      await socket.close();
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('✅ Connection successful!'), backgroundColor: Colors.green),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('❌ Connection failed: $e'), backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _isTestingConnection = false);
    }
  }
  
  Future<void> _printTestLabel() async {
    // Print a test label
  }
  
  void _saveSettings() {
    // Save to local storage
  }
}
```

### Hardware Integration Summary (Updated)

```
┌─────────────────────────────────────────────────────────────────┐
│              LABEL PRINTER INTEGRATION SUMMARY                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  SUPPORTED PRINTERS:                                           │
│  ───────────────────                                           │
│  • Zebra: ZD220, ZD420, ZD620, GK/GC series (ZPL)             │
│  • TSC: TTP-244, TE200/300, DA200 (TSPL)                       │
│  • Bixolon: SLP-DX220, SLP-TX400 (SLCS/ZPL)                    │
│  • Godex: G500, G530, EZ-series (EZPL/ZPL)                     │
│                                                                 │
│  CONNECTION OPTIONS:                                           │
│  ───────────────────                                           │
│  • Network/Ethernet (recommended): Port 9100                   │
│  • USB: Via printer driver or raw USB                          │
│  • Serial: RS-232 for legacy printers                          │
│                                                                 │
│  LABEL TYPES:                                                  │
│  ────────────                                                  │
│  • Product labels (barcode + name + price)                     │
│  • Shelf labels (large price display)                          │
│  • Weighable labels (weight + total price)                     │
│  • Promotional labels (was/now pricing)                        │
│                                                                 │
│  BARCODE FORMATS:                                              │
│  ────────────────                                              │
│  • EAN-13 (standard retail)                                    │
│  • EAN-8 (small products)                                      │
│  • Code 128 (alphanumeric)                                     │
│  • QR Code (extended data)                                     │
│  • Internal barcodes (200-prefix)                              │
│  • Weighable barcodes (21/22-prefix)                           │
│                                                                 │
│  FLUTTER PACKAGES:                                             │
│  ─────────────────                                             │
│  • barcode: Generate barcode data                              │
│  • pdf: Generate PDF labels                                    │
│  • printing: System printer support                            │
│  • Network raw print: Direct ZPL/TSPL                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## �🎯 Revised Recommendation

Given:
1. You want to **sell** this as a commercial product
2. It must work **offline** and as a **desktop app**
3. **ZATCA Phase 2** is mandatory in Saudi Arabia
4. You need to integrate with **existing hardware** (Bixolon, etc.)
5. You want **Thawani integration** as a differentiator

---

## 🆚 Flutter vs Tauri for Desktop POS

Since you already use Flutter for your mobile apps, this is an excellent question!

### Head-to-Head Comparison

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUTTER vs TAURI COMPARISON                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  CRITERIA              │ FLUTTER         │ TAURI              │
│  ─────────────────────────────────────────────────────────────│
│  Your Experience       │ ✅ You know it! │ ❌ New (Rust + JS) │
│  App Size              │ ⚠️ 50-100MB     │ ✅ 10-20MB         │
│  Performance           │ ✅ Native       │ ✅ Native          │
│  UI Quality            │ ✅ Beautiful    │ ✅ Web (flexible)  │
│  Desktop Maturity      │ ⚠️ Good (newer) │ ⚠️ Good (newer)    │
│  Mobile + Desktop      │ ✅ Same code    │ ❌ Separate        │
│  Printer Libraries     │ ⚠️ Exist        │ ✅ Mature          │
│  Serial Port Access    │ ⚠️ Plugin       │ ✅ Native Rust     │
│  ZATCA Crypto          │ ⚠️ FFI needed   │ ✅ Rust native     │
│  SQLite Offline        │ ✅ sqflite      │ ✅ rusqlite        │
│  Development Speed     │ ✅ Hot reload   │ ✅ Hot reload      │
│  Team Hiring           │ ✅ Easier       │ ⚠️ Rust is rare    │
│  Long-term Support     │ ✅ Google       │ ⚠️ Community       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Flutter Desktop: The Case FOR It

```
┌─────────────────────────────────────────────────────────────────┐
│                 WHY FLUTTER MAKES SENSE FOR YOU                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. YOU ALREADY KNOW FLUTTER                                   │
│     ─────────────────────────────────────────────────────────  │
│     • Your mobile apps are Flutter                             │
│     • Your team has Dart experience                            │
│     • No learning curve for new framework                      │
│     • Can start immediately                                    │
│                                                                 │
│  2. SINGLE CODEBASE POTENTIAL                                  │
│     ─────────────────────────────────────────────────────────  │
│     • Desktop POS (Windows/macOS/Linux)                        │
│     • Tablet POS (for smaller stores)                          │
│     • Mobile companion app (manager on-the-go)                 │
│     • 70-80% code sharing possible                             │
│                                                                 │
│  3. EXCELLENT UI CAPABILITIES                                  │
│     ─────────────────────────────────────────────────────────  │
│     • Touch-optimized (POS touchscreens)                       │
│     • Beautiful animations                                     │
│     • RTL/Arabic built-in                                      │
│     • Responsive layouts                                       │
│                                                                 │
│  4. MATURE ECOSYSTEM                                           │
│     ─────────────────────────────────────────────────────────  │
│     • Large package ecosystem                                  │
│     • Good documentation                                       │
│     • Active community                                         │
│     • Google backing                                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Flutter POS-Related Packages

| Package | Purpose | Stars | Maturity |
|---------|---------|-------|----------|
| `esc_pos_printer` | ESC/POS thermal printing | 200+ | ⭐⭐⭐ |
| `esc_pos_utils` | ESC/POS command builder | 150+ | ⭐⭐⭐ |
| `flutter_thermal_printer` | Multi-brand printer support | 100+ | ⭐⭐⭐ |
| `flutter_blue_plus` | Bluetooth (BT printers) | 600+ | ⭐⭐⭐⭐ |
| `usb_serial` | USB serial communication | 100+ | ⭐⭐⭐ |
| `sqflite` | SQLite database | 2700+ | ⭐⭐⭐⭐⭐ |
| `drift` | SQLite ORM (offline) | 2000+ | ⭐⭐⭐⭐⭐ |
| `qr_flutter` | QR code generation | 600+ | ⭐⭐⭐⭐ |
| `pdf` | PDF generation | 1000+ | ⭐⭐⭐⭐ |
| `printing` | Print to system printers | 500+ | ⭐⭐⭐⭐ |

### Flutter Desktop Challenges for POS

```
┌─────────────────────────────────────────────────────────────────┐
│              FLUTTER DESKTOP CHALLENGES (Solvable)              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  CHALLENGE 1: USB Thermal Printer Access                       │
│  ────────────────────────────────────────                      │
│  • Problem: Direct USB HID/bulk transfer is tricky             │
│  • Solution: Use network printing (most Bixolon have ethernet) │
│  • Solution: Use Windows printer driver (print as raw)         │
│  • Solution: FFI to native code if needed                      │
│                                                                 │
│  CHALLENGE 2: ZATCA Cryptographic Signing                      │
│  ────────────────────────────────────────                      │
│  • Problem: Dart crypto libraries less mature                  │
│  • Solution: Use dart:ffi to call OpenSSL                      │
│  • Solution: Use platform channel to native code               │
│  • Solution: pointycastle package (pure Dart)                  │
│  • Note: ZATCA uses ECDSA, X.509 - all possible in Dart       │
│                                                                 │
│  CHALLENGE 3: App Size                                         │
│  ────────────────────────────                                  │
│  • Problem: Flutter desktop apps are 50-100MB                  │
│  • Reality: For POS, this doesn't really matter               │
│  • These are installed apps, not web downloads                 │
│  • Supermarkets have good computers                            │
│                                                                 │
│  CHALLENGE 4: Desktop Platform Maturity                        │
│  ───────────────────────────────────────                       │
│  • Problem: Desktop is newer than mobile                       │
│  • Reality: Flutter 3.x desktop is production-ready           │
│  • Windows is most mature, then macOS, then Linux              │
│  • For Saudi POS: Windows focus is fine                        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Flutter POS Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                FLUTTER POS ARCHITECTURE                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    FLUTTER APP                           │   │
│  │  ┌─────────────────────────────────────────────────────┐│   │
│  │  │                  UI LAYER                           ││   │
│  │  │  • POS Screen (product grid, cart, payment)         ││   │
│  │  │  • Reports & Analytics                              ││   │
│  │  │  • Settings & Configuration                         ││   │
│  │  │  • Arabic/English with RTL support                  ││   │
│  │  └─────────────────────────────────────────────────────┘│   │
│  │                          │                              │   │
│  │  ┌─────────────────────────────────────────────────────┐│   │
│  │  │              STATE MANAGEMENT                       ││   │
│  │  │  • Riverpod / Bloc for state                        ││   │
│  │  │  • Cart state, product state, sync state            ││   │
│  │  └─────────────────────────────────────────────────────┘│   │
│  │                          │                              │   │
│  │  ┌─────────────────────────────────────────────────────┐│   │
│  │  │              BUSINESS LOGIC                         ││   │
│  │  │  • ZATCA Service (invoice signing)                  ││   │
│  │  │  • Sync Service (offline queue)                     ││   │
│  │  │  • Print Service (receipt generation)               ││   │
│  │  │  • Thawani Integration Service                      ││   │
│  │  └─────────────────────────────────────────────────────┘│   │
│  │                          │                              │   │
│  │  ┌─────────────────────────────────────────────────────┐│   │
│  │  │              DATA LAYER                             ││   │
│  │  │  • Drift/SQLite (local offline DB)                  ││   │
│  │  │  • Dio (HTTP client for sync)                       ││   │
│  │  │  • Shared Preferences (settings)                    ││   │
│  │  └─────────────────────────────────────────────────────┘│   │
│  │                          │                              │   │
│  │  ┌─────────────────────────────────────────────────────┐│   │
│  │  │          PLATFORM CHANNELS / FFI                    ││   │
│  │  │  • USB Printer access (if needed)                   ││   │
│  │  │  • Native crypto (if pointycastle insufficient)     ││   │
│  │  │  • Serial port for scales                           ││   │
│  │  └─────────────────────────────────────────────────────┘│   │
│  └─────────────────────────────────────────────────────────────┘│
│                                                                 │
│  SHARED WITH MOBILE (70-80%):                                  │
│  • All business logic                                          │
│  • All data models                                             │
│  • Most UI widgets (responsive)                                │
│  • Sync and offline logic                                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Flutter Printer Integration Example

```dart
// Flutter ESC/POS Printing for Bixolon
import 'package:esc_pos_printer/esc_pos_printer.dart';
import 'package:esc_pos_utils/esc_pos_utils.dart';

class ReceiptPrinter {
  late NetworkPrinter _printer;
  
  // Connect to Bixolon network printer
  Future<bool> connect(String ip, {int port = 9100}) async {
    final profile = await CapabilityProfile.load();
    _printer = NetworkPrinter(PaperSize.mm80, profile);
    
    final result = await _printer.connect(ip, port: port);
    return result == PosPrintResult.success;
  }
  
  // Print receipt with ZATCA QR
  Future<void> printReceipt(Receipt receipt) async {
    // Store header (Arabic as image)
    final headerImage = await _renderArabicText(
      receipt.storeNameAr,
      fontSize: 32,
      bold: true,
    );
    _printer.image(headerImage);
    
    _printer.text(
      '─' * 32,
      styles: PosStyles(align: PosAlign.center),
    );
    
    // Items
    for (final item in receipt.items) {
      _printer.row([
        PosColumn(
          text: item.name,
          width: 6,
          styles: PosStyles(align: PosAlign.left),
        ),
        PosColumn(
          text: '${item.quantity}x${item.price}',
          width: 3,
          styles: PosStyles(align: PosAlign.right),
        ),
        PosColumn(
          text: item.total.toStringAsFixed(2),
          width: 3,
          styles: PosStyles(align: PosAlign.right),
        ),
      ]);
    }
    
    _printer.text('─' * 32);
    
    // Total
    _printer.row([
      PosColumn(text: 'المجموع / Total', width: 6),
      PosColumn(
        text: '${receipt.total.toStringAsFixed(2)} SAR',
        width: 6,
        styles: PosStyles(align: PosAlign.right, bold: true),
      ),
    ]);
    
    // VAT
    _printer.row([
      PosColumn(text: 'VAT 15%', width: 6),
      PosColumn(
        text: receipt.vat.toStringAsFixed(2),
        width: 6,
        styles: PosStyles(align: PosAlign.right),
      ),
    ]);
    
    // ZATCA QR Code
    _printer.feed(1);
    _printer.qrcode(
      receipt.zatcaQRData, // Base64 TLV encoded
      size: QRSize.Size6,
      align: PosAlign.center,
    );
    
    // Footer
    _printer.feed(1);
    _printer.text(
      'شكراً لزيارتكم',
      styles: PosStyles(align: PosAlign.center),
    );
    
    _printer.cut();
    _printer.disconnect();
  }
  
  // Render Arabic text as image (for full Arabic support)
  Future<img.Image> _renderArabicText(
    String text, {
    double fontSize = 24,
    bool bold = false,
  }) async {
    // Use Flutter's Canvas to render Arabic text
    // Then convert to image for printer
    // ... implementation
  }
}
```

### Flutter ZATCA Integration Example

```dart
// ZATCA Phase 2 in Flutter/Dart
import 'package:pointycastle/export.dart';
import 'dart:convert';
import 'dart:typed_data';

class ZatcaService {
  late ECPrivateKey _privateKey;
  late X509Certificate _certificate;
  
  // Initialize with device credentials
  Future<void> initialize(String privateKeyPem, String certPem) async {
    _privateKey = _parsePrivateKey(privateKeyPem);
    _certificate = _parseCertificate(certPem);
  }
  
  // Generate ZATCA-compliant invoice
  Future<ZatcaInvoice> generateInvoice(Sale sale) async {
    // 1. Build UBL 2.1 XML
    final xml = _buildUblXml(sale);
    
    // 2. Calculate invoice hash
    final hash = _calculateHash(xml);
    
    // 3. Sign with ECDSA
    final signature = await _signInvoice(hash);
    
    // 4. Generate QR code data (TLV format)
    final qrData = _generateQrTlv(
      sellerName: sale.storeName,
      vatNumber: sale.storeVatNumber,
      timestamp: sale.timestamp,
      total: sale.total,
      vat: sale.vatAmount,
      hash: hash,
      signature: signature,
    );
    
    return ZatcaInvoice(
      xml: xml,
      hash: hash,
      signature: signature,
      qrCode: base64Encode(qrData),
    );
  }
  
  // ECDSA signing (ZATCA uses secp256k1)
  Future<Uint8List> _signInvoice(Uint8List hash) async {
    final signer = ECDSASigner(SHA256Digest(), null);
    signer.init(true, PrivateKeyParameter<ECPrivateKey>(_privateKey));
    
    final signature = signer.generateSignature(hash) as ECSignature;
    
    // Convert to DER format
    return _signatureToDer(signature);
  }
  
  // TLV encoding for QR code
  Uint8List _generateQrTlv({
    required String sellerName,
    required String vatNumber,
    required DateTime timestamp,
    required double total,
    required double vat,
    required Uint8List hash,
    required Uint8List signature,
  }) {
    final buffer = BytesBuilder();
    
    // Tag 1: Seller name
    buffer.addByte(1);
    final sellerBytes = utf8.encode(sellerName);
    buffer.addByte(sellerBytes.length);
    buffer.add(sellerBytes);
    
    // Tag 2: VAT number
    buffer.addByte(2);
    final vatBytes = utf8.encode(vatNumber);
    buffer.addByte(vatBytes.length);
    buffer.add(vatBytes);
    
    // Tag 3: Timestamp (ISO 8601)
    buffer.addByte(3);
    final timeBytes = utf8.encode(timestamp.toIso8601String());
    buffer.addByte(timeBytes.length);
    buffer.add(timeBytes);
    
    // Tag 4: Total
    buffer.addByte(4);
    final totalBytes = utf8.encode(total.toStringAsFixed(2));
    buffer.addByte(totalBytes.length);
    buffer.add(totalBytes);
    
    // Tag 5: VAT
    buffer.addByte(5);
    final vatAmountBytes = utf8.encode(vat.toStringAsFixed(2));
    buffer.addByte(vatAmountBytes.length);
    buffer.add(vatAmountBytes);
    
    // Tag 6: Hash (Phase 2)
    buffer.addByte(6);
    buffer.addByte(hash.length);
    buffer.add(hash);
    
    // Tag 7: Signature (Phase 2)
    buffer.addByte(7);
    buffer.addByte(signature.length);
    buffer.add(signature);
    
    // Tag 8: Public key (Phase 2)
    buffer.addByte(8);
    final pubKeyBytes = _getPublicKeyBytes();
    buffer.addByte(pubKeyBytes.length);
    buffer.add(pubKeyBytes);
    
    return buffer.toBytes();
  }
}
```

---

## 🔄 Thawani Integration & Real-Time Sync (CORE FEATURE)

### This is Your Competitive Advantage!

```
┌─────────────────────────────────────────────────────────────────┐
│              WHY STORES WILL BUY YOUR POS                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  "Buy our POS and instantly get delivery capability"           │
│                                                                 │
│  OTHER POS SYSTEMS:                                            │
│  • Just manage in-store sales                                  │
│  • No delivery integration                                     │
│  • Store must manually update online menus                     │
│  • Prices out of sync = customer complaints                    │
│                                                                 │
│  YOUR POS + THAWANI:                                           │
│  • In-store POS + Delivery platform in ONE                     │
│  • Update price once → Everywhere updated                      │
│  • Add product once → Available for delivery                   │
│  • Stock synced → No overselling                               │
│  • Unified reporting (in-store + delivery)                     │
│                                                                 │
│  THIS IS THE KILLER FEATURE! 🎯                                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Sync Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    SYNC ARCHITECTURE                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐                           ┌─────────────────┐ │
│  │   POS APP   │                           │  THAWANI CLOUD  │ │
│  │  (Flutter)  │                           │    (Laravel)    │ │
│  │             │                           │                 │ │
│  │ ┌─────────┐ │      Real-time Sync       │ ┌─────────────┐ │ │
│  │ │Products │ │◄──────────────────────────►│ │  Products   │ │ │
│  │ │ Table   │ │                           │ │   Table     │ │ │
│  │ └─────────┘ │                           │ └─────────────┘ │ │
│  │             │                           │                 │ │
│  │ ┌─────────┐ │      Price Updates        │ ┌─────────────┐ │ │
│  │ │ Prices  │ │─────────────────────────► │ │   Prices    │ │ │
│  │ │         │ │                           │ │             │ │ │
│  │ └─────────┘ │                           │ └─────────────┘ │ │
│  │             │                           │                 │ │
│  │ ┌─────────┐ │      Stock Levels         │ ┌─────────────┐ │ │
│  │ │  Stock  │ │◄──────────────────────────►│ │   Stock     │ │ │
│  │ │         │ │   (bidirectional)         │ │             │ │ │
│  │ └─────────┘ │                           │ └─────────────┘ │ │
│  │             │                           │                 │ │
│  │ ┌─────────┐ │    Delivery Orders        │ ┌─────────────┐ │ │
│  │ │ Orders  │ │◄────────────────────────── │ │   Orders    │ │ │
│  │ │         │ │   (Thawani → POS)         │ │             │ │ │
│  │ └─────────┘ │                           │ └─────────────┘ │ │
│  │             │                           │                 │ │
│  └─────────────┘                           └─────────────────┘ │
│         │                                          │           │
│         │            ┌──────────────┐              │           │
│         │            │   CUSTOMER   │              │           │
│         │            │   APP        │              │           │
│         │            │  (Flutter)   │              │           │
│         │            └──────────────┘              │           │
│         │                   │                      │           │
│         │     Sees real-time prices & stock        │           │
│         └──────────────────────────────────────────┘           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### What Gets Synced

```
┌─────────────────────────────────────────────────────────────────┐
│                    SYNC DATA FLOWS                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  POS → THAWANI (Store updates, customers see)                  │
│  ─────────────────────────────────────────────                 │
│  • New products added                                          │
│  • Product name/description changes (AR/EN)                    │
│  • Price updates                                               │
│  • Category changes                                            │
│  • Product images                                              │
│  • Stock quantity changes                                      │
│  • Product availability (active/inactive)                      │
│  • Offers/discounts                                            │
│  • Barcode assignments                                         │
│                                                                 │
│  THAWANI → POS (Delivery orders appear in POS)                 │
│  ─────────────────────────────────────────────                 │
│  • New delivery orders                                         │
│  • Order status updates                                        │
│  • Customer delivery address                                   │
│  • Special instructions                                        │
│  • Payment confirmation                                        │
│  • Stock deductions from online sales                          │
│                                                                 │
│  BIDIRECTIONAL (Both can modify)                               │
│  ────────────────────────────────                              │
│  • Stock levels (POS sale OR online sale deducts)              │
│  • Order status (POS prepares, Thawani tracks delivery)        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Real-Time Sync Implementation

#### Recommended: REST API (Simple & Sufficient)

```
┌─────────────────────────────────────────────────────────────────┐
│              WHY REST API IS BETTER FOR YOUR CASE               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  YOUR ACTUAL VOLUME:                                           │
│  • 5-10 new products per day                                   │
│  • 20-30 price updates per day                                 │
│  • Initial bulk import once                                    │
│                                                                 │
│  REST API ADVANTAGES:                                          │
│  ✅ Simpler to implement                                       │
│  ✅ Easier to debug                                            │
│  ✅ Works better with offline queue                            │
│  ✅ No connection management                                   │
│  ✅ Standard HTTP - firewalls love it                          │
│  ✅ Retry logic is straightforward                             │
│  ✅ Less server resources                                      │
│                                                                 │
│  WEBSOCKET IS OVERKILL WHEN:                                   │
│  • Updates are infrequent (yours: ~50/day)                     │
│  • Real-time milliseconds don't matter                         │
│  • Offline-first is priority                                   │
│                                                                 │
│  WEBSOCKET MAKES SENSE FOR:                                    │
│  • Chat applications                                           │
│  • Live dashboards (100+ updates/second)                       │
│  • Gaming                                                      │
│  • Stock trading                                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### REST API Sync Service (Recommended)

```dart
// Flutter POS - Simple REST Sync Service with Security
import 'package:dio/dio.dart';
import 'package:connectivity_plus/connectivity_plus.dart';

class ThawaniSyncService {
  final Dio _dio;
  final LocalDatabase _localDb;
  final SecureStorage _secureStorage;
  final String baseUrl = 'https://api.thawani.sa/api/v2';
  
  // Sync happens on specific triggers, not constantly
  ThawaniSyncService(this._dio, this._localDb, this._secureStorage) {
    _setupSecurityInterceptors();
  }
  
  /// Configure security interceptors for all API calls
  void _setupSecurityInterceptors() {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        // Add authentication token from secure storage
        final token = await _secureStorage.read(key: 'api_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        
        // Add security headers
        options.headers.addAll({
          'X-Device-ID': await DeviceInfo.getDeviceId(),
          'X-Request-Timestamp': DateTime.now().toUtc().toIso8601String(),
          'X-App-Version': AppInfo.version,
        });
        
        handler.next(options);
      },
      onError: (error, handler) {
        // Handle 401 Unauthorized - token expired
        if (error.response?.statusCode == 401) {
          // Trigger re-authentication flow
          AuthService.handleTokenExpired();
        }
        handler.next(error);
      },
    ));
  }
  
  // ═══════════════════════════════════════════════════════════
  // SYNC TRIGGERS (When to sync)
  // ═══════════════════════════════════════════════════════════
  //
  // 1. When cashier adds new product → immediate sync
  // 2. When cashier updates price → immediate sync  
  // 3. When sale completes → sync stock delta
  // 4. On app startup → check for pending syncs
  // 5. Every 5 minutes → pull new delivery orders
  // 6. Manual sync button → full sync
  //
  // ═══════════════════════════════════════════════════════════
  
  // ─────────────────────────────────────────────────────────────
  // PRODUCT SYNC (POS → Thawani)
  // ─────────────────────────────────────────────────────────────
  
  Future<SyncResult> syncProduct(Product product) async {
    // Check connectivity first
    if (!await _isOnline()) {
      await _queueForLater(SyncAction.createProduct, product.toJson());
      return SyncResult.queued;
    }
    
    try {
      final response = await _dio.post(
        '$baseUrl/store/products',
        data: {
          'local_id': product.localId,
          'barcode': product.barcode,
          'name_ar': product.nameAr,
          'name_en': product.nameEn,
          'price': product.price,
          'category_id': product.categoryId,
          'stock_quantity': product.stockQuantity,
          'is_active': product.isActive,
        },
      );
      
      // Store Thawani's ID for future reference
      final thawaniId = response.data['thawani_id'];
      await _localDb.updateProductThawaniId(product.localId, thawaniId);
      
      return SyncResult.success;
    } on DioException catch (e) {
      if (_isNetworkError(e)) {
        await _queueForLater(SyncAction.createProduct, product.toJson());
        return SyncResult.queued;
      }
      return SyncResult.failed;
    }
  }
  
  Future<SyncResult> syncPriceUpdate(String productId, double newPrice) async {
    if (!await _isOnline()) {
      await _queueForLater(SyncAction.updatePrice, {
        'product_id': productId,
        'price': newPrice,
      });
      return SyncResult.queued;
    }
    
    try {
      await _dio.patch(
        '$baseUrl/store/products/$productId/price',
        data: {'price': newPrice},
      );
      return SyncResult.success;
    } on DioException catch (e) {
      if (_isNetworkError(e)) {
        await _queueForLater(SyncAction.updatePrice, {
          'product_id': productId,
          'price': newPrice,
        });
        return SyncResult.queued;
      }
      return SyncResult.failed;
    }
  }
  
  // Sync stock after each sale (delta-based)
  Future<SyncResult> syncSaleStockDelta(String productId, int soldQuantity) async {
    if (!await _isOnline()) {
      await _queueForLater(SyncAction.stockDelta, {
        'product_id': productId,
        'delta': -soldQuantity,
        'reason': 'pos_sale',
        'timestamp': DateTime.now().toIso8601String(),
      });
      return SyncResult.queued;
    }
    
    try {
      await _dio.post(
        '$baseUrl/store/products/$productId/stock-delta',
        data: {
          'delta': -soldQuantity,
          'reason': 'pos_sale',
        },
      );
      return SyncResult.success;
    } on DioException catch (e) {
      if (_isNetworkError(e)) {
        await _queueForLater(SyncAction.stockDelta, {
          'product_id': productId,
          'delta': -soldQuantity,
        });
        return SyncResult.queued;
      }
      return SyncResult.failed;
    }
  }
  
  // ─────────────────────────────────────────────────────────────
  // DELIVERY ORDERS (Thawani → POS)
  // ─────────────────────────────────────────────────────────────
  
  // Poll for delivery orders every 5 minutes (or on-demand)
  Future<List<DeliveryOrder>> fetchNewDeliveryOrders() async {
    if (!await _isOnline()) return [];
    
    try {
      final lastSync = await _localDb.getLastOrderSyncTime();
      
      final response = await _dio.get(
        '$baseUrl/store/orders',
        queryParameters: {
          'since': lastSync?.toIso8601String(),
          'status': 'pending,confirmed',
        },
      );
      
      final orders = (response.data['orders'] as List)
          .map((o) => DeliveryOrder.fromJson(o))
          .toList();
      
      // Save to local DB
      for (final order in orders) {
        await _localDb.saveDeliveryOrder(order);
      }
      
      await _localDb.setLastOrderSyncTime(DateTime.now());
      
      return orders;
    } catch (e) {
      return [];
    }
  }
  
  // ─────────────────────────────────────────────────────────────
  // OFFLINE QUEUE MANAGEMENT
  // ─────────────────────────────────────────────────────────────
  
  Future<void> _queueForLater(SyncAction action, Map<String, dynamic> data) async {
    await _localDb.addToSyncQueue(SyncQueueItem(
      action: action,
      data: data,
      createdAt: DateTime.now(),
      retryCount: 0,
    ));
  }
  
  // Called on app startup and when connectivity restored
  Future<void> processSyncQueue() async {
    if (!await _isOnline()) return;
    
    final pendingItems = await _localDb.getPendingSyncItems();
    
    for (final item in pendingItems) {
      try {
        switch (item.action) {
          case SyncAction.createProduct:
            await _dio.post('$baseUrl/store/products', data: item.data);
            break;
          case SyncAction.updatePrice:
            await _dio.patch(
              '$baseUrl/store/products/${item.data['product_id']}/price',
              data: {'price': item.data['price']},
            );
            break;
          case SyncAction.stockDelta:
            await _dio.post(
              '$baseUrl/store/products/${item.data['product_id']}/stock-delta',
              data: item.data,
            );
            break;
        }
        
        await _localDb.removeSyncQueueItem(item.id);
      } catch (e) {
        // Increment retry count, will try again later
        await _localDb.incrementRetryCount(item.id);
      }
    }
  }
  
  Future<bool> _isOnline() async {
    final result = await Connectivity().checkConnectivity();
    return result != ConnectivityResult.none;
  }
  
  bool _isNetworkError(DioException e) {
    return e.type == DioExceptionType.connectionTimeout ||
           e.type == DioExceptionType.connectionError ||
           e.type == DioExceptionType.unknown;
  }
}
```

#### Simple Sync Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    SIMPLE SYNC FLOW                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                    POS ACTION                            │  │
│  └──────────────────────────────────────────────────────────┘  │
│                            │                                    │
│                            ▼                                    │
│              ┌─────────────────────────┐                       │
│              │    Online?              │                       │
│              └─────────────────────────┘                       │
│                    │           │                               │
│                   YES          NO                              │
│                    │           │                               │
│                    ▼           ▼                               │
│           ┌──────────────┐  ┌──────────────┐                  │
│           │ Send to API  │  │ Save to      │                  │
│           │ immediately  │  │ offline queue│                  │
│           └──────────────┘  └──────────────┘                  │
│                    │           │                               │
│                    ▼           │                               │
│           ┌──────────────┐     │                              │
│           │   Success?   │     │                              │
│           └──────────────┘     │                              │
│              │        │        │                               │
│             YES       NO       │                               │
│              │        │        │                               │
│              ▼        ▼        │                               │
│           ┌────────┐ ┌────────┐│                              │
│           │ Done!  │ │ Queue  ││                              │
│           │        │ │ it     │◄┘                              │
│           └────────┘ └────────┘                               │
│                         │                                      │
│                         ▼                                      │
│              ┌─────────────────────────┐                       │
│              │ When online again:      │                       │
│              │ Process queue in order  │                       │
│              └─────────────────────────┘                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Delivery Order Polling (Background)

```dart
// Simple timer-based polling for delivery orders
class DeliveryOrderPoller {
  Timer? _timer;
  final ThawaniSyncService _syncService;
  final StreamController<DeliveryOrder> _orderController;
  
  DeliveryOrderPoller(this._syncService)
      : _orderController = StreamController.broadcast();
  
  Stream<DeliveryOrder> get newOrders => _orderController.stream;
  
  void start() {
    // Check immediately on start
    _checkForOrders();
    
    // Then every 5 minutes (adjust as needed)
    _timer = Timer.periodic(Duration(minutes: 5), (_) {
      _checkForOrders();
    });
  }
  
  Future<void> _checkForOrders() async {
    final orders = await _syncService.fetchNewDeliveryOrders();
    
    for (final order in orders) {
      _orderController.add(order);
      
      // Play sound for new orders
      if (order.status == 'pending') {
        AudioPlayer().play(AssetSource('sounds/new_order.mp3'));
      }
    }
  }
  
  // Manual refresh button
  Future<void> refreshNow() async {
    await _checkForOrders();
  }
  
  void stop() {
    _timer?.cancel();
  }
}
```

#### When to Use What

```
┌─────────────────────────────────────────────────────────────────┐
│               SYNC TIMING RECOMMENDATION                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  IMMEDIATE SYNC (as it happens):                               │
│  ───────────────────────────────                               │
│  • New product added → POST /products                          │
│  • Price changed → PATCH /products/{id}/price                  │
│  • Product deleted → DELETE /products/{id}                     │
│  • Sale completed → POST /products/{id}/stock-delta            │
│                                                                 │
│  PERIODIC SYNC (every 5 minutes):                              │
│  ─────────────────────────────────                             │
│  • Fetch new delivery orders                                   │
│  • Process offline queue                                       │
│                                                                 │
│  ON APP STARTUP:                                               │
│  ─────────────────                                             │
│  • Process any pending offline queue                           │
│  • Fetch delivery orders since last check                      │
│  • Check for any stock adjustments from online sales           │
│                                                                 │
│  MANUAL TRIGGER (sync button):                                 │
│  ────────────────────────────                                  │
│  • Full product list reconciliation                            │
│  • Good for troubleshooting                                    │
│                                                                 │
│  FIRST TIME / BULK IMPORT:                                     │
│  ──────────────────────────                                    │
│  • Use /products/bulk-sync endpoint                            │
│  • Show progress bar                                           │
│  • Can take several minutes for large catalogs                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

#### Alternative: WebSocket (Only if Needed Later)

```dart
// Flutter POS - WebSocket Sync Service
import 'package:web_socket_channel/web_socket_channel.dart';

class ThawaniSyncService {
  late WebSocketChannel _channel;
  final String storeId;
  final String apiToken;
  
  // Sync queue for offline support
  final _offlineQueue = <SyncEvent>[];
  bool _isOnline = true;
  
  ThawaniSyncService({required this.storeId, required this.apiToken});
  
  Future<void> connect() async {
    _channel = WebSocketChannel.connect(
      Uri.parse('wss://api.thawani.sa/ws/store/$storeId'),
      headers: {'Authorization': 'Bearer $apiToken'},
    );
    
    // Listen for incoming events (orders, stock updates)
    _channel.stream.listen(
      _handleIncomingEvent,
      onError: _handleDisconnect,
      onDone: _handleDisconnect,
    );
    
    _isOnline = true;
    await _flushOfflineQueue();
  }
  
  // Handle events from Thawani
  void _handleIncomingEvent(dynamic message) {
    final event = SyncEvent.fromJson(jsonDecode(message));
    
    switch (event.type) {
      case SyncEventType.newDeliveryOrder:
        _handleNewDeliveryOrder(event.data);
        break;
      case SyncEventType.stockUpdate:
        _handleStockUpdate(event.data);
        break;
      case SyncEventType.orderStatusChange:
        _handleOrderStatusChange(event.data);
        break;
    }
  }
  
  // ═══════════════════════════════════════════════════════════
  // PRODUCT SYNC (POS → Thawani)
  // ═══════════════════════════════════════════════════════════
  
  Future<void> syncNewProduct(Product product) async {
    final event = SyncEvent(
      type: SyncEventType.productCreated,
      timestamp: DateTime.now(),
      data: {
        'product_id': product.localId,
        'barcode': product.barcode,
        'name_ar': product.nameAr,
        'name_en': product.nameEn,
        'description_ar': product.descriptionAr,
        'description_en': product.descriptionEn,
        'price': product.price,
        'category_id': product.categoryId,
        'stock_quantity': product.stockQuantity,
        'unit': product.unit, // piece, kg, etc.
        'is_active': product.isActive,
        'image_base64': product.imageBase64, // or upload separately
      },
    );
    
    await _sendEvent(event);
  }
  
  Future<void> syncPriceUpdate(String productId, double newPrice) async {
    final event = SyncEvent(
      type: SyncEventType.priceUpdated,
      timestamp: DateTime.now(),
      data: {
        'product_id': productId,
        'new_price': newPrice,
        'effective_from': DateTime.now().toIso8601String(),
      },
    );
    
    await _sendEvent(event);
  }
  
  Future<void> syncStockChange(String productId, int newQuantity, String reason) async {
    final event = SyncEvent(
      type: SyncEventType.stockUpdated,
      timestamp: DateTime.now(),
      data: {
        'product_id': productId,
        'new_quantity': newQuantity,
        'reason': reason, // 'sale', 'adjustment', 'restock', 'damage'
      },
    );
    
    await _sendEvent(event);
  }
  
  // ═══════════════════════════════════════════════════════════
  // OFFLINE SUPPORT
  // ═══════════════════════════════════════════════════════════
  
  Future<void> _sendEvent(SyncEvent event) async {
    if (_isOnline) {
      try {
        _channel.sink.add(jsonEncode(event.toJson()));
        
        // Store in local DB as synced
        await _localDb.markAsSynced(event);
      } catch (e) {
        // Connection lost, queue for later
        _queueForLater(event);
      }
    } else {
      _queueForLater(event);
    }
  }
  
  void _queueForLater(SyncEvent event) {
    _offlineQueue.add(event);
    _localDb.saveToSyncQueue(event); // Persist to survive app restart
  }
  
  Future<void> _flushOfflineQueue() async {
    final pending = await _localDb.getPendingSyncEvents();
    
    for (final event in pending) {
      try {
        _channel.sink.add(jsonEncode(event.toJson()));
        await _localDb.markAsSynced(event);
      } catch (e) {
        break; // Stop if connection lost again
      }
    }
  }
  
  // ═══════════════════════════════════════════════════════════
  // DELIVERY ORDER HANDLING
  // ═══════════════════════════════════════════════════════════
  
  void _handleNewDeliveryOrder(Map<String, dynamic> data) {
    final order = DeliveryOrder(
      thawaniOrderId: data['order_id'],
      customerName: data['customer_name'],
      customerPhone: data['customer_phone'],
      deliveryAddress: data['delivery_address'],
      items: (data['items'] as List).map((i) => OrderItem.fromJson(i)).toList(),
      total: data['total'],
      paymentMethod: data['payment_method'],
      paymentStatus: data['payment_status'],
      specialInstructions: data['special_instructions'],
      createdAt: DateTime.parse(data['created_at']),
    );
    
    // Save to local DB
    _localDb.saveDeliveryOrder(order);
    
    // Notify UI (show on POS screen)
    _deliveryOrderController.add(order);
    
    // Play notification sound
    _notificationService.playDeliveryOrderSound();
    
    // Auto-print order ticket (optional)
    if (_settings.autoPrintDeliveryOrders) {
      _printerService.printDeliveryOrderTicket(order);
    }
  }
}
```

> **Note**: The WebSocket code above is kept for reference only. For your use case (5-10 products/day, 20-30 price updates), the REST API approach shown earlier is recommended.

### Thawani Backend API Updates

```php
// Laravel - New API endpoints for POS sync
// routes/api.php

Route::prefix('v2/store')->middleware(['auth:sanctum', 'store'])->group(function () {
    
    // ═══════════════════════════════════════════════════════════
    // PRODUCT SYNC ENDPOINTS
    // ═══════════════════════════════════════════════════════════
    
    // POS creates/updates products
    Route::post('/products', [StoreSyncController::class, 'createProduct']);
    Route::put('/products/{localId}', [StoreSyncController::class, 'updateProduct']);
    Route::patch('/products/{localId}/price', [StoreSyncController::class, 'updatePrice']);
    Route::patch('/products/{localId}/stock', [StoreSyncController::class, 'updateStock']);
    Route::delete('/products/{localId}', [StoreSyncController::class, 'deleteProduct']);
    
    // Bulk sync (for initial setup or recovery)
    Route::post('/products/bulk-sync', [StoreSyncController::class, 'bulkSync']);
    
    // ═══════════════════════════════════════════════════════════
    // ORDER SYNC ENDPOINTS
    // ═══════════════════════════════════════════════════════════
    
    // POS fetches delivery orders
    Route::get('/orders', [StoreSyncController::class, 'getOrders']);
    Route::get('/orders/{orderId}', [StoreSyncController::class, 'getOrder']);
    
    // POS updates order status
    Route::patch('/orders/{orderId}/status', [StoreSyncController::class, 'updateOrderStatus']);
    Route::post('/orders/{orderId}/ready', [StoreSyncController::class, 'markOrderReady']);
    
    // ═══════════════════════════════════════════════════════════
    // WEBHOOK FOR REAL-TIME (alternative to polling)
    // ═══════════════════════════════════════════════════════════
    
    Route::post('/webhook/configure', [StoreSyncController::class, 'configureWebhook']);
});
```

```php
// app/Http/Controllers/Api/V2/StoreSyncController.php

class StoreSyncController extends Controller
{
    public function createProduct(Request $request)
    {
        $validated = $request->validate([
            'local_id' => 'required|string',
            'barcode' => 'nullable|string',
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);
        
        $store = $request->user()->store;
        
        // Check if product already exists (by local_id or barcode)
        $product = Product::where('store_id', $store->id)
            ->where(function ($q) use ($validated) {
                $q->where('pos_local_id', $validated['local_id'])
                  ->orWhere('barcode', $validated['barcode']);
            })
            ->first();
        
        if ($product) {
            // Update existing
            $product->update([
                'name_ar' => $validated['name_ar'],
                'name_en' => $validated['name_en'],
                'price' => $validated['price'],
                'quantity' => $validated['stock_quantity'],
                'is_active' => $validated['is_active'] ?? true,
            ]);
        } else {
            // Create new
            $product = Product::create([
                'store_id' => $store->id,
                'pos_local_id' => $validated['local_id'],
                'barcode' => $validated['barcode'],
                'name_ar' => $validated['name_ar'],
                'name_en' => $validated['name_en'],
                'price' => $validated['price'],
                'quantity' => $validated['stock_quantity'],
                'category_id' => $validated['category_id'],
                'is_active' => $validated['is_active'] ?? true,
            ]);
        }
        
        // Broadcast to customer apps (they see updated catalog)
        broadcast(new ProductUpdated($product));
        
        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'thawani_id' => $product->id, // POS stores this for future updates
        ]);
    }
    
    public function updatePrice(Request $request, string $localId)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);
        
        $store = $request->user()->store;
        $product = Product::where('store_id', $store->id)
            ->where('pos_local_id', $localId)
            ->firstOrFail();
        
        $oldPrice = $product->price;
        $product->update(['price' => $validated['price']]);
        
        // Log price change for analytics
        PriceHistory::create([
            'product_id' => $product->id,
            'old_price' => $oldPrice,
            'new_price' => $validated['price'],
            'changed_by' => 'pos',
        ]);
        
        // Notify customers who have this in cart/wishlist
        broadcast(new ProductPriceChanged($product, $oldPrice));
        
        return response()->json(['success' => true]);
    }
    
    public function updateStock(Request $request, string $localId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string', // sale, adjustment, restock
        ]);
        
        $store = $request->user()->store;
        $product = Product::where('store_id', $store->id)
            ->where('pos_local_id', $localId)
            ->firstOrFail();
        
        $product->update(['quantity' => $validated['quantity']]);
        
        // If stock is low, maybe notify store owner
        if ($product->quantity <= $product->low_stock_threshold) {
            // Send notification
        }
        
        // If out of stock, update availability for customers
        if ($product->quantity <= 0) {
            broadcast(new ProductOutOfStock($product));
        }
        
        return response()->json(['success' => true]);
    }
    
    public function getOrders(Request $request)
    {
        $store = $request->user()->store;
        
        $orders = Order::where('store_id', $store->id)
            ->where('order_type', 'delivery')
            ->when($request->since, function ($q, $since) {
                $q->where('created_at', '>=', $since);
            })
            ->when($request->status, function ($q, $status) {
                $q->whereIn('status', explode(',', $status));
            })
            ->with(['items.product', 'customer', 'address'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        
        return response()->json([
            'orders' => $orders->map(fn($o) => [
                'order_id' => $o->id,
                'order_number' => $o->order_number,
                'customer_name' => $o->customer->name,
                'customer_phone' => $o->customer->phone,
                'delivery_address' => $o->address->full_address,
                'items' => $o->items->map(fn($i) => [
                    'product_id' => $i->product->pos_local_id,
                    'name' => $i->product->name_ar,
                    'quantity' => $i->quantity,
                    'price' => $i->price,
                    'total' => $i->total,
                ]),
                'subtotal' => $o->subtotal,
                'delivery_fee' => $o->delivery_fee,
                'total' => $o->total,
                'payment_method' => $o->payment_method,
                'payment_status' => $o->payment_status,
                'status' => $o->status,
                'special_instructions' => $o->notes,
                'created_at' => $o->created_at->toIso8601String(),
            ]),
        ]);
    }
    
    public function markOrderReady(Request $request, int $orderId)
    {
        $store = $request->user()->store;
        $order = Order::where('store_id', $store->id)
            ->where('id', $orderId)
            ->firstOrFail();
        
        $order->update(['status' => 'ready_for_pickup']);
        
        // Notify delivery captain
        $order->captain?->notify(new OrderReadyForPickup($order));
        
        // Notify customer
        $order->customer->notify(new OrderBeingPrepared($order));
        
        return response()->json(['success' => true]);
    }
}
```

### Database Schema Updates for Sync

```sql
-- Add to products table
ALTER TABLE products ADD COLUMN pos_local_id VARCHAR(100) NULL;
ALTER TABLE products ADD COLUMN last_synced_at TIMESTAMP NULL;
ALTER TABLE products ADD COLUMN sync_version INT DEFAULT 1;

-- Create sync log table
CREATE TABLE sync_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    store_id BIGINT NOT NULL,
    entity_type ENUM('product', 'order', 'stock', 'price') NOT NULL,
    entity_id BIGINT NOT NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    source ENUM('pos', 'thawani', 'admin') NOT NULL,
    data JSON,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store_entity (store_id, entity_type, entity_id)
);

-- Price history for analytics
CREATE TABLE price_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    old_price DECIMAL(10,2) NOT NULL,
    new_price DECIMAL(10,2) NOT NULL,
    changed_by ENUM('pos', 'admin', 'api') NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id)
);
```

### Conflict Resolution Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                 CONFLICT RESOLUTION RULES                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  SCENARIO: Same product updated in POS and Thawani admin       │
│  ─────────────────────────────────────────────────────────────│
│                                                                 │
│  RULE 1: Last Write Wins (with version tracking)               │
│  • Each update increments sync_version                         │
│  • Higher version number wins                                  │
│  • Timestamp as tiebreaker                                     │
│                                                                 │
│  RULE 2: Source Priority for Specific Fields                   │
│  • Price: POS wins (store owner knows their prices)            │
│  • Stock: Most recent actual count wins                        │
│  • Name/Description: POS wins (store's branding)               │
│  • Category: Either (usually set once)                         │
│                                                                 │
│  RULE 3: Stock is Additive/Subtractive                         │
│  • Don't sync absolute stock numbers                           │
│  • Sync "sold 5 units" or "restocked 100 units"               │
│  • Both systems apply delta to their stock                     │
│  • Prevents lost sales/overselling                             │
│                                                                 │
│  EXAMPLE:                                                      │
│  ─────────────────────────────────────────────────────────────│
│  POS stock: 100                                                │
│  Thawani stock: 100                                            │
│                                                                 │
│  Simultaneously:                                               │
│  • POS sells 3 items → sends "delta: -3"                      │
│  • Customer orders 2 online → Thawani applies "delta: -2"     │
│                                                                 │
│  Result:                                                       │
│  • POS receives Thawani's delta → 100 - 3 - 2 = 95            │
│  • Thawani receives POS delta → 100 - 2 - 3 = 95              │
│  • Both end up correct!                                        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Flutter POS - Delivery Order Screen

```dart
// Show delivery orders alongside regular POS sales
class DeliveryOrdersPanel extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<DeliveryOrder>>(
      stream: context.read<ThawaniSyncService>().deliveryOrderStream,
      builder: (context, snapshot) {
        final orders = snapshot.data ?? [];
        final pendingOrders = orders.where((o) => o.status == 'pending').toList();
        
        return Container(
          width: 350,
          decoration: BoxDecoration(
            color: Colors.orange.shade50,
            border: Border(left: BorderSide(color: Colors.orange, width: 2)),
          ),
          child: Column(
            children: [
              // Header with count badge
              Container(
                padding: EdgeInsets.all(16),
                color: Colors.orange,
                child: Row(
                  children: [
                    Icon(Icons.delivery_dining, color: Colors.white),
                    SizedBox(width: 8),
                    Text(
                      'طلبات التوصيل',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Spacer(),
                    if (pendingOrders.isNotEmpty)
                      CircleAvatar(
                        radius: 14,
                        backgroundColor: Colors.red,
                        child: Text(
                          '${pendingOrders.length}',
                          style: TextStyle(color: Colors.white, fontSize: 12),
                        ),
                      ),
                  ],
                ),
              ),
              
              // Order list
              Expanded(
                child: ListView.builder(
                  itemCount: orders.length,
                  itemBuilder: (context, index) {
                    final order = orders[index];
                    return DeliveryOrderCard(
                      order: order,
                      onAccept: () => _acceptOrder(context, order),
                      onReady: () => _markReady(context, order),
                      onPrint: () => _printOrderTicket(context, order),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
```

### Customer App Sees Real-Time Updates

```dart
// In customer app - prices update automatically
class ProductDetailScreen extends StatelessWidget {
  final String productId;
  
  @override
  Widget build(BuildContext context) {
    return StreamBuilder<Product>(
      // Real-time product updates via Firebase/WebSocket
      stream: ProductRepository.watchProduct(productId),
      builder: (context, snapshot) {
        final product = snapshot.data;
        if (product == null) return LoadingWidget();
        
        return Scaffold(
          body: Column(
            children: [
              // Product image
              CachedNetworkImage(imageUrl: product.imageUrl),
              
              // Name (Arabic)
              Text(product.nameAr, style: Theme.of(context).textTheme.headlineMedium),
              
              // Price - ALWAYS CURRENT from store's POS
              Text(
                '${product.price.toStringAsFixed(2)} ر.س',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                  color: Colors.green,
                ),
              ),
              
              // Stock status
              if (product.stockQuantity <= 0)
                Chip(
                  label: Text('غير متوفر'),
                  backgroundColor: Colors.red.shade100,
                )
              else if (product.stockQuantity < 10)
                Chip(
                  label: Text('كمية محدودة'),
                  backgroundColor: Colors.orange.shade100,
                ),
              
              // Add to cart (disabled if out of stock)
              ElevatedButton(
                onPressed: product.stockQuantity > 0
                    ? () => _addToCart(product)
                    : null,
                child: Text('أضف للسلة'),
              ),
            ],
          ),
        );
      },
    );
  }
}
```

---

### 📊 Final Recommendation: Flutter vs Tauri

```
┌─────────────────────────────────────────────────────────────────┐
│                    UPDATED RECOMMENDATION                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  FOR YOUR SPECIFIC SITUATION:                                  │
│                                                                 │
│  ✅ FLUTTER IS THE BETTER CHOICE                               │
│                                                                 │
│  REASONS:                                                      │
│  ───────                                                       │
│  1. You already know Flutter (huge advantage)                  │
│  2. Your team can start immediately                            │
│  3. Single codebase for desktop + tablet + mobile              │
│  4. Touch-optimized UI (POS touchscreens)                      │
│  5. Arabic/RTL built-in                                        │
│  6. Good printer packages exist                                │
│  7. ZATCA crypto is possible with pointycastle                 │
│  8. Easier to hire Flutter devs than Rust devs                 │
│  9. Faster time to market                                      │
│                                                                 │
│  WHEN TAURI WOULD BE BETTER:                                   │
│  ────────────────────────────                                  │
│  • If you need absolute minimum app size                       │
│  • If you have complex native hardware needs                   │
│  • If you need maximum cryptographic performance               │
│  • If you're building web-first with desktop secondary         │
│                                                                 │
│  YOUR PATH:                                                    │
│  ──────────                                                    │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Flutter Desktop (Windows primary)                      │   │
│  │    +                                                    │   │
│  │  Drift (SQLite for offline)                             │   │
│  │    +                                                    │   │
│  │  esc_pos_printer (Bixolon/thermal printing)             │   │
│  │    +                                                    │   │
│  │  pointycastle (ZATCA crypto)                            │   │
│  │    +                                                    │   │
│  │  Next.js Web Portal (for management dashboard)          │   │
│  │    +                                                    │   │
│  │  Laravel API (you know it, Thawani integration)         │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### My Strong Recommendation: Build Custom with Flutter

```
┌─────────────────────────────────────────────────────────────────┐
│                    FINAL RECOMMENDATION                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ❌ DON'T USE OPEN SOURCE POS AS BASE                          │
│                                                                 │
│  Why:                                                          │
│  • ZATCA Phase 2 requires rewriting core anyway                │
│  • Offline-first requires architectural changes                │
│  • License restrictions limit your business                    │
│  • No open source has Thawani integration                      │
│  • Time spent adapting ≈ Time spent building                   │
│                                                                 │
│  ✅ BUILD CUSTOM WITH FLUTTER:                                 │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │                                                           │ │
│  │  DESKTOP APP: Flutter Desktop (Windows Primary)           │ │
│  │  ├── Your team already knows Flutter                      │ │
│  │  ├── Single codebase: desktop + tablet + mobile           │ │
│  │  ├── Native performance via Skia engine                   │ │
│  │  ├── Beautiful touch-optimized UI                         │ │
│  │  └── Arabic/RTL support built-in                          │ │
│  │                                                           │ │
│  │  DATABASE: Drift (SQLite ORM for Flutter)                 │ │
│  │  ├── Full offline support                                 │ │
│  │  ├── Type-safe queries                                    │ │
│  │  └── Reactive streams for UI updates                      │ │
│  │                                                           │ │
│  │  PRINTER: esc_pos_printer / flutter_thermal_printer       │ │
│  │  ├── ESC/POS protocol support                             │ │
│  │  ├── Network & Bluetooth connections                      │ │
│  │  └── Bixolon compatible                                   │ │
│  │                                                           │ │
│  │  ZATCA: pointycastle + qr_flutter                         │ │
│  │  ├── ECDSA signing (pure Dart)                            │ │
│  │  ├── SHA-256 hashing                                      │ │
│  │  └── Base64 QR code generation                            │ │
│  │                                                           │ │
│  │  BARCODE: RawKeyboardListener (keyboard emulation)        │ │
│  │                                                           │ │
│  │  WEB PORTAL: Next.js for management dashboard             │ │
│  │                                                           │ │
│  │  CLOUD: PostgreSQL + REST API sync (Laravel)              │ │
│  │                                                           │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ✅ USE THESE FLUTTER PACKAGES:                                │
│                                                                 │
│  • drift - SQLite offline database                             │
│  • esc_pos_printer - Thermal printer communication             │
│  • pointycastle - ZATCA cryptography (ECDSA)                   │
│  • qr_flutter - QR code generation                             │
│  • dio - HTTP client for API sync                              │
│  • riverpod / bloc - State management                          │
│  • flutter_libserialport - Scale/serial devices                │
│  • intl - Internationalization (AR/EN)                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Alternative: If You MUST Use Open Source

If budget or time constraints require starting with open source, here's the path:

```
┌─────────────────────────────────────────────────────────────────┐
│           IF YOU MUST USE OPEN SOURCE (NOT RECOMMENDED)         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  OPTION 1: ERPNext + Customization                             │
│  ──────────────────────────────────                            │
│  • Has ZATCA Phase 2 already                                   │
│  • Requires learning Python/Frappe                             │
│  • Heavy system (needs good server)                            │
│  • Offline is complex (Frappe offline module)                  │
│  • Timeline: 4-6 months customization                          │
│  • Cost: Still 300-500K SAR for customization                 │
│                                                                 │
│  OPTION 2: OpenSourcePOS + Complete Overhaul                   │
│  ───────────────────────────────────────────                   │
│  • Use as inspiration, rebuild core                            │
│  • PHP is familiar                                             │
│  • But no desktop, no offline, no ZATCA                        │
│  • You'd basically rebuild 80% of it                           │
│  • Why not start fresh?                                        │
│                                                                 │
│  OPTION 3: Hybrid - Web POS + Electron Wrapper                 │
│  ─────────────────────────────────────────────                 │
│  • Build web POS (your PHP/Laravel skills)                     │
│  • Wrap in Electron for desktop                                │
│  • Use service worker for offline                              │
│  • Add ZATCA as PHP service                                    │
│  • Limitations: Browser limitations, larger app                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🖨️ Bixolon-Specific Integration Guide

Since you mentioned Bixolon specifically:

### Bixolon Printer Models Common in Saudi Arabia

| Model | Connection | Paper Width | Speed | Arabic |
|-------|------------|-------------|-------|--------|
| SRP-350III | USB, Serial, Ethernet | 80mm | 250mm/s | Via image |
| SRP-330II | USB, Serial | 80mm | 220mm/s | Via image |
| SRP-Q300 | USB, Ethernet, BT | 80mm | 220mm/s | Via image |
| SRP-E300 | USB, Ethernet | 80mm | 250mm/s | Via image |

### Bixolon Network Integration (Flutter/Dart)

**Recommended approach: Network printing (most Bixolon printers support Ethernet)**

```dart
// Flutter/Dart code for Bixolon printer via Network
import 'dart:io';
import 'dart:typed_data';
import 'package:esc_pos_printer/esc_pos_printer.dart';
import 'package:esc_pos_utils/esc_pos_utils.dart';
import 'package:image/image.dart' as img;

class BixolonPrinter {
  final String ip;
  final int port;
  NetworkPrinter? _printer;
  
  BixolonPrinter({required this.ip, this.port = 9100});
  
  /// Connect to Bixolon printer
  Future<bool> connect() async {
    try {
      final profile = await CapabilityProfile.load();
      _printer = NetworkPrinter(PaperSize.mm80, profile);
      
      final result = await _printer!.connect(ip, port: port);
      return result == PosPrintResult.success;
    } catch (e) {
      print('Connection error: $e');
      return false;
    }
  }
  
  /// Print receipt with Arabic text support
  Future<void> printReceipt(Receipt receipt) async {
    if (_printer == null) {
      throw Exception('Printer not connected');
    }
    
    // Center alignment
    _printer!.setStyles(const PosStyles(align: PosAlign.center));
    
    // Arabic text must be rendered as image
    // because ESC/POS doesn't support Arabic well
    final arabicImage = await _renderArabicText(receipt.storeNameAr);
    _printer!.image(arabicImage);
    
    _printer!.emptyLines(1);
    
    // Receipt header
    _printer!.text('فاتورة ضريبية مبسطة',
        styles: const PosStyles(bold: true));
    _printer!.text('Simplified Tax Invoice');
    
    _printer!.hr();
    
    // Items (left align)
    _printer!.setStyles(const PosStyles(align: PosAlign.left));
    for (final item in receipt.items) {
      _printer!.row([
        PosColumn(
          text: item.nameAr,
          width: 6,
        ),
        PosColumn(
          text: 'x${item.quantity}',
          width: 2,
          styles: const PosStyles(align: PosAlign.center),
        ),
        PosColumn(
          text: '${item.total} SAR',
          width: 4,
          styles: const PosStyles(align: PosAlign.right),
        ),
      ]);
    }
    
    _printer!.hr();
    
    // Totals
    _printer!.row([
      PosColumn(text: 'المجموع / Total:', width: 8),
      PosColumn(
        text: '${receipt.total} SAR',
        width: 4,
        styles: const PosStyles(align: PosAlign.right, bold: true),
      ),
    ]);
    
    _printer!.row([
      PosColumn(text: 'ضريبة / VAT (15%):', width: 8),
      PosColumn(
        text: '${receipt.vatAmount} SAR',
        width: 4,
        styles: const PosStyles(align: PosAlign.right),
      ),
    ]);
    
    _printer!.emptyLines(1);
    
    // ZATCA QR Code
    _printer!.setStyles(const PosStyles(align: PosAlign.center));
    _printer!.qrcode(receipt.zatcaQrData, size: QRSize.Size5);
    
    _printer!.emptyLines(1);
    _printer!.text('شكراً لزيارتكم');
    _printer!.text('Thank you for visiting');
    
    // Cut paper
    _printer!.cut();
    
    // Disconnect
    _printer!.disconnect();
  }
  
  /// Render Arabic text as image (ESC/POS Arabic workaround)
  Future<img.Image> _renderArabicText(String text) async {
    // Create image with Arabic text using Flutter Canvas
    // This is a simplified example - real implementation would
    // use ui.Canvas and TextPainter
    final image = img.Image(width: 400, height: 60);
    img.fill(image, color: img.ColorRgb8(255, 255, 255));
    // In real implementation: render Arabic using Flutter's text engine
    // then convert to img.Image format
    return image;
  }
  
  /// Open cash drawer (connected via RJ11)
  Future<void> openCashDrawer() async {
    if (_printer == null) return;
    _printer!.drawer();
  }
}

// Receipt model
class Receipt {
  final String storeNameAr;
  final String storeNameEn;
  final List<ReceiptItem> items;
  final double total;
  final double vatAmount;
  final String zatcaQrData;
  
  Receipt({
    required this.storeNameAr,
    required this.storeNameEn,
    required this.items,
    required this.total,
    required this.vatAmount,
    required this.zatcaQrData,
  });
}

class ReceiptItem {
  final String nameAr;
  final String nameEn;
  final int quantity;
  final double price;
  final double total;
  
  ReceiptItem({
    required this.nameAr,
    required this.nameEn,
    required this.quantity,
    required this.price,
    required this.total,
  });
}
```

### Alternative: USB Printing via Platform Channel

If network printing isn't available, you can use platform channels to access USB:

```dart
// Method channel for native USB access (if needed)
class UsbPrinterChannel {
  static const _channel = MethodChannel('com.yourapp/usb_printer');
  
  static Future<bool> printRaw(Uint8List data) async {
    try {
      final result = await _channel.invokeMethod('printRaw', {'data': data});
      return result == true;
    } catch (e) {
      print('USB print error: $e');
      return false;
    }
  }
}

// Windows native code (C++) would handle the actual USB communication
// But network printing is much simpler and recommended for Bixolon
```

---

#### Device Onboarding Flow
```
┌─────────────────────────────────────────────────────────────────┐
│                    ZATCA DEVICE ONBOARDING                      │
└─────────────────────────────────────────────────────────────────┘

Step 1: Generate Key Pair (on device)
┌─────────────────┐
│   POS Device    │ → Generate ECDSA key pair (secp256k1)
│                 │ → Store private key in encrypted keystore
│                 │ → Use Windows DPAPI / macOS Keychain
│                 │ → Never expose key in logs or memory dumps
└─────────────────┘

Step 2: Create CSR (Certificate Signing Request)
┌─────────────────┐
│   CSR Contains: │
│ • VAT number    │
│ • Device serial │
│ • Organization  │
│ • Branch ID     │
└─────────────────┘

Step 3: Get OTP from ZATCA Portal
┌─────────────────┐
│   ZATCA Portal  │ → Organization admin requests OTP
│                 │ → Valid for limited time
└─────────────────┘

Step 4: Submit Compliance Request
┌─────────────────┐    ┌─────────────────┐
│   POS App       │───►│  ZATCA API      │
│ • CSR           │    │                 │
│ • OTP           │    │ Returns:        │
│                 │    │ • CSID          │
│                 │    │ • Certificate   │
└─────────────────┘    └─────────────────┘

Step 5: Production CSID (after testing)
┌─────────────────┐    ┌─────────────────┐
│   POS App       │───►│  ZATCA API      │
│ • Compliance    │    │                 │
│   CSID          │    │ Returns:        │
│                 │    │ • Production    │
│                 │    │   CSID          │
└─────────────────┘    └─────────────────┘
```

#### Invoice Signing Implementation (Flutter/Dart)

```dart
import 'dart:convert';
import 'dart:typed_data';
import 'package:pointycastle/export.dart';
import 'package:crypto/crypto.dart';

/// ZATCA Invoice Signer using pointycastle for ECDSA
class ZatcaInvoiceSigner {
  final ECPrivateKey privateKey;
  final String certificate;
  String previousHash;
  int invoiceCounter;
  
  ZatcaInvoiceSigner({
    required this.privateKey,
    required this.certificate,
    required this.previousHash,
    required this.invoiceCounter,
  });
  
  /// Sign an invoice and generate ZATCA-compliant data
  Future<SignedInvoice> signInvoice(Invoice invoice) async {
    // 1. Generate UBL 2.1 XML
    final xml = _generateUblXml(invoice);
    
    // 2. Canonicalize XML (C14N)
    final canonicalXml = _canonicalize(xml);
    
    // 3. Hash the invoice (SHA-256)
    final invoiceHash = sha256.convert(utf8.encode(canonicalXml)).toString();
    
    // 4. Create signed properties
    final signedProps = _createSignedProperties(invoiceHash);
    
    // 5. Sign with ECDSA using pointycastle
    final signature = _signEcdsa(utf8.encode(signedProps));
    
    // 6. Create QR code data (TLV format)
    final qrData = _createQrTlv(invoice, invoiceHash, signature);
    final qrBase64 = base64.encode(qrData);
    
    // 7. Update chain
    previousHash = invoiceHash;
    invoiceCounter++;
    
    return SignedInvoice(
      xml: _embedSignature(xml, signature),
      hash: invoiceHash,
      qrCode: qrBase64,
      uuid: invoice.uuid,
    );
  }
  
  /// Sign data using ECDSA with SHA-256
  Uint8List _signEcdsa(List<int> data) {
    final signer = ECDSASigner(SHA256Digest());
    signer.init(true, PrivateKeyParameter<ECPrivateKey>(privateKey));
    
    final signature = signer.generateSignature(Uint8List.fromList(data))
        as ECSignature;
    
    // Encode signature in DER format (required by ZATCA)
    return _encodeDerSignature(signature);
  }
  
  /// Encode ECDSA signature in DER format
  Uint8List _encodeDerSignature(ECSignature signature) {
    // Convert BigInt r and s to bytes
    final rBytes = _bigIntToBytes(signature.r);
    final sBytes = _bigIntToBytes(signature.s);
    
    // Build DER sequence
    final der = <int>[];
    der.add(0x30); // SEQUENCE tag
    
    final content = <int>[];
    // r INTEGER
    content.add(0x02);
    content.add(rBytes.length);
    content.addAll(rBytes);
    // s INTEGER
    content.add(0x02);
    content.add(sBytes.length);
    content.addAll(sBytes);
    
    der.add(content.length);
    der.addAll(content);
    
    return Uint8List.fromList(der);
  }
  
  Uint8List _bigIntToBytes(BigInt value) {
    var hex = value.toRadixString(16);
    if (hex.length % 2 != 0) hex = '0$hex';
    
    final bytes = <int>[];
    for (var i = 0; i < hex.length; i += 2) {
      bytes.add(int.parse(hex.substring(i, i + 2), radix: 16));
    }
    
    // Add leading zero if high bit is set (for positive integer)
    if (bytes.isNotEmpty && bytes[0] >= 0x80) {
      bytes.insert(0, 0);
    }
    
    return Uint8List.fromList(bytes);
  }
  
  /// Create ZATCA TLV-encoded QR data
  Uint8List _createQrTlv(Invoice invoice, String hash, Uint8List signature) {
    final tlv = <int>[];
    
    // Tag 1: Seller Name
    tlv.addAll(_encodeTlv(1, utf8.encode(invoice.sellerName)));
    // Tag 2: VAT Number
    tlv.addAll(_encodeTlv(2, utf8.encode(invoice.vatNumber)));
    // Tag 3: Timestamp (ISO 8601)
    tlv.addAll(_encodeTlv(3, utf8.encode(invoice.timestamp)));
    // Tag 4: Total with VAT
    tlv.addAll(_encodeTlv(4, utf8.encode(invoice.total.toStringAsFixed(2))));
    // Tag 5: VAT Amount
    tlv.addAll(_encodeTlv(5, utf8.encode(invoice.vatAmount.toStringAsFixed(2))));
    // Tag 6: Invoice Hash (hex)
    tlv.addAll(_encodeTlv(6, utf8.encode(hash)));
    // Tag 7: ECDSA Signature
    tlv.addAll(_encodeTlv(7, signature));
    // Tag 8: Public Key (from certificate)
    tlv.addAll(_encodeTlv(8, _getPublicKeyBytes()));
    
    return Uint8List.fromList(tlv);
  }
  
  /// Encode TLV (Tag-Length-Value)
  List<int> _encodeTlv(int tag, List<int> value) {
    return [tag, value.length, ...value];
  }
  
  Uint8List _getPublicKeyBytes() {
    // Extract public key from certificate
    // Implementation depends on certificate format
    return Uint8List(0);
  }
  
  String _generateUblXml(Invoice invoice) {
    // Generate ZATCA-compliant UBL 2.1 XML
    // This would be a full XML builder
    return '';
  }
  
  String _canonicalize(String xml) {
    // C14N canonicalization
    return xml;
  }
  
  String _createSignedProperties(String hash) {
    return hash;
  }
  
  String _embedSignature(String xml, Uint8List signature) {
    return xml;
  }
}

/// Invoice model
class Invoice {
  final String uuid;
  final String sellerName;
  final String vatNumber;
  final String timestamp;
  final double total;
  final double vatAmount;
  final List<InvoiceItem> items;
  
  Invoice({
    required this.uuid,
    required this.sellerName,
    required this.vatNumber,
    required this.timestamp,
    required this.total,
    required this.vatAmount,
    required this.items,
  });
}

class InvoiceItem {
  final String name;
  final int quantity;
  final double unitPrice;
  final double vatRate;
  
  InvoiceItem({
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.vatRate,
  });
}

/// Signed invoice result
class SignedInvoice {
  final String xml;
  final String hash;
  final String qrCode;
  final String uuid;
  
  SignedInvoice({
    required this.xml,
    required this.hash,
    required this.qrCode,
    required this.uuid,
  });
}
```

#### Offline ZATCA Handling

```
┌─────────────────────────────────────────────────────────────────┐
│                   OFFLINE ZATCA WORKFLOW                        │
└─────────────────────────────────────────────────────────────────┘

SALE HAPPENS (OFFLINE)
        │
        ▼
┌─────────────────┐
│ Sign Invoice    │  ← Uses locally stored private key
│ Locally         │  ← Generates hash, QR code
│                 │  ← Increments counter
└────────┬────────┘
        │
        ▼
┌─────────────────┐
│ Store in Local  │  ← SQLite: zatca_pending_invoices
│ Queue           │  ← status = 'pending_report'
└────────┬────────┘
        │
        ▼
┌─────────────────┐
│ Print Receipt   │  ← QR code included
│ with QR         │  ← "Pending ZATCA Reporting"
└─────────────────┘

WHEN ONLINE (Within 24 hours for B2C)
        │
        ▼
┌─────────────────┐
│ Sync Engine     │
│ Processes Queue │
└────────┬────────┘
        │
        ▼
┌─────────────────┐    ┌─────────────────┐
│ Report to ZATCA │───►│  ZATCA API      │
│ API             │    │                 │
└────────┬────────┘    │ Validates:      │
        │              │ • Signature     │
        │              │ • Hash chain    │
        │              │ • Business data │
        │              └─────────────────┘
        │
        ▼
┌─────────────────┐
│ Update Status   │  ← 'reported' or 'rejected'
│ in Local DB     │  ← Store ZATCA response
└─────────────────┘
```

---

## ⚙️ Core Features

### Must-Have Features (MVP)

#### 1. Sales Processing
```
┌─────────────────────────────────────────────────────────────────┐
│                    CASHIER SCREEN                               │
├──────────────────────────────────┬──────────────────────────────┤
│                                  │                              │
│  ┌────────────────────────────┐  │  ┌────────────────────────┐  │
│  │ 🔍 Scan or Search          │  │  │     CART               │  │
│  │ [__________________] [🔎]  │  │  │                        │  │
│  └────────────────────────────┘  │  │  Milk 1L        ×2     │  │
│                                  │  │  6.00 × 2 = 12.00      │  │
│  ┌────────────────────────────┐  │  │  ─────────────────────  │  │
│  │ QUICK CATEGORIES          │  │  │  Bread               ×1 │  │
│  │ ┌─────┐ ┌─────┐ ┌─────┐  │  │  │  5.00 × 1 = 5.00       │  │
│  │ │Dairy│ │Bread│ │Drinks│  │  │  │  ─────────────────────  │  │
│  │ └─────┘ └─────┘ └─────┘  │  │  │  Pepsi 500ml        ×3  │  │
│  │ ┌─────┐ ┌─────┐ ┌─────┐  │  │  │  2.00 × 3 = 6.00       │  │
│  │ │Snack│ │Veg  │ │Fruit│  │  │  │                        │  │
│  │ └─────┘ └─────┘ └─────┘  │  │  │  ─────────────────────  │  │
│  └────────────────────────────┘  │  │                        │  │
│                                  │  │  Subtotal:    23.00    │  │
│  ┌────────────────────────────┐  │  │  VAT (15%):    3.45    │  │
│  │ RECENT/FAVORITES          │  │  │  ═══════════════════   │  │
│  │ ┌─────────────────────┐   │  │  │  TOTAL:       26.45    │  │
│  │ │ 🥛 Milk 1L     6.00 │   │  │  │                        │  │
│  │ │ 🍞 Bread       5.00 │   │  │  │  ┌──────┐ ┌──────┐    │  │
│  │ │ 🥤 Pepsi      2.00 │   │  │  │  │ CASH │ │ CARD │    │  │
│  │ └─────────────────────┘   │  │  │  └──────┘ └──────┘    │  │
│  └────────────────────────────┘  │  │  ┌────────────────┐    │  │
│                                  │  │  │   PAY (26.45)  │    │  │
│  [Hold] [Recall] [Discount]      │  │  └────────────────┘    │  │
│                                  │  │                        │  │
└──────────────────────────────────┴──────────────────────────────┘
```

#### 2. Inventory Management
- Stock levels per store
- Low stock alerts
- Stock adjustments with reasons
- Stock transfers between stores
- Purchase order management
- Receiving goods
- Inventory counting (full & partial)

#### 3. Product Management
- Products with multiple barcodes
- Categories hierarchy
- Price management (store-specific pricing)
- Product images
- Unit types (piece, kg, liter)
- Weighable items support

#### 4. User Management
- Role-based access (Owner, Manager, Cashier)
- PIN login for quick access
- Shift management
- Activity logs

#### 5. Reports
- Daily sales summary
- Product sales report
- Inventory valuation
- Cash flow report
- Tax report (for VAT returns)
- Employee performance

#### 6. Hardware Integration
- Barcode scanners (USB HID)
- Receipt printers (ESC/POS)
- Cash drawers
- Customer displays
- Weighing scales

### Nice-to-Have Features (Post-MVP)

- Customer loyalty program
- Promotions/discounts engine
- Gift cards
- Mobile app for inventory
- WhatsApp receipts
- Multi-currency
- Table management (for cafes)

---

## 🎨 Industry-Specific POS Views

### Business Type Configuration

When a store signs up, they select their **business type**. This determines:
- Default POS layout
- Available features
- Category templates
- Receipt format
- Required fields
- Specialized workflows

```
┌─────────────────────────────────────────────────────────────────┐
│              SUPPORTED BUSINESS TYPES                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  🛒 RETAIL                      🍽️ FOOD SERVICE                │
│  ─────────────                  ────────────────                │
│  • Supermarkets                 • Restaurants                   │
│  • Mini Markets                 • Cafes                         │
│  • Grocery Stores               • Fast Food                     │
│  • Convenience Stores           • Bakeries                      │
│                                 • Juice Bars                    │
│  💊 HEALTH & BEAUTY             • Food Trucks                   │
│  ────────────────                                               │
│  • Pharmacies                   🎁 SPECIALTY RETAIL             │
│  • Cosmetics Stores             ─────────────────               │
│  • Perfume Shops                • Gift Shops                    │
│  • Optical Stores               • Flower Shops                  │
│                                 • Bookstores                    │
│  🛠️ SERVICES                    • Electronics                   │
│  ──────────                     • Jewelry                       │
│  • Auto Parts                   • Clothing/Fashion              │
│  • Hardware Stores              • Sports & Outdoors             │
│  • Mobile Phone Shops           • Pet Stores                    │
│                                 • Toy Stores                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Business Type Database Schema

```sql
-- Business types table
CREATE TABLE business_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(50) UNIQUE NOT NULL,  -- 'supermarket', 'restaurant', 'pharmacy'
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    icon VARCHAR(50),  -- Material icon name
    color VARCHAR(7),  -- Hex color code
    category ENUM('retail', 'food_service', 'health_beauty', 'specialty', 'services') NOT NULL,
    default_pos_layout VARCHAR(50) DEFAULT 'standard',  -- Layout template
    features JSONB,  -- Enabled features for this type
    category_templates JSONB,  -- Pre-defined categories
    receipt_template VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Store has a business type
ALTER TABLE stores ADD COLUMN business_type_id UUID REFERENCES business_types(id);
ALTER TABLE stores ADD COLUMN pos_layout_preference VARCHAR(50);

-- Insert default business types
INSERT INTO business_types (code, name, name_ar, icon, color, category, default_pos_layout, features) VALUES
('supermarket', 'Supermarket', 'سوبرماركت', 'shopping_cart', '#4CAF50', 'retail', 'supermarket_grid', 
 '{"weighable_items": true, "barcode_required": true, "inventory": true, "expiry_tracking": true}'),
('minimarket', 'Mini Market', 'ميني ماركت', 'store', '#8BC34A', 'retail', 'compact_grid', 
 '{"weighable_items": true, "barcode_required": false, "inventory": true}'),
('restaurant', 'Restaurant', 'مطعم', 'restaurant', '#FF5722', 'food_service', 'restaurant_tables', 
 '{"tables": true, "kitchen_display": true, "modifiers": true, "courses": true}'),
('cafe', 'Cafe', 'كافيه', 'local_cafe', '#795548', 'food_service', 'cafe_quick', 
 '{"tables": true, "modifiers": true, "quick_items": true}'),
('fastfood', 'Fast Food', 'وجبات سريعة', 'fastfood', '#FF9800', 'food_service', 'fastfood_combo', 
 '{"combos": true, "modifiers": true, "quick_service": true}'),
('bakery', 'Bakery', 'مخبز', 'bakery_dining', '#D7CCC8', 'food_service', 'bakery_visual', 
 '{"weighable_items": true, "fresh_items": true, "daily_production": true}'),
('pharmacy', 'Pharmacy', 'صيدلية', 'local_pharmacy', '#2196F3', 'health_beauty', 'pharmacy_search', 
 '{"prescription": true, "expiry_tracking": true, "controlled_substances": true, "insurance": true}'),
('cosmetics', 'Cosmetics', 'مستحضرات تجميل', 'face', '#E91E63', 'health_beauty', 'beauty_gallery', 
 '{"samples": true, "loyalty": true, "consultations": true}'),
('flowers', 'Flower Shop', 'محل ورد', 'local_florist', '#E91E63', 'specialty', 'flowers_occasion', 
 '{"occasions": true, "arrangements": true, "delivery": true, "freshness_tracking": true}'),
('gifts', 'Gift Shop', 'محل هدايا', 'card_giftcard', '#9C27B0', 'specialty', 'gifts_occasion', 
 '{"gift_wrapping": true, "occasions": true, "cards": true}'),
('electronics', 'Electronics', 'إلكترونيات', 'devices', '#607D8B', 'specialty', 'electronics_specs', 
 '{"serial_tracking": true, "warranty": true, "trade_in": true}'),
('jewelry', 'Jewelry', 'مجوهرات', 'diamond', '#FFC107', 'specialty', 'jewelry_luxury', 
 '{"serial_tracking": true, "certification": true, "appraisal": true, "high_value": true}'),
('clothing', 'Clothing', 'ملابس', 'checkroom', '#3F51B5', 'specialty', 'fashion_sizes', 
 '{"sizes": true, "colors": true, "fitting_room": true, "returns": true}');
```

---

## 🛒 SUPERMARKET POS LAYOUTS (5 Designs)

### Design 1: Grid Layout (Default)
**Best for:** High-volume supermarkets with barcode scanners
**Focus:** Speed and efficiency

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🛒 SUPERMARKET POS - GRID LAYOUT                          [Cashier: أحمد]  │
├─────────────────────────────────────────────┬───────────────────────────────┤
│                                             │                               │
│  ┌─────────────────────────────────────┐   │  ┌───────────────────────────┐│
│  │ 🔍 [Scan or type barcode...    ] 🎤 │   │  │       CART (5 items)      ││
│  └─────────────────────────────────────┘   │  ├───────────────────────────┤│
│                                             │  │ حليب المراعي 1L      ×2  ││
│  ┌─────────────────────────────────────┐   │  │ 6.00 SAR      = 12.00    ││
│  │ CATEGORIES                          │   │  │───────────────────────────││
│  │ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐    │   │  │ خبز أبيض            ×1  ││
│  │ │ 🥛  │ │ 🍞  │ │ 🥤  │ │ 🍎  │    │   │  │ 3.50 SAR      = 3.50     ││
│  │ │Dairy│ │Bread│ │Drink│ │Fruit│    │   │  │───────────────────────────││
│  │ └─────┘ └─────┘ └─────┘ └─────┘    │   │  │ بيبسي 500ml         ×3  ││
│  │ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐    │   │  │ 2.00 SAR      = 6.00     ││
│  │ │ 🥩  │ │ 🧹  │ │ 🍬  │ │ ❄️  │    │   │  │───────────────────────────││
│  │ │Meat │ │Clean│ │Snack│ │Froze│    │   │  │ أرز بسمتي 5kg       ×1  ││
│  │ └─────┘ └─────┘ └─────┘ └─────┘    │   │  │ 28.00 SAR     = 28.00    ││
│  └─────────────────────────────────────┘   │  │───────────────────────────││
│                                             │  │ بيض 30 حبة          ×1  ││
│  ┌─────────────────────────────────────┐   │  │ 18.00 SAR     = 18.00    ││
│  │ FREQUENT ITEMS           [See All]  │   │  ├───────────────────────────┤│
│  │ ┌───────┐ ┌───────┐ ┌───────┐      │   │  │                           ││
│  │ │  🥛   │ │  🍞   │ │  🥚   │      │   │  │ Subtotal:        67.50   ││
│  │ │ حليب  │ │  خبز  │ │ بيض   │      │   │  │ VAT (15%):       10.13   ││
│  │ │ 6.00  │ │ 3.50  │ │18.00  │      │   │  │━━━━━━━━━━━━━━━━━━━━━━━━━││
│  │ └───────┘ └───────┘ └───────┘      │   │  │ TOTAL:           77.63   ││
│  │ ┌───────┐ ┌───────┐ ┌───────┐      │   │  │                           ││
│  │ │  🍚   │ │  🧈   │ │  💧   │      │   │  ├───────────────────────────┤│
│  │ │  أرز  │ │ زبدة  │ │  ماء  │      │   │  │ ┌─────────┐ ┌─────────┐  ││
│  │ │28.00  │ │12.00  │ │ 1.00  │      │   │  │ │  CASH   │ │  CARD   │  ││
│  │ └───────┘ └───────┘ └───────┘      │   │  │ │  نقد    │ │  بطاقة  │  ││
│  └─────────────────────────────────────┘   │  │ └─────────┘ └─────────┘  ││
│                                             │  │ ┌─────────────────────┐  ││
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐       │  │ │    PAY  77.63 SAR   │  ││
│  │ Hold │ │Recall│ │ Disc │ │Return│       │  │ │        ادفع         │  ││
│  └──────┘ └──────┘ └──────┘ └──────┘       │  │ └─────────────────────┘  ││
│                                             │  └───────────────────────────┘│
└─────────────────────────────────────────────┴───────────────────────────────┘
```

**Features:**
- Large barcode input field with voice search
- Category icons for quick navigation
- Frequent items section (auto-learned from sales)
- Right-side cart always visible
- Quick action buttons at bottom

---

### Design 2: Full Product Grid (Touch-Optimized)
**Best for:** Stores where employees browse products visually
**Focus:** Touch-first, minimal barcode usage

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🛒 SUPERMARKET POS - FULL GRID                   [🔍 Search] [📦 67 items] │
├─────────────────────────────────────────────────────────────────────────────┤
│ ┌───────────────────────────────────────────────────────────────────────┐   │
│ │ 🥛 Dairy │ 🍞 Bakery │ 🥤 Drinks │ 🍎 Produce │ 🥩 Meat │ ❄️ Frozen │ ALL│
│ └───────────────────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐          │
│  │   📷     │ │   📷     │ │   📷     │ │   📷     │ │   📷     │          │
│  │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│          │
│  │ حليب طازج│ │ حليب مبخر│ │  لبن    │ │ جبنة كيري│ │  زبادي  │          │
│  │ Fresh    │ │ Evap.   │ │  Laban  │ │ Cheese  │ │ Yogurt  │          │
│  │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│          │
│  │  6.00    │ │  4.50    │ │  5.00    │ │  8.00    │ │  3.50    │          │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘          │
│                                                                             │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐          │
│  │   📷     │ │   📷     │ │   📷     │ │   📷     │ │   📷     │          │
│  │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│          │
│  │ قشطة    │ │ حليب بودرة│ │ كريمة   │ │ جبنة شرائح│ │ لبنة    │          │
│  │ Cream   │ │ Powder  │ │ Cream   │ │ Slices  │ │ Labneh  │          │
│  │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│ │ ─────────│          │
│  │  7.00    │ │  32.00   │ │  12.00   │ │  9.50    │ │  6.00    │          │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘          │
│                                                                 [Page 1/5]  │
├─────────────────────────────────────────────────────────────────────────────┤
│  CART: 5 items                                              TOTAL: 77.63   │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │ حليب ×2 (12.00) │ خبز ×1 (3.50) │ بيبسي ×3 (6.00) │ [View Cart] [PAY] │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Large product tiles with images
- Category tabs at top
- Minimal cart view (expandable)
- Pagination for large catalogs
- Great for touchscreens

---

### Design 3: Split View (Balanced)
**Best for:** Medium supermarkets balancing speed and browsing
**Focus:** 50/50 products and cart

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🛒 SPLIT VIEW                                    [🔍] [📊] [⚙️] [Ahmed]   │
├──────────────────────────────────┬──────────────────────────────────────────┤
│        PRODUCTS (Left 50%)       │           CART (Right 50%)              │
├──────────────────────────────────┼──────────────────────────────────────────┤
│ ┌──────────────────────────────┐ │ ┌──────────────────────────────────────┐ │
│ │ 🔍 Search products...        │ │ │  Customer: [Walk-in Customer    ▼]  │ │
│ └──────────────────────────────┘ │ └──────────────────────────────────────┘ │
│                                  │                                          │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐     │ ┌──────────────────────────────────────┐ │
│ │All │ │Dairy│ │Meat│ │Veg │     │ │ Item              Qty    Price      │ │
│ └────┘ └────┘ └────┘ └────┘     │ ├──────────────────────────────────────┤ │
│                                  │ │ 🥛 حليب المراعي    │      │         │ │
│ ┌────────┐ ┌────────┐ ┌────────┐│ │    Almarai Milk    │ ×2   │  12.00  │ │
│ │  🥛    │ │  🧀    │ │  🥚    ││ │    [−] [2] [+]     │      │ [🗑️]   │ │
│ │ حليب   │ │ جبنة   │ │ بيض    ││ ├──────────────────────────────────────┤ │
│ │ 6.00   │ │ 12.00  │ │ 18.00  ││ │ 🍞 خبز أبيض       │      │         │ │
│ └────────┘ └────────┘ └────────┘│ │    White Bread     │ ×1   │   3.50  │ │
│ ┌────────┐ ┌────────┐ ┌────────┐│ │    [−] [1] [+]     │      │ [🗑️]   │ │
│ │  🥩    │ │  🍗    │ │  🐟    ││ ├──────────────────────────────────────┤ │
│ │ لحم    │ │ دجاج   │ │ سمك    ││ │ 🥤 بيبسي          │      │         │ │
│ │ 45.00  │ │ 22.00  │ │ 35.00  ││ │    Pepsi 500ml     │ ×3   │   6.00  │ │
│ └────────┘ └────────┘ └────────┘│ │    [−] [3] [+]     │      │ [🗑️]   │ │
│ ┌────────┐ ┌────────┐ ┌────────┐│ ├──────────────────────────────────────┤ │
│ │  🍎    │ │  🥬    │ │  🥕    ││ │                                      │ │
│ │ تفاح   │ │ خس     │ │ جزر    ││ │        [+ Add Discount]              │ │
│ │ 8.00/kg│ │ 4.00   │ │ 3.00/kg││ │        [+ Add Note]                  │ │
│ └────────┘ └────────┘ └────────┘│ │                                      │ │
│                                  │ ├──────────────────────────────────────┤ │
│ ┌──────────────────────────────┐ │ │ Subtotal:                   21.50   │ │
│ │ ⚖️ WEIGHABLE ITEMS           │ │ │ Discount:                    0.00   │ │
│ │ [Enter Weight: _____ kg]     │ │ │ VAT (15%):                   3.23   │ │
│ └──────────────────────────────┘ │ │━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━│ │
│                                  │ │ TOTAL:                      24.73   │ │
│                                  │ ├──────────────────────────────────────┤ │
│                                  │ │ ┌────────────────────────────────┐  │ │
│                                  │ │ │         PAY 24.73 SAR          │  │ │
│                                  │ │ └────────────────────────────────┘  │ │
│                                  │ │   [Cash]    [Card]    [Mixed]       │ │
├──────────────────────────────────┴──────────────────────────────────────────┤
│ [🔄 Sync] [📋 Orders] [📊 Reports] [⏸️ Hold] [↩️ Return] [❌ Void]         │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Equal space for browsing and cart
- Inline quantity adjustment
- Customer selection
- Discount and note options
- Weighable items section
- Status bar with quick actions

---

### Design 4: Express Checkout (Speed-Focused)
**Best for:** High-volume express lanes (< 10 items)
**Focus:** Barcode scanning speed

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ⚡ EXPRESS CHECKOUT                              Transaction #4521         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                                                                     │   │
│  │    ████████████████████████████████████████████████████████████    │   │
│  │    ▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌▌    │   │
│  │                     SCAN BARCODE                                   │   │
│  │               [ 6 2 8 1 0 0 1 2 3 4 5 6 ]                         │   │
│  │                                                                     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  LAST SCANNED:                                                      │   │
│  │  ┌─────────────────────────────────────────────────────────────┐   │   │
│  │  │  🥛  حليب المراعي 1L - Almarai Milk 1L              6.00   │   │   │
│  │  └─────────────────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│    ┌───────────────────┐    ┌───────────────────┐    ┌───────────────────┐ │
│    │                   │    │                   │    │                   │ │
│    │   ITEMS: 5        │    │   VAT: 10.13      │    │   TOTAL           │ │
│    │                   │    │                   │    │                   │ │
│    │                   │    │                   │    │   77.63           │ │
│    │                   │    │                   │    │   SAR             │ │
│    │                   │    │                   │    │                   │ │
│    └───────────────────┘    └───────────────────┘    └───────────────────┘ │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│    ┌─────────────────────────────────────────────────────────────────┐     │
│    │                                                                 │     │
│    │                    PAY NOW - ادفع الآن                          │     │
│    │                                                                 │     │
│    └─────────────────────────────────────────────────────────────────┘     │
│                                                                             │
│    [View Cart]     [Void Last]     [Void All]     [Need Help?]             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Massive barcode scanning area
- Last scanned item display
- Big totals display
- Minimal distractions
- One-click payment
- Customer-facing friendly

---

### Design 5: Self-Checkout Kiosk
**Best for:** Unattended self-checkout stations
**Focus:** Customer-friendly, step-by-step

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🛒 SELF CHECKOUT                                    [🌐 EN/عربي] [❓ Help] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│     ╔═══════════════════════════════════════════════════════════════╗      │
│     ║                                                               ║      │
│     ║         SCAN YOUR ITEMS - امسح منتجاتك                       ║      │
│     ║                                                               ║      │
│     ║           ┌─────────────────────────┐                        ║      │
│     ║           │                         │                        ║      │
│     ║           │     📸 SCAN HERE        │                        ║      │
│     ║           │     امسح هنا            │                        ║      │
│     ║           │                         │                        ║      │
│     ║           └─────────────────────────┘                        ║      │
│     ║                                                               ║      │
│     ║    Or type barcode: [________________] [Search 🔍]           ║      │
│     ║                                                               ║      │
│     ╚═══════════════════════════════════════════════════════════════╝      │
│                                                                             │
│  ┌───────────────────────────────────────┬─────────────────────────────┐   │
│  │         YOUR ITEMS (5)                │      TOTALS                 │   │
│  ├───────────────────────────────────────┤─────────────────────────────┤   │
│  │  ✓ Milk 1L              ×2   12.00   │                             │   │
│  │  ✓ Bread                ×1    3.50   │    Items:    5              │   │
│  │  ✓ Pepsi 500ml          ×3    6.00   │    Subtotal: 67.50          │   │
│  │  ✓ Rice 5kg             ×1   28.00   │    VAT:      10.13          │   │
│  │  ✓ Eggs 30              ×1   18.00   │                             │   │
│  │                                       │    ━━━━━━━━━━━━━━━━        │   │
│  │  [Remove Item] [Change Qty]           │    TOTAL:   77.63 SAR      │   │
│  └───────────────────────────────────────┴─────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                                                                     │   │
│  │   ┌───────────────┐   ┌───────────────┐   ┌───────────────┐        │   │
│  │   │ KEEP SCANNING │   │  I'M DONE     │   │ CALL STAFF    │        │   │
│  │   │ استمر بالمسح  │   │  انتهيت      │   │ اتصل بالموظف  │        │   │
│  │   └───────────────┘   └───────────────┘   └───────────────┘        │   │
│  │                                                                     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Bilingual (English/Arabic)
- Large touch targets
- Step-by-step guidance
- Help button always visible
- Customer-facing optimized
- Staff call button

---

## 🍽️ RESTAURANT POS LAYOUTS (3 Designs)

### Design 1: Table Management View
**Best for:** Dine-in restaurants with table service
**Focus:** Table status and order management

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🍽️ RESTAURANT POS - TABLE VIEW                    [Orders: 12] [Kitchen] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  FLOOR: Main Hall                     [Hall] [Terrace] [VIP Room]   │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│    ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐        │
│    │  T-01   │  │  T-02   │  │  T-03   │  │  T-04   │  │  T-05   │        │
│    │ ●●○○    │  │ ●●●●    │  │ ○○○○    │  │ ●●○○    │  │ ●●●○    │        │
│    │ 125 SAR │  │ 340 SAR │  │ EMPTY   │  │  85 SAR │  │ 210 SAR │        │
│    │ 0:45    │  │ 1:20    │  │         │  │ 0:15    │  │ 0:55    │        │
│    │ 🟢 OPEN │  │ 🟡 BILL │  │ 🔵 FREE │  │ 🟢 OPEN │  │ 🟠 FOOD │        │
│    └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘        │
│                                                                             │
│    ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐        │
│    │  T-06   │  │  T-07   │  │  T-08   │  │  T-09   │  │  T-10   │        │
│    │ ○○○○○○  │  │ ●●●●●●  │  │ ○○○○    │  │ ●○○○    │  │ ○○○○    │        │
│    │ EMPTY   │  │ 520 SAR │  │ EMPTY   │  │  45 SAR │  │ RESERVE │        │
│    │         │  │ 2:10    │  │         │  │ 0:05    │  │ 19:30   │        │
│    │ 🔵 FREE │  │ 🔴 LATE │  │ 🔵 FREE │  │ 🟢 NEW  │  │ ⚪ RESV │        │
│    └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘        │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│  LEGEND:  🔵 Available  🟢 Active Order  🟡 Waiting Bill  🟠 Food Ready    │
│           🔴 Long Wait  ⚪ Reserved      ●=Occupied Seat  ○=Empty Seat     │
├─────────────────────────────────────────────────────────────────────────────┤
│  QUICK ACTIONS:                                                             │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐          │
│  │ + New    │ │ Transfer │ │  Merge   │ │  Split   │ │ Takeaway │          │
│  │  Order   │ │  Table   │ │  Tables  │ │   Bill   │ │  Order   │          │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘          │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Visual floor plan
- Table status at a glance
- Occupancy indicators
- Timer for service duration
- Quick table actions
- Reservation support

---

### Design 2: Quick Order Entry
**Best for:** Fast-casual restaurants, order-at-counter
**Focus:** Menu categories and modifiers

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🍔 QUICK ORDER                           Table: Counter │ Order #1523     │
├────────────────────────────────────┬────────────────────────────────────────┤
│   MENU CATEGORIES                  │   CURRENT ORDER                        │
├────────────────────────────────────┼────────────────────────────────────────┤
│ ┌──────┐┌──────┐┌──────┐┌──────┐  │                                        │
│ │ 🍔   ││ 🍟   ││ 🥗   ││ 🍰   │  │  ┌────────────────────────────────────┐│
│ │Burger││Sides ││Salads││Desser│  │  │ Big Burger Combo           45.00  ││
│ └──────┘└──────┘└──────┘└──────┘  │  │   ├─ No Onions                     ││
│ ┌──────┐┌──────┐┌──────┐┌──────┐  │  │   ├─ Extra Cheese (+3.00)         ││
│ │ 🥤   ││ ☕   ││ 🧃   ││ 🍦   │  │  │   └─ Large Fries (upgrade)        ││
│ │Drinks││Coffee││ Juice││IceCrm│  │  ├────────────────────────────────────┤│
│ └──────┘└──────┘└──────┘└──────┘  │  │ Chicken Wrap                22.00  ││
│                                    │  │   └─ Spicy Sauce                   ││
│ ━━━━━━━ BURGERS ━━━━━━━           │  ├────────────────────────────────────┤│
│                                    │  │ Sprite Large                  8.00  ││
│ ┌──────────┐ ┌──────────┐         │  ├────────────────────────────────────┤│
│ │   🍔     │ │   🍔     │         │  │ Chocolate Cake                15.00  ││
│ │  Classic │ │   Big    │         │  │                                      ││
│ │  Burger  │ │  Burger  │         │  └────────────────────────────────────┘│
│ │   25.00  │ │   35.00  │         │                                        │
│ └──────────┘ └──────────┘         │  ┌────────────────────────────────────┐│
│ ┌──────────┐ ┌──────────┐         │  │                                    ││
│ │   🍔     │ │   🍔     │         │  │ Subtotal:                  90.00   ││
│ │ Chicken  │ │  Combo   │         │  │ VAT (15%):                 13.50   ││
│ │  Burger  │ │  Meal    │         │  │━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ ││
│ │   28.00  │ │   45.00  │         │  │ TOTAL:                    103.50   ││
│ └──────────┘ └──────────┘         │  │                                    ││
│                                    │  └────────────────────────────────────┘│
│ ┌─────────────────────────────┐   │                                        │
│ │ 🔍 Search menu...           │   │  ┌─────────┐  ┌─────────────────────┐ │
│ └─────────────────────────────┘   │  │  CLEAR  │  │    SEND TO KITCHEN  │ │
│                                    │  │         │  │     & PAY           │ │
│ [Modifiers] [Combos] [Specials]   │  └─────────┘  └─────────────────────┘ │
└────────────────────────────────────┴────────────────────────────────────────┘
```

**Features:**
- Visual menu categories
- Modifier selection
- Combo detection
- Kitchen send button
- Order customization visible
- Search functionality

---

### Design 3: Kitchen Display System (KDS) Integration
**Best for:** Full-service restaurants with kitchen coordination
**Focus:** Course management and timing

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  👨‍🍳 RESTAURANT POS + KDS VIEW                         [Live Kitchen Feed] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ TABLE T-05 │ Server: Mohammed │ Guests: 4 │ Time: 0:45 │ Open      │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────┬───────────────────────────────────┐   │
│  │         ORDER ENTRY             │         KITCHEN STATUS            │   │
│  ├─────────────────────────────────┼───────────────────────────────────┤   │
│  │                                 │                                   │   │
│  │  COURSE 1: APPETIZERS ✓ SENT   │  🟢 Hummus        - READY         │   │
│  │  ─────────────────────────────  │  🟢 Fattoush      - READY         │   │
│  │  ✓ Hummus             18.00    │  🟢 Mutabbal      - READY         │   │
│  │  ✓ Fattoush           22.00    │                                   │   │
│  │  ✓ Mutabbal           20.00    │  ────── FIRE COURSE 1? ──────    │   │
│  │                                 │  [FIRE APPETIZERS]                │   │
│  │  COURSE 2: MAINS ⏳ WAITING    │                                   │   │
│  │  ─────────────────────────────  │  🟡 Lamb Kabsa   - COOKING 8m    │   │
│  │  ○ Lamb Kabsa         65.00    │  🟡 Grilled Fish - COOKING 5m    │   │
│  │  ○ Grilled Fish       85.00    │  ⚪ Chicken Mandi - QUEUED        │   │
│  │  ○ Chicken Mandi      55.00    │  ⚪ Vegetable... - QUEUED         │   │
│  │  ○ Vegetable Biryani  45.00    │                                   │   │
│  │                                 │                                   │   │
│  │  COURSE 3: DESSERTS 📝 DRAFT   │  AVERAGE COOK TIME: 12 min        │   │
│  │  ─────────────────────────────  │                                   │   │
│  │  ○ Um Ali            25.00     │  ┌─────────────────────────────┐  │   │
│  │  ○ Arabic Coffee     12.00     │  │ RECALL │ RUSH │ 86 ITEM    │  │   │
│  │                                 │  └─────────────────────────────┘  │   │
│  │  [+ Add Course] [+ Add Item]   │                                   │   │
│  │                                 │                                   │   │
│  └─────────────────────────────────┴───────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ Subtotal: 347.00  │ VAT: 52.05  │ Service 10%: 34.70  │ TOTAL: 433.75│  │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  [Send to Kitchen]  [Print Check]  [Split Bill]  [Transfer]  [Close Table] │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Course-based ordering
- Real-time kitchen status
- Fire timing control
- Cook time estimates
- 86 (out of stock) support
- Rush order capability

---

## 💊 PHARMACY POS LAYOUT

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  💊 PHARMACY POS                                    [Pharmacist: Dr. Sara]  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 🔍 Search by: [Drug Name ▼] [_________________________________] 🔎  │   │
│  │              Name │ Barcode │ Active Ingredient │ Manufacturer       │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌───────────────────────────────────────┬─────────────────────────────┐   │
│  │  SEARCH RESULTS / CATEGORIES          │   PRESCRIPTION / CART       │   │
│  ├───────────────────────────────────────┼─────────────────────────────┤   │
│  │                                       │                             │   │
│  │  Categories:                          │  Customer: Ahmed Al-Rashid  │   │
│  │  ┌────────┐┌────────┐┌────────┐      │  Phone: 050-XXX-XXXX        │   │
│  │  │Pain    ││Cold &  ││Vitamins│      │  Insurance: Bupa (ID: XXX)  │   │
│  │  │Relief  ││Flu     ││        │      │  ─────────────────────────  │   │
│  │  └────────┘└────────┘└────────┘      │                             │   │
│  │  ┌────────┐┌────────┐┌────────┐      │  ℞ PRESCRIPTION ITEMS:      │   │
│  │  │Diabetes││Heart   ││Antibio-│      │  ┌─────────────────────────┐│   │
│  │  │        ││        ││tics    │      │  │ Amoxicillin 500mg      ││   │
│  │  └────────┘└────────┘└────────┘      │  │ Qty: 21 │ 45.00 SAR    ││   │
│  │                                       │  │ ⚠️ Rx Required          ││   │
│  │  ═══════════════════════════════     │  │ Dr: [___________]      ││   │
│  │                                       │  └─────────────────────────┘│   │
│  │  📦 Panadol Extra 500mg              │                             │   │
│  │     Stock: 150 │ Exp: 2027-03        │  OTC ITEMS:                 │   │
│  │     Price: 12.50 SAR                 │  ┌─────────────────────────┐│   │
│  │     [+ Add]                          │  │ Panadol Extra           ││   │
│  │  ───────────────────────────────     │  │ Qty: 2 │ 25.00 SAR     ││   │
│  │  📦 Panadol Cold & Flu               │  └─────────────────────────┘│   │
│  │     Stock: 85 │ Exp: 2026-08         │  ┌─────────────────────────┐│   │
│  │     Price: 18.00 SAR                 │  │ Vitamin D 1000 IU       ││   │
│  │     [+ Add]                          │  │ Qty: 1 │ 35.00 SAR     ││   │
│  │  ───────────────────────────────     │  └─────────────────────────┘│   │
│  │  📦 Paracetamol Generic 500mg        │                             │   │
│  │     Stock: 300 │ Exp: 2026-12        │  ─────────────────────────  │   │
│  │     Price: 5.00 SAR                  │  Subtotal:       105.00    │   │
│  │     [+ Add]                          │  Insurance:      -35.00    │   │
│  │                                       │  VAT (0%):         0.00    │   │
│  └───────────────────────────────────────┤  ═════════════════════════ │   │
│                                          │  TOTAL:          70.00    │   │
│  ⚠️ ALERTS:                              │                             │   │
│  • Check drug interactions               │  [💳 Insurance] [💵 Cash]  │   │
│  • Verify prescription validity          │                             │   │
│                                          │  [Print Label] [Pay]       │   │
└──────────────────────────────────────────┴─────────────────────────────────┘
```

**Pharmacy-Specific Features:**
- Prescription vs OTC separation
- Insurance integration
- Expiry date display
- Stock alerts
- Drug interaction warnings
- Doctor/prescription fields
- Controlled substance tracking
- Print medication labels

---

## 🌸 FLOWER SHOP POS LAYOUT

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🌸 FLOWER SHOP POS                          [💐 42 Arrangements Today]    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  OCCASION: [🎂 Birthday] [💒 Wedding] [💔 Sympathy] [❤️ Romance]    │   │
│  │            [🎓 Graduation] [🏥 Get Well] [🎉 Congrats] [All]        │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────┬───────────────────────────────┐   │
│  │      ARRANGEMENTS & FLOWERS         │        ORDER DETAILS          │   │
│  ├─────────────────────────────────────┼───────────────────────────────┤   │
│  │                                     │                               │   │
│  │  💐 READY ARRANGEMENTS              │  Type: [🎂 Birthday]          │   │
│  │  ┌─────────┐ ┌─────────┐ ┌───────┐ │                               │   │
│  │  │  [IMG]  │ │  [IMG]  │ │ [IMG] │ │  ┌───────────────────────────┐│   │
│  │  │ Classic │ │ Premium │ │Luxury │ │  │ Premium Rose Bouquet     ││   │
│  │  │  Rose   │ │  Rose   │ │ Mixed │ │  │ Red Roses x24            ││   │
│  │  │ 150 SAR │ │ 280 SAR │ │450 SAR│ │  │ + Greeting Card          ││   │
│  │  └─────────┘ └─────────┘ └───────┘ │  │ + Gift Wrapping          ││   │
│  │                                     │  │ = 320.00 SAR             ││   │
│  │  🌹 BUILD YOUR OWN                  │  └───────────────────────────┘│   │
│  │  Roses: [12▼] Color: [Red▼]        │                               │   │
│  │  Filler: [Baby's Breath▼]          │  ADD-ONS:                     │   │
│  │  [Preview] [Add to Order]          │  ☑️ Greeting Card (+15)       │   │
│  │                                     │  ☑️ Gift Wrapping (+25)      │   │
│  │  🎀 ADD-ONS                         │  ☐ Chocolate Box (+45)       │   │
│  │  ┌───────┐ ┌───────┐ ┌───────┐    │  ☐ Teddy Bear (+60)          │   │
│  │  │ Card  │ │ Wrap  │ │Chocol │    │                               │   │
│  │  │  15   │ │  25   │ │  45   │    │  ─────────────────────────    │   │
│  │  └───────┘ └───────┘ └───────┘    │  DELIVERY DETAILS:            │   │
│  │  ┌───────┐ ┌───────┐ ┌───────┐    │  📍 Riyadh, Al-Olaya         │   │
│  │  │ Teddy │ │Balloon│ │ Vase  │    │  📅 Tomorrow, 10 AM - 2 PM   │   │
│  │  │  60   │ │  35   │ │  80   │    │  📝 "Call before delivery"   │   │
│  │  └───────┘ └───────┘ └───────┘    │                               │   │
│  │                                     │  ─────────────────────────    │   │
│  └─────────────────────────────────────┤  Subtotal:       320.00      │   │
│                                        │  Delivery:        25.00      │   │
│  MESSAGE ON CARD:                      │  VAT:             51.75      │   │
│  ┌─────────────────────────────────┐  │  ═══════════════════════════ │   │
│  │ "Happy Birthday! 🎂 With love" │  │  TOTAL:          396.75      │   │
│  └─────────────────────────────────┘  │                               │   │
│                                        │  [🚗 Delivery] [🏪 Pickup]   │   │
│  📱 Send preview to: [+966 5XX XXX]   │  [Process Order]              │   │
└────────────────────────────────────────┴───────────────────────────────────┘
```

**Flower Shop-Specific Features:**
- Occasion-based browsing
- Build-your-own arrangements
- Add-ons and gifts
- Delivery scheduling
- Card message entry
- Photo preview to customer
- Freshness tracking
- Seasonal availability

---

## 🎁 GIFT SHOP POS LAYOUT

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🎁 GIFT SHOP POS                               [🎄 Holiday Season Mode]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ SHOP BY: [🎂 Occasion] [👤 Recipient] [💰 Budget] [🏷️ Category]    │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌───────────────────────────────────┬─────────────────────────────────┐   │
│  │     GIFT FINDER                   │       CART / GIFT REGISTRY      │   │
│  ├───────────────────────────────────┼─────────────────────────────────┤   │
│  │                                   │                                 │   │
│  │  👤 FOR WHOM?                     │  🎁 GIFT ORDER                  │   │
│  │  ┌────┐┌────┐┌────┐┌────┐┌────┐  │  ─────────────────────────────  │   │
│  │  │ 👨 ││ 👩 ││ 👦 ││ 👧 ││ 👴 │  │  Item 1: Leather Wallet        │   │
│  │  │Him ││Her ││Boy ││Girl││Elder│  │  🎀 Gift Wrap: Premium (+20)   │   │
│  │  └────┘└────┘└────┘└────┘└────┘  │  💌 Card: "Happy Eid!"          │   │
│  │                                   │  ─────────────────  185.00     │   │
│  │  💰 BUDGET RANGE?                 │                                 │   │
│  │  ○ Under 100  ○ 100-250          │  Item 2: Perfume Set            │   │
│  │  ● 250-500    ○ 500+             │  🎀 Gift Wrap: Standard (+10)   │   │
│  │                                   │  💌 Card: "Best Wishes"         │   │
│  │  ════════════════════════════    │  ─────────────────  320.00     │   │
│  │                                   │                                 │   │
│  │  📦 RECOMMENDATIONS               │  ═════════════════════════════ │   │
│  │  ┌──────────┐ ┌──────────┐       │                                 │   │
│  │  │  [IMG]   │ │  [IMG]   │       │  GIFT SERVICES:                 │   │
│  │  │ Premium  │ │ Leather  │       │  ☑️ Gift Wrapping      30.00   │   │
│  │  │  Watch   │ │  Bag     │       │  ☑️ Greeting Cards     10.00   │   │
│  │  │  450 SAR │ │  380 SAR │       │  ☐ Gift Box Upgrade    25.00   │   │
│  │  │ ⭐ 4.8   │ │ ⭐ 4.9   │       │  ☐ Same-day Delivery   35.00   │   │
│  │  └──────────┘ └──────────┘       │                                 │   │
│  │  ┌──────────┐ ┌──────────┐       │  ─────────────────────────────  │   │
│  │  │  [IMG]   │ │  [IMG]   │       │  Subtotal:           505.00    │   │
│  │  │ Perfume  │ │  Smart   │       │  Gift Services:       40.00    │   │
│  │  │   Set    │ │  Speaker │       │  VAT (15%):           81.75    │   │
│  │  │  320 SAR │ │  280 SAR │       │  ═══════════════════════════   │   │
│  │  │ ⭐ 4.7   │ │ ⭐ 4.5   │       │  TOTAL:              626.75    │   │
│  │  └──────────┘ └──────────┘       │                                 │   │
│  │                                   │  [🎁 Add Gift Receipt]          │   │
│  └───────────────────────────────────┤  [Process Order]                │   │
└──────────────────────────────────────┴─────────────────────────────────────┘
```

**Gift Shop-Specific Features:**
- Gift finder by recipient/occasion/budget
- Gift wrapping options
- Greeting card messages
- Gift receipts (no price shown)
- Registry integration
- Recommendations engine
- Seasonal themes

---

## 🍰 BAKERY POS LAYOUT

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  🍰 BAKERY POS                                    [Today's Production: 245] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ ⏰ FRESH TODAY: Croissants (6:00) │ Bread (7:30) │ Cakes (9:00)     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌───────────────────────────────────┬─────────────────────────────────┐   │
│  │      PRODUCTS                     │          CART                   │   │
│  ├───────────────────────────────────┼─────────────────────────────────┤   │
│  │                                   │                                 │   │
│  │  🥐 FRESH BAKED         [12 left] │  ┌───────────────────────────┐ │   │
│  │  ┌────────┐ ┌────────┐ ┌────────┐│  │ Croissant Plain   ×3      │ │   │
│  │  │  🥐   │ │  🥐    │ │  🥐   ││  │ @ 8.00 = 24.00            │ │   │
│  │  │ Plain  │ │Chocolat│ │Almond ││  └───────────────────────────┘ │   │
│  │  │ 8.00   │ │ 12.00  │ │ 14.00 ││  ┌───────────────────────────┐ │   │
│  │  │[5 left]│ │[3 left]│ │[4 left]│  │ Chocolate Croissant ×2    │ │   │
│  │  └────────┘ └────────┘ └────────┘│  │ @ 12.00 = 24.00           │ │   │
│  │                                   │  └───────────────────────────┘ │   │
│  │  🍞 BREADS             [Stock OK] │  ┌───────────────────────────┐ │   │
│  │  ┌────────┐ ┌────────┐ ┌────────┐│  │ Baguette           ×1     │ │   │
│  │  │  🍞   │ │  🥖    │ │  🍞   ││  │ @ 6.00 = 6.00             │ │   │
│  │  │ White  │ │Baguette│ │ Whole  ││  └───────────────────────────┘ │   │
│  │  │ 5.00   │ │ 6.00   │ │ Wheat  ││                                 │   │
│  │  │        │ │        │ │ 7.00   ││  ⚖️ WEIGHABLE ITEM:            │   │
│  │  └────────┘ └────────┘ └────────┘│  ┌───────────────────────────┐ │   │
│  │                                   │  │ Cookies (per kg)          │ │   │
│  │  🎂 CAKES              [Order Now]│  │ Weight: 0.350 kg          │ │   │
│  │  ┌────────┐ ┌────────┐ ┌────────┐│  │ @ 45.00/kg = 15.75        │ │   │
│  │  │  🎂   │ │  🎂    │ │  🎂   ││  └───────────────────────────┘ │   │
│  │  │Chocolat│ │Strawber│ │ Custom ││                                 │   │
│  │  │ 85.00  │ │ 95.00  │ │ [Order]││  ─────────────────────────────  │   │
│  │  │[2 left]│ │[1 left]│ │        ││  Subtotal:            69.75    │   │
│  │  └────────┘ └────────┘ └────────┘│  VAT (15%):           10.46    │   │
│  │                                   │  ═══════════════════════════   │   │
│  │  🍪 BY WEIGHT (45 SAR/kg)        │  TOTAL:               80.21    │   │
│  │  [Enter weight: _____ kg] [Add]  │                                 │   │
│  │                                   │  [Quick Pay] [Custom Order]    │   │
│  └───────────────────────────────────┴─────────────────────────────────┘   │
│                                                                             │
│  [📋 Custom Cake Order] [📦 Bulk Order] [🕐 Production Queue] [📊 Wastage] │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Bakery-Specific Features:**
- Fresh-baked time indicators
- Remaining stock display
- Weighable items support
- Custom cake orders
- Daily production tracking
- Wastage management
- Bulk order capability

---

## 📱 MOBILE PHONE SHOP POS LAYOUT

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  📱 MOBILE SHOP POS                              [IMEI Verification: ON]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 🔍 [Search by Model / IMEI / Serial Number...                    ]  │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌───────────────────────────────────┬─────────────────────────────────┐   │
│  │      PRODUCTS                     │      SALE DETAILS               │   │
│  ├───────────────────────────────────┼─────────────────────────────────┤   │
│  │                                   │                                 │   │
│  │  📱 PHONES    🎧 ACCESSORIES     │  📱 DEVICE:                     │   │
│  │  💻 TABLETS   🔌 CHARGERS        │  ┌───────────────────────────┐ │   │
│  │                                   │  │ iPhone 15 Pro Max 256GB  │ │   │
│  │  ┌──────────────────────────────┐│  │ Color: Natural Titanium  │ │   │
│  │  │ 📱 iPhone 15 Pro Max         ││  │ IMEI: 35912XXXXXXXXX     │ │   │
│  │  │    256GB - Natural Titanium  ││  │ ────────────────────────  │ │   │
│  │  │    Price: 5,299 SAR          ││  │ Price:         5,299.00  │ │   │
│  │  │    Stock: 3 units            ││  └───────────────────────────┘ │   │
│  │  │    [Select Unit ▼]           ││                                 │   │
│  │  │    ┌────────────────────────┐││  🎧 ACCESSORIES:               │   │
│  │  │    │ IMEI: 359121234567890 │││  ┌───────────────────────────┐ │   │
│  │  │    │ IMEI: 359121234567891 │││  │ MagSafe Charger    149.00 │ │   │
│  │  │    │ IMEI: 359121234567892 │││  │ Silicone Case      199.00 │ │   │
│  │  │    └────────────────────────┘││  │ AirPods Pro 2     949.00 │ │   │
│  │  └──────────────────────────────┘│  └───────────────────────────┘ │   │
│  │                                   │                                 │   │
│  │  🔋 WARRANTY & PROTECTION        │  📄 SERVICES:                   │   │
│  │  ┌────────────┐ ┌────────────┐  │  ☑️ Screen Protector   50.00   │   │
│  │  │ 1 Year    │ │ 2 Year     │  │  ☑️ Data Transfer      Free    │   │
│  │  │ Standard  │ │ Extended   │  │  ☐ AppleCare+       699.00    │   │
│  │  │ Included  │ │ +299 SAR   │  │                                 │   │
│  │  └────────────┘ └────────────┘  │  ─────────────────────────────  │   │
│  │  ┌────────────┐                 │  Subtotal:         6,646.00    │   │
│  │  │ Screen     │                 │  VAT (15%):          996.90    │   │
│  │  │ Protection │                 │  ═══════════════════════════   │   │
│  │  │ +149 SAR   │                 │  TOTAL:            7,642.90    │   │
│  │  └────────────┘                 │                                 │   │
│  └───────────────────────────────────┤  [💳 Finance] [💵 Full Pay]   │   │
│                                      │                                 │   │
│  TRADE-IN: [Scan old device]        │  [Print Invoice + Warranty]    │   │
└──────────────────────────────────────┴─────────────────────────────────────┘
```

**Mobile Shop-Specific Features:**
- IMEI tracking for each unit
- Serial number verification
- Warranty management
- Trade-in capability
- Finance/installment options
- Accessory bundling
- Data transfer service
- Protection plan upsell

---

## 💎 JEWELRY STORE POS LAYOUT

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  💎 JEWELRY POS                                    [Gold: 245.50 SAR/g]    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌───────────────────────────────────┬─────────────────────────────────┐   │
│  │      INVENTORY                    │      SALE                       │   │
│  ├───────────────────────────────────┼─────────────────────────────────┤   │
│  │                                   │                                 │   │
│  │  [💍 Rings] [📿 Necklaces]       │  Customer: Fatima Al-Harbi      │   │
│  │  [⌚ Watches] [💎 Loose Stones]  │  Phone: 055-XXX-XXXX            │   │
│  │                                   │  ─────────────────────────────  │   │
│  │  ┌────────────────────────────┐  │                                 │   │
│  │  │ 💍 Gold Ring 21K           │  │  💍 ITEM 1:                     │   │
│  │  │ SKU: GR-21K-0542          │  │  ┌───────────────────────────┐ │   │
│  │  │ Weight: 8.5g              │  │  │ Gold Ring 21K             │ │   │
│  │  │ ────────────────────────  │  │  │ Weight: 8.5g              │ │   │
│  │  │ Gold Value:    1,882.05   │  │  │ Gold Value:    1,882.05   │ │   │
│  │  │ Making Charge:   350.00   │  │  │ Making:          350.00   │ │   │
│  │  │ Stone Value:     500.00   │  │  │ Stone:           500.00   │ │   │
│  │  │ ════════════════════════  │  │  │ Total:         2,732.05   │ │   │
│  │  │ TOTAL:         2,732.05   │  │  │                           │ │   │
│  │  │                            │  │  │ [Certificate] [Photo]    │ │   │
│  │  │ [View Certificate]         │  │  └───────────────────────────┘ │   │
│  │  │ [Add to Sale]              │  │                                 │   │
│  │  └────────────────────────────┘  │  💎 ITEM 2:                     │   │
│  │                                   │  ┌───────────────────────────┐ │   │
│  │  ══════════════════════════════  │  │ Diamond Pendant           │ │   │
│  │                                   │  │ 1.2 Carat, VVS1, E Color │ │   │
│  │  ⚖️ WEIGH ITEM:                  │  │ Certificate: GIA          │ │   │
│  │  Current Weight: [____] grams    │  │ Total:        12,500.00   │ │   │
│  │  Karat: [21K ▼]                  │  └───────────────────────────┘ │   │
│  │  Gold Rate: 245.50 SAR/g         │                                 │   │
│  │  Calculated: _____ SAR           │  ─────────────────────────────  │   │
│  │                                   │  Subtotal:        15,232.05   │   │
│  │  GOLD RATES (Live):              │  VAT (15%):        2,284.81   │   │
│  │  24K: 268.00 │ 21K: 245.50      │  ═══════════════════════════   │   │
│  │  18K: 201.00 │ 14K: 156.33      │  TOTAL:           17,516.86   │   │
│  │                                   │                                 │   │
│  │  [🔍 Search by SKU/Certificate]  │  [💳 Card] [💵 Cash] [📦 Layaway]│  │
│  └───────────────────────────────────┴─────────────────────────────────┘   │
│                                                                             │
│  [📋 Appraisal] [🔄 Buy Back] [🔧 Repair Order] [📜 Certificate Print]     │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Jewelry-Specific Features:**
- Live gold rate integration
- Weight-based pricing
- Karat calculation
- Certificate management
- Making charge separation
- Stone valuation
- Buy-back tracking
- Layaway plans
- Appraisal services

---

## 🎯 Business Type Selection UI

When store registers, they see:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                             │
│       🏪 SELECT YOUR BUSINESS TYPE                                          │
│       ─────────────────────────────                                         │
│       This determines your POS layout and available features                │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                                                                       │ │
│  │   🛒 RETAIL                         🍽️ FOOD SERVICE                   │ │
│  │   ┌─────────────┐ ┌─────────────┐   ┌─────────────┐ ┌─────────────┐  │ │
│  │   │     🛒      │ │     🏪      │   │     🍽️      │ │     ☕      │  │ │
│  │   │ Supermarket │ │ Mini Market │   │ Restaurant  │ │    Cafe     │  │ │
│  │   │  سوبرماركت │ │ ميني ماركت │   │    مطعم    │ │   كافيه    │  │ │
│  │   └─────────────┘ └─────────────┘   └─────────────┘ └─────────────┘  │ │
│  │   ┌─────────────┐ ┌─────────────┐   ┌─────────────┐ ┌─────────────┐  │ │
│  │   │     🏬      │ │     🛍️      │   │     🍔      │ │     🍰      │  │ │
│  │   │Convenience  │ │   General   │   │  Fast Food  │ │   Bakery    │  │ │
│  │   │    بقالة   │ │    عام    │   │ وجبات سريعة │ │    مخبز    │  │ │
│  │   └─────────────┘ └─────────────┘   └─────────────┘ └─────────────┘  │ │
│  │                                                                       │ │
│  │   💊 HEALTH & BEAUTY                🎁 SPECIALTY                      │ │
│  │   ┌─────────────┐ ┌─────────────┐   ┌─────────────┐ ┌─────────────┐  │ │
│  │   │     💊      │ │     💄      │   │     🎁      │ │     🌸      │  │ │
│  │   │  Pharmacy   │ │  Cosmetics  │   │  Gift Shop  │ │ Flower Shop │  │ │
│  │   │   صيدلية   │ │ مستحضرات  │   │ محل هدايا │ │  محل ورد  │  │ │
│  │   └─────────────┘ └─────────────┘   └─────────────┘ └─────────────┘  │ │
│  │   ┌─────────────┐                   ┌─────────────┐ ┌─────────────┐  │ │
│  │   │     👓      │                   │     📱      │ │     💎      │  │ │
│  │   │   Optical   │                   │   Mobile    │ │   Jewelry   │  │ │
│  │   │    بصريات  │                   │   جوالات   │ │  مجوهرات  │  │ │
│  │   └─────────────┘                   └─────────────┘ └─────────────┘  │ │
│  │                                                                       │ │
│  │   🛠️ OTHER                                                            │ │
│  │   ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐   │ │
│  │   │     👔      │ │     🐾      │ │     📚      │ │     ⚙️      │   │ │
│  │   │  Clothing   │ │  Pet Store  │ │  Bookstore  │ │   Custom    │   │ │
│  │   │   ملابس   │ │حيوانات أليفة│ │   مكتبة    │ │    مخصص    │   │ │
│  │   └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘   │ │
│  │                                                                       │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│         [← Back]                           [Next: Choose POS Layout →]      │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎨 POS Layout Customization Database

```sql
-- POS layout templates per business type
CREATE TABLE pos_layout_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_type_id UUID REFERENCES business_types(id),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT,
    layout_code VARCHAR(50) UNIQUE NOT NULL,  -- 'supermarket_grid', 'restaurant_tables'
    preview_image_url TEXT,
    config JSONB NOT NULL,  -- Full layout configuration
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Store's selected layout
ALTER TABLE stores ADD COLUMN pos_layout_id UUID REFERENCES pos_layout_templates(id);

-- Layout configuration JSON structure
/*
{
  "layout_type": "split",  // grid, split, minimal, table_view
  "cart_position": "right", // right, bottom, floating
  "cart_width": 35,  // percentage
  "show_categories": true,
  "category_style": "tabs",  // tabs, sidebar, icons
  "product_display": "grid",  // grid, list, images
  "product_columns": 5,
  "show_images": true,
  "quick_actions": ["hold", "recall", "discount", "return"],
  "payment_buttons": ["cash", "card", "mixed"],
  "special_features": {
    "weighable_items": true,
    "table_management": false,
    "kitchen_display": false,
    "prescription_mode": false,
    "imei_tracking": false
  },
  "theme": {
    "primary_color": "#4CAF50",
    "secondary_color": "#2196F3",
    "font_size": "medium"
  }
}
*/
```

---

## 🔧 Implementing Multiple Layouts in Flutter

```dart
// Flutter - Dynamic POS Layout System
import 'package:flutter/material.dart';

enum BusinessType {
  supermarket,
  restaurant,
  pharmacy,
  bakery,
  flowerShop,
  giftShop,
  jewelry,
  mobileShop,
  // ... more
}

enum POSLayoutType {
  supermarketGrid,
  supermarketFullGrid,
  supermarketSplit,
  supermarketExpress,
  supermarketSelfCheckout,
  restaurantTables,
  restaurantQuickOrder,
  restaurantKDS,
  pharmacySearch,
  bakeryFresh,
  flowerOccasion,
  giftFinder,
  jewelryWeigh,
  mobileIMEI,
}

class POSLayoutFactory {
  static Widget buildLayout(POSLayoutType layout, POSState state) {
    switch (layout) {
      // Supermarket layouts
      case POSLayoutType.supermarketGrid:
        return SupermarketGridLayout(state: state);
      case POSLayoutType.supermarketFullGrid:
        return SupermarketFullGridLayout(state: state);
      case POSLayoutType.supermarketSplit:
        return SupermarketSplitLayout(state: state);
      case POSLayoutType.supermarketExpress:
        return SupermarketExpressLayout(state: state);
      case POSLayoutType.supermarketSelfCheckout:
        return SupermarketSelfCheckoutLayout(state: state);
      
      // Restaurant layouts
      case POSLayoutType.restaurantTables:
        return RestaurantTableLayout(state: state);
      case POSLayoutType.restaurantQuickOrder:
        return RestaurantQuickOrderLayout(state: state);
      case POSLayoutType.restaurantKDS:
        return RestaurantKDSLayout(state: state);
      
      // Specialty layouts
      case POSLayoutType.pharmacySearch:
        return PharmacySearchLayout(state: state);
      case POSLayoutType.bakeryFresh:
        return BakeryFreshLayout(state: state);
      case POSLayoutType.flowerOccasion:
        return FlowerOccasionLayout(state: state);
      case POSLayoutType.giftFinder:
        return GiftFinderLayout(state: state);
      case POSLayoutType.jewelryWeigh:
        return JewelryWeighLayout(state: state);
      case POSLayoutType.mobileIMEI:
        return MobileIMEILayout(state: state);
        
      default:
        return SupermarketGridLayout(state: state);
    }
  }
}

// Example: Supermarket Grid Layout widget
class SupermarketGridLayout extends StatelessWidget {
  final POSState state;
  
  const SupermarketGridLayout({required this.state, Key? key}) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Left side: Products
        Expanded(
          flex: 65,
          child: Column(
            children: [
              SearchBar(onSearch: state.searchProducts),
              CategoryTabs(categories: state.categories),
              Expanded(
                child: ProductGrid(
                  products: state.filteredProducts,
                  onProductTap: state.addToCart,
                ),
              ),
              QuickActionsBar(actions: ['hold', 'recall', 'discount', 'return']),
            ],
          ),
        ),
        // Right side: Cart
        Expanded(
          flex: 35,
          child: CartPanel(
            items: state.cartItems,
            totals: state.totals,
            onPayment: state.processPayment,
          ),
        ),
      ],
    );
  }
}

// Example: Restaurant Table Layout widget
class RestaurantTableLayout extends StatelessWidget {
  final POSState state;
  
  const RestaurantTableLayout({required this.state, Key? key}) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Floor selector
        FloorSelector(
          floors: state.floors,
          selectedFloor: state.currentFloor,
          onFloorChange: state.selectFloor,
        ),
        // Table grid
        Expanded(
          child: TableGrid(
            tables: state.tables,
            onTableTap: (table) => _openTableOrder(context, table),
          ),
        ),
        // Quick actions
        TableQuickActions(),
      ],
    );
  }
}
```

---

*This section enables Thawani POS to serve multiple industries with tailored experiences, increasing market reach and customer satisfaction.*

---

## 🔌 Integration APIs

### Thawani Integration (Flutter/Dart)

```dart
// Thawani Integration Module for Flutter POS
import 'package:dio/dio.dart';

/// Thawani order model
class ThawaniOrder {
  final String orderNum;        // THA-245-1001-B
  final String customerName;
  final String customerPhone;
  final String delegateName;
  final String delegatePhone;
  final List<ThawaniOrderItem> items;
  final double subtotal;
  final double vat;
  final double total;
  final OrderStatus status;
  final DateTime createdAt;
  
  ThawaniOrder({
    required this.orderNum,
    required this.customerName,
    required this.customerPhone,
    required this.delegateName,
    required this.delegatePhone,
    required this.items,
    required this.subtotal,
    required this.vat,
    required this.total,
    required this.status,
    required this.createdAt,
  });
  
  factory ThawaniOrder.fromJson(Map<String, dynamic> json) {
    return ThawaniOrder(
      orderNum: json['order_num'],
      customerName: json['customer_name'],
      customerPhone: json['customer_phone'],
      delegateName: json['delegate_name'],
      delegatePhone: json['delegate_phone'],
      items: (json['items'] as List)
          .map((item) => ThawaniOrderItem.fromJson(item))
          .toList(),
      subtotal: (json['subtotal'] as num).toDouble(),
      vat: (json['vat'] as num).toDouble(),
      total: (json['total'] as num).toDouble(),
      status: OrderStatus.values.byName(json['status']),
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}

enum OrderStatus { pending, accepted, ready, fulfilled, cancelled }

class ThawaniOrderItem {
  final int productId;
  final String barcode;
  final String name;
  final String nameAr;
  final int quantity;
  final double unitPrice;
  final double total;
  
  ThawaniOrderItem({
    required this.productId,
    required this.barcode,
    required this.name,
    required this.nameAr,
    required this.quantity,
    required this.unitPrice,
    required this.total,
  });
  
  factory ThawaniOrderItem.fromJson(Map<String, dynamic> json) {
    return ThawaniOrderItem(
      productId: json['product_id'],
      barcode: json['barcode'],
      name: json['name'],
      nameAr: json['name_ar'],
      quantity: json['quantity'],
      unitPrice: (json['unit_price'] as num).toDouble(),
      total: (json['total'] as num).toDouble(),
    );
  }
}

/// Thawani Integration Service
class ThawaniIntegration {
  final Dio _dio;
  final String baseUrl;
  
  ThawaniIntegration({
    required this.baseUrl,
    required String apiKey,
  }) : _dio = Dio(BaseOptions(
    baseUrl: baseUrl,
    headers: {
      'Authorization': 'Bearer $apiKey',
      'Accept': 'application/json',
    },
  ));
  
  /// Fetch pending orders for this store
  Future<List<ThawaniOrder>> getPendingOrders() async {
    try {
      final response = await _dio.get('/thawani/orders/pending');
      return (response.data as List)
          .map((json) => ThawaniOrder.fromJson(json))
          .toList();
    } catch (e) {
      throw ThawaniException('Failed to fetch pending orders: $e');
    }
  }
  
  /// Get order details by order number
  Future<ThawaniOrder> getOrder(String orderNum) async {
    try {
      final response = await _dio.get('/thawani/orders/$orderNum');
      return ThawaniOrder.fromJson(response.data);
    } catch (e) {
      throw ThawaniException('Failed to fetch order: $e');
    }
  }
  
  /// Mark order as fulfilled (creates transaction)
  Future<void> fulfillOrder(String orderNum, FulfillmentData data) async {
    try {
      await _dio.post('/thawani/orders/$orderNum/fulfill', data: {
        'transaction_id': data.transactionId,
        'zatca_invoice_uuid': data.zatcaUuid,
        'actual_items': data.items.map((i) => i.toJson()).toList(),
        'notes': data.notes,
      });
    } catch (e) {
      throw ThawaniException('Failed to fulfill order: $e');
    }
  }
  
  /// Sync product catalog from Thawani
  Future<ProductSyncResult> syncProducts() async {
    try {
      final response = await _dio.get('/thawani/products/sync');
      return ProductSyncResult.fromJson(response.data);
    } catch (e) {
      throw ThawaniException('Failed to sync products: $e');
    }
  }
  
  /// Push stock updates to Thawani
  Future<void> pushStockUpdate(List<StockUpdate> updates) async {
    try {
      await _dio.post('/thawani/stock/update', data: {
        'updates': updates.map((u) => u.toJson()).toList(),
      });
    } catch (e) {
      throw ThawaniException('Failed to push stock update: $e');
    }
  }
}

class FulfillmentData {
  final String transactionId;
  final String zatcaUuid;
  final List<FulfillmentItem> items;
  final String? notes;
  
  FulfillmentData({
    required this.transactionId,
    required this.zatcaUuid,
    required this.items,
    this.notes,
  });
}

class FulfillmentItem {
  final String barcode;
  final int quantity;
  final double actualPrice;
  
  FulfillmentItem({
    required this.barcode,
    required this.quantity,
    required this.actualPrice,
  });
  
  Map<String, dynamic> toJson() => {
    'barcode': barcode,
    'quantity': quantity,
    'actual_price': actualPrice,
  };
}

class ThawaniException implements Exception {
  final String message;
  ThawaniException(this.message);
  
  @override
  String toString() => 'ThawaniException: $message';
}

// ProductSyncResult and StockUpdate classes would be defined similarly
class ProductSyncResult {
  final int added;
  final int updated;
  final int unchanged;
  
  ProductSyncResult({required this.added, required this.updated, required this.unchanged});
  
  factory ProductSyncResult.fromJson(Map<String, dynamic> json) {
    return ProductSyncResult(
      added: json['added'],
      updated: json['updated'],
      unchanged: json['unchanged'],
    );
  }
}

class StockUpdate {
  final String barcode;
  final int quantity;
  final String updateType; // 'set', 'increment', 'decrement'
  
  StockUpdate({required this.barcode, required this.quantity, required this.updateType});
  
  Map<String, dynamic> toJson() => {
    'barcode': barcode,
    'quantity': quantity,
    'update_type': updateType,
  };
}
```

### Thawani Backend Changes Needed

```php
// routes/api.php - Add POS integration routes
Route::prefix('pos-integration')->middleware('auth:pos_api')->group(function () {
    // Orders
    Route::get('/orders/pending', [POSIntegrationController::class, 'pendingOrders']);
    Route::get('/orders/{order_num}', [POSIntegrationController::class, 'getOrder']);
    Route::post('/orders/{order_num}/fulfill', [POSIntegrationController::class, 'fulfillOrder']);
    
    // Products
    Route::get('/products/sync', [POSIntegrationController::class, 'syncProducts']);
    Route::get('/products/changes', [POSIntegrationController::class, 'productChanges']);
    
    // Stock
    Route::post('/stock/update', [POSIntegrationController::class, 'updateStock']);
    Route::get('/stock/check/{barcode}', [POSIntegrationController::class, 'checkStock']);
});
```

### Generic Integration API (For Other Platforms)

```dart
// Generic webhook system for other delivery platforms
// This would be handled by your Laravel backend

/// Webhook payload model
class DeliveryPlatformWebhook {
  final String platform;      // 'thawani', 'hungerstation', 'jahez', 'toyou', 'custom'
  final String event;         // 'order.created', 'order.updated', 'order.cancelled'
  final GenericOrder order;
  final DateTime timestamp;
  final String signature;     // HMAC for verification
  
  DeliveryPlatformWebhook({
    required this.platform,
    required this.event,
    required this.order,
    required this.timestamp,
    required this.signature,
  });
}

// Laravel backend exposes webhook endpoint:
// POST /api/webhooks/delivery-orders
// {
//   "platform": "thawani",
//   "event": "order.created",
//   "order": {
//     "external_id": "THA-245-1001-B",
//     "items": [...],
//     "total": 26.45
//   }
// }
```

---

## � Super Admin Panel (Thawani Internal Management)

### What is This?

This is **YOUR internal dashboard** to manage the entire POS SaaS platform - not the store owners' panel.

```
┌─────────────────────────────────────────────────────────────────┐
│                    SYSTEM USER TYPES                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  LEVEL 1: THAWANI SUPER ADMIN (YOU)                     │   │
│  │  ─────────────────────────────────────                  │   │
│  │  • Manage ALL stores on the platform                    │   │
│  │  • Billing & subscriptions                              │   │
│  │  • System configuration                                 │   │
│  │  • Support tickets                                      │   │
│  │  • Platform analytics                                   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                            │                                    │
│                            ▼                                    │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  LEVEL 2: STORE OWNER (Customer)                        │   │
│  │  ─────────────────────────────────────                  │   │
│  │  • Manage their own store(s)                            │   │
│  │  • Add products, set prices                             │   │
│  │  • View their reports                                   │   │
│  │  • Manage their employees                               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                            │                                    │
│                            ▼                                    │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  LEVEL 3: STORE STAFF (Cashier, Manager)                │   │
│  │  ─────────────────────────────────────                  │   │
│  │  • Process sales                                        │   │
│  │  • Limited access based on role                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Super Admin Panel Features

```
┌─────────────────────────────────────────────────────────────────┐
│              SUPER ADMIN DASHBOARD (Laravel + Livewire)         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  📊 DASHBOARD HOME                                             │
│  ─────────────────────────────────────────────────────────────│
│  • Total active stores                                         │
│  • Monthly recurring revenue (MRR)                             │
│  • New signups this month                                      │
│  • Churn rate                                                  │
│  • Support tickets open                                        │
│  • ZATCA compliance rate                                       │
│  • System health status                                        │
│                                                                 │
│  🏪 STORE MANAGEMENT                                           │
│  ─────────────────────────────────────────────────────────────│
│  • List all stores (with search, filter, sort)                 │
│  • View store details (owner, plan, usage, revenue)            │
│  • Activate / Suspend / Delete store                           │
│  • Impersonate store owner (login as them for support)         │
│  • View store's POS terminals                                  │
│  • View store's transaction history                            │
│  • Export store data                                           │
│                                                                 │
│  💳 SUBSCRIPTION & BILLING                                     │
│  ─────────────────────────────────────────────────────────────│
│  • View all subscriptions                                      │
│  • Upgrade/downgrade plans                                     │
│  • Apply discounts/coupons                                     │
│  • View payment history                                        │
│  • Handle failed payments                                      │
│  • Generate invoices                                           │
│  • Revenue reports                                             │
│                                                                 │
│  👥 USER MANAGEMENT                                            │
│  ─────────────────────────────────────────────────────────────│
│  • List all users across all stores                            │
│  • Search users by email/phone                                 │
│  • Reset passwords                                             │
│  • Disable/enable accounts                                     │
│  • View user activity logs                                     │
│  • Super admin team management                                 │
│                                                                 │
│  🎫 SUPPORT SYSTEM                                             │
│  ─────────────────────────────────────────────────────────────│
│  • Support ticket inbox                                        │
│  • Assign tickets to team members                              │
│  • Ticket priority & categories                                │
│  • Canned responses                                            │
│  • Knowledge base management                                   │
│  • Live chat integration (optional)                            │
│  • Store remote access for troubleshooting                     │
│                                                                 │
│  ⚙️ SYSTEM CONFIGURATION                                       │
│  ─────────────────────────────────────────────────────────────│
│  • Pricing plans & features                                    │
│  • ZATCA settings (API keys, certificates)                     │
│  • Payment gateway configuration                               │
│  • Email templates                                             │
│  • SMS gateway settings                                        │
│  • Feature flags (enable/disable features)                     │
│  • Maintenance mode                                            │
│                                                                 │
│  📈 ANALYTICS & REPORTS                                        │
│  ─────────────────────────────────────────────────────────────│
│  • Platform-wide sales volume                                  │
│  • Store performance rankings                                  │
│  • Geographic distribution                                     │
│  • Feature usage analytics                                     │
│  • Error/crash reports                                         │
│  • API usage metrics                                           │
│                                                                 │
│  🔔 NOTIFICATIONS & ALERTS                                     │
│  ─────────────────────────────────────────────────────────────│
│  • Send announcements to all stores                            │
│  • System maintenance notifications                            │
│  • Payment reminder automation                                 │
│  • ZATCA deadline alerts                                       │
│                                                                 │
│  📦 APP UPDATES & DEPLOYMENT                                   │
│  ─────────────────────────────────────────────────────────────│
│  • Push POS app updates                                        │
│  • Version management                                          │
│  • Rollback capability                                         │
│  • Update changelog                                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Super Admin Database Schema

```sql
-- =====================================================
-- SUPER ADMIN TABLES (Add to existing schema)
-- =====================================================

-- Admin users (Thawani internal team)
CREATE TABLE admin_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'support', 'sales', 'viewer') NOT NULL,
    phone VARCHAR(50),
    avatar_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Admin activity log
CREATE TABLE admin_activity_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID REFERENCES admin_users(id),
    action VARCHAR(100) NOT NULL,  -- 'store.suspend', 'subscription.upgrade', etc.
    entity_type VARCHAR(50),       -- 'store', 'user', 'subscription'
    entity_id UUID,
    details JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Subscription plans
CREATE TABLE subscription_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    price_monthly DECIMAL(10,2) NOT NULL,
    price_yearly DECIMAL(10,2),
    max_registers INT,
    max_products INT,
    max_users INT,
    features JSONB,  -- {"reports": true, "inventory": true, "api_access": false}
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Store subscriptions
CREATE TABLE store_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id) ON DELETE CASCADE,
    plan_id UUID REFERENCES subscription_plans(id),
    status ENUM('active', 'past_due', 'cancelled', 'trial', 'suspended') DEFAULT 'trial',
    billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',
    current_period_start DATE,
    current_period_end DATE,
    trial_ends_at TIMESTAMP,
    cancelled_at TIMESTAMP,
    cancellation_reason TEXT,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Payment transactions
CREATE TABLE subscription_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_id UUID REFERENCES store_subscriptions(id),
    store_id UUID REFERENCES stores(id),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'SAR',
    status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),  -- 'credit_card', 'bank_transfer', 'mada'
    payment_gateway VARCHAR(50), -- 'tap', 'moyasar', 'hyperpay'
    gateway_transaction_id VARCHAR(255),
    invoice_number VARCHAR(50),
    invoice_url TEXT,
    paid_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Support tickets
CREATE TABLE support_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number VARCHAR(20) NOT NULL UNIQUE,  -- TKT-2024-0001
    store_id UUID REFERENCES stores(id),
    user_id UUID REFERENCES users(id),
    assigned_to UUID REFERENCES admin_users(id),
    category ENUM('billing', 'technical', 'zatca', 'feature_request', 'general') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'waiting_customer', 'resolved', 'closed') DEFAULT 'open',
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    resolved_at TIMESTAMP,
    first_response_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Ticket messages
CREATE TABLE ticket_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID REFERENCES support_tickets(id) ON DELETE CASCADE,
    sender_type ENUM('customer', 'admin') NOT NULL,
    sender_id UUID NOT NULL,  -- user_id or admin_user_id
    message TEXT NOT NULL,
    attachments JSONB,  -- [{url, filename, size}]
    is_internal BOOLEAN DEFAULT FALSE,  -- internal notes, not shown to customer
    created_at TIMESTAMP DEFAULT NOW()
);

-- Canned responses
CREATE TABLE canned_responses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(50),
    shortcut VARCHAR(50),  -- type '/greeting' to insert
    created_by UUID REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Feature flags
CREATE TABLE feature_flags (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    enabled_for JSONB,  -- {"stores": [uuid1, uuid2], "plans": ["enterprise"]}
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- System announcements
CREATE TABLE announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255),
    content TEXT NOT NULL,
    content_ar TEXT,
    type ENUM('info', 'warning', 'maintenance', 'update') DEFAULT 'info',
    target ENUM('all', 'specific_stores', 'specific_plans') DEFAULT 'all',
    target_ids JSONB,  -- store IDs or plan slugs
    starts_at TIMESTAMP,
    ends_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_by UUID REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

-- App versions
CREATE TABLE app_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    platform ENUM('windows', 'macos', 'linux', 'android', 'ios', 'web') NOT NULL,
    version VARCHAR(20) NOT NULL,
    build_number INT,
    release_notes TEXT,
    download_url TEXT,
    is_mandatory BOOLEAN DEFAULT FALSE,
    min_supported_version VARCHAR(20),
    released_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Super Admin Panel Technology

```
┌─────────────────────────────────────────────────────────────────┐
│              SUPER ADMIN TECH STACK                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  OPTION A: Laravel + Livewire (RECOMMENDED)                    │
│  ─────────────────────────────────────────────                 │
│  ✅ You already know Laravel                                   │
│  ✅ Same codebase as POS API                                   │
│  ✅ Livewire = reactive UI without JavaScript                  │
│  ✅ Filament Admin package (beautiful pre-built UI)            │
│  ✅ Fast development                                           │
│                                                                 │
│  Stack:                                                        │
│  • Laravel 11                                                  │
│  • Filament v3 (admin panel package)                           │
│  • Livewire 3                                                  │
│  • Alpine.js (minimal JS)                                      │
│  • Tailwind CSS                                                │
│                                                                 │
│  ─────────────────────────────────────────────────────────────│
│                                                                 │
│  OPTION B: Flutter Web (Same as POS)                           │
│  ─────────────────────────────────────────────                 │
│  ✅ Same codebase potential                                    │
│  ⚠️ Admin panels are easier in traditional web                 │
│  ⚠️ More effort for data tables, forms                         │
│                                                                 │
│  ─────────────────────────────────────────────────────────────│
│                                                                 │
│  RECOMMENDATION: Laravel + Filament                            │
│  • Super admin is internal tool, doesn't need Flutter          │
│  • Filament gives you 80% of features out-of-box              │
│  • Can build in 2-3 weeks                                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Super Admin Panel Pages List

```
┌─────────────────────────────────────────────────────────────────┐
│              SUPER ADMIN PAGES (~45 pages)                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  DASHBOARD (3 pages)                                           │
│  ├── Main Dashboard (KPIs, charts)                             │
│  ├── Real-time Activity Feed                                   │
│  └── System Health Monitor                                     │
│                                                                 │
│  STORES (8 pages)                                              │
│  ├── Store List (with filters, search)                         │
│  ├── Store Details                                             │
│  ├── Store Edit                                                │
│  ├── Store Create (manual onboarding)                          │
│  ├── Store Terminals                                           │
│  ├── Store Transactions                                        │
│  ├── Store ZATCA Status                                        │
│  └── Impersonate Store                                         │
│                                                                 │
│  SUBSCRIPTIONS (6 pages)                                       │
│  ├── Subscription List                                         │
│  ├── Subscription Details                                      │
│  ├── Plans Management                                          │
│  ├── Plan Create/Edit                                          │
│  ├── Coupons & Discounts                                       │
│  └── Revenue Dashboard                                         │
│                                                                 │
│  BILLING (5 pages)                                             │
│  ├── Payment History                                           │
│  ├── Failed Payments                                           │
│  ├── Invoices List                                             │
│  ├── Invoice Details                                           │
│  └── Payment Gateway Settings                                  │
│                                                                 │
│  USERS (4 pages)                                               │
│  ├── All Users List                                            │
│  ├── User Details                                              │
│  ├── Admin Team List                                           │
│  └── Admin Create/Edit                                         │
│                                                                 │
│  SUPPORT (7 pages)                                             │
│  ├── Ticket Inbox                                              │
│  ├── Ticket Details                                            │
│  ├── Ticket Create (on behalf)                                 │
│  ├── Canned Responses                                          │
│  ├── Knowledge Base (articles)                                 │
│  ├── Article Create/Edit                                       │
│  └── Support Analytics                                         │
│                                                                 │
│  SYSTEM (6 pages)                                              │
│  ├── General Settings                                          │
│  ├── ZATCA Configuration                                       │
│  ├── Email Templates                                           │
│  ├── Feature Flags                                             │
│  ├── Maintenance Mode                                          │
│  └── Activity Logs                                             │
│                                                                 │
│  NOTIFICATIONS (3 pages)                                       │
│  ├── Announcements List                                        │
│  ├── Create Announcement                                       │
│  └── Push Notification Send                                    │
│                                                                 │
│  APP MANAGEMENT (3 pages)                                      │
│  ├── App Versions                                              │
│  ├── Release New Version                                       │
│  └── Update Statistics                                         │
│                                                                 │
│  TOTAL: ~45 pages                                              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Super Admin Roles & Permissions

```
┌─────────────────────────────────────────────────────────────────┐
│              ADMIN ROLES & PERMISSIONS                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  SUPER_ADMIN (You, CEO)                                        │
│  ─────────────────────                                         │
│  ✅ Everything                                                 │
│  ✅ Delete stores                                              │
│  ✅ Access billing                                             │
│  ✅ System configuration                                       │
│  ✅ Manage admin team                                          │
│                                                                 │
│  ADMIN (Operations Manager)                                    │
│  ──────────────────────────                                    │
│  ✅ View all stores                                            │
│  ✅ Suspend/activate stores                                    │
│  ✅ View subscriptions                                         │
│  ✅ Manage support tickets                                     │
│  ❌ Delete stores                                              │
│  ❌ System configuration                                       │
│                                                                 │
│  SUPPORT (Support Team)                                        │
│  ──────────────────────                                        │
│  ✅ View stores (read-only)                                    │
│  ✅ Impersonate for troubleshooting                            │
│  ✅ Manage support tickets                                     │
│  ✅ View activity logs                                         │
│  ❌ Billing access                                             │
│  ❌ Suspend stores                                             │
│                                                                 │
│  SALES (Sales Team)                                            │
│  ─────────────────                                             │
│  ✅ View stores (pipeline)                                     │
│  ✅ Create trial accounts                                      │
│  ✅ Apply discounts (within limits)                            │
│  ❌ Support tickets                                            │
│  ❌ System access                                              │
│                                                                 │
│  VIEWER (Stakeholders, Investors)                              │
│  ─────────────────────────────────                             │
│  ✅ View dashboard (read-only)                                 │
│  ✅ View reports                                               │
│  ❌ Any modifications                                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Quick Implementation with Filament

```php
// Using Filament Admin for Laravel - Super fast!
// composer require filament/filament

// app/Filament/Resources/StoreResource.php
class StoreResource extends Resource
{
    protected static ?string $model = Store::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Store Management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('name_ar')->required()->label('Name (Arabic)'),
            TextInput::make('vat_number')->required(),
            Select::make('subscription_plan_id')
                ->relationship('subscriptionPlan', 'name'),
            Toggle::make('is_active'),
            // ... more fields
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('owner.email'),
            BadgeColumn::make('subscription.status')
                ->colors(['success' => 'active', 'danger' => 'suspended']),
            TextColumn::make('registers_count')->counts('registers'),
            TextColumn::make('created_at')->dateTime(),
        ])
        ->filters([
            SelectFilter::make('subscription_status')
                ->relationship('subscription', 'status'),
        ])
        ->actions([
            Action::make('impersonate')
                ->icon('heroicon-o-user')
                ->action(fn (Store $store) => redirect()->route('impersonate', $store)),
            Action::make('suspend')
                ->icon('heroicon-o-pause')
                ->requiresConfirmation()
                ->action(fn (Store $store) => $store->suspend()),
        ]);
    }
}

// app/Filament/Resources/SupportTicketResource.php
class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?int $navigationBadgeCount = 'getOpenTicketsCount';
    
    // Filament automatically creates list, create, edit pages
    // With search, filters, bulk actions, etc.
}
```

---

## � Third-Party Delivery Platform Integrations

### Overview

Providers who purchase the Thawani POS system can connect their product catalog and receive orders from major delivery platforms operating in Saudi Arabia. Integration is bidirectional: product changes in the POS are pushed to the third-party platform, and incoming orders from each platform arrive at a standardized Thawani POS webhook endpoint.

```
┌──────────────────────────────────────────────────────────────────────┐
│              THIRD-PARTY DELIVERY INTEGRATION ARCHITECTURE           │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   PLATFORM ADMIN          PROVIDER (STORE)          DELIVERY APP     │
│   ─────────────           ────────────────          ────────────     │
│   • Add integrations  →   • Enter API keys      →   HungerStation   │
│   • Define endpoints      • Toggle platforms        Keeta            │
│   • Set key names         • Map categories          Jahez            │
│   • Manage platforms      • View sync status        Noon Food        │
│                                                     Ninja            │
│   POS SYNC ENGINE                                   Mrsool           │
│   ────────────────                                  The Chefz        │
│   Product Add    →  push to all active platforms    Talabat          │
│   Product Update →  push to all active platforms    ToYou            │
│   Product Delete →  push to all active platforms    + more           │
│                                                                      │
│   INBOUND ORDERS                                                     │
│   ──────────────                                                     │
│   Each platform → POST /api/pos/orders/inbound/{platform}           │
│                   Header: X-API-KEY: {provider_generated_key}       │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

### Supported Platforms (Initial Set)

| Platform | Country Focus | Order Types | Notes |
|---|---|---|---|
| **HungerStation** | Saudi Arabia | Food, Grocery | Largest in KSA |
| **Keeta** | Saudi Arabia | Food | Meituan-backed |
| **Jahez** | Saudi Arabia | Food, Grocery | Local champion |
| **Noon Food** | Gulf wide | Food | E-commerce giant |
| **Ninja** | Gulf wide | Grocery, Retail | On-demand |
| **Mrsool** | Saudi Arabia | Everything | Peer-to-peer |
| **The Chefz** | Saudi Arabia | Food | Premium segment |
| **Talabat** | Gulf wide | Food, Grocery | Delivery Hero |
| **ToYou** | Saudi Arabia | Food, Courier | Local |

> **Extensible by Platform Admin:** The admin can add any new third party at any time from the Platform Admin panel without a code deployment.

---

### Platform Admin: Managing Third-Party Integrations

The **Thawani platform admin** (our internal admin, not the provider) manages the master list of integrations. For each third party the admin defines:

1. **Platform Name & Logo** — displayed in the provider settings panel
2. **Custom Key Names** — the field labels the provider must fill in (e.g., `Client ID`, `Client Secret`, `Restaurant ID`, `Branch Code`)
3. **Endpoints per Operation** — one endpoint URL template per operation type
4. **Auth Method** — Bearer Token, API Key header, Basic Auth, OAuth2
5. **Active / Inactive toggle** — disable a platform globally without deleting it

```
┌─────────────────────────────────────────────────────────────────────┐
│              PLATFORM ADMIN: INTEGRATION BUILDER                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Platform:  [ HungerStation ]                                       │
│  Logo URL:  [ https://... ]                                         │
│  Auth Type: [ Bearer Token ▼ ]                                      │
│                                                                     │
│  CUSTOM KEY FIELDS (what the provider must enter):                  │
│  ┌──────────────────────────────────┬──────────────────────────┐    │
│  │ Field Label (shown to provider)  │  Internal Key Name       │    │
│  ├──────────────────────────────────┼──────────────────────────┤    │
│  │ Restaurant ID                    │  restaurant_id            │    │
│  │ API Key                          │  api_key                  │    │
│  │ Branch Code                      │  branch_code              │    │
│  │ [+ Add Field]                    │                           │    │
│  └──────────────────────────────────┴──────────────────────────┘    │
│                                                                     │
│  OPERATION ENDPOINTS:                                               │
│  ┌───────────────────┬─────────────────────────────────────────┐    │
│  │ Operation         │ Endpoint URL Template                    │    │
│  ├───────────────────┼─────────────────────────────────────────┤    │
│  │ Product Create    │ https://partner.hungerstation.com/v1/... │    │
│  │ Product Update    │ https://partner.hungerstation.com/v1/... │    │
│  │ Product Delete    │ https://partner.hungerstation.com/v1/... │    │
│  │ Category Sync     │ https://partner.hungerstation.com/v1/... │    │
│  │ Menu Push (bulk)  │ https://partner.hungerstation.com/v1/... │    │
│  │ [+ Add Operation] │                                          │    │
│  └───────────────────┴─────────────────────────────────────────┘    │
│                                                                     │
│  [ Save Platform ]   [ Test Connectivity ]                          │
└─────────────────────────────────────────────────────────────────────┘
```

**Database schema (platform side):**

```php
// migrations/create_third_party_platforms_table.php
Schema::create('third_party_platforms', function (Blueprint $table) {
    $table->id();
    $table->string('name');                     // "HungerStation"
    $table->string('slug')->unique();           // "hungerstation"
    $table->string('logo_url')->nullable();
    $table->enum('auth_type', ['bearer', 'api_key_header', 'basic', 'oauth2']);
    $table->json('key_fields');                 // [{"label":"Restaurant ID","key":"restaurant_id"}, ...]
    $table->json('operation_endpoints');        // {"product_create":"https://...", ...}
    $table->json('request_field_mapping');      // maps our product schema → their schema
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// migrations/create_provider_platform_credentials_table.php
Schema::create('provider_platform_credentials', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained();
    $table->foreignId('third_party_platform_id')->constrained();
    $table->json('credentials');               // encrypted {"restaurant_id":"abc", "api_key":"xyz"}
    $table->boolean('is_enabled')->default(false);
    $table->timestamp('last_sync_at')->nullable();
    $table->enum('sync_status', ['ok', 'error', 'pending'])->default('pending');
    $table->text('last_error')->nullable();
    $table->timestamps();
});
```

---

### Provider Settings: Entering API Credentials

In the provider's POS web portal under **Settings → Delivery Integrations**, each enabled platform shows:

```
┌─────────────────────────────────────────────────────────────────────┐
│  🟢 HungerStation                              [ Enabled toggle ]   │
├─────────────────────────────────────────────────────────────────────┤
│  Restaurant ID    [ __________________ ]                            │
│  API Key          [ __________________ ] 👁                         │
│  Branch Code      [ __________________ ]                            │
│                                                                     │
│  Sync Status:  ✅ Last synced 2 mins ago                            │
│  [ Test Connection ]   [ Sync Full Menu Now ]   [ Save ]            │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  🔴 Jahez                                      [ Disabled toggle ]  │
├─────────────────────────────────────────────────────────────────────┤
│  Partner ID       [ __________________ ]                            │
│  Secret Key       [ __________________ ] 👁                         │
│                                                                     │
│  Enable this integration to start receiving Jahez orders.           │
│  [ Save ]                                                           │
└─────────────────────────────────────────────────────────────────────┘
```

Credentials are **AES-256 encrypted** at rest. The POS sync engine decrypts them only at runtime.

---

### Outbound: Product Sync Operations

The POS triggers these operations automatically when the provider manages their catalog:

| POS Event | Triggered Operation | Platforms Notified |
|---|---|---|
| New product saved | `product_create` | All enabled platforms |
| Product updated | `product_update` | All enabled platforms |
| Product deleted / archived | `product_delete` | All enabled platforms |
| Category created | `category_sync` | Platforms supporting category API |
| Bulk menu import | `menu_push` | All enabled platforms |
| Product availability toggle | `product_update` (availability field) | All enabled platforms |
| Price change | `product_update` (price field) | All enabled platforms |

**Sync Engine (Laravel Jobs):**

```php
// app/Jobs/SyncProductToThirdParties.php
class SyncProductToThirdParties implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Product $product,
        private string $operation, // 'create' | 'update' | 'delete'
        private int $storeId
    ) {}

    public function handle(ThirdPartySync $sync): void
    {
        $credentials = ProviderPlatformCredential::where('store_id', $this->storeId)
            ->where('is_enabled', true)
            ->with('platform')
            ->get();

        foreach ($credentials as $cred) {
            dispatch(new PushToPlatform($this->product, $this->operation, $cred));
        }
    }
}

// app/Jobs/PushToPlatform.php
class PushToPlatform implements ShouldQueue
{
    use InteractsWithQueue;
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(): void
    {
        $platform   = $this->credential->platform;
        $endpoint   = $platform->operation_endpoints[$this->operation] ?? null;
        $decrypted  = decrypt($this->credential->credentials);
        $payload    = FieldMapper::transform($this->product, $platform->request_field_mapping);

        $response = Http::withToken($decrypted['api_key'] ?? '')
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $this->credential->update([
                'sync_status' => 'error',
                'last_error'  => $response->body(),
            ]);
            $this->fail($response->body());
        } else {
            $this->credential->update([
                'sync_status'  => 'ok',
                'last_sync_at' => now(),
                'last_error'   => null,
            ]);
        }
    }
}
```

---

### Inbound: Receiving Orders from Delivery Platforms

Each provider gets a **unique API key per platform** (auto-generated). The platform admin provides the endpoint template; each delivery app sends orders to:

```
POST https://pos.thawani.com/api/orders/inbound/{platform_slug}
Headers:
  X-Api-Key: {provider_generated_key}
  Content-Type: application/json
```

#### Auto-Generated API Keys (per Provider per Platform)

```php
// When provider enables a platform integration
$credential->update([
    'inbound_api_key' => Str::random(48),
]);
```

#### Order Inbound Controller

```php
// app/Http/Controllers/Api/InboundOrderController.php
class InboundOrderController extends Controller
{
    public function receive(Request $request, string $platformSlug): JsonResponse
    {
        $credential = ProviderPlatformCredential::where('inbound_api_key', $request->header('X-Api-Key'))
            ->whereHas('platform', fn ($q) => $q->where('slug', $platformSlug))
            ->firstOrFail();

        $platform = $credential->platform;
        $mapper   = new FieldMapper($platform->request_field_mapping);
        $orderData = $mapper->transformInbound($request->all());

        $order = Order::createFromThirdParty($orderData, $credential->store_id, $platformSlug);

        // Notify cashier via WebSocket
        broadcast(new NewExternalOrderReceived($order));

        return response()->json(['status' => 'accepted', 'order_id' => $order->id], 201);
    }
}
```

#### Instructions Shown to Provider (per Platform)

```
┌─────────────────────────────────────────────────────────────────────┐
│  📡 HungerStation — Inbound Order Webhook                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Provide this endpoint and API key to HungerStation support:        │
│                                                                     │
│  Endpoint:                                                          │
│  POST https://pos.thawani.com/api/orders/inbound/hungerstation      │
│                                                                     │
│  API Key (auto-generated):                                          │
│  [ sk_hs_a7f3k9xp2m...  ]  [ 📋 Copy ]  [ 🔄 Regenerate ]          │
│                                                                     │
│  Sample Request Body:                                               │
│  {                                                                  │
│    "external_order_id": "HS-123456",                                │
│    "items": [                                                       │
│      { "sku": "PROD-001", "qty": 2, "price": 15.00 }               │
│    ],                                                               │
│    "customer": { "name": "Ahmed", "phone": "+966501234567" },       │
│    "delivery_address": { "lat": 24.7, "lng": 46.7 },               │
│    "total": 30.00,                                                  │
│    "notes": "Extra sauce"                                           │
│  }                                                                  │
│                                                                     │
│  Response on success:  HTTP 201                                     │
│  { "status": "accepted", "order_id": 9821 }                         │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Sync Logs & Error Dashboard

Providers can see a real-time log of all outbound pushes and inbound orders:

```
┌─────────────────────────────────────────────────────────────────────┐
│  Integration Sync Log                    [ Filter: All ▼ ]         │
├────────┬─────────────────┬───────────────┬──────────┬──────────────┤
│  Time  │ Platform        │ Operation     │ Status   │ Details      │
├────────┼─────────────────┼───────────────┼──────────┼──────────────┤
│ 10:32  │ HungerStation   │ product_update│ ✅ OK    │ SKU: P-991   │
│ 10:31  │ Jahez           │ product_create│ ❌ Error │ 401 Unauth   │
│ 10:28  │ Talabat         │ menu_push     │ ✅ OK    │ 120 items    │
│ 10:15  │ Keeta           │ ORDER INBOUND │ ✅ OK    │ #KT-5521     │
└────────┴─────────────────┴───────────────┴──────────┴──────────────┘
```

---

## 🔔 Full Notifications Settings

### Overview

The POS system provides a comprehensive, granular notification system for providers. Every role can configure which channels they receive notifications on and for which events.

```
┌──────────────────────────────────────────────────────────────────┐
│                 NOTIFICATION ARCHITECTURE                        │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  CHANNELS:                                                       │
│  ─────────                                                       │
│  • In-App (POS screen popup / bell icon)                         │
│  • Push Notification (mobile app via FCM/APNs)                   │
│  • SMS (via Unifonic / Taqnyat / Msegat)                         │
│  • Email                                                         │
│  • WhatsApp Business API (optional, provider-configured)         │
│  • Webhook (POST to provider's own URL)                          │
│                                                                  │
│  NOTIFICATION ENGINE:                                            │
│  • Laravel Notifications + Channels                              │
│  • Per-user, per-role, per-event preference matrix               │
│  • Quiet hours support                                           │
│  • Notification grouping / digest mode                          │
│  • Delivery receipt tracking                                     │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

### Notification Events Catalog

#### Order Events

| Event | Description | Default Recipients |
|---|---|---|
| `order.new` | New order received (any channel) | Cashier, Manager |
| `order.new_external` | New order from delivery platform | Cashier, Manager, Kitchen |
| `order.status_changed` | Order moved to new status | Store Owner, Manager |
| `order.completed` | Order fully fulfilled | Store Owner |
| `order.cancelled` | Order cancelled | Manager, Owner |
| `order.refund_requested` | Customer requests refund | Manager, Owner |
| `order.refund_approved` | Refund processed | Cashier, Customer |
| `order.payment_failed` | Payment did not go through | Cashier, Manager |

#### Inventory Events

| Event | Description | Default Recipients |
|---|---|---|
| `inventory.low_stock` | Product reaches reorder threshold | Manager, Owner |
| `inventory.out_of_stock` | Product quantity reaches zero | Manager, Owner, Cashier |
| `inventory.expiry_warning` | Product expiry within X days | Manager, Owner |
| `inventory.excess_stock` | Stock exceeds max threshold | Manager |
| `inventory.adjustment` | Manual stock adjustment made | Owner, Auditor |

#### Financial Events

| Event | Description | Default Recipients |
|---|---|---|
| `finance.daily_summary` | End-of-day sales report | Owner |
| `finance.shift_closed` | Cashier closes shift | Owner, Manager |
| `finance.cash_discrepancy` | Cash count doesn't match system | Owner, Manager |
| `finance.large_transaction` | Transaction above defined threshold | Owner |
| `finance.coupon_overuse` | Coupon used more than expected | Owner |

#### System Events

| Event | Description | Default Recipients |
|---|---|---|
| `system.offline_mode` | POS went offline | Owner, Manager |
| `system.sync_failed` | Third-party sync error | Manager, Owner |
| `system.printer_error` | Receipt printer offline | Cashier, Manager |
| `system.update_available` | New POS version available | Owner |
| `system.license_expiring` | Package/license about to expire | Owner |
| `system.backup_failed` | Automated backup failed | Owner |

#### Staff Events

| Event | Description | Default Recipients |
|---|---|---|
| `staff.login` | Employee logged into POS | Manager (optional) |
| `staff.unauthorized_access` | Tried to access restricted feature | Owner, Manager |
| `staff.discount_applied` | Discount above threshold applied | Manager, Owner |
| `staff.void_transaction` | Transaction voided | Manager, Owner |

---

### Notification Preferences UI (Provider Settings)

```
┌─────────────────────────────────────────────────────────────────────┐
│  🔔 Notification Settings                                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Configure by: [ My Account ▼ ]   or   [ Role: Manager ▼ ]         │
│                                                                     │
│  ┌──────────────────┬──────────┬───────┬─────┬───────┬────────┐    │
│  │ Event            │ In-App   │ Push  │ SMS │ Email │ WA     │    │
│  ├──────────────────┼──────────┼───────┼─────┼───────┼────────┤    │
│  │ 🛒 New Order     │  ☑       │  ☑    │ ☐   │  ☐    │  ☐     │    │
│  │ 🌐 External Order│  ☑       │  ☑    │ ☑   │  ☐    │  ☐     │    │
│  │ ✅ Order Complete│  ☑       │  ☐    │ ☐   │  ☑    │  ☐     │    │
│  │ ❌ Order Cancelled│ ☑       │  ☑    │ ☑   │  ☑    │  ☐     │    │
│  │ 📦 Low Stock     │  ☑       │  ☑    │ ☐   │  ☐    │  ☐     │    │
│  │ 🚫 Out of Stock  │  ☑       │  ☑    │ ☑   │  ☑    │  ☐     │    │
│  │ 📊 Daily Summary │  ☐       │  ☐    │ ☐   │  ☑    │  ☑     │    │
│  │ 💰 Large Sale    │  ☑       │  ☑    │ ☐   │  ☐    │  ☐     │    │
│  │ 🔌 Went Offline  │  ☑       │  ☑    │ ☑   │  ☐    │  ☐     │    │
│  └──────────────────┴──────────┴───────┴─────┴───────┴────────┘    │
│                                                                     │
│  🌙 Quiet Hours:  [ 11:00 PM ] to [ 07:00 AM ]   ☑ Enable          │
│  ⚡ Critical alerts override quiet hours:         ☑ Yes            │
│                                                                     │
│  Low Stock Threshold:  [ 10 ] units                                 │
│  Large Transaction Alert above:  [ SAR 5,000 ]                      │
│  Expiry Warning:  [ 7 ] days before                                 │
│                                                                     │
│  [ Save Preferences ]                                               │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Notification Templates (Admin Managed)

Platform admin can customize notification templates per language (Arabic/English):

```
┌────────────────────────────────────────────────────────────────────┐
│  Template: order.new_external — English                            │
├────────────────────────────────────────────────────────────────────┤
│  Title:  New {{platform}} Order #{{order_id}}                      │
│  Body:   You have a new order from {{platform}}.                   │
│          Total: {{total}} SAR | Items: {{item_count}}              │
│          Customer: {{customer_name}}                               │
│                                                                    │
│  Available variables:                                              │
│  {{platform}}, {{order_id}}, {{total}}, {{item_count}},            │
│  {{customer_name}}, {{store_name}}, {{branch_name}}                │
└────────────────────────────────────────────────────────────────────┘
```

---

### Laravel Implementation

```php
// app/Notifications/NewExternalOrderNotification.php
class NewExternalOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Order $order) {}

    public function via(object $notifiable): array
    {
        return $notifiable->enabledChannelsFor('order.new_external');
        // returns e.g. ['database', 'broadcast', 'fcm', 'sms']
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event'     => 'order.new_external',
            'order_id'  => $this->order->id,
            'platform'  => $this->order->source_platform,
            'total'     => $this->order->total,
        ];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return FcmMessage::create()
            ->setTitle("New {$this->order->source_platform} Order")
            ->setBody("Total: {$this->order->total} SAR");
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return SmsMessage::content(
            "New order #{$this->order->id} from {$this->order->source_platform}. Total: {$this->order->total} SAR"
        );
    }
}
```

---

## 🖐️ POS View Customization (Handedness, Fonts, Themes)

### Overview

Every POS terminal and user interface adapts to the operator's physical preferences, accessibility needs, and brand identity. Settings live at three levels:
- **Platform-wide defaults** (set by Thawani admin)
- **Store-level overrides** (set by store owner/manager)
- **Per-user preferences** (set per cashier/operator login)

---

### POS Type Layouts

Each business type ships with multiple pre-built view variants:

#### Restaurant POS Views

```
VIEW 1: Table-Centric (Dine-In Focus)
┌────────────────────────────────────────────────────────┐
│  [Floor Map]  [Tables: 3 free | 8 occupied]            │
│  ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐            │
│  │T-1 │ │T-2 │ │T-3 │ │T-4 │ │T-5 │ │T-6 │            │
│  │ ✅ │ │ 🔴 │ │ ✅ │ │ 🔴 │ │ 🔴 │ │ ✅ │            │
│  └────┘ └────┘ └────┘ └────┘ └────┘ └────┘            │
└────────────────────────────────────────────────────────┘

VIEW 2: Quick-Serve (Fast Food / Counter)
┌────────────────────────────────────────────────────────┐
│  Categories  │  Items (grid)      │  Cart              │
│  ─────────── │  ──────────────    │  ─────────────     │
│  🍔 Burgers  │  [Burger] [Fries]  │  2x Burger  30 SR  │
│  🍟 Sides    │  [Salad]  [Drink]  │  1x Drink    8 SR  │
│  🥤 Drinks   │  [Wrap]   [Rice]   │  ─────────────     │
│  🍰 Desserts │                    │  Total:     38 SR  │
└────────────────────────────────────────────────────────┘

VIEW 3: Kitchen Display Integration
┌────────────────────────────────────────────────────────┐
│  NEW ORDERS        IN PROGRESS        READY            │
│  ┌──────────┐      ┌──────────┐       ┌──────────┐     │
│  │ #1042    │      │ #1039    │       │ #1035    │     │
│  │ T-4      │      │ T-2      │       │ Pickup   │ ✅  │
│  │ 2 Burger │      │ 1 Pasta  │       │ 1 Pizza  │     │
│  └──────────┘      └──────────┘       └──────────┘     │
└────────────────────────────────────────────────────────┘
```

#### Supermarket POS Views

```
VIEW 1: Barcode-Scan Optimized
┌───────────────────────────────────────────────────────┐
│  [Scan barcode or search...]                          │
│  ┌───────────────────────────────────────────────┐   │
│  │ ITEM             QTY  PRICE  TOTAL             │   │
│  │ Milk 1L           1   4.50   4.50              │   │
│  │ Bread 500g        2   3.25   6.50              │   │
│  │ Eggs (12)         1  12.00  12.00              │   │
│  └───────────────────────────────────────────────┘   │
│  Subtotal: 23.00 SR   VAT: 3.45 SR   Total: 26.45 SR │
│  [CASH]  [CARD]  [SPLIT]  [HOLD]  [DISCOUNT]         │
└───────────────────────────────────────────────────────┘

VIEW 2: Touch-Optimized Grid (Deli / Bakery sections)
┌───────────────────────────────────────────────────────┐
│  [Bread] [Croissant] [Cake] [Baguette] [Muffin]       │
│  [Samosa][Cheese   ] [Dip ] [Hummus  ] [Fatayer]      │
│  [+] [-] [x]   Weight: [1.250 kg × 35.00 = 43.75 SR] │
└───────────────────────────────────────────────────────┘
```

---

### Handedness Support

```
┌─────────────────────────────────────────────────────────────────┐
│                  HANDEDNESS LAYOUT MODES                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  RIGHT-HANDED (default):                                        │
│  ┌──────────────────────┬───────────────┐                       │
│  │   Product Grid /     │    Cart /     │                       │
│  │   Category Browser   │   Numpad      │                       │
│  │   (left side)        │   (right)     │                       │
│  └──────────────────────┴───────────────┘                       │
│                                                                 │
│  LEFT-HANDED (mirrored):                                        │
│  ┌───────────────┬──────────────────────┐                       │
│  │    Cart /     │   Product Grid /     │                       │
│  │   Numpad      │   Category Browser   │                       │
│  │   (left)      │   (right side)       │                       │
│  └───────────────┴──────────────────────┘                       │
│                                                                 │
│  CENTERED (tablet / small screen):                              │
│  ┌──────────────────────────────────────┐                       │
│  │         Quick-Action Bar            │                       │
│  ├──────────────────────────────────────┤                       │
│  │      Product Grid (full width)      │                       │
│  ├──────────────────────────────────────┤                       │
│  │         Cart & Payment              │                       │
│  └──────────────────────────────────────┘                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

Setting is stored per user account and respected immediately on login across all devices.

---

### Font Size Options

| Setting | Scale Factor | Use Case |
|---|---|---|
| `small` | 0.85× | Dense data screens, experienced cashiers |
| `medium` (default) | 1.0× | Standard |
| `large` | 1.2× | Accessibility, aging staff |
| `extra-large` | 1.5× | Visually impaired, bright outdoor environments |

Font size applies to product names, prices, cart lines, and button labels. The numpad and action buttons also scale proportionally.

---

### Theme System

#### Pre-Built Themes

| Theme | Colors | Best For |
|---|---|---|
| **Light Classic** | White bg, dark text | Standard indoor retail |
| **Dark Mode** | Near-black bg, light text | Night shift, dim environments |
| **High Contrast** | Pure black/white | Accessibility |
| **Thawani Brand** | Thawani navy + gold | Default for Thawani-sold terminals |
| **Custom** | Provider-defined hex | Chain branding |

#### Custom Branding (per Store / Chain)

```
┌─────────────────────────────────────────────────────────────────┐
│  🎨 Brand & Theme Settings                                      │
├─────────────────────────────────────────────────────────────────┤
│  Store Logo:     [ Upload PNG/SVG ]                             │
│  Primary Color:  [ #1A56A0 ] ■   (buttons, headers)            │
│  Accent Color:   [ #F5A623 ] ■   (highlights, badges)          │
│  Background:     [ #F8F8F8 ] ■                                  │
│  Text Color:     [ #1C1C1C ] ■                                  │
│                                                                 │
│  Receipt Header: [ Store Name  ●  Arabic name  ●  Logo     ]   │
│  Receipt Footer: [ Thank you text (AR/EN) ]                     │
│                                                                 │
│  Preset Themes:  ○ Light  ○ Dark  ◉ Custom  ○ High Contrast    │
│  [ Preview ]  [ Apply to All Terminals ]  [ Save ]             │
└─────────────────────────────────────────────────────────────────┘
```

---

### Settings Storage (Laravel)

```php
// app/Models/UserPreference.php
class UserPreference extends Model
{
    protected $casts = [
        'pos_handedness'  => 'string',   // 'right' | 'left' | 'center'
        'font_size'       => 'string',   // 'small' | 'medium' | 'large' | 'extra-large'
        'theme'           => 'string',   // 'light' | 'dark' | 'high-contrast' | 'custom'
        'pos_view_type'   => 'string',   // varies by business type
    ];

    // Cascade: user preference → store default → platform default
    public static function resolved(User $user): array
    {
        return $user->preferences
            ?? $user->store?->defaultPreferences
            ?? config('pos.defaults.preferences');
    }
}
```

---

### RTL / LTR Interaction with Handedness

When the UI language is Arabic (RTL), the handedness flip is reversed automatically so that "right-handed" always places the action area on the dominant hand side — the system detects the active locale and adjusts:

```php
// resources/js/composables/useHandedness.js
export function useHandedness() {
    const { locale } = useI18n();
    const hand = useUserPreference('pos_handedness');

    const actionSide = computed(() => {
        const isRTL = locale.value === 'ar';
        if (hand.value === 'right') return isRTL ? 'left'  : 'right';
        if (hand.value === 'left')  return isRTL ? 'right' : 'left';
        return 'center';
    });

    return { actionSide };
}
```

---

## 📦 Packages & Subscription Management

### Overview

The Thawani Platform Admin manages all subscription packages. Each package defines:
- Which **features** are available
- Which **user roles** can be created
- **Limits** (cashiers, terminals, products, branches)
- **Pricing** (monthly / annual)

Provider portal pages, menu items, and capabilities adapt automatically based on the active package.

---

### Package Tiers (Example)

```
┌──────────────────────────────────────────────────────────────────────────┐
│                       THAWANI POS PACKAGE TIERS                          │
├───────────────────┬──────────────────┬────────────────┬──────────────────┤
│ Feature           │  STARTER         │  PROFESSIONAL  │  ENTERPRISE      │
├───────────────────┼──────────────────┼────────────────┼──────────────────┤
│ Price             │ 149 SAR/mo       │ 349 SAR/mo     │ 999 SAR/mo       │
│ Cashiers          │ 2                │ 10             │ Unlimited        │
│ Terminals         │ 1                │ 5              │ Unlimited        │
│ Products          │ 500              │ 5,000          │ Unlimited        │
│ Branches          │ 1                │ 3              │ Unlimited        │
│ 3rd Party Integr. │ ❌               │ ✅ 3 platforms  │ ✅ All platforms  │
│ Kitchen Display   │ ❌               │ ✅              │ ✅               │
│ Analytics         │ Basic            │ Advanced       │ Full + Export    │
│ Custom Themes     │ ❌               │ ✅              │ ✅               │
│ API Access        │ ❌               │ Read-only      │ Full             │
│ Multi-branch      │ ❌               │ ✅              │ ✅               │
│ Loyalty Program   │ ❌               │ ✅              │ ✅               │
│ Coupon Engine     │ Basic            │ Advanced       │ Advanced         │
│ Support           │ Email            │ Phone + Email  │ Dedicated        │
│ White-label       │ ❌               │ ❌              │ ✅               │
│ ZATCA Compliance  │ ✅               │ ✅              │ ✅               │
│ Offline Mode      │ ✅               │ ✅              │ ✅               │
└───────────────────┴──────────────────┴────────────────┴──────────────────┘
```

---

### Platform Admin: Package Builder

```
┌─────────────────────────────────────────────────────────────────────┐
│  📦 Package Builder                                                 │
├─────────────────────────────────────────────────────────────────────┤
│  Name (EN):  [ Professional ]   Name (AR):  [ احترافي ]             │
│  Slug:       [ professional ]                                       │
│  Price (mo): [ 349 ]   Price (yr): [ 3,490 ]   Currency: SAR       │
│  Sort Order: [ 2 ]     Highlighted: ☑ (shown as "Most Popular")    │
│  Active:     ☑                                                      │
│                                                                     │
│  LIMITS:                                                            │
│  Max Cashiers:   [ 10  ]   (0 = unlimited)                          │
│  Max Terminals:  [ 5   ]                                            │
│  Max Products:   [ 5000]                                            │
│  Max Branches:   [ 3   ]                                            │
│                                                                     │
│  FEATURES (toggle to include in package):                           │
│  ☑ third_party_integrations    Max platforms: [ 3 ]                 │
│  ☑ kitchen_display_system                                           │
│  ☑ advanced_analytics                                               │
│  ☑ custom_themes                                                    │
│  ☑ loyalty_program                                                  │
│  ☑ advanced_coupons                                                 │
│  ☐ white_label                                                      │
│  ☐ api_full_access                                                  │
│  ☑ multi_branch                                                     │
│  ☑ inventory_expiry_tracking                                        │
│                                                                     │
│  [ Save Package ]                                                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Database Schema

```php
// migrations/create_packages_table.php
Schema::create('packages', function (Blueprint $table) {
    $table->id();
    $table->json('name');                    // {"en":"Professional","ar":"احترافي"}
    $table->string('slug')->unique();
    $table->decimal('price_monthly', 10, 2);
    $table->decimal('price_yearly', 10, 2);
    $table->json('limits');                  // {"cashiers":10,"terminals":5,"products":5000,"branches":3}
    $table->json('features');                // ["third_party_integrations","kitchen_display", ...]
    $table->json('feature_limits');          // {"third_party_integrations":3}
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->boolean('is_highlighted')->default(false);
    $table->timestamps();
});

// migrations/create_subscriptions_table.php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained();
    $table->foreignId('package_id')->constrained();
    $table->enum('billing_cycle', ['monthly', 'yearly']);
    $table->enum('status', ['active', 'suspended', 'cancelled', 'trial', 'grace_period']);
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_start');
    $table->timestamp('current_period_end');
    $table->timestamp('cancelled_at')->nullable();
    $table->json('overrides')->nullable();   // admin can override specific limits
    $table->timestamps();
});
```

---

### Feature Gate Middleware & Helpers

```php
// app/Http/Middleware/FeatureGate.php
class FeatureGate
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $request->user()->store->hasFeature($feature)) {
            return response()->json([
                'error'   => 'feature_not_available',
                'message' => __('upgrade_required', ['feature' => $feature]),
                'upgrade_url' => route('billing.upgrade'),
            ], 403);
        }
        return $next($request);
    }
}

// app/Models/Store.php (partial)
public function hasFeature(string $feature): bool
{
    return in_array($feature, $this->subscription->package->features ?? []);
}

public function withinLimit(string $limitKey): bool
{
    $limit = $this->subscription->package->limits[$limitKey] ?? 0;
    if ($limit === 0) return true; // unlimited

    return match ($limitKey) {
        'cashiers'  => $this->users()->role('cashier')->count() < $limit,
        'terminals' => $this->terminals()->count()              < $limit,
        'products'  => $this->products()->count()               < $limit,
        'branches'  => $this->branches()->count()               < $limit,
        default     => false,
    };
}
```

---

### Provider-Side: Subscription & Billing Pages

The provider's portal shows their current plan, usage meters, upgrade options, and invoice history:

```
┌─────────────────────────────────────────────────────────────────────┐
│  💳 Subscription                                Plan: Professional  │
├─────────────────────────────────────────────────────────────────────┤
│  Status: ✅ Active   |  Renews: 15 Apr 2026   |  349 SAR/month      │
│                                                                     │
│  USAGE:                                                             │
│  Cashiers:   ████████░░  8 / 10                                     │
│  Terminals:  ████░░░░░░  2 / 5                                      │
│  Products:   ████████░░  4,200 / 5,000                              │
│  Branches:   ███░░░░░░░  1 / 3                                      │
│  Platforms:  ██░░░░░░░░  1 / 3                                      │
│                                                                     │
│  [ Upgrade to Enterprise ]   [ View Invoices ]   [ Cancel Plan ]   │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Package Enforcement in UI

When a provider reaches a limit or tries to access a locked feature, the UI shows a contextual upgrade prompt instead of an error:

```
┌─────────────────────────────────────────────────────────────────────┐
│  ⚠️  Cashier Limit Reached                                         │
│                                                                     │
│  Your Professional plan allows 10 cashiers.                        │
│  You currently have 10 active cashier accounts.                    │
│                                                                     │
│  Upgrade to Enterprise for unlimited cashiers.                     │
│                                                                     │
│  [ Upgrade Now ]   [ Manage Existing Cashiers ]                    │
└─────────────────────────────────────────────────────────────────────┘
```

Locked features in navigation are shown greyed-out with a lock icon and tooltip: *"Available on Enterprise plan"*.

---

## 🛡️ Full Roles, Permissions & User Management

### Overview

The system has **two distinct permission scopes**:
1. **Platform-level (Thawani Admin)** — manages the POS platform itself
2. **Provider-level (Store/Chain)** — manages operations within a store or chain

Both scopes are enforced independently, and limits from the active subscription package apply to provider-level user counts.

```
┌─────────────────────────────────────────────────────────────────────┐
│                    ROLE HIERARCHY                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  PLATFORM SCOPE (Thawani Internal)                                  │
│  ───────────────────────────────────                                │
│  Super Admin          → Full platform access                        │
│  Platform Manager     → Manage providers, packages, integrations    │
│  Support Agent        → View-only + ticket resolution               │
│  Finance Admin        → Billing, invoices, payouts                  │
│  Integration Manager  → Manage third-party platform configs        │
│                                                                     │
│  PROVIDER SCOPE (Per Store / Chain)                                 │
│  ──────────────────────────────────                                 │
│  Owner                → Full store access                           │
│  Chain Manager        → All branches read + limited write           │
│  Branch Manager       → Full access to one branch                   │
│  Cashier              → POS terminal only                           │
│  Inventory Clerk      → Products, stock only                        │
│  Accountant           → Reports, finance, no sales entry            │
│  Kitchen Staff        → Kitchen display only (view orders)          │
│  Custom Role          → Provider-defined combination                │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Permission Matrix — Provider Scope

| Permission | Owner | Chain Mgr | Branch Mgr | Cashier | Inventory | Accountant | Kitchen |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Orders** | | | | | | | |
| View all orders | ✅ | ✅ | ✅ | Own shift | ❌ | ✅ | ✅ |
| Create order | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Cancel order | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Apply discount | ✅ | ✅ | ✅ | Limited* | ❌ | ❌ | ❌ |
| Process refund | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Products** | | | | | | | |
| View products | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Create/edit products | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Delete products | ✅ | ✅ | Branch only | ❌ | ❌ | ❌ | ❌ |
| Manage categories | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Inventory** | | | | | | | |
| View stock levels | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Adjust stock | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Receive purchase order | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Finance** | | | | | | | |
| View reports | ✅ | ✅ | Branch only | ❌ | ❌ | ✅ | ❌ |
| Export reports | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Manage cash drawer | ✅ | ❌ | ✅ | Own drawer | ❌ | ❌ | ❌ |
| View payroll/shifts | ✅ | ✅ | ✅ | Own only | ❌ | ✅ | ❌ |
| **Settings** | | | | | | | |
| Store settings | ✅ | ❌ | Limited | ❌ | ❌ | ❌ | ❌ |
| Manage staff/roles | ✅ | ✅ | Add cashier | ❌ | ❌ | ❌ | ❌ |
| Integration settings | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Notification settings | ✅ | ✅ | Own branch | Own only | ❌ | Own only | ❌ |
| Subscription/billing | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

> \* Cashiers can apply discounts only up to `cashier_discount_limit` defined per store (e.g. 10%). Higher discounts require manager PIN.

---

### Cashier Limit Enforcement

When the active package limits cashiers (e.g., max 10 on Professional), the system:

1. **Prevents creating** new cashier accounts past the limit
2. **Counts only active** accounts (suspended ones don't count)
3. **Shows remaining slots** in the user management UI
4. Only `Owner` can promote users or purchase upgrades

```php
// app/Actions/CreateUserAction.php
public function execute(array $data, Store $store): User
{
    if ($data['role'] === 'cashier' && ! $store->withinLimit('cashiers')) {
        throw new PackageLimitException(
            'cashier_limit_reached',
            $store->subscription->package->limits['cashiers']
        );
    }
    // ... create user
}
```

---

### Custom Roles (Provider-Defined)

Providers on Professional and Enterprise plans can define custom roles:

```
┌─────────────────────────────────────────────────────────────────────┐
│  ➕ Create Custom Role                                              │
├─────────────────────────────────────────────────────────────────────┤
│  Role Name (EN): [ Senior Cashier  ]                                │
│  Role Name (AR): [ كاشير أول       ]                                │
│  Inherits from:  [ Cashier ▼ ]  (start with a base role)           │
│                                                                     │
│  ADDITIONAL PERMISSIONS:                                            │
│  ☑ Apply discount up to 20%                                         │
│  ☑ Void transactions (own only)                                     │
│  ☑ View daily summary report                                        │
│  ☐ Cancel orders                                                    │
│  ☐ Access inventory                                                 │
│                                                                     │
│  [ Save Role ]                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

### User Management UI (Provider Portal)

```
┌─────────────────────────────────────────────────────────────────────┐
│  👥 Staff Management                      [ + Add Staff ]           │
│  Cashiers: 8/10 used   Custom Roles: 2                              │
├────────────┬───────────────┬──────────────┬──────────┬─────────────┤
│ Name       │ Role          │ Branch       │ Status   │ Actions      │
├────────────┼───────────────┼──────────────┼──────────┼─────────────┤
│ Ahmed Ali  │ Branch Mgr    │ Main Branch  │ 🟢 Active│ Edit Suspend │
│ Sara Omar  │ Cashier       │ Main Branch  │ 🟢 Active│ Edit Suspend │
│ Khalid Y.  │ Cashier       │ Branch 2     │ 🟢 Active│ Edit Suspend │
│ Nora M.    │ Sr. Cashier   │ Main Branch  │ 🔴 Susp. │ Edit Activate│
│ Faisal K.  │ Inventory     │ All          │ 🟢 Active│ Edit Suspend │
│ Lina Q.    │ Accountant    │ All          │ 🟢 Active│ Edit Suspend │
└────────────┴───────────────┴──────────────┴──────────┴─────────────┘
```

---

### Platform-Level Roles (Thawani Admin Panel)

| Role | Capabilities |
|---|---|
| **Super Admin** | Everything — full platform control, all providers, billing, infrastructure |
| **Platform Manager** | Manage providers, approve registrations, assign packages |
| **Support Agent** | View any provider account (read-only), respond to tickets |
| **Finance Admin** | Billing, subscriptions, invoices, payouts, package pricing |
| **Integration Manager** | Create/edit third-party platform configs, test connections |

---

### Laravel Implementation (Spatie Permissions)

```php
// Using spatie/laravel-permission with team support

// Platform roles (guard: 'platform')
$superAdmin        = Role::create(['name' => 'super-admin',        'guard_name' => 'platform']);
$platformManager   = Role::create(['name' => 'platform-manager',   'guard_name' => 'platform']);
$supportAgent      = Role::create(['name' => 'support-agent',      'guard_name' => 'platform']);
$financeAdmin      = Role::create(['name' => 'finance-admin',      'guard_name' => 'platform']);
$integrationMgr    = Role::create(['name' => 'integration-manager','guard_name' => 'platform']);

// Provider roles (guard: 'web', scoped to store via team_id)
$owner         = Role::create(['name' => 'owner',          'guard_name' => 'web']);
$chainManager  = Role::create(['name' => 'chain-manager',  'guard_name' => 'web']);
$branchManager = Role::create(['name' => 'branch-manager', 'guard_name' => 'web']);
$cashier       = Role::create(['name' => 'cashier',        'guard_name' => 'web']);
$inventoryClerk= Role::create(['name' => 'inventory-clerk','guard_name' => 'web']);
$accountant    = Role::create(['name' => 'accountant',     'guard_name' => 'web']);
$kitchenStaff  = Role::create(['name' => 'kitchen-staff',  'guard_name' => 'web']);

// Registering permissions
Permission::create(['name' => 'orders.view',             'guard_name' => 'web']);
Permission::create(['name' => 'orders.create',           'guard_name' => 'web']);
Permission::create(['name' => 'orders.cancel',           'guard_name' => 'web']);
Permission::create(['name' => 'orders.apply_discount',   'guard_name' => 'web']);
Permission::create(['name' => 'orders.refund',           'guard_name' => 'web']);
Permission::create(['name' => 'products.manage',         'guard_name' => 'web']);
Permission::create(['name' => 'inventory.adjust',        'guard_name' => 'web']);
Permission::create(['name' => 'reports.export',          'guard_name' => 'web']);
Permission::create(['name' => 'settings.integrations',   'guard_name' => 'web']);
Permission::create(['name' => 'billing.manage',          'guard_name' => 'web']);
Permission::create(['name' => 'staff.manage',            'guard_name' => 'web']);

// Policy check example
class OrderPolicy
{
    public function applyDiscount(User $user, Order $order): bool
    {
        if ($user->hasPermissionTo('orders.apply_discount')) {
            $discountPct = $order->discount_percentage;
            $limit = $user->store->cashier_discount_limit ?? 0;
            return $user->hasRole('cashier') ? $discountPct <= $limit : true;
        }
        return false;
    }
}
```

---

### Session & Access Logging

Every sensitive action is logged with actor, role, IP, and timestamp:

```php
// app/Events/SensitiveActionPerformed.php
AuditLog::create([
    'user_id'    => auth()->id(),
    'store_id'   => auth()->user()->store_id,
    'role'       => auth()->user()->getRoleNames()->first(),
    'action'     => 'order.discount_applied',
    'subject_id' => $order->id,
    'metadata'   => ['discount_pct' => 15, 'approved_by' => $managerId],
    'ip_address' => request()->ip(),
    'created_at' => now(),
]);
```

---

## �🔐 Security Architecture

### Security Overview

POS systems are high-value targets for attackers due to payment data, financial transactions, and business intelligence. This section covers comprehensive security measures for the Thawani POS system.

```
┌─────────────────────────────────────────────────────────────────┐
│                    SECURITY THREAT MODEL                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  THREAT ACTORS:                                                │
│  ─────────────                                                 │
│  • External Hackers (data theft, ransomware)                   │
│  • Malicious Insiders (employee fraud)                         │
│  • Competitors (business intelligence theft)                   │
│  • Script Kiddies (opportunistic attacks)                      │
│                                                                 │
│  HIGH-VALUE ASSETS:                                            │
│  ─────────────────                                             │
│  • Customer payment card data (PCI scope)                      │
│  • ZATCA cryptographic keys                                    │
│  • Transaction history & sales data                            │
│  • User credentials & PINs                                     │
│  • Product pricing & inventory data                            │
│  • Business analytics & reports                                │
│                                                                 │
│  ATTACK VECTORS:                                               │
│  ───────────────                                               │
│  • Network interception (MITM)                                 │
│  • Physical device theft                                       │
│  • API abuse & injection attacks                               │
│  • Credential theft (phishing, brute force)                    │
│  • Supply chain attacks (compromised dependencies)             │
│  • Insider threats (privileged access abuse)                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Security Principles

```
┌─────────────────────────────────────────────────────────────────┐
│                 SECURITY DESIGN PRINCIPLES                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. DEFENSE IN DEPTH                                           │
│     Multiple layers of security controls                       │
│                                                                 │
│  2. LEAST PRIVILEGE                                            │
│     Users & services get minimum required access               │
│                                                                 │
│  3. ZERO TRUST                                                 │
│     Never trust, always verify - even internal traffic         │
│                                                                 │
│  4. SECURE BY DEFAULT                                          │
│     Security enabled out-of-box, not opt-in                    │
│                                                                 │
│  5. FAIL SECURE                                                │
│     System fails to secure state, not open state               │
│                                                                 │
│  6. SEPARATION OF DUTIES                                       │
│     Critical operations require multiple parties               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### 1. Authentication & Authorization Security

#### Password Security (Flutter/Dart)

```dart
// Secure password hashing using Argon2 (recommended) or bcrypt
import 'package:cryptography/cryptography.dart';

class SecureAuth {
  // Use Argon2id for password hashing (memory-hard, resistant to GPU attacks)
  static final _argon2 = Argon2id(
    parallelism: 4,      // Number of threads
    memory: 65536,       // 64 MB memory cost
    iterations: 3,       // Time cost
    hashLength: 32,      // Output hash length
  );
  
  /// Hash password securely
  static Future<String> hashPassword(String password) async {
    final salt = SecureRandom.instance.nextBytes(16);
    final secretKey = await _argon2.deriveKey(
      secretKey: SecretKey(utf8.encode(password)),
      nonce: salt,
    );
    final hash = await secretKey.extractBytes();
    
    // Store salt + hash together (base64 encoded)
    return '${base64Encode(salt)}:${base64Encode(hash)}';
  }
  
  /// Verify password against stored hash
  static Future<bool> verifyPassword(String password, String storedHash) async {
    try {
      final parts = storedHash.split(':');
      if (parts.length != 2) return false;
      
      final salt = base64Decode(parts[0]);
      final expectedHash = base64Decode(parts[1]);
      
      final secretKey = await _argon2.deriveKey(
        secretKey: SecretKey(utf8.encode(password)),
        nonce: salt,
      );
      final actualHash = await secretKey.extractBytes();
      
      // Constant-time comparison to prevent timing attacks
      return _constantTimeCompare(actualHash, expectedHash);
    } catch (e) {
      return false;
    }
  }
  
  /// Constant-time comparison to prevent timing attacks
  static bool _constantTimeCompare(List<int> a, List<int> b) {
    if (a.length != b.length) return false;
    var result = 0;
    for (var i = 0; i < a.length; i++) {
      result |= a[i] ^ b[i];
    }
    return result == 0;
  }
}
```

#### PIN Security for Quick Login

```dart
// Secure PIN handling for cashier quick login
class SecurePinAuth {
  // Use PBKDF2 with high iteration count for PIN
  // (PINs have low entropy, need extra protection)
  static const _iterations = 600000; // OWASP 2023 recommendation
  
  /// Hash PIN with device-specific salt
  static Future<String> hashPin(String pin, String deviceId) async {
    // Combine PIN with device ID to prevent rainbow tables
    final input = '$pin:$deviceId';
    
    final pbkdf2 = Pbkdf2(
      macAlgorithm: Hmac.sha256(),
      iterations: _iterations,
      bits: 256,
    );
    
    final salt = SecureRandom.instance.nextBytes(32);
    final secretKey = await pbkdf2.deriveKey(
      secretKey: SecretKey(utf8.encode(input)),
      nonce: salt,
    );
    final hash = await secretKey.extractBytes();
    
    return '${base64Encode(salt)}:${base64Encode(hash)}';
  }
  
  /// Verify PIN with rate limiting
  static Future<PinVerifyResult> verifyPin(
    String pin,
    String storedHash,
    String deviceId,
    PinAttemptTracker tracker,
  ) async {
    // Check rate limiting first
    if (tracker.isLocked) {
      return PinVerifyResult.locked(tracker.lockoutRemaining);
    }
    
    final input = '$pin:$deviceId';
    final parts = storedHash.split(':');
    if (parts.length != 2) {
      tracker.recordFailure();
      return PinVerifyResult.invalid();
    }
    
    final salt = base64Decode(parts[0]);
    final expectedHash = base64Decode(parts[1]);
    
    final pbkdf2 = Pbkdf2(
      macAlgorithm: Hmac.sha256(),
      iterations: _iterations,
      bits: 256,
    );
    
    final secretKey = await pbkdf2.deriveKey(
      secretKey: SecretKey(utf8.encode(input)),
      nonce: salt,
    );
    final actualHash = await secretKey.extractBytes();
    
    if (_constantTimeCompare(actualHash, expectedHash)) {
      tracker.reset();
      return PinVerifyResult.success();
    } else {
      tracker.recordFailure();
      return PinVerifyResult.invalid(attemptsRemaining: tracker.attemptsRemaining);
    }
  }
}

/// Track PIN attempts with exponential backoff
class PinAttemptTracker {
  static const maxAttempts = 5;
  static const baseLockoutMinutes = 1;
  
  int _failedAttempts = 0;
  DateTime? _lockoutUntil;
  
  bool get isLocked => _lockoutUntil != null && DateTime.now().isBefore(_lockoutUntil!);
  int get attemptsRemaining => maxAttempts - _failedAttempts;
  Duration get lockoutRemaining => 
      _lockoutUntil?.difference(DateTime.now()) ?? Duration.zero;
  
  void recordFailure() {
    _failedAttempts++;
    if (_failedAttempts >= maxAttempts) {
      // Exponential backoff: 1, 2, 4, 8, 16 minutes...
      final lockoutMinutes = baseLockoutMinutes * pow(2, _failedAttempts - maxAttempts).toInt();
      _lockoutUntil = DateTime.now().add(Duration(minutes: min(lockoutMinutes, 60)));
    }
  }
  
  void reset() {
    _failedAttempts = 0;
    _lockoutUntil = null;
  }
}
```

#### Session Management

```dart
// Secure session management
class SecureSessionManager {
  static const sessionDuration = Duration(hours: 8); // Shift length
  static const inactivityTimeout = Duration(minutes: 15);
  
  final SecureStorage _storage;
  
  /// Create new session with secure token
  Future<Session> createSession(User user, String deviceId) async {
    // Generate cryptographically secure session token
    final tokenBytes = SecureRandom.instance.nextBytes(32);
    final sessionToken = base64UrlEncode(tokenBytes);
    
    // Create session with expiry
    final session = Session(
      id: Uuid().v4(),
      userId: user.id,
      deviceId: deviceId,
      token: sessionToken,
      createdAt: DateTime.now(),
      expiresAt: DateTime.now().add(sessionDuration),
      lastActivityAt: DateTime.now(),
      ipAddress: await _getIpAddress(),
    );
    
    // Store session securely
    await _storage.saveSession(session);
    
    // Log session creation for audit
    await AuditLog.record(
      action: 'session.created',
      userId: user.id,
      deviceId: deviceId,
      metadata: {'session_id': session.id},
    );
    
    return session;
  }
  
  /// Validate session token
  Future<Session?> validateSession(String token) async {
    final session = await _storage.getSessionByToken(token);
    if (session == null) return null;
    
    // Check expiry
    if (DateTime.now().isAfter(session.expiresAt)) {
      await _storage.deleteSession(session.id);
      return null;
    }
    
    // Check inactivity
    if (DateTime.now().difference(session.lastActivityAt) > inactivityTimeout) {
      await _storage.deleteSession(session.id);
      await AuditLog.record(
        action: 'session.timeout',
        userId: session.userId,
        metadata: {'session_id': session.id},
      );
      return null;
    }
    
    // Update last activity
    session.lastActivityAt = DateTime.now();
    await _storage.updateSession(session);
    
    return session;
  }
  
  /// Force logout all sessions for user
  Future<void> invalidateAllSessions(String userId, {String? reason}) async {
    final sessions = await _storage.getSessionsByUser(userId);
    for (final session in sessions) {
      await _storage.deleteSession(session.id);
    }
    
    await AuditLog.record(
      action: 'session.invalidate_all',
      userId: userId,
      metadata: {'reason': reason, 'count': sessions.length},
    );
  }
}
```

---

### 2. Data Encryption

#### Encryption at Rest (Local SQLite)

```dart
// Encrypted SQLite database using SQLCipher via Drift
import 'package:drift/drift.dart';
import 'package:sqlite3/sqlite3.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class EncryptedDatabase {
  static const _keySize = 32; // 256-bit key
  
  /// Get or generate database encryption key
  static Future<Uint8List> _getOrCreateKey() async {
    const storage = FlutterSecureStorage(
      aOptions: AndroidOptions(
        encryptedSharedPreferences: true,
        keyCipherAlgorithm: KeyCipherAlgorithm.RSA_ECB_OAEPwithSHA_256andMGF1Padding,
      ),
      iOptions: IOSOptions(
        accessibility: KeychainAccessibility.first_unlock_this_device,
      ),
    );
    
    final existingKey = await storage.read(key: 'db_encryption_key');
    if (existingKey != null) {
      return base64Decode(existingKey);
    }
    
    // Generate new key securely
    final newKey = SecureRandom.instance.nextBytes(_keySize);
    await storage.write(
      key: 'db_encryption_key',
      value: base64Encode(newKey),
    );
    
    return newKey;
  }
  
  /// Open encrypted database
  static Future<Database> open() async {
    final key = await _getOrCreateKey();
    final keyHex = key.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    
    return NativeDatabase.createInBackground(
      File('${await getApplicationDocumentsDirectory()}/pos_data.db'),
      setup: (db) {
        // Enable SQLCipher encryption
        db.execute("PRAGMA key = 'x\"$keyHex\"';");
        db.execute("PRAGMA cipher_page_size = 4096;");
        db.execute("PRAGMA cipher_memory_security = ON;");
        db.execute("PRAGMA kdf_iter = 256000;"); // High iteration count
        
        // Additional security settings
        db.execute("PRAGMA secure_delete = ON;"); // Overwrite deleted data
        db.execute("PRAGMA auto_vacuum = FULL;");
      },
    );
  }
}
```

#### Field-Level Encryption for Sensitive Data

```dart
// Encrypt specific sensitive fields before storage
class FieldEncryption {
  static final _algorithm = AesCbc.with256bits(
    macAlgorithm: Hmac.sha256(),
  );
  
  /// Encrypt sensitive field (e.g., card last 4, customer phone)
  static Future<String> encrypt(String plaintext, SecretKey key) async {
    final nonce = _algorithm.newNonce();
    final secretBox = await _algorithm.encrypt(
      utf8.encode(plaintext),
      secretKey: key,
      nonce: nonce,
    );
    
    // Combine nonce + ciphertext + MAC
    return base64Encode(secretBox.concatenation());
  }
  
  /// Decrypt sensitive field
  static Future<String> decrypt(String ciphertext, SecretKey key) async {
    final bytes = base64Decode(ciphertext);
    final secretBox = SecretBox.fromConcatenation(
      bytes,
      nonceLength: _algorithm.nonceLength,
      macLength: _algorithm.macAlgorithm.macLength,
    );
    
    final plaintext = await _algorithm.decrypt(secretBox, secretKey: key);
    return utf8.decode(plaintext);
  }
}

// Usage example for storing card last 4 digits
class PaymentRecord {
  final String cardLast4Encrypted; // Stored encrypted
  
  Future<String> getCardLast4(SecretKey key) async {
    return FieldEncryption.decrypt(cardLast4Encrypted, key);
  }
}
```

#### Encryption in Transit

```dart
// Secure HTTP client configuration
class SecureHttpClient {
  static Dio createSecureClient() {
    final dio = Dio(BaseOptions(
      baseUrl: 'https://api.thawani.sa',
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));
    
    // Add security interceptors
    dio.interceptors.addAll([
      _CertificatePinningInterceptor(),
      _RequestSigningInterceptor(),
      _SecurityHeadersInterceptor(),
      _SensitiveDataRedactionInterceptor(),
    ]);
    
    return dio;
  }
}

// Certificate pinning to prevent MITM attacks
class _CertificatePinningInterceptor extends Interceptor {
  // SHA-256 fingerprints of valid certificates
  static const _validFingerprints = [
    'sha256/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', // Primary cert
    'sha256/BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB=', // Backup cert
  ];
  
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    // Configure certificate validation
    (options.extra['httpClientAdapter'] as HttpClientAdapter?)
        ?.onHttpClientCreate = (client) {
      client.badCertificateCallback = (cert, host, port) {
        final fingerprint = sha256.convert(cert.der).toString();
        return _validFingerprints.any((fp) => fp.contains(fingerprint));
      };
      return client;
    };
    
    handler.next(options);
  }
}

// Add security headers to all requests
class _SecurityHeadersInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final timestamp = DateTime.now().millisecondsSinceEpoch.toString();
    final nonce = SecureRandom.instance.nextBytes(16).toHex();
    
    options.headers.addAll({
      'X-Request-Timestamp': timestamp,
      'X-Request-Nonce': nonce,
      'X-Device-ID': DeviceInfo.deviceId,
      'X-App-Version': AppInfo.version,
    });
    
    handler.next(options);
  }
}

// Redact sensitive data from logs
class _SensitiveDataRedactionInterceptor extends Interceptor {
  static const _sensitiveKeys = [
    'password', 'pin', 'token', 'secret', 'card', 'cvv', 'authorization'
  ];
  
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    // Redact for logging only, don't modify actual request
    final redactedData = _redactSensitive(options.data);
    debugPrint('Request: ${options.method} ${options.path}');
    debugPrint('Body: $redactedData');
    handler.next(options);
  }
  
  Map<String, dynamic>? _redactSensitive(dynamic data) {
    if (data is! Map<String, dynamic>) return data;
    return data.map((key, value) {
      if (_sensitiveKeys.any((k) => key.toLowerCase().contains(k))) {
        return MapEntry(key, '[REDACTED]');
      }
      return MapEntry(key, value is Map ? _redactSensitive(value) : value);
    });
  }
}
```

---

### 3. ZATCA Private Key Security

```dart
// Secure ZATCA key management
class ZatcaKeyManager {
  /// Store ZATCA private key in platform secure enclave
  static Future<void> storePrivateKey(Uint8List privateKey) async {
    if (Platform.isWindows) {
      // Use Windows DPAPI for encryption
      await _storeWithDpapi(privateKey);
    } else if (Platform.isMacOS) {
      // Use macOS Keychain
      await _storeInKeychain(privateKey);
    } else if (Platform.isLinux) {
      // Use libsecret
      await _storeInSecretService(privateKey);
    }
  }
  
  /// Windows DPAPI storage (encrypts with user credentials)
  static Future<void> _storeWithDpapi(Uint8List key) async {
    // Using ffi to call Windows DPAPI
    final encrypted = await _dpapiEncrypt(key);
    
    // Store encrypted key in secure location
    final keyFile = File('${await _getSecureDir()}/zatca_key.enc');
    await keyFile.writeAsBytes(encrypted);
    
    // Set restrictive permissions (owner only)
    await Process.run('icacls', [
      keyFile.path,
      '/inheritance:r',
      '/grant:r',
      '${Platform.environment['USERNAME']}:R',
    ]);
  }
  
  /// Retrieve private key from secure storage
  static Future<ECPrivateKey> getPrivateKey() async {
    Uint8List? keyBytes;
    
    if (Platform.isWindows) {
      keyBytes = await _retrieveFromDpapi();
    } else if (Platform.isMacOS) {
      keyBytes = await _retrieveFromKeychain();
    }
    
    if (keyBytes == null) {
      throw SecurityException('ZATCA private key not found');
    }
    
    // Parse the key
    return _parseEcPrivateKey(keyBytes);
  }
  
  /// Clear key from memory after use
  static void secureWipe(Uint8List data) {
    for (var i = 0; i < data.length; i++) {
      data[i] = 0;
    }
  }
}

// Secure key usage pattern
Future<SignedInvoice> signInvoice(Invoice invoice) async {
  ECPrivateKey? privateKey;
  Uint8List? keyBytes;
  
  try {
    // Get key
    privateKey = await ZatcaKeyManager.getPrivateKey();
    
    // Sign invoice
    final signature = await _signWithKey(invoice, privateKey);
    
    return SignedInvoice(
      invoice: invoice,
      signature: signature,
    );
  } finally {
    // Always wipe sensitive data from memory
    if (keyBytes != null) {
      ZatcaKeyManager.secureWipe(keyBytes);
    }
  }
}
```

---

### 4. API Security

#### Rate Limiting (Laravel Backend)

```php
// app/Http/Middleware/ApiRateLimiter.php
class ApiRateLimiter
{
    public function handle(Request $request, Closure $next)
    {
        $key = $this->resolveRequestKey($request);
        
        // Different limits for different endpoints
        $limits = [
            'auth.login' => ['attempts' => 5, 'decay' => 300],      // 5 per 5 min
            'auth.pin' => ['attempts' => 3, 'decay' => 60],         // 3 per minute
            'transactions.create' => ['attempts' => 100, 'decay' => 60], // 100 per min
            'sync.products' => ['attempts' => 10, 'decay' => 60],   // 10 per minute
            'default' => ['attempts' => 60, 'decay' => 60],         // 60 per minute
        ];
        
        $routeName = $request->route()->getName() ?? 'default';
        $limit = $limits[$routeName] ?? $limits['default'];
        
        if (RateLimiter::tooManyAttempts($key, $limit['attempts'])) {
            $retryAfter = RateLimiter::availableIn($key);
            
            // Log potential attack
            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'route' => $routeName,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        RateLimiter::hit($key, $limit['decay']);
        
        $response = $next($request);
        
        // Add rate limit headers
        $response->headers->add([
            'X-RateLimit-Limit' => $limit['attempts'],
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $limit['attempts']),
        ]);
        
        return $response;
    }
    
    private function resolveRequestKey(Request $request): string
    {
        // Combine user ID (if authenticated) + IP + route
        $userId = $request->user()?->id ?? 'guest';
        return "rate_limit:{$userId}:{$request->ip()}:{$request->route()->getName()}";
    }
}
```

#### Input Validation & Sanitization

```php
// app/Http/Requests/CreateTransactionRequest.php
class CreateTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:9999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'customer_phone' => ['nullable', 'string', 'regex:/^(05|5)\d{8}$/'], // Saudi mobile
            
            'payment_method' => ['required', 'in:cash,card,mixed'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
    
    protected function prepareForValidation()
    {
        // Sanitize inputs
        if ($this->has('notes')) {
            $this->merge([
                'notes' => strip_tags($this->notes),
            ]);
        }
        
        if ($this->has('customer_phone')) {
            // Normalize phone number
            $phone = preg_replace('/[^0-9]/', '', $this->customer_phone);
            if (strlen($phone) === 9 && $phone[0] === '5') {
                $phone = '0' . $phone;
            }
            $this->merge(['customer_phone' => $phone]);
        }
    }
    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Business logic validation
            $this->validateTotalAmount($validator);
            $this->validateStockAvailability($validator);
        });
    }
    
    private function validateTotalAmount($validator)
    {
        $calculatedTotal = collect($this->items)->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'] * (1 - ($item['discount'] ?? 0) / 100);
        });
        
        // Prevent price manipulation
        if (abs($calculatedTotal - $this->payment_amount) > 0.01) {
            $validator->errors()->add('payment_amount', 'Payment amount does not match items total');
        }
    }
}
```

#### SQL Injection Prevention

```php
// Always use parameterized queries - Laravel Eloquent handles this
// NEVER do this:
// DB::select("SELECT * FROM users WHERE email = '$email'"); // VULNERABLE!

// DO this instead:
$user = User::where('email', $email)->first();

// Or with raw queries, always use bindings:
$results = DB::select(
    'SELECT * FROM products WHERE barcode = :barcode AND store_id = :store_id',
    ['barcode' => $barcode, 'store_id' => $storeId]
);

// For complex queries with dynamic columns (rare case):
class SecureQueryBuilder
{
    private const ALLOWED_COLUMNS = ['name', 'price', 'created_at', 'category_id'];
    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];
    
    public static function buildOrderBy(string $column, string $direction): array
    {
        // Whitelist validation - prevents SQL injection
        if (!in_array($column, self::ALLOWED_COLUMNS, true)) {
            throw new InvalidArgumentException("Invalid column: $column");
        }
        if (!in_array(strtolower($direction), self::ALLOWED_DIRECTIONS, true)) {
            throw new InvalidArgumentException("Invalid direction: $direction");
        }
        
        return [$column, $direction];
    }
}
```

---

### 5. Audit Logging

```sql
-- Comprehensive audit log table
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    -- Who
    user_id UUID REFERENCES users(id),
    user_type VARCHAR(50), -- 'user', 'admin', 'system', 'api'
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_id VARCHAR(255),
    
    -- What
    action VARCHAR(100) NOT NULL, -- 'transaction.create', 'user.login', etc.
    entity_type VARCHAR(50), -- 'transaction', 'product', 'user'
    entity_id UUID,
    
    -- Details
    old_values JSONB, -- Previous state (for updates/deletes)
    new_values JSONB, -- New state (for creates/updates)
    metadata JSONB, -- Additional context
    
    -- Risk assessment
    risk_level VARCHAR(20) DEFAULT 'normal', -- 'low', 'normal', 'high', 'critical'
    flags JSONB, -- Anomaly flags
    
    -- Integrity
    checksum VARCHAR(64), -- SHA-256 of log entry
    previous_checksum VARCHAR(64), -- Chain link
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    
    -- Indexes
    INDEX idx_audit_user (user_id, created_at),
    INDEX idx_audit_action (action, created_at),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_risk (risk_level, created_at)
);

-- Audit log integrity verification
CREATE OR REPLACE FUNCTION verify_audit_chain()
RETURNS TABLE(is_valid BOOLEAN, broken_at UUID) AS $$
DECLARE
    rec RECORD;
    expected_checksum VARCHAR(64);
    prev_checksum VARCHAR(64) := '';
BEGIN
    FOR rec IN SELECT * FROM audit_logs ORDER BY created_at LOOP
        expected_checksum := encode(
            sha256(
                (rec.id || rec.user_id || rec.action || rec.created_at || prev_checksum)::bytea
            ),
            'hex'
        );
        
        IF rec.checksum != expected_checksum OR rec.previous_checksum != prev_checksum THEN
            RETURN QUERY SELECT FALSE, rec.id;
            RETURN;
        END IF;
        
        prev_checksum := rec.checksum;
    END LOOP;
    
    RETURN QUERY SELECT TRUE, NULL::UUID;
END;
$$ LANGUAGE plpgsql;
```

```dart
// Audit logging service
class AuditLog {
  static String? _previousChecksum;
  
  /// Record an audit event
  static Future<void> record({
    required String action,
    String? userId,
    String? entityType,
    String? entityId,
    Map<String, dynamic>? oldValues,
    Map<String, dynamic>? newValues,
    Map<String, dynamic>? metadata,
    String riskLevel = 'normal',
  }) async {
    final id = const Uuid().v4();
    final timestamp = DateTime.now().toUtc();
    
    // Calculate checksum for integrity chain
    final checksumInput = '$id$userId$action$timestamp${_previousChecksum ?? ''}';
    final checksum = sha256.convert(utf8.encode(checksumInput)).toString();
    
    final log = AuditLogEntry(
      id: id,
      userId: userId,
      userType: _getCurrentUserType(),
      ipAddress: await _getIpAddress(),
      deviceId: DeviceInfo.deviceId,
      action: action,
      entityType: entityType,
      entityId: entityId,
      oldValues: oldValues,
      newValues: newValues,
      metadata: metadata,
      riskLevel: riskLevel,
      checksum: checksum,
      previousChecksum: _previousChecksum,
      createdAt: timestamp,
    );
    
    _previousChecksum = checksum;
    
    // Store locally first (for offline)
    await _localDb.insertAuditLog(log);
    
    // Queue for sync
    await _syncQueue.add(SyncItem(
      type: 'audit_log',
      data: log.toJson(),
      priority: _getPriority(riskLevel),
    ));
    
    // Alert on high-risk actions
    if (riskLevel == 'high' || riskLevel == 'critical') {
      await _alertService.sendSecurityAlert(log);
    }
  }
  
  static int _getPriority(String riskLevel) {
    switch (riskLevel) {
      case 'critical': return 1;
      case 'high': return 2;
      case 'normal': return 5;
      case 'low': return 8;
      default: return 5;
    }
  }
}

// Usage examples
await AuditLog.record(
  action: 'transaction.void',
  entityType: 'transaction',
  entityId: transaction.id,
  oldValues: transaction.toJson(),
  metadata: {'reason': voidReason},
  riskLevel: 'high',
);

await AuditLog.record(
  action: 'user.permission_change',
  entityType: 'user',
  entityId: user.id,
  oldValues: {'role': oldRole},
  newValues: {'role': newRole},
  riskLevel: 'high',
);
```

---

### 6. PCI DSS Compliance Guidelines

```
┌─────────────────────────────────────────────────────────────────┐
│                  PCI DSS COMPLIANCE CHECKLIST                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ⚠️ NOTE: Full PCI compliance requires official assessment.    │
│     These are guidelines, not certification.                   │
│                                                                 │
│  REQUIREMENT 1: Secure Network                                 │
│  ─────────────────────────────                                 │
│  ✅ Use firewall between POS and internet                      │
│  ✅ Don't use vendor-supplied default passwords                │
│  ✅ Segment POS network from other business networks           │
│                                                                 │
│  REQUIREMENT 2: Protect Cardholder Data                        │
│  ─────────────────────────────────────────                     │
│  ✅ Never store full card number (PAN) in POS                  │
│  ✅ Only store last 4 digits if needed (encrypted)             │
│  ✅ Never store CVV/CVC                                        │
│  ✅ Never store PIN                                            │
│  ✅ Use P2PE (Point-to-Point Encryption) terminals             │
│                                                                 │
│  REQUIREMENT 3: Encryption                                     │
│  ─────────────────────────                                     │
│  ✅ TLS 1.2+ for all transmissions                             │
│  ✅ AES-256 for data at rest                                   │
│  ✅ Proper key management                                      │
│                                                                 │
│  REQUIREMENT 4: Access Control                                 │
│  ──────────────────────────                                    │
│  ✅ Unique user IDs for each person                            │
│  ✅ Role-based access (least privilege)                        │
│  ✅ Strong authentication (see password policy)                │
│                                                                 │
│  REQUIREMENT 5: Monitoring & Testing                           │
│  ──────────────────────────────────                            │
│  ✅ Audit logging for all access to card data                  │
│  ✅ Regular security testing                                   │
│  ✅ Vulnerability scanning                                     │
│                                                                 │
│  REQUIREMENT 6: Security Policy                                │
│  ────────────────────────────                                  │
│  ✅ Documented security policies                               │
│  ✅ Employee security training                                 │
│  ✅ Incident response plan                                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

```dart
// Card data handling - NEVER store full PAN
class SecurePaymentHandler {
  /// Process card payment - card data goes directly to terminal
  /// POS only receives masked data and authorization result
  static Future<PaymentResult> processCardPayment({
    required double amount,
    required String terminalId,
  }) async {
    // Send amount to payment terminal
    // Terminal handles all card interaction via P2PE
    final result = await PaymentTerminal.requestPayment(
      terminalId: terminalId,
      amount: amount,
      currency: 'SAR',
    );
    
    // Store ONLY non-sensitive data
    return PaymentResult(
      success: result.approved,
      authorizationCode: result.authCode,
      cardLast4: result.maskedPan.substring(result.maskedPan.length - 4),
      cardBrand: result.cardBrand, // 'VISA', 'MADA', etc.
      transactionRef: result.referenceNumber,
      // NEVER store: full PAN, CVV, PIN, track data
    );
  }
}
```

---

### 7. Security Monitoring & Alerts

```dart
// Real-time security monitoring
class SecurityMonitor {
  static const anomalyThresholds = {
    'high_value_transaction': 10000.0, // SAR
    'void_frequency': 5, // voids per hour
    'discount_frequency': 20, // discounts per hour
    'failed_logins': 3, // per 15 minutes
    'after_hours_access': true,
  };
  
  /// Check transaction for anomalies
  static Future<List<SecurityFlag>> analyzeTransaction(Transaction tx) async {
    final flags = <SecurityFlag>[];
    
    // High-value transaction
    if (tx.total > anomalyThresholds['high_value_transaction']!) {
      flags.add(SecurityFlag(
        type: 'high_value_transaction',
        severity: 'medium',
        details: 'Transaction amount ${tx.total} SAR exceeds threshold',
      ));
    }
    
    // Void frequency check
    final recentVoids = await _db.countRecentVoids(
      cashierId: tx.cashierId,
      withinMinutes: 60,
    );
    if (recentVoids >= anomalyThresholds['void_frequency']!) {
      flags.add(SecurityFlag(
        type: 'excessive_voids',
        severity: 'high',
        details: 'Cashier has $recentVoids voids in last hour',
      ));
    }
    
    // Discount abuse check
    final recentDiscounts = await _db.countRecentDiscounts(
      cashierId: tx.cashierId,
      withinMinutes: 60,
    );
    if (recentDiscounts >= anomalyThresholds['discount_frequency']!) {
      flags.add(SecurityFlag(
        type: 'excessive_discounts',
        severity: 'medium',
        details: 'Cashier has $recentDiscounts discounts in last hour',
      ));
    }
    
    // After-hours access
    if (_isAfterHours() && anomalyThresholds['after_hours_access'] == true) {
      flags.add(SecurityFlag(
        type: 'after_hours_access',
        severity: 'low',
        details: 'Transaction created outside business hours',
      ));
    }
    
    // Log and alert if flags found
    if (flags.isNotEmpty) {
      await AuditLog.record(
        action: 'security.anomaly_detected',
        entityType: 'transaction',
        entityId: tx.id,
        metadata: {'flags': flags.map((f) => f.toJson()).toList()},
        riskLevel: _getHighestSeverity(flags),
      );
      
      // Send alerts for high/critical
      final highFlags = flags.where((f) => f.severity == 'high' || f.severity == 'critical');
      for (final flag in highFlags) {
        await _alertService.sendSecurityAlert(
          title: 'Security Alert: ${flag.type}',
          message: flag.details,
          storeId: tx.storeId,
        );
      }
    }
    
    return flags;
  }
}
```

---

### 8. Security Checklist for Deployment

```
┌─────────────────────────────────────────────────────────────────┐
│              SECURITY DEPLOYMENT CHECKLIST                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PRE-DEPLOYMENT:                                               │
│  ───────────────                                               │
│  □ Run static code analysis (SAST)                             │
│  □ Run dependency vulnerability scan                           │
│  □ Perform penetration testing                                 │
│  □ Review all API endpoints for auth                           │
│  □ Verify all secrets are in secure storage                    │
│  □ Remove all debug/test credentials                           │
│  □ Enable all logging                                          │
│  □ Configure rate limiting                                     │
│  □ Set up security monitoring alerts                           │
│                                                                 │
│  INFRASTRUCTURE:                                               │
│  ───────────────                                               │
│  □ TLS 1.2+ enforced on all endpoints                          │
│  □ HSTS headers configured                                     │
│  □ Database connections encrypted                              │
│  □ Firewall rules reviewed                                     │
│  □ DDoS protection enabled                                     │
│  □ Backup encryption verified                                  │
│                                                                 │
│  APPLICATION:                                                  │
│  ────────────                                                  │
│  □ Session timeout configured                                  │
│  □ Password policy enforced                                    │
│  □ Brute force protection active                               │
│  □ Input validation on all endpoints                           │
│  □ Output encoding implemented                                 │
│  □ CORS properly configured                                    │
│  □ CSP headers set (for web portal)                            │
│                                                                 │
│  MONITORING:                                                   │
│  ───────────                                                   │
│  □ Security event logging active                               │
│  □ Alert thresholds configured                                 │
│  □ Log retention policy set                                    │
│  □ Incident response plan documented                           │
│  □ Security contact info updated                               │
│                                                                 │
│  POST-DEPLOYMENT:                                              │
│  ────────────────                                              │
│  □ Verify audit logs are recording                             │
│  □ Test security alerts                                        │
│  □ Document security architecture                              │
│  □ Schedule regular security reviews                           │
│  □ Plan for security patch process                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### 9. Security Database Schema

```sql
-- Security-related tables to add to the schema

-- API tokens with proper security
CREATE TABLE api_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    token_hash VARCHAR(64) NOT NULL, -- SHA-256 of token
    token_prefix VARCHAR(8) NOT NULL, -- First 8 chars for identification
    abilities JSONB DEFAULT '[]', -- Scoped permissions
    last_used_at TIMESTAMP,
    last_used_ip VARCHAR(45),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    revoked_at TIMESTAMP
);

-- Security events for monitoring
CREATE TABLE security_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_type VARCHAR(50) NOT NULL, -- 'login_failed', 'permission_denied', etc.
    severity VARCHAR(20) NOT NULL, -- 'low', 'medium', 'high', 'critical'
    user_id UUID REFERENCES users(id),
    ip_address VARCHAR(45),
    device_id VARCHAR(255),
    details JSONB,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP,
    resolved_by UUID REFERENCES admin_users(id),
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    
    INDEX idx_security_events_type (event_type, created_at),
    INDEX idx_security_events_severity (severity, resolved, created_at)
);

-- IP blocklist
CREATE TABLE ip_blocklist (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ip_address VARCHAR(45) NOT NULL,
    reason VARCHAR(255),
    blocked_until TIMESTAMP, -- NULL = permanent
    created_by UUID REFERENCES admin_users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    
    UNIQUE(ip_address)
);

-- Device trust management
CREATE TABLE trusted_devices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    device_id VARCHAR(255) NOT NULL,
    device_name VARCHAR(100),
    device_fingerprint VARCHAR(64),
    first_seen_at TIMESTAMP DEFAULT NOW(),
    last_seen_at TIMESTAMP,
    is_trusted BOOLEAN DEFAULT FALSE,
    trusted_at TIMESTAMP,
    
    UNIQUE(user_id, device_id)
);
```

---

*This security section ensures Thawani POS meets enterprise security standards, regulatory requirements (ZATCA, PDPL), and industry best practices (PCI DSS guidelines). Regular security audits and penetration testing are recommended.*

---

## 🧪 Testing Strategy

### Testing Pyramid

```
┌─────────────────────────────────────────────────────────────────┐
│                    TESTING PYRAMID                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│                         ┌───────┐                              │
│                        /  E2E   \                              │
│                       /  Tests   \                             │
│                      /  (~10%)    \                            │
│                     ┌─────────────┐                            │
│                    /  Integration  \                           │
│                   /    Tests        \                          │
│                  /    (~20%)         \                         │
│                 ┌─────────────────────┐                        │
│                /      Unit Tests       \                       │
│               /        (~70%)           \                      │
│              └───────────────────────────┘                     │
│                                                                 │
│  Focus:                                                        │
│  • Unit: Business logic, calculations, data transformations    │
│  • Integration: Database, API, hardware mocks                  │
│  • E2E: Critical user flows (sale, payment, receipt)           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Unit Testing (Flutter)

```dart
// test/services/price_calculator_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_app/services/price_calculator.dart';

void main() {
  group('PriceCalculator', () {
    late PriceCalculator calculator;
    
    setUp(() {
      calculator = PriceCalculator(vatRate: 0.15);
    });
    
    group('VAT Calculations', () {
      test('calculates VAT for tax-inclusive price', () {
        final result = calculator.calculateVat(
          price: 115.0,
          isTaxInclusive: true,
        );
        
        expect(result.netPrice, closeTo(100.0, 0.01));
        expect(result.vatAmount, closeTo(15.0, 0.01));
        expect(result.grossPrice, equals(115.0));
      });
      
      test('calculates VAT for tax-exclusive price', () {
        final result = calculator.calculateVat(
          price: 100.0,
          isTaxInclusive: false,
        );
        
        expect(result.netPrice, equals(100.0));
        expect(result.vatAmount, equals(15.0));
        expect(result.grossPrice, equals(115.0));
      });
      
      test('handles zero price', () {
        final result = calculator.calculateVat(price: 0, isTaxInclusive: true);
        expect(result.vatAmount, equals(0));
      });
    });
    
    group('Discount Calculations', () {
      test('applies percentage discount correctly', () {
        final result = calculator.applyDiscount(
          price: 100.0,
          discount: Discount.percentage(10),
        );
        
        expect(result.discountedPrice, equals(90.0));
        expect(result.discountAmount, equals(10.0));
      });
      
      test('applies fixed discount correctly', () {
        final result = calculator.applyDiscount(
          price: 100.0,
          discount: Discount.fixed(15.0),
        );
        
        expect(result.discountedPrice, equals(85.0));
      });
      
      test('discount cannot exceed price', () {
        final result = calculator.applyDiscount(
          price: 50.0,
          discount: Discount.fixed(100.0),
        );
        
        expect(result.discountedPrice, equals(0));
        expect(result.discountAmount, equals(50.0));
      });
    });
    
    group('Cart Total Calculations', () {
      test('calculates cart total with multiple items', () {
        final items = [
          CartItem(productId: '1', quantity: 2, unitPrice: 10.0),
          CartItem(productId: '2', quantity: 1, unitPrice: 25.0),
          CartItem(productId: '3', quantity: 3, unitPrice: 5.0),
        ];
        
        final result = calculator.calculateCartTotal(items);
        
        expect(result.subtotal, equals(60.0)); // 20 + 25 + 15
        expect(result.vatAmount, closeTo(9.0, 0.01));
        expect(result.total, closeTo(69.0, 0.01));
      });
    });
  });
  
  group('ZATCA QR Code Generation', () {
    test('generates valid TLV encoded QR data', () {
      final qrService = ZatcaQrService();
      
      final qrData = qrService.generateQrTlv(
        sellerName: 'Test Store',
        vatNumber: '300000000000003',
        timestamp: DateTime(2026, 2, 3, 10, 30, 0),
        total: 115.0,
        vatAmount: 15.0,
      );
      
      // Verify TLV structure
      expect(qrData, isNotEmpty);
      
      // Decode and verify tags
      final decoded = qrService.decodeTlv(qrData);
      expect(decoded[1], equals('Test Store')); // Tag 1: Seller Name
      expect(decoded[2], equals('300000000000003')); // Tag 2: VAT Number
      expect(decoded[4], equals('115.00')); // Tag 4: Total
      expect(decoded[5], equals('15.00')); // Tag 5: VAT
    });
  });
}
```

### Integration Testing

```dart
// test/integration/database_sync_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:drift/drift.dart';
import 'package:pos_app/database/app_database.dart';
import 'package:pos_app/services/sync_service.dart';
import 'package:mockito/mockito.dart';

@GenerateMocks([ApiClient])
void main() {
  late AppDatabase database;
  late MockApiClient mockApi;
  late SyncService syncService;
  
  setUp(() async {
    // Use in-memory database for testing
    database = AppDatabase.forTesting();
    mockApi = MockApiClient();
    syncService = SyncService(database, mockApi);
  });
  
  tearDown(() async {
    await database.close();
  });
  
  group('Offline Sync', () {
    test('queues transaction when offline', () async {
      // Simulate offline
      when(mockApi.isOnline).thenReturn(false);
      
      // Create transaction
      final transaction = await database.createTransaction(
        items: [TestData.cartItem],
        total: 100.0,
      );
      
      // Verify queued
      final queue = await database.getSyncQueue();
      expect(queue.length, equals(1));
      expect(queue.first.entityType, equals('transaction'));
      expect(queue.first.entityId, equals(transaction.id));
    });
    
    test('processes queue when online', () async {
      // Add items to queue
      await database.addToSyncQueue(SyncQueueItem(
        entityType: 'transaction',
        entityId: 'tx-123',
        action: 'create',
        payload: {'total': 100.0},
      ));
      
      // Simulate coming online
      when(mockApi.isOnline).thenReturn(true);
      when(mockApi.syncTransaction(any)).thenAnswer((_) async => true);
      
      // Process queue
      await syncService.processSyncQueue();
      
      // Verify queue is empty
      final queue = await database.getSyncQueue();
      expect(queue, isEmpty);
    });
    
    test('handles sync conflicts correctly', () async {
      // Server has newer version
      final serverProduct = Product(
        id: 'prod-1',
        name: 'Updated Name',
        price: 25.0,
        syncVersion: 5,
      );
      
      // Local has older version
      await database.insertProduct(Product(
        id: 'prod-1',
        name: 'Old Name',
        price: 20.0,
        syncVersion: 3,
      ));
      
      when(mockApi.getProduct('prod-1'))
          .thenAnswer((_) async => serverProduct);
      
      // Sync
      await syncService.syncProduct('prod-1');
      
      // Server version should win
      final localProduct = await database.getProduct('prod-1');
      expect(localProduct.name, equals('Updated Name'));
      expect(localProduct.price, equals(25.0));
    });
  });
}
```

### E2E Testing

```dart
// integration_test/complete_sale_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:pos_app/main.dart' as app;

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  
  group('Complete Sale Flow', () {
    testWidgets('Process sale with cash payment', (tester) async {
      app.main();
      await tester.pumpAndSettle();
      
      // Login with PIN
      await tester.enterText(find.byKey(Key('pin_input')), '1234');
      await tester.tap(find.byKey(Key('login_button')));
      await tester.pumpAndSettle();
      
      // Verify on POS screen
      expect(find.text('New Sale'), findsOneWidget);
      
      // Scan barcode (simulate)
      await tester.enterText(
        find.byKey(Key('barcode_input')),
        '6281007028783',
      );
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pumpAndSettle();
      
      // Verify item added to cart
      expect(find.text('Milk 1L'), findsOneWidget);
      expect(find.text('SAR 6.00'), findsOneWidget);
      
      // Add another item
      await tester.enterText(find.byKey(Key('barcode_input')), '6281007028790');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pumpAndSettle();
      
      // Tap Pay button
      await tester.tap(find.byKey(Key('pay_button')));
      await tester.pumpAndSettle();
      
      // Select Cash payment
      await tester.tap(find.text('Cash'));
      await tester.pumpAndSettle();
      
      // Enter tendered amount
      await tester.enterText(find.byKey(Key('tendered_amount')), '20');
      await tester.tap(find.text('Complete'));
      await tester.pumpAndSettle();
      
      // Verify change displayed
      expect(find.textContaining('Change:'), findsOneWidget);
      
      // Verify receipt printed (mock)
      expect(find.text('Receipt Printed'), findsOneWidget);
      
      // Verify ZATCA QR generated
      expect(find.byKey(Key('zatca_qr')), findsOneWidget);
      
      // Verify back to new sale
      await tester.tap(find.text('New Sale'));
      await tester.pumpAndSettle();
      expect(find.byKey(Key('empty_cart')), findsOneWidget);
    });
    
    testWidgets('Process sale with card payment', (tester) async {
      // Similar flow but with card payment...
    });
    
    testWidgets('Apply discount to sale', (tester) async {
      // Test discount flow...
    });
    
    testWidgets('Process return transaction', (tester) async {
      // Test return flow...
    });
  });
}
```

### CI/CD Pipeline Testing

```yaml
# .github/workflows/test.yml
name: Test Suite

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.19.0'
      
      - name: Install dependencies
        run: flutter pub get
      
      - name: Run unit tests
        run: flutter test --coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: coverage/lcov.info
  
  integration-tests:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: pos_test
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
    
    steps:
      - uses: actions/checkout@v4
      - uses: subosito/flutter-action@v2
      
      - name: Run integration tests
        run: flutter test test/integration/
  
  e2e-tests:
    runs-on: windows-latest  # Desktop E2E on Windows
    steps:
      - uses: actions/checkout@v4
      - uses: subosito/flutter-action@v2
      
      - name: Build Windows app
        run: flutter build windows
      
      - name: Run E2E tests
        run: flutter test integration_test/
```

---

## 🔧 Error Handling & Recovery

### Error Handling Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    ERROR HANDLING LAYERS                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  LAYER 1: UI ERRORS (User-facing)                              │
│  ─────────────────────────────────                             │
│  • Friendly error messages (Arabic/English)                    │
│  • Retry options                                               │
│  • Fallback actions                                            │
│                                                                 │
│  LAYER 2: APPLICATION ERRORS (Logic)                           │
│  ──────────────────────────────────                            │
│  • Validation errors                                           │
│  • Business rule violations                                    │
│  • State inconsistencies                                       │
│                                                                 │
│  LAYER 3: INFRASTRUCTURE ERRORS (Technical)                    │
│  ─────────────────────────────────────────                     │
│  • Network failures                                            │
│  • Database errors                                             │
│  • Hardware failures                                           │
│                                                                 │
│  LAYER 4: CATASTROPHIC ERRORS (Critical)                       │
│  ───────────────────────────────────────                       │
│  • Data corruption                                             │
│  • System crashes                                              │
│  • Security breaches                                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Error Handling Implementation

```dart
// Core error types
abstract class PosException implements Exception {
  final String code;
  final String messageEn;
  final String messageAr;
  final dynamic originalError;
  final StackTrace? stackTrace;
  
  PosException({
    required this.code,
    required this.messageEn,
    required this.messageAr,
    this.originalError,
    this.stackTrace,
  });
  
  String getMessage(String locale) => locale == 'ar' ? messageAr : messageEn;
}

class NetworkException extends PosException {
  NetworkException({dynamic error, StackTrace? stack})
      : super(
          code: 'NETWORK_ERROR',
          messageEn: 'Unable to connect to server. Please check your internet connection.',
          messageAr: 'تعذر الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت.',
          originalError: error,
          stackTrace: stack,
        );
}

class DatabaseException extends PosException {
  DatabaseException({required String operation, dynamic error})
      : super(
          code: 'DATABASE_ERROR',
          messageEn: 'Database error during $operation. Data has been preserved.',
          messageAr: 'خطأ في قاعدة البيانات أثناء $operation. تم الحفاظ على البيانات.',
          originalError: error,
        );
}

class PrinterException extends PosException {
  PrinterException({required String printerName, dynamic error})
      : super(
          code: 'PRINTER_ERROR',
          messageEn: 'Unable to print. Please check if $printerName is connected.',
          messageAr: 'تعذرت الطباعة. يرجى التحقق من اتصال الطابعة $printerName.',
          originalError: error,
        );
}

class ZatcaException extends PosException {
  ZatcaException({required String reason, dynamic error})
      : super(
          code: 'ZATCA_ERROR',
          messageEn: 'ZATCA invoice error: $reason. Transaction saved offline.',
          messageAr: 'خطأ في فاتورة ZATCA: $reason. تم حفظ المعاملة محلياً.',
          originalError: error,
        );
}

// Global error handler
class ErrorHandler {
  final ErrorReporter _reporter;
  final NotificationService _notifications;
  
  ErrorHandler(this._reporter, this._notifications);
  
  Future<T?> run<T>(Future<T> Function() action, {
    String? context,
    T? fallbackValue,
    bool showUserError = true,
  }) async {
    try {
      return await action();
    } on PosException catch (e, stack) {
      _handlePosException(e, stack, context, showUserError);
      return fallbackValue;
    } on DioException catch (e, stack) {
      _handleNetworkException(e, stack, context, showUserError);
      return fallbackValue;
    } catch (e, stack) {
      _handleUnknownException(e, stack, context, showUserError);
      return fallbackValue;
    }
  }
  
  void _handlePosException(
    PosException e,
    StackTrace stack,
    String? context,
    bool showUser,
  ) {
    // Log to remote service
    _reporter.report(
      error: e,
      stackTrace: stack,
      context: context,
      severity: _getSeverity(e),
    );
    
    // Show user notification
    if (showUser) {
      _notifications.showError(
        message: e.getMessage(AppLocale.current),
        action: _getErrorAction(e),
      );
    }
  }
  
  ErrorAction? _getErrorAction(PosException e) {
    switch (e.code) {
      case 'NETWORK_ERROR':
        return ErrorAction(
          label: 'Retry',
          labelAr: 'إعادة المحاولة',
          action: () => _retryLastAction(),
        );
      case 'PRINTER_ERROR':
        return ErrorAction(
          label: 'Settings',
          labelAr: 'الإعدادات',
          action: () => _openPrinterSettings(),
        );
      default:
        return null;
    }
  }
}

// Usage in services
class TransactionService {
  final ErrorHandler _errorHandler;
  
  Future<Transaction?> createTransaction(Cart cart) async {
    return _errorHandler.run(
      () async {
        // Validate cart
        if (cart.isEmpty) {
          throw ValidationException(
            field: 'cart',
            messageEn: 'Cart cannot be empty',
            messageAr: 'السلة لا يمكن أن تكون فارغة',
          );
        }
        
        // Create transaction
        final tx = await _database.createTransaction(cart);
        
        // Generate ZATCA invoice
        await _zatcaService.generateInvoice(tx);
        
        // Print receipt
        await _printerService.printReceipt(tx);
        
        return tx;
      },
      context: 'createTransaction',
      showUserError: true,
    );
  }
}
```

### Crash Recovery

```dart
// Automatic transaction recovery on app restart
class TransactionRecoveryService {
  final AppDatabase _database;
  final NotificationService _notifications;
  
  /// Check for incomplete transactions on startup
  Future<void> checkForIncompleteTransactions() async {
    final incomplete = await _database.getIncompleteTransactions();
    
    if (incomplete.isEmpty) return;
    
    for (final tx in incomplete) {
      final result = await _showRecoveryDialog(tx);
      
      switch (result) {
        case RecoveryAction.complete:
          await _completeTransaction(tx);
          break;
        case RecoveryAction.void_:
          await _voidTransaction(tx);
          break;
        case RecoveryAction.hold:
          await _holdTransaction(tx);
          break;
      }
    }
  }
  
  Future<void> _completeTransaction(Transaction tx) async {
    try {
      // Attempt to complete the transaction
      await _database.completeTransaction(tx.id);
      
      // Generate ZATCA invoice if missing
      if (tx.zatcaUuid == null) {
        await _zatcaService.generateInvoice(tx);
      }
      
      // Print receipt if not printed
      if (!tx.receiptPrinted) {
        await _printerService.printReceipt(tx);
      }
      
      _notifications.showSuccess(
        'Transaction ${tx.number} completed successfully',
      );
    } catch (e) {
      // Mark for manual review
      await _database.markForReview(tx.id, reason: e.toString());
      _notifications.showWarning(
        'Transaction saved for manual review',
      );
    }
  }
}

// Database integrity check
class DatabaseIntegrityService {
  /// Run on app startup and periodically
  Future<IntegrityReport> checkIntegrity() async {
    final issues = <IntegrityIssue>[];
    
    // Check transaction-items relationship
    final orphanedItems = await _database.rawQuery('''
      SELECT ti.id FROM transaction_items ti
      LEFT JOIN transactions t ON ti.transaction_id = t.id
      WHERE t.id IS NULL
    ''');
    
    if (orphanedItems.isNotEmpty) {
      issues.add(IntegrityIssue(
        type: 'orphaned_transaction_items',
        count: orphanedItems.length,
        severity: 'warning',
        autoFix: true,
      ));
    }
    
    // Check inventory consistency
    final inventoryMismatch = await _database.rawQuery('''
      SELECT p.id, p.name, i.quantity as inventory_qty,
        (SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = p.id) as calc_qty
      FROM products p
      JOIN inventory i ON p.id = i.product_id
      WHERE i.quantity != (SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = p.id)
    ''');
    
    if (inventoryMismatch.isNotEmpty) {
      issues.add(IntegrityIssue(
        type: 'inventory_mismatch',
        count: inventoryMismatch.length,
        severity: 'error',
        autoFix: false, // Requires manual review
      ));
    }
    
    // Check ZATCA invoice sequence
    final sequenceGaps = await _checkZatcaSequence();
    if (sequenceGaps.isNotEmpty) {
      issues.add(IntegrityIssue(
        type: 'zatca_sequence_gap',
        count: sequenceGaps.length,
        severity: 'critical',
        autoFix: false,
      ));
    }
    
    return IntegrityReport(
      timestamp: DateTime.now(),
      issues: issues,
      isHealthy: issues.every((i) => i.severity != 'critical'),
    );
  }
  
  /// Auto-fix safe issues
  Future<void> autoFix(List<IntegrityIssue> issues) async {
    for (final issue in issues.where((i) => i.autoFix)) {
      switch (issue.type) {
        case 'orphaned_transaction_items':
          await _database.deleteOrphanedTransactionItems();
          break;
        // Add more auto-fix cases
      }
    }
  }
}
```

---

## 💾 Backup & Disaster Recovery

### Backup Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    BACKUP ARCHITECTURE                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  LOCAL BACKUPS (On Device)                                     │
│  ─────────────────────────                                     │
│  • Automatic: Every 4 hours                                    │
│  • Before updates: Always                                      │
│  • Manual: On-demand                                           │
│  • Retention: Last 7 days                                      │
│  • Location: %AppData%/ThawaniPOS/backups/                     │
│                                                                 │
│  CLOUD BACKUPS (When Online)                                   │
│  ──────────────────────────                                    │
│  • Automatic: Daily at closing time                            │
│  • Transaction data: Real-time sync                            │
│  • Full backup: Weekly                                         │
│  • Retention: 90 days (configurable)                           │
│                                                                 │
│  BACKUP CONTENTS                                               │
│  ───────────────                                               │
│  ✅ SQLite database (encrypted)                                │
│  ✅ Configuration files                                        │
│  ✅ ZATCA certificates (encrypted)                             │
│  ✅ Pending sync queue                                         │
│  ❌ Cached images (can be re-downloaded)                       │
│  ❌ Logs (separate retention policy)                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Backup Implementation

```dart
// Automated backup service
class BackupService {
  static const backupInterval = Duration(hours: 4);
  static const maxLocalBackups = 42; // 7 days × 6 backups/day
  
  final AppDatabase _database;
  final SecureStorage _secureStorage;
  final CloudBackupApi _cloudApi;
  
  Timer? _autoBackupTimer;
  
  /// Start automatic backup schedule
  void startAutoBackup() {
    _autoBackupTimer = Timer.periodic(backupInterval, (_) {
      createLocalBackup(reason: 'scheduled');
    });
  }
  
  /// Create local backup
  Future<BackupResult> createLocalBackup({required String reason}) async {
    try {
      final timestamp = DateTime.now();
      final backupDir = await _getBackupDirectory();
      final fileName = 'backup_${timestamp.toIso8601String().replaceAll(':', '-')}.zip';
      final backupPath = path.join(backupDir.path, fileName);
      
      // Create temporary directory for backup contents
      final tempDir = await Directory.systemTemp.createTemp('pos_backup_');
      
      try {
        // 1. Export database
        final dbPath = path.join(tempDir.path, 'database.sqlite');
        await _database.exportEncrypted(dbPath);
        
        // 2. Export settings
        final settingsPath = path.join(tempDir.path, 'settings.json');
        await _exportSettings(settingsPath);
        
        // 3. Export ZATCA certificates
        final certsPath = path.join(tempDir.path, 'certs.enc');
        await _exportZatcaCerts(certsPath);
        
        // 4. Export sync queue
        final queuePath = path.join(tempDir.path, 'sync_queue.json');
        await _exportSyncQueue(queuePath);
        
        // 5. Create manifest
        final manifest = BackupManifest(
          version: AppInfo.version,
          timestamp: timestamp,
          reason: reason,
          databaseVersion: _database.schemaVersion,
          files: ['database.sqlite', 'settings.json', 'certs.enc', 'sync_queue.json'],
        );
        await File(path.join(tempDir.path, 'manifest.json'))
            .writeAsString(jsonEncode(manifest.toJson()));
        
        // 6. Compress to zip
        final archive = Archive();
        await for (final file in tempDir.list(recursive: true)) {
          if (file is File) {
            final relativePath = path.relative(file.path, from: tempDir.path);
            archive.addFile(ArchiveFile(
              relativePath,
              await file.length(),
              await file.readAsBytes(),
            ));
          }
        }
        
        final zipBytes = ZipEncoder().encode(archive);
        await File(backupPath).writeAsBytes(zipBytes!);
        
        // 7. Clean up old backups
        await _cleanupOldBackups(backupDir);
        
        // 8. Record backup
        await _recordBackup(BackupRecord(
          fileName: fileName,
          path: backupPath,
          size: zipBytes.length,
          timestamp: timestamp,
          reason: reason,
          isLocal: true,
        ));
        
        return BackupResult.success(backupPath, zipBytes.length);
      } finally {
        await tempDir.delete(recursive: true);
      }
    } catch (e, stack) {
      ErrorReporter.report(e, stack, context: 'createLocalBackup');
      return BackupResult.failed(e.toString());
    }
  }
  
  /// Restore from backup
  Future<RestoreResult> restoreFromBackup(String backupPath) async {
    try {
      // 1. Verify backup integrity
      final manifest = await _verifyBackup(backupPath);
      if (manifest == null) {
        return RestoreResult.failed('Invalid or corrupted backup');
      }
      
      // 2. Check version compatibility
      if (!_isCompatibleVersion(manifest.version)) {
        return RestoreResult.failed(
          'Backup from incompatible version ${manifest.version}',
        );
      }
      
      // 3. Create safety backup before restore
      await createLocalBackup(reason: 'pre_restore_safety');
      
      // 4. Extract backup
      final tempDir = await Directory.systemTemp.createTemp('pos_restore_');
      
      try {
        final bytes = await File(backupPath).readAsBytes();
        final archive = ZipDecoder().decodeBytes(bytes);
        
        for (final file in archive) {
          if (file.isFile) {
            final outputFile = File(path.join(tempDir.path, file.name));
            await outputFile.create(recursive: true);
            await outputFile.writeAsBytes(file.content as List<int>);
          }
        }
        
        // 5. Close current database
        await _database.close();
        
        // 6. Replace database
        final dbBackupPath = path.join(tempDir.path, 'database.sqlite');
        await _database.restoreFromEncrypted(dbBackupPath);
        
        // 7. Restore settings
        await _restoreSettings(path.join(tempDir.path, 'settings.json'));
        
        // 8. Restore ZATCA certs
        await _restoreZatcaCerts(path.join(tempDir.path, 'certs.enc'));
        
        // 9. Reopen database
        await _database.open();
        
        return RestoreResult.success(manifest);
      } finally {
        await tempDir.delete(recursive: true);
      }
    } catch (e, stack) {
      ErrorReporter.report(e, stack, context: 'restoreFromBackup');
      return RestoreResult.failed(e.toString());
    }
  }
  
  /// Upload backup to cloud
  Future<void> uploadToCloud(String localBackupPath) async {
    final file = File(localBackupPath);
    if (!await file.exists()) return;
    
    final bytes = await file.readAsBytes();
    final fileName = path.basename(localBackupPath);
    
    await _cloudApi.uploadBackup(
      fileName: fileName,
      data: bytes,
      metadata: {
        'store_id': AppConfig.storeId,
        'device_id': DeviceInfo.deviceId,
        'timestamp': DateTime.now().toIso8601String(),
      },
    );
  }
}
```

---

## 🌍 Localization & i18n

### Localization Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    LOCALIZATION SUPPORT                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  LANGUAGES                                                     │
│  ─────────                                                     │
│  • Arabic (ar) - Primary, RTL                                  │
│  • English (en) - Secondary, LTR                               │
│                                                                 │
│  LOCALIZED CONTENT                                             │
│  ─────────────────                                             │
│  ✅ UI strings (buttons, labels, messages)                     │
│  ✅ Error messages                                             │
│  ✅ Receipt templates                                          │
│  ✅ Reports                                                    │
│  ✅ Email/SMS notifications                                    │
│  ✅ Date/time formats                                          │
│  ✅ Number formats                                             │
│  ✅ Currency formats                                           │
│                                                                 │
│  FORMAT DIFFERENCES                                            │
│  ──────────────────                                            │
│  Arabic:                  English:                             │
│  • ١٢٣٫٤٥٦               • 123.456                            │
│  • ١٥ فبراير ٢٠٢٦        • 15 Feb 2026                        │
│  • ١٢:٣٠ م              • 12:30 PM                            │
│  • ١٢٣٫٤٥ ر.س            • SAR 123.45                         │
│  • RTL layout             • LTR layout                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Localization Implementation

```dart
// lib/l10n/app_localizations.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class AppLocalizations {
  final Locale locale;
  
  AppLocalizations(this.locale);
  
  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }
  
  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();
  
  // Strings
  String get appTitle => _t('app_title');
  String get newSale => _t('new_sale');
  String get pay => _t('pay');
  String get cash => _t('cash');
  String get card => _t('card');
  String get subtotal => _t('subtotal');
  String get vat => _t('vat');
  String get total => _t('total');
  String get discount => _t('discount');
  String get quantity => _t('quantity');
  String get price => _t('price');
  String get search => _t('search');
  String get scanBarcode => _t('scan_barcode');
  String get emptyCart => _t('empty_cart');
  String get addToCart => _t('add_to_cart');
  String get removeFromCart => _t('remove_from_cart');
  String get clearCart => _t('clear_cart');
  String get holdSale => _t('hold_sale');
  String get recallSale => _t('recall_sale');
  String get printReceipt => _t('print_receipt');
  String get emailReceipt => _t('email_receipt');
  
  // Parameterized strings
  String itemCount(int count) => _tp('item_count', {'count': count});
  String changeAmount(String amount) => _tp('change_amount', {'amount': amount});
  String receiptNumber(String number) => _tp('receipt_number', {'number': number});
  String stockRemaining(int count) => _tp('stock_remaining', {'count': count});
  
  // Error messages
  String get errorNetworkOffline => _t('error_network_offline');
  String get errorPrinterNotConnected => _t('error_printer_not_connected');
  String get errorInvalidBarcode => _t('error_invalid_barcode');
  String get errorInsufficientStock => _t('error_insufficient_stock');
  
  String _t(String key) => _localizedStrings[locale.languageCode]?[key] ?? key;
  
  String _tp(String key, Map<String, dynamic> params) {
    var result = _t(key);
    params.forEach((k, v) => result = result.replaceAll('{$k}', v.toString()));
    return result;
  }
  
  static const _localizedStrings = {
    'en': {
      'app_title': 'Thawani POS',
      'new_sale': 'New Sale',
      'pay': 'Pay',
      'cash': 'Cash',
      'card': 'Card',
      'subtotal': 'Subtotal',
      'vat': 'VAT (15%)',
      'total': 'Total',
      'discount': 'Discount',
      'quantity': 'Qty',
      'price': 'Price',
      'search': 'Search',
      'scan_barcode': 'Scan or enter barcode...',
      'empty_cart': 'Cart is empty',
      'item_count': '{count} items',
      'change_amount': 'Change: {amount}',
      'error_network_offline': 'You are offline. Transactions will sync when connected.',
      'error_printer_not_connected': 'Printer not connected. Please check connection.',
    },
    'ar': {
      'app_title': 'ثواني نقطة البيع',
      'new_sale': 'بيع جديد',
      'pay': 'ادفع',
      'cash': 'نقد',
      'card': 'بطاقة',
      'subtotal': 'المجموع الفرعي',
      'vat': 'ضريبة القيمة المضافة (15%)',
      'total': 'المجموع',
      'discount': 'خصم',
      'quantity': 'الكمية',
      'price': 'السعر',
      'search': 'بحث',
      'scan_barcode': 'امسح أو أدخل الباركود...',
      'empty_cart': 'السلة فارغة',
      'item_count': '{count} عناصر',
      'change_amount': 'الباقي: {amount}',
      'error_network_offline': 'أنت غير متصل. سيتم مزامنة المعاملات عند الاتصال.',
      'error_printer_not_connected': 'الطابعة غير متصلة. يرجى التحقق من الاتصال.',
    },
  };
}

// Number formatting
class PosNumberFormat {
  final Locale locale;
  
  PosNumberFormat(this.locale);
  
  String formatCurrency(double amount) {
    final format = NumberFormat.currency(
      locale: locale.toString(),
      symbol: locale.languageCode == 'ar' ? 'ر.س ' : 'SAR ',
      decimalDigits: 2,
    );
    return format.format(amount);
  }
  
  String formatQuantity(double quantity, {bool useArabicNumerals = true}) {
    if (locale.languageCode == 'ar' && useArabicNumerals) {
      return _toArabicNumerals(quantity.toString());
    }
    return quantity.toString();
  }
  
  String formatDate(DateTime date) {
    final format = DateFormat.yMMMMd(locale.toString());
    return format.format(date);
  }
  
  String formatTime(DateTime time) {
    final format = DateFormat.jm(locale.toString());
    return format.format(time);
  }
  
  String formatDateTime(DateTime dateTime) {
    return '${formatDate(dateTime)} ${formatTime(dateTime)}';
  }
  
  String _toArabicNumerals(String input) {
    const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    const arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    
    for (var i = 0; i < english.length; i++) {
      input = input.replaceAll(english[i], arabic[i]);
    }
    input = input.replaceAll('.', '٫'); // Arabic decimal separator
    return input;
  }
}

// RTL-aware widgets
class DirectionalWidget extends StatelessWidget {
  final Widget child;
  
  const DirectionalWidget({required this.child, super.key});
  
  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: Localizations.localeOf(context).languageCode == 'ar'
          ? TextDirection.rtl
          : TextDirection.ltr,
      child: child,
    );
  }
}

// RTL-aware row for cart items
class CartItemRow extends StatelessWidget {
  final String name;
  final int quantity;
  final double price;
  
  @override
  Widget build(BuildContext context) {
    final isRtl = Directionality.of(context) == TextDirection.rtl;
    final format = PosNumberFormat(Localizations.localeOf(context));
    
    return Row(
      children: [
        // Product name (expands)
        Expanded(
          child: Text(
            name,
            textAlign: isRtl ? TextAlign.right : TextAlign.left,
          ),
        ),
        
        // Quantity
        SizedBox(
          width: 50,
          child: Text(
            '×${format.formatQuantity(quantity.toDouble())}',
            textAlign: TextAlign.center,
          ),
        ),
        
        // Price (always aligned based on reading direction)
        SizedBox(
          width: 100,
          child: Text(
            format.formatCurrency(price),
            textAlign: isRtl ? TextAlign.left : TextAlign.right,
          ),
        ),
      ],
    );
  }
}
```

---

## ⚡ Performance Optimization

### Performance Targets

```
┌─────────────────────────────────────────────────────────────────┐
│                    PERFORMANCE TARGETS                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  CRITICAL PATHS (must be fast)                                 │
│  ──────────────────────────────                                │
│  • Barcode lookup: < 50ms                                      │
│  • Add item to cart: < 30ms                                    │
│  • Calculate total: < 10ms                                     │
│  • Complete transaction: < 200ms                               │
│  • Print receipt: < 2s                                         │
│                                                                 │
│  STARTUP PERFORMANCE                                           │
│  ────────────────────                                          │
│  • Cold start to usable: < 3s                                  │
│  • Warm start (resume): < 500ms                                │
│                                                                 │
│  MEMORY TARGETS                                                │
│  ──────────────                                                │
│  • Idle memory: < 200MB                                        │
│  • Active with 50 cart items: < 300MB                          │
│  • Peak during reporting: < 500MB                              │
│                                                                 │
│  DATABASE PERFORMANCE                                          │
│  ────────────────────                                          │
│  • Product search (10k products): < 100ms                      │
│  • Transaction insert: < 50ms                                  │
│  • Daily report query: < 2s                                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Optimization Implementation

```dart
// Product cache for fast lookups
class ProductCache {
  final AppDatabase _database;
  
  // In-memory cache for frequently accessed products
  final _barcodeCache = <String, Product>{};
  final _idCache = <String, Product>{};
  final _searchIndex = <String, Set<String>>{}; // term -> product IDs
  
  int _cacheSize = 0;
  static const maxCacheSize = 1000;
  
  /// Initialize cache with frequent products
  Future<void> initialize() async {
    // Load top 500 most-sold products
    final topProducts = await _database.getTopProducts(limit: 500);
    for (final product in topProducts) {
      _addToCache(product);
    }
    
    // Build search index for all products
    await _buildSearchIndex();
  }
  
  /// Fast barcode lookup (< 50ms target)
  Future<Product?> getByBarcode(String barcode) async {
    // Check cache first
    if (_barcodeCache.containsKey(barcode)) {
      return _barcodeCache[barcode];
    }
    
    // Query database
    final product = await _database.getProductByBarcode(barcode);
    
    if (product != null) {
      _addToCache(product);
    }
    
    return product;
  }
  
  /// Fast product search
  Future<List<Product>> search(String query, {int limit = 20}) async {
    if (query.length < 2) return [];
    
    final queryLower = query.toLowerCase();
    final matchingIds = <String>{};
    
    // Check search index
    for (final term in _searchIndex.keys) {
      if (term.contains(queryLower)) {
        matchingIds.addAll(_searchIndex[term]!);
      }
    }
    
    // Get products from cache or database
    final results = <Product>[];
    for (final id in matchingIds.take(limit)) {
      if (_idCache.containsKey(id)) {
        results.add(_idCache[id]!);
      } else {
        final product = await _database.getProduct(id);
        if (product != null) {
          results.add(product);
          _addToCache(product);
        }
      }
    }
    
    return results;
  }
  
  void _addToCache(Product product) {
    if (_cacheSize >= maxCacheSize) {
      _evictOldest();
    }
    
    _idCache[product.id] = product;
    if (product.barcode != null) {
      _barcodeCache[product.barcode!] = product;
    }
    _cacheSize++;
  }
  
  void _evictOldest() {
    // Simple eviction - remove first 10%
    final toRemove = _idCache.keys.take(maxCacheSize ~/ 10).toList();
    for (final id in toRemove) {
      final product = _idCache.remove(id);
      if (product?.barcode != null) {
        _barcodeCache.remove(product!.barcode);
      }
      _cacheSize--;
    }
  }
  
  Future<void> _buildSearchIndex() async {
    final products = await _database.getAllProducts();
    
    for (final product in products) {
      final terms = <String>[];
      
      // Add name terms
      terms.addAll(product.name.toLowerCase().split(' '));
      terms.addAll(product.nameAr.split(' '));
      
      // Add barcode
      if (product.barcode != null) {
        terms.add(product.barcode!);
      }
      
      // Add SKU
      if (product.sku != null) {
        terms.add(product.sku!.toLowerCase());
      }
      
      for (final term in terms) {
        _searchIndex.putIfAbsent(term, () => {}).add(product.id);
      }
    }
  }
  
  /// Invalidate cache for updated product
  void invalidate(String productId) {
    final product = _idCache.remove(productId);
    if (product?.barcode != null) {
      _barcodeCache.remove(product!.barcode);
    }
    _cacheSize--;
  }
  
  /// Full cache refresh
  Future<void> refresh() async {
    _barcodeCache.clear();
    _idCache.clear();
    _searchIndex.clear();
    _cacheSize = 0;
    await initialize();
  }
}

// Lazy loading for large lists
class LazyProductList extends StatelessWidget {
  final List<String> productIds;
  
  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      itemCount: productIds.length,
      itemBuilder: (context, index) {
        return FutureBuilder<Product?>(
          future: context.read<ProductCache>().getById(productIds[index]),
          builder: (context, snapshot) {
            if (!snapshot.hasData) {
              return const ProductListItemSkeleton();
            }
            return ProductListItem(product: snapshot.data!);
          },
        );
      },
    );
  }
}

// Database query optimization
class OptimizedQueries {
  final AppDatabase _db;
  
  /// Paginated product list
  Future<List<Product>> getProducts({
    required int page,
    required int pageSize,
    String? categoryId,
    String? search,
  }) async {
    return _db.customSelect(
      '''
      SELECT * FROM products p
      WHERE p.is_active = TRUE
        ${categoryId != null ? 'AND p.category_id = ?' : ''}
        ${search != null ? 'AND (p.name LIKE ? OR p.name_ar LIKE ? OR p.barcode LIKE ?)' : ''}
      ORDER BY p.name
      LIMIT ? OFFSET ?
      ''',
      variables: [
        if (categoryId != null) Variable.withString(categoryId),
        if (search != null) ...[
          Variable.withString('%$search%'),
          Variable.withString('%$search%'),
          Variable.withString('%$search%'),
        ],
        Variable.withInt(pageSize),
        Variable.withInt(page * pageSize),
      ],
      readsFrom: {_db.products},
    ).map((row) => Product.fromRow(row)).get();
  }
  
  /// Optimized daily sales report
  Future<DailySalesReport> getDailySalesReport(DateTime date) async {
    final result = await _db.customSelect(
      '''
      SELECT 
        COUNT(*) as transaction_count,
        COALESCE(SUM(total_amount), 0) as total_sales,
        COALESCE(SUM(tax_amount), 0) as total_tax,
        COALESCE(SUM(discount_amount), 0) as total_discount,
        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales,
        COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END), 0) as card_sales
      FROM transactions
      WHERE DATE(created_at) = DATE(?)
        AND status = 'completed'
      ''',
      variables: [Variable.withDateTime(date)],
    ).getSingleOrNull();
    
    // Use single query instead of multiple
    return DailySalesReport.fromRow(result);
  }
}
```

---

## 👥 Customer Features

### Customer Management

```dart
// Customer database and loyalty
class CustomerService {
  final AppDatabase _database;
  
  /// Search customers by phone or name
  Future<List<Customer>> searchCustomers(String query) async {
    return _database.customers.findAll(
      where: (c) => c.phone.contains(query) | c.name.contains(query),
      limit: 10,
    );
  }
  
  /// Get or create customer by phone
  Future<Customer> getOrCreateByPhone(String phone) async {
    var customer = await _database.customers.findByPhone(phone);
    
    if (customer == null) {
      customer = await _database.customers.insert(Customer(
        id: Uuid().v4(),
        phone: phone,
        createdAt: DateTime.now(),
      ));
    }
    
    return customer;
  }
  
  /// Get customer purchase history
  Future<CustomerHistory> getHistory(String customerId) async {
    final transactions = await _database.transactions.findByCustomer(
      customerId,
      limit: 50,
    );
    
    final totalSpent = transactions.fold<double>(
      0,
      (sum, tx) => sum + tx.total,
    );
    
    return CustomerHistory(
      transactions: transactions,
      transactionCount: transactions.length,
      totalSpent: totalSpent,
      firstPurchase: transactions.lastOrNull?.createdAt,
      lastPurchase: transactions.firstOrNull?.createdAt,
    );
  }
}

// Loyalty points system
class LoyaltyService {
  static const pointsPerSar = 1; // 1 point per SAR spent
  static const sarPerPoint = 0.1; // 10 points = 1 SAR
  
  final AppDatabase _database;
  
  /// Calculate points for transaction
  int calculatePoints(double amount) {
    return (amount * pointsPerSar).floor();
  }
  
  /// Award points for transaction
  Future<void> awardPoints(String customerId, Transaction tx) async {
    final points = calculatePoints(tx.total);
    
    await _database.loyaltyTransactions.insert(LoyaltyTransaction(
      id: Uuid().v4(),
      customerId: customerId,
      transactionId: tx.id,
      type: 'earn',
      points: points,
      description: 'Purchase #${tx.number}',
      createdAt: DateTime.now(),
    ));
    
    await _database.customers.addPoints(customerId, points);
  }
  
  /// Redeem points
  Future<double> redeemPoints(String customerId, int points) async {
    final customer = await _database.customers.find(customerId);
    
    if (customer.loyaltyPoints < points) {
      throw InsufficientPointsException();
    }
    
    final discount = points * sarPerPoint;
    
    await _database.loyaltyTransactions.insert(LoyaltyTransaction(
      id: Uuid().v4(),
      customerId: customerId,
      type: 'redeem',
      points: -points,
      description: 'Redeemed for ${discount.toStringAsFixed(2)} SAR discount',
      createdAt: DateTime.now(),
    ));
    
    await _database.customers.deductPoints(customerId, points);
    
    return discount;
  }
  
  /// Get loyalty tiers
  LoyaltyTier getTier(int totalPoints) {
    if (totalPoints >= 10000) return LoyaltyTier.platinum;
    if (totalPoints >= 5000) return LoyaltyTier.gold;
    if (totalPoints >= 1000) return LoyaltyTier.silver;
    return LoyaltyTier.bronze;
  }
}

// Digital receipt delivery
class ReceiptDeliveryService {
  final WhatsAppApi _whatsApp;
  final SmsApi _sms;
  final EmailApi _email;
  
  /// Send receipt via WhatsApp
  Future<void> sendViaWhatsApp(Transaction tx, String phone) async {
    final receipt = await _generateReceiptText(tx);
    final qrImage = await _generateQrImage(tx.zatcaQrCode);
    
    await _whatsApp.sendMessage(
      phone: phone,
      message: receipt,
      image: qrImage,
    );
  }
  
  /// Send receipt via SMS
  Future<void> sendViaSms(Transaction tx, String phone) async {
    final shortReceipt = '''
شكراً لزيارة ${tx.storeName}
الفاتورة: ${tx.number}
المجموع: ${tx.total.toStringAsFixed(2)} ر.س
${tx.createdAt.toIso8601String()}
''';
    
    await _sms.send(phone: phone, message: shortReceipt);
  }
  
  /// Send receipt via Email
  Future<void> sendViaEmail(Transaction tx, String email) async {
    final pdfReceipt = await _generatePdfReceipt(tx);
    
    await _email.send(
      to: email,
      subject: 'Receipt #${tx.number} from ${tx.storeName}',
      body: 'Thank you for your purchase. Please find your receipt attached.',
      attachments: [
        EmailAttachment(
          name: 'receipt_${tx.number}.pdf',
          data: pdfReceipt,
        ),
      ],
    );
  }
}
```

---

## 🚀 Deployment & Auto-Updates

### Update Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    UPDATE STRATEGY                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  UPDATE CHANNELS                                               │
│  ───────────────                                               │
│  • Stable: Production releases (recommended)                   │
│  • Beta: Early access to new features                          │
│  • Canary: Latest development builds                           │
│                                                                 │
│  UPDATE TYPES                                                  │
│  ────────────                                                  │
│  • Critical: Security fixes (auto-install on next launch)      │
│  • Major: New features (notify, user confirms)                 │
│  • Minor: Bug fixes (auto-install during off-hours)            │
│  • Patch: Small fixes (silent update)                          │
│                                                                 │
│  UPDATE PROCESS                                                │
│  ──────────────                                                │
│  1. Check for updates (startup + every 4 hours)                │
│  2. Download in background                                     │
│  3. Verify signature                                           │
│  4. Create backup                                              │
│  5. Apply update (based on type and time)                      │
│  6. Restart if needed                                          │
│                                                                 │
│  ROLLBACK CAPABILITY                                           │
│  ────────────────────                                          │
│  • Keep last 3 versions                                        │
│  • Auto-rollback on crash loop                                 │
│  • Manual rollback available                                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Auto-Update Implementation

```dart
// Auto-update service
class AutoUpdateService {
  final UpdateApi _api;
  final SecureStorage _storage;
  final NotificationService _notifications;
  
  static const updateCheckInterval = Duration(hours: 4);
  Timer? _checkTimer;
  
  /// Start background update checking
  void startBackgroundChecks() {
    _checkTimer = Timer.periodic(updateCheckInterval, (_) {
      checkForUpdates();
    });
  }
  
  /// Check for available updates
  Future<UpdateInfo?> checkForUpdates() async {
    try {
      final currentVersion = await PackageInfo.fromPlatform()
          .then((p) => p.version);
      
      final channel = await _storage.read(key: 'update_channel') ?? 'stable';
      
      final updateInfo = await _api.checkUpdate(
        currentVersion: currentVersion,
        channel: channel,
        platform: Platform.operatingSystem,
      );
      
      if (updateInfo != null && updateInfo.version != currentVersion) {
        await _handleUpdateAvailable(updateInfo);
      }
      
      return updateInfo;
    } catch (e) {
      // Silently fail - don't interrupt user
      return null;
    }
  }
  
  Future<void> _handleUpdateAvailable(UpdateInfo update) async {
    switch (update.severity) {
      case UpdateSeverity.critical:
        // Download immediately, install on next launch
        await _downloadUpdate(update);
        _notifications.showPersistent(
          title: 'Critical Security Update',
          message: 'A security update will be installed on next launch.',
        );
        break;
        
      case UpdateSeverity.major:
        // Notify user, let them decide
        _notifications.showAction(
          title: 'New Version Available',
          message: 'Version ${update.version} is available with new features.',
          action: () => _showUpdateDialog(update),
        );
        break;
        
      case UpdateSeverity.minor:
        // Download silently, install during off-hours
        await _downloadUpdate(update);
        if (_isOffHours()) {
          await _installUpdate(update);
        }
        break;
        
      case UpdateSeverity.patch:
        // Silent update
        await _downloadUpdate(update);
        await _installUpdate(update);
        break;
    }
  }
  
  Future<void> _downloadUpdate(UpdateInfo update) async {
    final downloadPath = await _getDownloadPath(update.version);
    
    // Download with progress
    await _api.downloadUpdate(
      url: update.downloadUrl,
      savePath: downloadPath,
      onProgress: (received, total) {
        // Update progress indicator
      },
    );
    
    // Verify signature
    final isValid = await _verifySignature(downloadPath, update.signature);
    if (!isValid) {
      await File(downloadPath).delete();
      throw UpdateException('Invalid update signature');
    }
    
    // Mark as ready to install
    await _storage.write(key: 'pending_update', value: update.toJson());
  }
  
  Future<void> _installUpdate(UpdateInfo update) async {
    // Create backup first
    await BackupService.instance.createLocalBackup(reason: 'pre_update');
    
    final updatePath = await _getDownloadPath(update.version);
    
    if (Platform.isWindows) {
      // Use Windows installer
      await Process.run(
        'msiexec',
        ['/i', updatePath, '/quiet', '/norestart'],
      );
    } else if (Platform.isMacOS) {
      // Mount DMG and run installer
      await Process.run('hdiutil', ['attach', updatePath]);
      // Copy app to Applications
    }
    
    // Schedule restart
    _scheduleRestart();
  }
  
  bool _isOffHours() {
    final now = DateTime.now();
    return now.hour < 6 || now.hour > 22;
  }
}

// Version management
class VersionManager {
  static const maxKeptVersions = 3;
  
  /// Rollback to previous version
  Future<void> rollback() async {
    final previousVersions = await _getPreviousVersions();
    
    if (previousVersions.isEmpty) {
      throw RollbackException('No previous versions available');
    }
    
    final targetVersion = previousVersions.first;
    
    // Restore from backup
    await BackupService.instance.restoreFromBackup(
      targetVersion.backupPath,
    );
    
    // Switch executable
    await _switchToVersion(targetVersion);
    
    // Restart
    await _restart();
  }
  
  /// Auto-rollback on crash loop detection
  Future<void> checkCrashLoop() async {
    final crashes = await _getCrashCount();
    
    if (crashes >= 3) {
      // Likely a bad update
      await _notifications.showCritical(
        title: 'App Stability Issue',
        message: 'Rolling back to previous stable version...',
      );
      
      await rollback();
    }
  }
}
```

---

## ♿ Accessibility

### Accessibility Features

```
┌─────────────────────────────────────────────────────────────────┐
│                    ACCESSIBILITY SUPPORT                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  VISUAL                                                        │
│  ──────                                                        │
│  • High contrast mode                                          │
│  • Large text support (up to 200%)                             │
│  • Screen reader compatibility                                 │
│  • Color blind friendly palette                                │
│                                                                 │
│  MOTOR                                                         │
│  ─────                                                         │
│  • Keyboard navigation (all functions)                         │
│  • Large touch targets (min 44x44px)                           │
│  • Reduced motion option                                       │
│  • Sticky keys support                                         │
│                                                                 │
│  COGNITIVE                                                     │
│  ─────────                                                     │
│  • Clear, simple language                                      │
│  • Consistent navigation                                       │
│  • Error prevention and recovery                               │
│  • Focus indicators                                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

```dart
// Accessibility settings
class AccessibilitySettings {
  bool highContrast;
  double textScale;
  bool reduceMotion;
  bool screenReaderEnabled;
  
  AccessibilitySettings({
    this.highContrast = false,
    this.textScale = 1.0,
    this.reduceMotion = false,
    this.screenReaderEnabled = false,
  });
}

// Accessible button with semantic label
class AccessibleButton extends StatelessWidget {
  final String label;
  final String semanticLabel;
  final VoidCallback onPressed;
  final IconData? icon;
  
  @override
  Widget build(BuildContext context) {
    final settings = context.watch<AccessibilitySettings>();
    
    return Semantics(
      label: semanticLabel,
      button: true,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          minimumSize: const Size(44, 44), // WCAG touch target
          textStyle: TextStyle(
            fontSize: 16 * settings.textScale,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 24 * settings.textScale),
              const SizedBox(width: 8),
            ],
            Text(label),
          ],
        ),
      ),
    );
  }
}

// Keyboard navigation for POS
class KeyboardNavigablePOS extends StatefulWidget {
  @override
  State<KeyboardNavigablePOS> createState() => _KeyboardNavigablePOSState();
}

class _KeyboardNavigablePOSState extends State<KeyboardNavigablePOS> {
  final FocusNode _focusNode = FocusNode();
  
  @override
  Widget build(BuildContext context) {
    return Shortcuts(
      shortcuts: {
        // F1 - Help
        LogicalKeySet(LogicalKeyboardKey.f1): const HelpIntent(),
        // F2 - New Sale
        LogicalKeySet(LogicalKeyboardKey.f2): const NewSaleIntent(),
        // F3 - Search
        LogicalKeySet(LogicalKeyboardKey.f3): const SearchIntent(),
        // F4 - Payment
        LogicalKeySet(LogicalKeyboardKey.f4): const PaymentIntent(),
        // F8 - Hold
        LogicalKeySet(LogicalKeyboardKey.f8): const HoldIntent(),
        // F9 - Recall
        LogicalKeySet(LogicalKeyboardKey.f9): const RecallIntent(),
        // Esc - Cancel
        LogicalKeySet(LogicalKeyboardKey.escape): const CancelIntent(),
      },
      child: Actions(
        actions: {
          HelpIntent: CallbackAction<HelpIntent>(
            onInvoke: (_) => _showHelp(),
          ),
          NewSaleIntent: CallbackAction<NewSaleIntent>(
            onInvoke: (_) => _startNewSale(),
          ),
          // ... more actions
        },
        child: Focus(
          focusNode: _focusNode,
          autofocus: true,
          child: POSScreen(),
        ),
      ),
    );
  }
}
```

---

## 💰 Business Model

### Pricing Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    PRICING TIERS                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │    STARTER      │  │   BUSINESS      │  │   ENTERPRISE    │ │
│  │                 │  │                 │  │                 │ │
│  │   149 SAR/mo    │  │   299 SAR/mo    │  │   Custom        │ │
│  │                 │  │                 │  │                 │ │
│  │ • 1 Register    │  │ • 3 Registers   │  │ • Unlimited     │ │
│  │ • 1,000 SKUs    │  │ • 10,000 SKUs   │  │ • Unlimited     │ │
│  │ • Basic reports │  │ • Full reports  │  │ • Custom reports│ │
│  │ • Email support │  │ • Phone support │  │ • Dedicated AM  │ │
│  │ • ZATCA ready   │  │ • ZATCA ready   │  │ • ZATCA ready   │ │
│  │                 │  │ • Multi-user    │  │ • Multi-store   │ │
│  │                 │  │ • Inventory     │  │ • API access    │ │
│  │                 │  │                 │  │ • On-premise    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  Add-ons:                                                       │
│  • Extra register: +50 SAR/mo                                   │
│  • Thawani integration: +100 SAR/mo                             │
│  • Other delivery platforms: +50 SAR/mo each                    │
│  • On-premise server: +500 SAR/mo                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Revenue Projections

| Year | Stores | ARPU (SAR) | MRR (SAR) | ARR (SAR) |
|------|--------|------------|-----------|-----------|
| Y1 | 100 | 200 | 20,000 | 240,000 |
| Y2 | 500 | 250 | 125,000 | 1,500,000 |
| Y3 | 2,000 | 300 | 600,000 | 7,200,000 |

### Additional Revenue Streams
1. **Hardware Sales**: POS terminals, printers, scanners (markup)
2. **Implementation**: Setup fee (500-2000 SAR per store)
3. **Training**: On-site training (500 SAR/session)
4. **Custom Development**: API integrations, custom reports
5. **Transaction Fees**: If processing payments (future)

---

## ⚖️ Build vs Open Source (Revised)

### For a Commercial Product, Build Custom

Given you want to **sell** this POS system, building custom is the better choice:

| Factor | Build Custom | Use Open Source |
|--------|--------------|-----------------|
| **Ownership** | ✅ Full IP ownership | ⚠️ License restrictions |
| **Branding** | ✅ 100% your brand | ⚠️ May need attribution |
| **Pricing Freedom** | ✅ Any model | ⚠️ May have limits |
| **Control** | ✅ Every feature | ❌ Limited by base |
| **Support** | ✅ You define SLA | ❌ Dependent on community |
| **Differentiation** | ✅ Unique value | ❌ Competitors use same |
| **ZATCA** | ✅ Native integration | ⚠️ Bolt-on |
| **Time to Market** | ✅ Fast (team knows it) | ⚠️ Learning curve |
| **Initial Cost** | ✅ Lower (existing skills) | ⚠️ Higher (new tech) |

### Recommendation: **Build Custom with Flutter Desktop**

For a product you want to sell:
1. Full ownership of code
2. No licensing concerns
3. Your team already knows Flutter
4. Single codebase: Desktop + Tablet + Mobile
5. Faster time to market
6. Beautiful touch-optimized UI
7. Built-in Arabic/RTL support

---

## 🛠️ Technology Stack Decision

### Recommended Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    RECOMMENDED STACK                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  DESKTOP POS APP (Flutter Desktop - Windows Primary)           │
│  ├── UI Framework: Flutter 3.x                                 │
│  ├── State Management: Riverpod or Bloc                        │
│  ├── UI Components: Custom + flutter_adaptive_scaffold         │
│  ├── Local Database: Drift (SQLite ORM)                        │
│  ├── ZATCA Crypto: pointycastle (ECDSA, SHA-256)               │
│  ├── Printing: esc_pos_printer + flutter_thermal_printer       │
│  ├── HTTP Client: Dio                                          │
│  └── Arabic/RTL: Built-in Flutter support                      │
│                                                                 │
│  SUPER ADMIN PANEL (Thawani Internal)                          │
│  ├── Framework: Laravel 11 + Filament v3                       │
│  ├── UI: Filament (pre-built admin components)                 │
│  ├── Database: PostgreSQL (shared with API)                    │
│  ├── Auth: Laravel Sanctum                                     │
│  └── Hosting: Same server as API                               │
│                                                                 │
│  STORE OWNER DASHBOARD                                          │
│  ├── Option A: Laravel + Livewire 3                            │
│  ├── Option B: Flutter Web (shared with desktop)               │
│  ├── UI: Tailwind CSS                                          │
│  └── Auth: Laravel Sanctum                                     │
│                                                                 │
│  TABLET POS (Same Flutter Codebase)                            │
│  ├── Framework: Flutter (shared with desktop)                  │
│  ├── Adaptive Layout: Responsive for tablet screens            │
│  ├── Offline: Same Drift database                              │
│  └── Code Sharing: 80-90% shared with desktop                  │
│                                                                 │
│  MOBILE COMPANION APP (Manager On-The-Go)                      │
│  ├── Framework: Flutter (shared codebase)                      │
│  ├── Features: Reports, inventory check, alerts                │
│  ├── Barcode: Native camera scanner                            │
│  └── Code Sharing: 70% shared with POS                         │
│                                                                 │
│  CLOUD INFRASTRUCTURE                                          │
│  ├── API: Laravel (existing Thawani infrastructure)            │
│  ├── Database: PostgreSQL                                      │
│  ├── File Storage: S3 / DigitalOcean Spaces                    │
│  ├── Sync: REST API with polling (5-minute intervals)          │
│  └── Queue: Redis for background jobs                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Why This Stack?

1. **Flutter Desktop**
   - Your team already knows it (HUGE advantage)
   - Single codebase for desktop, tablet, mobile
   - Touch-optimized UI (perfect for POS touchscreens)
   - Arabic/RTL built-in
   - Hot reload for fast development
   - Cross-platform (Windows/macOS/Linux)

2. **Next.js for Web Portal**
   - Modern React framework
   - Built-in API routes
   - Great for admin dashboards
   - Easy deployment

3. **Flutter for Mobile Companion**
   - Same codebase as desktop
   - 70%+ code sharing
   - Consistent UX across platforms

4. **Drift + PostgreSQL**
   - Drift: Type-safe SQLite for offline
   - PostgreSQL: Central cloud database
   - Best of both worlds

---

## ☁️ Cloud Infrastructure & Scaling Strategy

### Database Provider Decision

```
┌─────────────────────────────────────────────────────────────────┐
│              DATABASE PROVIDER COMPARISON                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  FACTOR          │ SUPABASE │ DO MANAGED │ AWS RDS │ NEON     │
│  ────────────────┼──────────┼────────────┼─────────┼──────────│
│  PostgreSQL      │ ✅ Native │ ✅ Managed │ ✅ Managed│ ✅ Serverless│
│  Laravel Support │ ✅        │ ✅         │ ✅       │ ✅        │
│  Saudi Region    │ ❌ (EU/Asia)│ ❌ (Singapore)│ ✅ Bahrain│ ❌      │
│  Starting Price  │ Free/$25  │ $15/mo     │ $15/mo   │ Free/$19 │
│  Real-time       │ ✅ Built-in│ ❌ DIY     │ ❌ DIY   │ ❌ DIY   │
│  Auto-scaling    │ ✅        │ ❌ Manual  │ ✅ Aurora │ ✅       │
│  Conn. Pooling   │ ✅ Built-in│ ❌ DIY     │ ❌ DIY   │ ✅       │
│  Daily Backups   │ ✅        │ ✅         │ ✅       │ ✅       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Recommended: Supabase (Start) → AWS RDS (Scale)

**Why Supabase for Years 1-2:**
- Team already familiar with it
- Built-in connection pooling (PgBouncer)
- Real-time subscriptions (useful for dashboard)
- PostgreSQL underneath (easy migration)
- Cost-effective: $25/mo Pro plan
- Row-level security for multi-tenant isolation

**Why Migration is Easy:**
- It's just PostgreSQL - standard `pg_dump`/`pg_restore`
- Logical replication for zero-downtime migration
- Same Eloquent queries work on any PostgreSQL

### Architecture Evolution

```
┌─────────────────────────────────────────────────────────────────┐
│         INFRASTRUCTURE EVOLUTION PATH                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  STAGE 1: STARTUP (0-500 stores)                               │
│  ───────────────────────────────                               │
│  ┌─────────────────┐     ┌─────────────────┐                   │
│  │  DigitalOcean   │     │    Supabase     │                   │
│  │  ┌───────────┐  │     │  ┌───────────┐  │                   │
│  │  │  Laravel  │──┼─────┼──│ PostgreSQL│  │                   │
│  │  │   API     │  │     │  │ + Pooler  │  │                   │
│  │  └───────────┘  │     │  └───────────┘  │                   │
│  │  ┌───────────┐  │     └─────────────────┘                   │
│  │  │   Redis   │  │     Cost: ~$50/month                      │
│  │  └───────────┘  │                                           │
│  └─────────────────┘                                           │
│                                                                 │
│  STAGE 2: GROWTH (500-2000 stores)                             │
│  ─────────────────────────────────                             │
│  ┌─────────────────┐     ┌─────────────────┐                   │
│  │   DO Kubernetes │     │  Supabase Team  │                   │
│  │  ┌───────────┐  │     │  ┌───────────┐  │                   │
│  │  │ Laravel x3│──┼─────┼──│ PostgreSQL│  │                   │
│  │  └───────────┘  │     │  │ (Larger)  │  │                   │
│  │  ┌───────────┐  │     │  └───────────┘  │                   │
│  │  │Redis Cluster│ │     └─────────────────┘                   │
│  │  └───────────┘  │     Cost: ~$800/month                     │
│  └─────────────────┘                                           │
│                                                                 │
│  STAGE 3: SCALE (2000-10000 stores)                            │
│  ──────────────────────────────────                            │
│  ┌─────────────────────────────────────────────────┐           │
│  │              AWS / GCP Infrastructure           │           │
│  │  ┌─────────────────────────────────────────┐   │           │
│  │  │           Load Balancer (ALB)           │   │           │
│  │  └──────────────────┬──────────────────────┘   │           │
│  │           ┌─────────┴─────────┐                │           │
│  │           ▼                   ▼                │           │
│  │  ┌─────────────────┐ ┌─────────────────┐      │           │
│  │  │  Laravel ECS x5 │ │  Laravel ECS x5 │      │           │
│  │  │  (Riyadh)       │ │  (Jeddah)       │      │           │
│  │  └────────┬────────┘ └────────┬────────┘      │           │
│  │           └─────────┬─────────┘                │           │
│  │                     ▼                          │           │
│  │           ┌─────────────────┐                  │           │
│  │           │   PgBouncer    │                  │           │
│  │           └────────┬────────┘                  │           │
│  │           ┌────────┴────────┐                  │           │
│  │           ▼                 ▼                  │           │
│  │  ┌─────────────────┐ ┌─────────────────┐      │           │
│  │  │  RDS Primary    │ │  RDS Replica    │      │           │
│  │  │  (Write)        │ │  (Read x2)      │      │           │
│  │  └─────────────────┘ └─────────────────┘      │           │
│  │  Cost: ~$5,000/month                          │           │
│  └───────────────────────────────────────────────┘           │
│                                                                 │
│  STAGE 4: ENTERPRISE (10000-25000+ stores)                     │
│  ─────────────────────────────────────────                     │
│  • Multi-region deployment (Riyadh, Jeddah, Dammam)            │
│  • Database sharding by region                                 │
│  • Global load balancing                                       │
│  • Dedicated DevOps team                                       │
│  • Cost: ~$50,000/month                                        │
│  • Revenue: 6,250,000 SAR/month (0.8% infra cost)             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Scale Projections (25,000 Stores)

```
┌─────────────────────────────────────────────────────────────────┐
│                 SCALE ANALYSIS (25K STORES)                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  CONCURRENT CONNECTIONS                                        │
│  ──────────────────────                                        │
│  • 25,000 stores × 3 terminals avg = 75,000 devices            │
│  • Peak online: ~60% = 45,000 simultaneous connections         │
│  • Solution: Regional API servers + Connection pooling         │
│                                                                 │
│  TRANSACTIONS PER DAY                                          │
│  ────────────────────                                          │
│  • 25,000 stores × 200 transactions/day = 5,000,000 tx/day     │
│  • Peak hour: ~500,000 transactions/hour = 139 tx/second       │
│  • With sync batching: ~50 API calls/second (manageable)       │
│                                                                 │
│  DATA VOLUME                                                   │
│  ───────────                                                   │
│  • 5M transactions/day × 365 = 1.8B transactions/year          │
│  • Products: 25K stores × 5K products = 125M product records   │
│  • Database size: 500GB - 2TB after 2 years                    │
│  • Solution: Table partitioning + Archiving                    │
│                                                                 │
│  REVENUE AT SCALE                                              │
│  ────────────────                                              │
│  • 25,000 stores × 250 SAR avg = 6,250,000 SAR/month          │
│  • ARR: 75,000,000 SAR (~$20M USD)                            │
│  • Infrastructure: ~55,000 SAR/mo (0.9% of revenue)           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Future-Proof Code Patterns (Implement from Day 1)

#### 1. Repository Pattern (Abstract Database Access)

```php
// app/Repositories/Contracts/TransactionRepositoryInterface.php
interface TransactionRepositoryInterface
{
    public function create(array $data): Transaction;
    public function findByStore(string $storeId, array $filters): Collection;
    public function getDailyReport(string $storeId, Carbon $date): DailyReport;
}

// app/Repositories/EloquentTransactionRepository.php
// Current implementation (works with Supabase or any PostgreSQL)
class EloquentTransactionRepository implements TransactionRepositoryInterface
{
    public function create(array $data): Transaction
    {
        return Transaction::create($data);
    }
    
    public function findByStore(string $storeId, array $filters): Collection
    {
        return Transaction::where('store_id', $storeId)
            ->when($filters['date'] ?? null, fn($q, $date) => 
                $q->whereDate('created_at', $date))
            ->get();
    }
    
    public function getDailyReport(string $storeId, Carbon $date): DailyReport
    {
        return Transaction::where('store_id', $storeId)
            ->whereDate('created_at', $date)
            ->selectRaw('COUNT(*) as count, SUM(total) as total')
            ->first();
    }
}

// FUTURE: Sharded implementation (no business logic changes!)
// app/Repositories/ShardedTransactionRepository.php
class ShardedTransactionRepository implements TransactionRepositoryInterface
{
    public function create(array $data): Transaction
    {
        $shard = $this->getShardForStore($data['store_id']);
        return $shard->table('transactions')->create($data);
    }
    
    private function getShardForStore(string $storeId): Connection
    {
        $region = Store::find($storeId)->region;
        return DB::connection("pgsql_{$region}"); // pgsql_riyadh, pgsql_jeddah
    }
}

// Service Provider binding (swap implementations easily)
// app/Providers/RepositoryServiceProvider.php
public function register()
{
    $this->app->bind(
        TransactionRepositoryInterface::class,
        EloquentTransactionRepository::class // Change to Sharded when ready
    );
}
```

#### 2. Shard-Ready Database Schema

```sql
-- ALL tables include these columns for future sharding/partitioning
CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL,           -- Shard key
    organization_id UUID NOT NULL,    -- Tenant isolation
    
    -- Business fields
    receipt_number VARCHAR(50) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    vat_amount DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    
    -- Timestamps (timezone-aware for multi-region)
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,           -- Soft delete
    
    -- Composite indexes for future partitioning
    INDEX idx_store_created (store_id, created_at),
    INDEX idx_org_created (organization_id, created_at)
);

-- When ready for partitioning (no code changes needed):
-- ALTER TABLE transactions RENAME TO transactions_old;
-- CREATE TABLE transactions (LIKE transactions_old INCLUDING ALL)
--     PARTITION BY RANGE (created_at);
-- CREATE TABLE transactions_2026_q1 PARTITION OF transactions
--     FOR VALUES FROM ('2026-01-01') TO ('2026-04-01');
```

#### 3. Config-Driven Database Connections

```php
// config/database.php - Ready for multi-database future
'connections' => [
    // Current: Single Supabase connection
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', 'db.xxxx.supabase.co'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'postgres'),
        'username' => env('DB_USERNAME', 'postgres'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
        'sslmode' => 'require',
    ],
    
    // FUTURE: Read/Write split (uncomment when needed)
    // 'pgsql' => [
    //     'driver' => 'pgsql',
    //     'read' => [
    //         'host' => [
    //             env('DB_HOST_REPLICA_1'),
    //             env('DB_HOST_REPLICA_2'),
    //         ],
    //     ],
    //     'write' => [
    //         'host' => env('DB_HOST_PRIMARY'),
    //     ],
    //     'sticky' => true, // Read from primary after write
    // ],
    
    // FUTURE: Regional shards (uncomment when needed)
    // 'pgsql_riyadh' => [
    //     'driver' => 'pgsql',
    //     'host' => env('DB_HOST_RIYADH'),
    //     'read' => ['host' => env('DB_HOST_RIYADH_REPLICA')],
    // ],
    // 'pgsql_jeddah' => [
    //     'driver' => 'pgsql',
    //     'host' => env('DB_HOST_JEDDAH'),
    // ],
],
```

#### 4. Cache Layer (Use from Day 1)

```php
// app/Services/ProductService.php
class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private CacheManager $cache
    ) {}
    
    public function getByBarcode(string $storeId, string $barcode): ?Product
    {
        $cacheKey = "product:{$storeId}:{$barcode}";
        
        // Cache-aside pattern (works with any cache backend)
        return $this->cache->tags(['products', "store:{$storeId}"])
            ->remember($cacheKey, 3600, function () use ($storeId, $barcode) {
                return $this->repository->findByBarcode($storeId, $barcode);
            });
    }
    
    public function update(string $productId, array $data): Product
    {
        $product = $this->repository->update($productId, $data);
        
        // Invalidate cache (tag-based, scales beautifully)
        $this->cache->tags(["store:{$product->store_id}", 'products'])->flush();
        
        return $product;
    }
}

// config/cache.php - Redis from day 1
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],
```

#### 5. Stateless API Design

```php
// All API servers must be stateless for horizontal scaling

// ❌ BAD: Local file storage
Storage::disk('local')->put('receipt.pdf', $content);

// ✅ GOOD: Cloud storage (S3/Spaces)
Storage::disk('s3')->put("receipts/{$storeId}/{$receiptId}.pdf", $content);

// ❌ BAD: Session stored in files
'driver' => 'file',

// ✅ GOOD: Session stored in Redis/Database
'driver' => env('SESSION_DRIVER', 'redis'),

// ❌ BAD: Local queue
'default' => 'sync',

// ✅ GOOD: Redis queue (distributable)
'default' => env('QUEUE_CONNECTION', 'redis'),
```

### Migration Playbook (Supabase → AWS RDS)

```
┌─────────────────────────────────────────────────────────────────┐
│            MIGRATION TIMELINE (6-8 WEEKS)                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  TRIGGER: When you reach ~2,000-5,000 stores                   │
│  TRIGGER: When Supabase costs exceed $1,000/month              │
│  TRIGGER: When you need Saudi-based data residency             │
│                                                                 │
│  ─────────────────────────────────────────────────────────────│
│                                                                 │
│  PHASE 1: PREPARATION (Week 1-2)                               │
│  ───────────────────────────────                               │
│  □ Audit current database size and query patterns              │
│  □ Set up AWS RDS in Bahrain region (me-south-1)               │
│  □ Configure read replicas (2-3 replicas)                      │
│  □ Set up PgBouncer connection pooling                         │
│  □ Create monitoring dashboards (CloudWatch)                   │
│  □ Test connection from staging environment                    │
│                                                                 │
│  PHASE 2: REPLICATION SETUP (Week 3-4)                         │
│  ────────────────────────────────────                          │
│  □ Enable logical replication on Supabase                      │
│  □ Set up continuous replication to AWS RDS                    │
│  □ Verify data consistency (row counts, checksums)             │
│  □ Run parallel read queries to compare results                │
│  □ Monitor replication lag                                     │
│                                                                 │
│  PHASE 3: APPLICATION PREPARATION (Week 5-6)                   │
│  ──────────────────────────────────────────                    │
│  □ Update staging .env to point to RDS                         │
│  □ Run full test suite against RDS                             │
│  □ Load test with production-like traffic                      │
│  □ Optimize slow queries identified in testing                 │
│  □ Document rollback procedure                                 │
│  □ Train ops team on new infrastructure                        │
│                                                                 │
│  PHASE 4: CUTOVER (Week 7 - Weekend Window)                    │
│  ─────────────────────────────────────────                     │
│  Friday 11 PM (low traffic):                                   │
│  □ Announce 4-hour maintenance window                          │
│  □ Stop all Laravel queue workers                              │
│  □ Wait for in-flight sync requests to complete                │
│  □ Verify final replication sync                               │
│                                                                 │
│  Saturday 1 AM:                                                │
│  □ Run data consistency verification                           │
│  □ Update production .env to RDS endpoint                      │
│  □ Deploy configuration change                                 │
│  □ Restart all Laravel workers                                 │
│  □ Resume queue processing                                     │
│                                                                 │
│  Saturday 3 AM:                                                │
│  □ Monitor error rates                                         │
│  □ Verify sync from 10 test stores                             │
│  □ Check transaction processing                                │
│                                                                 │
│  Saturday 8 AM:                                                │
│  □ Full system verification                                    │
│  □ Performance comparison vs baseline                          │
│  □ Announce maintenance complete                               │
│                                                                 │
│  PHASE 5: CLEANUP (Week 8)                                     │
│  ─────────────────────────                                     │
│  □ Keep Supabase running 2 weeks (rollback option)             │
│  □ Monitor AWS RDS performance and costs                       │
│  □ Optimize any slow queries                                   │
│  □ Update documentation                                        │
│  □ Terminate Supabase subscription                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Migration Verification Script

```php
// app/Console/Commands/VerifyMigration.php
class VerifyMigration extends Command
{
    protected $signature = 'db:verify-migration 
                            {--source=supabase : Source connection}
                            {--target=rds : Target connection}';
    
    public function handle()
    {
        $source = $this->option('source');
        $target = $this->option('target');
        
        $tables = [
            'organizations',
            'stores', 
            'users',
            'products',
            'transactions',
            'transaction_items',
            'inventory',
        ];
        
        $this->info("Comparing {$source} → {$target}");
        $this->newLine();
        
        $allMatch = true;
        
        foreach ($tables as $table) {
            $sourceCount = DB::connection($source)->table($table)->count();
            $targetCount = DB::connection($target)->table($table)->count();
            
            $status = $sourceCount === $targetCount ? '✅' : '❌';
            $this->line("{$status} {$table}: {$sourceCount} → {$targetCount}");
            
            if ($sourceCount !== $targetCount) {
                $allMatch = false;
            }
        }
        
        $this->newLine();
        
        if ($allMatch) {
            $this->info('✅ All tables match! Safe to proceed with cutover.');
            return 0;
        } else {
            $this->error('❌ Table counts do not match. Do not proceed!');
            return 1;
        }
    }
}
```

### Laravel Connection Configuration for Migration

```php
// .env.migration (temporary during migration)

# Source (Supabase - read only during migration)
DB_CONNECTION_SUPABASE=pgsql_supabase
DB_HOST_SUPABASE=db.xxxx.supabase.co
DB_PORT_SUPABASE=5432

# Target (AWS RDS - being populated)
DB_CONNECTION_RDS=pgsql_rds  
DB_HOST_RDS=pos-db.xxxx.me-south-1.rds.amazonaws.com
DB_HOST_RDS_REPLICA_1=pos-db-replica-1.xxxx.me-south-1.rds.amazonaws.com
DB_HOST_RDS_REPLICA_2=pos-db-replica-2.xxxx.me-south-1.rds.amazonaws.com
DB_PORT_RDS=5432

# Active connection (flip this for cutover)
DB_CONNECTION=pgsql_supabase  # Change to pgsql_rds for cutover
```

### Infrastructure Cost Comparison

| Stage | Stores | Supabase | AWS RDS | Winner |
|-------|--------|----------|---------|--------|
| Startup | 0-500 | $25/mo | $150/mo | **Supabase** |
| Growth | 500-2K | $599/mo | $400/mo | **AWS** |
| Scale | 2K-10K | N/A (limits) | $2,000/mo | **AWS** |
| Enterprise | 10K+ | N/A | $10,000/mo | **AWS** |

### Summary: What to Do Now vs Later

```
┌─────────────────────────────────────────────────────────────────┐
│         BUILD NOW (Zero Extra Cost)    │  BUILD LATER          │
├────────────────────────────────────────┼────────────────────────┤
│                                        │                        │
│  ✅ UUID primary keys                  │  ⏳ Read replicas      │
│  ✅ store_id on all tables             │  ⏳ Regional shards    │
│  ✅ Timestamps with timezone           │  ⏳ Table partitioning │
│  ✅ Soft deletes (deleted_at)          │  ⏳ PgBouncer setup    │
│  ✅ Repository pattern                 │  ⏳ Multi-region API   │
│  ✅ Config-driven DB connections       │  ⏳ CDN integration    │
│  ✅ Redis cache layer                  │  ⏳ Kubernetes         │
│  ✅ Queue for async operations         │                        │
│  ✅ Stateless API design               │                        │
│  ✅ S3/Spaces for file storage         │                        │
│                                        │                        │
│  Cost: $0 extra                        │  Cost: When needed     │
│  Effort: Best practices                │  Effort: DevOps team   │
│                                        │                        │
└────────────────────────────────────────┴────────────────────────┘
```

---

## 📅 Implementation Roadmap

### Phase 0: Foundation (Month 1-2)
```
Week 1-2: Project Setup
├── Set up Flutter monorepo structure
├── Initialize Flutter Desktop project
├── Initialize Next.js web portal
├── Set up PostgreSQL database
├── Design database schema
└── Create shared Dart models

Week 3-4: Core Data Models
├── Organizations & Stores
├── Users & Permissions
├── Products & Categories
├── Inventory
└── Basic CRUD APIs (Laravel)
```

### Phase 1: Desktop MVP (Month 3-5)
```
Week 5-8: Basic POS
├── Product lookup (barcode + search)
├── Cart management
├── Simple sale completion
├── Cash payment
├── Receipt printing (ESC/POS)
└── Local SQLite storage

Week 9-12: Offline + Sync
├── Offline transaction storage
├── Sync engine (bidirectional)
├── Conflict resolution
├── Background sync
└── Sync status UI
```

### Phase 2: ZATCA Integration (Month 6-7)
```
Week 13-16: ZATCA Phase 2
├── Device registration flow
├── Invoice signing (Rust)
├── QR code generation
├── Offline invoice queue
├── ZATCA reporting API
└── Error handling & retry
```

### Phase 3: Inventory & Management (Month 8-9)
```
Week 17-20: Inventory
├── Stock tracking
├── Stock adjustments
├── Low stock alerts
├── Receiving goods
├── Inventory counting
└── Stock reports

Week 21-24: Admin Panels
├── Super Admin Panel (Filament)
├── Store subscriptions & billing
├── Support ticket system
├── Store Owner Dashboard
├── Product management
├── User management
├── Reports
└── Multi-store view
```

### Phase 4: Thawani Integration (Month 10)
```
Week 25-28: Integration
├── Thawani API connector
├── Order lookup
├── Order fulfillment
├── Stock sync to Thawani
├── Price sync
└── Webhook handling
```

### Phase 5: Polish & Launch (Month 11-12)
```
Week 29-32: Testing
├── Beta testing with 3-5 stores
├── Bug fixes
├── Performance optimization
├── ZATCA certification
└── Security audit

Week 33-36: Launch
├── Marketing website
├── Documentation
├── Video tutorials
├── Support system setup
├── Official launch
└── First 10 paying customers
```

---

## 💵 Cost & Resource Planning

### Team Requirements

| Role | Type | Duration | Monthly Cost (SAR) |
|------|------|----------|-------------------|
| Tech Lead / Architect (Flutter) | Full-time | 12 months | 25,000 |
| Senior Flutter Developer | Full-time | 12 months | 20,000 |
| Flutter Developer | Full-time | 12 months | 15,000 |
| Laravel Backend Developer | Full-time | 10 months | 16,000 |
| Laravel/Filament Developer (Admin) | Part-time | 4 months | 14,000 |
| QA Engineer | Part-time | 8 months | 12,000 |
| UI/UX Designer | Contract | 3 months | 15,000 |
| DevOps | Part-time | 6 months | 12,000 |

### Development Budget

| Category | Cost (SAR) |
|----------|------------|
| **Team (12 months)** | |
| Tech Lead (Flutter) | 300,000 |
| Senior Flutter Dev | 240,000 |
| Flutter Dev | 180,000 |
| Laravel Backend Dev (10 mo) | 160,000 |
| Laravel/Filament Dev (4 mo, Admin Panel) | 56,000 |
| QA (8 mo, part-time) | 96,000 |
| Designer (3 mo) | 45,000 |
| DevOps (6 mo, part-time) | 72,000 |
| **Subtotal Team** | **1,149,000** |
| | |
| **Infrastructure** | |
| Cloud (DigitalOcean/AWS) | 24,000 |
| Development tools & licenses | 10,000 |
| ZATCA certification | 15,000 |
| Flutter packages/licenses | 5,000 |
| **Subtotal Infra** | **54,000** |
| | |
| **Other** | |
| Hardware for testing (printers, scanners, POS terminals) | 25,000 |
| Legal/Company setup | 20,000 |
| Marketing (pre-launch) | 40,000 |
| Contingency (15%) | 191,000 |
| **Subtotal Other** | **276,000** |
| | |
| **TOTAL** | **~1,479,000 SAR** |
| | |
| **💡 COST SAVINGS vs separate tech stacks:** | |
| No separate frontend framework (Next.js) | -100,000 |
| Team already knows Flutter & Laravel | -150,000 |
| Single codebase (desktop+tablet+mobile) | -150,000 |
| Filament speeds up admin panel dev | -50,000 |
| **Estimated savings** | **~450,000 SAR** |

### Breakeven Analysis

```
Monthly Fixed Costs (post-launch):
├── Team (reduced): 70,000 SAR
│   ├── 2 Flutter devs: 35,000
│   ├── 1 Backend dev: 16,000
│   ├── Support/QA: 12,000
│   └── DevOps (part-time): 7,000
├── Cloud: 4,000 SAR
├── Support tools: 3,000 SAR
├── Marketing: 8,000 SAR
└── Total: 85,000 SAR/month

Breakeven:
├── At 200 SAR ARPU: 425 stores
├── At 250 SAR ARPU: 340 stores
├── At 300 SAR ARPU: 284 stores

Timeline to breakeven: ~15-20 months post-launch
(Faster than Tauri approach due to lower dev costs)
```

---

## ✅ Next Steps

### Immediate Actions (This Week)

1. **Validate Market**
   - [ ] Talk to 10 supermarket owners
   - [ ] Understand their current POS pain points
   - [ ] Validate pricing sensitivity
   - [ ] Confirm ZATCA urgency

2. **Technical Proof of Concept**
   - [ ] Set up Flutter Desktop hello world (Windows)
   - [ ] Test Drift (SQLite) offline database
   - [ ] Test pointycastle for ZATCA ECDSA signing
   - [ ] Test esc_pos_printer with Bixolon
   - [ ] Test barcode scanner via keyboard emulation

3. **Business Planning**
   - [ ] Finalize feature prioritization
   - [ ] Create detailed project plan
   - [ ] Identify hiring needs (Flutter developers)
   - [ ] Set up company structure (if needed)

### Decision Points

1. **Build internally or outsource?**
   - Internal: More control, higher commitment
   - Outsource: Faster start, need good Flutter vendor

2. **Windows only or cross-platform?**
   - Windows first: Most Saudi supermarkets use Windows
   - Add macOS/Linux later if needed

3. **Thawani integration priority?**
   - Day 1: Competitive advantage
   - Post-launch: More market validation

---

## 📚 Resources

### Technical - Flutter Desktop
- [Flutter Desktop Documentation](https://docs.flutter.dev/desktop)
- [Flutter Windows Development](https://docs.flutter.dev/platform-integration/windows)
- [Drift (SQLite ORM) Documentation](https://drift.simonbinder.eu/)
- [esc_pos_printer Package](https://pub.dev/packages/esc_pos_printer)
- [pointycastle Package](https://pub.dev/packages/pointycastle)

### Technical - ZATCA
- [ZATCA Developer Portal](https://zatca.gov.sa/en/E-Invoicing/SystemsDevelopers/Pages/default.aspx)
- [ZATCA SDK GitHub](https://github.com/AhmedMohamedAdel/zatca-einvoicing-sdk)
- [ESC/POS Command Reference](https://reference.epson-biz.com/modules/ref_escpos/)

### Business
- [Saudi Retail Market Report](https://www.statista.com/outlook/cmo/food/saudi-arabia)
- [POS Software Market Analysis](https://www.grandviewresearch.com/industry-analysis/point-of-sale-software-market)

### Competitors to Study
- [Foodics](https://www.foodics.com/)
- [Marn](https://marn.com/)
- [Salla POS](https://salla.com/)
- [Qoyod](https://qoyod.com/)

---

## 💳 SoftPOS & NFC Payment Integration (NearPay)

### 🎯 Goal

Accept card payments **directly on the POS device's NFC chip** (Tap-to-Pay / SoftPOS) — eliminating the need for a separate physical payment terminal. This reduces hardware cost for merchants and lets Thawani earn a margin on every card transaction.

### What is SoftPOS?

```
┌─────────────────────────────────────────────────────────────────┐
│                  TRADITIONAL vs SOFTPOS                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  TRADITIONAL SETUP:                                            │
│  ─────────────────                                             │
│  [POS App] ──(amount)──► [Separate Terminal] ──► [Bank/Network]│
│                           Ingenico / Verifone                   │
│                           ~1,500-3,000 SAR per terminal        │
│                           Owned by acquirer bank                │
│                           Has its own TID/MID                   │
│                                                                 │
│  SOFTPOS SETUP (NearPay):                                      │
│  ────────────────────────                                      │
│  [POS App + NearPay SDK on same device]                        │
│       │                                                        │
│       ├── Customer taps card on device NFC                     │
│       ├── NearPay SDK processes payment                        │
│       ├── Talks to NearPay backend → Acquirer → Card Network   │
│       └── Returns result to POS app                            │
│                                                                 │
│       Cost: ~0 SAR hardware (device already has NFC)           │
│       Requirement: Android device with NFC                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### ⚠️ Critical Platform Constraint

```
┌─────────────────────────────────────────────────────────────────┐
│                  PLATFORM SUPPORT MATRIX                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ✅ ANDROID TABLETS/PHONES                                     │
│  ─────────────────────────                                     │
│  • NearPay SDK is Android-native (Kotlin/Java)                 │
│  • Device must have NFC hardware                               │
│  • Android 8.0+ required                                       │
│  • Works in Flutter via platform channel / plugin              │
│                                                                 │
│  ❌ WINDOWS DESKTOP                                             │
│  ──────────────────                                            │
│  • NO SoftPOS SDK available for Windows                        │
│  • Windows POS terminals still need external payment terminal  │
│  • OR: pair with Android device running NearPay as companion   │
│                                                                 │
│  ❌ iOS                                                         │
│  ──────                                                        │
│  • Apple restricts NFC payment to Apple Pay only               │
│  • Tap to Pay on iPhone (Apple) exists but:                    │
│    - Only available through Apple's own SDK                    │
│    - Requires Apple partnership (very selective)               │
│    - NearPay exploring this but not available yet              │
│  • Not viable for POS deployment in KSA currently              │
│                                                                 │
│  ✅ RECOMMENDED DEPLOYMENT:                                     │
│  ──────────────────────────                                    │
│  • Primary POS on Android tablet (10-14") with NFC             │
│  • Samsung Galaxy Tab A/S series or Lenovo Tab M series       │
│  • Android handles both POS app + SoftPOS payments             │
│  • Windows desktop becomes management/backoffice only          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 📊 Saudi Payment Providers Comparison

#### Payment Ecosystem Players in KSA (SAMA-Licensed)

> **Updated understanding:** NearPay is the SoftPOS **technology** provider. HALA and banks are **acquirers** that issue TIDs. These are different layers, not competitors.

| Provider | Role | Transaction Fee | SoftPOS Tech? | Issues TIDs? | Reseller Program? | Notes |
|----------|------|----------------|---------------|--------------|---------------------|-------|
| **NearPay** | SoftPOS technology | Tech fee ~0.05-0.15% | ✅ Core product (SDK) | ❌ No — needs acquirer | ✅ Technology partner | **SoftPOS SDK layer** — pair with HALA or bank |
| **HALA** | Payment facilitator / Acquirer | ~1.2-2.0% (partner negotiable) | ❌ (uses NearPay) | ✅ Yes — issues SoftPOS TIDs | ✅ Partner/reseller | **Acquirer layer** — issues TIDs for NearPay |
| **Direct Bank** (Rajhi/SNB) | Acquiring bank | ~0.8-1.2% mada | ❌ (uses NearPay) | ✅ Yes — issues SoftPOS TIDs | ⚠️ PayFac arrangement | **Cheapest rates** — longer onboarding |
| **Geidea** | Full PSP + terminals | ~1.5-2.2% | ✅ (own SDK, limited) | ✅ Yes (own ecosystem) | ⚠️ Not standard | Alternative stack — less mature SoftPOS |
| **NeoLeap** | Payment network | ~1.4-1.8% | ✅ (infrastructure) | ⚠️ Network level | ⚠️ Need PSP license | mada switch operator — future option |
| **Moyasar** | Online PSP | ~1.75-2.5% +0.50 SAR | ❌ (online only) | ❌ | ❌ | Online/e-commerce only |
| **HyperPay** | Online PSP | ~2.0-2.8% | ❌ (online only) | ❌ | ❌ | Online/e-commerce only |
| **Tap Payments** | Online PSP | ~2.0-2.75% +1 SAR | ❌ (online only) | ❌ | ❌ | Online/e-commerce only |
| **STC Pay** | Wallet + Merchant | ~1.0-1.5% | ❌ (QR) | N/A | ❌ | QR-based, not card tap |

#### Key Fee Breakdown

```
┌─────────────────────────────────────────────────────────────────┐
│              TRANSACTION FEE STRUCTURE                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  HOW CARD PAYMENT FEES WORK IN KSA:                            │
│  ───────────────────────────────────                           │
│                                                                 │
│  Customer taps card                                            │
│       │                                                        │
│       ▼                                                        │
│  ┌──────────┐    ┌──────────┐    ┌──────────┐                  │
│  │ SoftPOS  │───►│ Acquirer │───►│  Card    │                  │
│  │ Provider │    │  Bank    │    │ Network  │                  │
│  │(NearPay) │    │(Rajhi,   │    │(mada/    │                  │
│  │          │    │SNB,etc.) │    │Visa/MC)  │                  │
│  └──────────┘    └──────────┘    └──────────┘                  │
│                                                                 │
│  FEE COMPONENTS:                                               │
│  ───────────────                                               │
│  1. Interchange Fee   → Goes to card-issuing bank              │
│     • mada: ~0.45-0.75% (SAMA regulated, lowest in GCC)       │
│     • Visa/MC: ~1.0-1.8%                                      │
│                                                                 │
│  2. Network Fee       → Goes to card network (mada/Visa/MC)   │
│     • mada: ~0.10-0.15%                                       │
│     • Visa/MC: ~0.10-0.25%                                    │
│                                                                 │
│  3. Acquirer Markup   → Goes to acquirer (HALA or bank)        │
│     • HALA: ~0.3-0.8% on top (aggregator margin)              │
│     • Direct bank: ~0.1-0.4% (lower, but harder to get)       │
│                                                                 │
│  4. NearPay Tech Fee  → Goes to NearPay for SoftPOS tech      │
│     • Typically built into acquirer rate or separate small fee │
│     • ~0.1-0.3% or flat monthly per terminal                   │
│                                                                 │
│  TOTAL TO MERCHANT:                                            │
│  ─────────────────                                             │
│  • mada transactions: ~1.0-1.5%                                │
│  • Visa/MC: ~1.5-2.5%                                         │
│  • Blended rate (all cards): ~1.5-1.75%                        │
│                                                                 │
│  YOUR MARGIN (THAWANI):                                        │
│  ──────────────────────                                        │
│  If your acquiring cost is ~1.2-1.5% (via HALA or bank)        │
│  You charge merchants 1.8-2.0%                                 │
│  YOUR CUT = 0.3-0.8% per transaction                          │
│                                                                 │
│  Example: 50 SAR sale                                          │
│  • Merchant pays: 50 × 2.0% = 1.00 SAR                        │
│  • Acquirer + NearPay: 50 × 1.3% = 0.65 SAR                   │
│  • Thawani keeps: 0.35 SAR                                     │
│                                                                 │
│  At scale (1,000 stores × 200 txns/day × 80 SAR avg):         │
│  Monthly volume: ~480M SAR                                     │
│  Thawani revenue at 0.3%: ~1.44M SAR/month                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 🏆 Corrected Architecture: NearPay = Technology, Acquirer = Separate

> **UPDATE (from NearPay directly):** NearPay is the **SoftPOS technology provider** (SDK, EMV kernel, NFC handling, PCI compliance). The **acquiring relationship** (TID/MID issuance, settlement, merchant account) comes from a **separate entity** — either **HALA** (as payment facilitator/aggregator) or **directly from an acquiring bank** (Al Rajhi, SNB, etc.). NearPay told Thawani to "contact HALA to get TIDs or take TIDs from bank directly."

#### The Corrected Three-Layer Model

```
┌─────────────────────────────────────────────────────────────────┐
│              CORRECTED PAYMENT ARCHITECTURE                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  LAYER 1: YOUR APP (Thawani POS)                               │
│  ─────────────────────────────────                             │
│  • Flutter POS application on Android tablet                   │
│  • Initiates payment requests                                  │
│  • Handles UI, receipts, reconciliation                        │
│                                                                 │
│  LAYER 2: SOFTPOS TECHNOLOGY (NearPay)                         │
│  ──────────────────────────────────────                        │
│  • NearPay SDK embedded in your app                            │
│  • Handles NFC communication with card                         │
│  • EMV kernel (contactless payment processing)                 │
│  • PCI CPoC / SPoC certified environment                      │
│  • Secure cryptographic operations                             │
│  • Does NOT hold merchant funds                               │
│  • Does NOT issue TIDs — that comes from acquirer             │
│                                                                 │
│  LAYER 3: ACQUIRING (HALA or Bank)                             │
│  ──────────────────────────────────                            │
│  • Issues TID (Terminal ID) and MID (Merchant ID)             │
│  • Routes transactions to card networks (mada/Visa/MC)        │
│  • Handles settlement (pays merchant's bank account)           │
│  • Handles chargebacks and disputes                            │
│  • Collects and distributes fees                               │
│                                                                 │
│  FLOW:                                                         │
│                                                                 │
│  Thawani POS ──► NearPay SDK ──► Acquirer (HALA/Bank) ──►     │
│    Card Network (mada/Visa/MC) ──► Issuing Bank ──► Approved  │
│                                                                 │
│  ┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐   │
│  │ Thawani  │   │ NearPay  │   │  HALA    │   │  Card    │   │
│  │ POS App  │──►│ SDK      │──►│  or Bank │──►│ Network  │   │
│  │ (Flutter)│   │ (NFC +   │   │ (Acquirer│   │ (mada/   │   │
│  │          │   │  EMV)    │   │  + TID)  │   │ Visa/MC) │   │
│  └──────────┘   └──────────┘   └──────────┘   └──────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Two Acquiring Options: HALA vs Direct Bank

```
┌─────────────────────────────────────────────────────────────────┐
│          OPTION A: GET TIDs VIA HALA (Easier, Faster)          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  WHAT IS HALA IN THIS CONTEXT:                                 │
│  ──────────────────────────────                                │
│  • HALA acts as a Payment Facilitator (PayFac) / Aggregator   │
│  • HALA has master MID with acquiring banks                    │
│  • Merchants get sub-MID/TID under HALA's umbrella            │
│  • HALA handles KYC, onboarding, settlement to merchants      │
│  • NearPay is the SoftPOS tech, HALA is the acquiring layer   │
│                                                                 │
│  HOW IT WORKS:                                                 │
│  ─────────────                                                 │
│  1. Thawani signs agreement with HALA (as partner/reseller)   │
│  2. For each new merchant, Thawani requests TID from HALA     │
│  3. HALA issues sub-MID/TID under their master merchant       │
│  4. TID is configured in NearPay SDK on device                │
│  5. Transactions route: NearPay → HALA → Bank → Card Network  │
│  6. Settlement: Card Network → Bank → HALA → Merchant account │
│                                                                 │
│  PROS:                                                         │
│  ──────                                                        │
│  ✅ Fast onboarding (HALA handles bank relationship)           │
│  ✅ Simplified KYC (HALA's streamlined process)               │
│  ✅ Single integration partner for acquiring                   │
│  ✅ HALA already SAMA-licensed and mada-certified              │
│  ✅ Can onboard merchants in 24-48 hours                       │
│  ✅ HALA handles chargebacks, disputes, compliance             │
│  ✅ Thawani can negotiate volume-based partner rates           │
│                                                                 │
│  CONS:                                                         │
│  ──────                                                        │
│  ❌ Higher fees (HALA adds their margin: ~0.3-0.5%)            │
│  ❌ Less control over settlement timing                        │
│  ❌ Dependent on HALA's platform stability                     │
│  ❌ HALA's rate ~1.8-2.2% → less room for Thawani margin     │
│                                                                 │
│  TYPICAL FEE STRUCTURE (via HALA):                             │
│  ──────────────────────────────────                            │
│  • mada: ~1.2-1.5% (HALA's rate to you as partner)            │
│  • Visa/MC: ~1.8-2.2%                                         │
│  • You charge merchant: ~2.0-2.5%                              │
│  • Thawani margin: ~0.3-0.5%                                  │
│                                                                 │
│  BEST FOR:                                                     │
│  ─────────                                                     │
│  • Getting started quickly (Phase 1)                           │
│  • Small-medium merchants who can't get bank TIDs directly    │
│  • When speed of onboarding matters more than fee optimization │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│       OPTION B: GET TIDs DIRECTLY FROM BANK (Cheaper)          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  WHAT THIS MEANS:                                              │
│  ────────────────                                              │
│  • Go directly to an acquiring bank (Al Rajhi, SNB, etc.)     │
│  • Each merchant gets their own MID/TID from the bank         │
│  • NearPay SDK uses that bank-issued TID                       │
│  • No HALA in the middle                                       │
│                                                                 │
│  HOW IT WORKS:                                                 │
│  ─────────────                                                 │
│  1. Thawani signs acquiring agreement with bank (as referrer/ │
│     payment facilitator / or merchant applies directly)       │
│  2. Bank does full KYC on each merchant                        │
│  3. Bank issues MID + TID for SoftPOS use                      │
│  4. TID configured in NearPay SDK on device                    │
│  5. Transactions route: NearPay → Bank → Card Network          │
│  6. Settlement: Card Network → Bank → Merchant account         │
│                                                                 │
│  TWO SUB-OPTIONS:                                              │
│  ─────────────────                                             │
│                                                                 │
│  B1: Each merchant applies to bank individually                │
│  • Merchant has direct relationship with bank                  │
│  • Thawani is just the POS software provider                  │
│  • Lowest fees for merchant, but Thawani gets NO fee margin   │
│  • Thawani only earns from POS subscription                   │
│  • ⚠️ Not ideal for your revenue model                       │
│                                                                 │
│  B2: Thawani becomes a Payment Facilitator (PayFac) with bank │
│  • Thawani gets master MID from bank                           │
│  • Sub-merchants get TIDs under Thawani's master              │
│  • Thawani controls fees and settlement                        │
│  • ✅ Best for revenue: you set the merchant rate              │
│  • ⚠️ Requires SAMA approval / bank partnership agreement    │
│  • ⚠️ Takes 2-6 months to set up                             │
│  • ⚠️ Need compliance team for merchant KYC                  │
│                                                                 │
│  PROS:                                                         │
│  ──────                                                        │
│  ✅ Lowest acquiring rates (~0.8-1.2% for mada)               │
│  ✅ Maximum margin for Thawani (if PayFac model)              │
│  ✅ Direct relationship, more control                          │
│  ✅ Better settlement terms (T+1 possible)                     │
│  ✅ No intermediary dependency                                 │
│                                                                 │
│  CONS:                                                         │
│  ──────                                                        │
│  ❌ Longer onboarding per merchant (bank KYC: 1-4 weeks)      │
│  ❌ More paperwork (CR, VAT cert, bank statements, etc.)      │
│  ❌ Need dedicated onboarding operations team                  │
│  ❌ PayFac model requires SAMA regulatory compliance          │
│  ❌ Not all banks support SoftPOS TIDs yet                    │
│                                                                 │
│  TYPICAL FEE STRUCTURE (direct bank):                          │
│  ──────────────────────────────────────                        │
│  • mada: ~0.8-1.2% (direct acquiring rate)                    │
│  • Visa/MC: ~1.3-1.8%                                         │
│  • You charge merchant: ~1.8-2.0%                              │
│  • Thawani margin: ~0.6-1.0% (MUCH higher than HALA route)   │
│                                                                 │
│  BEST FOR:                                                     │
│  ─────────                                                     │
│  • Phase 2 / long-term strategy                                │
│  • Larger merchants who already bank with the acquirer         │
│  • When maximizing Thawani's margin is the priority           │
│  • Once you have enough volume to justify PayFac setup         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### 🎯 Recommended Strategy: HALA First, Bank Later

```
┌─────────────────────────────────────────────────────────────────┐
│          RECOMMENDED TWO-PHASE ACQUIRING STRATEGY              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PHASE 1: START WITH HALA (Months 1-6)                         │
│  ──────────────────────────────────────                        │
│  • Partner with HALA as your acquirer/PayFac                   │
│  • Fast merchant onboarding (days not weeks)                   │
│  • Accept slightly lower margins (~0.3-0.5%)                   │
│  • Focus on getting merchants live and volume growing          │
│  • Use this time to prove the model and gather data            │
│                                                                 │
│  Thawani POS ──► NearPay SDK ──► HALA (acquirer) ──► mada     │
│                                                                 │
│  PHASE 2: ADD DIRECT BANK (Month 6+)                           │
│  ────────────────────────────────────                          │
│  • With transaction volume data, negotiate with banks          │
│  • Apply for PayFac arrangement with Al Rajhi / SNB / etc.    │
│  • Migrate high-volume merchants to direct bank acquiring     │
│  • Keep HALA for small merchants / quick onboarding            │
│  • Your margin jumps to ~0.6-1.0%                              │
│                                                                 │
│  Thawani POS ──► NearPay SDK ──► Bank (acquirer) ──► mada     │
│                                                                 │
│  PHASE 3: DUAL ACQUIRING (Month 12+)                           │
│  ────────────────────────────────────                          │
│  • Run both HALA and direct bank in parallel                   │
│  • Route based on merchant tier:                               │
│    - Small merchants → HALA (easy onboarding)                  │
│    - Large/chain merchants → Direct bank (better rates)       │
│  • NearPay SDK supports multiple TID sources                   │
│  • Maximize revenue across all merchant segments               │
│                                                                 │
│  REVENUE COMPARISON:                                           │
│  ────────────────────                                          │
│  Monthly volume: 100M SAR                                      │
│                                                                 │
│  HALA only:       100M × 0.4% = 400K SAR/month                │
│  Bank only:       100M × 0.8% = 800K SAR/month                │
│  Hybrid (60/40):  (60M × 0.4%) + (40M × 0.8%) = 560K SAR     │
│                                                                 │
│  The bank route literally DOUBLES your payment revenue         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### NearPay's Role Clarified

```
┌─────────────────────────────────────────────────────────────────┐
│              NEARPAY = TECHNOLOGY, NOT ACQUIRER                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  WHAT NEARPAY DOES:                                            │
│  ───────────────────                                           │
│  ✅ Provides SoftPOS SDK (Android/Flutter)                     │
│  ✅ EMV contactless kernel (certified)                         │
│  ✅ NFC card reading and communication                         │
│  ✅ PCI CPoC / SPoC security compliance                       │
│  ✅ Card data encryption (P2PE)                                │
│  ✅ Transaction authorization relay to acquirer                │
│  ✅ Certification with card schemes (mada, Visa, MC)           │
│  ✅ Device attestation (ensures unrooted, secure device)       │
│                                                                 │
│  WHAT NEARPAY DOES NOT DO:                                     │
│  ──────────────────────────                                    │
│  ❌ Does NOT issue TIDs/MIDs (that's the acquirer's job)      │
│  ❌ Does NOT hold or settle merchant funds                     │
│  ❌ Does NOT set transaction fees (the acquirer does)          │
│  ❌ Does NOT handle chargebacks (acquirer + card network)      │
│  ❌ Does NOT do merchant KYC (acquirer/HALA does)             │
│                                                                 │
│  ANALOGY:                                                      │
│  ─────────                                                     │
│  Think of it like a phone:                                     │
│  • NearPay = the phone hardware (enables calls)               │
│  • HALA/Bank = the carrier (STC/Mobily — provides the number, │
│    routes calls, bills you)                                    │
│  • You can't make calls without both                           │
│                                                                 │
│  NearPay charges for their technology:                         │
│  • Per-transaction tech fee (~0.05-0.15%) OR                   │
│  • Monthly per-terminal fee (~50-200 SAR/terminal) OR          │
│  • Bundled into acquirer's rate (HALA includes it)             │
│  • Exact terms depend on your partnership negotiation          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Why HALA Works Here (Corrected Understanding)

```
┌─────────────────────────────────────────────────────────────────┐
│              HALA — CORRECTED ROLE                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PREVIOUS (WRONG) UNDERSTANDING:                               │
│  ────────────────────────────────                              │
│  "HALA only does hardware terminals, no SoftPOS"               │
│                                                                 │
│  CORRECTED UNDERSTANDING:                                      │
│  ─────────────────────────                                     │
│  HALA is a PAYMENT FACILITATOR / AGGREGATOR that:              │
│  • Has acquiring relationships with Saudi banks                │
│  • Can issue TIDs for various terminal types including SoftPOS │
│  • Partners with NearPay to provision SoftPOS TIDs            │
│  • Handles merchant onboarding, KYC, settlement               │
│  • Yes, they ALSO sell physical terminals (that's separate)    │
│                                                                 │
│  So the stack is:                                              │
│  ────────────────                                              │
│  Your App (Thawani POS)                                        │
│       ↓                                                        │
│  NearPay SDK (SoftPOS technology — handles NFC/EMV)            │
│       ↓                                                        │
│  HALA (acquirer/PayFac — issues TID, routes to bank)          │
│       ↓                                                        │
│  Bank + Card Network (settlement)                              │
│                                                                 │
│  HALA IN THIS MODEL:                                           │
│  ────────────────────                                          │
│  • Provides TID/MID for each merchant                          │
│  • Routes NearPay transactions to card networks                │
│  • Settles funds to merchant bank accounts                     │
│  • Bills Thawani for transaction fees                          │
│  • Thawani adds margin and bills merchants                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 🔧 NearPay Integration Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│            NEARPAY INTEGRATION IN THAWANI POS                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ANDROID TABLET (Samsung Tab A8 / S6 Lite / etc.)              │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                                                          │  │
│  │  ┌──────────────────────────────────────────────────┐   │  │
│  │  │           THAWANI POS (Flutter App)               │   │  │
│  │  │                                                    │   │  │
│  │  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  │   │  │
│  │  │  │  Sales UI  │  │  Payment   │  │  Receipt   │  │   │  │
│  │  │  │  (Cart,    │  │  Screen    │  │  Printer   │  │   │  │
│  │  │  │  Products) │  │            │  │  Service   │  │   │  │
│  │  │  └─────┬──────┘  └─────┬──────┘  └────────────┘  │   │  │
│  │  │        │               │                           │   │  │
│  │  │        │    ┌──────────▼──────────┐                │   │  │
│  │  │        │    │  NearPay Service    │                │   │  │
│  │  │        │    │  (Payment Handler)  │                │   │  │
│  │  │        │    └──────────┬──────────┘                │   │  │
│  │  │        │               │                           │   │  │
│  │  └────────┼───────────────┼───────────────────────────┘   │  │
│  │           │               │                               │  │
│  │           │    ┌──────────▼──────────┐                    │  │
│  │           │    │  NearPay SDK        │                    │  │
│  │           │    │  (Android Native)   │                    │  │
│  │           │    │                     │                    │  │
│  │           │    │  • EMV Kernel       │                    │  │
│  │           │    │  • NFC Controller   │                    │  │
│  │           │    │  • PCI Secure Env   │                    │  │
│  │           │    │  • Tokenization     │                    │  │
│  │           │    └──────────┬──────────┘                    │  │
│  │           │               │                               │  │
│  └───────────┼───────────────┼───────────────────────────────┘  │
│              │               │                                   │
│              │    ┌──────────▼──────────┐                        │
│              │    │  DEVICE NFC CHIP    │  ← Customer taps card  │
│              │    └─────────────────────┘                        │
│              │                                                   │
└──────────────┼───────────────────────────────────────────────────┘
               │
        ┌──────▼──────────────────────────────────────────────┐
        │                 CLOUD                                │
        │  ┌────────────┐  ┌────────────┐  ┌──────────────┐  │
        │  │  NearPay   │  │  HALA or   │  │ Card Network │  │
        │  │  Backend   │──│  Acquiring │──│  (mada/Visa/ │  │
        │  │  (tech     │  │  Bank      │  │  Mastercard) │  │
        │  │  routing)  │  │  (TID/MID) │  │              │  │
        │  └─────┬──────┘  └────────────┘  └──────────────┘  │
        │        │                                            │
        │  ┌─────▼──────┐                                     │
        │  │  NearPay   │  ← Tech monitoring + transaction    │
        │  │  Dashboard │    logs (not settlement)             │
        │  └────────────┘                                     │
        │                                                     │
        │  ┌────────────┐                                     │
        │  │  HALA /    │  ← Settlement & reconciliation      │
        │  │  Bank      │    reports + merchant payouts        │
        │  │  Dashboard │                                     │
        │  └────────────┘                                     │
        └─────────────────────────────────────────────────────┘
```

### Flutter Integration Code

```dart
// lib/services/payment/nearpay_payment_service.dart

import 'package:nearpay_flutter_sdk/nearpay_flutter_sdk.dart';

/// NearPay SoftPOS payment service
/// Handles tap-to-pay card payments via device NFC
class NearPayPaymentService {
  NearPayPaymentService._();
  static final instance = NearPayPaymentService._();

  Nearpay? _nearpay;
  bool _isInitialized = false;

  /// Initialize NearPay SDK
  /// Called once at app startup
  Future<void> initialize({
    required String authKey, // From NearPay partner dashboard
    required String environment, // 'sandbox' or 'production'
    required String locale, // 'ar' or 'en'
  }) async {
    _nearpay = Nearpay(
      authtype: AuthenticationType.email, // or .jwt for partner auth
      authvalue: authKey,
      environment: environment == 'production' 
          ? Environments.production 
          : Environments.sandbox,
      locale: locale == 'ar' ? Locale.localeAr : Locale.localeDefault,
    );
    _isInitialized = true;
  }

  /// Process a card payment (tap-to-pay)
  ///
  /// [amountInHalalas] - amount in halalas (1 SAR = 100 halalas)
  /// [transactionRef] - your POS transaction ID for reconciliation
  /// Returns [NearPayResult] with approval code, card info, receipt
  Future<NearPayResult> purchase({
    required int amountInHalalas,
    required String transactionRef,
    String? customerRef,
  }) async {
    _ensureInitialized();

    try {
      final response = await _nearpay!.purchase(
        amount: amountInHalalas,
        transactionUUID: transactionRef,
        customerReferenceNumber: customerRef ?? '',
        enableReceiptUi: false, // We print our own receipt
        enableReversalUi: true,  // Allow auto-reversal on failure
        finishTimeout: 60,       // Seconds to wait for tap
      );

      if (response.status == 200) {
        final receipt = response.receipts?.first;
        return NearPayResult(
          success: true,
          approvalCode: receipt?.approvalCode ?? '',
          transactionId: receipt?.transactionUuid ?? '',
          cardScheme: receipt?.cardSchemeName ?? '', // mada, Visa, MC
          maskedCard: receipt?.pan ?? '',             // **** **** **** 1234
          amount: amountInHalalas,
          receiptData: receipt,
        );
      } else {
        return NearPayResult(
          success: false,
          errorCode: response.status.toString(),
          errorMessage: _mapErrorMessage(response.status ?? 0),
        );
      }
    } catch (e) {
      return NearPayResult(
        success: false,
        errorCode: 'SDK_ERROR',
        errorMessage: e.toString(),
      );
    }
  }

  /// Process a refund back to the original card
  Future<NearPayResult> refund({
    required String originalTransactionId,
    required int amountInHalalas,
    required String refundRef,
  }) async {
    _ensureInitialized();

    try {
      final response = await _nearpay!.refund(
        amount: amountInHalalas,
        originalTransactionUUID: originalTransactionId,
        transactionUUID: refundRef,
        customerReferenceNumber: '',
        enableReceiptUi: false,
        enableReversalUi: true,
        editableRefundAmountUI: false, // Fixed refund amount
        finishTimeout: 60,
      );

      if (response.status == 200) {
        return NearPayResult(
          success: true,
          transactionId: response.receipts?.first?.transactionUuid ?? '',
          amount: amountInHalalas,
        );
      } else {
        return NearPayResult(
          success: false,
          errorCode: response.status.toString(),
          errorMessage: _mapErrorMessage(response.status ?? 0),
        );
      }
    } catch (e) {
      return NearPayResult(
        success: false,
        errorCode: 'SDK_ERROR',
        errorMessage: e.toString(),
      );
    }
  }

  /// Reconcile (settlement) — end of day
  Future<NearPayResult> reconcile() async {
    _ensureInitialized();

    try {
      final response = await _nearpay!.reconcile(
        enableReceiptUi: false,
        finishTimeout: 60,
      );

      return NearPayResult(
        success: response.status == 200,
        receiptData: response.receipts?.first,
      );
    } catch (e) {
      return NearPayResult(
        success: false,
        errorCode: 'RECONCILE_ERROR',
        errorMessage: e.toString(),
      );
    }
  }

  /// Get a session (on startup or login)
  Future<void> setupSession() async {
    _ensureInitialized();
    await _nearpay!.session();
  }

  /// Close session (logout)
  Future<void> logout() async {
    _ensureInitialized();
    await _nearpay!.logout();
  }

  void _ensureInitialized() {
    if (!_isInitialized || _nearpay == null) {
      throw Exception('NearPay SDK not initialized. Call initialize() first.');
    }
  }

  String _mapErrorMessage(int statusCode) {
    switch (statusCode) {
      case 401: return 'Authentication failed';
      case 402: return 'Payment declined';
      case 403: return 'Terminal not activated';
      case 404: return 'Transaction not found';
      case 408: return 'Transaction timeout — customer did not tap';
      case 409: return 'Duplicate transaction';
      case 500: return 'NearPay server error';
      default: return 'Payment failed (code: $statusCode)';
    }
  }
}

/// Result of a NearPay transaction
class NearPayResult {
  final bool success;
  final String? approvalCode;
  final String? transactionId;
  final String? cardScheme;  // mada, Visa, Mastercard
  final String? maskedCard;  // **** 1234
  final int? amount;
  final String? errorCode;
  final String? errorMessage;
  final dynamic receiptData;

  const NearPayResult({
    required this.success,
    this.approvalCode,
    this.transactionId,
    this.cardScheme,
    this.maskedCard,
    this.amount,
    this.errorCode,
    this.errorMessage,
    this.receiptData,
  });
}
```

### Payment Flow in POS UI

```dart
// lib/screens/pos/payment_screen.dart (simplified)

/// Payment screen shown after cart is finalized
class PaymentScreen extends ConsumerStatefulWidget {
  final double totalAmount;
  final String transactionId;

  const PaymentScreen({
    required this.totalAmount,
    required this.transactionId,
  });

  @override
  ConsumerState<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends ConsumerState<PaymentScreen> {
  PaymentMethod _selectedMethod = PaymentMethod.card;
  bool _isProcessing = false;
  NearPayResult? _cardResult;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Row(
        children: [
          // LEFT: Payment method selection
          Expanded(
            flex: 2,
            child: Column(
              children: [
                _buildTotal(),
                const SizedBox(height: 24),
                _buildPaymentMethods(),
                const SizedBox(height: 24),
                if (_selectedMethod == PaymentMethod.cash)
                  _buildCashInput(),
                if (_selectedMethod == PaymentMethod.card)
                  _buildCardPaymentArea(),
                if (_selectedMethod == PaymentMethod.split)
                  _buildSplitPaymentArea(),
              ],
            ),
          ),
          // RIGHT: Order summary
          Expanded(
            flex: 1,
            child: _buildOrderSummary(),
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentMethods() {
    return Row(
      children: [
        _methodButton(
          icon: Icons.payments_outlined,
          label: 'نقدي\nCash',
          method: PaymentMethod.cash,
        ),
        const SizedBox(width: 16),
        _methodButton(
          icon: Icons.contactless,
          label: 'بطاقة\nCard (Tap)',
          method: PaymentMethod.card,
        ),
        const SizedBox(width: 16),
        _methodButton(
          icon: Icons.call_split,
          label: 'تقسيم\nSplit',
          method: PaymentMethod.split,
        ),
      ],
    );
  }

  Widget _buildCardPaymentArea() {
    if (_isProcessing) {
      return Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.contactless, size: 120, color: Colors.blue),
          const SizedBox(height: 24),
          const CircularProgressIndicator(),
          const SizedBox(height: 16),
          Text(
            'يرجى تقريب البطاقة\nPlease tap your card',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
        ],
      );
    }

    if (_cardResult?.success == true) {
      return Column(
        children: [
          const Icon(Icons.check_circle, size: 80, color: Colors.green),
          const SizedBox(height: 16),
          Text('Payment Approved ✓'),
          Text('${_cardResult!.cardScheme} ${_cardResult!.maskedCard}'),
          Text('Approval: ${_cardResult!.approvalCode}'),
        ],
      );
    }

    return ElevatedButton.icon(
      onPressed: _processCardPayment,
      icon: const Icon(Icons.contactless, size: 32),
      label: Text(
        'ادفع ${widget.totalAmount.toStringAsFixed(2)} ر.س\n'
        'Pay SAR ${widget.totalAmount.toStringAsFixed(2)}',
        textAlign: TextAlign.center,
      ),
      style: ElevatedButton.styleFrom(
        minimumSize: const Size(300, 80),
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
      ),
    );
  }

  Future<void> _processCardPayment() async {
    setState(() => _isProcessing = true);

    final amountInHalalas = (widget.totalAmount * 100).round();

    final result = await NearPayPaymentService.instance.purchase(
      amountInHalalas: amountInHalalas,
      transactionRef: widget.transactionId,
    );

    setState(() {
      _isProcessing = false;
      _cardResult = result;
    });

    if (result.success) {
      // Save payment record to local DB
      await _savePaymentRecord(result);
      // Print receipt
      await _printReceipt(result);
      // Open cash drawer (optional for card)
      // Complete the transaction
      _completeTransaction();
    } else {
      // Show error
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('فشل الدفع: ${result.errorMessage}'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }
}
```

### Partner Onboarding Model (Corrected)

```
┌─────────────────────────────────────────────────────────────────┐
│          THAWANI ↔ NEARPAY + HALA/BANK MODEL                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  STEP 1: THAWANI SIGNS TWO AGREEMENTS                          │
│  ─────────────────────────────────────                         │
│  A) NearPay: SoftPOS technology partnership                    │
│     • Get SDK access, sandbox, documentation                   │
│     • Agree on per-terminal or per-transaction tech fee        │
│     • NearPay provides Flutter plugin + support                │
│                                                                 │
│  B) HALA (and/or Bank): Acquiring partnership                  │
│     • Get ability to request TIDs for merchants                │
│     • Negotiate wholesale acquiring rates                      │
│     • Agree on settlement terms (T+1, T+2)                     │
│     • HALA handles merchant KYC under your umbrella            │
│                                                                 │
│  STEP 2: MERCHANT ONBOARDING                                   │
│  ──────────────────────────────                                │
│  • Merchant signs up for Thawani POS                           │
│  • Thawani requests TID from HALA (or bank) for merchant      │
│  • HALA/Bank runs KYC on merchant                              │
│  • TID + MID issued                                            │
│  • TID configured in NearPay SDK on merchant's device          │
│  • Device activated → ready for tap-to-pay                     │
│                                                                 │
│  STEP 3: DAILY OPERATIONS                                      │
│  ────────────────────────                                      │
│  • Customer taps card on device NFC                            │
│  • NearPay SDK reads card, sends to HALA/Bank for auth        │
│  • Acquirer routes to card network → approved/declined         │
│  • HALA/Bank settles to merchant account (T+1/T+2)            │
│  • Fees deducted: interchange + network + acquirer + NearPay   │
│  • Thawani's markup collected from merchant's fee differential │
│                                                                 │
│  REVENUE FLOW (100 SAR sale, via HALA):                        │
│  ──────────────────────────────────────                        │
│  Customer pays 100 SAR                                         │
│       │                                                        │
│       ├── Interchange fee:    ~0.60 SAR (to issuing bank)     │
│       ├── Network fee:        ~0.12 SAR (to mada/Visa/MC)    │
│       ├── HALA acquirer fee:  ~0.48 SAR (HALA's cut)          │
│       ├── NearPay tech fee:   ~0.10 SAR (SoftPOS technology)  │
│       ├── Thawani margin:     ~0.40 SAR (your markup)         │
│       └── Merchant receives:  ~98.30 SAR                      │
│                                                                 │
│  REVENUE FLOW (100 SAR sale, via direct bank):                 │
│  ─────────────────────────────────────────────                 │
│  Customer pays 100 SAR                                         │
│       │                                                        │
│       ├── Interchange fee:    ~0.60 SAR (to issuing bank)     │
│       ├── Network fee:        ~0.12 SAR (to mada/Visa/MC)    │
│       ├── Bank acquirer fee:  ~0.18 SAR (bank's cut — lower!) │
│       ├── NearPay tech fee:   ~0.10 SAR (SoftPOS technology)  │
│       ├── Thawani margin:     ~0.80 SAR (your markup — more!) │
│       └── Merchant receives:  ~98.20 SAR                      │
│                                                                 │
│  MERCHANT ONBOARDING FLOW:                                     │
│  ──────────────────────────                                    │
│                                                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │ Merchant │  │ Thawani  │  │ HALA /   │  │ NearPay  │      │
│  │ Signs Up │─►│ Platform │─►│ Bank     │─►│ SDK      │      │
│  │ for POS  │  │ Requests │  │ Issues   │  │ Activated│      │
│  │          │  │ TID      │  │ TID/MID  │  │ on Device│      │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘      │
│                                                                 │
│  TIME:                                                         │
│  • Via HALA: ~24-72 hours                                      │
│  • Via Bank: ~1-4 weeks (full bank KYC)                        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### About Existing Supermarket Terminals

```
┌─────────────────────────────────────────────────────────────────┐
│     CAN YOU USE EXISTING TERMINAL IDs? — UPDATED ANSWER        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  SHORT ANSWER: ❌ NO — existing TIDs can't be transferred      │
│  BUT you CAN get NEW TIDs for SoftPOS from the SAME bank      │
│                                                                 │
│  DETAILED EXPLANATION:                                         │
│  ─────────────────────                                         │
│                                                                 │
│  ❌ WHAT YOU CAN'T DO:                                         │
│  ─────────────────────                                         │
│  • Take the TID from an existing Ingenico/Verifone terminal   │
│  • Register that same TID in NearPay for SoftPOS              │
│  • Reason: TIDs are bound to specific hardware + encryption   │
│    keys injected during terminal manufacturing/provisioning    │
│                                                                 │
│  ✅ WHAT YOU CAN DO:                                           │
│  ────────────────────                                          │
│  • Ask the merchant's EXISTING acquiring bank for a NEW TID   │
│    specifically for SoftPOS use                                │
│  • OR request a new TID from HALA as payment facilitator      │
│  • The new TID is provisioned for NearPay SoftPOS             │
│  • The merchant can keep their old terminal running too        │
│                                                                 │
│  EXAMPLE:                                                      │
│  ─────────                                                     │
│  Store currently has:                                          │
│  • Al Rajhi terminal: TID-AAA (for Ingenico hardware)          │
│  • Al Rajhi can ALSO issue: TID-BBB (for NearPay SoftPOS)     │
│  • Both TIDs settle to same merchant bank account              │
│  • OR: HALA can issue: TID-CCC (under HALA's master MID)      │
│                                                                 │
│  THE ADVANTAGE OF DIRECT BANK:                                 │
│  ──────────────────────────────                                │
│  If the merchant already has a relationship with Al Rajhi:     │
│  • Bank already did KYC ✅                                     │
│  • Bank already knows the merchant ✅                          │
│  • Getting a new SoftPOS TID is faster (~days)                 │
│  • Fees may be lower (existing volume relationship)            │
│  • Settlement goes to same bank account                        │
│                                                                 │
│  MIGRATION OPTIONS:                                            │
│  ──────────────────                                            │
│                                                                 │
│  A) KEEP BOTH (Easiest start)                                  │
│  • Old terminals: continue normal card payments                │
│  • New SoftPOS: used via Thawani POS app                      │
│  • Merchant chooses which to use                               │
│                                                                 │
│  B) REPLACE (Cost savings)                                     │
│  • Cancel old terminal rental                                  │
│  • All payments via SoftPOS on tablet                          │
│  • Save ~150-300 SAR/month terminal rental                     │
│                                                                 │
│  C) GRADUAL MIGRATE (Recommended)                              │
│  • Start with both, build confidence                           │
│  • Migrate to SoftPOS-only after 1-3 months                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Database Schema for Payment Integration

```sql
-- Payment transactions (enhanced for NearPay)
CREATE TABLE payment_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID REFERENCES transactions(id),
    store_id UUID REFERENCES stores(id),
    register_id UUID REFERENCES registers(id),
    
    -- Payment method
    payment_method VARCHAR(20) NOT NULL, -- 'cash', 'card_nearpay', 'card_external', 'split'
    
    -- Amount
    amount DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'SAR',
    
    -- NearPay specific fields (null for cash)
    nearpay_transaction_id VARCHAR(100),
    nearpay_approval_code VARCHAR(20),
    card_scheme VARCHAR(20),          -- 'mada', 'visa', 'mastercard'
    masked_card VARCHAR(20),          -- '**** **** **** 1234'
    card_holder_name VARCHAR(100),
    
    -- External terminal (for parallel operation with old terminal)
    external_terminal_ref VARCHAR(100),
    
    -- Reconciliation
    settlement_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'settled', 'failed'
    settlement_date TIMESTAMPTZ,
    
    -- Fees tracking
    transaction_fee_percentage DECIMAL(5, 4), -- e.g., 0.0180 = 1.80%
    transaction_fee_amount DECIMAL(10, 2),     -- actual fee deducted
    thawani_margin_amount DECIMAL(10, 2),      -- Thawani's cut
    
    -- Status
    status VARCHAR(20) DEFAULT 'completed', -- 'completed', 'refunded', 'reversed', 'failed'
    refund_of UUID REFERENCES payment_transactions(id),
    
    -- Metadata
    created_at TIMESTAMPTZ DEFAULT NOW(),
    created_by UUID REFERENCES users(id),
    synced_at TIMESTAMPTZ,
    
    -- Offline support: payment recorded locally, synced later
    is_offline BOOLEAN DEFAULT FALSE,
    offline_receipt_data JSONB -- Store NearPay receipt for later sync
);

-- NearPay device registration (each device = one terminal)
CREATE TABLE nearpay_terminals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    device_id VARCHAR(100) NOT NULL,      -- Android device ID
    nearpay_tid VARCHAR(50),              -- Terminal ID (from HALA or bank)
    nearpay_mid VARCHAR(50),              -- Merchant ID at acquirer
    acquirer_source VARCHAR(20) NOT NULL, -- 'hala', 'bank_rajhi', 'bank_snb', etc.
    acquirer_name VARCHAR(100),           -- Human-readable acquirer name
    device_model VARCHAR(100),
    android_version VARCHAR(20),
    nfc_capable BOOLEAN DEFAULT TRUE,
    status VARCHAR(20) DEFAULT 'active',  -- 'active', 'suspended', 'deactivated'
    activated_at TIMESTAMPTZ,
    last_transaction_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Daily reconciliation records
CREATE TABLE payment_reconciliations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID REFERENCES stores(id),
    terminal_id UUID REFERENCES nearpay_terminals(id),
    reconciliation_date DATE NOT NULL,
    
    total_transactions INTEGER,
    total_amount DECIMAL(12, 2),
    total_refunds DECIMAL(12, 2),
    net_amount DECIMAL(12, 2),
    
    mada_count INTEGER DEFAULT 0,
    mada_amount DECIMAL(12, 2) DEFAULT 0,
    visa_count INTEGER DEFAULT 0,
    visa_amount DECIMAL(12, 2) DEFAULT 0,
    mastercard_count INTEGER DEFAULT 0,
    mastercard_amount DECIMAL(12, 2) DEFAULT 0,
    
    total_fees DECIMAL(10, 2),
    thawani_total_margin DECIMAL(10, 2),
    
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'matched', 'discrepancy'
    nearpay_reconciliation_id VARCHAR(100),
    
    created_at TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE(store_id, terminal_id, reconciliation_date)
);

-- Indexes
CREATE INDEX idx_payment_txn_store ON payment_transactions(store_id, created_at);
CREATE INDEX idx_payment_txn_nearpay ON payment_transactions(nearpay_transaction_id);
CREATE INDEX idx_payment_txn_status ON payment_transactions(status);
CREATE INDEX idx_nearpay_terminals_store ON nearpay_terminals(store_id);
CREATE INDEX idx_reconciliation_date ON payment_reconciliations(reconciliation_date);
```

### Recommended Hardware for SoftPOS

```
┌─────────────────────────────────────────────────────────────────┐
│          RECOMMENDED ANDROID TABLETS FOR SOFTPOS                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  BUDGET (Small Groceries - بقالة):                             │
│  ──────────────────────────────────                            │
│  Samsung Galaxy Tab A8 (10.5")                                 │
│  • Price: ~700-900 SAR                                         │
│  • NFC: ✅ Yes                                                  │
│  • Screen: 10.5" (good for POS)                                │
│  • Android 13+                                                 │
│  • 4GB RAM / 64GB storage                                      │
│                                                                 │
│  MID-RANGE (Mini Markets):                                     │
│  ─────────────────────────                                     │
│  Samsung Galaxy Tab S6 Lite (10.4")                             │
│  • Price: ~1,200-1,500 SAR                                     │
│  • NFC: ✅ Yes                                                  │
│  • Screen: 10.4" AMOLED                                        │
│  • Android 14+                                                 │
│  • 4GB RAM / 128GB storage                                     │
│  • S Pen included (useful for signatures)                      │
│                                                                 │
│  PREMIUM (Supermarkets):                                       │
│  ────────────────────────                                      │
│  Samsung Galaxy Tab S9 FE (10.9")                               │
│  • Price: ~1,800-2,300 SAR                                     │
│  • NFC: ✅ Yes                                                  │
│  • Screen: 10.9" TFT                                           │
│  • Android 14+                                                 │
│  • 6-8GB RAM / 128-256GB storage                               │
│  • IP68 waterproof (spill-proof for supermarket)               │
│  • Long battery life                                           │
│                                                                 │
│  ACCESSORIES:                                                  │
│  ────────────                                                  │
│  • Tablet stand/mount: ~100-200 SAR (for counter mounting)     │
│  • USB-C hub: ~80-150 SAR (for USB printer/scanner)            │
│  • Receipt printer: ~500-1,000 SAR (Bixolon USB/Network)      │
│  • Barcode scanner: ~200-400 SAR (Bluetooth/USB)               │
│                                                                 │
│  TOTAL SETUP COST (vs traditional):                            │
│  ──────────────────────────────────                            │
│  Traditional: Terminal ~2,500 + POS PC ~3,000 = ~5,500 SAR    │
│  SoftPOS:     Tablet ~1,200 + Accessories ~800 = ~2,000 SAR   │
│  SAVINGS:     ~3,500 SAR per register (63% less)               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Summary & Action Plan (Updated)

```
┌─────────────────────────────────────────────────────────────────┐
│              SOFTPOS INTEGRATION ACTION PLAN                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  DECISION: NearPay (technology) + HALA (acquiring, Phase 1)    │
│            + Direct bank acquiring (Phase 2)                   │
│                                                                 │
│  PHASE 1: PARTNERSHIPS (Week 1-3)                              │
│  ─────────────────────────────────                             │
│  □ Sign NearPay technology partnership                         │
│    • Get SDK + Flutter plugin + sandbox access                 │
│    • Agree on per-terminal or per-txn tech fee                 │
│  □ Sign HALA acquiring partnership                             │
│    • Negotiate wholesale rates (target: mada ≤1.2%)           │
│    • Get TID provisioning API/process                          │
│    • Understand settlement terms                               │
│  □ Explore direct bank relationships in parallel               │
│    • Contact Al Rajhi / SNB acquiring departments              │
│    • Inquire about SoftPOS TID issuance                        │
│    • Compare rates to HALA                                     │
│                                                                 │
│  PHASE 2: DEVELOPMENT (Week 4-7)                               │
│  ────────────────────────────────                              │
│  □ Integrate NearPay Flutter SDK into POS app                  │
│  □ Build payment flow UI (tap-to-pay screen)                   │
│  □ Implement TID configuration/activation flow                 │
│  □ Implement refund flow                                       │
│  □ Build reconciliation sync (with HALA settlement reports)   │
│  □ Add payment method to receipt printing                      │
│  □ Test with sandbox (simulated NFC payments)                  │
│                                                                 │
│  PHASE 3: CERTIFICATION (Week 8-9)                             │
│  ──────────────────────────────────                            │
│  □ NearPay tests your integration                              │
│  □ EMV certification on target devices                         │
│  □ mada scheme certification via HALA                          │
│  □ Production TIDs and API keys issued                         │
│                                                                 │
│  PHASE 4: PILOT (Week 10-14)                                  │
│  ──────────────────────────                                    │
│  □ Deploy to 3-5 pilot stores (with HALA TIDs)                │
│  □ Monitor transaction success rates                           │
│  □ Verify reconciliation accuracy vs HALA reports             │
│  □ Collect merchant feedback                                   │
│  □ Track fee breakdown (verify margin)                         │
│                                                                 │
│  PHASE 5: DIRECT BANK (Month 4-6)                              │
│  ─────────────────────────────────                             │
│  □ With volume data, negotiate direct bank acquiring           │
│  □ Get bank SoftPOS TIDs for high-volume merchants            │
│  □ Implement dual-acquirer routing in POS                      │
│  □ Migrate top merchants to direct bank (higher margin)        │
│  □ Keep HALA for quick onboarding of new small merchants       │
│                                                                 │
│  PHASE 6: SCALE                                                │
│  ─────────────────                                             │
│  □ Automated merchant onboarding (HALA API + bank process)    │
│  □ Device activation flow in POS setup wizard                  │
│  □ Smart routing: small merchants → HALA, large → bank        │
│  □ Scale to all subscribed stores                              │
│                                                                 │
│  KEY CONTACTS:                                                 │
│  ─────────────                                                 │
│  • NearPay: https://nearpay.io — SoftPOS technology partner   │
│  • HALA: Partnership/acquiring — TID issuance                  │
│  • Al Rajhi acquiring dept — direct bank TIDs                  │
│  • SNB / Riyad Bank — alternative direct bank options          │
│  • SAMA: Not needed directly (NearPay + HALA are licensed)    │
│                                                                 │
│  KEY QUESTION TO ASK HALA:                                     │
│  ──────────────────────────                                    │
│  1. What is your mada rate for SoftPOS (via NearPay)?         │
│  2. Can Thawani be a sub-facilitator under your master MID?   │
│  3. What is onboarding time per merchant?                      │
│  4. Is settlement T+1 or T+2?                                  │
│  5. Do you have an API for TID provisioning?                   │
│  6. What is your minimum monthly volume commitment?            │
│                                                                 │
│  KEY QUESTION TO ASK BANK:                                     │
│  ──────────────────────────                                    │
│  1. Do you support issuing TIDs for SoftPOS (NearPay)?        │
│  2. What is your mada/Visa/MC acquiring rate for SoftPOS?     │
│  3. Can Thawani get a master/PayFac arrangement?              │
│  4. What is merchant onboarding time?                          │
│  5. What documentation is needed per merchant?                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

*Document Version: 3.1*
*Updated: March 10, 2026*
*Scope: Standalone Commercial POS System for Saudi Market*
*Stack: Flutter + Laravel Only (No Next.js)*
*Author: GitHub Copilot Analysis*
