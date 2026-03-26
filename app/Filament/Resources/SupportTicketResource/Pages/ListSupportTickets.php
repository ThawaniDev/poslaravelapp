<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Widgets\SupportStatsOverview;
use App\Filament\Resources\SupportTicketResource\Widgets\TicketsByCategoryChart;
use App\Filament\Resources\SupportTicketResource\Widgets\TicketVolumeChart;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupportStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TicketVolumeChart::class,
            TicketsByCategoryChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|string|array
    {
        return 2;
    }
}
