<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class PricingOptimizationService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'pricing_optimization';
    }

    public function getSuggestions(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $productPerformance = DB::select("
            SELECT p.id, p.name, p.name_ar, p.sell_price, p.cost_price,
                   c.name as category,
                   COALESCE(pss.total_quantity, 0) as qty_sold_30d,
                   COALESCE(pss.total_revenue, 0) as revenue_30d,
                   COALESCE(prev.total_quantity, 0) as qty_sold_prev_30d,
                   COALESCE(prev.total_revenue, 0) as revenue_prev_30d,
                   sl.quantity as current_stock,
                   CASE WHEN p.cost_price > 0 THEN ((p.sell_price - p.cost_price) / p.cost_price * 100) ELSE 0 END as margin_pct
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT ti.product_id, SUM(ti.quantity) as total_quantity, SUM(ti.line_total) as total_revenue
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
                GROUP BY ti.product_id
            ) pss ON pss.product_id = p.id
            LEFT JOIN (
                SELECT ti.product_id, SUM(ti.quantity) as total_quantity, SUM(ti.line_total) as total_revenue
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '60 days'
                  AND t.created_at < NOW() - INTERVAL '30 days' AND t.status = 'completed'
                GROUP BY ti.product_id
            ) prev ON prev.product_id = p.id
            LEFT JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            WHERE p.organization_id = ? AND p.is_active = true
            ORDER BY revenue_30d DESC
            LIMIT 100
        ", [$storeId, $storeId, $storeId, $organizationId]);

        if (empty($productPerformance)) {
            return ['pricing_suggestions' => [], 'message' => 'No product performance data available'];
        }

        $discountImpact = DB::select("
            SELECT p.name, COUNT(CASE WHEN t.discount_amount > 0 THEN 1 END) as discounted_txns,
                   COUNT(*) as total_txns,
                   AVG(CASE WHEN t.discount_amount > 0 THEN t.total_amount END) as avg_discounted_basket,
                   AVG(CASE WHEN t.discount_amount = 0 THEN t.total_amount END) as avg_full_price_basket
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            GROUP BY p.id, p.name
            HAVING COUNT(*) >= 5
            ORDER BY total_txns DESC LIMIT 30
        ", [$storeId]);

        $context = [
            'product_performance' => json_encode($productPerformance, JSON_UNESCAPED_UNICODE),
            'discount_impact' => json_encode($discountImpact, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
