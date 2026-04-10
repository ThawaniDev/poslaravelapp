<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ShrinkageDetectionService extends BaseFeatureService
{
    public function getFeatureSlug(): string
    {
        return 'shrinkage_detection';
    }

    public function detect(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $shrinkageData = DB::select("
            SELECT p.id, p.name, p.name_ar, p.cost_price,
                   c.name as category,
                   sl.quantity as actual_stock,
                   COALESCE(received.total_received, 0) as total_received,
                   COALESCE(sold.total_sold, 0) as total_sold,
                   COALESCE(adjusted.total_adjusted, 0) as total_adjusted,
                   (COALESCE(received.total_received, 0) - COALESCE(sold.total_sold, 0) - COALESCE(adjusted.total_adjusted, 0)) as expected_stock,
                   sl.quantity - (COALESCE(received.total_received, 0) - COALESCE(sold.total_sold, 0) - COALESCE(adjusted.total_adjusted, 0)) as variance
            FROM products p
            JOIN stock_levels sl ON sl.product_id = p.id AND sl.store_id = ?
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT product_id, SUM(quantity) as total_received
                FROM goods_receipt_items gri
                JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                WHERE gr.store_id = ? AND gr.received_at >= NOW() - INTERVAL '90 days'
                GROUP BY product_id
            ) received ON received.product_id = p.id
            LEFT JOIN (
                SELECT ti.product_id, SUM(ti.quantity) as total_sold
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                WHERE t.store_id = ? AND t.created_at >= NOW() - INTERVAL '90 days' AND t.status = 'completed'
                GROUP BY ti.product_id
            ) sold ON sold.product_id = p.id
            LEFT JOIN (
                SELECT sai.product_id, SUM(ABS(sai.quantity)) as total_adjusted
                FROM stock_adjustment_items sai
                JOIN stock_adjustments sa ON sa.id = sai.stock_adjustment_id
                WHERE sa.store_id = ? AND sa.created_at >= NOW() - INTERVAL '90 days'
                GROUP BY sai.product_id
            ) adjusted ON adjusted.product_id = p.id
            WHERE p.organization_id = ? AND p.is_active = true
            HAVING ABS(sl.quantity - (COALESCE(received.total_received, 0) - COALESCE(sold.total_sold, 0) - COALESCE(adjusted.total_adjusted, 0))) > 2
            ORDER BY ABS(variance) DESC
            LIMIT 50
        ", [$storeId, $storeId, $storeId, $storeId, $organizationId]);

        if (empty($shrinkageData)) {
            return ['products' => [], 'total_shrinkage_value' => 0, 'message' => 'No significant shrinkage detected'];
        }

        $totalShrinkageValue = array_sum(array_map(fn ($p) => abs((float) $p->variance) * (float) $p->cost_price, $shrinkageData));

        $context = [
            'discrepancies' => json_encode($shrinkageData, JSON_UNESCAPED_UNICODE),
            'total_shrinkage_value' => number_format($totalShrinkageValue, 2),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 720);
    }
}
