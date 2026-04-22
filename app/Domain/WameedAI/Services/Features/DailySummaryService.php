<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class DailySummaryService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'daily_summary';
    }

    /**
     * Build a comprehensive daily-summary feature payload.
     *
     * `$storeId` is nullable: when null the service aggregates across every
     * active store in the organization so org-level users get a combined
     * picture instead of a 500.
     */
    public function getSummary(?string $storeId, string $organizationId, string $date, ?string $userId = null): ?array
    {
        $storeIds = $this->resolveStoreIds($storeId, $organizationId);
        $currency = $this->getStoreCurrency($storeId);
        $isOrgScope = $storeId === null;

        if (empty($storeIds)) {
            return $this->emptyDayResponse($date);
        }

        $completedCount = (int) DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->whereRaw('DATE(created_at) = ?', [$date])
            ->where('status', 'completed')
            ->where('type', 'sale')
            ->count();

        if ($completedCount === 0) {
            return $this->emptyDayResponse($date);
        }

        $sales = DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->whereRaw('DATE(created_at) = ?', [$date])
            ->where('status', 'completed')
            ->where('type', 'sale')
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) AS total_sales,
                COUNT(*) AS total_transactions,
                COALESCE(AVG(total_amount), 0) AS avg_basket,
                COALESCE(MAX(total_amount), 0) AS max_transaction,
                COALESCE(SUM(discount_amount), 0) AS total_discounts,
                COALESCE(SUM(tax_amount), 0) AS total_tax
            ')
            ->first();

        $voidCount = (int) DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->whereRaw('DATE(created_at) = ?', [$date])
            ->where('status', 'voided')
            ->count();

        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $yesterdaySales = DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->whereRaw('DATE(created_at) = ?', [$yesterday])
            ->where('status', 'completed')
            ->where('type', 'sale')
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) AS total_sales,
                COUNT(*) AS total_transactions,
                COALESCE(AVG(total_amount), 0) AS avg_basket
            ')
            ->first();

        $topProducts = DB::table('transaction_items as ti')
            ->join('transactions as t', 't.id', '=', 'ti.transaction_id')
            ->join('products as p', 'p.id', '=', 'ti.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->whereIn('t.store_id', $storeIds)
            ->whereRaw('DATE(t.created_at) = ?', [$date])
            ->where('t.status', 'completed')
            ->groupBy('p.id', 'p.name', 'p.name_ar', 'p.cost_price', 'c.name', 'c.name_ar')
            ->orderByRaw('SUM(ti.line_total) DESC')
            ->limit(10)
            ->get([
                'p.name', 'p.name_ar',
                DB::raw('SUM(ti.quantity) AS qty_sold'),
                DB::raw('SUM(ti.line_total) AS revenue'),
                'p.cost_price',
                'c.name as category', 'c.name_ar as category_ar',
            ])
            ->all();

        $paymentMethods = DB::table('payments as pm')
            ->join('transactions as t', 't.id', '=', 'pm.transaction_id')
            ->whereIn('t.store_id', $storeIds)
            ->whereRaw('DATE(t.created_at) = ?', [$date])
            ->where('t.status', 'completed')
            ->groupBy('pm.method')
            ->orderByRaw('SUM(pm.amount) DESC')
            ->get(['pm.method', DB::raw('COUNT(*) AS count'), DB::raw('SUM(pm.amount) AS total')])
            ->all();

        $lowStock = DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->whereIn('sl.store_id', $storeIds)
            ->whereRaw('sl.quantity <= COALESCE(sl.reorder_point, 5)')
            ->where('p.is_active', true)
            ->orderBy('sl.quantity')
            ->limit(10)
            ->get(['p.name', 'p.name_ar', 'sl.quantity', DB::raw('COALESCE(sl.reorder_point, 5) AS reorder_point')])
            ->all();

        $returns = DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->whereRaw('DATE(created_at) = ?', [$date])
            ->where('type', 'return')
            ->selectRaw('COUNT(*) AS return_count, COALESCE(SUM(total_amount), 0) AS return_total')
            ->first();

        $hourExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(HOUR FROM created_at)',
            'mysql' => 'HOUR(created_at)',
            default => "CAST(strftime('%H', created_at) AS INTEGER)",
        };

        $hourlyBreakdown = DB::table('transactions')
            ->whereIn('store_id', $storeIds)
            ->whereRaw('DATE(created_at) = ?', [$date])
            ->where('status', 'completed')
            ->groupByRaw($hourExpr)
            ->orderByRaw($hourExpr)
            ->get([
                DB::raw("{$hourExpr} AS hour"),
                DB::raw('COUNT(*) AS txn_count'),
                DB::raw('SUM(total_amount) AS revenue'),
            ])
            ->all();

        // Per-store breakdown when org-scoped across multiple stores
        $perStore = $isOrgScope && count($storeIds) > 1
            ? DB::table('transactions as t')
                ->leftJoin('stores as s', 's.id', '=', 't.store_id')
                ->whereIn('t.store_id', $storeIds)
                ->whereRaw('DATE(t.created_at) = ?', [$date])
                ->where('t.status', 'completed')
                ->groupBy('t.store_id', 's.name')
                ->orderByRaw('SUM(t.total_amount) DESC')
                ->get([
                    't.store_id',
                    's.name as store_name',
                    DB::raw('COUNT(*) AS txn_count'),
                    DB::raw('SUM(t.total_amount) AS revenue'),
                ])
                ->all()
            : [];

        $context = [
            'sales_data' => json_encode([
                'date' => $date,
                'scope' => $isOrgScope ? 'organization' : 'store',
                'stores_in_scope' => count($storeIds),
                'total_sales' => number_format((float) $sales->total_sales, 2),
                'completed_transactions' => $completedCount,
                'avg_basket' => number_format((float) $sales->avg_basket, 2),
                'max_transaction' => number_format((float) $sales->max_transaction, 2),
                'total_discounts' => number_format((float) $sales->total_discounts, 2),
                'total_tax' => number_format((float) $sales->total_tax, 2),
                'void_count' => $voidCount,
                'yesterday_revenue' => number_format((float) $yesterdaySales->total_sales, 2),
                'yesterday_transactions' => $yesterdaySales->total_transactions,
                'yesterday_avg_basket' => number_format((float) $yesterdaySales->avg_basket, 2),
                'payment_methods' => $paymentMethods,
                'hourly_breakdown' => $hourlyBreakdown,
                'per_store_breakdown' => $perStore,
            ], JSON_UNESCAPED_UNICODE),
            'top_products' => json_encode($topProducts, JSON_UNESCAPED_UNICODE),
            'low_stock' => json_encode($lowStock, JSON_UNESCAPED_UNICODE),
            'returns' => json_encode([
                'count' => $returns->return_count ?? 0,
                'total' => number_format((float) ($returns->return_total ?? 0), 2),
            ], JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }

    private function emptyDayResponse(string $date): array
    {
        return [
            'headline_ar' => 'لا توجد مبيعات لهذا اليوم',
            'revenue' => 0,
            'transaction_count' => 0,
            'avg_basket' => 0,
            'top_products' => [],
            'alerts' => [],
            'comparison_yesterday' => ['revenue_change' => 0, 'transaction_count_change' => 0, 'avg_basket_change' => 0],
            'recommendations_ar' => [],
            'narrative_ar' => 'لا توجد بيانات مبيعات مسجلة لهذا اليوم (' . $date . ').',
        ];
    }
}
