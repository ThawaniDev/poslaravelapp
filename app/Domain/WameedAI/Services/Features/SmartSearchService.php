<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SmartSearchService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'smart_search'; }

    /**
     * Two-step approach:
     * 1. AI parses user query into structured intent
     * 2. Execute pre-built SQL for that intent type (never dynamic SQL)
     */
    public function search(string $storeId, string $organizationId, string $query, ?string $userId = null): ?array
    {
        // Step 1: Parse intent via AI
        $intentContext = [
            'user_query' => $query,
            'available_intents' => json_encode([
                'product_sales' => 'How much of product X was sold in period Y',
                'category_sales' => 'Sales for category in period',
                'total_revenue' => 'Total revenue for period',
                'top_products' => 'Best selling products in period',
                'stock_check' => 'Current stock level for product',
                'customer_info' => 'Customer purchase history or info',
                'transaction_count' => 'Number of transactions in period',
                'expense_summary' => 'Expense breakdown for period',
                'staff_info' => 'Staff performance or attendance',
                'general_insight' => 'General business question',
            ]),
        ];

        $intent = $this->callAI($storeId, $organizationId, $intentContext, $userId, cacheTtlMinutes: 0);
        if (!$intent || !isset($intent['intent_type'])) {
            return ['answer' => 'عذراً، لم أتمكن من فهم السؤال. حاول مرة أخرى بصياغة مختلفة.', 'intent' => 'unknown'];
        }

        // Step 2: Execute predefined SQL based on intent
        $data = $this->executeIntent($intent, $storeId, $organizationId);

        // Step 3: Format response
        $responseContext = [
            'user_query' => $query,
            'intent' => $intent['intent_type'],
            'raw_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'currency' => 'SAR',
        ];

        $response = $this->gateway->call(
            featureSlug: 'smart_search',
            storeId: $storeId,
            organizationId: $organizationId,
            contextData: $responseContext,
            userId: $userId,
            cacheKeyOverride: "smart_search:{$storeId}:" . md5($query),
            cacheTtlMinutes: 5,
        );

        return $response ?? ['answer' => json_encode($data, JSON_UNESCAPED_UNICODE), 'data' => $data];
    }

    private function executeIntent(array $intent, string $storeId, string $organizationId): array
    {
        $type = $intent['intent_type'] ?? 'general_insight';
        $period = $intent['period'] ?? 'last_7_days';
        $sinceClause = match ($period) {
            'today' => "DATE(t.created_at) = CURRENT_DATE",
            'yesterday' => "DATE(t.created_at) = CURRENT_DATE - 1",
            'last_7_days' => "t.created_at >= NOW() - INTERVAL '7 days'",
            'last_30_days' => "t.created_at >= NOW() - INTERVAL '30 days'",
            'this_month' => "EXTRACT(MONTH FROM t.created_at) = EXTRACT(MONTH FROM NOW()) AND EXTRACT(YEAR FROM t.created_at) = EXTRACT(YEAR FROM NOW())",
            default => "t.created_at >= NOW() - INTERVAL '7 days'",
        };

        return match ($type) {
            'total_revenue' => $this->queryTotalRevenue($storeId, $sinceClause),
            'top_products' => $this->queryTopProducts($storeId, $sinceClause),
            'stock_check' => $this->queryStockCheck($storeId, $organizationId, $intent['product_name'] ?? ''),
            'transaction_count' => $this->queryTransactionCount($storeId, $sinceClause),
            default => ['message' => 'Query type not supported for direct lookup'],
        };
    }

    private function queryTotalRevenue(string $storeId, string $where): array
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as total_revenue,
                   COALESCE(AVG(total_amount), 0) as avg_basket
            FROM transactions t WHERE t.store_id = ? AND t.status = 'completed' AND {$where}
        ", [$storeId]);
        return (array) $result;
    }

    private function queryTopProducts(string $storeId, string $where): array
    {
        return DB::select("
            SELECT p.name, p.name_ar, SUM(ti.quantity) as qty, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.store_id = ? AND t.status = 'completed' AND {$where}
            GROUP BY p.id, p.name, p.name_ar ORDER BY revenue DESC LIMIT 10
        ", [$storeId]);
    }

    private function queryStockCheck(string $storeId, string $orgId, string $productName): array
    {
        return DB::select("
            SELECT p.name, p.name_ar, sl.quantity, p.sell_price
            FROM products p
            JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            WHERE p.organization_id = ? AND (p.name ILIKE ? OR p.name_ar ILIKE ?)
        ", [$storeId, $orgId, "%{$productName}%", "%{$productName}%"]);
    }

    private function queryTransactionCount(string $storeId, string $where): array
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM transactions t WHERE t.store_id = ? AND t.status = 'completed' AND {$where}
        ", [$storeId]);
        return (array) $result;
    }
}
