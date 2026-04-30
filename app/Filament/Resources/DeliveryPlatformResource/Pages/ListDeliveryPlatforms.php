<?php

namespace App\Filament\Resources\DeliveryPlatformResource\Pages;

use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Filament\Resources\DeliveryPlatformResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListDeliveryPlatforms extends ListRecords
{
    protected static string $resource = DeliveryPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => auth('admin')->user()?->hasPermissionTo('integrations.manage')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DeliveryPlatformHealthWidget::class,
        ];
    }
}

/**
 * Compact health stats bar rendered above the platform list.
 */
class DeliveryPlatformHealthWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $twentyFourHoursAgo = Carbon::now()->subHours(24);

        $totalPlatforms   = \App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform::count();
        $activePlatforms  = \App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform::where('is_active', true)->count();
        $totalIntegrations = DeliveryPlatformConfig::where('is_enabled', true)->count();
        $errorIntegrations = DeliveryPlatformConfig::where('status', 'error')->count();

        $recentSyncCount = DeliveryMenuSyncLog::where('started_at', '>=', $twentyFourHoursAgo)->count();
        $failedSyncCount = DeliveryMenuSyncLog::where('started_at', '>=', $twentyFourHoursAgo)->where('status', 'failed')->count();
        $errorRate = $recentSyncCount > 0 ? round(($failedSyncCount / $recentSyncCount) * 100, 1) : 0;

        $pendingOrders = DeliveryOrderMapping::where('delivery_status', 'pending')->count();

        return [
            Stat::make(__('delivery.total_platforms'), $activePlatforms . '/' . $totalPlatforms)
                ->description(__('delivery.active_of_total'))
                ->descriptionIcon('heroicon-m-truck')
                ->color($activePlatforms > 0 ? 'success' : 'gray'),

            Stat::make(__('delivery.active_integrations'), $totalIntegrations)
                ->description(__('delivery.enabled_store_integrations'))
                ->descriptionIcon('heroicon-m-signal')
                ->color($errorIntegrations > 0 ? 'warning' : 'success'),

            Stat::make(__('delivery.sync_error_rate_24h'), $errorRate . '%')
                ->description(__('delivery.sync_error_rate_desc', ['failed' => $failedSyncCount, 'total' => $recentSyncCount]))
                ->descriptionIcon($errorRate > 5 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($errorRate > 10 ? 'danger' : ($errorRate > 3 ? 'warning' : 'success')),

            Stat::make(__('delivery.pending_orders'), $pendingOrders)
                ->description(__('delivery.awaiting_acceptance'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 10 ? 'danger' : ($pendingOrders > 0 ? 'warning' : 'success')),
        ];
    }
}
