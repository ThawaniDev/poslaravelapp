<?php

namespace App\Filament\Pages;

use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Models\FeatureAdoptionStat;
use Filament\Pages\Page;

class FeatureAdoptionDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_analytics');
    }

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.feature-adoption-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('analytics.feature_adoption');
    }

    public function getTitle(): string
    {
        return __('analytics.feature_adoption_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'analytics.features']);
    }

    public function getViewData(): array
    {
        $totalStores = Store::count();

        // Latest snapshot of feature adoption
        $latestDate = FeatureAdoptionStat::max('date');
        $features = collect();

        if ($latestDate) {
            $features = FeatureAdoptionStat::whereDate('date', $latestDate)
                ->orderByDesc('stores_using_count')
                ->get()
                ->map(fn ($f) => [
                    'feature_key' => $f->feature_key,
                    'stores_using' => $f->stores_using_count,
                    'total_events' => $f->total_events,
                    'adoption_rate' => $totalStores > 0
                        ? round(($f->stores_using_count / $totalStores) * 100, 1)
                        : 0,
                ]);
        }

        // Trend (daily totals, last 30 days)
        $trend = FeatureAdoptionStat::where('date', '>=', now()->subDays(30))
            ->selectRaw('date, SUM(stores_using_count) as total_using, SUM(total_events) as total_events')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->format('M d'),
                'total_using' => (int) $row->total_using,
                'total_events' => (int) $row->total_events,
            ]);

        return [
            'totalStores' => $totalStores,
            'features' => $features,
            'trend' => $trend,
            'latestDate' => $latestDate,
        ];
    }
}
