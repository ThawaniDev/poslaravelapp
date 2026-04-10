<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class MarginAnalyzerService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'margin_analyzer'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $products = DB::select("
            SELECT p.id, p.name, p.name_ar, p.sell_price, p.cost_price,
                   c.name as category,
                   CASE WHEN p.cost_price > 0 THEN ((p.sell_price - p.cost_price) / p.cost_price * 100) ELSE 0 END as margin_pct,
                   COALESCE(pss.qty, 0) as qty_sold_30d,
                   COALESCE(pss.revenue, 0) as revenue_30d,
                   COALESCE(pss.actual_cost, 0) as cost_30d,
                   COALESCE(pss.revenue, 0) - COALESCE(pss.actual_cost, 0) as profit_30d
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT ti.product_id, SUM(ti.quantity) as qty,
                       SUM(ti.line_total) as revenue,
                       SUM(ti.quantity * COALESCE(ti.cost_price, 0)) as actual_cost
                FROM transaction_items ti JOIN transactions t ON t.id = ti.transaction_id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
                GROUP BY ti.product_id
            ) pss ON pss.product_id = p.id
            WHERE p.organization_id = ? AND p.is_active = true AND p.cost_price > 0
            ORDER BY margin_pct ASC
            LIMIT 100
        ", [$storeId, $organizationId]);

        if (empty($products)) {
            return ['low_margin_products' => [], 'message' => 'No products with cost data found'];
        }

        $categoryMargins = DB::select("
            SELECT c.name as category, c.name_ar,
                   AVG(CASE WHEN p.cost_price > 0 THEN ((p.sell_price - p.cost_price) / p.cost_price * 100) ELSE 0 END) as avg_margin,
                   COALESCE(SUM(pss.revenue), 0) as total_revenue,
                   COALESCE(SUM(pss.cost), 0) as total_cost
            FROM categories c
            JOIN products p ON p.category_id = c.id AND p.is_active = true AND p.cost_price > 0
            LEFT JOIN (
                SELECT ti.product_id, SUM(ti.line_total) as revenue,
                       SUM(ti.quantity * COALESCE(ti.cost_price, 0)) as cost
                FROM transaction_items ti JOIN transactions t ON t.id = ti.transaction_id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
                GROUP BY ti.product_id
            ) pss ON pss.product_id = p.id
            WHERE c.organization_id = ?
            GROUP BY c.id, c.name, c.name_ar
            ORDER BY avg_margin ASC
        ", [$storeId, $organizationId]);

        $overallMargin = DB::selectOne("
            SELECT COALESCE(SUM(ti.line_total), 0) as total_revenue,
                   COALESCE(SUM(ti.quantity * COALESCE(ti.cost_price, 0)), 0) as total_cost
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
        ", [$storeId]);

        $context = [
            'products' => json_encode($products, JSON_UNESCAPED_UNICODE),
            'category_margins' => json_encode($categoryMargins, JSON_UNESCAPED_UNICODE),
            'overall_revenue' => number_format((float) $overallMargin->total_revenue, 2),
            'overall_cost' => number_format((float) $overallMargin->total_cost, 2),
            'overall_margin_pct' => $overallMargin->total_revenue > 0
                ? number_format(($overallMargin->total_revenue - $overallMargin->total_cost) / $overallMargin->total_revenue * 100, 1) : '0',
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
