# ZATCA Compliance — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS — Saudi Arabia)  
> **Module:** ZATCA Phase 2 E-Invoicing, QR Code Generation, XML Invoice Signing, Tax Reporting  
> **Tech Stack:** Flutter 3.x Desktop · pointycastle · qr_flutter · xml · Laravel 11  

---

## 1. Feature Overview

ZATCA Compliance implements Saudi Arabia's Zakat, Tax and Customs Authority requirements for electronic invoicing (Fatoorah). Phase 2 requires cryptographic signing of invoices, real-time or near-real-time reporting to ZATCA, and specific QR code formats on printed receipts. This module is activated only for stores operating in Saudi Arabia.

### What This Feature Does
- **E-invoice generation** — generates ZATCA-compliant XML invoices (UBL 2.1 format) for every transaction
- **Cryptographic signing** — ECDSA digital signature on every invoice using the store's ZATCA-registered certificate
- **QR code generation** — TLV-encoded Base64 QR code on receipts containing seller name, VAT number, timestamp, total, VAT amount, and digital signature hash
- **Invoice hash chain** — each invoice references the previous invoice's hash (PIH — Previous Invoice Hash) creating a tamper-evident chain
- **Real-time clearance** — simplified tax invoices (B2B ≥ 1,000 SAR) are submitted to ZATCA for clearance before being issued
- **Near-real-time reporting** — standard tax invoices (B2C) are reported to ZATCA within 24 hours
- **Credit / debit notes** — ZATCA-compliant credit notes for returns and debit notes for corrections
- **ZATCA certificate management** — CSR generation, certificate enrollment, renewal
- **Compliance dashboard** — overview of invoice submission status, rejected invoices, compliance score
- **Offline signing** — invoices signed locally using the private key; submission queued for when online

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **POS Terminal** | Transaction data for invoice generation |
| **Order Management** | Order details for invoice content |
| **Customer Management** | Customer details for B2B invoices |
| **Language & Localization** | Bilingual invoices (Arabic mandatory) |
| **Offline/Online Sync** | Queued invoice submission when online |
| **Business Type & Onboarding** | ZATCA activated for Saudi Arabia businesses |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **POS Terminal** | QR code on receipts; signing delay in checkout flow |
| **Reports & Analytics** | Tax reporting; VAT summary |
| **Payments & Finance** | Tax amount calculations |

### Features to Review After Changing This Feature
1. **POS Terminal** — receipt QR code format
2. **Order Management** — invoice reference on orders
3. **Business Type & Onboarding** — ZATCA setup step

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **pointycastle** | ECDSA cryptographic signing (secp256k1) |
| **qr_flutter** | QR code generation for printed receipts |
| **xml** | UBL 2.1 XML invoice generation |
| **asn1lib** | ASN.1 encoding for certificate handling |
| **basic_utils** | X.509 certificate parsing |
| **flutter_secure_storage** | Secure storage for ZATCA private key |
| **crypto** | SHA-256 hashing for invoice hash chain |
| **dio** | HTTP client for ZATCA API |
| **archive** | Base64 encoding for QR code TLV data |

### 3.2 Technologies
- **UBL 2.1 (Universal Business Language)** — XML schema for ZATCA e-invoices
- **ECDSA (secp256k1)** — Elliptic Curve Digital Signature Algorithm for invoice signing
- **X.509 Certificate** — store's ZATCA-registered signing certificate; enrolled via CCSID (Cryptographic Stamp Identifier)
- **TLV (Tag-Length-Value)** — encoding format for QR code data per ZATCA specifications
- **PIH (Previous Invoice Hash)** — SHA-256 hash of the previous invoice embedded in the current invoice for chain integrity
- **ZATCA Fatoorah Portal API** — ZATCA's official API for invoice submission, clearance, and reporting
- **CSR (Certificate Signing Request)** — generated locally; submitted to ZATCA for certificate enrollment

---

## 4. Screens

### 4.1 ZATCA Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/zatca` |
| **Purpose** | Configure ZATCA compliance |
| **Layout** | Certificate status (active/expired/not enrolled), VAT registration number, ZATCA organization unit, submission mode (real-time clearance / batch reporting), certificate details (expiry date, issuer) |
| **Actions** | Enroll Certificate, Renew Certificate, Test Connection |
| **Access** | Owner only |

