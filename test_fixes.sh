#!/bin/bash
# Quick test of previously-failing endpoints
BASE="http://127.0.0.1:8001/api/v2"

echo "=== Getting provider token ==="
TOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"email":"owner@ostora.sa","password":"password"}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('token',''))" 2>/dev/null)

if [ -z "$TOKEN" ]; then
  echo "FAILED: Could not get provider token"
  exit 1
fi
echo "Token: ${TOKEN:0:30}..."

echo ""
echo "=== Getting admin token via tinker ==="
ADMIN_TOKEN=$(php artisan tinker --execute="echo App\Domain\AdminPanel\Models\AdminUser::where('email','dev@wameedpos.com')->first()?->createToken('api-test')->plainTextToken ?? 'NO_ADMIN_USER';" 2>/dev/null | tr -d '\n')
echo "Admin token: ${ADMIN_TOKEN:0:30}..."

PASS=0
FAIL=0
TOTAL=0

test_endpoint() {
  local METHOD="$1"
  local URL="$2"
  local AUTH="$3"
  local BODY="$4"
  local DESC="$5"
  
  TOTAL=$((TOTAL+1))
  
  if [ "$AUTH" = "admin" ]; then
    BEARER="$ADMIN_TOKEN"
  else
    BEARER="$TOKEN"
  fi
  
  if [ "$METHOD" = "GET" ]; then
    STATUS=$(curl -s -o /tmp/api_resp.txt -w "%{http_code}" -X GET "$BASE$URL" \
      -H "Authorization: Bearer $BEARER" -H "Accept: application/json")
  elif [ "$METHOD" = "POST" ]; then
    STATUS=$(curl -s -o /tmp/api_resp.txt -w "%{http_code}" -X POST "$BASE$URL" \
      -H "Authorization: Bearer $BEARER" -H "Accept: application/json" \
      -H "Content-Type: application/json" -d "$BODY")
  elif [ "$METHOD" = "PUT" ]; then
    STATUS=$(curl -s -o /tmp/api_resp.txt -w "%{http_code}" -X PUT "$BASE$URL" \
      -H "Authorization: Bearer $BEARER" -H "Accept: application/json" \
      -H "Content-Type: application/json" -d "$BODY")
  elif [ "$METHOD" = "DELETE" ]; then
    STATUS=$(curl -s -o /tmp/api_resp.txt -w "%{http_code}" -X DELETE "$BASE$URL" \
      -H "Authorization: Bearer $BEARER" -H "Accept: application/json")
  fi
  
  if [ "$STATUS" -ge 500 ]; then
    FAIL=$((FAIL+1))
    echo "  FAIL [$STATUS] $METHOD $URL - $DESC"
    head -c 200 /tmp/api_resp.txt 2>/dev/null
    echo ""
  else
    PASS=$((PASS+1))
    echo "  OK   [$STATUS] $METHOD $URL - $DESC"
  fi
}

echo ""
echo "============================================================"
echo "  Testing previously-failing endpoints (56 errors)"
echo "============================================================"

# 1. Enum endpoints - accounting export status
echo ""
echo "--- Enum-related endpoints ---"
test_endpoint GET "/accounting/exports" "provider" "" "AccountingExportStatus enum"
test_endpoint GET "/finance/expenses" "provider" "" "ExpenseCategory enum"
test_endpoint GET "/gift-cards/transactions" "provider" "" "GiftCardTransactionType enum"
test_endpoint GET "/backups" "provider" "" "BackupType enum"
test_endpoint GET "/sync/status" "provider" "" "SyncDirection enum"
test_endpoint GET "/payment-methods" "provider" "" "PaymentMethodCategory enum"
test_endpoint GET "/labels" "provider" "" "LabelType enum"

