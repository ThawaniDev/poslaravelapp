<?php

namespace App\Domain\WameedAI\Services\Features;

use App\Domain\WameedAI\Models\AIPlatformDailySummary;
use Illuminate\Support\Facades\DB;

class PlatformTrendService extends BaseFeatureService
{
    public function getFeatureSlug(): string { return 'platform_trends'; }

    public function analyze(?string $userId = null): ?array
    {
        $dailySummaries = AIPlatformDailySummary::where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date')
            ->get()
            ->toArray();

        $storeGrowth = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM stores WHERE is_active = true) as total_active,
                (SELECT COUNT(*) FROM stores WHERE created_at >= NOW() - INTERVAL '30 days') as new_stores_30d,
                (SELECT COUNT(*) FROM stores WHERE created_at >= NOW() - INTERVAL '7 days') as new_stores_7d,
                (SELECT COUNT(*) FROM organizations WHERE is_active = true) as total_orgs
        ");

        $platformRevenue = DB::selectOne("
            SELECT COALESCE(SUM(total_amount), 0) as total_revenue_30d,
                   COUNT(*) as total_txn_30d
            FROM transactions
            WHERE status = 'completed' AND created_at >= NOW() - INTERVAL '30 days'
        ");

        $aiUsageStats = DB::selectOne("
            SELECT COUNT(*) as total_ai_calls,
                   COALESCE(SUM(total_tokens), 0) as total_tokens,
                   COALESCE(SUM(estimated_cost_usd), 0) as total_cost_usd,
                   AVG(latency_ms) as avg_latency
            FROM ai_usage_logs
            WHERE created_at >= NOW() - INTERVAL '30 days'
        ");

        $context = [
            'daily_platform_stats' => json_encode($dailySummaries, JSON_UNESCAPED_UNICODE),
            'store_growth' => json_encode($storeGrowth, JSON_UNESCAPED_UNICODE),
            'platform_revenue' => json_encode($platformRevenue, JSON_UNESCAPED_UNICODE),
            'ai_usage' => json_encode($aiUsageStats, JSON_UNESCAPED_UNICODE),
        ];

        return $this->callAI('platform', 'platform', $context, $userId, cacheTtlMinutes: 720);
    }
}