### 4.2 Certificate Enrollment Wizard
| Field | Detail |
|---|---|
| **Route** | `/settings/zatca/enroll` |
| **Steps** | 1. Enter OTP from ZATCA portal → 2. Generate CSR → 3. Submit to ZATCA → 4. Receive production CSID → 5. Verify certificate |
| **Security** | Private key generated locally and never leaves the device |

### 4.3 ZATCA Compliance Dashboard
| Field | Detail |
|---|---|
| **Route** | `/zatca/dashboard` |
| **Purpose** | Monitor invoice submission compliance |
| **Layout** | Cards: Total invoices submitted, Pending submission, Accepted, Rejected, Warnings. Compliance percentage. Last submission time. List of rejected invoices with error codes. |
| **Actions** | Retry failed submissions, View invoice XML, Export tax report |
| **Access** | Owner, Accountant |

### 4.4 Invoice Viewer
| Field | Detail |
|---|---|
| **Route** | `/zatca/invoices/{id}` |
| **Purpose** | View ZATCA invoice details |
| **Layout** | Invoice XML preview, QR code display, signing certificate info, submission status, ZATCA response (if any), hash chain visualization |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `POST /api/zatca/enroll` | POST | Submit CSR for ZATCA certificate enrollment | Bearer token, Owner |
| `POST /api/zatca/renew` | POST | Renew ZATCA certificate | Bearer token, Owner |
| `POST /api/zatca/submit-invoice` | POST | Submit signed invoice to ZATCA (via Laravel proxy) | Bearer token |
| `POST /api/zatca/submit-batch` | POST | Batch submit invoices for reporting | Bearer token |
| `GET /api/zatca/invoices` | GET | List ZATCA invoices with submission status | Bearer token |
| `GET /api/zatca/invoices/{id}/xml` | GET | Download invoice XML | Bearer token |
| `GET /api/zatca/compliance-summary` | GET | Compliance dashboard data | Bearer token |
| `GET /api/zatca/vat-report` | GET | VAT report for period | Bearer token, Owner/Accountant |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `ZatcaInvoiceGenerator` | Generates UBL 2.1 XML invoices from transaction data |
| `ZatcaSigningService` | ECDSA invoice signing using private key; hash chain maintenance |
| `ZatcaQrCodeService` | TLV encoding and QR code generation for receipts |
| `ZatcaCertificateService` | CSR generation, certificate enrollment, renewal, secure storage |
| `ZatcaSubmissionService` | Submit invoices to ZATCA via API; retry failed submissions |
| `ZatcaHashChainService` | Maintains PIH (Previous Invoice Hash) chain; validates integrity |
| `ZatcaComplianceService` | Calculates compliance metrics; tracks pending/rejected invoices |
| `ZatcaVatCalculator` | Calculates VAT amounts per ZATCA rules; handles exempt items |

---

## 6. Full Database Schema

### 6.1 Tables

#### `zatca_invoices`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| order_id | UUID | FK → orders(id), NOT NULL | |
| invoice_number | VARCHAR(50) | NOT NULL | Sequential invoice number |
| invoice_type | VARCHAR(20) | NOT NULL | standard (B2C), simplified (B2B), credit_note, debit_note |
| invoice_xml | TEXT | NOT NULL | Complete UBL 2.1 XML |
| invoice_hash | VARCHAR(64) | NOT NULL | SHA-256 of the invoice |
| previous_invoice_hash | VARCHAR(64) | NOT NULL | PIH — hash of previous invoice |
| digital_signature | TEXT | NOT NULL | ECDSA signature (Base64) |
| qr_code_data | TEXT | NOT NULL | TLV-encoded Base64 QR data |
| total_amount | DECIMAL(12,2) | NOT NULL | Invoice total (SAR — 2 decimals) |
| vat_amount | DECIMAL(12,2) | NOT NULL | VAT amount |
| submission_status | VARCHAR(20) | DEFAULT 'pending' | pending, submitted, accepted, rejected, warning |
| zatca_response_code | VARCHAR(10) | NULLABLE | ZATCA response code |
| zatca_response_message | TEXT | NULLABLE | ZATCA validation messages |
| submitted_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE zatca_invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    order_id UUID NOT NULL REFERENCES orders(id),
    invoice_number VARCHAR(50) NOT NULL,
    invoice_type VARCHAR(20) NOT NULL,
    invoice_xml TEXT NOT NULL,
    invoice_hash VARCHAR(64) NOT NULL,
    previous_invoice_hash VARCHAR(64) NOT NULL,
    digital_signature TEXT NOT NULL,
    qr_code_data TEXT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    vat_amount DECIMAL(12,2) NOT NULL,
    submission_status VARCHAR(20) DEFAULT 'pending',
    zatca_response_code VARCHAR(10),
    zatca_response_message TEXT,
    submitted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### `zatca_certificates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| certificate_type | VARCHAR(20) | NOT NULL | compliance, production |
