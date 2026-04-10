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
        $currency = $this->getStoreCurrency($storeId);

        $historicalSales = DB::select("
            SELECT DATE(created_at) as sale_date,
                   EXTRACT(DOW FROM created_at) as day_of_week,
                   COUNT(*) as transaction_count,
                   SUM(total_amount) as total_sales,
                   AVG(total_amount) as avg_basket,
                   SUM(discount_amount) as total_discounts
            FROM transactions
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '90 days'
              AND status = 'completed'
            GROUP BY DATE(created_at), EXTRACT(DOW FROM created_at)
            ORDER BY sale_date
        ", [$storeId]);

        if (empty($historicalSales)) {
            return ['forecast' => [], 'message' => 'Not enough historical data for sales forecasting'];
        }

        $categorySales = DB::select("
            SELECT c.name, c.name_ar,
                   DATE(t.created_at) as sale_date,
                   SUM(ti.line_total) as category_revenue,
                   SUM(ti.quantity) as quantity_sold
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '90 days'
              AND t.status = 'completed'
            GROUP BY c.id, c.name, c.name_ar, DATE(t.created_at)
            ORDER BY sale_date
        ", [$storeId]);

        $upcomingPromotions = DB::select("
            SELECT name, type, discount_value, valid_from, valid_to
            FROM promotions
            WHERE organization_id = ? AND is_active = true
              AND valid_from >= NOW() AND valid_from <= NOW() + INTERVAL '{$days} days'
            ORDER BY valid_from
        ", [$organizationId]);

        $weeklyPattern = DB::select("
            SELECT EXTRACT(DOW FROM created_at) as day_of_week,
                   AVG(daily_total) as avg_revenue
            FROM (
                SELECT DATE(created_at) as d, EXTRACT(DOW FROM created_at) as dow_unused,
                       SUM(total_amount) as daily_total, created_at
                FROM transactions
                WHERE store_id = ? AND created_at >= NOW() - INTERVAL '90 days' AND status = 'completed'
                GROUP BY DATE(created_at), created_at
            ) sub
            GROUP BY EXTRACT(DOW FROM created_at)
            ORDER BY day_of_week
        ", [$storeId]);

        $context = [
            'forecast_days' => $days,
            'historical_sales' => json_encode($historicalSales, JSON_UNESCAPED_UNICODE),
            'category_breakdown' => json_encode($categorySales, JSON_UNESCAPED_UNICODE),
            'upcoming_promotions' => json_encode($upcomingPromotions, JSON_UNESCAPED_UNICODE),
            'weekly_pattern' => json_encode($weeklyPattern, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
