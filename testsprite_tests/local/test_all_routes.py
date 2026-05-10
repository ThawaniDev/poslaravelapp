"""Auto-generated smoke suite for every v2 route in all_routes.json.

For each unique (method, uri) under api/v2/:
- Protected routes (sanctum/admin guard): call once unauthenticated and assert 401/403/419,
  then call again with the seeded owner Bearer token.
- Public routes: just call.
- Admin routes (api/v2/admin/*): with provider Sanctum token expect 401/403 (admin-api guard).
- Parameterized routes get placeholder ids.
- Write methods (POST/PUT/PATCH/DELETE) send {} body.
- A 5xx is always a hard failure.
"""
from __future__ import annotations

import json
import os
import pytest

ROUTES_FILE = os.path.normpath(
    os.path.join(os.path.dirname(__file__), "..", "..", "all_routes.json")
)
PLACEHOLDER_UUID = "00000000-0000-0000-0000-000000000000"
WRITE_METHODS = {"POST", "PUT", "PATCH", "DELETE"}


def _load_routes():
    with open(ROUTES_FILE) as f:
        raw = json.load(f)
    seen = set()
    out = []
    for r in raw:
        if not r["uri"].startswith("api/v2/"):
            continue
        key = (r["method"], r["uri"])
        if key in seen:
            continue
        seen.add(key)
        out.append(r)
    out.sort(key=lambda x: (x["uri"], x["method"]))
    return out


ROUTES = _load_routes()


def _fill_path_params(uri: str) -> str:
    out = []
    for part in uri.split("/"):
        if part.startswith("{") and part.endswith("}"):
            name = part[1:-1].rstrip("?")
            if name.endswith("Id") or name in {"id", "page", "perPage", "limit", "offset"}:
                out.append("1")
            elif name.endswith("Key") or name in {"key", "slug", "code", "platform"}:
                # routes often constrain these to lowercase letters/dots/underscores
                out.append("placeholder")
            else:
                out.append(PLACEHOLDER_UUID)
        else:
            out.append(part)
    return "/".join(out)


def _is_admin(r):
    return r["uri"].startswith("api/v2/admin/")


def _requires_auth(r):
    return any("sanctum" in m or "admin" in m.lower() for m in r["middleware"])


def _id_for(r):
    return f"{r['method']} {r['uri']}"


@pytest.mark.parametrize("route", ROUTES, ids=_id_for)
def test_route_smoke(http, base_url, auth_headers, timeout_s, route):
    method = route["method"]
    uri = _fill_path_params(route["uri"])
    url = f"{base_url}/{uri}"
    body = {} if method in WRITE_METHODS else None

    if _requires_auth(route):
        r0 = http.request(method, url, json=body, timeout=timeout_s)
        assert r0.status_code in (401, 403, 419), (
            f"{method} {route['uri']} without auth returned {r0.status_code} "
            f"(expected 401/403/419). Body: {r0.text[:200]}"
        )

    r1 = http.request(method, url, headers=auth_headers, json=body, timeout=timeout_s)

    if _is_admin(route):
        assert r1.status_code in (401, 403), (
            f"Admin route {method} {route['uri']} with provider token returned "
            f"{r1.status_code} (expected 401/403). Body: {r1.text[:200]}"
        )
    else:
        assert r1.status_code < 500, (
            f"{method} {route['uri']} (auth) returned {r1.status_code} server error. "
            f"Body: {r1.text[:300]}"
        )
