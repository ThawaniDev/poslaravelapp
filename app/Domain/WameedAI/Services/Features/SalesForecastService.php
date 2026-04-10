<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SalesForecastService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'sales_forecast';
    }

    public function getForecast(string $storeId, string $organizationId, int $days = 7, ?string $userId = null): ?array
    {
        $historicalSales = DB::select("
            SELECT DATE(created_at) as sale_date,
                   EXTRACT(DOW FROM created_at) as day_of_week,
                   COUNT(*) as transaction_count,
                   SUM(total_amount) as total_sales
            FROM transactions
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '90 days'
              AND status = 'completed'
            GROUP BY DATE(created_at), EXTRACT(DOW FROM created_at)
            ORDER BY sale_date
        ", [$storeId]);

        $categorySales = DB::select("
            SELECT c.name, c.name_ar,
                   DATE(t.created_at) as sale_date,
                   SUM(ti.line_total) as category_revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '90 days'
              AND t.status = 'completed'
            GROUP BY c.id, c.name, c.name_ar, DATE(t.created_at)
            ORDER BY sale_date
        ", [$storeId]);

        $context = [
            'forecast_days' => $days,
            'historical_sales' => json_encode($historicalSales, JSON_UNESCAPED_UNICODE),
            'category_breakdown' => json_encode($categorySales, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
