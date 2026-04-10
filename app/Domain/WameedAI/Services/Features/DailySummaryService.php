<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class DailySummaryService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'daily_summary';
    }

    public function getSummary(string $storeId, string $organizationId, string $date, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $completedCount = (int) DB::selectOne("
            SELECT COUNT(*) as c FROM transactions
            WHERE store_id = ? AND DATE(created_at) = ? AND status = 'completed' AND type = 'sale'
        ", [$storeId, $date])->c;

        if ($completedCount === 0) {
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

        $sales = DB::selectOne("
            SELECT COALESCE(SUM(total_amount), 0) as total_sales,
                   COUNT(*) as total_transactions,
                   COALESCE(AVG(total_amount), 0) as avg_basket,
                   COALESCE(MAX(total_amount), 0) as max_transaction,
                   COALESCE(SUM(discount_amount), 0) as total_discounts,
                   COALESCE(SUM(tax_amount), 0) as total_tax
            FROM transactions
            WHERE store_id = ? AND DATE(created_at) = ? AND status = 'completed' AND type = 'sale'
        ", [$storeId, $date]);

        $voidCount = (int) DB::selectOne("
            SELECT COUNT(*) as c FROM transactions
            WHERE store_id = ? AND DATE(created_at) = ? AND status = 'voided'
        ", [$storeId, $date])->c;

        // Yesterday comparison
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $yesterdaySales = DB::selectOne("
            SELECT COALESCE(SUM(total_amount), 0) as total_sales,
                   COUNT(*) as total_transactions,
                   COALESCE(AVG(total_amount), 0) as avg_basket
            FROM transactions
            WHERE store_id = ? AND DATE(created_at) = ? AND status = 'completed' AND type = 'sale'
        ", [$storeId, $yesterday]);

        $topProducts = DB::select("
            SELECT p.name, p.name_ar, SUM(ti.quantity) as qty_sold,
                   SUM(ti.line_total) as revenue, p.cost_price,
                   c.name as category, c.name_ar as category_ar
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND DATE(t.created_at) = ? AND t.status = 'completed'
            GROUP BY p.id, p.name, p.name_ar, p.cost_price, c.name, c.name_ar
            ORDER BY revenue DESC LIMIT 10
        ", [$storeId, $date]);

        $paymentMethods = DB::select("
            SELECT pm.method, COUNT(*) as count, SUM(pm.amount) as total
            FROM payments pm
            JOIN transactions t ON t.id = pm.transaction_id
            WHERE t.store_id = ? AND DATE(t.created_at) = ? AND t.status = 'completed'
            GROUP BY pm.method ORDER BY total DESC
        ", [$storeId, $date]);

        $lowStock = DB::select("
            SELECT p.name, p.name_ar, sl.quantity, COALESCE(sl.reorder_point, 5) as reorder_point
            FROM stock_levels sl
            JOIN products p ON p.id = sl.product_id
            WHERE sl.store_id = ? AND sl.quantity <= COALESCE(sl.reorder_point, 5)
              AND p.is_active = true
            ORDER BY sl.quantity ASC LIMIT 10
        ", [$storeId]);

        $returns = DB::selectOne("
            SELECT COUNT(*) as return_count, COALESCE(SUM(total_amount), 0) as return_total
            FROM transactions
            WHERE store_id = ? AND DATE(created_at) = ? AND type = 'return'
        ", [$storeId, $date]);

        $hourlyBreakdown = DB::select("
            SELECT EXTRACT(HOUR FROM created_at) as hour,
                   COUNT(*) as txn_count, SUM(total_amount) as revenue
            FROM transactions
            WHERE store_id = ? AND DATE(created_at) = ? AND status = 'completed'
            GROUP BY EXTRACT(HOUR FROM created_at) ORDER BY hour
        ", [$storeId, $date]);

        $context = [
            'sales_data' => json_encode([
                'date' => $date,
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
}
