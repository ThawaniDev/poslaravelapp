<?php

namespace App\Filament\Pages;

use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Filament\Pages\Page;

class RevenueDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_analytics');
    }

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.revenue-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('analytics.revenue_dashboard');
    }

    public function getTitle(): string
    {
        return __('analytics.revenue_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'analytics.revenue']);
    }

    public function getViewData(): array
    {
        $latestStat = PlatformDailyStat::orderByDesc('date')->first();
        $mrr = (float) ($latestStat?->total_mrr ?? 0);
        $arr = $mrr * 12;

        // Revenue trend (last 30 days)
        $revenueTrend = PlatformDailyStat::where('date', '>=', now()->subDays(30))
            ->orderBy('date')
            ->get()
            ->map(fn ($s) => [
                'date' => $s->date->format('M d'),
                'mrr' => (float) $s->total_mrr,
                'gmv' => (float) $s->total_gmv,
            ]);

        // Revenue by plan (latest date)
        $latestPlanDate = PlatformPlanStat::max('date');
        $revenueByPlan = $latestPlanDate
            ? PlatformPlanStat::with('subscriptionPlan')
                ->whereDate('date', $latestPlanDate)
                ->get()
                ->map(fn ($ps) => [
                    'plan' => $ps->subscriptionPlan?->name ?? __('analytics.unknown'),
                    'active' => $ps->active_count,
                    'mrr' => (float) $ps->mrr,
                ])
            : collect();

        // Failed payments (last 30 days)
        $failedPayments = Invoice::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Upcoming renewals (next 7 days)
        $upcomingRenewals = StoreSubscription::where('status', 'active')
            ->where('current_period_end', '>=', now())
            ->where('current_period_end', '<=', now()->addDays(7))
            ->count();

        return [
            'mrr' => $mrr,
            'arr' => $arr,
            'revenueTrend' => $revenueTrend,
            'revenueByPlan' => $revenueByPlan,
            'failedPayments' => $failedPayments,
            'upcomingRenewals' => $upcomingRenewals,
        ];
    }
}
