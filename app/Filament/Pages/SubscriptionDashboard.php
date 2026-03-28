<?php

namespace App\Filament\Pages;

use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Filament\Pages\Page;

class SubscriptionDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_analytics');
    }

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.subscription-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('analytics.subscription_dashboard');
    }

    public function getTitle(): string
    {
        return __('analytics.subscription_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'analytics.subscriptions']);
    }

    public function getViewData(): array
    {
        // Status counts
        $statusCounts = StoreSubscription::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->status instanceof \BackedEnum ? $row->status->value : $row->status) => (int) $row->count,
            ]);

        // Lifecycle trend (last 30 days from plan stats)
        $lifecycleTrend = PlatformPlanStat::where('date', '>=', now()->subDays(30))
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($ps) => $ps->date->format('Y-m-d'))
            ->map(fn ($group, $date) => [
                'date' => $date,
                'active' => $group->sum('active_count'),
                'trial' => $group->sum('trial_count'),
                'churned' => $group->sum('churned_count'),
            ])
            ->values();

        // Churn in period
        $totalChurn = PlatformPlanStat::where('date', '>=', now()->subDays(30))
            ->sum('churned_count');

        // Trial-to-paid conversion
        $totalTrials = StoreSubscription::where('status', 'trial')->count();
        $totalActive = StoreSubscription::where('status', 'active')->count();
        $conversionRate = ($totalTrials + $totalActive) > 0
            ? round(($totalActive / ($totalTrials + $totalActive)) * 100, 1)
            : 0;

        return [
            'statusCounts' => $statusCounts,
            'lifecycleTrend' => $lifecycleTrend,
            'totalChurn' => (int) $totalChurn,
            'conversionRate' => $conversionRate,
            'totalActive' => $totalActive,
            'totalTrials' => $totalTrials,
        ];
    }
}
