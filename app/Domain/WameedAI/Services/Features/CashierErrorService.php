<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class CashierErrorService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'cashier_errors'; }

    public function detect(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $cashierStats = DB::select("
            SELECT u.id, u.name,
                   COUNT(t.id) as total_transactions,
                   SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END) as void_count,
                   CASE WHEN COUNT(t.id) > 0 THEN SUM(CASE WHEN t.status = 'voided' THEN 1 ELSE 0 END)::DECIMAL / COUNT(t.id) * 100 ELSE 0 END as void_rate
            FROM users u
            JOIN transactions t ON t.cashier_id = u.id AND t.store_id = ?
            WHERE t.created_at >= NOW() - INTERVAL '30 days'
            GROUP BY u.id, u.name
            HAVING COUNT(t.id) >= 10
            ORDER BY void_rate DESC
        ", [$storeId]);

        $cashVariances = DB::select("
            SELECT cs.user_id, u.name, cs.opening_amount, cs.closing_amount,
                   cs.expected_amount, (cs.closing_amount - cs.expected_amount) as variance
            FROM cash_sessions cs
            JOIN users u ON u.id = cs.user_id
            WHERE cs.store_id = ? AND cs.created_at >= NOW() - INTERVAL '30 days'
              AND cs.closing_amount IS NOT NULL
            ORDER BY ABS(cs.closing_amount - cs.expected_amount) DESC
            LIMIT 20
        ", [$storeId]);

        $context = [
            'cashier_stats' => json_encode($cashierStats, JSON_UNESCAPED_UNICODE),
            'cash_variances' => json_encode($cashVariances, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
