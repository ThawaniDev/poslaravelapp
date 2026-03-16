<?php

namespace App\Filament\Widgets;

use App\Domain\OwnerDashboard\Services\OwnerDashboardService;
use Filament\Widgets\Widget;

class ActiveCashiersWidget extends Widget
{
    protected static string $view = 'filament.widgets.active-cashiers';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function getData(): array
    {
        $user = auth()->user();
        if (! $user?->store_id) {
            return ['cashiers' => []];
        }

        $service = app(OwnerDashboardService::class);

        return [
            'cashiers' => $service->activeCashiers($user->store_id),
        ];
    }
}
