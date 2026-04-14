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
        $currency = $this->getStoreCurrency($storeId);

        $lowStockProducts = DB::select("
            SELECT p.name, p.name_ar, p.sku, p.barcode,
                   sl.quantity as current_stock,
                   COALESCE(sl.reorder_point, 10) as reorder_point,
                   COALESCE(sl.max_stock_level, 100) as max_stock_level,
                   p.cost_price, p.sell_price,
                   c.name as category
            FROM products p
            JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.organization_id = ? AND p.is_active = true
              AND sl.quantity <= COALESCE(sl.reorder_point, 10) * 1.5
            ORDER BY sl.quantity ASC
            LIMIT 50
        ", [$storeId, $organizationId]);

        $salesHistory = DB::select("
            SELECT p.name,
                   SUM(ti.quantity) as total_sold,
                   COUNT(DISTINCT t.id) as transaction_count,
                   SUM(ti.quantity)::DECIMAL / 30 as avg_daily_sales
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days'
              AND t.status = 'completed'
            GROUP BY ti.product_id, p.name
            ORDER BY total_sold DESC
            LIMIT 100
        ", [$storeId]);

        if (empty($lowStockProducts) && empty($salesHistory)) {
            return ['suggestions' => [], 'message' => 'All stock levels are healthy'];
        }

        $supplierInfo = DB::select("
            SELECT p.name as product_name, p.name_ar as product_name_ar,
                   s.name as supplier_name, ps.cost_price as supplier_cost,
                   ps.lead_time_days
            FROM product_suppliers ps
            JOIN suppliers s ON s.id = ps.supplier_id
            JOIN products p ON p.id = ps.product_id
            WHERE s.organization_id = ?
            ORDER BY ps.cost_price ASC
        ", [$organizationId]);

        $pendingOrders = DB::select("
            SELECT p.name as product_name, p.name_ar as product_name_ar,
                   SUM(poi.quantity_ordered) as pending_quantity,
                   MIN(po.expected_date) as earliest_delivery
            FROM purchase_order_items poi
            JOIN purchase_orders po ON po.id = poi.purchase_order_id
            JOIN products p ON p.id = poi.product_id
            WHERE po.store_id = ? AND po.status IN ('pending', 'approved', 'ordered')
            GROUP BY p.id, p.name, p.name_ar
        ", [$storeId]);

        $context = [
            'products' => json_encode($lowStockProducts, JSON_UNESCAPED_UNICODE),
            'recent_sales' => json_encode($salesHistory, JSON_UNESCAPED_UNICODE),
            'supplier_info' => json_encode($supplierInfo, JSON_UNESCAPED_UNICODE),
            'pending_orders' => json_encode($pendingOrders, JSON_UNESCAPED_UNICODE),
            'store_id' => $storeId,
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 360);
    }
}
