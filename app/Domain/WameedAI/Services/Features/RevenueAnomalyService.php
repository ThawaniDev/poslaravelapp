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
        $dailyRevenue = DB::select("
            SELECT DATE(created_at) as sale_date,
                   SUM(total_amount) as daily_revenue,
                   COUNT(*) as transaction_count,
                   SUM(CASE WHEN type = 'return' THEN 1 ELSE 0 END) as return_count,
                   SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) as void_count
            FROM transactions
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '60 days'
            GROUP BY DATE(created_at)
            ORDER BY sale_date
        ", [$storeId]);

        $cashEvents = DB::select("
            SELECT DATE(created_at) as event_date, event_type, COUNT(*) as count,
                   SUM(amount) as total_amount
            FROM cash_events
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(created_at), event_type
            ORDER BY event_date
        ", [$storeId]);

        $context = [
            'daily_revenue' => json_encode($dailyRevenue, JSON_UNESCAPED_UNICODE),
            'cash_events' => json_encode($cashEvents, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 360);
    }
}
