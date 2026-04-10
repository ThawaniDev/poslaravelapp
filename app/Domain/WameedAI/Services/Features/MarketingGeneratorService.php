<?php

namespace App\Domain\WameedAI\Services\Features;

use Illuminate\Support\Facades\DB;

class MarketingGeneratorService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'marketing_generator'; }

    public function generate(string $storeId, string $organizationId, string $type, array $contextInfo, ?string $userId = null): ?array
    {
        $store = DB::selectOne("
            SELECT s.name, s.name_ar, o.name as org_name
            FROM stores s
            JOIN organizations o ON o.id = s.organization_id
            WHERE s.id = ?
        ", [$storeId]);

        $contextInfo['store_name'] = $store->name ?? '';
        $contextInfo['store_name_ar'] = $store->name_ar ?? '';

        $context = [
            'message_type' => $type,
            'context' => json_encode($contextInfo, JSON_UNESCAPED_UNICODE),
            'max_length' => $type === 'sms' ? 160 : 1000,
        ];

        return $this->callAI($storeId, $organizationId, $context, $userId, cacheTtlMinutes: 60);
    }
}
