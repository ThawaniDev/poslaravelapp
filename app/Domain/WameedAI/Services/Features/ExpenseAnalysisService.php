<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ExpenseAnalysisService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'expense_analysis'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $expenses = DB::select("
            SELECT category, SUM(amount) as total, COUNT(*) as count,
                   AVG(amount) as avg_amount
            FROM expenses
            WHERE store_id = ? AND created_at >= NOW() - INTERVAL '90 days'
            GROUP BY category
            ORDER BY total DESC
        ", [$storeId]);

        $monthlyTrend = DB::select("
            SELECT DATE_TRUNC('month', created_at) as month, SUM(amount) as total
            FROM expenses WHERE store_id = ? AND created_at >= NOW() - INTERVAL '12 months'
            GROUP BY DATE_TRUNC('month', created_at) ORDER BY month
        ", [$storeId]);

        $context = [
            'expense_by_category' => json_encode($expenses, JSON_UNESCAPED_UNICODE),
            'monthly_trend' => json_encode($monthlyTrend, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
