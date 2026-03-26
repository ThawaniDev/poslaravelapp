<?php

namespace App\Filament\Resources\StoreDeliveryConfigResource\Pages;

use App\Filament\Resources\StoreDeliveryConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreDeliveryConfig extends EditRecord
{
    protected static string $resource = StoreDeliveryConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
