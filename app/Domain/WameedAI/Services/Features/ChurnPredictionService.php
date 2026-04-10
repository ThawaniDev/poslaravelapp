<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ChurnPredictionService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'churn_prediction'; }

    public function predict(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $atRiskCustomers = DB::select("
            SELECT c.id, c.name, c.phone, c.total_spend, c.visit_count,
                   c.last_visit_at,
                   EXTRACT(DAY FROM NOW() - c.last_visit_at) as days_since_last_visit,
                   AVG(t.total_amount) as avg_basket_size,
                   COUNT(t.id) as recent_transactions
            FROM customers c
            LEFT JOIN transactions t ON t.customer_id = c.id AND t.store_id = ?
              AND t.created_at >= NOW() - INTERVAL '90 days' AND t.status = 'completed'
            WHERE c.organization_id = ?
              AND c.visit_count >= 3
              AND c.last_visit_at < NOW() - INTERVAL '14 days'
            GROUP BY c.id, c.name, c.phone, c.total_spend, c.visit_count, c.last_visit_at
            ORDER BY days_since_last_visit DESC
            LIMIT 100
        ", [$storeId, $organizationId]);

        $context = [
            'at_risk_customers' => json_encode($atRiskCustomers, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
