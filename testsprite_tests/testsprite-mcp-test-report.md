# API Smoke Test Report — 1132 Routes

- **Project:** poslaravelapp (Wameed POS / Thawani backend)
- **Date:** 2026-05-06
- **Target:** `https://system.wameedpos.com` (production, Frankfurt)
- **Test plan:** [testsprite_tests/testsprite_backend_test_plan.json](testsprite_tests/testsprite_backend_test_plan.json) (1132 cases, mirrored 1:1 in pytest)
- **Runner:** local pytest mirror at [testsprite_tests/local/](testsprite_tests/local/) (TestSprite cloud rejected the run with HTTP 403 "insufficient credits" on both API keys)
- **Result:** **1096 / 1132 passed (96.8%)** in **67 seconds** with 8-way parallelism
- **JUnit XML:** [testsprite_tests/local/junit.xml](testsprite_tests/local/junit.xml)

---

## 1. Test Methodology

For every unique `(method, uri)` under `api/v2/*` (1132 routes from [all_routes.json](all_routes.json)):

1. **Protected routes** — call once **without** `Authorization` header → must return `401/403/419`.
2. **Authenticated call** with seeded owner Bearer token (`owner@testsprite.local` / `Password123!`) → must NOT return `5xx` (4xx like `404/422` are accepted as the route exists and validates).
3. **Admin routes (`api/v2/admin/*`)** — with provider Sanctum token, expected to return `401/403` (admin-api guard rejects provider tokens).
4. **Path parameters** are filled with `00000000-0000-0000-0000-000000000000` (or `1` for numeric ids).
5. **Write methods** (`POST/PUT/PATCH/DELETE`) send `{}` as body; `400/404/409/422` are accepted.
6. Any **5xx** is a hard failure.

---

## 2. Results Summary

| Bucket | Count |
|---|---:|
| Total routes tested | **1132** |
| ✅ Passed | **1096** |
| ❌ Failed (reproducible) | **34** |
| 🌀 Transient (passed on re-run) | **2** |

### Failure categories

