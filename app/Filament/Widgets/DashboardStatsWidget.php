<?php

namespace App\Filament\Widgets;

use App\Domain\OwnerDashboard\Services\OwnerDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user?->store_id) {
            return [];
        }

        $service = app(OwnerDashboardService::class);
        $data = $service->stats($user->store_id);

        return [
            Stat::make(
                __('owner_dashboard.filament.today_sales'),
                Number::currency($data['today_sales']['value'], 'SAR')
            )
                ->description($this->changeLabel($data['today_sales']['change']))
                ->descriptionIcon($this->changeIcon($data['today_sales']['change']))
                ->color($this->changeColor($data['today_sales']['change'])),

            Stat::make(
                __('owner_dashboard.filament.transactions'),
                Number::format($data['transactions']['value'])
            )
                ->description($this->changeLabel($data['transactions']['change']))
                ->descriptionIcon($this->changeIcon($data['transactions']['change']))
                ->color($this->changeColor($data['transactions']['change'])),

            Stat::make(
                __('owner_dashboard.filament.avg_basket'),
                Number::currency($data['avg_basket']['value'], 'SAR')
            )
                ->description($this->changeLabel($data['avg_basket']['change']))
                ->descriptionIcon($this->changeIcon($data['avg_basket']['change']))
                ->color($this->changeColor($data['avg_basket']['change'])),

            Stat::make(
                __('owner_dashboard.filament.net_profit'),
                Number::currency($data['net_profit']['value'], 'SAR')
            )
                ->description($this->changeLabel($data['net_profit']['change']))
                ->descriptionIcon($this->changeIcon($data['net_profit']['change']))
                ->color($this->changeColor($data['net_profit']['change'])),
        ];
    }

    private function changeLabel(float $change): string
    {
        $sign = $change >= 0 ? '+' : '';

        return $sign . $change . '% ' . __('owner_dashboard.filament.vs_yesterday');
    }

    private function changeIcon(float $change): string
    {
        return $change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
    }

    private function changeColor(float $change): string
    {
        return $change >= 0 ? 'success' : 'danger';
    }
}
