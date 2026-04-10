<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ProductCategorizationService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'product_categorization'; }

    public function categorize(string $storeId, string $organizationId, string $productName, ?string $barcode = null, ?string $userId = null): ?array
    {
        $existingCategories = DB::select("
            SELECT id, name, name_ar FROM categories WHERE organization_id = ? AND is_active = true ORDER BY name
        ", [$organizationId]);

        $context = [
            'uncategorized' => json_encode(['name' => $productName, 'barcode' => $barcode ?? ''], JSON_UNESCAPED_UNICODE),
            'categories' => json_encode($existingCategories, JSON_UNESCAPED_UNICODE),
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 43200); // 30 days
    }
}
