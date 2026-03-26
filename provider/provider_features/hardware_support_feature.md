# Hardware Support — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Peripherals — Receipt Printers, Barcode Scanners, Cash Drawers, Customer Displays, Weighing Scales, Label Printers, Card Terminals  
> **Tech Stack:** Flutter 3.x Desktop (Windows) · esc_pos_printer · flutter_libserialport · USB HID · NearPay SDK  

---

## 1. Feature Overview

Hardware Support covers the detection, configuration, and communication with all POS peripheral devices. The system auto-detects connected hardware where possible and provides a unified configuration screen for managing all peripherals. Each device type has dedicated driver logic abstracted behind a common interface.

### What This Feature Does
- **Receipt printers** — ESC/POS thermal printers (USB, Network/IP, Bluetooth); 58mm and 80mm paper widths; auto-cut support
- **Barcode scanners** — USB HID scanners (keyboard-wedge mode); dedicated serial scanners; Bluetooth scanners
- **Cash drawers** — electronically controlled via printer kick-pulse or dedicated USB driver
- **Customer display** — pole display or secondary screen showing line items and totals to customers
- **Weighing scales** — serial-connected precision scales (RS-232); auto-read weight for weighted products
- **Label printers** — Zebra (ZPL), TSC (TSPL), generic ESC/POS label printers; roll and sheet labels
- **Card payment terminals** — NearPay SDK integration for Mada/Visa/Mastercard
- **NFC readers** — for staff badge authentication (clock-in/out)
- **Auto-detection** — scan USB and network for compatible devices on startup
- **Test & Diagnostics** — test print, test scan, test drawer, test scale from configuration screen

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **POS Terminal** | Print receipts after transactions |
| **Barcode & Label Printing** | Label printer hardware |
| **Product Catalog** | Barcode scanner for product lookup |
| **Inventory Management** | Weighing scale for weighted products |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | Receipt printing, cash drawer, scanner, card terminal |
| **Barcode & Label Printing** | Label printer access |
| **Inventory Management** | Scale integration |
| **Staff & User Management** | NFC reader for clock-in |
| **Customer Management** | Customer display |

### Features to Review After Changing This Feature
1. **POS Terminal** — print flow, drawer kick flow
2. **Barcode & Label Printing** — label printer driver changes

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **esc_pos_printer** / **flutter_thermal_printer** | ESC/POS command generation and printing |
| **flutter_libserialport** | Serial port communication (RS-232 scales, serial scanners) |
| **win32** / **ffi** | Windows native API calls for USB device enumeration |
| **raw_printer** | Raw data sending to Windows print spooler |
| **nearpay_flutter** / **NearPay SDK** | Card payment terminal integration |
| **nfc_manager** | NFC badge reading |
| **usb_serial** | USB-to-serial adapter communication |

### 3.2 Technologies
- **ESC/POS** — standard thermal printer command language; supports text formatting, barcodes, QR codes, images, cut commands
- **ZPL (Zebra Programming Language)** — label printer command language for Zebra printers
- **TSPL (TSC Printer Language)** — label printer command language for TSC printers
- **RS-232 Serial** — weighing scale communication; reads weight data via serial port at 9600-115200 baud
- **USB HID** — barcode scanners in keyboard wedge mode appear as HID keyboards
- **Windows Print Spooler** — raw byte printing via Windows native API for receipt printers
- **mDNS / Network Discovery** — auto-detect network printers on LAN
- **NearPay SDK** — Android-based card payment terminal (running on NearPay device); Flutter communicates via SDK

---

## 4. Screens

### 4.1 Hardware Configuration Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/hardware` |
| **Purpose** | Configure all connected peripherals |
| **Layout** | Sections per device type: Receipt Printer, Barcode Scanner, Cash Drawer, Customer Display, Weighing Scale, Label Printer, Card Terminal, NFC Reader |
| **Per-Device Section** | Connection type (USB/Network/Bluetooth/Serial), device selection dropdown, port/IP configuration, driver settings, Test button |
| **Auto-Detect** | "Scan for Devices" button at top — detects USB, Network, Bluetooth peripherals |
| **Access** | `settings.hardware` permission |

### 4.2 Receipt Printer Setup
| Field | Detail |
|---|---|
| **Section** | Within Hardware Configuration |
| **Fields** | Connection type (USB/Network/Bluetooth), Printer model/driver, Paper width (58mm/80mm), Auto-cut enabled, Print density (light/normal/dark), Cash drawer connected via printer, Test Print button |
| **Network Printer** | IP address, Port (default 9100) |
| **USB Printer** | Detected device dropdown |

### 4.3 Scale Configuration
| Field | Detail |
|---|---|
| **Section** | Within Hardware Configuration |
| **Fields** | COM port, Baud rate, Data bits, Parity, Protocol (standard/custom), Unit (kg/g/lb), Decimal places, Test Read button |
| **Live Preview** | Shows current weight reading in real time once connected |

