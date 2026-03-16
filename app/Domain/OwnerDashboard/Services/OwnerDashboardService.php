<?php

namespace App\Domain\OwnerDashboard\Services;

use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OwnerDashboardService
{
    // ─── Dashboard Stats (KPI Cards) ─────────────────────────

    public function stats(string $storeId): array
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $todaySummary = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', $today)
            ->first();

        $yesterdaySummary = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', $yesterday)
            ->first();

        return [
            'today_sales' => [
                'value' => (float) ($todaySummary?->total_revenue ?? 0),
                'change' => $this->percentChange(
                    (float) ($todaySummary?->total_revenue ?? 0),
                    (float) ($yesterdaySummary?->total_revenue ?? 0),
                ),
            ],
            'transactions' => [
                'value' => (int) ($todaySummary?->total_transactions ?? 0),
                'change' => $this->percentChange(
                    (float) ($todaySummary?->total_transactions ?? 0),
                    (float) ($yesterdaySummary?->total_transactions ?? 0),
                ),
            ],
            'avg_basket' => [
                'value' => (float) ($todaySummary?->avg_basket_size ?? 0),
                'change' => $this->percentChange(
                    (float) ($todaySummary?->avg_basket_size ?? 0),
                    (float) ($yesterdaySummary?->avg_basket_size ?? 0),
                ),
            ],
            'net_profit' => [
                'value' => (float) ($todaySummary?->net_revenue ?? 0),
                'change' => $this->percentChange(
                    (float) ($todaySummary?->net_revenue ?? 0),
                    (float) ($yesterdaySummary?->net_revenue ?? 0),
                ),
            ],
            'unique_customers' => (int) ($todaySummary?->unique_customers ?? 0),
            'total_refunds' => (float) ($todaySummary?->total_refunds ?? 0),
        ];
    }

    // ─── Sales Trend (chart data) ────────────────────────────

    public function salesTrend(string $storeId, array $filters): array
    {
        $days = (int) ($filters['days'] ?? 7);
        $from = Carbon::today()->subDays($days - 1)->toDateString();
        $to = Carbon::today()->toDateString();

        $prevFrom = Carbon::today()->subDays($days * 2 - 1)->toDateString();
        $prevTo = Carbon::today()->subDays($days)->toDateString();

        $current = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();

        $previous = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', '>=', $prevFrom)
            ->whereDate('date', '<=', $prevTo)
            ->orderBy('date')
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'current' => $current->map(fn ($r) => [
                'date' => $r->date->format('Y-m-d'),
                'revenue' => (float) $r->total_revenue,
                'orders' => (int) $r->total_transactions,
                'net_revenue' => (float) $r->net_revenue,
            ])->values()->toArray(),
            'previous' => $previous->map(fn ($r) => [
                'date' => $r->date->format('Y-m-d'),
                'revenue' => (float) $r->total_revenue,
                'orders' => (int) $r->total_transactions,
                'net_revenue' => (float) $r->net_revenue,
            ])->values()->toArray(),
            'summary' => [
                'current_total' => (float) $current->sum('total_revenue'),
                'previous_total' => (float) $previous->sum('total_revenue'),
                'change' => $this->percentChange(
                    (float) $current->sum('total_revenue'),
                    (float) $previous->sum('total_revenue'),
                ),
            ],
        ];
    }

    // ─── Top Products ────────────────────────────────────────

    public function topProducts(string $storeId, array $filters): array
    {
        $limit = (int) ($filters['limit'] ?? 5);
        $days = (int) ($filters['days'] ?? 30);
        $metric = $filters['metric'] ?? 'revenue';

        $from = Carbon::today()->subDays($days - 1)->toDateString();

        $orderColumn = $metric === 'quantity' ? 'total_qty' : 'total_revenue';

        return ProductSalesSummary::where('product_sales_summary.store_id', $storeId)
            ->whereDate('product_sales_summary.date', '>=', $from)
            ->join('products', 'product_sales_summary.product_id', '=', 'products.id')
            ->select([
                'product_sales_summary.product_id',
                'products.name as product_name',
                'products.name_ar as product_name_ar',
                'products.sku',
                DB::raw('SUM(product_sales_summary.quantity_sold) as total_qty'),
                DB::raw('SUM(product_sales_summary.revenue) as total_revenue'),
            ])
            ->groupBy(
                'product_sales_summary.product_id',
                'products.name',
                'products.name_ar',
                'products.sku',
            )
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'product_name_ar' => $r->product_name_ar,
                'sku' => $r->sku,
                'total_qty' => (float) $r->total_qty,
                'total_revenue' => (float) $r->total_revenue,
            ])->toArray();
    }

    // ─── Low Stock Alerts ────────────────────────────────────

    public function lowStockAlerts(string $storeId, int $limit = 10): array
    {
        return DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->where('stock_levels.store_id', $storeId)
            ->whereColumn('stock_levels.quantity', '<=', 'stock_levels.reorder_point')
            ->where('stock_levels.quantity', '>', 0)
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
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'product_name_ar' => $r->product_name_ar,
                'sku' => $r->sku,
                'current_stock' => (float) $r->current_stock,
                'reorder_point' => (float) $r->reorder_point,
            ])->toArray();
    }

    // ─── Active Cashiers ─────────────────────────────────────

    public function activeCashiers(string $storeId): array
    {
        return DB::table('pos_sessions')
            ->join('users', 'pos_sessions.cashier_id', '=', 'users.id')
            ->leftJoin('registers', 'pos_sessions.register_id', '=', 'registers.id')
            ->where('pos_sessions.store_id', $storeId)
            ->whereNull('pos_sessions.closed_at')
            ->where('pos_sessions.status', 'open')
            ->select([
                'users.id as user_id',
                'users.name as user_name',
                'registers.name as register_name',
                'pos_sessions.opened_at',
                'pos_sessions.total_cash_sales',
                'pos_sessions.total_card_sales',
                'pos_sessions.transaction_count',
            ])
            ->orderBy('pos_sessions.opened_at')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'user_name' => $r->user_name,
                'register_name' => $r->register_name,
                'opened_at' => $r->opened_at,
                'total_sales' => (float) $r->total_cash_sales + (float) $r->total_card_sales,
                'transaction_count' => (int) $r->transaction_count,
            ])->toArray();
    }

    // ─── Recent Orders ───────────────────────────────────────

    public function recentOrders(string $storeId, int $limit = 10): array
    {
        return DB::table('orders')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.store_id', $storeId)
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

    public function financialSummary(string $storeId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::today()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::today()->toDateString();

        $rows = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->get();

        $paymentBreakdown = DB::table('payments')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId)
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
                'count' => (int) $r->count,
                'total' => (float) $r->total,
            ])->toArray();

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'revenue' => [
                'total' => (float) $rows->sum('total_revenue'),
                'net' => (float) $rows->sum('net_revenue'),
                'cost' => (float) $rows->sum('total_cost'),
                'tax' => (float) $rows->sum('total_tax'),
                'discounts' => (float) $rows->sum('total_discount'),
                'refunds' => (float) $rows->sum('total_refunds'),
            ],
            'payments' => $paymentBreakdown,
            'daily' => $rows->map(fn ($r) => [
                'date' => $r->date->format('Y-m-d'),
                'revenue' => (float) $r->total_revenue,
                'net' => (float) $r->net_revenue,
                'orders' => (int) $r->total_transactions,
            ])->values()->toArray(),
        ];
    }

    // ─── Hourly Sales (for today or a specific date) ─────────

    public function hourlySales(string $storeId, ?string $date = null): array
    {
        $targetDate = $date ?? Carbon::today()->toDateString();

        $driver = DB::connection()->getDriverName();
        $hourExpr = match ($driver) {
            'sqlite' => "strftime('%H', transactions.created_at)",
            default => 'EXTRACT(HOUR FROM transactions.created_at)',
        };

        return DB::table('transactions')
            ->where('transactions.store_id', $storeId)
            ->whereDate('transactions.created_at', $targetDate)
            ->where('transactions.status', 'completed')
            ->select([
                DB::raw("$hourExpr as hour"),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(transactions.total_amount) as revenue'),
            ])
            ->groupBy(DB::raw($hourExpr))
            ->orderBy('hour')
            ->get()
            ->map(fn ($r) => [
                'hour' => (int) $r->hour,
                'transaction_count' => (int) $r->transaction_count,
                'revenue' => (float) $r->revenue,
            ])->toArray();
    }

    // ─── Multi-Branch Overview ───────────────────────────────

    public function branchOverview(string $organizationId): array
    {
        $today = Carbon::today()->toDateString();

        return DB::table('daily_sales_summary')
            ->join('stores', 'daily_sales_summary.store_id', '=', 'stores.id')
            ->where('stores.organization_id', $organizationId)
            ->whereDate('daily_sales_summary.date', $today)
            ->select([
                'stores.id as store_id',
                'stores.name as store_name',
                'stores.name_ar as store_name_ar',
                'daily_sales_summary.total_transactions',
                'daily_sales_summary.total_revenue',
                'daily_sales_summary.net_revenue',
                'daily_sales_summary.avg_basket_size',
            ])
            ->orderByDesc('daily_sales_summary.total_revenue')
            ->get()
            ->map(fn ($r) => [
                'store_id' => $r->store_id,
                'store_name' => $r->store_name,
                'store_name_ar' => $r->store_name_ar,
                'total_transactions' => (int) $r->total_transactions,
                'total_revenue' => (float) $r->total_revenue,
                'net_revenue' => (float) $r->net_revenue,
                'avg_basket_size' => (float) $r->avg_basket_size,
            ])->toArray();
    }

    // ─── Staff Performance Summary ───────────────────────────

    public function staffPerformance(string $storeId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::today()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::today()->toDateString();

        return DB::table('transactions')
            ->join('users', 'transactions.cashier_id', '=', 'users.id')
            ->where('transactions.store_id', $storeId)
            ->where('transactions.status', 'completed')
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
                'staff_id' => $r->staff_id,
                'staff_name' => $r->staff_name,
                'transaction_count' => (int) $r->transaction_count,
                'total_revenue' => (float) $r->total_revenue,
                'avg_transaction' => round((float) $r->avg_transaction, 2),
            ])->toArray();
    }

    // ─── Private Helpers ─────────────────────────────────────

    private function percentChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
