<?php

namespace App\Filament\Resources\DeliveryPlatformResource\Pages;

use App\Filament\Resources\DeliveryPlatformResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditDeliveryPlatform extends EditRecord
{
    protected static string $resource = DeliveryPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
