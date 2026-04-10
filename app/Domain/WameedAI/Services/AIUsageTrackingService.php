<?php

namespace App\Domain\WameedAI\Services;

use App\Domain\WameedAI\Models\AIDailyUsageSummary;
use App\Domain\WameedAI\Models\AIMonthlyUsageSummary;
use App\Domain\WameedAI\Models\AIPlatformDailySummary;
use App\Domain\WameedAI\Models\AIUsageLog;
use Illuminate\Support\Facades\DB;

class AIUsageTrackingService
{
    public function getTodayUsage(string $storeId): array
    {
        $today = now()->toDateString();

        $stats = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw("
                COUNT(*) as total_requests,
                SUM(CASE WHEN response_cached = true THEN 1 ELSE 0 END) as cached_requests,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_requests,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost_usd) as total_cost
            ")
            ->first();

        $byFeature = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', now()->startOfDay())
            ->groupBy('feature_slug')
            ->selectRaw('feature_slug, COUNT(*) as count, SUM(total_tokens) as tokens')
            ->get()
            ->keyBy('feature_slug')
            ->toArray();

        return [
            'date' => $today,
            'total_requests' => (int) ($stats->total_requests ?? 0),
            'cached_requests' => (int) ($stats->cached_requests ?? 0),
            'failed_requests' => (int) ($stats->failed_requests ?? 0),
            'total_tokens' => (int) ($stats->total_tokens ?? 0),
            'total_cost_usd' => round((float) ($stats->total_cost ?? 0), 6),
            'by_feature' => $byFeature,
        ];
    }

    public function getMonthlyUsage(string $storeId, ?string $month = null): array
    {
        $monthDate = $month ? \Carbon\Carbon::parse($month)->startOfMonth() : now()->startOfMonth();

        $summary = AIMonthlyUsageSummary::where('store_id', $storeId)
            ->where('month', $monthDate->toDateString())
            ->first();

        if ($summary) {
            return $summary->toArray();
        }

        // Fall back to real-time aggregation
        $stats = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', $monthDate)
            ->where('created_at', '<', $monthDate->copy()->addMonth())
            ->selectRaw("
                COUNT(*) as total_requests,
                SUM(CASE WHEN response_cached = true THEN 1 ELSE 0 END) as cached_requests,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(estimated_cost_usd) as total_estimated_cost_usd
            ")
            ->first();

        return [
            'month' => $monthDate->toDateString(),
            'total_requests' => (int) ($stats->total_requests ?? 0),
            'cached_requests' => (int) ($stats->cached_requests ?? 0),
            'failed_requests' => (int) ($stats->failed_requests ?? 0),
            'total_input_tokens' => (int) ($stats->total_input_tokens ?? 0),
            'total_output_tokens' => (int) ($stats->total_output_tokens ?? 0),
            'total_estimated_cost_usd' => round((float) ($stats->total_estimated_cost_usd ?? 0), 6),
        ];
    }

    public function getUsageByFeature(string $storeId, ?string $period = 'last_30_days'): array
    {
        $since = match ($period) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            default => now()->subDays(30),
        };

        return AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->groupBy('feature_slug')
            ->selectRaw("
                feature_slug,
                COUNT(*) as total_requests,
                SUM(CASE WHEN response_cached = true THEN 1 ELSE 0 END) as cached_requests,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost_usd) as total_cost,
                AVG(latency_ms) as avg_latency_ms
            ")
            ->orderByDesc('total_requests')
            ->get()
            ->toArray();
    }

    public function aggregateDaily(string $date): void
    {
        $stores = AIUsageLog::where('created_at', '>=', $date . ' 00:00:00')
            ->where('created_at', '<', \Carbon\Carbon::parse($date)->addDay()->toDateString() . ' 00:00:00')
            ->select('store_id', 'organization_id')
            ->distinct()
            ->get();

        foreach ($stores as $store) {
            $stats = AIUsageLog::where('store_id', $store->store_id)
                ->where('created_at', '>=', $date . ' 00:00:00')
                ->where('created_at', '<', \Carbon\Carbon::parse($date)->addDay()->toDateString() . ' 00:00:00')
                ->selectRaw("
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN response_cached = true THEN 1 ELSE 0 END) as cached_requests,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_requests,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(estimated_cost_usd) as total_estimated_cost_usd
                ")
                ->first();

            $breakdown = AIUsageLog::where('store_id', $store->store_id)
                ->where('created_at', '>=', $date . ' 00:00:00')
                ->where('created_at', '<', \Carbon\Carbon::parse($date)->addDay()->toDateString() . ' 00:00:00')
                ->groupBy('feature_slug')
                ->selectRaw('feature_slug, COUNT(*) as count, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost')
                ->get()
                ->keyBy('feature_slug')
                ->toArray();

            AIDailyUsageSummary::updateOrCreate(
                ['store_id' => $store->store_id, 'date' => $date],
                [
                    'organization_id' => $store->organization_id,
                    'total_requests' => (int) ($stats->total_requests ?? 0),
                    'cached_requests' => (int) ($stats->cached_requests ?? 0),
                    'failed_requests' => (int) ($stats->failed_requests ?? 0),
                    'total_input_tokens' => (int) ($stats->total_input_tokens ?? 0),
                    'total_output_tokens' => (int) ($stats->total_output_tokens ?? 0),
                    'total_estimated_cost_usd' => round((float) ($stats->total_estimated_cost_usd ?? 0), 6),
                    'feature_breakdown_json' => $breakdown,
                    'created_at' => now(),
                ],
            );
        }
    }

