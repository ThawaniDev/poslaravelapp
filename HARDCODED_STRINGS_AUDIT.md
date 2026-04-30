# Hardcoded Strings Audit — Pre-Production Checklist

> Generated: 30 April 2026  
> Scanner found **1,038 raw occurrences** across PHP, Blade, and JS files.  
> After filtering false-positives (Eloquent `.with()`, ISO unit suffixes, mock-preview data), the **actionable count is ~310 strings** that need `__()` wrapping.

---

## False-Positive Categories (do NOT fix)

| Pattern | Reason |
|---|---|
| `->with('relationship')` | Eloquent eager-loading key, not UI text |
| `->prefix('SAR')` / `->suffix('SAR')` | ISO currency code — not translated |
| `->suffix('mm')` / `->suffix('px')` / `->suffix('ms')` / `->suffix('kg')` / `->suffix('bytes')` | Technical units — standard abbreviations |
| Preview blade templates with dummy data ("2× Cappuccino", "Cashier: Ahmed") | Design mock data in template previewer, not real content |
| `documents/zatca-store-setup-guide.blade.php` | Internal ops guide (PDF), not a UI screen |
| `welcome.blade.php` | Default Laravel starter page, not production |

---

## PRIORITY 1 — CRITICAL (Customer-Facing)

These strings are visible to **end customers** (merchants, store owners) in emails, payment pages, and invoices. They must be translated before go-live.

### 📧 `resources/views/emails/payment-reminder.blade.php`

| Line | Hardcoded String |
|------|-----------------|
| 6 | `<title>Subscription Reminder</title>` |
| 29 | `Wameed POS` (email header) |
| 33 | `Your subscription is expiring soon` |
| 35 | `plan expires on` |
| 38 | `Your subscription has expired` |
| 43 | `Your trial is ending soon` |
| 45 | `ends on` |
| 51 | `Organization` (table label) |
| 55 | `Plan` (table label) |
| 59 | `Expiry Date` (table label) |
| 65 | `Please renew to continue...` (footer CTA) |
| 67 | `All rights reserved.` (footer) |

**Fix:** Wrap all visible strings in `{{ __('emails.payment_reminder.xxx') }}`. The email is sent to Arabic and English users — it must respect the store's locale.

---

### 📧 `resources/views/emails/announcement.blade.php` & `notification.blade.php`

| Line | Hardcoded String |
|------|-----------------|
| 28/23 | `Wameed POS` (email header branding text) |

---

### 💳 `resources/views/payment/result.blade.php`

| Line | Hardcoded String |
|------|-----------------|
| 158 | `المبلغ الإجمالي / Total Amount` — bilingual hybrid, should be split and use locale |
| 172 | `الغرض / Purpose` |
| 176 | `المبلغ / Subtotal` |
| 180 | `الضريبة / VAT` |
| 185 | `رقم المرجع / Reference` |
| 191 | `رقم الطلب / Order ID` |
| 197 | `البطاقة / Card` |
| 202 | `التاريخ / Date` |
| 207 | `السبب / Reason` |
| 215 | `Payment information not available` |

**Fix:** These bilingual "label / label" strings should be replaced with `{{ __('payment.amount') }}` etc. and the template should conditionally render based on `app()->getLocale()`.

---

### 🧾 `resources/views/invoices/subscription.blade.php`

| Line | Hardcoded String |
|------|-----------------|
| 225 | `PAID` (status badge) |
| 231 | `Wameed POS` |
| 232 | `وميض نقاط البيع — Platform Invoice` |
| 235 | `Invoice` (document title) |
| 254 | `From` |
| 255 | `Wameed Technology` |
| 256 | `Riyadh, Saudi Arabia` |
| 257 | `VAT: 300000000000003` |
| 260 | `Bill To` |
| 275 | `Issue Date` |
| + 17 more | Column headers, totals, footer text |

