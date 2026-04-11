<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SmartSearchService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'smart_search'; }

    public function search(string $storeId, string $organizationId, string $query, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        // Step 1: Parse intent from natural language query using AI
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

        // Step 2: Execute the intent against the database
        $data = $this->executeIntent($intent, $storeId, $organizationId);

        // Step 3: Format structured response for the Flutter UI
        return $this->formatResponse($intent, $data, $currency);
    }

    private function executeIntent(array $intent, string $storeId, string $organizationId): array
    {
        $type = $intent['intent_type'] ?? 'general_insight';
        $period = $intent['period'] ?? 'last_7_days';
        $sinceClause = $this->buildDateClause($period);

        return match ($type) {
            'total_revenue' => $this->queryTotalRevenue($storeId, $sinceClause),
            'top_products' => $this->queryTopProducts($storeId, $sinceClause),
            'product_sales' => $this->queryProductSales($storeId, $sinceClause, $intent['product_name'] ?? ''),
            'category_sales' => $this->queryCategorySales($storeId, $sinceClause, $intent['category'] ?? ''),
            'stock_check' => $this->queryStockCheck($storeId, $organizationId, $intent['product_name'] ?? ''),
            'transaction_count' => $this->queryTransactionCount($storeId, $sinceClause),
            'expense_summary' => $this->queryExpenseSummary($storeId, $period),
            'customer_info' => $this->queryCustomerInfo($organizationId, $intent['customer_name'] ?? ''),
            'staff_info' => $this->queryStaffInfo($storeId, $sinceClause),
            'general_insight' => $this->queryGeneralInsight($storeId, $sinceClause),
            default => ['message' => 'Query type not supported'],
        };
    }

    private function buildDateClause(string $period): string
    {
        return match ($period) {
            'today' => "DATE(t.created_at) = CURRENT_DATE",
            'yesterday' => "DATE(t.created_at) = CURRENT_DATE - 1",
            'last_7_days' => "t.created_at >= NOW() - INTERVAL '7 days'",
            'last_30_days' => "t.created_at >= NOW() - INTERVAL '30 days'",
            'this_month' => "EXTRACT(MONTH FROM t.created_at) = EXTRACT(MONTH FROM NOW()) AND EXTRACT(YEAR FROM t.created_at) = EXTRACT(YEAR FROM NOW())",
            'this_year' => "EXTRACT(YEAR FROM t.created_at) = EXTRACT(YEAR FROM NOW())",
            default => "t.created_at >= NOW() - INTERVAL '7 days'",
        };
    }

    private function formatResponse(array $intent, array $data, string $currency): array
    {
        $type = $intent['intent_type'] ?? 'unknown';
        $period = $intent['period'] ?? 'last_7_days';
        $periodLabel = $this->periodLabel($period);

        return match ($type) {
            'total_revenue' => [
                'intent' => $type,
                'period' => $periodLabel,
                'total_revenue' => "{$currency} " . number_format((float) ($data['total_revenue'] ?? 0), 2),
                'transactions' => (int) ($data['txn_count'] ?? 0),
                'avg_basket' => "{$currency} " . number_format((float) ($data['avg_basket'] ?? 0), 2),
            ],
            'top_products' => [
                'intent' => $type,
                'period' => $periodLabel,
                'products' => array_map(fn ($p) => [
                    'name' => $p->name_ar ?: $p->name,
                    'qty' => (int) $p->qty,
                    'revenue' => "{$currency} " . number_format((float) $p->revenue, 2),
                ], $data),
            ],
            'product_sales' => [
                'intent' => $type,
                'period' => $periodLabel,
                'products' => array_map(fn ($p) => [
                    'name' => $p->name_ar ?: $p->name,
                    'qty' => (int) $p->qty,
                    'revenue' => "{$currency} " . number_format((float) $p->revenue, 2),
                ], $data),
            ],
            'category_sales' => [
                'intent' => $type,
                'period' => $periodLabel,
                'categories' => array_map(fn ($c) => [
                    'name' => $c->category_name_ar ?: $c->category_name,
                    'products_sold' => (int) $c->qty,
                    'revenue' => "{$currency} " . number_format((float) $c->revenue, 2),
                ], $data),
            ],
            'stock_check' => [
                'intent' => $type,
                'products' => array_map(fn ($p) => [
                    'name' => $p->name_ar ?: $p->name,
                    'stock' => (int) $p->quantity,
                    'price' => "{$currency} " . number_format((float) $p->sell_price, 2),
                    'reorder_point' => (int) $p->reorder_point,
                ], $data),
            ],
            'transaction_count' => [
                'intent' => $type,
                'period' => $periodLabel,
                'transactions' => (int) ($data['count'] ?? 0),
                'total_amount' => "{$currency} " . number_format((float) ($data['total'] ?? 0), 2),
            ],
            'expense_summary' => [
                'intent' => $type,
                'period' => $periodLabel,
                'expenses' => array_map(fn ($e) => [
                    'name' => $e->category ?? 'Other',
                    'total' => "{$currency} " . number_format((float) $e->total, 2),
                    'count' => (int) $e->count,
                ], $data),
            ],
            'customer_info' => [
                'intent' => $type,
                'customers' => array_map(fn ($c) => [
                    'name' => $c->name,
                    'phone' => $c->phone ?? '-',
                    'total_spend' => "{$currency} " . number_format((float) ($c->total_spend ?? 0), 2),
                    'visits' => (int) ($c->visit_count ?? 0),
                ], $data),
            ],
            'staff_info' => [
                'intent' => $type,
                'period' => $periodLabel,
                'staff' => array_map(fn ($s) => [
                    'name' => $s->name,
                    'transactions' => (int) $s->txn_count,
                    'revenue' => "{$currency} " . number_format((float) $s->revenue, 2),
                ], $data),
            ],
            'general_insight' => [
                'intent' => $type,
                'period' => $periodLabel,
                'total_revenue' => "{$currency} " . number_format((float) ($data['total_revenue'] ?? 0), 2),
                'transactions' => (int) ($data['txn_count'] ?? 0),
                'top_products' => array_map(fn ($p) => [
                    'name' => $p->name_ar ?: $p->name,
                    'revenue' => "{$currency} " . number_format((float) $p->revenue, 2),
                ], $data['top_products'] ?? []),
            ],
            default => [
                'intent' => $type,
                'result' => $data,
            ],
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
            'this_year' => 'This Year',
            default => 'Last 7 Days',
        };
    }

    // ─── Query Methods ───────────────────────────────────────

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

    private function queryProductSales(string $storeId, string $where, string $productName): array
    {
        if (empty($productName)) {
            return $this->queryTopProducts($storeId, $where);
        }

        return DB::select("
            SELECT p.name, p.name_ar, SUM(ti.quantity) as qty, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.store_id = ? AND t.status = 'completed' AND {$where}
              AND (p.name ILIKE ? OR p.name_ar ILIKE ?)
            GROUP BY p.id, p.name, p.name_ar ORDER BY revenue DESC LIMIT 10
        ", [$storeId, "%{$productName}%", "%{$productName}%"]);
    }

    private function queryCategorySales(string $storeId, string $where, string $category): array
    {
        $categoryFilter = '';
        $params = [$storeId];
        if (!empty($category)) {
            $categoryFilter = "AND (c.name ILIKE ? OR c.name_ar ILIKE ?)";
            $params[] = "%{$category}%";
            $params[] = "%{$category}%";
        }

        return DB::select("
            SELECT c.name as category_name, c.name_ar as category_name_ar,
                   SUM(ti.quantity) as qty, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE t.store_id = ? AND t.status = 'completed' AND {$where} {$categoryFilter}
            GROUP BY c.id, c.name, c.name_ar ORDER BY revenue DESC LIMIT 10
        ", $params);
    }

    private function queryStockCheck(string $storeId, string $orgId, string $productName): array
    {
        if (empty($productName)) {
            return DB::select("
                SELECT p.name, p.name_ar, sl.quantity, p.sell_price, p.cost_price,
                       COALESCE(sl.reorder_point, 5) as reorder_point
                FROM products p
                JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
                WHERE p.organization_id = ? AND sl.quantity <= COALESCE(sl.reorder_point, 5)
                ORDER BY sl.quantity ASC LIMIT 10
            ", [$storeId, $orgId]);
        }

        return DB::select("
            SELECT p.name, p.name_ar, sl.quantity, p.sell_price, p.cost_price,
                   COALESCE(sl.reorder_point, 5) as reorder_point
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

    private function queryExpenseSummary(string $storeId, string $period): array
    {
        $interval = match ($period) {
            'today' => '1 day',
            'yesterday' => '2 days',
            'last_7_days' => '7 days',
            'last_30_days' => '30 days',
            'this_month' => '30 days',
            default => '7 days',
        };

        return DB::select("
            SELECT category, SUM(amount) as total, COUNT(*) as count
            FROM expenses WHERE store_id = ? AND expense_date >= NOW() - INTERVAL '{$interval}'
            GROUP BY category ORDER BY total DESC
        ", [$storeId]);
    }

    private function queryCustomerInfo(string $orgId, string $customerName): array
    {
        if (empty($customerName)) {
            return DB::select("
                SELECT name, phone, total_spend, visit_count, last_visit_at, loyalty_points
                FROM customers WHERE organization_id = ?
                ORDER BY total_spend DESC NULLS LAST LIMIT 10
            ", [$orgId]);
        }

        return DB::select("
            SELECT name, phone, total_spend, visit_count, last_visit_at, loyalty_points
            FROM customers WHERE organization_id = ? AND (name ILIKE ? OR phone ILIKE ?)
            LIMIT 10
        ", [$orgId, "%{$customerName}%", "%{$customerName}%"]);
    }

    private function queryStaffInfo(string $storeId, string $where): array
    {
        return DB::select("
            SELECT u.name, COUNT(t.id) as txn_count, COALESCE(SUM(t.total_amount), 0) as revenue
            FROM transactions t
            JOIN users u ON u.id = t.cashier_id
            WHERE t.store_id = ? AND t.status = 'completed' AND {$where}
            GROUP BY u.id, u.name ORDER BY revenue DESC LIMIT 10
        ", [$storeId]);
    }

    private function queryGeneralInsight(string $storeId, string $where): array
    {
        $revenue = DB::selectOne("
            SELECT COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as total_revenue,
                   COALESCE(AVG(total_amount), 0) as avg_basket
            FROM transactions t WHERE t.store_id = ? AND t.status = 'completed' AND {$where}
        ", [$storeId]);

        $topProducts = DB::select("
            SELECT p.name, p.name_ar, SUM(ti.quantity) as qty, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN products p ON p.id = ti.product_id
            WHERE t.store_id = ? AND t.status = 'completed' AND {$where}
            GROUP BY p.id, p.name, p.name_ar ORDER BY revenue DESC LIMIT 5
        ", [$storeId]);

        $result = (array) $revenue;
        $result['top_products'] = $topProducts;

        return $result;
    }
}
