<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ExpenseAnalysisService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'expense_analysis'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $expenses = DB::select("
            SELECT category, SUM(amount) as total, COUNT(*) as count,
                   AVG(amount) as avg_amount, MAX(amount) as max_amount
            FROM expenses
            WHERE store_id = ? AND expense_date >= NOW() - INTERVAL '90 days'
            GROUP BY category
            ORDER BY total DESC
        ", [$storeId]);

        if (empty($expenses)) {
            return ['by_category' => [], 'message' => 'No expense data found'];
        }

        $monthlyTrend = DB::select("
            SELECT DATE_TRUNC('month', expense_date) as month, SUM(amount) as total
            FROM expenses WHERE store_id = ? AND expense_date >= NOW() - INTERVAL '12 months'
            GROUP BY DATE_TRUNC('month', expense_date) ORDER BY month
        ", [$storeId]);

        $revenue90d = DB::selectOne("
            SELECT COALESCE(SUM(total_amount), 0) as total_revenue
            FROM transactions
            WHERE store_id = ? AND status = 'completed' AND created_at >= NOW() - INTERVAL '90 days'
        ", [$storeId]);

        $totalExpenses = array_sum(array_map(fn ($e) => (float) $e->total, $expenses));

        $topExpenseItems = DB::select("
            SELECT description, amount, category, expense_date
            FROM expenses
            WHERE store_id = ? AND expense_date >= NOW() - INTERVAL '90 days'
            ORDER BY amount DESC LIMIT 10
        ", [$storeId]);

        $context = [
            'expense_by_category' => json_encode($expenses, JSON_UNESCAPED_UNICODE),
            'monthly_trend' => json_encode($monthlyTrend, JSON_UNESCAPED_UNICODE),
            'total_expenses_90d' => number_format($totalExpenses, 2),
            'total_revenue_90d' => number_format((float) $revenue90d->total_revenue, 2),
            'expense_to_revenue_pct' => $revenue90d->total_revenue > 0
                ? number_format($totalExpenses / $revenue90d->total_revenue * 100, 1) : 'N/A',
            'top_expense_items' => json_encode($topExpenseItems, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