**Note:** The company name and address are arguably config values rather than translations, but the column headers (Invoice #, Description, Qty, Unit Price, Total, Subtotal, VAT, Grand Total) all need `__()`.

---

### 📬 `app/Domain/Customer/Services/DigitalReceiptService.php`

| Line | Issue |
|------|-------|
| 79 | `.subject('Your receipt')` — email subject sent to customer, not translated |

**Fix:** `->subject(__('emails.receipt.subject'))`

---

### 📬 `app/Domain/AppUpdateManagement/Notifications/AutoRollbackNotification.php`

| Line | Hardcoded String |
|------|-----------------|
| 38 | `.greeting('Alert: App Release Auto-Rolled Back')` |
| 41 | `.line('The release has been deactivated...')` |
| 43 | `.line('Please review the release...')` |

---

## PRIORITY 2 — HIGH (Admin Panel — Daily-Use Screens)

These are Filament resource labels seen by platform admins and operators constantly. While Arabic-only locales are less common for admins, consistency is required and the panel **does support Arabic** via the locale switcher.

### 🤖 `app/Filament/Resources/AIFeatureDefinitionResource.php` — 27 issues

All `->label()` calls use raw English strings:

```
"Slug", "Name (English)", "Name (Arabic)", "Description (English)",
"Description (Arabic)", "Category", "Icon", "Sort Order", "Default Model",
"Default Max Tokens", "Cost Per Request (USD)", "Enabled", "Premium Only",
"Daily Limit", "Monthly Limit", "Name", "Model", "Premium", "Sort"
```

**Fix pattern:**
```php
// Before
->label('Name (English)')
// After
->label(__('ai.fields.name_en'))
```

---

### 🤖 `app/Filament/Resources/AIProviderConfigResource.php` — 39 issues

```
"Provider", "Model ID", "Display Name", "Description", "API Key",
"Max Context Tokens", "Max Output Tokens", "Supports Vision",
"Supports JSON Mode", "Input Price ($)", "Output Price ($)", "Enabled",
"Default Model", "Sort Order", "Max Context", "Max Output", "Vision",
"JSON Mode", "Input ($/1M tokens)", "Output ($/1M tokens)",
"Input $/1M", "Output $/1M", "Context", "JSON", "Default", "Order"
```

Also: `.helperText('Stored encrypted. Leave blank to keep existing key.')`

---

### 📊 `app/Filament/Resources/AIUsageLogResource.php` — 39 issues

```
"Feature", "Store", "Store ID", "User ID", "Feature Definition ID",
"Model", "Status", "Cached", "Created At", "Input Tokens", "Output Tokens",
"Total Tokens", "Raw Cost (OpenAI)", "Margin %", "Billed Cost",
"Latency", "Payload Hash", "Error Message", "Messages Sent to Model",
"Metadata (JSON)", "Tokens", "Raw Cost", "Date"
```

Also: `.description('OpenAI price')`, `.placeholder('No errors')`

---

### 💳 `app/Filament/Pages/WameedAIBilling.php` — 6 notification titles

```php
->title('Setting updated')       // L88
->title('Setting added')         // L104
->title('Setting deleted')       // L114
->title('Store config updated')  // L152
->title('Invoice marked as paid')    // L212
->title('Invoice marked as overdue') // L225
```

---

### 🧾 `app/Filament/Resources/ZatcaInvoiceResource/Pages/ListZatcaInvoices.php` — 4 issues

```
->label('Invoice Type')                    // L38
->label('B2B (for Credit/Debit notes)')   // L49
->helperText('ON = Standard B2B subtype, OFF = Simplified B2C')  // L51
->helperText('Including VAT')              // L58
```

---

### 🏛️ `app/Filament/Resources/ZatcaCertificateResource.php` — 10 issues

```
->label('Environment')
->helperText('developer-portal = stub QA · simulation = ZATCA simulation server · production = live ZATCA')
->label('ZATCA API URL')
->label('CCSID'), ->label('PCSID')
->label('Certificate PEM'), ->label('CSR PEM'), ->label('Public Key PEM')
```

---

### 🏛️ `app/Filament/Resources/ZatcaCertificateResource/Pages/ListZatcaCertificates.php` — 6 issues

```
->label('Run All 6 Compliance Tests')         // L33
->label('Store')                               // L41
->label('Get Production Certificate (PCSID)') // L192
->label('Store')                               // L200
->title('Production certificate issued')       // L210
->title('PCSID issuance failed')               // L217
```

---

### 🏛️ `app/Filament/Resources/ZatcaDeviceResource.php` — 4 issues

```
->label('UUID'), ->label('ICV'), ->label('PIH')
```

---

### ❌ `app/Filament/Resources/FailedJobResource.php` — 2 issues

```
->label('ID'), ->label('UUID')
```

---

### 💰 `app/Filament/Resources/TransactionResource.php` — 4 issues

```
->label('UUID'), ->label('Hash'), ->label('ZATCA')  // L188, 189, 270, 301
```

---

### 🔧 `app/Filament/Resources/SignageTemplateResource.php` — 4 issues

```
->label('X %'), ->label('Y %'), ->label('W %'), ->label('H %')
```

---

### 🔧 `app/Filament/Resources/LabelLayoutTemplateResource.php` — 4 label issues

```
->label('X %'), ->label('Y %'), ->label('W %'), ->label('H %')
```

---

## PRIORITY 3 — MEDIUM (Admin Panel — Specialist/Infrequent Screens)

These screens are used by platform operators or rarely by admins. Still need translation for a polished Arabic panel.

### 🏷️ `app/Filament/Pages/ZatcaStoreSetupPage.php` — 2 helperText issues

```
L421: ->helperText('Get OTP from https://fatoora.zatca.gov.sa → EGS Units → Add Device. Use 123321 for developer-portal only.')
L431: ->helperText('This is stored on the certificate. Each store can be on a different environment independently of the server .env.')
```

---

### 🏷️ `app/Filament/Resources/DeliveryPlatformResource.php` — 2 real issues

```
L205: ->placeholder('Restaurant ID')   ← visible label
L172: ->placeholder('SA, AE, BH...')   ← hint text
```

---

### 🏷️ `app/Filament/Resources/PricingPageContentResource.php` — 5 real issues

```
->placeholder('Most Popular')      // L113
->placeholder('Get Started')       // L159
->placeholder('Learn More')        // L168
->placeholder('Starting at')       // L189
->placeholder('Save 20% annually') // L207
```

These placeholder values are default CMS content that may appear on the public pricing page if an admin doesn't override them.

---

### 🤖 `app/Filament/Resources/BusinessTypeResource/RelationManagers/GamificationChallengesRelationManager.php`

```
L81: ->placeholder('Visit us 4 weekends in a row and earn 200 bonus points')
```
This description placeholder will appear in the admin UI.

---

### 🏷️ `app/Filament/Resources/BusinessTypeResource/RelationManagers/ReceiptTemplateRelationManager.php`

```
L121: ->placeholder('e.g., Thank you for your visit!')
```

---

## PRIORITY 4 — LOW (Filament Custom Blade Views)

These Filament Blade partials are rendered inside the admin panel and contain hardcoded English.

### 📊 WameedAI Pages — `resources/views/filament/pages/wameed-ai-*`

All custom Blade tabs for the AI billing and store intelligence pages are **entirely hardcoded in English** with no `__()` calls.

**Files affected:**
| File | Hardcoded strings |
|------|------------------|
| `_tab-invoices.blade.php` | Requests, Raw Cost, Billed |
| `_tab-overview.blade.php` | Overdue, Total Invoiced, Total Paid, etc. |
| `_tab-settings.blade.php` | Value, Description, Save, Cancel |
| `_tab-stores.blade.php` | Notes, Save, Cancel |
| `_tab-billing.blade.php` | 37 hardcoded strings |
| `_tab-chats.blade.php` | Avg Msgs/Chat, Chat Tokens, Chat Cost, No chats for this store |
| `_tab-features.blade.php` | 27 hardcoded strings |
| `_tab-logs.blade.php` | 12 hardcoded strings |
| `_tab-trends.blade.php` | 16 hardcoded strings |

**Fix pattern:**
```html
<!-- Before -->
<th>Raw Cost</th>

<!-- After -->
<th>{{ __('ai.table.raw_cost') }}</th>
```

---

### 📊 `resources/views/filament/pages/zatca-configuration.blade.php`

```
"CSR Template", ".env key"
```

### 📊 `resources/views/filament/pages/zatca-store-setup.blade.php`

```
"CCSID", "UUID"
```

---

## PRIORITY 5 — LOW (Preview/Designer Templates)

These Blade views power the **template preview** UI in the admin designer. The mock data ("Cappuccino", "Cashier: Ahmed", "VAT: 123456789012345") is intentional dummy data for visual preview and does **not** need translation.

However, **UI chrome** strings (column headers, labels shown around the preview) should be translated:

| File | Strings needing `__()` |
|------|----------------------|
| `resources/view/filament/resources/receipt-layout-template/preview.blade.php` | "Item", "Total", "Subtotal", "VAT (5%)", "TOTAL" |
| `resources/views/filament/resources/label-layout-template/preview.blade.php` | "Position (x, y)", "Size (w × h)", "Font Size", "Position:", "Size:" |
| `resources/views/filament/resources/signage-template/preview.blade.php` | "Video", "Position (x, y)", "Size (w × h)" |
| `resources/views/filament/resources/pos-layout-template/preview.blade.php` | "Z-Index" |
| `resources/views/filament/resources/theme/preview.blade.php` | "Colour Palette", "Active", "Featured", "Sale", "Wameed POS" |
| `resources/views/preview/receipt-template.blade.php` | "Bilingual", "Item", "Total", "Subtotal", "VAT (5%)", "TOTAL" |
| `resources/views/preview/label-template.blade.php` | "Field Layout Details", "Field", "Label (EN)", etc. |
| `resources/views/preview/marketplace-listing.blade.php` | "Featured", "Verified", "Screenshots", "Description", etc. |

---

## Summary by Priority

| Priority | Area | Approx. Count | Go-Live Blocker? |
|----------|------|--------------|-----------------|
| **P1 — CRITICAL** | Customer emails, payment page, invoices | ~50 | ✅ **Yes** |
| **P2 — HIGH** | Filament admin resources (AI, ZATCA, Transactions) | ~120 | ⚠️ Depends on whether admins use Arabic |
| **P3 — MEDIUM** | Specialist admin screens, pricing CMS defaults | ~25 | ⚠️ Minor |
| **P4 — LOW** | Custom Blade AI/ZATCA pages | ~100 | ❌ No |
| **P5 — LOW** | Preview template UI chrome | ~60 | ❌ No |

---

## Files Requiring Zero Changes (confirmed false positives)

- All `->with('...')` calls in Services/Controllers — Eloquent relationship loading
- All `->prefix('SAR')` / `->suffix('SAR')` — ISO currency code
- All `->suffix('mm')` / `->suffix('px')` / `->suffix('ms')` / `->suffix('kg')` / `->suffix('bytes')` — unit abbreviations
- `resources/views/documents/zatca-store-setup-guide.blade.php` — internal ops PDF
- `resources/views/welcome.blade.php` — Laravel default, should be removed in production
- Preview templates' dummy product data (Coffee, Croissant, etc.)

---

## Recommended Fix Strategy

### Phase 1 (Before Go-Live — P1 only)

1. **`resources/views/emails/payment-reminder.blade.php`** — Full translation pass. Add keys to `lang/en/emails.php` and `lang/ar/emails.php`.
2. **`resources/views/payment/result.blade.php`** — Replace bilingual hybrids with proper locale detection.
3. **`resources/views/invoices/subscription.blade.php`** — Translate column headers and status labels.
4. **`app/Domain/Customer/Services/DigitalReceiptService.php` L79** — `.subject(__('emails.receipt.subject'))`.

### Phase 2 (Post Go-Live — P2)

Filament admin labels for AI, ZATCA, and Transaction resources. Can be batched by running:

```bash
# Find all remaining hardcoded label() calls
grep -rn "->label('" app/Filament/ --include="*.php" | grep -v "__("
```

And replacing each with `->label(__('admin.resource_name.field_key'))`.

### Phase 3 (Later — P3/P4/P5)

Custom Blade tabs for AI billing dashboard and template preview UI chrome.

---

## Quick-Fix Script (for placeholders — run only if needed)

To identify all lines that still need fixing after Phase 1:

```bash
# Filament: labels without __()
grep -rn "->label('" app/Filament/ --include="*.php" | grep -v "__("

# Filament: titles/headings without __()
grep -rn "->title('" app/Filament/ --include="*.php" | grep -v "__("

# Blade: visible text not wrapped in {{ __() }}
grep -rn ">[A-Z][a-z]" resources/views/emails/ resources/views/payment/ resources/views/invoices/
```
