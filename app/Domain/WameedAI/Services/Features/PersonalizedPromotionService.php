<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class PersonalizedPromotionService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'personalized_promotions'; }

    public function suggest(string $storeId, string $organizationId, ?string $segment = null, ?string $userId = null): ?array
    {
        $customerData = DB::select("
            SELECT c.id, c.name, c.total_spend, c.visit_count,
                   c.last_visit_at,
                   (SELECT string_agg(DISTINCT cat.name, ', ')
                    FROM transaction_items ti2
                    JOIN transactions t2 ON t2.id = ti2.transaction_id
                    JOIN products p2 ON p2.id = ti2.product_id
                    JOIN categories cat ON cat.id = p2.category_id
                    WHERE t2.customer_id = c.id AND t2.store_id = ?
                    AND t2.created_at >= NOW() - INTERVAL '60 days'
                   ) as favorite_categories
            FROM customers c
            WHERE c.organization_id = ? AND c.visit_count >= 2
            ORDER BY c.total_spend DESC
            LIMIT 200
        ", [$storeId, $organizationId]);

        $topProducts = DB::select("
            SELECT p.name, p.name_ar, p.sell_price, SUM(ti.quantity) as total_sold
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            GROUP BY p.id, p.name, p.name_ar, p.sell_price
            ORDER BY total_sold DESC LIMIT 20
        ", [$storeId]);

        $context = [
            'customer_data' => json_encode($customerData, JSON_UNESCAPED_UNICODE),
            'top_products' => json_encode($topProducts, JSON_UNESCAPED_UNICODE),
            'segment_filter' => $segment ?? 'all',
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
