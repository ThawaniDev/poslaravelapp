<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SeasonalPlanningService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'seasonal_planning'; }

    public function plan(string $storeId, string $organizationId, string $season = 'ramadan', ?string $userId = null): ?array
    {
        $historicalSales = DB::select("
            SELECT p.id, p.name, p.name_ar, c.name as category,
                   SUM(ti.quantity) as total_sold, SUM(ti.line_total) as total_revenue,
                   EXTRACT(MONTH FROM t.created_at) as month
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND t.status = 'completed'
              AND t.created_at >= NOW() - INTERVAL '365 days'
            GROUP BY p.id, p.name, p.name_ar, c.name, EXTRACT(MONTH FROM t.created_at)
            ORDER BY total_revenue DESC
            LIMIT 200
        ", [$storeId]);

        $currentStock = DB::select("
            SELECT p.id, p.name, sl.quantity as current_stock
            FROM stock_levels sl
            JOIN products p ON p.id = sl.product_id
            WHERE sl.store_id = ? AND p.is_active = true AND sl.quantity > 0
        ", [$storeId]);

        $context = [
            'monthly_sales' => json_encode($historicalSales, JSON_UNESCAPED_UNICODE),
            'category_trends' => json_encode($currentStock, JSON_UNESCAPED_UNICODE),
            'current_month' => now()->format('F Y'),
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
