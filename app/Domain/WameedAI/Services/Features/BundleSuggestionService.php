<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class BundleSuggestionService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'bundle_suggestions';
    }

    public function getSuggestions(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $coPurchased = DB::select("
            SELECT a.product_id as product_a, b.product_id as product_b,
                   pa.name as name_a, pa.name_ar as name_ar_a, pa.sell_price as price_a,
                   pb.name as name_b, pb.name_ar as name_ar_b, pb.sell_price as price_b,
                   ca.name as category_a, cb.name as category_b,
                   COUNT(*) as co_purchase_count
            FROM transaction_items a
            JOIN transaction_items b ON a.transaction_id = b.transaction_id AND a.product_id < b.product_id
            JOIN transactions t ON t.id = a.transaction_id
            JOIN products pa ON pa.id = a.product_id
            JOIN products pb ON pb.id = b.product_id
            LEFT JOIN categories ca ON ca.id = pa.category_id
            LEFT JOIN categories cb ON cb.id = pb.category_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '60 days'
              AND t.status = 'completed'
            GROUP BY a.product_id, b.product_id, pa.name, pa.name_ar, pa.sell_price,
                     pb.name, pb.name_ar, pb.sell_price, ca.name, cb.name
            HAVING COUNT(*) >= 3
            ORDER BY co_purchase_count DESC
            LIMIT 30
        ", [$storeId]);

        if (empty($coPurchased)) {
            return ['suggestions' => [], 'message' => 'Not enough transaction data for bundle analysis'];
        }

        $topProducts = DB::select("
            SELECT p.name, p.name_ar, p.sell_price, p.cost_price,
                   SUM(ti.quantity) as qty_sold, SUM(ti.line_total) as revenue,
                   c.name as category
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            GROUP BY p.id, p.name, p.name_ar, p.sell_price, p.cost_price, c.name
            ORDER BY revenue DESC LIMIT 20
        ", [$storeId]);

        $avgBasket = DB::selectOne("
            SELECT COALESCE(AVG(total_amount), 0) as avg_basket,
                   COALESCE(AVG(item_count), 0) as avg_items
            FROM (
                SELECT t.total_amount, COUNT(ti.id) as item_count
                FROM transactions t
                JOIN transaction_items ti ON ti.transaction_id = t.id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
                GROUP BY t.id, t.total_amount
            ) sub
        ", [$storeId]);

        $context = [
            'copurchase_pairs' => json_encode($coPurchased, JSON_UNESCAPED_UNICODE),
            'top_products' => json_encode($topProducts, JSON_UNESCAPED_UNICODE),
            'avg_basket' => json_encode($avgBasket, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