| certificate_pem | TEXT | NOT NULL | X.509 certificate (PEM) |
| ccsid | VARCHAR(100) | NOT NULL | Cryptographic Stamp Identifier |
| issued_at | TIMESTAMP | NOT NULL | |
| expires_at | TIMESTAMP | NOT NULL | |
| status | VARCHAR(20) | DEFAULT 'active' | active, expired, revoked |
| created_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE zatca_certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    certificate_type VARCHAR(20) NOT NULL,
    certificate_pem TEXT NOT NULL,
    ccsid VARCHAR(100) NOT NULL,
    issued_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);
```

> **Note:** The private key is stored ONLY in `flutter_secure_storage` on the POS device and NEVER in the database.

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `zatca_invoices_store_status` | (store_id, submission_status) | B-TREE | Compliance dashboard queries |
| `zatca_invoices_store_date` | (store_id, created_at) | B-TREE | Invoice listing |
| `zatca_invoices_order` | order_id | B-TREE | Invoice lookup by order |
| `zatca_invoices_number` | (store_id, invoice_number) | B-TREE UNIQUE | Invoice number uniqueness |
| `zatca_invoices_hash` | invoice_hash | B-TREE | Hash chain verification |
| `zatca_certificates_store` | (store_id, status) | B-TREE | Active certificate lookup |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ zatca_invoices
orders ──1:1──▶ zatca_invoices
stores ──1:N──▶ zatca_certificates
```

---

## 7. Business Rules

1. **Saudi Arabia only** — ZATCA compliance module is only activated for stores with country = Saudi Arabia; Oman stores do not generate ZATCA invoices
2. **Invoice number sequential** — invoice numbers must be strictly sequential with no gaps; the system ensures atomicity of number generation
3. **Hash chain integrity** — every invoice includes the SHA-256 hash of the previous invoice (PIH); the first invoice uses a well-known seed hash; breaking the chain requires re-submission
4. **B2B clearance** — simplified tax invoices (B2B, total ≥ 1,000 SAR) must be submitted to ZATCA for clearance BEFORE being issued to the buyer; POS holds the transaction until ZATCA responds (max 10s timeout; if no response, issue with pending status)
5. **B2C near-real-time** — standard tax invoices (B2C) must be reported to ZATCA within 24 hours of issuance; batch submission is done periodically
6. **QR code mandatory** — every printed receipt MUST include the TLV-encoded QR code with: seller name, VAT number, timestamp, total with VAT, VAT amount, and invoice hash
7. **Arabic mandatory** — all ZATCA fields (seller name, item descriptions) must include Arabic text; English is optional (bilingual)
8. **Private key security** — the ZATCA signing private key is generated on-device, stored in `flutter_secure_storage`, and NEVER transmitted over the network or stored in the database
9. **Certificate renewal** — ZATCA production certificates expire (typically 1 year); the system alerts 30 days before expiry; if expired, POS can still sign locally but submissions will be rejected
10. **Credit note linking** — ZATCA credit notes must reference the original invoice number and cannot exceed the original invoice amount
11. **VAT calculation** — Saudi VAT is 15% on all taxable items; the system supports tax-inclusive and tax-exclusive pricing modes
12. **Retry on failure** — failed ZATCA submissions are retried with exponential backoff: 30s, 2min, 10min, 1hr, up to 6hr max; after 24 hours of failures, an alert is sent to the store owner