# 2. Industry model endpoints (datetime cast)
echo ""
echo "--- Industry model datetime endpoints ---"
test_endpoint GET "/trade-ins" "provider" "" "TradeInRecord datetime"
test_endpoint GET "/flower-arrangements" "provider" "" "FlowerArrangement datetime"
test_endpoint GET "/flower-subscriptions" "provider" "" "FlowerSubscription datetime"
test_endpoint GET "/buyback-transactions" "provider" "" "BuybackTransaction datetime"
test_endpoint GET "/daily-metal-rates" "provider" "" "DailyMetalRate datetime"
test_endpoint GET "/prescriptions" "provider" "" "Prescription datetime"
test_endpoint GET "/reservations" "provider" "" "TableReservation datetime"
test_endpoint GET "/tables" "provider" "" "RestaurantTable datetime"
test_endpoint GET "/flower-freshness-logs" "provider" "" "FlowerFreshnessLog resource"

# 3. Admin controller endpoints (type hints)
echo ""
echo "--- Admin controller type hint endpoints ---"
test_endpoint GET "/roles" "provider" "" "RoleController list"
test_endpoint GET "/roles/test-uuid" "provider" "" "RoleController show (UUID)"

# 4. Analytics endpoints (JULIANDAY + column fixes)
echo ""
echo "--- Analytics endpoints ---"
test_endpoint GET "/admin/analytics/revenue-by-plan" "admin" "" "AnalyticsReporting revenue"
test_endpoint GET "/admin/analytics/plan-stats-over-time" "admin" "" "AnalyticsReporting planStats"
test_endpoint GET "/admin/analytics/feature-adoption" "admin" "" "AnalyticsReporting featureAdoption"
test_endpoint GET "/admin/analytics/churn" "admin" "" "AnalyticsReporting churn"
test_endpoint GET "/admin/analytics/plan-stats" "admin" "" "AnalyticsReporting listPlanStats"
test_endpoint GET "/admin/analytics/feature-stats" "admin" "" "AnalyticsReporting featureStats"

# 5. Financial operations
echo ""
echo "--- Financial operations ---"
test_endpoint GET "/cash-sessions" "provider" "" "CashSession latest()"

# 6. Announcements
echo ""
echo "--- Announcements ---"
test_endpoint POST "/announcements/nonexistent-id/dismiss" "provider" "" "Announcement dismiss (null check)"

# 7. WameedAI
echo ""
echo "--- WameedAI ---"
test_endpoint GET "/admin/ai/store-health" "admin" "" "WameedAI storeHealth"
test_endpoint GET "/admin/ai/platform-trends" "admin" "" "WameedAI platformTrends"

# 8. CMS Pages (new table)
echo ""
echo "--- CMS Pages (new table) ---"
test_endpoint GET "/admin/cms/pages" "admin" "" "CMS Pages list"

# 9. Platform event logs (new table)
echo ""
echo "--- Platform event logs (new table) ---"
test_endpoint GET "/admin/platform/events" "admin" "" "Platform event logs"

# 10. Infrastructure
echo ""
echo "--- Infrastructure ---"
test_endpoint GET "/admin/infrastructure/failed-jobs" "admin" "" "Infrastructure failed jobs"

# 11. Localization
echo ""
echo "--- Localization ---"
test_endpoint GET "/admin/localization/versions" "admin" "" "Localization versions"

# 12. Custom cake / bakery
echo ""
echo "--- Industry enums ---"
test_endpoint GET "/custom-cakes" "provider" "" "CustomCakeOrderStatus"
test_endpoint GET "/production-schedules" "provider" "" "ProductionScheduleStatus"
test_endpoint GET "/repairs" "provider" "" "RepairJobStatus"
test_endpoint GET "/kitchen-tickets" "provider" "" "KitchenTicketStatus"

# 13. Jewelry
echo ""
echo "--- Jewelry ---"
test_endpoint GET "/jewelry/product-details" "provider" "" "JewelryProductDetail"

echo ""
echo "============================================================"
echo "  RESULTS: $PASS passed, $FAIL failed (out of $TOTAL)"
echo "============================================================"
if [ "$FAIL" -eq 0 ]; then
  echo "  ALL TESTS PASSED!"
else
  echo "  $FAIL endpoints still returning 500 errors"
fi
