<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SmartReorderService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'smart_reorder';
    }

    public function getSuggestions(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $lowStockProducts = DB::select("
            SELECT p.id, p.name, p.name_ar, p.sku, sl.quantity as current_stock,
                   COALESCE(sl.reorder_point, 10) as reorder_point,
                   p.cost_price, p.sell_price
            FROM products p
            JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            WHERE p.organization_id = ? AND p.is_active = true
              AND sl.quantity <= COALESCE(sl.reorder_point, 10) * 1.5
            ORDER BY sl.quantity ASC
            LIMIT 50
        ", [$storeId, $organizationId]);

        $salesHistory = DB::select("
            SELECT ti.product_id, SUM(ti.quantity) as total_sold,
                   COUNT(DISTINCT t.id) as transaction_count
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days'
              AND t.status = 'completed'
            GROUP BY ti.product_id
            ORDER BY total_sold DESC
            LIMIT 100
        ", [$storeId]);

        if (empty($lowStockProducts) && empty($salesHistory)) {
            return ['suggestions' => [], 'message' => 'All stock levels are healthy'];
        }

        $context = [
            'low_stock_products' => json_encode($lowStockProducts, JSON_UNESCAPED_UNICODE),
            'sales_history_30d' => json_encode($salesHistory, JSON_UNESCAPED_UNICODE),
            'store_id' => $storeId,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 360);
    }
}
