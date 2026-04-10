<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SeasonalPlanningService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'seasonal_planning'; }

    public function plan(string $storeId, string $organizationId, string $season = 'ramadan', ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $historicalSales = DB::select("
            SELECT p.id, p.name, p.name_ar, c.name as category,
                   SUM(ti.quantity) as total_sold, SUM(ti.line_total) as total_revenue,
                   EXTRACT(MONTH FROM t.created_at) as month
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND t.status = 'completed'
              AND t.created_at >= NOW() - INTERVAL '365 days'
            GROUP BY p.id, p.name, p.name_ar, c.name, EXTRACT(MONTH FROM t.created_at)
            ORDER BY total_revenue DESC
            LIMIT 200
        ", [$storeId]);

        if (empty($historicalSales)) {
            return ['seasonal_insights' => [], 'message' => 'Not enough historical data for seasonal planning'];
        }

        $currentStock = DB::select("
            SELECT p.id, p.name, p.name_ar, sl.quantity as current_stock,
                   p.cost_price, p.sell_price,
                   COALESCE(sl.reorder_point, 10) as reorder_point,
                   c.name as category
            FROM stock_levels sl
            JOIN products p ON p.id = sl.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE sl.store_id = ? AND p.is_active = true AND sl.quantity > 0
        ", [$storeId]);

        $supplierLeadTimes = DB::select("
            SELECT s.name as supplier, AVG(EXTRACT(DAY FROM gr.received_at - po.created_at)) as avg_lead_days,
                   COUNT(*) as deliveries
            FROM goods_receipts gr
            JOIN suppliers s ON s.id = gr.supplier_id
            LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
            WHERE gr.store_id = ? AND gr.received_at IS NOT NULL
              AND gr.received_at >= NOW() - INTERVAL '180 days'
            GROUP BY s.id, s.name
        ", [$storeId]);

        $monthlyRevenueTrend = DB::select("
            SELECT EXTRACT(MONTH FROM created_at) as month,
                   EXTRACT(YEAR FROM created_at) as year,
                   SUM(total_amount) as revenue, COUNT(*) as txn_count
            FROM transactions
            WHERE store_id = ? AND status = 'completed' AND created_at >= NOW() - INTERVAL '24 months'
            GROUP BY EXTRACT(YEAR FROM created_at), EXTRACT(MONTH FROM created_at)
            ORDER BY year, month
        ", [$storeId]);

        $context = [
            'monthly_sales' => json_encode($historicalSales, JSON_UNESCAPED_UNICODE),
            'category_trends' => json_encode($currentStock, JSON_UNESCAPED_UNICODE),
            'supplier_lead_times' => json_encode($supplierLeadTimes, JSON_UNESCAPED_UNICODE),
            'monthly_revenue_trend' => json_encode($monthlyRevenueTrend, JSON_UNESCAPED_UNICODE),
            'current_month' => now()->format('F Y'),
            'season' => $season,
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
