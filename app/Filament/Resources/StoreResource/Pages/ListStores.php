<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('map_view')
                ->label('Map View')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->url(StoreResource::getUrl('map')),
            Actions\CreateAction::make(),
        ];
    }
}
