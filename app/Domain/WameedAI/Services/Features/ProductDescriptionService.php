<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class ProductDescriptionService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'product_description'; }

    public function generate(string $storeId, string $organizationId, string $productId, ?string $userId = null): ?array
    {
        $product = DB::selectOne("
            SELECT p.name, p.name_ar, p.description, p.sell_price, p.unit,
                   c.name as category_name, c.name_ar as category_name_ar
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? AND p.organization_id = ?
        ", [$productId, $organizationId]);

        if (!$product) {
            return null;
        }

        $context = [
            'product_data' => json_encode([
                'name' => $product->name,
                'name_ar' => $product->name_ar ?? '',
                'category' => $product->category_name ?? '',
                'category_ar' => $product->category_name_ar ?? '',
                'price' => $product->sell_price,
                'unit' => $product->unit ?? '',
                'currency' => 'SAR',
            ], JSON_UNESCAPED_UNICODE),
            'tone' => 'professional',
            'language' => 'ar',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 10080); // 7 days
    }
}
