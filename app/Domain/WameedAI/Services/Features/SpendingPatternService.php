<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SpendingPatternService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'spending_patterns'; }

    public function analyze(string $storeId, string $organizationId, string $customerId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $customer = DB::selectOne("
            SELECT c.name, c.phone, c.total_spend, c.visit_count,
                   c.last_visit_at, c.created_at, c.loyalty_points,
                   c.store_credit_balance, c.date_of_birth,
                   cg.name as customer_group
            FROM customers c
            LEFT JOIN customer_groups cg ON cg.id = c.customer_group_id
            WHERE c.id = ?
        ", [$customerId]);

        $customerTransactions = DB::select("
            SELECT t.id, t.total_amount, t.discount_amount, t.created_at,
                   EXTRACT(HOUR FROM t.created_at) as hour,
                   EXTRACT(DOW FROM t.created_at) as day_of_week,
                   COUNT(ti.id) as item_count
            FROM transactions t
            LEFT JOIN transaction_items ti ON ti.transaction_id = t.id
            WHERE t.customer_id = ? AND t.store_id = ? AND t.status = 'completed'
            GROUP BY t.id, t.total_amount, t.discount_amount, t.created_at
            ORDER BY t.created_at DESC LIMIT 100
        ", [$customerId, $storeId]);

        if (empty($customerTransactions)) {
            return ['patterns' => [], 'message' => 'No transaction history for this customer'];
        }

        $categoryBreakdown = DB::select("
            SELECT c.name, c.name_ar, SUM(ti.line_total) as spend, COUNT(*) as items,
                   SUM(ti.quantity) as total_quantity
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.customer_id = ? AND t.store_id = ? AND t.status = 'completed'
            GROUP BY c.id, c.name, c.name_ar
            ORDER BY spend DESC
        ", [$customerId, $storeId]);

        $topProducts = DB::select("
            SELECT p.name, p.name_ar, SUM(ti.quantity) as qty, SUM(ti.line_total) as spend
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.customer_id = ? AND t.store_id = ? AND t.status = 'completed'
            GROUP BY p.id, p.name, p.name_ar
            ORDER BY spend DESC LIMIT 10
        ", [$customerId, $storeId]);

        $loyaltyHistory = DB::select("
            SELECT type, SUM(points) as total_points, COUNT(*) as count
            FROM loyalty_transactions
            WHERE customer_id = ? AND created_at >= NOW() - INTERVAL '90 days'
            GROUP BY type
        ", [$customerId]);

        $context = [
            'customer_info' => json_encode($customer, JSON_UNESCAPED_UNICODE),
            'transactions' => json_encode($customerTransactions, JSON_UNESCAPED_UNICODE),
            'category_breakdown' => json_encode($categoryBreakdown, JSON_UNESCAPED_UNICODE),
            'top_products' => json_encode($topProducts, JSON_UNESCAPED_UNICODE),
            'loyalty_history' => json_encode($loyaltyHistory, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
