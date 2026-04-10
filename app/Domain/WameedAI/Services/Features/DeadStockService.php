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
        $currency = $this->getStoreCurrency($storeId);

        $deadStock = DB::select("
            SELECT p.name, p.name_ar, p.sell_price, p.cost_price,
                   c.name as category, c.name_ar as category_ar,
                   sl.quantity as current_stock,
                   (sl.quantity * COALESCE(p.cost_price, 0)) as stock_value,
                   MAX(t.created_at) as last_sold_at,
                   EXTRACT(DAY FROM NOW() - MAX(t.created_at)) as days_since_last_sale,
                   p.created_at as product_created_at
            FROM products p
            JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN transaction_items ti ON ti.product_id = p.id
            LEFT JOIN transactions t ON t.id = ti.transaction_id AND t.store_id = ? AND t.status = 'completed'
            WHERE p.organization_id = ? AND p.is_active = true AND sl.quantity > 0
            GROUP BY p.id, p.name, p.name_ar, p.sell_price, p.cost_price, c.name, c.name_ar, sl.quantity, p.created_at
            HAVING MAX(t.created_at) IS NULL OR MAX(t.created_at) < NOW() - INTERVAL '{$daysSinceLastSale} days'
            ORDER BY stock_value DESC
            LIMIT 50
        ", [$storeId, $storeId, $organizationId]);

        if (empty($deadStock)) {
            return ['products' => [], 'total_value_at_risk' => 0, 'message' => 'No dead stock found'];
        }

        $totalDeadValue = array_sum(array_map(fn ($p) => (float) $p->stock_value, $deadStock));

        $totalInventoryValue = DB::selectOne("
            SELECT COALESCE(SUM(sl.quantity * COALESCE(p.cost_price, 0)), 0) as total
            FROM stock_levels sl
            JOIN products p ON p.id = sl.product_id
            WHERE sl.store_id = ? AND p.is_active = true AND sl.quantity > 0
        ", [$storeId])->total;

        $context = [
            'dead_products' => json_encode($deadStock, JSON_UNESCAPED_UNICODE),
            'total_dead_stock_value' => number_format($totalDeadValue, 2),
            'total_inventory_value' => number_format((float) $totalInventoryValue, 2),
            'dead_stock_pct' => $totalInventoryValue > 0 ? number_format($totalDeadValue / $totalInventoryValue * 100, 1) : '0',
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
