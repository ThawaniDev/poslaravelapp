<?php

namespace App\Filament\Resources\ProviderPaymentResource\Widgets;

use App\Domain\ProviderPayment\Enums\ProviderPaymentStatus;
use App\Domain\ProviderPayment\Models\ProviderPayment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRevenue = ProviderPayment::where('status', ProviderPaymentStatus::Completed)->sum('total_amount');
        $pendingPayments = ProviderPayment::where('status', ProviderPaymentStatus::Pending)->count();
        $completedToday = ProviderPayment::where('status', ProviderPaymentStatus::Completed)
            ->whereDate('created_at', today())
            ->count();
        $failedPayments = ProviderPayment::where('status', ProviderPaymentStatus::Failed)->count();
        $refundedTotal = ProviderPayment::where('status', ProviderPaymentStatus::Refunded)->sum('refund_amount');

        return [
            Stat::make(__('provider_payments.stat_total_revenue'), number_format($totalRevenue, 2) . ' SAR')
                ->description(__('provider_payments.stat_total_revenue_desc'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
            Stat::make(__('provider_payments.stat_pending'), $pendingPayments)
                ->description(__('provider_payments.stat_pending_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make(__('provider_payments.stat_completed_today'), $completedToday)
                ->description(__('provider_payments.stat_completed_today_desc'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make(__('provider_payments.stat_failed'), $failedPayments)
                ->description(__('provider_payments.stat_failed_desc'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
            Stat::make(__('provider_payments.stat_refunded'), number_format($refundedTotal ?? 0, 2) . ' SAR')
                ->description(__('provider_payments.stat_refunded_desc'))
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('gray'),
        ];
    }
}
