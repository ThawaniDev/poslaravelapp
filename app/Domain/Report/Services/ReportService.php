<?php

namespace App\Domain\Report\Services;

use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\Report\Models\ScheduledReport;
use Illuminate\Support\Facades\DB;

class ReportService
{
    // ─── Sales Summary (uses pre-aggregated daily_sales_summary) ──

    public function salesSummary(string $storeId, array $filters): array
    {
        $query = DailySalesSummary::where('store_id', $storeId);

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $rows = $query->orderBy('date')->get();

        $totals = [
            'total_transactions' => $rows->sum('total_transactions'),
            'total_revenue' => (float) $rows->sum('total_revenue'),
            'total_cost' => (float) $rows->sum('total_cost'),
            'total_discount' => (float) $rows->sum('total_discount'),
            'total_tax' => (float) $rows->sum('total_tax'),
            'total_refunds' => (float) $rows->sum('total_refunds'),
            'net_revenue' => (float) $rows->sum('net_revenue'),
            'cash_revenue' => (float) $rows->sum('cash_revenue'),
            'card_revenue' => (float) $rows->sum('card_revenue'),
            'other_revenue' => (float) $rows->sum('other_revenue'),
            'avg_basket_size' => $rows->count() > 0
                ? round((float) $rows->sum('total_revenue') / max(1, $rows->sum('total_transactions')), 2)
                : 0,
            'unique_customers' => (int) $rows->sum('unique_customers'),
        ];

        $result = [
            'totals' => $totals,
            'daily' => $rows->map(fn ($r) => [
                'date' => $r->date->format('Y-m-d'),
                'total_transactions' => $r->total_transactions,
                'total_revenue' => (float) $r->total_revenue,
                'net_revenue' => (float) $r->net_revenue,
                'total_cost' => (float) $r->total_cost,
                'total_discount' => (float) $r->total_discount,
                'total_tax' => (float) $r->total_tax,
                'total_refunds' => (float) $r->total_refunds,
            ])->values()->toArray(),
        ];

        // Period comparison
        if (! empty($filters['compare']) && ! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $result['previous_period'] = $this->previousPeriodSummary($storeId, $filters['date_from'], $filters['date_to']);
        }

        return $result;
    }

    private function previousPeriodSummary(string $storeId, string $dateFrom, string $dateTo): array
    {
        $from = \Carbon\Carbon::parse($dateFrom);
        $to = \Carbon\Carbon::parse($dateTo);
        $days = $from->diffInDays($to) + 1;

        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        $rows = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', '>=', $prevFrom->toDateString())
            ->whereDate('date', '<=', $prevTo->toDateString())
            ->get();

        $prevRevenue = (float) $rows->sum('total_revenue');
        $currRevenue = (float) DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->sum('total_revenue');

        return [
            'date_from' => $prevFrom->toDateString(),
            'date_to' => $prevTo->toDateString(),
            'total_revenue' => $prevRevenue,
            'total_transactions' => (int) $rows->sum('total_transactions'),
            'revenue_change' => $prevRevenue > 0
                ? round(($currRevenue - $prevRevenue) / $prevRevenue * 100, 2)
                : ($currRevenue > 0 ? 100.0 : 0.0),
        ];
    }

    // ─── Product Performance (uses pre-aggregated product_sales_summary) ──

