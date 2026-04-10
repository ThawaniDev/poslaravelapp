<?php

namespace App\Domain\WameedAI\Services\Features;

class MarketingGeneratorService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'marketing_generator'; }

    public function generate(string $storeId, string $organizationId, string $type, array $contextInfo, ?string $userId = null): ?array
    {
        $context = [
            'message_type' => $type, // sms, whatsapp
            'context' => json_encode($contextInfo, JSON_UNESCAPED_UNICODE),
            'max_length' => $type === 'sms' ? 160 : 1000,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 60);
    }
}
