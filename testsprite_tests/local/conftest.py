"""Shared fixtures for the locally-run smoke suite generated from the TestSprite plan.

Token is cached on disk so xdist workers don't each hit the login endpoint
(which is rate-limited and returns 429 on bursts).
"""
import json
import os
import time

import pytest
import requests
from filelock import FileLock

BASE_URL = os.environ.get("POS_BASE_URL", "http://localhost:8000").rstrip("/")
LOGIN_EMAIL = os.environ.get("POS_LOGIN_EMAIL", "owner@testsprite.local")
LOGIN_PASSWORD = os.environ.get("POS_LOGIN_PASSWORD", "Password123!")
TIMEOUT = float(os.environ.get("POS_HTTP_TIMEOUT", "30"))
TOKEN_CACHE = os.path.join(os.path.dirname(__file__), ".token_cache.json")
TOKEN_TTL = 60 * 30  # 30 minutes


@pytest.fixture(scope="session")
def base_url():
    return BASE_URL


@pytest.fixture(scope="session")
def timeout_s():
    return TIMEOUT


@pytest.fixture(scope="session")
def http():
    s = requests.Session()
    s.headers.update({"Accept": "application/json", "Content-Type": "application/json"})
    return s


def _login(http):
    last = None
    for attempt in range(6):
        try:
            r = http.post(
                f"{BASE_URL}/api/v2/auth/login",
                json={"email": LOGIN_EMAIL, "password": LOGIN_PASSWORD},
                timeout=TIMEOUT,
            )
            if r.status_code == 200:
                token = (r.json().get("data") or {}).get("token")
                if token:
                    return token
            last = f"{r.status_code}: {r.text[:200]}"
            if r.status_code == 429:
                time.sleep(5 + attempt * 5)
                continue
        except Exception as e:  # noqa: BLE001
            last = repr(e)
            time.sleep(2)
    raise AssertionError(f"Login failed after retries: {last}")


@pytest.fixture(scope="session")
def bearer_token(http):
    lock = FileLock(TOKEN_CACHE + ".lock")
    with lock:
        if os.path.exists(TOKEN_CACHE):
            try:
                cached = json.load(open(TOKEN_CACHE))
                if (
                    cached.get("base_url") == BASE_URL
                    and cached.get("email") == LOGIN_EMAIL
                    and time.time() - cached.get("ts", 0) < TOKEN_TTL
                    and cached.get("token")
                ):
                    return cached["token"]
            except Exception:  # noqa: BLE001
                pass
        token = _login(http)
        json.dump(
            {"base_url": BASE_URL, "email": LOGIN_EMAIL, "ts": time.time(), "token": token},
            open(TOKEN_CACHE, "w"),
        )
        return token


@pytest.fixture(scope="session")
def auth_headers(bearer_token):
    return {
        "Authorization": f"Bearer {bearer_token}",
        "Accept": "application/json",
    }
