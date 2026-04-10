<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class CustomerSegmentationService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'customer_segmentation'; }

    public function segment(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $customers = DB::select("
            SELECT c.name, c.phone, c.total_spend, c.visit_count,
                   c.last_visit_at, c.created_at, c.loyalty_points,
                   c.store_credit_balance, c.date_of_birth,
                   cg.name as customer_group,
                   EXTRACT(DAY FROM NOW() - c.last_visit_at) as days_since_last_visit,
                   CASE WHEN c.visit_count > 0 THEN c.total_spend / c.visit_count ELSE 0 END as avg_basket
            FROM customers c
            LEFT JOIN customer_groups cg ON cg.id = c.group_id
            WHERE c.organization_id = ?
              AND c.visit_count > 0
            ORDER BY c.total_spend DESC
            LIMIT 500
        ", [$organizationId]);

        if (empty($customers)) {
            return ['segments' => [], 'message' => 'No customer data available for segmentation'];
        }

        $topCategories = DB::select("
            SELECT c.name as customer_name, cat.name as category, SUM(ti.line_total) as spend
            FROM customers c
            JOIN transactions t ON t.customer_id = c.id AND t.store_id = ?
            JOIN transaction_items ti ON ti.transaction_id = t.id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories cat ON cat.id = p.category_id
            WHERE c.organization_id = ? AND t.status = 'completed'
              AND t.created_at >= NOW() - INTERVAL '90 days'
            GROUP BY c.name, cat.name
            ORDER BY spend DESC
        ", [$storeId, $organizationId]);

        $loyaltyActivity = DB::select("
            SELECT c.name as customer_name, lt.type, SUM(lt.points) as total_points, COUNT(*) as count
            FROM loyalty_transactions lt
            JOIN customers c ON c.id = lt.customer_id
            WHERE c.organization_id = ? AND lt.created_at >= NOW() - INTERVAL '90 days'
            GROUP BY c.name, lt.type
        ", [$organizationId]);

        $context = [
            'customers' => json_encode($customers, JSON_UNESCAPED_UNICODE),
            'total_customers' => count($customers),
            'category_preferences' => json_encode($topCategories, JSON_UNESCAPED_UNICODE),
            'loyalty_activity' => json_encode($loyaltyActivity, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
