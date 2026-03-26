<?php

namespace App\Filament\Widgets;

use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeliveryOverviewWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';

    protected static ?int $sort = 13;

    public static function canView(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['integrations.view', 'integrations.manage']);
    }

    protected function getStats(): array
    {
        $activePlatforms = DeliveryPlatformConfig::where('is_enabled', true)->count();
        $todayOrders = DeliveryOrderMapping::whereDate('created_at', today())->count();
        $pendingOrders = DeliveryOrderMapping::where('delivery_status', 'pending')->count();
        $todayRevenue = DeliveryOrderMapping::whereDate('created_at', today())
            ->where('delivery_status', 'delivered')
            ->sum('total_amount');

        return [
            Stat::make(__('delivery.active_platforms'), $activePlatforms)
                ->description(__('delivery.enabled_integrations'))
                ->descriptionIcon('heroicon-m-signal')
                ->color('success'),

            Stat::make(__('delivery.today_orders'), $todayOrders)
                ->description(__('delivery.orders_today'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make(__('delivery.pending_orders'), $pendingOrders)
                ->description(__('delivery.awaiting_acceptance'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'success'),

            Stat::make(__('delivery.today_revenue'), number_format($todayRevenue, 2) . ' SAR')
                ->description(__('delivery.delivered_orders_revenue'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}
