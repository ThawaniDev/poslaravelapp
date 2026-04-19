#!/usr/bin/env python3
"""
Comprehensive API Test Script for Wameed POS
=============================================
Tests all 1132+ API endpoints against the local Laravel server.
Logs in as both provider (owner) and admin user, then hits every route.

Usage:
    python3 test_all_apis.py [--base-url http://127.0.0.1:8001] [--category auth] [--method GET] [--summary-only] [--stop-on-error]

Requirements:
    pip3 install requests
"""

import requests
import json
import sys
import time
import argparse
import re
from datetime import datetime
from collections import defaultdict

# ─── Configuration ─────────────────────────────────────────────────────────────

DEFAULT_BASE_URL = "http://127.0.0.1:8001/api/v2"

# Provider (owner) credentials
PROVIDER_EMAIL = "owner@ostora.sa"
PROVIDER_PASSWORD = "password"

# Admin credentials
ADMIN_EMAIL = "dev@wameedpos.com"
ADMIN_PASSWORD = "Admin@2026"

# Placeholder IDs for parameterized routes
PLACEHOLDER_UUID = "00000000-0000-0000-0000-000000000001"
PLACEHOLDER_ID = "1"
PLACEHOLDER_SLUG = "test-slug"

# Fallback store ID (from seeders / known data)
FALLBACK_STORE_ID = "019d90a8-0571-725b-8191-7d38ceef0154"

# ─── Minimal request bodies for POST/PUT endpoints ────────────────────────────
# These are minimal payloads to avoid 422 validation errors where possible.
# Most endpoints will return 422 with empty bodies - that's fine, we just want
# to confirm the route exists and the controller responds.

SAMPLE_BODIES = {
    # Auth
    "POST api/v2/auth/login": {"email": PROVIDER_EMAIL, "password": PROVIDER_PASSWORD},
    "POST api/v2/auth/login/pin": {"store_id": PLACEHOLDER_UUID, "pin": "1111"},
    "POST api/v2/auth/register": {"name": "Test", "email": "test-api-script@test.com", "password": "Test@12345", "password_confirmation": "Test@12345", "phone": "+96899999999"},
    "POST api/v2/auth/otp/send": {"phone": "+96891234567"},
    "POST api/v2/auth/otp/verify": {"phone": "+96891234567", "code": "000000"},
    # Sync
    "POST api/v2/sync/push": {"changes": []},
    "POST api/v2/sync/heartbeat": {},
    # Notifications
    "POST api/v2/notifications/fcm-tokens": {"token": "test-fcm-token", "device_type": "android"},
    "POST api/v2/notifications/read-all": {},
    # Settings
    "PUT api/v2/settings": {"key": "test", "value": "test"},
}

# ─── Color helpers ─────────────────────────────────────────────────────────────

class Colors:
    GREEN = "\033[92m"
    RED = "\033[91m"
    YELLOW = "\033[93m"
    BLUE = "\033[94m"
    CYAN = "\033[96m"
    BOLD = "\033[1m"
    DIM = "\033[2m"
    RESET = "\033[0m"

def color_status(code):
    if code < 300:
        return f"{Colors.GREEN}{code}{Colors.RESET}"
    elif code < 400:
        return f"{Colors.BLUE}{code}{Colors.RESET}"
    elif code == 401:
        return f"{Colors.YELLOW}{code}{Colors.RESET}"
    elif code == 403:
        return f"{Colors.YELLOW}{code}{Colors.RESET}"
    elif code == 404:
        return f"{Colors.RED}{code}{Colors.RESET}"
    elif code == 405:
        return f"{Colors.RED}{code} METHOD NOT ALLOWED{Colors.RESET}"
    elif code == 422:
        return f"{Colors.YELLOW}{code}{Colors.RESET}"
    elif code == 429:
        return f"{Colors.YELLOW}{code} THROTTLED{Colors.RESET}"
    elif code >= 500:
        return f"{Colors.RED}{Colors.BOLD}{code} SERVER ERROR{Colors.RESET}"
    else:
        return f"{Colors.RED}{code}{Colors.RESET}"

# ─── Route definitions ────────────────────────────────────────────────────────
# All routes exported from `php artisan route:list --path=api/v2 --json`

ALL_ROUTES = None  # Will be loaded from all_routes.json or defined inline

