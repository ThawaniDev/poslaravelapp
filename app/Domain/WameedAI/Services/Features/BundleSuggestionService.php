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
        // Basket analysis: products frequently purchased together
        $coPurchased = DB::select("
            SELECT a.product_id as product_a, b.product_id as product_b,
                   pa.name as name_a, pa.name_ar as name_ar_a, pa.sell_price as price_a,
                   pb.name as name_b, pb.name_ar as name_ar_b, pb.sell_price as price_b,
                   COUNT(*) as co_purchase_count
            FROM transaction_items a
            JOIN transaction_items b ON a.transaction_id = b.transaction_id AND a.product_id < b.product_id
            JOIN transactions t ON t.id = a.transaction_id
            JOIN products pa ON pa.id = a.product_id
            JOIN products pb ON pb.id = b.product_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '60 days'
              AND t.status = 'completed'
            GROUP BY a.product_id, b.product_id, pa.name, pa.name_ar, pa.sell_price, pb.name, pb.name_ar, pb.sell_price
            HAVING COUNT(*) >= 5
            ORDER BY co_purchase_count DESC
            LIMIT 30
        ", [$storeId]);

        if (empty($coPurchased)) {
            return ['suggestions' => [], 'message' => 'Not enough transaction data for bundle analysis'];
        }

        $topProducts = DB::select("
            SELECT p.name, p.name_ar, SUM(ti.quantity) as qty_sold, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            GROUP BY p.id, p.name, p.name_ar
            ORDER BY revenue DESC LIMIT 20
        ", [$storeId]);

        $context = [
            'copurchase_pairs' => json_encode($coPurchased, JSON_UNESCAPED_UNICODE),
            'top_products' => json_encode($topProducts, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
