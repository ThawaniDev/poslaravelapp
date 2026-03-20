<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OpenTicketsWidget;
use App\Filament\Widgets\PlatformRevenueTrendChart;
use App\Filament\Widgets\PlatformStatsWidget;
use App\Filament\Widgets\RecentStoresWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            PlatformStatsWidget::class,
            PlatformRevenueTrendChart::class,
            RecentStoresWidget::class,
            OpenTicketsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
