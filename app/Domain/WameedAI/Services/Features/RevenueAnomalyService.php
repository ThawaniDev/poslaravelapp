<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class RevenueAnomalyService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'revenue_anomaly';
    }

    public function detect(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $dailyRevenue = DB::select("
            SELECT DATE(created_at) as sale_date,
                   EXTRACT(DOW FROM created_at) as day_of_week,
                   SUM(total_amount) as daily_revenue,
                   COUNT(*) as transaction_count,
                   AVG(total_amount) as avg_basket,
                   SUM(CASE WHEN type = 'return' THEN total_amount ELSE 0 END) as return_total,
                   SUM(CASE WHEN type = 'return' THEN 1 ELSE 0 END) as return_count,
                   SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) as void_count,
                   SUM(discount_amount) as total_discounts
            FROM transactions
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '60 days'
            GROUP BY DATE(created_at), EXTRACT(DOW FROM created_at)
            ORDER BY sale_date
        ", [$storeId]);

        if (empty($dailyRevenue)) {
            return ['anomalies' => [], 'message' => 'Not enough revenue data for anomaly detection'];
        }

        $cashEvents = DB::select("
            SELECT DATE(created_at) as event_date, type as event_type, COUNT(*) as count,
                   SUM(amount) as total_amount
            FROM cash_events
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(created_at), type
            ORDER BY event_date
        ", [$storeId]);

        $paymentAnomaly = DB::select("
            SELECT DATE(t.created_at) as date, pm.method,
                   SUM(pm.amount) as total, COUNT(*) as count
            FROM payments pm
            JOIN transactions t ON t.id = pm.transaction_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            GROUP BY DATE(t.created_at), pm.method
            ORDER BY date
        ", [$storeId]);

        $context = [
            'daily_revenue' => json_encode($dailyRevenue, JSON_UNESCAPED_UNICODE),
            'cash_events' => json_encode($cashEvents, JSON_UNESCAPED_UNICODE),
            'payment_method_daily' => json_encode($paymentAnomaly, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 360);
    }
}
