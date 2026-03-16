<?php

namespace App\Filament\Widgets;

use App\Domain\OwnerDashboard\Services\OwnerDashboardService;
use Filament\Widgets\ChartWidget;

class SalesTrendChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected static ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('owner_dashboard.filament.sales_last_7_days');
    }

    protected function getData(): array
    {
        $user = auth()->user();
        if (! $user?->store_id) {
            return ['datasets' => [], 'labels' => []];
        }

        $service = app(OwnerDashboardService::class);
        $data = $service->salesTrend($user->store_id, ['days' => 7]);

        $labels = collect($data['current'])->pluck('date')->map(
            fn ($d) => \Carbon\Carbon::parse($d)->format('D')
        )->toArray();

        return [
            'datasets' => [
                [
                    'label' => __('owner_dashboard.filament.revenue'),
                    'data' => collect($data['current'])->pluck('revenue')->toArray(),
                    'borderColor' => '#fd8208',
                    'backgroundColor' => 'rgba(253, 130, 8, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => __('owner_dashboard.filament.previous_period'),
                    'data' => collect($data['previous'])->pluck('revenue')->toArray(),
                    'borderColor' => '#d1d5db',
                    'backgroundColor' => 'rgba(209, 213, 219, 0.1)',
                    'fill' => false,
                    'borderDash' => [5, 5],
                    'tension' => 0.4,
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
