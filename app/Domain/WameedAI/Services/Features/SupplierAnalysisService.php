<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SupplierAnalysisService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'supplier_analysis'; }

    public function analyze(string $storeId, string $organizationId, ?string $userId = null): ?array
    {
        $supplierMetrics = DB::select("
            SELECT s.id, s.name, s.name_ar,
                   COUNT(DISTINCT gr.id) as total_deliveries,
                   AVG(EXTRACT(DAY FROM gr.received_at - gr.created_at)) as avg_delivery_days,
                   SUM(gri.received_quantity) as total_items_received,
                   SUM(gri.total_cost) as total_spend
            FROM suppliers s
            JOIN goods_receipts gr ON gr.supplier_id = s.id AND gr.store_id = ?
            JOIN goods_receipt_items gri ON gri.goods_receipt_id = gr.id
            WHERE s.organization_id = ? AND gr.created_at >= NOW() - INTERVAL '180 days'
            GROUP BY s.id, s.name, s.name_ar
            ORDER BY total_spend DESC
            LIMIT 20
        ", [$storeId, $organizationId]);

        $context = [
            'supplier_data' => json_encode($supplierMetrics, JSON_UNESCAPED_UNICODE),
            'delivery_records' => json_encode($supplierMetrics, JSON_UNESCAPED_UNICODE),
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 1440);
    }
}