def load_routes():
    """Load routes from the JSON export."""
    global ALL_ROUTES
    import os
    json_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "all_routes.json")
    if os.path.exists(json_path):
        with open(json_path) as f:
            ALL_ROUTES = json.load(f)
        print(f"{Colors.GREEN}Loaded {len(ALL_ROUTES)} routes from all_routes.json{Colors.RESET}")
    else:
        print(f"{Colors.RED}ERROR: all_routes.json not found. Run:{Colors.RESET}")
        print(f"  php artisan route:list --path=api/v2 --json > all_routes.json")
        sys.exit(1)

def resolve_uri(uri):
    """Replace route parameters with placeholder values."""
    # Replace {param} with placeholder values
    resolved = uri
    # UUID-like params
    resolved = re.sub(r'\{[a-zA-Z]*[Ii]d\}', PLACEHOLDER_UUID, resolved)
    resolved = re.sub(r'\{id\}', PLACEHOLDER_UUID, resolved)
    # Slug params
    resolved = re.sub(r'\{slug\}', PLACEHOLDER_SLUG, resolved)
    resolved = re.sub(r'\{[a-zA-Z]*[Ss]lug\}', PLACEHOLDER_SLUG, resolved)
    # Version params
    resolved = re.sub(r'\{version\}', '1.0.0', resolved)
    # Platform/provider params
    resolved = re.sub(r'\{platform\}', 'android', resolved)
    resolved = re.sub(r'\{provider\}', 'tabby', resolved)
    # Key params
    resolved = re.sub(r'\{key\}', 'test-key', resolved)
    # Any remaining params
    resolved = re.sub(r'\{[a-zA-Z_]+\}', PLACEHOLDER_UUID, resolved)
    return resolved

def get_category(uri):
    """Extract the category from a URI."""
    path = uri.replace("api/v2/", "")
    parts = path.split("/")
    if parts[0] == "admin" and len(parts) > 1:
        return f"admin/{parts[1]}"
    return parts[0]

def is_admin_route(route):
    """Check if a route requires admin auth."""
    return any("admin-api" in m for m in route.get("middleware", []))

def is_public_route(route):
    """Check if a route requires no auth."""
    return not any("Authenticate" in m for m in route.get("middleware", []))

# ─── API Client ────────────────────────────────────────────────────────────────

