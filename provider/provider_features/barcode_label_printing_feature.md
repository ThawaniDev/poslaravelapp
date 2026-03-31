# Barcode Label Printing — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Label Design, Print Queue, Printer Communication  
> **Tech Stack:** Flutter 3.x Desktop · Drift (SQLite) · ZPL / TSPL · ESC/POS · USB / Network Printing  

---

## 1. Feature Overview

Barcode Label Printing enables store staff to design, preview, and print adhesive barcode labels for products. Labels are printed on thermal transfer or direct-thermal label printers (Zebra, TSC, Godex, Xprinter) and applied to products, shelves, and packaging. This feature is essential for grocery, retail, and warehouse workflows where items arrive without barcodes, require repricing, or need shelf-edge labels.

### What This Feature Does
- **Label template designer** — visual drag-and-drop editor for positioning: barcode, product name (AR/EN), price, expiry date, weight, store logo, QR code
- **Predefined templates** — preset layouts for common label sizes (40×30 mm, 58×40 mm, 100×50 mm, shelf-edge strips) comes from system (already done); users can create custom templates
- **Print queue** — select multiple products, set quantity per product, batch-print all labels in one job
- **Barcode formats** — supports EAN-13, Code128, QR Code, UPC-A, EAN-8
- **Dynamic field binding** — template fields pull live data from the product catalog (name, price, barcode, expiry, weight)
- **Price labels** — for price-by-weight items, prints price-per-kg labels with embedded weight/price barcode (21/22/23/24 prefix)
- **Shelf-edge labels** — smaller format labels for shelf rails
- **Printer auto-detection** — discovers USB-connected label printers; stores last-used printer preference
- **ZPL and TSPL output** — generates native printer commands for Zebra (ZPL II) and TSC (TSPL) printers
- **Image-based fallback** — for unsupported printers, renders label as a raster image and prints via raw ESC/POS or Windows driver

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Product & Catalog Management** | All label data (name, barcode, price, expiry) sourced from product catalog |
| **Hardware Support** | Printer discovery, USB/network communication layer |
| **Language & Localization** | Arabic name rendering on labels (RTL text flow) |
| **Inventory Management** | Expiry dates, batch numbers for label printing |
| **POS Interface Customization** | Label printer assignment per terminal |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Quick-print label from product lookup or after receiving goods |
| **Inventory Management** | Print labels after goods receipt or stock adjustment |
| **Industry-Specific Workflows** | Pharmacy labels (includes dosage), Jewelry labels (karat, weight), Bakery (production/expiry date) |

### Features to Review After Changing This Feature
1. **Hardware Support** — printer driver compatibility if print command format changes
2. **Product & Catalog Management** — if product fields change, label data bindings must update
3. **Industry-Specific Workflows** — custom label templates reference industry-specific fields

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **barcode** (pub.dev) | Generate barcode data matrices (EAN-13, Code128, QR) in Dart |
| **qr_flutter** | QR code widget rendering for label preview |
| **printing** (pub.dev) | Cross-platform print dialog and PDF/raster output for image-based fallback |
| **pdf** (pub.dev) | Generate PDF label layouts for preview and image-based printing |
| **flutter_libserialport** | Serial port communication for printers on COM/ttyUSB ports |
| **win32** (pub.dev) | Windows USB raw printing via `WritePrinter` API (ZPL/TSPL direct) |
| **drift** | Persist label templates and print history locally |
| **riverpod** / **flutter_bloc** | State management for template designer, print queue |
| **intl** | Number and date formatting on labels (Arabic numerals, Hijri dates) |

### 3.2 Technologies
- **ZPL II (Zebra Programming Language)** — native command language for Zebra label printers; generates text, barcodes, graphics with precise mm positioning
- **TSPL (TSC Printer Language)** — native command language for TSC label printers; similar to ZPL but different syntax
- **ESC/POS** — thermal receipt printer commands; used as fallback for label-capable receipt printers
- **USB Raw Printing (Windows)** — sends ZPL/TSPL bytes directly to printer via `winspool.drv` without going through GDI driver
- **Network Printing** — sends ZPL/TSPL commands via TCP socket to network-connected label printers (port 9100)
- **Flutter Canvas** — custom paint for WYSIWYG label template preview
- **Dart `Uint8List`** — binary buffer construction for printer command streams

---

## 4. Screens

### 4.1 Label Template Designer Screen
| Field | Detail |
|---|---|
| **Route** | `/labels/templates/designer` |
| **Purpose** | Create and edit label templates with visual drag-and-drop editor |
| **Layout** | Left: field palette (Product Name, Name AR, Barcode, Price, Expiry, Weight, Logo, QR Code, Custom Text). Centre: WYSIWYG label canvas with mm grid. Right: property panel (font size, rotation, alignment, position X/Y, width/height) |
| **Canvas** | Shows label at actual size (zoom in/out); handles drag-to-position and resize |
| **Label Sizes** | Dropdown: 40×30 mm, 58×40 mm, 100×50 mm, custom (enter W×H) |
| **Preview** | Live preview with sample product data bound to fields |
| **Actions** | Save template, Save as, Delete, Print test label |
| **Access** | `labels.manage` (Branch Manager, Owner) |

### 4.2 Label Template List Screen
| Field | Detail |
|---|---|
| **Route** | `/labels/templates` |
| **Purpose** | Browse and manage saved label templates |
| **Table Columns** | Template name, Label size, Created by, Created date, Actions |
| **Row Actions** | Edit (→ designer), Duplicate, Delete, Set as default |
| **Presets** | System presets (read-only, can be duplicated): Standard Product, Shelf Edge, Weighable Item |
| **Access** | `labels.view` |

