<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DeliveryOverviewWidget;
use App\Filament\Widgets\OpenTicketsWidget;
use App\Filament\Widgets\PlatformRevenueTrendChart;
use App\Filament\Widgets\PlatformStatsWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\RecentStoresWidget;
use App\Filament\Widgets\SecurityOverviewWidget;
use App\Filament\Widgets\StoreGrowthChart;
use App\Filament\Widgets\StoresByCityChart;
use App\Filament\Widgets\SubscriptionDistributionChart;
use App\Filament\Widgets\SubscriptionHealthWidget;
use App\Filament\Widgets\SystemHealthWidget;
use App\Filament\Widgets\TopPerformingStoresWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = null;

    public function getTitle(): string
    {
        return __('admin_dashboard.title');
    }

    public function getWidgets(): array
    {
        return [
            // Row 1 — Full-width KPI stats (11 cards)
            PlatformStatsWidget::class,

            // Row 2 — Revenue line chart (left) + Subscription doughnut (right)
            PlatformRevenueTrendChart::class,
            SubscriptionDistributionChart::class,

            // Row 3 — Store growth bar+line chart (full width)
            StoreGrowthChart::class,

            // Row 4 — Subscription health stats (6 stats)
            SubscriptionHealthWidget::class,

            // Row 5 — Top stores (full width)
            TopPerformingStoresWidget::class,

            // Row 6 — Recent stores (left) + Open tickets (right)
            RecentStoresWidget::class,
            OpenTicketsWidget::class,

            // Row 7 — Recent activity (left) + Stores by city (right)
            RecentActivityWidget::class,
            StoresByCityChart::class,

            // Row 8 — Security overview (full width, permission-gated)
            SecurityOverviewWidget::class,

            // Row 9 — System health (full width, permission-gated)
            SystemHealthWidget::class,

            // Row 10 — Delivery overview (full width, permission-gated)
            DeliveryOverviewWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
