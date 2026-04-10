<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ChurnPredictionService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'churn_prediction'; }

    public function predict(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $atRiskCustomers = DB::select("
            SELECT c.id, c.name, c.phone, c.total_spend, c.visit_count,
                   c.last_visit_at, c.loyalty_points, c.store_credit_balance,
                   cg.name as customer_group,
                   EXTRACT(DAY FROM NOW() - c.last_visit_at) as days_since_last_visit,
                   COALESCE(recent.avg_basket, 0) as avg_basket_size,
                   COALESCE(recent.txn_count, 0) as recent_transactions,
                   COALESCE(recent.favorite_category, '') as favorite_category
            FROM customers c
            LEFT JOIN customer_groups cg ON cg.id = c.group_id
            LEFT JOIN LATERAL (
                SELECT AVG(t.total_amount) as avg_basket,
                       COUNT(t.id) as txn_count,
                       (SELECT cat.name FROM transaction_items ti2
                        JOIN products p2 ON p2.id = ti2.product_id
                        JOIN categories cat ON cat.id = p2.category_id
                        JOIN transactions t2 ON t2.id = ti2.transaction_id
                        WHERE t2.customer_id = c.id AND t2.store_id = ?
                        GROUP BY cat.name ORDER BY SUM(ti2.line_total) DESC LIMIT 1
                       ) as favorite_category
                FROM transactions t
                WHERE t.customer_id = c.id AND t.store_id = ?
                  AND t.created_at >= NOW() - INTERVAL '180 days' AND t.status = 'completed'
            ) recent ON true
            WHERE c.organization_id = ?
              AND c.visit_count >= 3
              AND c.last_visit_at < NOW() - INTERVAL '14 days'
            ORDER BY c.total_spend DESC
            LIMIT 100
        ", [$storeId, $storeId, $organizationId]);

        if (empty($atRiskCustomers)) {
            return ['at_risk' => [], 'total_revenue_at_risk' => 0, 'message' => 'No at-risk customers identified'];
        }

        $overallStats = DB::selectOne("
            SELECT AVG(visit_count) as avg_visits,
                   AVG(total_spend) as avg_spend,
                   AVG(EXTRACT(DAY FROM NOW() - last_visit_at)) as avg_days_since_visit
            FROM customers
            WHERE organization_id = ? AND visit_count > 0
        ", [$organizationId]);

        $context = [
            'at_risk_customers' => json_encode($atRiskCustomers, JSON_UNESCAPED_UNICODE),
            'overall_customer_stats' => json_encode($overallStats, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
