<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class EfficiencyScoreService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'efficiency_score'; }

    public function calculate(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $salesMetrics = DB::selectOne("
            SELECT COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as revenue,
                   COALESCE(AVG(total_amount), 0) as avg_basket,
                   SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) as void_count,
                   SUM(CASE WHEN type = 'return' THEN 1 ELSE 0 END) as return_count,
                   COALESCE(SUM(discount_amount), 0) as total_discounts
            FROM transactions WHERE store_id = ? AND created_at >= NOW() - INTERVAL '30 days'
        ", [$storeId]);

        $inventoryHealth = DB::selectOne("
            SELECT COUNT(*) as total_products,
                   SUM(CASE WHEN sl.quantity <= COALESCE(sl.reorder_point, 5) THEN 1 ELSE 0 END) as low_stock_count,
                   SUM(CASE WHEN sl.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                   COALESCE(SUM(sl.quantity * COALESCE(p.cost_price, 0)), 0) as total_inventory_value
            FROM stock_levels sl
            JOIN products p ON p.id = sl.product_id AND p.is_active = true
            WHERE sl.store_id = ?
        ", [$storeId]);

        $staffMetrics = DB::selectOne("
            SELECT COUNT(DISTINCT staff_member_id) as active_staff,
                   AVG(EXTRACT(EPOCH FROM (clock_out - clock_in)) / 3600) as avg_hours,
                   SUM(EXTRACT(EPOCH FROM (clock_out - clock_in)) / 3600) as total_hours
            FROM attendance_records
            WHERE store_id = ? AND clock_in >= NOW() - INTERVAL '30 days' AND clock_out IS NOT NULL
        ", [$storeId]);

        $cashVariance = DB::selectOne("
            SELECT COUNT(*) as sessions,
                   AVG(ABS(actual_cash - expected_cash)) as avg_variance,
                   SUM(ABS(actual_cash - expected_cash)) as total_variance
            FROM cash_sessions
            WHERE store_id = ? AND closed_at >= NOW() - INTERVAL '30 days' AND closed_at IS NOT NULL
        ", [$storeId]);

        $customerMetrics = DB::selectOne("
            SELECT COUNT(DISTINCT customer_id) as unique_customers,
                   COUNT(DISTINCT CASE WHEN customer_id IS NOT NULL THEN customer_id END) as identified_customers
            FROM transactions
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '30 days' AND status = 'completed'
        ", [$storeId]);

        $context = [
            'sales' => json_encode($salesMetrics, JSON_UNESCAPED_UNICODE),
            'inventory' => json_encode($inventoryHealth, JSON_UNESCAPED_UNICODE),
            'staff' => json_encode($staffMetrics, JSON_UNESCAPED_UNICODE),
            'cash_variance' => json_encode($cashVariance, JSON_UNESCAPED_UNICODE),
            'customer_metrics' => json_encode($customerMetrics, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
