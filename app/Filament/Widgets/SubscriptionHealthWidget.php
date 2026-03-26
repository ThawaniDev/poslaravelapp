<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class SubscriptionHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin_dashboard.subscription_health');
    }

    protected function getStats(): array
    {
        $data = Cache::remember('filament:subscription_health', 300, function () {
            $activeSubscriptions = DB::table('store_subscriptions')
                ->where('status', 'active')
                ->count();

            $trialSubscriptions = DB::table('store_subscriptions')
                ->where('status', 'trial')
                ->count();

            // Trial → Paid conversion: subscriptions that were trial and are now active
            $totalEverTrial = DB::table('store_subscriptions')
                ->where(function ($q) {
                    $q->where('status', 'active')
                      ->orWhere('status', 'trial')
                      ->orWhere('status', 'cancelled');
                })
                ->where('trial_ends_at', '!=', null)
                ->count();

            $convertedFromTrial = DB::table('store_subscriptions')
                ->where('status', 'active')
                ->whereNotNull('trial_ends_at')
                ->count();

            $trialConversionRate = $totalEverTrial > 0
                ? round(($convertedFromTrial / $totalEverTrial) * 100, 1)
                : 0;

            // Churn rate: cancelled this month / active at start of month
            $cancelledThisMonth = DB::table('store_subscriptions')
                ->where('status', 'cancelled')
                ->where('cancelled_at', '>=', now()->startOfMonth())
                ->count();

            $activeAtMonthStart = $activeSubscriptions + $cancelledThisMonth;
            $churnRate = $activeAtMonthStart > 0
                ? round(($cancelledThisMonth / $activeAtMonthStart) * 100, 1)
                : 0;

            // ARPU — Average Revenue Per User (monthly)
            $mrr = (float) DB::table('store_subscriptions')
                ->where('status', 'active')
                ->join('subscription_plans', 'store_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.monthly_price');

            $arpu = $activeSubscriptions > 0 ? $mrr / $activeSubscriptions : 0;

            // Upcoming renewals in next 7 days
            $upcomingRenewals = DB::table('store_subscriptions')
                ->where('status', 'active')
                ->whereBetween('current_period_end', [now(), now()->addDays(7)])
                ->count();

            // Grace period subscriptions
            $inGracePeriod = DB::table('store_subscriptions')
                ->where('status', 'grace_period')
                ->count();

            // Active discount codes
            $activeDiscounts = DB::table('subscription_discounts')
                ->where(function ($q) {
                    $q->whereNull('valid_to')
                      ->orWhere('valid_to', '>=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('max_uses')
                      ->orWhereColumn('times_used', '<', 'max_uses');
                })
                ->count();

            return compact(
                'trialConversionRate', 'churnRate', 'arpu',
                'upcomingRenewals', 'inGracePeriod', 'activeDiscounts'
            );
        });

        return [
            Stat::make(__('admin_dashboard.trial_to_paid'), $data['trialConversionRate'] . '%')
                ->description(__('admin_dashboard.trial_conversion_rate'))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->chart([30, 45, 55, 60, 58, 65, 70])
                ->color($data['trialConversionRate'] >= 50 ? 'success' : 'warning'),

            Stat::make(__('admin_dashboard.churn_rate'), $data['churnRate'] . '%')
                ->description(__('admin_dashboard.monthly_churn_rate'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart([5, 3, 4, 6, 3, 2, 4])
                ->color($data['churnRate'] <= 5 ? 'success' : 'danger'),

            Stat::make(__('admin_dashboard.avg_revenue_per_store'), Number::currency($data['arpu'], 'SAR'))
                ->description(__('admin_dashboard.arpu_monthly'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),

            Stat::make(__('admin_dashboard.upcoming_renewals'), Number::format($data['upcomingRenewals']))
                ->description(__('admin_dashboard.next_7_days'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make(__('admin_dashboard.in_grace_period'), Number::format($data['inGracePeriod']))
                ->description(__('admin_dashboard.requires_attention'))
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($data['inGracePeriod'] > 0 ? 'danger' : 'success'),

            Stat::make(__('admin_dashboard.active_discounts'), Number::format($data['activeDiscounts']))
                ->description(__('admin_dashboard.discount_codes_in_use'))
                ->descriptionIcon('heroicon-m-tag')
                ->color('gray'),
        ];
    }
}