### 4.3 Print Labels Screen (Print Queue)
| Field | Detail |
|---|---|
| **Route** | `/labels/print` |
| **Purpose** | Select products and print labels in batch |
| **Flow** | 1. Search/browse products → 2. Add to print queue with quantity → 3. Select template → 4. Select printer → 5. Print |
| **Queue Table** | Product name, Barcode, Price, Qty to print, Remove button |
| **Template Selector** | Dropdown of saved templates; shows preview thumbnail |
| **Printer Selector** | Dropdown of detected printers (USB + network); last-used is pre-selected |
| **Actions** | Print all, Clear queue |
| **Quick Print** | From product list or inventory receipt, "Print Label" sends 1 label directly with default template/printer |
| **Access** | `labels.print` (Cashier, Inventory Clerk, Branch Manager, Owner) |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/label-templates` | GET | List all label templates for organisation | Bearer token |
| `POST /api/label-templates` | POST | Create label template (JSON layout definition) | Bearer token + `labels.manage` |
| `PUT /api/label-templates/{id}` | PUT | Update label template | Bearer token + `labels.manage` |
| `DELETE /api/label-templates/{id}` | DELETE | Delete label template | Bearer token + `labels.manage` |
| `GET /api/label-templates/presets` | GET | List system preset templates | Bearer token |

> **Note:** Label printing itself is entirely client-side — no server API call is made when printing. Templates are synced to allow sharing across terminals.

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `LabelTemplateRepository` | CRUD on label templates in local Drift DB; sync with cloud |
| `LabelRenderService` | Takes template + product data → renders label preview (Flutter Canvas / PDF) |
| `ZplGeneratorService` | Converts template + product data → ZPL II command string |
| `TsplGeneratorService` | Converts template + product data → TSPL command string |
| `LabelPrintService` | Orchestrates print job: selects generator (ZPL/TSPL/image), sends to printer |
| `PrinterDiscoveryService` | Enumerates USB and network printers; stores preferences |
| `UsbRawPrintService` | Sends raw bytes to USB printer via `win32` `WritePrinter` |
| `NetworkPrintService` | Sends raw bytes via TCP socket to network printer (port 9100) |
| `BarcodeDataService` | Generates barcode/QR binary data for embedding in labels |

---

## 6. Full Database Schema

### 6.1 Tables

#### `label_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| organization_id | UUID | FK → organizations(id), NOT NULL | |
| name | VARCHAR(255) | NOT NULL | Template display name |
| label_width_mm | DECIMAL(6,2) | NOT NULL | Label width in mm |
| label_height_mm | DECIMAL(6,2) | NOT NULL | Label height in mm |
| layout_json | JSONB | NOT NULL | Field positions, sizes, fonts, rotation |
| is_preset | BOOLEAN | DEFAULT FALSE | TRUE for system presets |
| is_default | BOOLEAN | DEFAULT FALSE | Default template for quick-print |
| created_by | UUID | FK → users(id), NULLABLE | |
| sync_version | INT | DEFAULT 1 | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE label_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id),
    name VARCHAR(255) NOT NULL,
    label_width_mm DECIMAL(6,2) NOT NULL,
    label_height_mm DECIMAL(6,2) NOT NULL,
    layout_json JSONB NOT NULL,
    is_preset BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    created_by UUID REFERENCES users(id),
    sync_version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `label_print_history`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| template_id | UUID | FK → label_templates(id), NULLABLE | |
| printed_by | UUID | FK → users(id), NOT NULL | |
| product_count | INT | NOT NULL | Number of distinct products in job |
| total_labels | INT | NOT NULL | Total labels printed |
| printer_name | VARCHAR(255) | NULLABLE | Printer used |
| printed_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE label_print_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    template_id UUID REFERENCES label_templates(id),
    printed_by UUID NOT NULL REFERENCES users(id),
    product_count INT NOT NULL,
    total_labels INT NOT NULL,
    printer_name VARCHAR(255),
    printed_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `label_templates_org` | organization_id | B-TREE | Template listing per organisation |
| `label_templates_org_default` | (organization_id, is_default) | B-TREE | Quick-find default template |
| `label_print_history_store_date` | (store_id, printed_at) | B-TREE | Print history report queries |

### 6.3 Relationships Diagram
```
organizations ──1:N──▶ label_templates
stores ──1:N──▶ label_print_history
label_templates ──1:N──▶ label_print_history
users ──1:N──▶ label_templates (created_by)
users ──1:N──▶ label_print_history (printed_by)
```

---

## 7. Business Rules

1. **System presets are immutable** — preset templates (`is_preset = TRUE`) cannot be edited or deleted; users can duplicate them and customise the copy
2. **One default per organisation** — only one template can be `is_default = TRUE`; setting a new default unsets the previous one
3. **Minimum label size** — label dimensions must be at least 20×15 mm; maximum 200×150 mm
4. **Barcode must fit** — the barcode field width must be sufficient to encode the data; system warns if the field is too small for the barcode type
5. **Arabic text rendering** — Arabic product names on labels must render right-to-left; the ZPL/TSPL generators include RTL control characters or use image rendering for Arabic glyphs
6. **Weighable product labels** — for weighable items, the barcode encodes weight (21/22-prefix) or price (23/24-prefix) per EAN-13 embedded format; the template must include a weight/price field
7. **Printer compatibility check** — before printing, the system verifies the selected printer supports ZPL or TSPL; if neither, falls back to image-based printing via Windows driver
8. **Print history retention** — print history is kept for 90 days locally, indefinitely on cloud for audit
9. **Offline print** — label printing works fully offline; templates are stored locally, printing is direct to USB/network with no server dependency
10. **Quick-print shortcut** — from any product context (product list, barcode scan, goods receipt), a "Print Label" action uses the default template and last-used printer with quantity = 1; no dialogs shown
