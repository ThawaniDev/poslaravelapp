<?php

namespace App\Filament\Resources\SupportTicketResource\Widgets;

use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class SupportStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $total      = DB::table('support_tickets')->count();
        $open       = DB::table('support_tickets')->where('status', TicketStatus::Open->value)->count();
        $inProgress = DB::table('support_tickets')->where('status', TicketStatus::InProgress->value)->count();
        $resolved   = DB::table('support_tickets')->where('status', TicketStatus::Resolved->value)->count();

        $slaBreach  = DB::table('support_tickets')
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value])
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '<', now())
            ->count();

        $critical = DB::table('support_tickets')
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value])
            ->where('priority', TicketPriority::Critical->value)
            ->count();

        $resolvedToday = DB::table('support_tickets')
            ->where('status', TicketStatus::Resolved->value)
            ->where('resolved_at', '>=', now()->startOfDay())
            ->count();

        $unassigned = DB::table('support_tickets')
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value])
            ->whereNull('assigned_to')
            ->count();

        return [
            Stat::make(__('support.stat_total'), Number::format($total))
                ->description(__('support.stat_all_tickets'))
                ->descriptionIcon('heroicon-m-ticket')
                ->color('gray'),

            Stat::make(__('support.stat_open'), Number::format($open))
                ->description(__('support.stat_awaiting_response'))
                ->descriptionIcon('heroicon-m-envelope-open')
                ->color($open > 10 ? 'danger' : 'warning'),

            Stat::make(__('support.stat_in_progress'), Number::format($inProgress))
                ->description(__('support.stat_being_handled'))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make(__('support.stat_resolved_today'), Number::format($resolvedToday))
                ->description(__('support.stat_closed_today'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('support.stat_sla_breached'), Number::format($slaBreach))
                ->description(__('support.stat_past_deadline'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($slaBreach > 0 ? 'danger' : 'success'),

            Stat::make(__('support.stat_critical'), Number::format($critical))
                ->description(__('support.stat_critical_tickets'))
                ->descriptionIcon('heroicon-m-fire')
                ->color($critical > 0 ? 'danger' : 'gray'),

            Stat::make(__('support.stat_unassigned'), Number::format($unassigned))
                ->description(__('support.stat_needs_assignment'))
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($unassigned > 0 ? 'warning' : 'success'),
        ];
    }
}
