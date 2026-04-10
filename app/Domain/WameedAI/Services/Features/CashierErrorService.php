<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class CashierErrorService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'cashier_errors'; }

    public function detect(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $cashierStats = DB::select("
            SELECT u.id, u.name,
                   COUNT(t.id) as total_transactions,
                   SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END) as void_count,
                   SUM(CASE WHEN t.type = 'return' THEN 1 ELSE 0 END) as return_count,
                   COALESCE(SUM(t.discount_amount), 0) as total_discounts_given,
                   COALESCE(SUM(t.total_amount), 0) as total_sales,
                   COALESCE(AVG(t.total_amount), 0) as avg_transaction,
                   CASE WHEN COUNT(t.id) > 0
                        THEN SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END)::DECIMAL / COUNT(t.id) * 100
                        ELSE 0 END as void_rate
            FROM users u
            JOIN transactions t ON t.cashier_id = u.id AND t.store_id = ?
            WHERE t.created_at >= NOW() - INTERVAL '30 days'
            GROUP BY u.id, u.name
            HAVING COUNT(t.id) >= 5
            ORDER BY void_rate DESC
        ", [$storeId]);

        if (empty($cashierStats)) {
            return ['cashier_issues' => [], 'message' => 'Not enough cashier transaction data'];
        }

        $cashVariances = DB::select("
            SELECT cs.user_id, u.name,
                   cs.opening_float, cs.actual_cash, cs.expected_cash,
                   (cs.actual_cash - cs.expected_cash) as variance,
                   cs.total_cash_sales, cs.total_refunds,
                   cs.closed_at
            FROM cash_sessions cs
            JOIN users u ON u.id = cs.user_id
            WHERE cs.store_id = ? AND cs.created_at >= NOW() - INTERVAL '30 days'
              AND cs.closed_at IS NOT NULL
            ORDER BY ABS(cs.actual_cash - cs.expected_cash) DESC
            LIMIT 30
        ", [$storeId]);

        $timePatterns = DB::select("
            SELECT u.name, EXTRACT(HOUR FROM t.created_at) as hour,
                   SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END) as voids
            FROM users u
            JOIN transactions t ON t.cashier_id = u.id AND t.store_id = ?
            WHERE t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'voided'
            GROUP BY u.name, EXTRACT(HOUR FROM t.created_at)
            HAVING SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END) > 0
            ORDER BY voids DESC LIMIT 20
        ", [$storeId]);

        $context = [
            'cashier_stats' => json_encode($cashierStats, JSON_UNESCAPED_UNICODE),
            'cash_variances' => json_encode($cashVariances, JSON_UNESCAPED_UNICODE),
            'void_time_patterns' => json_encode($timePatterns, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
