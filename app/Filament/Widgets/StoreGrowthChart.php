<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StoreGrowthChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return __('admin_dashboard.store_growth_heading');
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn ($m) => now()->subMonths($m)->startOfMonth());

        $cached = Cache::remember('filament:store_growth', 600, function () use ($months) {
            $newPerMonth = DB::table('stores')
                ->select(
                    DB::raw("DATE_TRUNC('month', created_at) as month"),
                    DB::raw('COUNT(*) as cnt')
                )
                ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
                ->groupBy('month')
                ->pluck('cnt', 'month')
                ->toArray();

            $totalBefore = DB::table('stores')
                ->where('created_at', '<', now()->subMonths(12)->startOfMonth())
                ->count();

            return compact('newPerMonth', 'totalBefore');
        });

        $newPerMonth = collect($cached['newPerMonth']);
        $cumulative = $cached['totalBefore'];

        $cumulativeData = [];
        $newData = [];
        $labels = [];

        foreach ($months as $month) {
            $key = $month->toDateString() . ' 00:00:00';
            $altKey = $month->format('Y-m-d') . 'T00:00:00';
            $new = (int) ($newPerMonth[$key] ?? $newPerMonth[$altKey] ?? 0);
            $cumulative += $new;

            $cumulativeData[] = $cumulative;
            $newData[] = $new;
            $labels[] = $month->format('M Y');
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin_dashboard.cumulative_stores'),
                    'data' => $cumulativeData,
                    'borderColor' => '#FD8209',
                    'backgroundColor' => 'rgba(253, 130, 8, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => __('admin_dashboard.new_registrations'),
                    'data' => $newData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.6)',
                    'type' => 'bar',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
