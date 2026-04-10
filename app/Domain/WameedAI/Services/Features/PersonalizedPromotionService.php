<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class PersonalizedPromotionService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'personalized_promotions'; }

    public function suggest(string $storeId, string $organizationId, ?string $segment = null, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $customerData = DB::select("
            SELECT c.id, c.name, c.total_spend, c.visit_count,
                   c.last_visit_at, c.loyalty_points, c.store_credit_balance,
                   cg.name as customer_group,
                   EXTRACT(DAY FROM NOW() - c.last_visit_at) as days_since_last_visit,
                   (SELECT string_agg(DISTINCT cat.name, ', ')
                    FROM transaction_items ti2
                    JOIN transactions t2 ON t2.id = ti2.transaction_id
                    JOIN products p2 ON p2.id = ti2.product_id
                    JOIN categories cat ON cat.id = p2.category_id
                    WHERE t2.customer_id = c.id AND t2.store_id = ?
                    AND t2.created_at >= NOW() - INTERVAL '60 days'
                   ) as favorite_categories
            FROM customers c
            LEFT JOIN customer_groups cg ON cg.id = c.group_id
            WHERE c.organization_id = ? AND c.visit_count >= 2
            ORDER BY c.total_spend DESC
            LIMIT 200
        ", [$storeId, $organizationId]);

        if (empty($customerData)) {
            return ['promotions' => [], 'message' => 'Not enough customer data for personalized promotions'];
        }

        $topProducts = DB::select("
            SELECT p.name, p.name_ar, p.sell_price, p.cost_price,
                   c.name as category,
                   SUM(ti.quantity) as total_sold, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            GROUP BY p.id, p.name, p.name_ar, p.sell_price, p.cost_price, c.name
            ORDER BY total_sold DESC LIMIT 20
        ", [$storeId]);

        $activePromotions = DB::select("
            SELECT name, type, discount_value, valid_from, valid_to, usage_count
            FROM promotions
            WHERE organization_id = ? AND is_active = true
              AND (valid_to IS NULL OR valid_to >= NOW())
            ORDER BY usage_count DESC LIMIT 10
        ", [$organizationId]);

        $context = [
            'customer_data' => json_encode($customerData, JSON_UNESCAPED_UNICODE),
            'top_products' => json_encode($topProducts, JSON_UNESCAPED_UNICODE),
            'active_promotions' => json_encode($activePromotions, JSON_UNESCAPED_UNICODE),
            'segment' => $segment ?? 'all',
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
