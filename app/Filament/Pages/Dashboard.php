<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveCashiersWidget;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\LowStockAlertsWidget;
use App\Filament\Widgets\SalesTrendChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            DashboardStatsWidget::class,
            SalesTrendChart::class,
            LowStockAlertsWidget::class,
            ActiveCashiersWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