class APITester:
    def __init__(self, base_url, summary_only=False, stop_on_error=False):
        self.base_url = base_url.rstrip("/")
        self.session = requests.Session()
        self.session.headers.update({
            "Accept": "application/json",
            "Content-Type": "application/json",
        })
        self.provider_token = None
        self.admin_token = None
        self.store_id = None
        self.summary_only = summary_only
        self.stop_on_error = stop_on_error

        # Results tracking
        self.results = []
        self.status_counts = defaultdict(int)
        self.category_results = defaultdict(lambda: {"pass": 0, "fail": 0, "skip": 0})
        self.errors_500 = []
        self.errors_connection = []
        self.start_time = None

    def login_provider(self):
        """Login as provider (owner) and get token."""
        print(f"\n{Colors.BOLD}{'='*80}{Colors.RESET}")
        print(f"{Colors.BOLD}  STEP 1: Login as Provider ({PROVIDER_EMAIL}){Colors.RESET}")
        print(f"{Colors.BOLD}{'='*80}{Colors.RESET}")
        try:
            resp = self.session.post(f"{self.base_url}/auth/login", json={
                "email": PROVIDER_EMAIL,
                "password": PROVIDER_PASSWORD,
                "device_id": "api-test-script",
                "device_name": "API Test Script",
            })
            if resp.status_code == 200:
                data = resp.json()
                self.provider_token = data.get("data", {}).get("token")
                # Try to get store_id from user data
                user = data.get("data", {}).get("user", {})
                self.store_id = user.get("store_id")
                stores = user.get("stores", [])
                if not self.store_id and stores:
                    self.store_id = stores[0].get("id")
                if self.store_id:
                    print(f"  {Colors.GREEN}✓ Provider login OK — Token: {self.provider_token[:30]}...{Colors.RESET}")
                    print(f"  {Colors.GREEN}  Store ID: {self.store_id}{Colors.RESET}")
                else:
                    self.store_id = FALLBACK_STORE_ID
                    print(f"  {Colors.GREEN}✓ Provider login OK — Token: {self.provider_token[:30]}...{Colors.RESET}")
                    print(f"  {Colors.YELLOW}  Using fallback Store ID: {self.store_id}{Colors.RESET}")
                return True
            else:
                print(f"  {Colors.RED}✗ Provider login FAILED — Status: {resp.status_code}{Colors.RESET}")
                print(f"  {Colors.DIM}{resp.text[:500]}{Colors.RESET}")
                return False
        except requests.ConnectionError:
            print(f"  {Colors.RED}✗ Cannot connect to {self.base_url}{Colors.RESET}")
            print(f"  {Colors.RED}  Make sure Laravel is running: php artisan serve{Colors.RESET}")
            return False

    def login_admin(self):
        """Login as admin and get token via tinker-created token or direct API."""
        print(f"\n{Colors.BOLD}{'='*80}{Colors.RESET}")
        print(f"{Colors.BOLD}  STEP 2: Get Admin Token{Colors.RESET}")
        print(f"{Colors.BOLD}{'='*80}{Colors.RESET}")
        # Admin doesn't have an API login endpoint — uses Filament.
        # We'll create a token via artisan tinker command.
        import subprocess
        try:
            result = subprocess.run(
                ["php", "artisan", "tinker", "--execute",
                 "echo App\\Domain\\AdminPanel\\Models\\AdminUser::where('email','dev@wameedpos.com')->first()?->createToken('api-test')->plainTextToken ?? 'NO_ADMIN_USER';"],
                capture_output=True, text=True, timeout=30,
                cwd=self.base_url.replace("/api/v2", "").replace("http://127.0.0.1:8001", ".")
                    if "127.0.0.1" in self.base_url else "."
            )
            token = result.stdout.strip()
            if token and token != "NO_ADMIN_USER" and "|" in token:
                self.admin_token = token
                print(f"  {Colors.GREEN}✓ Admin token created: {token[:30]}...{Colors.RESET}")
                return True
            else:
                print(f"  {Colors.YELLOW}⚠ Could not create admin token (output: {token[:100]}){Colors.RESET}")
                print(f"  {Colors.YELLOW}  Admin routes will be tested with provider token (expect 401/403){Colors.RESET}")
                return False
        except Exception as e:
            print(f"  {Colors.YELLOW}⚠ Could not create admin token: {e}{Colors.RESET}")
            print(f"  {Colors.YELLOW}  Admin routes will be tested with provider token (expect 401/403){Colors.RESET}")
            return False

    def re_login_provider(self):
        """Re-login if token was invalidated (e.g. by testing logout endpoint)."""
        try:
            resp = self.session.get(f"{self.base_url}/auth/me",
                                    headers={"Authorization": f"Bearer {self.provider_token}"},
                                    timeout=10)
            if resp.status_code == 401:
                resp2 = self.session.post(f"{self.base_url}/auth/login", json={
                    "email": PROVIDER_EMAIL, "password": PROVIDER_PASSWORD,
                    "device_id": "api-test-re", "device_name": "API Re-login",
                })
                if resp2.status_code == 200:
                    data = resp2.json()
                    self.provider_token = data.get("data", {}).get("token")
        except Exception:
            pass

    def make_request(self, method, uri, route):
        """Make a single API request and return result dict."""
        resolved = resolve_uri(uri)
        url = f"{self.base_url}/{resolved.replace('api/v2/', '')}"
        category = get_category(uri)

        # Choose the right token
        headers = {}
        if is_admin_route(route):
            if self.admin_token:
                headers["Authorization"] = f"Bearer {self.admin_token}"
            elif self.provider_token:
                headers["Authorization"] = f"Bearer {self.provider_token}"
        elif not is_public_route(route):
            if self.provider_token:
                headers["Authorization"] = f"Bearer {self.provider_token}"

        # Add store context
        if self.store_id and not is_admin_route(route):
            headers["X-Store-Id"] = self.store_id

        # Determine request body
        body_key = f"{method} {uri}"
        body = SAMPLE_BODIES.get(body_key, {})

        try:
            if method == "GET":
                resp = self.session.get(url, headers=headers, timeout=30)
            elif method == "POST":
                resp = self.session.post(url, headers=headers, json=body, timeout=30)
            elif method == "PUT":
                resp = self.session.put(url, headers=headers, json=body, timeout=30)
            elif method == "PATCH":
                resp = self.session.patch(url, headers=headers, json=body, timeout=30)
            elif method == "DELETE":
                resp = self.session.delete(url, headers=headers, timeout=30)
            else:
                return {"method": method, "uri": uri, "url": url, "status": "SKIP",
                        "body": f"Unknown method: {method}", "category": category, "time_ms": 0}

            # Truncate response body for display
            try:
                resp_body = resp.json()
                resp_text = json.dumps(resp_body, ensure_ascii=False)
            except Exception:
                resp_text = resp.text

            if len(resp_text) > 500:
                resp_text = resp_text[:500] + "..."

            return {
                "method": method,
                "uri": uri,
                "url": url,
                "status": resp.status_code,
                "body": resp_text,
                "category": category,
                "time_ms": resp.elapsed.total_seconds() * 1000,
            }

        except requests.ConnectionError:
            return {"method": method, "uri": uri, "url": url, "status": "CONN_ERR",
                    "body": "Connection refused", "category": category, "time_ms": 0}
        except requests.Timeout:
            return {"method": method, "uri": uri, "url": url, "status": "TIMEOUT",
                    "body": "Request timed out (30s)", "category": category, "time_ms": 30000}
        except Exception as e:
            return {"method": method, "uri": uri, "url": url, "status": "ERROR",
                    "body": str(e)[:300], "category": category, "time_ms": 0}

    def test_route(self, index, total, route):
        """Test a single route and print results."""
        method = route["method"].split("|")[0]
        if method == "HEAD":
            method = route["method"].split("|")[1] if "|" in route["method"] else "GET"
        uri = route["uri"]
        category = get_category(uri)

        result = self.make_request(method, uri, route)
        self.results.append(result)

        status = result["status"]
        self.status_counts[status] += 1

        # Categorize
        if isinstance(status, int):
            if status < 500:
                self.category_results[category]["pass"] += 1
            else:
                self.category_results[category]["fail"] += 1
                self.errors_500.append(result)
        elif status == "CONN_ERR":
            self.category_results[category]["fail"] += 1
            self.errors_connection.append(result)
        else:
            self.category_results[category]["skip"] += 1

        # Print result
        if not self.summary_only:
            status_str = color_status(status) if isinstance(status, int) else f"{Colors.RED}{status}{Colors.RESET}"
            time_str = f"{result['time_ms']:.0f}ms"
            progress = f"[{index}/{total}]"
            print(f"  {Colors.DIM}{progress:>10}{Colors.RESET} {method:6} {status_str:>20}  {time_str:>8}  {uri}")

            # Show response body for errors
            if isinstance(status, int) and status >= 500:
                print(f"           {Colors.RED}Response: {result['body'][:200]}{Colors.RESET}")
        else:
            # Print dots for progress
            if isinstance(status, int) and status >= 500:
                print(f"{Colors.RED}F{Colors.RESET}", end="", flush=True)
            elif isinstance(status, int) and status < 300:
                print(f"{Colors.GREEN}.{Colors.RESET}", end="", flush=True)
            elif isinstance(status, int) and status in (401, 403):
                print(f"{Colors.YELLOW}a{Colors.RESET}", end="", flush=True)
            elif isinstance(status, int) and status == 404:
                print(f"{Colors.RED}4{Colors.RESET}", end="", flush=True)
            elif isinstance(status, int) and status == 422:
                print(f"{Colors.CYAN}v{Colors.RESET}", end="", flush=True)
            else:
                print(".", end="", flush=True)

            if index % 80 == 0:
                print(f" {index}/{total}")

        if self.stop_on_error and isinstance(status, int) and status >= 500:
            print(f"\n{Colors.RED}STOPPING: 500 error encountered (--stop-on-error){Colors.RESET}")
            return False

        return True

    def run_all(self, category_filter=None, method_filter=None):
        """Run all API tests."""
        routes = ALL_ROUTES

        # Apply filters
        if category_filter:
            routes = [r for r in routes if get_category(r["uri"]) == category_filter
                      or r["uri"].replace("api/v2/", "").startswith(category_filter)]
        if method_filter:
            routes = [r for r in routes if r["method"].startswith(method_filter.upper())]

        total = len(routes)
        print(f"\n{Colors.BOLD}{'='*80}{Colors.RESET}")
        print(f"{Colors.BOLD}  STEP 3: Testing {total} API Endpoints{Colors.RESET}")
        if category_filter:
            print(f"{Colors.BOLD}  Filter: category = {category_filter}{Colors.RESET}")
        if method_filter:
            print(f"{Colors.BOLD}  Filter: method = {method_filter}{Colors.RESET}")
        print(f"{Colors.BOLD}{'='*80}{Colors.RESET}\n")

        if not self.summary_only:
            print(f"  {'Progress':>10} {'Method':6} {'Status':>12}  {'Time':>8}  URI")
            print(f"  {'-'*10} {'-'*6} {'-'*12}  {'-'*8}  {'-'*40}")

        self.start_time = time.time()

        for i, route in enumerate(routes, 1):
            # Re-login if token was invalidated by logout tests
            if i % 20 == 0 and self.provider_token:
                self.re_login_provider()

            if not self.test_route(i, total, route):
                break

            # Small delay to avoid overwhelming the server
            if i % 50 == 0:
                time.sleep(0.1)

        elapsed = time.time() - self.start_time

        if self.summary_only:
            print()  # newline after dots

        self.print_summary(total, elapsed, category_filter)
        self.save_results()

    def print_summary(self, total, elapsed, category_filter=None):
        """Print test summary."""
        print(f"\n\n{Colors.BOLD}{'='*80}{Colors.RESET}")
        print(f"{Colors.BOLD}  TEST SUMMARY{Colors.RESET}")
        print(f"{Colors.BOLD}{'='*80}{Colors.RESET}")
        print(f"  Total routes tested:  {len(self.results)}")
        print(f"  Total time:           {elapsed:.1f}s")
        print(f"  Avg time per request: {(elapsed / max(len(self.results), 1) * 1000):.0f}ms")

        print(f"\n  {Colors.BOLD}Status Code Distribution:{Colors.RESET}")
        for status in sorted(self.status_counts.keys(), key=lambda x: str(x)):
            count = self.status_counts[status]
            pct = (count / max(len(self.results), 1)) * 100
            bar = "█" * int(pct / 2)
            if isinstance(status, int):
                status_display = color_status(status)
            else:
                status_display = f"{Colors.RED}{status}{Colors.RESET}"
            print(f"    {status_display:>25}  {count:>5}  ({pct:5.1f}%)  {bar}")

        # Success metrics
        success = sum(1 for r in self.results if isinstance(r["status"], int) and r["status"] < 300)
        auth_errors = sum(1 for r in self.results if isinstance(r["status"], int) and r["status"] in (401, 403))
        validation = sum(1 for r in self.results if isinstance(r["status"], int) and r["status"] == 422)
        not_found = sum(1 for r in self.results if isinstance(r["status"], int) and r["status"] == 404)
        server_errors = sum(1 for r in self.results if isinstance(r["status"], int) and r["status"] >= 500)

        print(f"\n  {Colors.BOLD}Result Breakdown:{Colors.RESET}")
        print(f"    {Colors.GREEN}✓ Success (2xx):       {success:>5}{Colors.RESET}")
        print(f"    {Colors.YELLOW}⚠ Auth errors (401/3): {auth_errors:>5}{Colors.RESET}  (expected for admin routes if no admin token)")
        print(f"    {Colors.CYAN}⚠ Validation (422):    {validation:>5}{Colors.RESET}  (expected for POST/PUT with empty body)")
        print(f"    {Colors.RED}✗ Not Found (404):     {not_found:>5}{Colors.RESET}")
        print(f"    {Colors.RED}✗ Server Error (5xx):  {server_errors:>5}{Colors.RESET}  ← INVESTIGATE THESE")

        # Category breakdown
        print(f"\n  {Colors.BOLD}Category Breakdown:{Colors.RESET}")
        print(f"    {'Category':<35} {'Pass':>6} {'Fail':>6} {'Skip':>6}")
        print(f"    {'-'*35} {'-'*6} {'-'*6} {'-'*6}")
        for cat in sorted(self.category_results.keys()):
            cr = self.category_results[cat]
            fail_color = Colors.RED if cr["fail"] > 0 else ""
            reset = Colors.RESET if cr["fail"] > 0 else ""
            print(f"    {cat:<35} {cr['pass']:>6} {fail_color}{cr['fail']:>6}{reset} {cr['skip']:>6}")

        # Detailed 500 errors
        if self.errors_500:
            print(f"\n  {Colors.RED}{Colors.BOLD}━━━ SERVER ERRORS (5xx) — NEED INVESTIGATION ━━━{Colors.RESET}")
            for err in self.errors_500:
                print(f"    {err['method']:6} {err['uri']}")
                print(f"           Status: {err['status']}  Response: {err['body'][:300]}")
                print()

        # Connection errors
        if self.errors_connection:
            print(f"\n  {Colors.RED}{Colors.BOLD}━━━ CONNECTION ERRORS ━━━{Colors.RESET}")
            for err in self.errors_connection:
                print(f"    {err['method']:6} {err['uri']}")

        print(f"\n{Colors.BOLD}{'='*80}{Colors.RESET}")

    def save_results(self):
        """Save detailed results to JSON file."""
        import os
        output_path = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                                   f"api_test_results_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json")
        report = {
            "timestamp": datetime.now().isoformat(),
            "base_url": self.base_url,
            "total_routes": len(self.results),
            "status_counts": {str(k): v for k, v in self.status_counts.items()},
            "results": self.results,
            "errors_500": self.errors_500,
        }
        with open(output_path, "w") as f:
            json.dump(report, f, indent=2, ensure_ascii=False)
        print(f"  {Colors.GREEN}Results saved to: {output_path}{Colors.RESET}")

        # Also save a quick CSV summary
        csv_path = output_path.replace(".json", ".csv")
        with open(csv_path, "w") as f:
            f.write("method,uri,status,time_ms,category,response_snippet\n")
            for r in self.results:
                body_snippet = r["body"][:100].replace('"', "'").replace(",", ";").replace("\n", " ") if r["body"] else ""
                f.write(f'{r["method"]},{r["uri"]},{r["status"]},{r["time_ms"]:.0f},{r["category"]},"{body_snippet}"\n')
        print(f"  {Colors.GREEN}CSV saved to:     {csv_path}{Colors.RESET}")
        print(f"{Colors.BOLD}{'='*80}{Colors.RESET}\n")