    public function aggregateMonthly(string $month): void
    {
        $monthStart = \Carbon\Carbon::parse($month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $stores = AIDailyUsageSummary::where('date', '>=', $monthStart->toDateString())
            ->where('date', '<=', $monthEnd->toDateString())
            ->select('store_id', 'organization_id')
            ->distinct()
            ->get();

        foreach ($stores as $store) {
            $stats = AIDailyUsageSummary::where('store_id', $store->store_id)
                ->where('date', '>=', $monthStart->toDateString())
                ->where('date', '<=', $monthEnd->toDateString())
                ->selectRaw("
                    SUM(total_requests) as total_requests,
                    SUM(cached_requests) as cached_requests,
                    SUM(failed_requests) as failed_requests,
                    SUM(total_input_tokens) as total_input_tokens,
                    SUM(total_output_tokens) as total_output_tokens,
                    SUM(total_estimated_cost_usd) as total_estimated_cost_usd
                ")
                ->first();

            AIMonthlyUsageSummary::updateOrCreate(
                ['store_id' => $store->store_id, 'month' => $monthStart->toDateString()],
                [
                    'organization_id' => $store->organization_id,
                    'total_requests' => (int) ($stats->total_requests ?? 0),
                    'cached_requests' => (int) ($stats->cached_requests ?? 0),
                    'failed_requests' => (int) ($stats->failed_requests ?? 0),
                    'total_input_tokens' => (int) ($stats->total_input_tokens ?? 0),
                    'total_output_tokens' => (int) ($stats->total_output_tokens ?? 0),
                    'total_estimated_cost_usd' => round((float) ($stats->total_estimated_cost_usd ?? 0), 6),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function aggregatePlatformDaily(string $date): void
    {
        $stats = AIDailyUsageSummary::where('date', $date)
            ->selectRaw("
                COUNT(DISTINCT store_id) as total_stores_active,
                SUM(total_requests) as total_requests,
                SUM(total_input_tokens + total_output_tokens) as total_tokens,
                SUM(total_estimated_cost_usd) as total_estimated_cost_usd,
                CASE WHEN SUM(total_requests) > 0 THEN SUM(failed_requests)::DECIMAL / SUM(total_requests) * 100 ELSE 0 END as error_rate
            ")
            ->first();

        $avgLatency = AIUsageLog::where('created_at', '>=', $date . ' 00:00:00')
            ->where('created_at', '<', \Carbon\Carbon::parse($date)->addDay()->toDateString() . ' 00:00:00')
            ->avg('latency_ms');

        $featureBreakdown = AIDailyUsageSummary::where('date', $date)
            ->get()
            ->pluck('feature_breakdown_json')
            ->filter()
            ->reduce(function ($carry, $breakdown) {
                foreach ($breakdown as $slug => $data) {
                    if (!isset($carry[$slug])) {
                        $carry[$slug] = ['count' => 0, 'tokens' => 0, 'cost' => 0];
                    }
                    $carry[$slug]['count'] += $data['count'] ?? 0;
                    $carry[$slug]['tokens'] += $data['tokens'] ?? 0;
                    $carry[$slug]['cost'] += $data['cost'] ?? 0;
                }
                return $carry;
            }, []);

        $topStores = AIDailyUsageSummary::where('date', $date)
            ->orderByDesc('total_requests')
            ->limit(10)
            ->get(['store_id', 'total_requests', 'total_estimated_cost_usd'])
            ->toArray();

        AIPlatformDailySummary::updateOrCreate(
            ['date' => $date],
            [
                'total_stores_active' => (int) ($stats->total_stores_active ?? 0),
                'total_requests' => (int) ($stats->total_requests ?? 0),
                'total_tokens' => (int) ($stats->total_tokens ?? 0),
                'total_estimated_cost_usd' => round((float) ($stats->total_estimated_cost_usd ?? 0), 6),
                'feature_breakdown_json' => $featureBreakdown,
                'top_stores_json' => $topStores,
                'error_rate' => round((float) ($stats->error_rate ?? 0), 2),
                'avg_latency_ms' => (int) ($avgLatency ?? 0),
                'created_at' => now(),
            ],
        );
    }

    public function cleanupExpiredCache(): int
    {
        return \App\Domain\WameedAI\Models\AICache::where('expires_at', '<', now())->delete();
    }
}
