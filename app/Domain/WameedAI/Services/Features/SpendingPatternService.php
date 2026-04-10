<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SpendingPatternService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'spending_patterns'; }

    public function analyze(string $storeId, string $organizationId, string $customerId, ?string $userId = null): ?array
    {
        $customerTransactions = DB::select("
            SELECT t.id, t.total_amount, t.created_at,
                   EXTRACT(HOUR FROM t.created_at) as hour,
                   EXTRACT(DOW FROM t.created_at) as day_of_week
            FROM transactions t
            WHERE t.customer_id = ? AND t.store_id = ? AND t.status = 'completed'
            ORDER BY t.created_at DESC LIMIT 100
        ", [$customerId, $storeId]);

        $categoryBreakdown = DB::select("
            SELECT c.name, c.name_ar, SUM(ti.line_total) as spend, COUNT(*) as items
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.customer_id = ? AND t.store_id = ? AND t.status = 'completed'
            GROUP BY c.id, c.name, c.name_ar
            ORDER BY spend DESC
        ", [$customerId, $storeId]);

        $context = [
            'transactions' => json_encode($customerTransactions, JSON_UNESCAPED_UNICODE),
            'category_breakdown' => json_encode($categoryBreakdown, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