# ─── Main ──────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description="Comprehensive API tester for Wameed POS")
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="API base URL")
    parser.add_argument("--category", help="Filter by category (e.g. 'auth', 'catalog', 'admin/plans')")
    parser.add_argument("--method", help="Filter by HTTP method (GET, POST, PUT, DELETE)")
    parser.add_argument("--summary-only", action="store_true", help="Show dots instead of per-route output")
    parser.add_argument("--stop-on-error", action="store_true", help="Stop on first 500 error")
    parser.add_argument("--skip-admin", action="store_true", help="Skip admin routes entirely")
    parser.add_argument("--laravel-path", default="/Users/dogorshom/Desktop/Thawani/thawani/POS/poslaravelapp",
                        help="Path to Laravel project (for tinker admin token)")
    args = parser.parse_args()

    print(f"""
{Colors.BOLD}╔══════════════════════════════════════════════════════════════════════════════╗
║                    WAMEED POS — COMPREHENSIVE API TESTER                     ║
║                                                                              ║
║  Tests all {len(ALL_ROUTES) if ALL_ROUTES else '???'} API endpoints against the local Laravel server             ║
║  Base URL: {args.base_url:<62} ║
╚══════════════════════════════════════════════════════════════════════════════╝{Colors.RESET}
""")

    # Load routes
    load_routes()

    if args.skip_admin:
        original = len(ALL_ROUTES)
        ALL_ROUTES[:] = [r for r in ALL_ROUTES if not r["uri"].startswith("api/v2/admin")]
        print(f"  {Colors.YELLOW}Skipping admin routes: {original} → {len(ALL_ROUTES)} routes{Colors.RESET}")

    tester = APITester(args.base_url, summary_only=args.summary_only, stop_on_error=args.stop_on_error)

    # Step 1: Login as provider
    if not tester.login_provider():
        print(f"\n{Colors.RED}Cannot proceed without provider login. Check server and credentials.{Colors.RESET}")
        sys.exit(1)

    # Step 2: Get admin token
    import subprocess, os
    os.chdir(args.laravel_path)
    tester.login_admin()

    # Step 3: Test all routes
    tester.run_all(category_filter=args.category, method_filter=args.method)


if __name__ == "__main__":
    load_routes()
    main()
