<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class CashFlowPredictionService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'cashflow_prediction'; }

    public function predict(string $storeId, string $organizationId, int $days = 30, ?string $userId = null): ?array
    {
        $salesHistory = DB::select("
            SELECT DATE(created_at) as date, SUM(total_amount) as revenue
            FROM transactions WHERE store_id = ? AND status = 'completed'
              AND created_at >= NOW() - INTERVAL '90 days'
            GROUP BY DATE(created_at) ORDER BY date
        ", [$storeId]);

        $expenseHistory = DB::select("
            SELECT DATE(created_at) as date, SUM(amount) as total_expense
            FROM expenses WHERE store_id = ? AND created_at >= NOW() - INTERVAL '90 days'
            GROUP BY DATE(created_at) ORDER BY date
        ", [$storeId]);

        $upcomingPayments = DB::select("
            SELECT po.id, po.total_amount, po.expected_delivery_date
            FROM purchase_orders po
            WHERE po.store_id = ? AND po.status IN ('pending', 'approved')
              AND po.expected_delivery_date >= NOW()
            ORDER BY po.expected_delivery_date
        ", [$storeId]);

        $context = [
            'forecast_days' => $days,
            'sales_history' => json_encode($salesHistory, JSON_UNESCAPED_UNICODE),
            'expense_history' => json_encode($expenseHistory, JSON_UNESCAPED_UNICODE),
            'upcoming_payments' => json_encode($upcomingPayments, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