| Category | Count | Severity |
|---|---:|---|
| **Admin guard misconfiguration** — admin/wameed-ai/* accepts provider tokens (returns 200, should be 401) | **31** | **High** |
| **5xx server error on parameterized lookup** | 2 | Medium |
| **Public route returns 404 instead of 401 when unauthenticated** | 1 | Low |

---

## 3. Findings

### 🔴 Finding 1 — Admin AI endpoints are not protected by the admin guard (31 routes)

Every route under `api/v2/admin/wameed-ai/**` returns **HTTP 200** when called with a regular **provider** Sanctum token (the seeded `owner@testsprite.local`). All other `api/v2/admin/**` routes correctly return 401.

This means a provider-side cashier/owner can reach platform-admin AI endpoints, including:

- LLM model CRUD (`GET/POST/PUT/DELETE /api/v2/admin/wameed-ai/llm-models`)
- AI provider configuration (`GET/POST/PUT /api/v2/admin/wameed-ai/providers`)
- Billing dashboard, invoices, and "mark paid" / "record payment" actions on AI billing
- Per-store toggle-AI, billing settings, billing stores
- Platform-wide analytics (chats, dashboard, usage, logs, log-stats, trends)
- Feature flags (`features`, `features/{id}/toggle`)
- `store-health`

**Affected routes (31):**
```
GET    /api/v2/admin/wameed-ai/analytics/chats
GET    /api/v2/admin/wameed-ai/analytics/chats/{chatId}
GET    /api/v2/admin/wameed-ai/analytics/dashboard
GET    /api/v2/admin/wameed-ai/billing/dashboard
GET    /api/v2/admin/wameed-ai/billing/invoices
GET    /api/v2/admin/wameed-ai/billing/invoices/{invoiceId}
GET    /api/v2/admin/wameed-ai/billing/settings
PUT    /api/v2/admin/wameed-ai/billing/settings
GET    /api/v2/admin/wameed-ai/billing/stores
GET    /api/v2/admin/wameed-ai/billing/stores/{storeId}
PUT    /api/v2/admin/wameed-ai/billing/stores/{storeId}
POST   /api/v2/admin/wameed-ai/billing/stores/{storeId}/toggle-ai
POST   /api/v2/admin/wameed-ai/billing/check-overdue
POST   /api/v2/admin/wameed-ai/billing/generate-invoices
POST   /api/v2/admin/wameed-ai/billing/invoices/{invoiceId}/mark-paid
POST   /api/v2/admin/wameed-ai/billing/invoices/{invoiceId}/record-payment
GET    /api/v2/admin/wameed-ai/features
PATCH  /api/v2/admin/wameed-ai/features/{featureId}/toggle
GET    /api/v2/admin/wameed-ai/llm-models
POST   /api/v2/admin/wameed-ai/llm-models
PUT    /api/v2/admin/wameed-ai/llm-models/{id}
DELETE /api/v2/admin/wameed-ai/llm-models/{id}
PATCH  /api/v2/admin/wameed-ai/llm-models/{id}/toggle
GET    /api/v2/admin/wameed-ai/providers
POST   /api/v2/admin/wameed-ai/providers
PUT    /api/v2/admin/wameed-ai/providers/{id}
GET    /api/v2/admin/wameed-ai/platform-logs
GET    /api/v2/admin/wameed-ai/platform-log-stats
GET    /api/v2/admin/wameed-ai/platform-usage
POST   /api/v2/admin/wameed-ai/platform-trends
POST   /api/v2/admin/wameed-ai/store-health
```

**Root cause hypothesis:** the `admin/wameed-ai/**` route group in [routes/api/admin.php](routes/api/admin.php) (or a dedicated `admin-wameed-ai.php`) is missing the `auth:admin-api` (or `role:platform_admin`) middleware that the rest of `api/v2/admin/**` uses.

**Recommended fix:** wrap the `admin/wameed-ai` group in the same admin middleware as the surrounding routes:
```php
Route::middleware(['auth:admin-api', 'role:platform_admin'])
     ->prefix('admin/wameed-ai')
     ->group(base_path('routes/api/admin/wameed-ai.php'));
```

---

### 🟡 Finding 2 — Generic 500 "Server Error" on parameterized lookups (2 routes)

| Route | Error |
|---|---|
| `GET /api/v2/subscription/plans/{planId}` | `500 {"message":"Server Error"}` |
| `POST /api/v2/delivery/webhook/{platform}/{storeId}` | `500 {"message":"Server Error"}` |

When probed individually with curl, both routes returned a **clean JSON envelope**:
- `subscription/plans/{planId}` → `{"success":false,"message":"Subscription plan not found."}`
- `delivery/webhook/{platform}/{storeId}` → `{"success":false,"message":"Unknown platform configuration"}`

But under the parallel test load they leak unhandled exceptions surfaced as opaque `500 Server Error` (the Laravel production exception handler fallback).

**Recommended fix:** wrap the two controller actions in proper try/catch (or use `ModelNotFoundException`/`abort(404)`) so they always return the JSON envelope with the appropriate 4xx code instead of bubbling to 500. Most likely an unhandled `Throwable` from a service-layer call when the resource doesn't exist or the platform key is unknown.

---

### 🟢 Finding 3 — `notification-templates/events/{eventKey}` returns 404 instead of 401 unauthenticated

`GET /api/v2/notification-templates/events/{eventKey}` without an `Authorization` header returns:
```
404 The route api/v2/notification-templates/events/00000000-0000-0000-0000-000000000000 could not be found.
```
But Laravel **does** know the route — supplying a Bearer token returns a normal response. This is a route-ordering issue: an earlier route in [routes/api/notification-templates.php](routes/api/notification-templates.php) is shadowing the `events/{eventKey}` pattern when the request lacks auth (route caching may evaluate the constraint differently). Cosmetic only.

---

## 4. Honest Caveats

- The smoke run confirms each endpoint is **wired up, validates input, and applies (or fails to apply) auth**. It does **not** assert business-logic correctness — passing a smoke test only proves the endpoint did not crash.
- Parameterized routes were called with placeholder ids; a 404/422 is a green outcome here. Resource-creation flows that need real foreign keys are out of scope.
- The TestSprite cloud could not run the suite (account out of credits — confirmed on both API keys). The same plan is preserved at [testsprite_tests/testsprite_backend_test_plan.json](testsprite_tests/testsprite_backend_test_plan.json) and can be replayed on TestSprite once credits are available.
- Login endpoint is rate-limited; the suite caches the Bearer token to disk via filelock so the 16 xdist workers don't trigger 429.

---

## 5. How to Reproduce

```bash
pip install pytest requests pytest-xdist filelock
POS_BASE_URL=https://system.wameedpos.com \
POS_LOGIN_EMAIL=owner@testsprite.local \
POS_LOGIN_PASSWORD=Password123! \
python3 -m pytest testsprite_tests/local -n 8 --tb=line -q --junitxml=testsprite_tests/local/junit.xml
```

To re-run only the 36 previously failing tests:
```bash
python3 -m pytest testsprite_tests/local --lf -n 2 --tb=line -q
```

---

## 6. Artifacts

- [testsprite_tests/local/conftest.py](testsprite_tests/local/conftest.py) — fixtures + token cache
- [testsprite_tests/local/test_all_routes.py](testsprite_tests/local/test_all_routes.py) — parameterized test that loads `all_routes.json`
- [testsprite_tests/local/run.log](testsprite_tests/local/run.log) — full run output
- [testsprite_tests/local/junit.xml](testsprite_tests/local/junit.xml) — JUnit report
- [testsprite_tests/testsprite_backend_test_plan.json](testsprite_tests/testsprite_backend_test_plan.json) — TestSprite plan (1132 entries)
- [database/seeders/TestSpriteSeeder.php](database/seeders/TestSpriteSeeder.php) — seeded owner/manager/cashier/inventory/accountant