    public function productPerformance(string $storeId, array $filters): array
    {
        $query = ProductSalesSummary::where('product_sales_summary.store_id', $storeId)
            ->join('products', 'product_sales_summary.product_id', '=', 'products.id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('product_sales_summary.date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('product_sales_summary.date', '<=', $filters['date_to']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        $results = $query->select([
            'product_sales_summary.product_id',
            'products.name as product_name',
            'products.name_ar as product_name_ar',
            'products.sku',
            'products.category_id',
            DB::raw('SUM(product_sales_summary.quantity_sold) as total_quantity'),
            DB::raw('SUM(product_sales_summary.revenue) as total_revenue'),
            DB::raw('SUM(product_sales_summary.cost) as total_cost'),
            DB::raw('SUM(product_sales_summary.discount_amount) as total_discount'),
            DB::raw('SUM(product_sales_summary.return_quantity) as total_returns'),
            DB::raw('SUM(product_sales_summary.return_amount) as total_return_amount'),
        ])
            ->groupBy(
                'product_sales_summary.product_id',
                'products.name',
                'products.name_ar',
                'products.sku',
                'products.category_id',
            )
            ->orderByDesc('total_revenue')
            ->limit((int) ($filters['limit'] ?? 50))
            ->get();

        return $results->map(fn ($r) => [
            'product_id' => $r->product_id,
            'product_name' => $r->product_name,
            'product_name_ar' => $r->product_name_ar,
            'sku' => $r->sku,
            'category_id' => $r->category_id,
            'total_quantity' => (float) $r->total_quantity,
            'total_revenue' => (float) $r->total_revenue,
            'total_cost' => (float) $r->total_cost,
            'total_discount' => (float) $r->total_discount,
            'profit' => round((float) $r->total_revenue - (float) $r->total_cost, 2),
            'total_returns' => (float) $r->total_returns,
            'total_return_amount' => (float) $r->total_return_amount,
        ])->toArray();
    }

    // ─── Category Breakdown ──────────────────────────────────

    public function categoryBreakdown(string $storeId, array $filters): array
    {
        $query = ProductSalesSummary::where('product_sales_summary.store_id', $storeId)
            ->join('products', 'product_sales_summary.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('product_sales_summary.date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('product_sales_summary.date', '<=', $filters['date_to']);
        }

        return $query->select([
            'categories.id as category_id',
            'categories.name as category_name',
            'categories.name_ar as category_name_ar',
            DB::raw('SUM(product_sales_summary.quantity_sold) as total_quantity'),
            DB::raw('SUM(product_sales_summary.revenue) as total_revenue'),
            DB::raw('SUM(product_sales_summary.cost) as total_cost'),
            DB::raw('COUNT(DISTINCT product_sales_summary.product_id) as product_count'),
        ])
            ->groupBy('categories.id', 'categories.name', 'categories.name_ar')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn ($r) => [
                'category_id' => $r->category_id,
                'category_name' => $r->category_name,
                'category_name_ar' => $r->category_name_ar,
                'total_quantity' => (float) $r->total_quantity,
                'total_revenue' => (float) $r->total_revenue,
                'total_cost' => (float) $r->total_cost,
                'profit' => round((float) $r->total_revenue - (float) $r->total_cost, 2),
                'product_count' => (int) $r->product_count,
            ])->toArray();
    }

    // ─── Staff Performance ───────────────────────────────────

    public function staffPerformance(string $storeId, array $filters): array
    {
        $query = DB::table('orders')
            ->join('staff_users', function ($join) {
                $join->on('orders.created_by', '=', 'staff_users.id');
            })
            ->where('orders.store_id', $storeId)
            ->whereNotIn('orders.status', ['cancelled', 'voided']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('orders.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('orders.created_at', '<=', $filters['date_to']);
        }

        return $query->select([
            'staff_users.id as staff_id',
            DB::raw("(staff_users.first_name || ' ' || staff_users.last_name) as staff_name"),
            DB::raw('COUNT(orders.id) as total_orders'),
            DB::raw('SUM(orders.total) as total_revenue'),
            DB::raw('ROUND(AVG(orders.total), 2) as avg_order_value'),
            DB::raw('SUM(orders.discount_amount) as total_discounts_given'),
        ])
            ->groupBy('staff_users.id', 'staff_users.first_name', 'staff_users.last_name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn ($r) => [
                'staff_id' => $r->staff_id,
                'staff_name' => $r->staff_name,
                'total_orders' => (int) $r->total_orders,
                'total_revenue' => (float) $r->total_revenue,
                'avg_order_value' => (float) $r->avg_order_value,
                'total_discounts_given' => (float) $r->total_discounts_given,
            ])->toArray();
    }

    // ─── Hourly Sales Pattern ────────────────────────────────

    public function hourlySales(string $storeId, array $filters): array
    {
        $query = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereNotIn('status', ['cancelled', 'voided']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // SQLite: strftime('%H', ...) returns hour.  PostgreSQL: EXTRACT(HOUR FROM ...)
        // Use strftime for SQLite compatibility in tests
        $hourExpr = DB::connection()->getDriverName() === 'pgsql'
            ? "EXTRACT(HOUR FROM created_at)::integer"
            : "CAST(strftime('%H', created_at) AS INTEGER)";

        return $query->select([
            DB::raw("$hourExpr as hour"),
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(total) as total_revenue'),
            DB::raw('ROUND(AVG(total), 2) as avg_order_value'),
        ])
            ->groupBy(DB::raw($hourExpr))
            ->orderBy('hour')
            ->get()
            ->map(fn ($r) => [
                'hour' => (int) $r->hour,
                'total_orders' => (int) $r->total_orders,
                'total_revenue' => (float) $r->total_revenue,
                'avg_order_value' => (float) $r->avg_order_value,
            ])->toArray();
    }

    // ─── Payment Method Breakdown ────────────────────────────

    public function paymentMethodBreakdown(string $storeId, array $filters): array
    {
        $query = DB::table('payments')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId);

        if (! empty($filters['date_from'])) {
            $query->whereDate('payments.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('payments.created_at', '<=', $filters['date_to']);
        }

        return $query->select([
            'payments.method',
            DB::raw('COUNT(*) as transaction_count'),
            DB::raw('SUM(payments.amount) as total_amount'),
            DB::raw('ROUND(AVG(payments.amount), 2) as avg_amount'),
        ])
            ->groupBy('payments.method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'transaction_count' => (int) $r->transaction_count,
                'total_amount' => (float) $r->total_amount,
                'avg_amount' => (float) $r->avg_amount,
            ])->toArray();
    }

    // ─── Dashboard (today summary + comparison) ──────────────

    public function dashboard(string $storeId): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $todaySummary = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', $today)
            ->first();

        $yesterdaySummary = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', $yesterday)
            ->first();

        $todayData = $todaySummary ? [
            'total_transactions' => $todaySummary->total_transactions,
            'total_revenue' => (float) $todaySummary->total_revenue,
            'net_revenue' => (float) $todaySummary->net_revenue,
            'total_refunds' => (float) $todaySummary->total_refunds,
            'avg_basket_size' => (float) $todaySummary->avg_basket_size,
            'unique_customers' => $todaySummary->unique_customers,
        ] : [
            'total_transactions' => 0,
            'total_revenue' => 0.0,
            'net_revenue' => 0.0,
            'total_refunds' => 0.0,
            'avg_basket_size' => 0.0,
            'unique_customers' => 0,
        ];

        $yesterdayData = $yesterdaySummary ? [
            'total_transactions' => $yesterdaySummary->total_transactions,
            'total_revenue' => (float) $yesterdaySummary->total_revenue,
            'net_revenue' => (float) $yesterdaySummary->net_revenue,
        ] : [
            'total_transactions' => 0,
            'total_revenue' => 0.0,
            'net_revenue' => 0.0,
        ];

        // Top 5 products today
        $topProducts = ProductSalesSummary::where('product_sales_summary.store_id', $storeId)
            ->whereDate('product_sales_summary.date', $today)
            ->join('products', 'product_sales_summary.product_id', '=', 'products.id')
            ->select([
                'products.name as product_name',
                'product_sales_summary.quantity_sold',
                'product_sales_summary.revenue',
            ])
            ->orderByDesc('product_sales_summary.revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'product_name' => $r->product_name,
                'quantity_sold' => (float) $r->quantity_sold,
                'revenue' => (float) $r->revenue,
            ])->toArray();

