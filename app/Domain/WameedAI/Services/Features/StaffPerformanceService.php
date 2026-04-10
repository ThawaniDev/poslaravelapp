<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class StaffPerformanceService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'staff_performance'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $staffStats = DB::select("
            SELECT sm.id, sm.first_name, sm.last_name,
                   COUNT(t.id) as transaction_count,
                   COALESCE(SUM(t.total_amount), 0) as total_sales,
                   COALESCE(AVG(t.total_amount), 0) as avg_basket,
                   SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END) as void_count
            FROM staff_members sm
            LEFT JOIN transactions t ON t.cashier_id = sm.user_id AND t.store_id = ?
              AND t.created_at >= NOW() - INTERVAL '30 days'
            WHERE sm.store_id = ?
            GROUP BY sm.id, sm.first_name, sm.last_name
            ORDER BY total_sales DESC
        ", [$storeId, $storeId]);

        $attendance = DB::select("
            SELECT staff_member_id, COUNT(*) as days_present,
                   AVG(EXTRACT(EPOCH FROM (clock_out - clock_in)) / 3600) as avg_hours
            FROM attendance_records
            WHERE store_id = ? AND clock_in >= NOW() - INTERVAL '30 days' AND clock_out IS NOT NULL
            GROUP BY staff_member_id
        ", [$storeId]);

        $context = [
            'staff_stats' => json_encode($staffStats, JSON_UNESCAPED_UNICODE),
            'attendance' => json_encode($attendance, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
