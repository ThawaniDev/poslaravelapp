<?php

namespace App\Domain\WameedAI\Services\Features;

class TranslationService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'translation'; }

    public function translate(string $storeId, string $organizationId, array $texts, string $from = 'ar', string $to = 'en', ?string $userId = null): ?array
    {
        $context = [
            'texts' => json_encode($texts, JSON_UNESCAPED_UNICODE),
            'source_language' => $from,
            'target_language' => $to,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 43200); // 30 days
    }
}
