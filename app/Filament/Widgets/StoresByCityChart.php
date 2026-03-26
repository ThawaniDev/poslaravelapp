<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StoresByCityChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('admin_dashboard.stores_by_city');
    }

    protected function getData(): array
    {
        $data = Cache::remember('filament:stores_by_city', 600, function () {
            return DB::table('stores')
                ->select('city', DB::raw('COUNT(*) as count'))
                ->where('is_active', true)
                ->whereNotNull('city')
                ->groupBy('city')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->toArray();
        });

        $colors = [
            '#FD8209', '#FFBF0D', '#3b82f6', '#10b981', '#8b5cf6',
            '#ef4444', '#f59e0b', '#6366f1', '#ec4899', '#14b8a6',
        ];

        return [
            'datasets' => [
                [
                    'data' => array_map(fn ($row) => $row->count, $data),
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_map(fn ($row) => $row->city, $data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
