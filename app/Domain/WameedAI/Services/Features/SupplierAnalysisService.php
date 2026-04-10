<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SupplierAnalysisService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'supplier_analysis'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $currency = $this->getStoreCurrency($storeId);

        $supplierMetrics = DB::select("
            SELECT s.id, s.name, s.credit_limit, s.outstanding_balance,
                   s.rating, s.category as supplier_category,
                   COUNT(DISTINCT gr.id) as total_deliveries,
                   AVG(EXTRACT(DAY FROM gr.received_at - po.created_at)) as avg_delivery_days,
                   MIN(EXTRACT(DAY FROM gr.received_at - po.created_at)) as min_delivery_days,
                   MAX(EXTRACT(DAY FROM gr.received_at - po.created_at)) as max_delivery_days,
                   SUM(gri.quantity) as total_items_received,
                   SUM(gri.quantity * gri.unit_cost) as total_spend,
                   MAX(gr.received_at) as last_delivery
            FROM suppliers s
            LEFT JOIN goods_receipts gr ON gr.supplier_id = s.id AND gr.store_id = ?
              AND gr.received_at IS NOT NULL
            LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
              AND po.created_at >= NOW() - INTERVAL '180 days'
            LEFT JOIN goods_receipt_items gri ON gri.goods_receipt_id = gr.id
            WHERE s.organization_id = ?
            GROUP BY s.id, s.name, s.credit_limit, s.outstanding_balance, s.rating, s.category
            ORDER BY total_spend DESC
            LIMIT 30
        ", [$storeId, $organizationId]);

        if (empty($supplierMetrics)) {
            return ['supplier_rankings' => [], 'message' => 'No supplier data available'];
        }

        $pendingPOs = DB::select("
            SELECT po.supplier_id, s.name as supplier_name,
                   COUNT(*) as pending_count,
                   SUM(po.total_cost) as pending_value,
                   MIN(po.expected_date) as earliest_expected
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            WHERE po.store_id = ? AND po.status IN ('pending', 'approved', 'ordered')
            GROUP BY po.supplier_id, s.name
        ", [$storeId]);

        $productPerSupplier = DB::select("
            SELECT ps.supplier_id, COUNT(DISTINCT ps.product_id) as product_count,
                   AVG(ps.lead_time_days) as avg_lead_time
            FROM product_suppliers ps
            JOIN suppliers s ON s.id = ps.supplier_id
            WHERE s.organization_id = ?
            GROUP BY ps.supplier_id
        ", [$organizationId]);

        $context = [
            'supplier_data' => json_encode($supplierMetrics, JSON_UNESCAPED_UNICODE),
            'delivery_records' => json_encode($supplierMetrics, JSON_UNESCAPED_UNICODE),
            'pending_orders' => json_encode($pendingPOs, JSON_UNESCAPED_UNICODE),
            'product_coverage' => json_encode($productPerSupplier, JSON_UNESCAPED_UNICODE),
            'currency' => $currency,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
