<?php

namespace App\Filament\Resources\DeliveryPlatformResource\Pages;

use App\Filament\Resources\DeliveryPlatformResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListDeliveryPlatforms extends ListRecords
{
    protected static string $resource = DeliveryPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
