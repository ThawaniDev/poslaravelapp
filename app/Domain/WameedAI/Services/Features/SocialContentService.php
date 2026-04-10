<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class SocialContentService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'social_content'; }

    public function generate(string $storeId, string $organizationId, string $platform, string $topic, ?array $productIds = null, ?string $userId = null): ?array
    {
        $products = [];
        if ($productIds) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $products = DB::select("
                SELECT name, name_ar, sell_price, description, barcode
                FROM products WHERE id IN ({$placeholders})
            ", $productIds);
        }

        $store = DB::selectOne("
            SELECT s.name, s.name_ar FROM stores s WHERE s.id = ?
        ", [$storeId]);

        $context = [
            'platform' => $platform,
            'topic' => $topic,
            'products' => json_encode($products, JSON_UNESCAPED_UNICODE),
            'store_name' => $store->name ?? '',
            'store_name_ar' => $store->name_ar ?? '',
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 60);
    }
}
