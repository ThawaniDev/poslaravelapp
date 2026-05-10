# Local pytest mirror of the TestSprite plan

Runs the same 1132-route smoke coverage as the TestSprite plan in
[../testsprite_backend_test_plan.json](../testsprite_backend_test_plan.json),
but executes locally with `pytest` (no TestSprite credits required).

## Setup

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install pytest requests pytest-xdist
```

Make sure the Laravel server is running:

```bash
php artisan serve   # serves http://localhost:8000
php artisan db:seed --class=TestSpriteSeeder --force   # if not already seeded
```

## Run

From the project root:

```bash
pytest testsprite_tests/local -q                        # serial
pytest testsprite_tests/local -q -n auto                # parallel (recommended)
pytest testsprite_tests/local -q --junitxml=report.xml  # CI-style report
```

Override defaults with env vars:

```bash
POS_BASE_URL=http://127.0.0.1:8000 \
POS_LOGIN_EMAIL=owner@testsprite.local \
POS_LOGIN_PASSWORD=Password123! \
pytest testsprite_tests/local -q
```

## What each test asserts

For every unique `(method, uri)` under `api/v2/*`:

1. **Protected routes** — unauthenticated call must return `401/403/419`.
2. **Authenticated call** with the seeded owner Bearer token must NOT return 5xx
   (4xx client errors like 404/422 are acceptable smoke results — the route is
   wired up).
3. **Admin routes (`api/v2/admin/*`)** — with a provider token, expect `401/403`
   (admin-api guard). Any 2xx here would indicate a guard misconfiguration.
4. **Parameterized routes** receive placeholder ids
   (`00000000-0000-0000-0000-000000000000` or `1`). 404/422 are normal.
5. **Write methods** send `{}` as body. 400/404/409/422 are accepted.

A 5xx response is always a hard failure.