### 4.4 Customer Display Configuration
| Field | Detail |
|---|---|
| **Section** | Within Hardware Configuration |
| **Fields** | Display type (Pole display via serial / Secondary screen), COM port (for pole), Screen selection (for secondary), Welcome message, Idle message |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/hardware/config` | GET | Get saved hardware configuration for this POS terminal | Bearer token |
| `PUT /api/hardware/config` | PUT | Save hardware configuration | Bearer token, `settings.hardware` |
| `GET /api/hardware/supported-models` | GET | List of supported printer/scale models and drivers | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `HardwareManager` | Central registry of all connected peripherals; initialization and shutdown |
| `ReceiptPrinterService` | ESC/POS command builder and printer communication (USB, Network, Bluetooth) |
| `BarcodeScannerService` | Listens for HID keyboard events from barcode scanner; parses scan data |
| `CashDrawerService` | Sends kick-pulse to open drawer (via printer or direct USB) |
| `CustomerDisplayService` | Renders line items and totals to pole display or secondary screen |
| `WeighingScaleService` | Serial port communication; reads weight; applies tare; unit conversion |
| `LabelPrinterService` | ZPL/TSPL/ESC/POS label generator and printer communication |
| `CardTerminalService` | NearPay SDK integration; initiates payment, handles response |
| `NfcReaderService` | NFC tag UID reading for staff authentication |
| `HardwareAutoDetector` | USB enumeration, network printer discovery (mDNS) |
| `PrinterTestService` | Generates test print for receipt and label printers |

---

## 6. Full Database Schema

### 6.1 Tables

#### `hardware_configurations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| terminal_id | UUID | NOT NULL | POS terminal identifier |
| device_type | VARCHAR(30) | NOT NULL | receipt_printer, barcode_scanner, cash_drawer, customer_display, weighing_scale, label_printer, card_terminal, nfc_reader |
| connection_type | VARCHAR(20) | NOT NULL | usb, network, bluetooth, serial |
| device_name | VARCHAR(100) | NULLABLE | User-friendly name |
| config_json | JSONB | NOT NULL | Device-specific configuration |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE hardware_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    device_type VARCHAR(30) NOT NULL,
    connection_type VARCHAR(20) NOT NULL,
    device_name VARCHAR(100),
    config_json JSONB NOT NULL DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, terminal_id, device_type)
);
```

**`config_json` examples:**
- Receipt Printer: `{"model": "epson_tm_t88", "paper_width": 80, "auto_cut": true, "density": "normal", "ip": "192.168.1.100", "port": 9100}`
- Weighing Scale: `{"com_port": "COM3", "baud_rate": 9600, "data_bits": 8, "parity": "none", "protocol": "standard", "unit": "kg", "decimal_places": 3}`
- Cash Drawer: `{"trigger_method": "printer_kick", "pin": 0, "pulse_on": 100, "pulse_off": 100}`

#### `hardware_event_log`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| terminal_id | UUID | NOT NULL | |
| device_type | VARCHAR(30) | NOT NULL | |
| event | VARCHAR(50) | NOT NULL | connected, disconnected, error, test_success, test_failed |
| details | TEXT | NULLABLE | Error message or event details |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE hardware_event_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    terminal_id UUID NOT NULL,
    device_type VARCHAR(30) NOT NULL,
    event VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `hw_config_store_terminal` | (store_id, terminal_id) | B-TREE | All devices for a terminal |
| `hw_event_store_date` | (store_id, created_at) | B-TREE | Event log queries |
| `hw_event_device` | (store_id, device_type) | B-TREE | Per-device-type events |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ hardware_configurations
stores ──1:N──▶ hardware_event_log
hardware_configurations ── scoped by terminal_id
```

---

## 7. Business Rules

1. **One active device per type per terminal** — each POS terminal can have at most one active device per type (e.g., one receipt printer, one scale)
2. **Fallback on printer failure** — if the configured receipt printer is unavailable, show error dialog with options: Retry, Print to Alternative Printer, Skip Print
3. **Scanner mode detection** — barcode scanner must be in keyboard-wedge (HID) mode; the system distinguishes scanner input from keyboard input by timing (rapid character entry < 50ms between characters)
4. **Scale stability check** — weight is only accepted when the scale reports a stable reading (no fluctuation for 0.5 seconds)
5. **Cash drawer kick debounce** — prevent opening cash drawer more than once every 3 seconds to avoid hardware damage
6. **Auto-reconnect** — if a USB device is disconnected and reconnected, the system auto-detects and re-establishes the connection within 5 seconds
7. **Network printer timeout** — network printer connections timeout after 5 seconds; if unreachable, show error and offer retry
8. **Customer display fallback** — if no customer display is configured, the POS operates normally without customer-facing output
9. **Card terminal offline** — if NearPay terminal is offline, card payment option is hidden; only cash and other payment methods are shown
10. **Hardware config sync** — hardware configurations are synced to cloud so that if a terminal is replaced, the config can be restored to the new machine
11. **Print queue** — if a print job fails, it is queued locally and retried up to 3 times with 5-second intervals
12. **Scale zero calibration** — tare/zero button on the POS sends a zero command to the scale; this must be done with an empty weighing platform
