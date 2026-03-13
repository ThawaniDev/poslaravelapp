<?php

namespace App\Domain\Report\Services;

use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
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

        return [
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
}
