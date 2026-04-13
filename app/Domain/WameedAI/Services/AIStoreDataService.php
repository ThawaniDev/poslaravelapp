<?php

namespace App\Domain\WameedAI\Services;

use Illuminate\Support\Facades\DB;

class AIStoreDataService
{
    /**
     * Build a comprehensive store context array for AI features.
     * Returns key-value pairs suitable for template interpolation ({{key}}).
     */
    public function getStoreContext(string $storeId, string $organizationId): array
    {
        $thirtyDaysAgo = now()->subDays(30)->toDateTimeString();
        $today = now()->toDateString();
        $thirtyDaysFromNow = now()->addDays(30)->toDateString();

        // ─── Store Info ─────────────────────────────────
        $store = DB::selectOne("SELECT name, name_ar, currency, city, timezone, is_main_branch FROM stores WHERE id = ?", [$storeId]);
        $storeName = $store->name ?? 'Unknown Store';
        $storeNameAr = $store->name_ar ?? '';
        $currency = $store->currency ?? 'OMR';
        $city = $store->city ?? '';
        $timezone = $store->timezone ?? 'Asia/Muscat';
        $isMainBranch = $store->is_main_branch ?? false;

        // Organization info
        $org = DB::selectOne("SELECT name, business_type FROM organizations WHERE id = ?", [$organizationId]);
        $orgName = $org->name ?? '';
        $businessType = $org->business_type ?? 'retail';

        // Total branches in org
        $branchCount = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM stores WHERE organization_id = ?", [$organizationId])->cnt ?? 1);

        // ─── Sales Snapshot (last 30 days) ──────────────
        $salesStats = DB::selectOne("
            SELECT
                COUNT(*) as total_transactions,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_transaction,
                COALESCE(MAX(total_amount), 0) as max_transaction,
                COALESCE(SUM(discount_amount), 0) as total_discounts,
                COALESCE(SUM(tax_amount), 0) as total_tax
            FROM transactions
            WHERE store_id = ? AND status = 'completed' AND created_at >= ?
        ", [$storeId, $thirtyDaysAgo]);

        // Today's sales
        $todaySales = DB::selectOne("
            SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total
            FROM transactions
            WHERE store_id = ? AND status = 'completed' AND created_at >= ?
        ", [$storeId, $today]);

        // ─── Top 10 Products (last 30 days by revenue) ──
        $topProducts = DB::select("
            SELECT ti.product_name, SUM(ti.quantity) as qty_sold, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            WHERE t.store_id = ? AND t.status = 'completed' AND t.created_at >= ?
            GROUP BY ti.product_name
            ORDER BY revenue DESC
            LIMIT 10
        ", [$storeId, $thirtyDaysAgo]);

        $topProductsText = '';
        foreach ($topProducts as $i => $p) {
            $n = $i + 1;
            $qty = round($p->qty_sold, 1);
            $rev = number_format($p->revenue, 2);
            $topProductsText .= "  {$n}. {$p->product_name}: {$qty} units, {$currency} {$rev}\n";
        }

        // ─── Inventory Snapshot ─────────────────────────
        $inventory = DB::selectOne("
            SELECT
                COUNT(*) as total_skus,
                COALESCE(SUM(quantity), 0) as total_units,
                COUNT(CASE WHEN quantity <= reorder_point AND reorder_point > 0 THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_count
            FROM stock_levels
            WHERE store_id = ?
        ", [$storeId]);

        // Expiring within 30 days
        $expiringCount = (int) (DB::selectOne("
            SELECT COUNT(*) as cnt FROM stock_batches
            WHERE store_id = ? AND expiry_date IS NOT NULL AND expiry_date <= ? AND expiry_date >= ? AND quantity > 0
        ", [$storeId, $thirtyDaysFromNow, $today])->cnt ?? 0);

        // ─── Categories ─────────────────────────────────
        $categories = DB::select("
            SELECT name FROM categories WHERE organization_id = ? AND is_active = 1 AND parent_id IS NULL ORDER BY sort_order LIMIT 20
        ", [$organizationId]);
        $categoryNames = implode(', ', array_map(fn ($c) => $c->name, $categories));

        // ─── Customers ──────────────────────────────────
        $customers = DB::selectOne("
            SELECT
                COUNT(*) as total_customers,
                COALESCE(SUM(total_spend), 0) as lifetime_spend,
                COALESCE(AVG(visit_count), 0) as avg_visits,
                COUNT(CASE WHEN last_visit_at >= ? THEN 1 END) as active_30d
            FROM customers
            WHERE organization_id = ?
        ", [$thirtyDaysAgo, $organizationId]);

        // ─── Staff ──────────────────────────────────────
        $staffCount = (int) (DB::selectOne("
            SELECT COUNT(*) as cnt FROM staff_users WHERE store_id = ? AND status = 'active'
        ", [$storeId])->cnt ?? 0);

        // ─── Products ───────────────────────────────────
        $productStats = DB::selectOne("
            SELECT
                COUNT(*) as total_products,
                COALESCE(AVG(sell_price), 0) as avg_price,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_count
            FROM products
            WHERE organization_id = ?
        ", [$organizationId]);

        // ─── Payment Methods (last 30 days) ─────────────
        $paymentMethods = DB::select("
            SELECT p.method, COUNT(*) as cnt, SUM(p.amount) as total
            FROM payments p
            JOIN transactions t ON t.id = p.transaction_id
            WHERE t.store_id = ? AND t.status = 'completed' AND t.created_at >= ?
            GROUP BY p.method ORDER BY total DESC
        ", [$storeId, $thirtyDaysAgo]);
        $paymentText = '';
        foreach ($paymentMethods as $pm) {
            $paymentText .= "  - {$pm->method}: {$pm->cnt} transactions, {$currency} " . number_format($pm->total, 2) . "\n";
        }

        // ─── Recent Expenses (last 30 days) ─────────────
        $expenses = DB::selectOne("
            SELECT COALESCE(SUM(amount), 0) as total_expenses, COUNT(*) as cnt
            FROM expenses WHERE store_id = ? AND expense_date >= ?
        ", [$storeId, $thirtyDaysAgo]);

        // ─── Active Promotions ──────────────────────────
        $activePromos = (int) (DB::selectOne("
            SELECT COUNT(*) as cnt FROM promotions
            WHERE organization_id = ? AND is_active = 1 AND (valid_to IS NULL OR valid_to >= ?)
        ", [$organizationId, $today])->cnt ?? 0);

        // ─── Build context for template interpolation ───
        return [
            'store_name' => $storeName,
            'store_name_ar' => $storeNameAr,
            'currency' => $currency,
            'city' => $city,
            'timezone' => $timezone,
            'is_main_branch' => $isMainBranch ? 'Yes' : 'No',
            'organization_name' => $orgName,
            'business_type' => $businessType,
            'branch_count' => $branchCount,

            'store_profile' => json_encode([
                'store_name' => $storeName,
                'store_name_ar' => $storeNameAr,
                'organization' => $orgName,
                'business_type' => $businessType,
                'city' => $city,
                'timezone' => $timezone,
                'currency' => $currency,
                'branch_count' => $branchCount,
                'is_main_branch' => $isMainBranch,
                'active_staff' => $staffCount,
                'active_promotions' => $activePromos,
                'product_categories' => $categoryNames,
            ], JSON_UNESCAPED_UNICODE),

            'sales_snapshot' => json_encode([
                'today_transactions' => $todaySales->cnt ?? 0,
                'today_revenue' => number_format((float) ($todaySales->total ?? 0), 2),
                'last_30d_transactions' => $salesStats->total_transactions ?? 0,
                'last_30d_revenue' => number_format((float) ($salesStats->total_revenue ?? 0), 2),
                'avg_transaction' => number_format((float) ($salesStats->avg_transaction ?? 0), 2),
                'max_transaction' => number_format((float) ($salesStats->max_transaction ?? 0), 2),
                'total_discounts' => number_format((float) ($salesStats->total_discounts ?? 0), 2),
                'total_tax' => number_format((float) ($salesStats->total_tax ?? 0), 2),
            ], JSON_UNESCAPED_UNICODE),

            'top_products_summary' => $topProductsText ?: 'No sales data available.',

            'inventory_snapshot' => json_encode([
                'total_skus' => $inventory->total_skus ?? 0,
                'total_units' => $inventory->total_units ?? 0,
                'low_stock_count' => $inventory->low_stock_count ?? 0,
                'out_of_stock_count' => $inventory->out_of_stock_count ?? 0,
                'expiring_within_30d' => $expiringCount,
            ], JSON_UNESCAPED_UNICODE),

            'product_catalog_summary' => json_encode([
                'total_products' => $productStats->total_products ?? 0,
                'inactive_count' => $productStats->inactive_count ?? 0,
                'avg_price' => number_format((float) ($productStats->avg_price ?? 0), 2),
                'categories' => $categoryNames,
            ], JSON_UNESCAPED_UNICODE),

            'customer_summary' => json_encode([
                'total_customers' => $customers->total_customers ?? 0,
                'active_last_30d' => $customers->active_30d ?? 0,
                'lifetime_spend' => number_format((float) ($customers->lifetime_spend ?? 0), 2),
                'avg_visits' => number_format((float) ($customers->avg_visits ?? 0), 1),
            ], JSON_UNESCAPED_UNICODE),

            'payment_methods_summary' => $paymentText ?: 'No payment data available.',

            'expenses_summary' => json_encode([
                'total_expenses_30d' => number_format((float) ($expenses->total_expenses ?? 0), 2),
                'expense_count_30d' => $expenses->cnt ?? 0,
            ], JSON_UNESCAPED_UNICODE),

            'staff_count' => $staffCount,
            'active_promotions' => $activePromos,
        ];
    }

    /**
     * Build a formatted system prompt block from store context.
     * Used by AIChatService for the enriched system prompt.
     */
    public function buildStoreContextPrompt(string $storeId, string $organizationId): string
    {
        $ctx = $this->getStoreContext($storeId, $organizationId);

        $salesSnapshot = json_decode($ctx['sales_snapshot'], true);
        $inventorySnapshot = json_decode($ctx['inventory_snapshot'], true);
        $productCatalog = json_decode($ctx['product_catalog_summary'], true);
        $customerSummary = json_decode($ctx['customer_summary'], true);
        $expensesSummary = json_decode($ctx['expenses_summary'], true);

        return <<<PROMPT
You are Wameed AI, an intelligent POS assistant for "{$ctx['store_name']}" in {$ctx['city']}. Timezone: {$ctx['timezone']}. Currency: {$ctx['currency']}.
The business ({$ctx['business_type']}) has {$ctx['branch_count']} branch(es). Organization: {$ctx['organization_name']}.

═══ TODAY'S SNAPSHOT ═══
- Transactions today: {$salesSnapshot['today_transactions']}, Revenue: {$ctx['currency']} {$salesSnapshot['today_revenue']}

═══ LAST 30 DAYS SALES ═══
- Total transactions: {$salesSnapshot['last_30d_transactions']}
- Total revenue: {$ctx['currency']} {$salesSnapshot['last_30d_revenue']}
- Average transaction: {$ctx['currency']} {$salesSnapshot['avg_transaction']}
- Largest transaction: {$ctx['currency']} {$salesSnapshot['max_transaction']}
- Total discounts given: {$ctx['currency']} {$salesSnapshot['total_discounts']}
- Total tax collected: {$ctx['currency']} {$salesSnapshot['total_tax']}

═══ TOP SELLING PRODUCTS (30 days) ═══
{$ctx['top_products_summary']}
═══ INVENTORY STATUS ═══
- Total SKUs tracked: {$inventorySnapshot['total_skus']}
- Total units in stock: {$inventorySnapshot['total_units']}
- Products at/below reorder point: {$inventorySnapshot['low_stock_count']}
- Out of stock: {$inventorySnapshot['out_of_stock_count']}
- Expiring within 30 days: {$inventorySnapshot['expiring_within_30d']}

═══ PRODUCT CATALOG ═══
- Total products: {$productCatalog['total_products']} (inactive: {$productCatalog['inactive_count']})
- Average sell price: {$ctx['currency']} {$productCatalog['avg_price']}
- Categories: {$productCatalog['categories']}

═══ CUSTOMERS ═══
- Total customers: {$customerSummary['total_customers']}
- Active in last 30 days: {$customerSummary['active_last_30d']}
- Lifetime spend: {$ctx['currency']} {$customerSummary['lifetime_spend']}
- Average visits per customer: {$customerSummary['avg_visits']}

═══ STAFF & OPERATIONS ═══
- Active staff: {$ctx['staff_count']}
- Active promotions: {$ctx['active_promotions']}

═══ PAYMENT METHODS (30 days) ═══
{$ctx['payment_methods_summary']}
═══ EXPENSES (30 days) ═══
- Total expenses: {$ctx['currency']} {$expensesSummary['total_expenses_30d']} ({$expensesSummary['expense_count_30d']} entries)

═══ GUIDELINES ═══
- Always respond in the SAME LANGUAGE the user writes in (Arabic or English).
- Use {$ctx['currency']} for all monetary values.
- Be concise but thorough. Use tables, bullet points, and structured formatting.
- Provide actionable recommendations backed by the data above.
- If a question requires data you don't have, say so clearly.
- When comparing periods, note that your data covers the last 30 days.
PROMPT;
    }
}
