<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class PeakHoursService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'peak_hours';
    }

    public function analyze(string $storeId, string $organizationId, string $period = 'last_30_days', ?string $userId = null): ?array
    {
        $days = match ($period) {
            'last_7_days' => 7,
            'last_30_days' => 30,
            'last_90_days' => 90,
            default => 30,
        };

        $hourlyData = DB::select("
            SELECT EXTRACT(HOUR FROM created_at) as hour,
                   EXTRACT(DOW FROM created_at) as day_of_week,
                   COUNT(*) as transaction_count,
                   SUM(total_amount) as revenue
            FROM transactions
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '{$days} days'
              AND status = 'completed'
            GROUP BY EXTRACT(HOUR FROM created_at), EXTRACT(DOW FROM created_at)
            ORDER BY hour, day_of_week
        ", [$storeId]);

        $staffOnShift = DB::select("
            SELECT EXTRACT(DOW FROM clock_in) as day_of_week,
                   EXTRACT(HOUR FROM clock_in) as start_hour,
                   COUNT(DISTINCT staff_member_id) as staff_count
            FROM attendance_records
            WHERE store_id = ? AND clock_in >= NOW() - INTERVAL '{$days} days'
            GROUP BY EXTRACT(DOW FROM clock_in), EXTRACT(HOUR FROM clock_in)
        ", [$storeId]);

        $context = [
            'hourly_data' => json_encode($hourlyData, JSON_UNESCAPED_UNICODE),
            'staff_data' => json_encode($staffOnShift, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
