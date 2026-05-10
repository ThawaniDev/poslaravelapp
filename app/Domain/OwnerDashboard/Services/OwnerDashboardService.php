<?php

namespace App\Domain\OwnerDashboard\Services;

use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use App\Domain\Shared\Traits\ScopesStoreQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owner-dashboard aggregations.
 *
 * IMPORTANT: every metric is computed from LIVE tables (`transactions`,
 * `transaction_items`, `payments`, `stock_levels`). The previous version
 * read from pre-aggregated `daily_sales_summary` / `product_sales_summary`
 * tables that are only populated by the manual BackfillSalesSummaries
 * command, which is why a brand-new transaction was never reflected on
 * the dashboard. Do NOT reintroduce summary-table reads here.
 */
class OwnerDashboardService
{
    use ScopesStoreQuery;
    // ─── Dashboard Stats (KPI Cards) ─────────────────────────

    public function stats(string|array $storeId): array
    {
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayAgg     = $this->aggregateRange($storeId, $today->toDateString(), $today->toDateString());
        $yesterdayAgg = $this->aggregateRange($storeId, $yesterday->toDateString(), $yesterday->toDateString());

        $todayRevenue   = $todayAgg['revenue'];
        $todayTxCount   = $todayAgg['transactions'];
        $todayAvgBasket = $todayTxCount > 0 ? $todayRevenue / $todayTxCount : 0.0;
        $todayNet       = $todayAgg['net'];

        $yRevenue   = $yesterdayAgg['revenue'];
        $yTxCount   = $yesterdayAgg['transactions'];
        $yAvgBasket = $yTxCount > 0 ? $yRevenue / $yTxCount : 0.0;
        $yNet       = $yesterdayAgg['net'];

        $uniqueCustomers = (int) $this->scopeByStore(DB::table('transactions'), $storeId)
            ->whereDate('created_at', $today->toDateString())
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::Sale->value)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'today_sales' => [
                'value'  => $todayRevenue,
                'change' => $this->percentChange($todayRevenue, $yRevenue),
            ],
            'transactions' => [
                'value'  => $todayTxCount,
                'change' => $this->percentChange((float) $todayTxCount, (float) $yTxCount),
            ],
            'avg_basket' => [
                'value'  => round($todayAvgBasket, 2),
                'change' => $this->percentChange($todayAvgBasket, $yAvgBasket),
            ],
            'net_profit' => [
                'value'  => $todayNet,
                'change' => $this->percentChange($todayNet, $yNet),
            ],
            'unique_customers' => $uniqueCustomers,
            'total_refunds'    => $todayAgg['refunds'],
        ];
    }

    // ─── Sales Trend (chart data) ────────────────────────────

    public function salesTrend(string|array $storeId, array $filters): array
    {
        $days = (int) ($filters['days'] ?? 7);
        $from = Carbon::today()->subDays($days - 1)->toDateString();
        $to   = Carbon::today()->toDateString();

        $prevFrom = Carbon::today()->subDays($days * 2 - 1)->toDateString();
        $prevTo   = Carbon::today()->subDays($days)->toDateString();

        $current  = $this->dailySeries($storeId, $from, $to, includeNet: true);
        $previous = $this->dailySeries($storeId, $prevFrom, $prevTo, includeNet: true);

        $currentTotal  = array_sum(array_column($current, 'revenue'));
        $previousTotal = array_sum(array_column($previous, 'revenue'));

        return [
            'period'   => ['from' => $from, 'to' => $to],
            'current'  => $current,
            'previous' => $previous,
            'summary'  => [
                'current_total'  => (float) $currentTotal,
                'previous_total' => (float) $previousTotal,
                'change'         => $this->percentChange((float) $currentTotal, (float) $previousTotal),
            ],
        ];
    }

    // ─── Top Products ────────────────────────────────────────

    public function topProducts(string|array $storeId, array $filters): array
    {
        $limit  = (int) ($filters['limit'] ?? 5);
        $days   = (int) ($filters['days'] ?? 30);
        $metric = $filters['metric'] ?? 'revenue';

        $from = Carbon::today()->subDays($days - 1)->toDateString();
        $to   = Carbon::today()->toDateString();

        $orderColumn = $metric === 'quantity' ? 'total_qty' : 'total_revenue';

        return $this->scopeByStore(
                DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id'),
                $storeId,
                'transactions.store_id'
            )
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', '>=', $from)
            ->whereDate('transactions.created_at', '<=', $to)
            ->where('transaction_items.is_return_item', false)
            ->select([
                'transaction_items.product_id',
                'products.name as product_name',
                'products.name_ar as product_name_ar',
                'products.sku',
                DB::raw('SUM(transaction_items.quantity) as total_qty'),
                DB::raw('SUM(transaction_items.line_total) as total_revenue'),
            ])
            ->groupBy(
                'transaction_items.product_id',
                'products.name',
                'products.name_ar',
                'products.sku',
            )
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'product_id'      => $r->product_id,
                'product_name'    => $r->product_name,
                'product_name_ar' => $r->product_name_ar,
                'sku'             => $r->sku,
                'total_qty'       => (float) $r->total_qty,
                'total_revenue'   => (float) $r->total_revenue,
            ])->toArray();
    }

    // ─── Low Stock Alerts ────────────────────────────────────

    public function lowStockAlerts(string|array $storeId, int $limit = 10): array
    {
        // Reads live `stock_levels` table — already updated by TransactionService
        // on every sale/return. Includes out-of-stock items (quantity = 0) so
        // the dashboard surfaces them as well, since the Flutter UI labels this
        // section as "alerts".
        return $this->scopeByStore(DB::table('stock_levels'), $storeId, 'stock_levels.store_id')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->whereColumn('stock_levels.quantity', '<=', 'stock_levels.reorder_point')
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.name_ar as product_name_ar',
                'products.sku',
                'stock_levels.quantity as current_stock',
                'stock_levels.reorder_point',
            ])
            ->orderBy('stock_levels.quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'product_id'      => $r->product_id,
                'product_name'    => $r->product_name,
                'product_name_ar' => $r->product_name_ar,
                'sku'             => $r->sku,
                'current_stock'   => (float) $r->current_stock,
                'reorder_point'   => (float) $r->reorder_point,
            ])->toArray();
    }

    // ─── Active Cashiers ─────────────────────────────────────

    public function activeCashiers(string|array $storeId): array
    {
        $sessions = $this->scopeByStore(DB::table('pos_sessions'), $storeId, 'pos_sessions.store_id')
            ->join('users', 'pos_sessions.cashier_id', '=', 'users.id')
            ->leftJoin('registers', 'pos_sessions.register_id', '=', 'registers.id')
            ->whereNull('pos_sessions.closed_at')
            ->where('pos_sessions.status', 'open')
            ->select([
                'pos_sessions.id as session_id',
                'users.id as user_id',
                'users.name as user_name',
                'registers.name as register_name',
                'pos_sessions.opened_at',
            ])
            ->orderBy('pos_sessions.opened_at')
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        // Live per-session totals from completed sales transactions. The
        // `pos_sessions.total_*` columns are only written on close, so during
        // an open shift they read 0; aggregating from `transactions` keeps the
        // dashboard in sync with reality.
        $sessionIds = $sessions->pluck('session_id')->all();
        $totals = DB::table('transactions')
            ->whereIn('pos_session_id', $sessionIds)
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::Sale->value)
            ->select([
                'pos_session_id',
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('COALESCE(SUM(total_amount), 0) as total_sales'),
            ])
            ->groupBy('pos_session_id')
            ->get()
            ->keyBy('pos_session_id');

        return $sessions->map(function ($r) use ($totals) {
            $t = $totals->get($r->session_id);

            return [
                'user_id'           => $r->user_id,
                'user_name'         => $r->user_name,
                'register_name'     => $r->register_name,
                'opened_at'         => $r->opened_at,
                'total_sales'       => (float) ($t->total_sales ?? 0),
                'transaction_count' => (int) ($t->tx_count ?? 0),
            ];
        })->toArray();
    }

    // ─── Recent Orders ───────────────────────────────────────

    public function recentOrders(string|array $storeId, int $limit = 10): array
    {
        return $this->scopeByStore(DB::table('orders'), $storeId, 'orders.store_id')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.total',
                'orders.status',
                'orders.source',
                'orders.created_at',
                'customers.name as customer_name',
            ])
            ->orderByDesc('orders.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'order_number' => $r->order_number,
                'total' => (float) $r->total,
                'status' => $r->status,
                'source' => $r->source,
                'created_at' => $r->created_at,
                'customer_name' => $r->customer_name,
            ])->toArray();
    }

    // ─── Financial Summary ───────────────────────────────────

    public function financialSummary(string|array $storeId, array $filters): array
    {
        if (! empty($filters['days'])) {
            $days     = (int) $filters['days'];
            $dateFrom = Carbon::today()->subDays($days - 1)->toDateString();
            $dateTo   = Carbon::today()->toDateString();
        } else {
            $dateFrom = $filters['date_from'] ?? Carbon::today()->startOfMonth()->toDateString();
            $dateTo   = $filters['date_to']   ?? Carbon::today()->toDateString();
        }

        // Aggregate sales (excluding refunds/returns) from live transactions.
        $salesAgg = $this->scopeByStore(DB::table('transactions'), $storeId)
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::Sale->value)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('
                COALESCE(SUM(total_amount), 0)    as revenue,
                COALESCE(SUM(tax_amount), 0)      as tax,
                COALESCE(SUM(discount_amount), 0) as discounts
            ')
            ->first();

        // Refunds = sum of total_amount for type = return (always positive in storage).
        $refundsTotal = (float) $this->scopeByStore(DB::table('transactions'), $storeId)
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::Return->value)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('total_amount');

        // COGS from transaction_items (sales lines only).
        $costTotal = (float) $this->scopeByStore(
                DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id'),
                $storeId,
                'transactions.store_id'
            )
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', '>=', $dateFrom)
            ->whereDate('transactions.created_at', '<=', $dateTo)
            ->where('transaction_items.is_return_item', false)
            ->selectRaw('COALESCE(SUM(COALESCE(transaction_items.cost_price, 0) * transaction_items.quantity), 0) as cost')
            ->value('cost');

        $revenue = (float) ($salesAgg->revenue ?? 0);
        $tax     = (float) ($salesAgg->tax ?? 0);
        $disc    = (float) ($salesAgg->discounts ?? 0);
        $net     = $revenue - $tax - $costTotal;

        // Payment-method breakdown — sales only, since refunds typically don't have new payment rows.
        $paymentBreakdown = $this->scopeByStore(
                DB::table('payments')
                    ->join('transactions', 'payments.transaction_id', '=', 'transactions.id'),
                $storeId,
                'transactions.store_id'
            )
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', '>=', $dateFrom)
            ->whereDate('transactions.created_at', '<=', $dateTo)
            ->select([
                'payments.method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(payments.amount) as total'),
            ])
            ->groupBy('payments.method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'count'  => (int) $r->count,
                'total'  => (float) $r->total,
            ])->toArray();

        return [
            'period'  => ['from' => $dateFrom, 'to' => $dateTo],
            'revenue' => [
                'total'     => $revenue,
                'net'       => $net,
                'cost'      => $costTotal,
                'tax'       => $tax,
                'discounts' => $disc,
                'refunds'   => $refundsTotal,
            ],
            'payments' => $paymentBreakdown,
            'daily'    => $this->dailySeries($storeId, $dateFrom, $dateTo, includeNet: true),
        ];
    }

    // ─── Hourly Sales (for today or a specific date) ─────────

    public function hourlySales(string|array $storeId, ?string $date = null): array
    {
        $targetDate = $date ?? Carbon::today()->toDateString();

        $driver = DB::connection()->getDriverName();
        $hourExpr = match ($driver) {
            'sqlite' => "CAST(strftime('%H', transactions.created_at) AS INTEGER)",
            default  => 'EXTRACT(HOUR FROM transactions.created_at)',
        };

        return $this->scopeByStore(DB::table('transactions'), $storeId, 'transactions.store_id')
            ->whereDate('transactions.created_at', $targetDate)
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->select([
                DB::raw("$hourExpr as hour"),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(transactions.total_amount) as revenue'),
            ])
            ->groupBy(DB::raw($hourExpr))
            ->orderBy('hour')
            ->get()
            ->map(fn ($r) => [
                'hour'              => (int) $r->hour,
                'transaction_count' => (int) $r->transaction_count,
                'revenue'           => (float) $r->revenue,
            ])->toArray();
    }

    // ─── Multi-Branch Overview ───────────────────────────────

    public function branchOverview(string $organizationId): array
    {
        $today = Carbon::today()->toDateString();

        // Aggregate today's sales per store directly from `transactions`.
        $aggregates = DB::table('transactions')
            ->join('stores', 'transactions.store_id', '=', 'stores.id')
            ->where('stores.organization_id', $organizationId)
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', $today)
            ->select([
                'stores.id as store_id',
                'stores.name as store_name',
                'stores.name_ar as store_name_ar',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('COALESCE(SUM(transactions.total_amount), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(transactions.tax_amount), 0)   as total_tax'),
            ])
            ->groupBy('stores.id', 'stores.name', 'stores.name_ar')
            ->orderByDesc('total_revenue')
            ->get();

        // COGS per store for the same window.
        $costs = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('stores', 'transactions.store_id', '=', 'stores.id')
            ->where('stores.organization_id', $organizationId)
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', $today)
            ->where('transaction_items.is_return_item', false)
            ->select([
                'transactions.store_id',
                DB::raw('COALESCE(SUM(COALESCE(transaction_items.cost_price, 0) * transaction_items.quantity), 0) as cost'),
            ])
            ->groupBy('transactions.store_id')
            ->pluck('cost', 'store_id');

        return $aggregates->map(function ($r) use ($costs) {
            $revenue = (float) $r->total_revenue;
            $tax     = (float) $r->total_tax;
            $cost    = (float) ($costs[$r->store_id] ?? 0);
            $txCount = (int) $r->total_transactions;
            $avg     = $txCount > 0 ? $revenue / $txCount : 0.0;

            return [
                'store_id'           => $r->store_id,
                'store_name'         => $r->store_name,
                'store_name_ar'      => $r->store_name_ar,
                'total_transactions' => $txCount,
                'total_revenue'      => $revenue,
                'net_revenue'        => $revenue - $tax - $cost,
                'avg_basket_size'    => round($avg, 2),
            ];
        })->values()->toArray();
    }

    // ─── Staff Performance Summary ───────────────────────────

    public function staffPerformance(string|array $storeId, array $filters): array
    {
        if (! empty($filters['days'])) {
            $days     = (int) $filters['days'];
            $dateFrom = Carbon::today()->subDays($days - 1)->toDateString();
            $dateTo   = Carbon::today()->toDateString();
        } else {
            $dateFrom = $filters['date_from'] ?? Carbon::today()->startOfMonth()->toDateString();
            $dateTo   = $filters['date_to']   ?? Carbon::today()->toDateString();
        }

        return $this->scopeByStore(DB::table('transactions'), $storeId, 'transactions.store_id')
            ->join('users', 'transactions.cashier_id', '=', 'users.id')
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', '>=', $dateFrom)
            ->whereDate('transactions.created_at', '<=', $dateTo)
            ->select([
                'users.id as staff_id',
                'users.name as staff_name',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(transactions.total_amount) as total_revenue'),
                DB::raw('AVG(transactions.total_amount) as avg_transaction'),
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn ($r) => [
                'staff_id'          => $r->staff_id,
                'staff_name'        => $r->staff_name,
                'transaction_count' => (int) $r->transaction_count,
                'total_revenue'     => (float) $r->total_revenue,
                'avg_transaction'   => round((float) $r->avg_transaction, 2),
            ])->toArray();
    }

    // ─── Aggregated Summary (single-request dashboard load) ──

    public function summary(string|array $storeId, string $organizationId, array $filters): array
    {
        $days    = isset($filters['days']) ? (int) $filters['days'] : null;
        $derived = $days !== null ? ['days' => $days] : [];

        return [
            'stats'              => $this->stats($storeId),
            'sales_trend'        => $this->salesTrend($storeId, array_merge(['days' => $days ?? 7], $derived)),
            'top_products'       => $this->topProducts($storeId, array_merge(['limit' => 5, 'days' => $days ?? 30], $derived)),
            'low_stock'          => $this->lowStockAlerts($storeId, 10),
            'active_cashiers'    => $this->activeCashiers($storeId),
            'recent_orders'      => $this->recentOrders($storeId, 10),
            'financial_summary'  => $this->financialSummary($storeId, $derived),
            'hourly_sales'       => $this->hourlySales($storeId),
            'branches'           => $this->branchOverview($organizationId),
            'staff_performance'  => $this->staffPerformance($storeId, $derived),
        ];
    }

    // ─── Private Helpers ─────────────────────────────────────

    /**
     * Aggregate sale figures (revenue, count, COGS, net, refunds) for a single
     * inclusive date range from live transactions/transaction_items tables.
     *
     * Always returns float/int — never null.
     *
     * @return array{revenue: float, transactions: int, cost: float, net: float, tax: float, refunds: float}
     */
    private function aggregateRange(string|array $storeId, string $from, string $to): array
    {
        $sales = $this->scopeByStore(DB::table('transactions'), $storeId)
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::Sale->value)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('
                COUNT(*)                          as tx_count,
                COALESCE(SUM(total_amount), 0)    as revenue,
                COALESCE(SUM(tax_amount), 0)      as tax
            ')
            ->first();

        $cost = (float) $this->scopeByStore(
                DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id'),
                $storeId,
                'transactions.store_id'
            )
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', '>=', $from)
            ->whereDate('transactions.created_at', '<=', $to)
            ->where('transaction_items.is_return_item', false)
            ->selectRaw('COALESCE(SUM(COALESCE(transaction_items.cost_price, 0) * transaction_items.quantity), 0) as cost')
            ->value('cost');

        $refunds = (float) $this->scopeByStore(DB::table('transactions'), $storeId)
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::Return->value)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('total_amount');

        $revenue = (float) ($sales->revenue ?? 0);
        $tax     = (float) ($sales->tax ?? 0);

        return [
            'revenue'      => $revenue,
            'transactions' => (int) ($sales->tx_count ?? 0),
            'cost'         => $cost,
            'tax'          => $tax,
            'net'          => $revenue - $tax - $cost,
            'refunds'      => $refunds,
        ];
    }

    /**
     * Per-day sales series for a date range. Only returns days that had at
     * least one completed sale (matches the original summary-table behaviour
     * so callers/tests that compare against an empty array keep working).
     *
     * @return array<int, array{date: string, revenue: float, orders: int, net_revenue?: float, net?: float}>
     */
    private function dailySeries(string|array $storeId, string $from, string $to, bool $includeNet = false): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($driver) {
            'sqlite' => "date(transactions.created_at)",
            default  => "DATE(transactions.created_at)",
        };

        $rows = $this->scopeByStore(DB::table('transactions'), $storeId, 'transactions.store_id')
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.type', TransactionType::Sale->value)
            ->whereDate('transactions.created_at', '>=', $from)
            ->whereDate('transactions.created_at', '<=', $to)
            ->select([
                DB::raw("$dateExpr as day"),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(transactions.total_amount), 0) as revenue'),
                DB::raw('COALESCE(SUM(transactions.tax_amount), 0)   as tax'),
            ])
            ->groupBy(DB::raw($dateExpr))
            ->orderBy('day')
            ->get();

        // Per-day cost for net_revenue.
        $costsByDay = collect();
        if ($includeNet) {
            $costsByDay = $this->scopeByStore(
                    DB::table('transaction_items')
                        ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id'),
                    $storeId,
                    'transactions.store_id'
                )
                ->where('transactions.status', TransactionStatus::Completed->value)
                ->where('transactions.type', TransactionType::Sale->value)
                ->whereDate('transactions.created_at', '>=', $from)
                ->whereDate('transactions.created_at', '<=', $to)
                ->where('transaction_items.is_return_item', false)
                ->select([
                    DB::raw("$dateExpr as day"),
                    DB::raw('COALESCE(SUM(COALESCE(transaction_items.cost_price, 0) * transaction_items.quantity), 0) as cost'),
                ])
                ->groupBy(DB::raw($dateExpr))
                ->pluck('cost', 'day');
        }

        return $rows->map(function ($r) use ($includeNet, $costsByDay) {
            $key     = (string) $r->day;
            $revenue = (float) $r->revenue;
            $tax     = (float) $r->tax;
            $entry   = [
                'date'    => $key,
                'revenue' => $revenue,
                'orders'  => (int) $r->orders,
            ];

            if ($includeNet) {
                $cost = (float) ($costsByDay[$key] ?? 0);
                $net  = $revenue - $tax - $cost;
                $entry['net_revenue'] = $net;
                $entry['net']         = $net; // backwards-compat key used by Flutter financial-summary mapper
            }

            return $entry;
        })->values()->toArray();
    }

    private function percentChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
