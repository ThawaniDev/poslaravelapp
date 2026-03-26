<?php

namespace App\Filament\Resources\SupportTicketResource\Widgets;

use App\Domain\Support\Enums\TicketCategory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TicketsByCategoryChart extends ChartWidget
{
    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('support.chart_by_category');
    }

    protected function getData(): array
    {
        $data = DB::table('support_tickets')
            ->select('category', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('cnt', 'category');

        $categories = TicketCategory::cases();
        $colors = ['#FD8209', '#3b82f6', '#10b981', '#8b5cf6', '#6b7280', '#ef4444'];

        return [
            'datasets' => [
                [
                    'data' => collect($categories)->map(fn ($c) => (int) ($data[$c->value] ?? 0))->toArray(),
                    'backgroundColor' => array_slice($colors, 0, count($categories)),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => collect($categories)->map(fn ($c) => $c->label())->toArray(),
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
            'cutout' => '55%',
        ];
    }
}