        return [
            'today' => $todayData,
            'yesterday' => $yesterdayData,
            'top_products' => $topProducts,
        ];
    }

    // ─── Slow Movers ─────────────────────────────────────────

    public function slowMovers(string $storeId, array $filters): array
    {
        $query = ProductSalesSummary::where('product_sales_summary.store_id', $storeId)
            ->join('products', 'product_sales_summary.product_id', '=', 'products.id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('product_sales_summary.date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('product_sales_summary.date', '<=', $filters['date_to']);
        }

        return $query->select([
            'product_sales_summary.product_id',
            'products.name as product_name',
            'products.name_ar as product_name_ar',
            'products.sku',
            DB::raw('SUM(product_sales_summary.quantity_sold) as total_quantity'),
            DB::raw('SUM(product_sales_summary.revenue) as total_revenue'),
        ])
            ->groupBy('product_sales_summary.product_id', 'products.name', 'products.name_ar', 'products.sku')
            ->orderBy('total_quantity')
            ->limit((int) ($filters['limit'] ?? 20))
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'product_name_ar' => $r->product_name_ar,
                'sku' => $r->sku,
                'total_quantity' => (float) $r->total_quantity,
                'total_revenue' => (float) $r->total_revenue,
            ])->toArray();
    }

    // ─── Product Margin Analysis ─────────────────────────────

    public function productMargin(string $storeId, array $filters): array
    {
        $query = ProductSalesSummary::where('product_sales_summary.store_id', $storeId)
            ->join('products', 'product_sales_summary.product_id', '=', 'products.id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('product_sales_summary.date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('product_sales_summary.date', '<=', $filters['date_to']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        return $query->select([
            'product_sales_summary.product_id',
            'products.name as product_name',
            'products.sku',
            DB::raw('SUM(product_sales_summary.revenue) as total_revenue'),
            DB::raw('SUM(product_sales_summary.cost) as total_cost'),
            DB::raw('SUM(product_sales_summary.quantity_sold) as total_quantity'),
        ])
            ->groupBy('product_sales_summary.product_id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->limit((int) ($filters['limit'] ?? 50))
            ->get()
            ->map(function ($r) {
                $revenue = (float) $r->total_revenue;
                $cost = (float) $r->total_cost;
                $profit = round($revenue - $cost, 2);

                return [
                    'product_id' => $r->product_id,
                    'product_name' => $r->product_name,
                    'sku' => $r->sku,
                    'total_quantity' => (float) $r->total_quantity,
                    'total_revenue' => $revenue,
                    'total_cost' => $cost,
                    'profit' => $profit,
                    'margin_percent' => $revenue > 0 ? round($profit / $revenue * 100, 2) : 0,
                    'markup_percent' => $cost > 0 ? round($profit / $cost * 100, 2) : 0,
                ];
            })->toArray();
    }

    // ─── Inventory Valuation ─────────────────────────────────

    public function inventoryValuation(string $storeId): array
    {
        $rows = DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->where('stock_levels.store_id', $storeId)
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                'stock_levels.quantity',
                'stock_levels.average_cost',
                DB::raw('(stock_levels.quantity * stock_levels.average_cost) as stock_value'),
            ])
            ->orderByDesc(DB::raw('stock_levels.quantity * stock_levels.average_cost'))
            ->get();

        $totalValue = $rows->sum('stock_value');
        $totalItems = $rows->sum('quantity');

        return [
            'total_stock_value' => round((float) $totalValue, 2),
            'total_items' => (float) $totalItems,
            'product_count' => $rows->count(),
            'products' => $rows->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'sku' => $r->sku,
                'quantity' => (float) $r->quantity,
                'average_cost' => (float) $r->average_cost,
                'stock_value' => round((float) $r->stock_value, 2),
            ])->toArray(),
        ];
    }

    // ─── Inventory Turnover ──────────────────────────────────

    public function inventoryTurnover(string $storeId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        // COGS from product_sales_summary
        $cogs = ProductSalesSummary::where('product_sales_summary.store_id', $storeId)
            ->whereDate('product_sales_summary.date', '>=', $dateFrom)
            ->whereDate('product_sales_summary.date', '<=', $dateTo)
            ->join('products', 'product_sales_summary.product_id', '=', 'products.id')
            ->select([
                'product_sales_summary.product_id',
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(product_sales_summary.cost) as total_cost'),
                DB::raw('SUM(product_sales_summary.quantity_sold) as total_sold'),
            ])
            ->groupBy('product_sales_summary.product_id', 'products.name', 'products.sku')
            ->get();

        // Current stock values
        $stockMap = DB::table('stock_levels')
            ->where('store_id', $storeId)
            ->pluck('quantity', 'product_id')
            ->mapWithKeys(fn ($qty, $id) => [$id => (float) $qty]);

        $avgCostMap = DB::table('stock_levels')
            ->where('store_id', $storeId)
            ->pluck('average_cost', 'product_id')
            ->mapWithKeys(fn ($cost, $id) => [$id => (float) $cost]);

        return $cogs->map(function ($r) use ($stockMap, $avgCostMap) {
            $currentStock = $stockMap[$r->product_id] ?? 0;
            $avgCost = $avgCostMap[$r->product_id] ?? 0;
            $avgInventoryValue = $currentStock * $avgCost;
            $totalCost = (float) $r->total_cost;

            return [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'sku' => $r->sku,
                'total_sold' => (float) $r->total_sold,
                'cogs' => $totalCost,
                'current_stock' => $currentStock,
                'avg_inventory_value' => round($avgInventoryValue, 2),
                'turnover_ratio' => $avgInventoryValue > 0
                    ? round($totalCost / $avgInventoryValue, 2)
                    : 0,
            ];
        })->sortByDesc('turnover_ratio')->values()->toArray();
    }

    // ─── Inventory Shrinkage / Adjustments ───────────────────

    public function inventoryShrinkage(string $storeId, array $filters): array
    {
        $query = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('stock_movements.store_id', $storeId)
            ->where('stock_movements.type', 'adjustment');

        if (! empty($filters['date_from'])) {
            $query->whereDate('stock_movements.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('stock_movements.created_at', '<=', $filters['date_to']);
        }

        $byReason = (clone $query)->select([
            'stock_movements.reason',
            DB::raw('COUNT(*) as adjustment_count'),
            DB::raw('SUM(ABS(stock_movements.quantity)) as total_quantity'),
            DB::raw('SUM(ABS(stock_movements.quantity) * stock_movements.unit_cost) as total_value'),
        ])
            ->groupBy('stock_movements.reason')
            ->orderByDesc('total_value')
            ->get()
            ->map(fn ($r) => [
                'reason' => $r->reason ?? 'unknown',
                'adjustment_count' => (int) $r->adjustment_count,
                'total_quantity' => (float) $r->total_quantity,
                'total_value' => round((float) $r->total_value, 2),
            ])->toArray();

        $byProduct = (clone $query)->select([
            'products.id as product_id',
            'products.name as product_name',
            'products.sku',
            DB::raw('COUNT(*) as adjustment_count'),
            DB::raw('SUM(stock_movements.quantity) as net_quantity_change'),
            DB::raw('SUM(ABS(stock_movements.quantity) * stock_movements.unit_cost) as total_value'),
        ])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_value')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'sku' => $r->sku,
                'adjustment_count' => (int) $r->adjustment_count,
                'net_quantity_change' => (float) $r->net_quantity_change,
                'total_value' => round((float) $r->total_value, 2),
            ])->toArray();

        return [
            'by_reason' => $byReason,
            'by_product' => $byProduct,
        ];
    }

    // ─── Inventory Low Stock ─────────────────────────────────

    public function inventoryLowStock(string $storeId): array
    {
        return DB::table('stock_levels')
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->where('stock_levels.store_id', $storeId)
            ->whereColumn('stock_levels.quantity', '<=', 'stock_levels.reorder_point')
            ->where('stock_levels.reorder_point', '>', 0)
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                'stock_levels.quantity as current_stock',
                'stock_levels.reorder_point',
                'stock_levels.max_stock_level',
                DB::raw('(stock_levels.reorder_point - stock_levels.quantity) as deficit'),
            ])
            ->orderByDesc(DB::raw('stock_levels.reorder_point - stock_levels.quantity'))
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'sku' => $r->sku,
                'current_stock' => (float) $r->current_stock,
                'reorder_point' => (float) $r->reorder_point,
                'max_stock_level' => (float) $r->max_stock_level,
                'deficit' => (float) $r->deficit,
            ])->toArray();
    }

    // ─── Financial: Daily P&L ────────────────────────────────

    public function financialDailyPL(string $storeId, array $filters): array
    {
        // Revenue from daily_sales_summary
        $revenueQuery = DailySalesSummary::where('store_id', $storeId);

        if (! empty($filters['date_from'])) {
            $revenueQuery->whereDate('date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $revenueQuery->whereDate('date', '<=', $filters['date_to']);
        }

        $dailyRevenue = $revenueQuery->orderBy('date')->get()->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        // Expenses grouped by date
        $expenseQuery = DB::table('expenses')->where('store_id', $storeId);
        if (! empty($filters['date_from'])) {
            $expenseQuery->whereDate('expense_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $expenseQuery->whereDate('expense_date', '<=', $filters['date_to']);
        }
        $dailyExpenses = $expenseQuery->select([
            'expense_date',
            DB::raw('SUM(amount) as total_expenses'),
        ])
            ->groupBy('expense_date')
            ->get()
            ->keyBy('expense_date');

        // Merge dates
        $allDates = collect($dailyRevenue->keys())
            ->merge($dailyExpenses->keys())
            ->unique()
            ->sort()
            ->values();

        $daily = $allDates->map(function ($date) use ($dailyRevenue, $dailyExpenses) {
            $rev = $dailyRevenue->get($date);
            $exp = $dailyExpenses->get($date);

            $revenue = $rev ? (float) $rev->net_revenue : 0;
            $cost = $rev ? (float) $rev->total_cost : 0;
            $expenses = $exp ? (float) $exp->total_expenses : 0;
            $grossProfit = $revenue - $cost;
            $netProfit = $grossProfit - $expenses;

            return [
                'date' => $date,
                'revenue' => round($revenue, 2),
                'cost_of_goods' => round($cost, 2),
                'gross_profit' => round($grossProfit, 2),
                'expenses' => round($expenses, 2),
                'net_profit' => round($netProfit, 2),
                'transactions' => $rev ? $rev->total_transactions : 0,
            ];
        })->toArray();

        $totals = [
            'total_revenue' => round(collect($daily)->sum('revenue'), 2),
            'total_cost' => round(collect($daily)->sum('cost_of_goods'), 2),
            'total_gross_profit' => round(collect($daily)->sum('gross_profit'), 2),
            'total_expenses' => round(collect($daily)->sum('expenses'), 2),
            'total_net_profit' => round(collect($daily)->sum('net_profit'), 2),
        ];

        return [
            'totals' => $totals,
            'daily' => $daily,
        ];
    }

    // ─── Financial: Expense Breakdown ────────────────────────

    public function financialExpenses(string $storeId, array $filters): array
    {
        $query = DB::table('expenses')->where('store_id', $storeId);

        if (! empty($filters['date_from'])) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }

        $byCategory = $query->select([
            'category',
            DB::raw('COUNT(*) as expense_count'),
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('ROUND(AVG(amount), 2) as avg_amount'),
        ])
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category,
                'expense_count' => (int) $r->expense_count,
                'total_amount' => round((float) $r->total_amount, 2),
                'avg_amount' => (float) $r->avg_amount,
            ])->toArray();

        $total = collect($byCategory)->sum('total_amount');

        return [
            'total_expenses' => round($total, 2),
            'categories' => $byCategory,
        ];
    }

    // ─── Financial: Cash Variance ────────────────────────────

    public function financialCashVariance(string $storeId, array $filters): array
    {
        $query = DB::table('cash_sessions')
            ->where('store_id', $storeId)
            ->where('status', 'closed');

        if (! empty($filters['date_from'])) {
            $query->whereDate('closed_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('closed_at', '<=', $filters['date_to']);
        }

        $sessions = $query->select([
            'id',
            'opened_by',
            'closed_by',
            'opening_float',
            'expected_cash',
            'actual_cash',
            'variance',
            'opened_at',
            'closed_at',
        ])
            ->orderByDesc('closed_at')
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'session_id' => $r->id,
                'opening_float' => (float) $r->opening_float,
                'expected_cash' => (float) $r->expected_cash,
                'actual_cash' => (float) $r->actual_cash,
                'variance' => (float) $r->variance,
                'opened_at' => $r->opened_at,
                'closed_at' => $r->closed_at,
            ])->toArray();

        $totalVariance = collect($sessions)->sum('variance');
        $positiveCount = collect($sessions)->where('variance', '>', 0)->count();
        $negativeCount = collect($sessions)->where('variance', '<', 0)->count();

        return [
            'total_variance' => round($totalVariance, 2),
            'sessions_count' => count($sessions),
            'positive_variance_count' => $positiveCount,
            'negative_variance_count' => $negativeCount,
            'sessions' => $sessions,
        ];
    }

    // ─── Customer: Top Customers ─────────────────────────────

    public function topCustomers(string $storeId, array $filters): array
    {
        $query = DB::table('customers')
            ->where('customers.organization_id', function ($sub) use ($storeId) {
                $sub->select('organization_id')
                    ->from('stores')
                    ->where('id', $storeId)
                    ->limit(1);
            });

        return $query->select([
            'customers.id',
            'customers.name',
            'customers.phone',
            'customers.email',
            'customers.total_spend',
            'customers.visit_count',
            'customers.loyalty_points',
            'customers.last_visit_at',
        ])
            ->orderByDesc('customers.total_spend')
            ->limit((int) ($filters['limit'] ?? 20))
            ->get()
            ->map(fn ($r) => [
                'customer_id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'email' => $r->email,
                'total_spend' => (float) $r->total_spend,
                'visit_count' => (int) $r->visit_count,
                'loyalty_points' => (int) $r->loyalty_points,
                'last_visit_at' => $r->last_visit_at,
                'avg_spend_per_visit' => $r->visit_count > 0
                    ? round((float) $r->total_spend / $r->visit_count, 2)
                    : 0,
            ])->toArray();
    }

    // ─── Customer: Retention / Repeat Purchase ───────────────

    public function customerRetention(string $storeId, array $filters): array
    {
        $orgId = DB::table('stores')->where('id', $storeId)->value('organization_id');

        $totalCustomers = DB::table('customers')
            ->where('organization_id', $orgId)
            ->count();

        $repeatCustomers = DB::table('customers')
            ->where('organization_id', $orgId)
            ->where('visit_count', '>=', 2)
            ->count();

        $newCustomers30d = DB::table('customers')
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $activeCustomers30d = DB::table('customers')
            ->where('organization_id', $orgId)
            ->where('last_visit_at', '>=', now()->subDays(30))
            ->count();

        $avgVisitCount = DB::table('customers')
            ->where('organization_id', $orgId)
            ->avg('visit_count');

        $avgSpend = DB::table('customers')
            ->where('organization_id', $orgId)
            ->where('total_spend', '>', 0)
            ->avg('total_spend');

        $loyaltyIssued = DB::table('customers')
            ->where('organization_id', $orgId)
            ->sum('loyalty_points');

        return [
            'total_customers' => $totalCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $totalCustomers > 0
                ? round($repeatCustomers / $totalCustomers * 100, 2)
                : 0,
            'new_customers_30d' => $newCustomers30d,
            'active_customers_30d' => $activeCustomers30d,
            'avg_visit_count' => round((float) ($avgVisitCount ?? 0), 2),
            'avg_spend' => round((float) ($avgSpend ?? 0), 2),
            'total_loyalty_points' => (int) $loyaltyIssued,
        ];
    }

    // ─── Summary Refresh ─────────────────────────────────────

    public function refreshDailySummary(string $storeId, string $date): void
    {
        $dateStr = $date;

        // Aggregate from orders table
        $orderData = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereDate('created_at', $dateStr)
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->selectRaw('
                COUNT(*) as total_transactions,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(SUM(discount_amount), 0) as total_discount
            ')
            ->first();

        // Payment breakdown
        $payments = DB::table('payments')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId)
            ->whereDate('payments.created_at', $dateStr)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN payments.method = 'cash' THEN payments.amount ELSE 0 END), 0) as cash_revenue,
                COALESCE(SUM(CASE WHEN payments.method = 'card' THEN payments.amount ELSE 0 END), 0) as card_revenue,
                COALESCE(SUM(CASE WHEN payments.method NOT IN ('cash', 'card') THEN payments.amount ELSE 0 END), 0) as other_revenue
            ")
            ->first();

        $totalRevenue = (float) ($orderData->total_revenue ?? 0);
        $totalTransactions = (int) ($orderData->total_transactions ?? 0);

        DailySalesSummary::updateOrCreate(
            ['store_id' => $storeId, 'date' => $dateStr],
            [
                'total_transactions' => $totalTransactions,
                'total_revenue' => $totalRevenue,
                'total_discount' => (float) ($orderData->total_discount ?? 0),
                'net_revenue' => $totalRevenue,
                'cash_revenue' => (float) ($payments->cash_revenue ?? 0),
                'card_revenue' => (float) ($payments->card_revenue ?? 0),
                'other_revenue' => (float) ($payments->other_revenue ?? 0),
                'avg_basket_size' => $totalTransactions > 0
                    ? round($totalRevenue / $totalTransactions, 2) : 0,
            ],
        );
    }

    public function refreshProductSummary(string $storeId, string $date): void
    {
        $dateStr = $date;

        // Aggregate from transaction_items joined with transactions
        $items = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId)
            ->whereDate('transactions.created_at', $dateStr)
            ->where('transactions.status', 'completed')
            ->select([
                'transaction_items.product_id',
                DB::raw('SUM(transaction_items.quantity) as quantity_sold'),
                DB::raw('SUM(transaction_items.line_total) as revenue'),
                DB::raw('SUM(transaction_items.quantity * transaction_items.cost_price) as cost'),
                DB::raw('SUM(transaction_items.discount_amount) as discount_amount'),
            ])
            ->groupBy('transaction_items.product_id')
            ->get();

        foreach ($items as $item) {
            ProductSalesSummary::updateOrCreate(
                [
                    'store_id' => $storeId,
                    'product_id' => $item->product_id,
                    'date' => $dateStr,
                ],
                [
                    'quantity_sold' => (float) $item->quantity_sold,
                    'revenue' => (float) $item->revenue,
                    'cost' => (float) $item->cost,
                    'discount_amount' => (float) $item->discount_amount,
                ],
            );
        }
    }

    // ─── Scheduled Reports CRUD ──────────────────────────────

    public function createScheduledReport(string $storeId, array $data): ScheduledReport
    {
        $nextRun = match ($data['frequency']) {
            'daily' => now()->addDay()->startOfDay()->addHours(2),
            'weekly' => now()->next('monday')->startOfDay()->addHours(2),
            'monthly' => now()->addMonth()->startOfMonth()->addHours(2),
            default => now()->addDay(),
        };

        return ScheduledReport::create([
            'store_id' => $storeId,
            'report_type' => $data['report_type'],
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'filters' => $data['filters'] ?? null,
            'recipients' => $data['recipients'],
            'format' => $data['format'] ?? 'pdf',
            'next_run_at' => $nextRun,
            'is_active' => true,
        ]);
    }

    public function listScheduledReports(string $storeId): array
    {
        return ScheduledReport::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function deleteScheduledReport(string $storeId, string $id): bool
    {
        return ScheduledReport::where('store_id', $storeId)
            ->where('id', $id)
            ->delete() > 0;
    }

    // ─── Export Helper ───────────────────────────────────────

    public function exportReport(string $storeId, string $reportType, array $filters, string $format): array
    {
        $data = match ($reportType) {
            'sales_summary' => $this->salesSummary($storeId, $filters),
            'product_performance' => $this->productPerformance($storeId, $filters),
            'category_breakdown' => $this->categoryBreakdown($storeId, $filters),
            'staff_performance' => $this->staffPerformance($storeId, $filters),
            'slow_movers' => $this->slowMovers($storeId, $filters),
            'product_margin' => $this->productMargin($storeId, $filters),
            'inventory_valuation' => $this->inventoryValuation($storeId),
            'inventory_low_stock' => $this->inventoryLowStock($storeId),
            'financial_pl' => $this->financialDailyPL($storeId, $filters),
            'financial_expenses' => $this->financialExpenses($storeId, $filters),
            'top_customers' => $this->topCustomers($storeId, $filters),
            default => throw new \InvalidArgumentException("Unknown report type: {$reportType}"),
        };

        return [
            'report_type' => $reportType,
            'format' => $format,
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'data' => $data,
        ];
    }
}
