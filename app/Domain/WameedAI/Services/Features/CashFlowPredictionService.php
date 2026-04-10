<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class CashFlowPredictionService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'cashflow_prediction'; }

    public function predict(string $storeId, string $organizationId, int $days = 30, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $salesHistory = DB::select("
            SELECT DATE(created_at) as date,
                   SUM(total_amount) as revenue,
                   COUNT(*) as txn_count,
                   EXTRACT(DOW FROM created_at) as day_of_week
            FROM transactions WHERE store_id = ? AND status = 'completed'
              AND created_at >= NOW() - INTERVAL '90 days'
            GROUP BY DATE(created_at), EXTRACT(DOW FROM created_at) ORDER BY date
        ", [$storeId]);

        if (empty($salesHistory)) {
            return ['forecast' => [], 'message' => 'Not enough sales history for cash flow prediction'];
        }

        $expenseHistory = DB::select("
            SELECT DATE(expense_date) as date, SUM(amount) as total_expense, category
            FROM expenses WHERE store_id = ? AND expense_date >= NOW() - INTERVAL '90 days'
            GROUP BY DATE(expense_date), category ORDER BY date
        ", [$storeId]);

        $upcomingPayments = DB::select("
            SELECT po.id, po.total_cost as total_amount, po.expected_delivery_date,
                   s.name as supplier_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            WHERE po.store_id = ? AND po.status IN ('pending', 'approved', 'ordered')
              AND po.expected_delivery_date >= NOW()
            ORDER BY po.expected_delivery_date LIMIT 20
        ", [$storeId]);

        $paymentMethodSplit = DB::select("
            SELECT pm.method, SUM(pm.amount) as total, COUNT(*) as count
            FROM payments pm
            JOIN transactions t ON t.id = pm.transaction_id
            WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '30 days' AND t.status = 'completed'
            GROUP BY pm.method ORDER BY total DESC
        ", [$storeId]);

        $latestCashSession = DB::selectOne("
            SELECT opening_float, expected_cash, actual_cash, variance,
                   total_cash_sales, total_card_sales, total_refunds
            FROM cash_sessions
            WHERE store_id = ? AND closed_at IS NOT NULL
            ORDER BY closed_at DESC LIMIT 1
        ", [$storeId]);

        $recurringExpenses = DB::select("
            SELECT category, AVG(amount) as avg_amount,
                   COUNT(*) as occurrences,
                   MAX(expense_date) as last_occurrence
            FROM expenses WHERE store_id = ? AND expense_date >= NOW() - INTERVAL '90 days'
            GROUP BY category HAVING COUNT(*) >= 2
            ORDER BY avg_amount DESC
        ", [$storeId]);

        $context = [
            'forecast_days' => $days,
            'sales_history' => json_encode($salesHistory, JSON_UNESCAPED_UNICODE),
            'expense_history' => json_encode($expenseHistory, JSON_UNESCAPED_UNICODE),
            'upcoming_payments' => json_encode($upcomingPayments, JSON_UNESCAPED_UNICODE),
            'payment_method_split' => json_encode($paymentMethodSplit, JSON_UNESCAPED_UNICODE),
            'latest_cash_session' => json_encode($latestCashSession, JSON_UNESCAPED_UNICODE),
            'recurring_expenses' => json_encode($recurringExpenses, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
