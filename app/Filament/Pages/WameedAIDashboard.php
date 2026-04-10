<?php

namespace App\Filament\Pages;

use App\Domain\WameedAI\Models\AIDailyUsageSummary;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIPlatformDailySummary;
use App\Domain\WameedAI\Models\AIUsageLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class WameedAIDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.wameed-ai-dashboard';

    protected static ?string $pollingInterval = '60s';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.ai_dashboard');
    }

    public function getTitle(): string
    {
        return 'Wameed AI Dashboard';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.view', 'wameed_ai.manage']);
    }

    public function getViewData(): array
    {
        // Total platform stats
        $totalRequests = AIUsageLog::count();
        $todayRequests = AIUsageLog::whereDate('created_at', today())->count();
        $totalCost = AIUsageLog::sum('estimated_cost_usd');
        $todayCost = AIUsageLog::whereDate('created_at', today())->sum('estimated_cost_usd');
        $avgLatency = round(AIUsageLog::avg('latency_ms') ?? 0);
        $cacheHitRate = $totalRequests > 0
            ? round(AIUsageLog::where('response_cached', true)->count() / $totalRequests * 100, 1)
            : 0;

        // Active features count
        $totalFeatures = AIFeatureDefinition::count();
        $enabledFeatures = AIFeatureDefinition::where('is_enabled', true)->count();

        // Active stores using AI (last 30 days)
        $activeStores = AIUsageLog::where('created_at', '>=', now()->subDays(30))
            ->distinct('store_id')
            ->count('store_id');

        // Top features by usage (last 30 days)
        $topFeatures = AIUsageLog::where('created_at', '>=', now()->subDays(30))
            ->select('feature_slug', DB::raw('COUNT(*) as total_requests'), DB::raw('SUM(estimated_cost_usd) as total_cost'))
            ->groupBy('feature_slug')
            ->orderByDesc('total_requests')
            ->limit(10)
            ->get();

        // Error rate (last 7 days)
        $recentTotal = AIUsageLog::where('created_at', '>=', now()->subDays(7))->count();
        $recentErrors = AIUsageLog::where('created_at', '>=', now()->subDays(7))
            ->where('status', 'error')
            ->count();
        $errorRate = $recentTotal > 0 ? round($recentErrors / $recentTotal * 100, 1) : 0;

        // Daily trend (last 14 days)
        $dailyTrend = AIUsageLog::where('created_at', '>=', now()->subDays(14))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as requests'), DB::raw('SUM(estimated_cost_usd) as cost'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return [
            'totalRequests' => $totalRequests,
            'todayRequests' => $todayRequests,
            'totalCost' => number_format($totalCost, 4),
            'todayCost' => number_format($todayCost, 4),
            'avgLatency' => $avgLatency,
            'cacheHitRate' => $cacheHitRate,
            'totalFeatures' => $totalFeatures,
            'enabledFeatures' => $enabledFeatures,
            'activeStores' => $activeStores,
            'topFeatures' => $topFeatures,
            'errorRate' => $errorRate,
            'dailyTrend' => $dailyTrend,
        ];
    }
}
