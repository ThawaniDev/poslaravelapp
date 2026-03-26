<?php

namespace App\Filament\Resources\SupportTicketResource\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TicketVolumeChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('support.chart_ticket_volume');
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString());

        $created = DB::table('support_tickets')
            ->select(DB::raw("DATE(created_at) as day"), DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $resolved = DB::table('support_tickets')
            ->select(DB::raw("DATE(resolved_at) as day"), DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy('day')
            ->pluck('cnt', 'day');

        return [
            'datasets' => [
                [
                    'label' => __('support.chart_created'),
                    'data' => $days->map(fn ($d) => (int) ($created[$d] ?? 0))->values()->toArray(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => __('support.chart_resolved'),
                    'data' => $days->map(fn ($d) => (int) ($resolved[$d] ?? 0))->values()->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
