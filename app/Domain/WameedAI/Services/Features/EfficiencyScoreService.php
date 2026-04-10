<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class EfficiencyScoreService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'efficiency_score'; }

    public function calculate(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $salesMetrics = DB::selectOne("
            SELECT COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as revenue,
                   COALESCE(AVG(total_amount), 0) as avg_basket
            FROM transactions WHERE store_id = ? AND status = 'completed'
              AND created_at >= NOW() - INTERVAL '30 days'
        ", [$storeId]);

        $inventoryHealth = DB::selectOne("
            SELECT COUNT(*) as total_products,
                   SUM(CASE WHEN sl.quantity <= COALESCE(sl.reorder_point, 5) THEN 1 ELSE 0 END) as low_stock_count,
                   SUM(CASE WHEN sl.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count
            FROM stock_levels sl
            JOIN products p ON p.id = sl.product_id AND p.is_active = true
            WHERE sl.store_id = ?
        ", [$storeId]);

        $staffMetrics = DB::selectOne("
            SELECT COUNT(DISTINCT staff_member_id) as active_staff,
                   AVG(EXTRACT(EPOCH FROM (clock_out - clock_in)) / 3600) as avg_hours
            FROM attendance_records
            WHERE store_id = ? AND clock_in >= NOW() - INTERVAL '30 days' AND clock_out IS NOT NULL
        ", [$storeId]);

        $context = [
            'sales' => json_encode($salesMetrics, JSON_UNESCAPED_UNICODE),
            'inventory' => json_encode($inventoryHealth, JSON_UNESCAPED_UNICODE),
            'staff' => json_encode($staffMetrics, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
