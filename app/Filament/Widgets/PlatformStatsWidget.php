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

    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $data = Cache::remember('filament:platform_stats', 300, function () {
            $activeStores = DB::table('stores')->where('is_active', true)->count();
            $totalStores = DB::table('stores')->count();

            $mrr = (float) DB::table('store_subscriptions')
                ->where('status', 'active')
                ->join('subscription_plans', 'store_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.monthly_price');

            $arr = $mrr * 12;

            $newSignups = DB::table('stores')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            $lastMonthSignups = DB::table('stores')
                ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                ->count();

            $openTickets = DB::table('support_tickets')
                ->whereIn('status', ['open', 'pending', 'in_progress'])
                ->count();

            $overdueInvoices = DB::table('invoices')
                ->where('status', 'pending')
                ->where('due_date', '<', now())
                ->count();

            $overdueAmount = (float) DB::table('invoices')
                ->where('status', 'pending')
                ->where('due_date', '<', now())
                ->sum('total');

            $trialSubscriptions = DB::table('store_subscriptions')
                ->where('status', 'trial')
                ->count();

            $churnThisMonth = DB::table('store_subscriptions')
                ->where('status', 'cancelled')
                ->where('updated_at', '>=', now()->startOfMonth())
                ->count();

            $hardwareRevenue = (float) DB::table('hardware_sales')
                ->where('sold_at', '>=', now()->startOfMonth())
                ->sum('amount');

            $totalGmv = (float) DB::table('transactions')
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total_amount');

            $activeUsers = DB::table('users')
                ->where('last_login_at', '>=', now()->subDays(7))
                ->count();

            return compact(
                'activeStores', 'totalStores', 'mrr', 'arr', 'newSignups',
                'lastMonthSignups', 'openTickets', 'overdueInvoices', 'overdueAmount',
                'trialSubscriptions', 'churnThisMonth', 'hardwareRevenue',
                'totalGmv', 'activeUsers'
            );
        });

        $signupTrend = $data['lastMonthSignups'] > 0
            ? round((($data['newSignups'] - $data['lastMonthSignups']) / $data['lastMonthSignups']) * 100)
            : null;

        return [
            Stat::make(__('admin_dashboard.active_stores'), Number::format($data['activeStores']) . ' / ' . Number::format($data['totalStores']))
                ->description(__('admin_dashboard.total_registered_stores'))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3])
                ->color('success'),

            Stat::make(__('admin_dashboard.mrr'), Number::currency($data['mrr'], 'SAR'))
                ->description(__('admin_dashboard.monthly_recurring_revenue'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart([4, 6, 2, 8, 3, 4, 6, 5])
                ->color('primary'),

            Stat::make(__('admin_dashboard.arr'), Number::currency($data['arr'], 'SAR'))
                ->description(__('admin_dashboard.annual_recurring_revenue'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make(__('admin_dashboard.total_gmv'), Number::currency($data['totalGmv'], 'SAR'))
                ->description(__('admin_dashboard.platform_gross_volume_mtd'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([3, 5, 4, 6, 7, 4, 8, 6])
                ->color('success'),

            Stat::make(__('admin_dashboard.new_signups'), Number::format($data['newSignups']))
                ->description(
                    $signupTrend !== null
                        ? ($signupTrend >= 0 ? '+' : '') . $signupTrend . '% ' . __('admin_dashboard.this_month')
                        : __('admin_dashboard.this_month')
                )
                ->descriptionIcon($signupTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart([2, 4, 6, 4, 3, 5, 7, 5])
                ->color($signupTrend >= 0 ? 'success' : 'warning'),

            Stat::make(__('admin_dashboard.trial_stores'), Number::format($data['trialSubscriptions']))
                ->description(__('admin_dashboard.active_trial_subscriptions'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make(__('admin_dashboard.churned'), Number::format($data['churnThisMonth']))
                ->description(__('admin_dashboard.cancelled_this_month'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($data['churnThisMonth'] > 5 ? 'danger' : 'warning'),

            Stat::make(__('admin_dashboard.overdue_invoices'), Number::format($data['overdueInvoices']))
                ->description(__('admin_dashboard.outstanding', ['amount' => Number::currency($data['overdueAmount'], 'SAR')]))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($data['overdueInvoices'] > 0 ? 'danger' : 'success'),

            Stat::make(__('admin_dashboard.hardware_revenue'), Number::currency($data['hardwareRevenue'], 'SAR'))
                ->description(__('admin_dashboard.sales_this_month'))
                ->descriptionIcon('heroicon-m-computer-desktop')
                ->color('gray'),

            Stat::make(__('admin_dashboard.open_tickets'), Number::format($data['openTickets']))
                ->description(__('admin_dashboard.awaiting_response'))
                ->descriptionIcon('heroicon-m-ticket')
                ->color($data['openTickets'] > 10 ? 'danger' : 'warning'),

            Stat::make(__('admin_dashboard.active_users'), Number::format($data['activeUsers']))
                ->description(__('admin_dashboard.logged_in_last_7_days'))
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
