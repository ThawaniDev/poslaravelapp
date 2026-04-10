<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class StaffPerformanceService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'staff_performance'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $staffStats = DB::select("
            SELECT u.name,
                   COUNT(t.id) as transaction_count,
                   COALESCE(SUM(t.total_amount), 0) as total_sales,
                   COALESCE(AVG(t.total_amount), 0) as avg_basket,
                   SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END) as void_count,
                   SUM(CASE WHEN t.type = 'return' THEN 1 ELSE 0 END) as return_count,
                   COALESCE(SUM(t.discount_amount), 0) as total_discounts_given,
                   COUNT(DISTINCT DATE(t.created_at)) as days_worked
            FROM users u
            LEFT JOIN transactions t ON t.cashier_id = u.id AND t.store_id = ?
              AND t.created_at >= NOW() - INTERVAL '30 days'
            WHERE u.store_id = ?
            GROUP BY u.id, u.name
            ORDER BY total_sales DESC
        ", [$storeId, $storeId]);

        if (empty($staffStats)) {
            return ['rankings' => [], 'message' => 'No staff performance data available'];
        }

        $attendance = DB::select("
            SELECT staff_user_id, COUNT(*) as days_present,
                   SUM(EXTRACT(EPOCH FROM (clock_out_at - clock_in_at)) / 3600) as total_hours,
                   AVG(EXTRACT(EPOCH FROM (clock_out_at - clock_in_at)) / 3600) as avg_hours,
                   SUM(COALESCE(overtime_minutes, 0)) as total_overtime_minutes,
                   SUM(COALESCE(break_minutes, 0)) as total_break_minutes,
                   MIN(clock_in_at) as first_shift, MAX(clock_out_at) as last_shift
            FROM attendance_records
            WHERE store_id = ? AND clock_in_at >= NOW() - INTERVAL '30 days' AND clock_out_at IS NOT NULL
            GROUP BY staff_user_id
        ", [$storeId]);

        $commissions = DB::select("
            SELECT ce.staff_user_id,
                   COALESCE(SUM(ce.commission_amount), 0) as total_commission,
                   COUNT(*) as commission_count,
                   COALESCE(SUM(ce.order_total), 0) as commission_order_total
            FROM commission_earnings ce
            JOIN transactions t ON t.id = ce.order_id
            WHERE t.store_id = ? AND ce.created_at >= NOW() - INTERVAL '30 days'
            GROUP BY ce.staff_user_id
        ", [$storeId]);

        $cashVariances = DB::select("
            SELECT cs.opened_by as user_id, AVG(ABS(cs.actual_cash - cs.expected_cash)) as avg_variance,
                   COUNT(*) as sessions
            FROM cash_sessions cs
            WHERE cs.store_id = ? AND cs.closed_at >= NOW() - INTERVAL '30 days' AND cs.closed_at IS NOT NULL
            GROUP BY cs.opened_by
        ", [$storeId]);

        $context = [
            'staff_stats' => json_encode($staffStats, JSON_UNESCAPED_UNICODE),
            'attendance' => json_encode($attendance, JSON_UNESCAPED_UNICODE),
            'commissions' => json_encode($commissions, JSON_UNESCAPED_UNICODE),
            'cash_variances' => json_encode($cashVariances, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
