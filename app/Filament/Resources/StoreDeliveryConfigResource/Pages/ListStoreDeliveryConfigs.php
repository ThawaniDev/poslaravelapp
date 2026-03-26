<?php

namespace App\Filament\Resources\StoreDeliveryConfigResource\Pages;

use App\Filament\Resources\StoreDeliveryConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreDeliveryConfigs extends ListRecords
{
    protected static string $resource = StoreDeliveryConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
