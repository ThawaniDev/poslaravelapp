<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $data = Cache::remember('filament:platform_stats', 300, function () {
            $activeStores = DB::table('stores')->where('is_active', true)->count();
            $totalStores = DB::table('stores')->count();

            $mrr = DB::table('store_subscriptions')
                ->where('status', 'active')
                ->join('subscription_plans', 'store_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.monthly_price');

            $newSignups = DB::table('stores')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            $openTickets = DB::table('support_tickets')
                ->whereIn('status', ['open', 'pending', 'in_progress'])
                ->count();

            return compact('activeStores', 'totalStores', 'mrr', 'newSignups', 'openTickets');
        });

        return [
            Stat::make('Active Stores', Number::format($data['activeStores']) . ' / ' . Number::format($data['totalStores']))
                ->description('Total registered stores')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('success'),

            Stat::make('Monthly Recurring Revenue', Number::currency($data['mrr'], 'OMR'))
                ->description('Active subscriptions')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary'),

            Stat::make('New Signups', Number::format($data['newSignups']))
                ->description('This month')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Open Tickets', Number::format($data['openTickets']))
                ->description('Awaiting response')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($data['openTickets'] > 10 ? 'danger' : 'warning'),
        ];
    }
}
