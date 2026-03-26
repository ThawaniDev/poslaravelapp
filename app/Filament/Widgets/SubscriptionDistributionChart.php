<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubscriptionDistributionChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('admin_dashboard.subscription_distribution');
    }

    protected function getData(): array
    {
        $data = Cache::remember('filament:subscription_distribution', 300, function () {
            return DB::table('store_subscriptions')
                ->join('subscription_plans', 'store_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                ->where('store_subscriptions.status', '!=', 'cancelled')
                ->select('subscription_plans.name', DB::raw('COUNT(*) as count'))
                ->groupBy('subscription_plans.name')
                ->orderByDesc('count')
                ->get()
                ->toArray();
        });

        $colors = ['#FD8209', '#FFBF0D', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#f59e0b', '#6366f1'];

        return [
            'datasets' => [
                [
                    'data' => array_map(fn ($row) => $row->count, $data),
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => array_map(fn ($row) => $row->name, $data),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '60%',
        ];
    }
}
