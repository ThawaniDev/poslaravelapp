<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformRevenueTrendChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('admin_dashboard.revenue_trend_heading');
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString());

        $cached = Cache::remember('filament:revenue_trend', 300, function () {
            $revenue = DB::table('transactions')
                ->select(DB::raw("DATE(created_at) as day"), DB::raw('SUM(total_amount) as revenue'))
                ->where('created_at', '>=', now()->subDays(30)->startOfDay())
                ->whereIn('status', ['completed', 'paid'])
                ->groupBy('day')
                ->pluck('revenue', 'day')
                ->toArray();

            $newStores = DB::table('stores')
                ->select(DB::raw("DATE(created_at) as day"), DB::raw('COUNT(*) as cnt'))
                ->where('created_at', '>=', now()->subDays(30)->startOfDay())
                ->groupBy('day')
                ->pluck('cnt', 'day')
                ->toArray();

            return compact('revenue', 'newStores');
        });

        $revenue = collect($cached['revenue']);
        $newStores = collect($cached['newStores']);

        return [
            'datasets' => [
                [
                    'label' => __('admin_dashboard.revenue_sar'),
                    'data' => $days->map(fn ($d) => (float) ($revenue[$d] ?? 0))->values()->toArray(),
                    'borderColor' => '#fd8208',
                    'backgroundColor' => 'rgba(253, 130, 8, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => __('admin_dashboard.new_stores'),
                    'data' => $days->map(fn ($d) => (int) ($newStores[$d] ?? 0))->values()->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => false,
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
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
