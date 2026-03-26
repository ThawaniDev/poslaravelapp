# Language & Localization — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS + Store Owner Web Dashboard)  
> **Module:** Multi-Language UI, RTL Support, Currency Formatting, Date/Time Localization  
> **Tech Stack:** Flutter 3.x Desktop · flutter_localizations · intl · Laravel 11  

---

## 1. Feature Overview

Language & Localization ensures the POS system is fully usable in Arabic (primary) and English, with proper RTL layout handling, locale-specific number and date formatting, and culturally appropriate defaults for Oman and Saudi Arabia markets.

### What This Feature Does
- **Dual language support** — Arabic (ar) and English (en) for all UI strings
- **RTL / LTR layout** — automatic layout direction based on selected language; Arabic uses RTL
- **Per-user language preference** — each staff member selects their preferred language; POS switches on login
- **Currency formatting** — OMR (3 decimals) and SAR (2 decimals) with locale-correct formatting
- **Date/time formatting** — Gregorian and Hijri calendar support; locale-appropriate date format
- **Receipt language** — receipts can be bilingual (Arabic + English) or single language
- **Dynamic string loading** — translation strings loaded from ARB files; can be updated via sync without app rebuild
- **Keyboard input** — Arabic and English keyboard support with language toggle
- **Number pad** — always uses Western Arabic numerals (0-9) for data entry regardless of UI language

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **Staff & User Management** | Per-user language preference storage |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **All UI Features** | Every screen uses localized strings |
| **POS Terminal** | Receipt language, number formatting |
| **Reports & Analytics** | Report headers, date formatting |
| **ZATCA Compliance** | Arabic invoice requirements |
| **Customer Management** | Customer-facing receipt language |

### Features to Review After Changing This Feature
1. **All screens** — any new string must be added to both AR and EN ARB files
2. **Receipt templates** — bilingual receipt layout

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **flutter_localizations** | Flutter SDK localization delegates |
| **intl** | Number formatting, date formatting, pluralization |
| **easy_localization** or **flutter_gen** | ARB-based translation string management |
| **hijri** / **hijri_calendar** | Hijri calendar date formatting |
| **drift** | SQLite ORM — stores user language preference |

### 3.2 Technologies
- **ARB files** — Application Resource Bundle format for translation strings (`app_ar.arb`, `app_en.arb`)
- **Locale-aware formatting** — `NumberFormat.currency(locale: 'ar_OM')` for OMR with 3 decimals
- **RTL layout** — Flutter's built-in `Directionality` widget; all padding/margin uses `EdgeInsetsDirectional`
- **Text rendering** — Arabic text uses right-aligned `TextDirection.rtl`; numbers within Arabic text use LTR embedding
- **Receipt templates** — bilingual templates use two-column layout (Arabic right, English left)
- **Hijri date** — displayed alongside Gregorian date on receipts and reports per Saudi requirement

---

## 4. Screens

### 4.1 Language Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/language` |
| **Purpose** | Select system language and locale preferences |
| **Layout** | Language selector (Arabic / English), Date format preference (DD/MM/YYYY, Hijri toggle), Number format preview, Receipt language (Arabic only, English only, Bilingual) |
| **Access** | Per-user setting; store default set by Owner |

### 4.2 Translation Override Screen (Optional — Web Dashboard)
| Field | Detail |
|---|---|
| **Route** | `/dashboard/settings/translations` |
| **Purpose** | Override specific translation strings (e.g., custom receipt text) |
| **Layout** | Searchable table — key, Arabic text, English text; editable fields |
| **Scope** | Only receipt-related and customer-facing strings are overridable |
| **Access** | Owner only |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/translations` | GET | Download translation strings (ARB data) | Bearer token |
| `GET /api/translations/overrides` | GET | Store-specific translation overrides | Bearer token |
| `PUT /api/translations/overrides` | PUT | Update translation overrides | Bearer token, Owner |
| `PUT /api/staff/{id}/language` | PUT | Update staff language preference | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `LocaleService` | Manages active locale, language switching, direction |
| `TranslationLoader` | Loads ARB strings; applies store overrides |
| `CurrencyFormatter` | Locale-aware currency formatting (OMR 3dp, SAR 2dp) |
| `DateFormatter` | Gregorian + Hijri date formatting per locale |
| `ReceiptLanguageService` | Determines receipt language (single/bilingual) and renders accordingly |

---

## 6. Full Database Schema

> **Note:** This feature does not have dedicated database tables. Language preferences are stored in existing user/store tables. Translation overrides are stored as shown below.

### 6.1 Tables

#### `translation_overrides`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | UUID | PK | |
| store_id | UUID | FK → stores(id), NOT NULL | |
| string_key | VARCHAR(200) | NOT NULL | ARB string key |
| locale | VARCHAR(5) | NOT NULL | "ar" or "en" |
| custom_value | TEXT | NOT NULL | Store-specific override |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

```sql
CREATE TABLE translation_overrides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    store_id UUID NOT NULL REFERENCES stores(id),
    string_key VARCHAR(200) NOT NULL,
    locale VARCHAR(5) NOT NULL,
    custom_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(store_id, string_key, locale)
);
```

### 6.2 Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `translation_overrides_store_locale` | (store_id, locale) | B-TREE | Load all overrides for store + locale |

### 6.3 Relationships Diagram
```
stores ──1:N──▶ translation_overrides
staff_users.language_preference ── stores language setting per user
```

---

## 7. Business Rules

1. **Default language** — Arabic is the default language for new stores in Oman and Saudi Arabia
2. **Instant language switch** — changing language reloads all strings without requiring app restart; layout direction changes immediately
3. **Number pad consistency** — numeric input (prices, quantities, PINs) always uses Western Arabic numerals (0-9) regardless of UI language
4. **Receipt bilingual layout** — when bilingual mode is enabled, product names show Arabic/English stacked; store name in both languages; totals use both numeral systems
5. **Currency decimal places** — OMR always 3 decimal places (0.100); SAR always 2 decimal places (1.00); enforced at the formatter level
6. **Hijri date on receipts** — Hijri date is displayed alongside Gregorian on receipts and invoices when the store is in Saudi Arabia (ZATCA requirement)
7. **Missing translation fallback** — if a translation string is missing in the active locale, fall back to English; if English is also missing, display the key name
8. **Translation override scope** — only customer-facing strings (receipt text, customer display text) can be overridden; system/internal strings cannot
9. **RTL-safe icons** — directional icons (arrows, back buttons) are automatically mirrored in RTL mode
10. **Number formatting in reports** — reports use the store's locale for number formatting; exported CSV files use standard decimal notation for portability
