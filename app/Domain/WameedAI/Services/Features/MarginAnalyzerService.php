<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class MarginAnalyzerService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'margin_analyzer'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $products = DB::select("
            SELECT p.id, p.name, p.name_ar, p.sell_price, p.cost_price,
                   CASE WHEN p.cost_price > 0 THEN ((p.sell_price - p.cost_price) / p.cost_price * 100) ELSE 0 END as margin_pct,
                   COALESCE(pss.qty, 0) as qty_sold_30d,
                   COALESCE(pss.revenue, 0) as revenue_30d
            FROM products p
            LEFT JOIN (
                SELECT ti.product_id, SUM(ti.quantity) as qty, SUM(ti.line_total) as revenue
                FROM transaction_items ti JOIN transactions t ON t.id = ti.transaction_id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
                GROUP BY ti.product_id
            ) pss ON pss.product_id = p.id
            WHERE p.organization_id = ? AND p.is_active = true AND p.cost_price > 0
            ORDER BY margin_pct ASC
            LIMIT 100
        ", [$storeId, $organizationId]);

        $context = [
            'products' => json_encode($products, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
