<?php

namespace App\Domain\WameedAI\Services\Features;

class SocialContentService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'social_content'; }

    public function generate(string $storeId, string $organizationId, string $platform, string $topic, ?array $productIds = null, ?string $userId = null): ?array
    {
        $products = [];
        if ($productIds) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $products = \Illuminate\Support\Facades\DB::select("
                SELECT name, name_ar, sell_price, description FROM products WHERE id IN ({$placeholders})
            ", $productIds);
        }

        $context = [
            'platform' => $platform, // instagram, twitter, snapchat
            'topic' => $topic,
            'products' => json_encode($products, JSON_UNESCAPED_UNICODE),
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 60);
    }
}
