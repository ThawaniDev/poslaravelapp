<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class BarcodeEnrichmentService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'barcode_enrichment'; }

    public function enrich(string $storeId, string $organizationId, string $barcode, ?string $userId = null): ?array
    {
        // Check internal products first
        $existing = DB::selectOne("
            SELECT p.name, p.name_ar, p.sell_price, p.cost_price, c.name as category
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE (p.barcode = ? OR EXISTS (SELECT 1 FROM product_barcodes pb WHERE pb.product_id = p.id AND pb.barcode = ?))
              AND p.organization_id = ?
        ", [$barcode, $barcode, $organizationId]);

        if ($existing) {
            return ['found_internally' => true, 'product' => (array) $existing];
        }

        // Check predefined catalog
        $predefined = DB::selectOne("
            SELECT pp.name, pp.name_ar, pp.barcode, pc.name as category, pp.sell_price as suggested_price
            FROM predefined_products pp
            LEFT JOIN predefined_categories pc ON pc.id = pp.predefined_category_id
            WHERE pp.barcode = ?
        ", [$barcode]);

        $context = [
            'barcode' => $barcode,
            'existing_data' => 'none',
            'predefined_data' => $predefined ? json_encode($predefined, JSON_UNESCAPED_UNICODE) : 'none',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 43200); // 30 days
    }
}
