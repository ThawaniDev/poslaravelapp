<?php

namespace App\Domain\WameedAI\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds rich, business-aware context that gets injected into every Wameed AI
 * system prompt. Supports both single-store and organization-wide scopes so
 * org-level users (no specific store selected) still get a comprehensive
 * picture aggregated across every store they have access to.
 */
class AIStoreDataService
{
    // ─────────────────────────────────────────────────────────────────
    //  Single-store entrypoints (kept for backward compatibility)
    // ─────────────────────────────────────────────────────────────────

    public function getStoreContext(string $storeId, string $organizationId): array
    {
        return $this->buildContext($organizationId, [$storeId], $storeId);
    }

    public function buildStoreContextPrompt(string $storeId, string $organizationId): string
    {
        return $this->buildPrompt($this->getStoreContext($storeId, $organizationId));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Organization-scope entrypoints (all stores aggregated)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build context aggregated across every store in the organization (or the
     * given subset of accessible store IDs).
     */
    public function getOrganizationContext(string $organizationId, ?array $storeIds = null): array
    {
        if ($storeIds === null || empty($storeIds)) {
            $storeIds = DB::table('stores')
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        return $this->buildContext($organizationId, $storeIds, null);
    }

    public function buildOrganizationContextPrompt(string $organizationId, ?array $storeIds = null): string
    {
        return $this->buildPrompt($this->getOrganizationContext($organizationId, $storeIds));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Core context builder
    // ─────────────────────────────────────────────────────────────────

    /**
     * @param array<string> $storeIds Stores to scope every aggregate to.
     */
    private function buildContext(string $organizationId, array $storeIds, ?string $primaryStoreId): array
    {
        $tz = $this->resolveTimezone($organizationId, $primaryStoreId);
        $now = Carbon::now($tz);

        $todayStart = $now->copy()->startOfDay()->utc()->toDateTimeString();
        $todayEnd = $now->copy()->endOfDay()->utc()->toDateTimeString();
        $yesterdayStart = $now->copy()->subDay()->startOfDay()->utc()->toDateTimeString();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay()->utc()->toDateTimeString();
        $sevenDaysAgo = $now->copy()->subDays(7)->utc()->toDateTimeString();
        $thirtyDaysAgo = $now->copy()->subDays(30)->utc()->toDateTimeString();
        $monthStart = $now->copy()->startOfMonth()->utc()->toDateTimeString();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->utc()->toDateTimeString();
        $lastMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth()->utc()->toDateTimeString();
        $yearStart = $now->copy()->startOfYear()->utc()->toDateTimeString();
        $lastYearStart = $now->copy()->subYearNoOverflow()->startOfYear()->utc()->toDateTimeString();
        $lastYearEnd = $now->copy()->subYearNoOverflow()->endOfYear()->utc()->toDateTimeString();
        $thirtyDaysFromNow = $now->copy()->addDays(30)->toDateString();
        $todayDate = $now->toDateString();

        // ── Org / store profile ─────────────────────────────────────
        $org = DB::selectOne("SELECT name, business_type FROM organizations WHERE id = ?", [$organizationId]);
        $orgName = $org->name ?? 'Unknown Organization';
        $businessType = $org->business_type ?? 'retail';

        $primaryStore = null;
        if ($primaryStoreId) {
            $primaryStore = DB::selectOne(
                "SELECT name, name_ar, currency, city, timezone, is_main_branch FROM stores WHERE id = ?",
                [$primaryStoreId]
            );
        }
        // Currency falls back to any store in the org, then a sane default.
        $currency = $primaryStore->currency
            ?? DB::table('stores')->where('organization_id', $organizationId)->value('currency')
            ?? 'SAR';
        $displayName = $primaryStore->name ?? $orgName;

        $stores = empty($storeIds)
            ? collect()
            : DB::table('stores')
                ->whereIn('id', $storeIds)
                ->get(['id', 'name', 'city', 'currency', 'is_active', 'is_main_branch']);

        $branchCount = (int) DB::table('stores')->where('organization_id', $organizationId)->count();
        $activeBranchCount = $stores->where('is_active', true)->count();

        // ── Sales aggregates per period ─────────────────────────────
        $today = $this->salesAgg($storeIds, $todayStart, $todayEnd);
        $yesterday = $this->salesAgg($storeIds, $yesterdayStart, $yesterdayEnd);
        $last7d = $this->salesAgg($storeIds, $sevenDaysAgo, null);
        $last30d = $this->salesAgg($storeIds, $thirtyDaysAgo, null);
        $mtd = $this->salesAgg($storeIds, $monthStart, null);
        $lastMonth = $this->salesAgg($storeIds, $lastMonthStart, $lastMonthEnd);
        $ytd = $this->salesAgg($storeIds, $yearStart, null);
        $lastYear = $this->salesAgg($storeIds, $lastYearStart, $lastYearEnd);

        // ── Daily series (last 14 days) ─────────────────────────────
        $dailySeries = $this->dailySeries($storeIds, $now->copy()->subDays(13));

        // ── Monthly series (last 12 months) ─────────────────────────
        $monthlySeries = $this->monthlySeries($storeIds, $now->copy()->subMonthsNoOverflow(11)->startOfMonth());

        // ── Top products / categories ───────────────────────────────
        $topProducts = $this->topProducts($storeIds, $thirtyDaysAgo, 10);
        $topCategories = $this->topCategories($storeIds, $thirtyDaysAgo, 8);

        // ── Per-store breakdown (only meaningful for org-scope) ─────
        $perStoreBreakdown = $this->perStoreBreakdown($storeIds, $thirtyDaysAgo);

        // ── Inventory snapshot ──────────────────────────────────────
        $inventory = $this->inventorySnapshot($storeIds);
        $expiringCount = $this->expiringCount($storeIds, $todayDate, $thirtyDaysFromNow);
        $lowStockSamples = $this->lowStockSamples($storeIds, 10);

        // ── Catalog ─────────────────────────────────────────────────
        $catalog = DB::selectOne("
            SELECT COUNT(*) AS total_products,
                   COALESCE(AVG(sell_price), 0) AS avg_price,
                   COUNT(CASE WHEN is_active = false THEN 1 END) AS inactive_count
            FROM products WHERE organization_id = ?
        ", [$organizationId]);

        $categories = DB::select("
            SELECT name FROM categories
            WHERE organization_id = ? AND is_active = true AND parent_id IS NULL
            ORDER BY sort_order LIMIT 25
        ", [$organizationId]);
        $categoryNames = implode(', ', array_map(fn ($c) => $c->name, $categories));

        // ── Customers ───────────────────────────────────────────────
        $customers = DB::selectOne("
            SELECT COUNT(*) AS total_customers,
                   COALESCE(SUM(total_spend), 0) AS lifetime_spend,
                   COALESCE(AVG(visit_count), 0) AS avg_visits,
                   COUNT(CASE WHEN last_visit_at >= ? THEN 1 END) AS active_30d
            FROM customers WHERE organization_id = ?
        ", [$thirtyDaysAgo, $organizationId]);

        $topCustomers = DB::select("
            SELECT name, total_spend, visit_count
            FROM customers
            WHERE organization_id = ? AND total_spend > 0
            ORDER BY total_spend DESC LIMIT 5
        ", [$organizationId]);

        // ── Staff ───────────────────────────────────────────────────
        $staffCount = empty($storeIds) ? 0 : (int) DB::table('staff_users')
            ->whereIn('store_id', $storeIds)
            ->where('status', 'active')
            ->count();

        // ── Payments / expenses / promotions ────────────────────────
        $payments = $this->paymentsBreakdown($storeIds, $thirtyDaysAgo);

        $expenses = empty($storeIds) ? null : DB::table('expenses')
            ->whereIn('store_id', $storeIds)
            ->where('expense_date', '>=', $thirtyDaysAgo)
            ->selectRaw('COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt')
            ->first();

        $activePromos = (int) (DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM promotions
             WHERE organization_id = ? AND is_active = true AND (valid_to IS NULL OR valid_to >= ?)",
            [$organizationId, $todayDate]
        )->cnt ?? 0);

        // ── Returns / orders / sessions ─────────────────────────────
        $returns = $this->returnsAgg($storeIds, $thirtyDaysAgo);
        $orders = $this->ordersAgg($storeIds, $thirtyDaysAgo);
        $openSessions = empty($storeIds) ? 0 : (int) DB::table('pos_sessions')
            ->whereIn('store_id', $storeIds)
            ->whereNull('closed_at')
            ->count();

        $scope = $primaryStoreId ? 'store' : 'organization';

        return [
            'scope' => $scope,
            'display_name' => $displayName,
            'currency' => $currency,
            'timezone' => $tz,
            'now_local' => $now->toDateTimeString(),
            'organization_name' => $orgName,
            'business_type' => $businessType,
            'branch_count_total' => $branchCount,
            'branch_count_active_in_scope' => $activeBranchCount,
            'primary_store_city' => $primaryStore->city ?? null,
            'is_main_branch' => $primaryStore->is_main_branch ?? null,

            'sales_today' => $today,
            'sales_yesterday' => $yesterday,
            'sales_last_7d' => $last7d,
            'sales_last_30d' => $last30d,
            'sales_month_to_date' => $mtd,
            'sales_last_month' => $lastMonth,
            'sales_year_to_date' => $ytd,
            'sales_last_year' => $lastYear,

            'daily_series_14d' => $dailySeries,
            'monthly_series_12m' => $monthlySeries,

            'top_products_30d' => $topProducts,
            'top_categories_30d' => $topCategories,
            'per_store_breakdown_30d' => $perStoreBreakdown,

            'inventory' => $inventory + ['expiring_within_30d' => $expiringCount],
            'low_stock_samples' => $lowStockSamples,

            'catalog' => [
                'total_products' => (int) ($catalog->total_products ?? 0),
                'inactive_count' => (int) ($catalog->inactive_count ?? 0),
                'avg_price' => round((float) ($catalog->avg_price ?? 0), 2),
                'top_level_categories' => $categoryNames,
            ],

            'customers' => [
                'total' => (int) ($customers->total_customers ?? 0),
                'active_last_30d' => (int) ($customers->active_30d ?? 0),
                'lifetime_spend' => round((float) ($customers->lifetime_spend ?? 0), 2),
                'avg_visits' => round((float) ($customers->avg_visits ?? 0), 1),
                'top' => array_map(fn ($c) => [
                    'name' => trim((string) ($c->name ?? '')),
                    'spend' => round((float) $c->total_spend, 2),
                    'visits' => (int) $c->visit_count,
                ], $topCustomers),
            ],

            'staff_active_count' => $staffCount,
            'open_pos_sessions' => $openSessions,
            'active_promotions' => $activePromos,
            'payment_methods_30d' => $payments,
            'expenses_30d' => [
                'total' => round((float) ($expenses->total ?? 0), 2),
                'count' => (int) ($expenses->cnt ?? 0),
            ],
            'returns_30d' => $returns,
            'orders_30d' => $orders,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Aggregation helpers
    // ─────────────────────────────────────────────────────────────────

    private function salesAgg(array $storeIds, string $start, ?string $end): array
    {
        if (empty($storeIds)) {
            return $this->emptySales();
        }
        $q = DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed')
            ->where('created_at', '>=', $start);
        if ($end) {
            $q->where('created_at', '<=', $end);
        }
        $row = $q->selectRaw("
            COUNT(*) AS cnt,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(AVG(total_amount), 0) AS avg_ticket,
            COALESCE(MAX(total_amount), 0) AS max_ticket,
            COALESCE(SUM(discount_amount), 0) AS discounts,
            COALESCE(SUM(tax_amount), 0) AS tax
        ")->first();

        return [
            'transactions' => (int) ($row->cnt ?? 0),
            'revenue' => round((float) ($row->revenue ?? 0), 2),
            'avg_ticket' => round((float) ($row->avg_ticket ?? 0), 2),
            'max_ticket' => round((float) ($row->max_ticket ?? 0), 2),
            'discounts' => round((float) ($row->discounts ?? 0), 2),
            'tax' => round((float) ($row->tax ?? 0), 2),
        ];
    }

    private function emptySales(): array
    {
        return [
            'transactions' => 0, 'revenue' => 0.0, 'avg_ticket' => 0.0,
            'max_ticket' => 0.0, 'discounts' => 0.0, 'tax' => 0.0,
        ];
    }

    private function dailySeries(array $storeIds, Carbon $from): array
    {
        if (empty($storeIds)) {
            return [];
        }
        $rows = DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed')
            ->where('created_at', '>=', $from->copy()->utc()->toDateTimeString())
            ->selectRaw("DATE(created_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS rev")
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        return $rows->map(fn ($r) => [
            'date' => (string) $r->d,
            'transactions' => (int) $r->cnt,
            'revenue' => round((float) $r->rev, 2),
        ])->values()->all();
    }

    private function monthlySeries(array $storeIds, Carbon $from): array
    {
        if (empty($storeIds)) {
            return [];
        }
        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            'mysql' => "DATE_FORMAT(created_at, '%Y-%m')",
            default => "strftime('%Y-%m', created_at)",
        };

        $rows = DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed')
            ->where('created_at', '>=', $from->copy()->utc()->toDateTimeString())
            ->selectRaw("{$monthExpr} AS m, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS rev")
            ->groupBy('m')
            ->orderBy('m')
            ->get();

        return $rows->map(fn ($r) => [
            'month' => (string) $r->m,
            'transactions' => (int) $r->cnt,
            'revenue' => round((float) $r->rev, 2),
        ])->values()->all();
    }

    private function topProducts(array $storeIds, string $since, int $limit): array
    {
        if (empty($storeIds)) {
            return [];
        }
        $rows = DB::table('transaction_items as ti')
            ->join('transactions as t', 't.id', '=', 'ti.transaction_id')
            ->whereIn('t.store_id', $storeIds)
            ->where('t.status', 'completed')
            ->where('t.created_at', '>=', $since)
            ->groupBy('ti.product_name')
            ->orderByRaw('SUM(ti.line_total) DESC')
            ->limit($limit)
            ->get(['ti.product_name', DB::raw('SUM(ti.quantity) AS qty'), DB::raw('SUM(ti.line_total) AS revenue')]);

        return $rows->map(fn ($r) => [
            'name' => (string) $r->product_name,
            'qty' => round((float) $r->qty, 2),
            'revenue' => round((float) $r->revenue, 2),
        ])->values()->all();
    }

    private function topCategories(array $storeIds, string $since, int $limit): array
    {
        if (empty($storeIds) || !Schema::hasColumn('transaction_items', 'category_name')) {
            return [];
        }
        $rows = DB::table('transaction_items as ti')
            ->join('transactions as t', 't.id', '=', 'ti.transaction_id')
            ->whereIn('t.store_id', $storeIds)
            ->where('t.status', 'completed')
            ->where('t.created_at', '>=', $since)
            ->whereNotNull('ti.category_name')
            ->groupBy('ti.category_name')
            ->orderByRaw('SUM(ti.line_total) DESC')
            ->limit($limit)
            ->get(['ti.category_name', DB::raw('SUM(ti.quantity) AS qty'), DB::raw('SUM(ti.line_total) AS revenue')]);

        return $rows->map(fn ($r) => [
            'category' => (string) $r->category_name,
            'qty' => round((float) $r->qty, 2),
            'revenue' => round((float) $r->revenue, 2),
        ])->values()->all();
    }

    private function perStoreBreakdown(array $storeIds, string $since): array
    {
        if (count($storeIds) <= 1) {
            return [];
        }
        $rows = DB::table('transactions as t')
            ->leftJoin('stores as s', 's.id', '=', 't.store_id')
            ->whereIn('t.store_id', $storeIds)
            ->where('t.status', 'completed')
            ->where('t.created_at', '>=', $since)
            ->groupBy('t.store_id', 's.name')
            ->orderByRaw('SUM(t.total_amount) DESC')
            ->get([
                't.store_id',
                's.name as store_name',
                DB::raw('COUNT(*) AS cnt'),
                DB::raw('COALESCE(SUM(t.total_amount), 0) AS revenue'),
            ]);

        return $rows->map(fn ($r) => [
            'store_id' => (string) $r->store_id,
            'store_name' => (string) ($r->store_name ?? 'Unknown'),
            'transactions' => (int) $r->cnt,
            'revenue' => round((float) $r->revenue, 2),
        ])->values()->all();
    }

    private function inventorySnapshot(array $storeIds): array
    {
        if (empty($storeIds)) {
            return ['total_skus' => 0, 'total_units' => 0, 'low_stock_count' => 0, 'out_of_stock_count' => 0];
        }
        $row = DB::table('stock_levels')
            ->whereIn('store_id', $storeIds)
            ->selectRaw("
                COUNT(*) AS total_skus,
                COALESCE(SUM(quantity), 0) AS total_units,
                COUNT(CASE WHEN quantity <= reorder_point AND reorder_point > 0 THEN 1 END) AS low_stock_count,
                COUNT(CASE WHEN quantity = 0 THEN 1 END) AS out_of_stock_count
            ")
            ->first();

        return [
            'total_skus' => (int) ($row->total_skus ?? 0),
            'total_units' => round((float) ($row->total_units ?? 0), 2),
            'low_stock_count' => (int) ($row->low_stock_count ?? 0),
            'out_of_stock_count' => (int) ($row->out_of_stock_count ?? 0),
        ];
    }

    private function expiringCount(array $storeIds, string $today, string $until): int
    {
        if (empty($storeIds)) {
            return 0;
        }
        return (int) DB::table('stock_batches')
            ->whereIn('store_id', $storeIds)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $today)
            ->where('expiry_date', '<=', $until)
            ->where('quantity', '>', 0)
            ->count();
    }

    private function lowStockSamples(array $storeIds, int $limit): array
    {
        if (empty($storeIds)) {
            return [];
        }
        $rows = DB::table('stock_levels as sl')
            ->leftJoin('products as p', 'p.id', '=', 'sl.product_id')
            ->whereIn('sl.store_id', $storeIds)
            ->whereColumn('sl.quantity', '<=', 'sl.reorder_point')
            ->where('sl.reorder_point', '>', 0)
            ->orderBy('sl.quantity')
            ->limit($limit)
            ->get(['sl.product_id', 'p.name', 'sl.quantity', 'sl.reorder_point']);

        return $rows->map(fn ($r) => [
            'product_id' => (string) $r->product_id,
            'name' => (string) ($r->name ?? 'Unknown'),
            'quantity' => round((float) $r->quantity, 2),
            'reorder_point' => round((float) $r->reorder_point, 2),
        ])->values()->all();
    }

    private function paymentsBreakdown(array $storeIds, string $since): array
    {
        if (empty($storeIds)) {
            return [];
        }
        $rows = DB::table('payments as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->whereIn('t.store_id', $storeIds)
            ->where('t.status', 'completed')
            ->where('t.created_at', '>=', $since)
            ->groupBy('p.method')
            ->orderByRaw('SUM(p.amount) DESC')
            ->get(['p.method', DB::raw('COUNT(*) AS cnt'), DB::raw('SUM(p.amount) AS total')]);

        return $rows->map(fn ($r) => [
            'method' => (string) $r->method,
            'transactions' => (int) $r->cnt,
            'total' => round((float) $r->total, 2),
        ])->values()->all();
    }

    private function returnsAgg(array $storeIds, string $since): array
    {
        if (empty($storeIds) || !Schema::hasTable('sale_returns')) {
            return ['count' => 0, 'amount' => 0.0];
        }
        $amountColumn = Schema::hasColumn('sale_returns', 'total_refund_amount')
            ? 'total_refund_amount'
            : (Schema::hasColumn('sale_returns', 'refund_amount') ? 'refund_amount' : null);

        $q = DB::table('sale_returns')
            ->whereIn('store_id', $storeIds)
            ->where('created_at', '>=', $since);

        $amount = $amountColumn
            ? (float) $q->clone()->sum($amountColumn)
            : 0.0;

        return [
            'count' => (int) $q->count(),
            'amount' => round($amount, 2),
        ];
    }

    private function ordersAgg(array $storeIds, string $since): array
    {
        if (empty($storeIds) || !Schema::hasTable('orders')) {
            return ['count' => 0, 'amount' => 0.0, 'by_status' => []];
        }
        // Real column is `total` (no `_amount` suffix in some schemas).
        $amountCol = Schema::hasColumn('orders', 'total_amount') ? 'total_amount'
            : (Schema::hasColumn('orders', 'total') ? 'total' : null);

        $base = DB::table('orders')
            ->whereIn('store_id', $storeIds)
            ->where('created_at', '>=', $since);

        $count = (int) $base->count();
        $amount = $amountCol ? (float) $base->clone()->sum($amountCol) : 0.0;

        $byStatus = $base->clone()
            ->groupBy('status')
            ->get(['status', DB::raw('COUNT(*) AS cnt')])
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->cnt])
            ->values()->all();

        return [
            'count' => $count,
            'amount' => round($amount, 2),
            'by_status' => $byStatus,
        ];
    }

    private function resolveTimezone(string $organizationId, ?string $storeId): string
    {
        if ($storeId) {
            $tz = DB::table('stores')->where('id', $storeId)->value('timezone');
            if ($tz) {
                return (string) $tz;
            }
        }
        return (string) (DB::table('stores')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderByDesc('is_main_branch')
            ->value('timezone') ?? config('app.timezone', 'Asia/Riyadh'));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Prompt builder — turns the context array into a system prompt.
    // ─────────────────────────────────────────────────────────────────

    private function buildPrompt(array $ctx): string
    {
        $cur = $ctx['currency'];
        $isOrg = $ctx['scope'] === 'organization';
        $scopeLabel = $isOrg
            ? "the organization \"{$ctx['organization_name']}\" (covering {$ctx['branch_count_active_in_scope']} active branch(es))"
            : "the store \"{$ctx['display_name']}\"" . ($ctx['primary_store_city'] ? " in {$ctx['primary_store_city']}" : '');

        $fmtSales = function (string $label, array $s) use ($cur) {
            return sprintf(
                '- %s: %d transactions, revenue %s %s, avg ticket %s %s, discounts %s %s, tax %s %s',
                $label,
                $s['transactions'],
                $cur, number_format($s['revenue'], 2),
                $cur, number_format($s['avg_ticket'], 2),
                $cur, number_format($s['discounts'], 2),
                $cur, number_format($s['tax'], 2)
            );
        };

        $salesBlock = implode("\n", [
            $fmtSales('Today',         $ctx['sales_today']),
            $fmtSales('Yesterday',     $ctx['sales_yesterday']),
            $fmtSales('Last 7 days',   $ctx['sales_last_7d']),
            $fmtSales('Last 30 days',  $ctx['sales_last_30d']),
            $fmtSales('Month-to-date', $ctx['sales_month_to_date']),
            $fmtSales('Last month',    $ctx['sales_last_month']),
            $fmtSales('Year-to-date',  $ctx['sales_year_to_date']),
            $fmtSales('Last year',     $ctx['sales_last_year']),
        ]);

        $fmtNum = fn (float $n): string => rtrim(rtrim(number_format($n, 2), '0'), '.');

        $dailyBlock = empty($ctx['daily_series_14d'])
            ? '  (no sales in the last 14 days)'
            : implode("\n", array_map(
                fn ($d) => sprintf('  %s — %d txns, %s %s', $d['date'], $d['transactions'], $cur, number_format($d['revenue'], 2)),
                $ctx['daily_series_14d']
            ));

        $monthlyBlock = empty($ctx['monthly_series_12m'])
            ? '  (no sales in the last 12 months)'
            : implode("\n", array_map(
                fn ($m) => sprintf('  %s — %d txns, %s %s', $m['month'], $m['transactions'], $cur, number_format($m['revenue'], 2)),
                $ctx['monthly_series_12m']
            ));

        $topProductsBlock = empty($ctx['top_products_30d'])
            ? '  (no product sales in the last 30 days)'
            : implode("\n", array_map(
                fn ($p, $i) => sprintf('  %d. %s — %s units, %s %s', $i + 1, $p['name'], $fmtNum((float) $p['qty']), $cur, number_format($p['revenue'], 2)),
                $ctx['top_products_30d'],
                array_keys($ctx['top_products_30d'])
            ));

        $topCatBlock = empty($ctx['top_categories_30d'])
            ? '  (category breakdown not available)'
            : implode("\n", array_map(
                fn ($c) => sprintf('  - %s: %s units, %s %s', $c['category'], $fmtNum((float) $c['qty']), $cur, number_format($c['revenue'], 2)),
                $ctx['top_categories_30d']
            ));

        $perStoreBlock = empty($ctx['per_store_breakdown_30d'])
            ? ''
            : "\n═══ PER-STORE BREAKDOWN (last 30 days) ═══\n" . implode("\n", array_map(
                fn ($s) => sprintf('  - %s: %d txns, %s %s', $s['store_name'], $s['transactions'], $cur, number_format($s['revenue'], 2)),
                $ctx['per_store_breakdown_30d']
            ));

        $paymentBlock = empty($ctx['payment_methods_30d'])
            ? '  (no payment data)'
            : implode("\n", array_map(
                fn ($p) => sprintf('  - %s: %d txns, %s %s', $p['method'], $p['transactions'], $cur, number_format($p['total'], 2)),
                $ctx['payment_methods_30d']
            ));

        $lowStockBlock = empty($ctx['low_stock_samples'])
            ? '  (no low-stock items)'
            : implode("\n", array_map(
                fn ($i) => sprintf('  - %s: %s on hand (reorder at %s)', $i['name'], $fmtNum((float) $i['quantity']), $fmtNum((float) $i['reorder_point'])),
                $ctx['low_stock_samples']
            ));

        $topCustomersBlock = empty($ctx['customers']['top'])
            ? '  (no top customers)'
            : implode("\n", array_map(
                fn ($c) => sprintf('  - %s: %s %s lifetime, %d visits', $c['name'] !== '' ? $c['name'] : 'Unknown', $cur, number_format($c['spend'], 2), $c['visits']),
                $ctx['customers']['top']
            ));

        $ordersBlock = $ctx['orders_30d']['count'] > 0
            ? sprintf('  - Orders (30d): %d, value %s %s', $ctx['orders_30d']['count'], $cur, number_format($ctx['orders_30d']['amount'], 2))
                . (empty($ctx['orders_30d']['by_status']) ? '' : "\n  - By status: " . implode(', ', array_map(fn ($s) => "{$s['status']}={$s['count']}", $ctx['orders_30d']['by_status'])))
            : '  (no online/external orders in the last 30 days)';

        $catalog = $ctx['catalog'];
        $customers = $ctx['customers'];
        $inventory = $ctx['inventory'];
        $expenses = $ctx['expenses_30d'];
        $returns = $ctx['returns_30d'];

        return <<<PROMPT
You are Wameed AI, an intelligent point-of-sale and business assistant for {$scopeLabel}.
Organization: "{$ctx['organization_name']}" — business type: {$ctx['business_type']}. Currency: {$ctx['currency']}. Timezone: {$ctx['timezone']}. Local time now: {$ctx['now_local']}.

═══ SALES BY PERIOD ═══
{$salesBlock}

═══ DAILY SALES (last 14 days) ═══
{$dailyBlock}

═══ MONTHLY SALES (last 12 months) ═══
{$monthlyBlock}

═══ TOP 10 PRODUCTS (last 30 days, by revenue) ═══
{$topProductsBlock}

═══ TOP CATEGORIES (last 30 days) ═══
{$topCatBlock}
{$perStoreBlock}

═══ INVENTORY ═══
- Total SKUs tracked: {$inventory['total_skus']}
- Total units in stock: {$inventory['total_units']}
- Low-stock SKUs (≤ reorder point): {$inventory['low_stock_count']}
- Out of stock: {$inventory['out_of_stock_count']}
- Expiring within 30 days: {$inventory['expiring_within_30d']}

Low-stock examples:
{$lowStockBlock}

═══ PRODUCT CATALOG ═══
- Active products: {$catalog['total_products']} (inactive: {$catalog['inactive_count']})
- Average sell price: {$cur} {$catalog['avg_price']}
- Top-level categories: {$catalog['top_level_categories']}

═══ CUSTOMERS ═══
- Total customers: {$customers['total']}
- Active in last 30 days: {$customers['active_last_30d']}
- Lifetime spend (all time): {$cur} {$customers['lifetime_spend']}
- Average visits per customer: {$customers['avg_visits']}

Top customers by lifetime spend:
{$topCustomersBlock}

═══ PAYMENT METHODS (last 30 days) ═══
{$paymentBlock}

═══ ORDERS / RETURNS (last 30 days) ═══
{$ordersBlock}
- Sale returns: {$returns['count']} (refund value {$cur} {$returns['amount']})

═══ OPERATIONS ═══
- Active staff in scope: {$ctx['staff_active_count']}
- Open POS sessions right now: {$ctx['open_pos_sessions']}
- Active promotions: {$ctx['active_promotions']}
- Expenses (last 30 days): {$cur} {$expenses['total']} across {$expenses['count']} entries

═══ RESPONSE GUIDELINES ═══
- Always reply in the SAME language the user writes in (Arabic or English).
- Always use {$cur} for monetary values and the local timezone {$ctx['timezone']} for dates.
- Be concise but data-driven: cite exact numbers from the sections above when relevant.
- Use markdown (tables, bullet lists, headings) to make answers easy to scan.
- When data needed for an answer is not in the context above, say so clearly instead of guessing.
- Provide actionable recommendations grounded in the figures shown.
PROMPT;
    }
}
