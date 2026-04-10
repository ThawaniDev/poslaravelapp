<?php

namespace App\Domain\WameedAI\Services\Features;

use App\Domain\WameedAI\Models\AIPlatformDailySummary;

class PlatformTrendService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'platform_trends'; }

    public function analyze(?string $userId = null): ?array
    {
        $dailySummaries = AIPlatformDailySummary::where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date')
            ->get()
            ->toArray();

        $storeGrowth = \Illuminate\Support\Facades\DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM stores WHERE is_active = true) as total_active,
                (SELECT COUNT(*) FROM stores WHERE created_at >= NOW() - INTERVAL '30 days') as new_stores_30d,
                (SELECT COUNT(*) FROM stores WHERE created_at >= NOW() - INTERVAL '7 days') as new_stores_7d
        ");

        $context = [
            'daily_platform_stats' => json_encode($dailySummaries, JSON_UNESCAPED_UNICODE),
            'store_growth' => json_encode($storeGrowth, JSON_UNESCAPED_UNICODE),
        ];

        return $this->callAI('platform', 'platform', $context, $userId, cacheTtlMinutes: 720);
    }
}
