<?php

namespace App\Domain\WameedAI\Services\Features;

class InvoiceOCRService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'invoice_ocr'; }

    public function scan(string $storeId, string $organizationId, string $imageBase64, ?string $userId = null): ?array
    {
        $existingProducts = \Illuminate\Support\Facades\DB::select("
            SELECT id, name, name_ar, barcode, sku, cost_price
            FROM products WHERE organization_id = ? AND is_active = true
            ORDER BY name LIMIT 500
        ", [$organizationId]);

        $suppliers = \Illuminate\Support\Facades\DB::select("
            SELECT id, name, name_ar FROM suppliers WHERE organization_id = ? ORDER BY name
        ", [$organizationId]);

        $context = [
            'image_base64' => $imageBase64,
            'existing_products' => json_encode($existingProducts, JSON_UNESCAPED_UNICODE),
            'known_suppliers' => json_encode($suppliers, JSON_UNESCAPED_UNICODE),
            'instruction' => 'Extract all data from the attached invoice image. Match line items to known products when possible.',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 0);
    }
}
