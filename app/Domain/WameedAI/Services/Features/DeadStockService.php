<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class DeadStockService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'dead_stock';
    }

    public function identify(string $storeId, string $organizationId, int $daysSinceLastSale = 30, ?string $userId = null): ?array
    {
        $deadStock = DB::select("
            SELECT p.id, p.name, p.name_ar, p.sell_price, p.cost_price,
                   sl.quantity as current_stock,
                   (sl.quantity * p.cost_price) as stock_value,
                   MAX(t.created_at) as last_sold_at,
                   EXTRACT(DAY FROM NOW() - MAX(t.created_at)) as days_since_last_sale
            FROM products p
            JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            LEFT JOIN transaction_items ti ON ti.product_id = p.id
            LEFT JOIN transactions t ON t.id = ti.transaction_id AND t.store_id = ? AND t.status = 'completed'
            WHERE p.organization_id = ? AND p.is_active = true AND sl.quantity > 0
            GROUP BY p.id, p.name, p.name_ar, p.sell_price, p.cost_price, sl.quantity
            HAVING MAX(t.created_at) IS NULL OR MAX(t.created_at) < NOW() - INTERVAL '{$daysSinceLastSale} days'
            ORDER BY stock_value DESC
            LIMIT 50
        ", [$storeId, $storeId, $organizationId]);

        if (empty($deadStock)) {
            return ['products' => [], 'message' => 'No dead stock found'];
        }

        $context = [
            'dead_products' => json_encode($deadStock, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
