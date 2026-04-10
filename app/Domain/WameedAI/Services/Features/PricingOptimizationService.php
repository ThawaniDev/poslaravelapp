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
        $productPerformance = DB::select("
            SELECT p.id, p.name, p.name_ar, p.sell_price, p.cost_price,
                   COALESCE(pss.total_quantity, 0) as qty_sold_30d,
                   COALESCE(pss.total_revenue, 0) as revenue_30d,
                   sl.quantity as current_stock,
                   CASE WHEN p.cost_price > 0 THEN ((p.sell_price - p.cost_price) / p.cost_price * 100) ELSE 0 END as margin_pct
            FROM products p
            LEFT JOIN (
                SELECT ti.product_id, SUM(ti.quantity) as total_quantity, SUM(ti.line_total) as total_revenue
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
                GROUP BY ti.product_id
            ) pss ON pss.product_id = p.id
            LEFT JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            WHERE p.organization_id = ? AND p.is_active = true
            ORDER BY revenue_30d DESC
            LIMIT 100
        ", [$storeId, $storeId, $organizationId]);

        $context = [
            'products_performance' => json_encode($productPerformance, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
