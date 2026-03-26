<?php

namespace App\Filament\Resources\BusinessTypeResource\Pages;

use App\Filament\Resources\BusinessTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessType extends ViewRecord
{
    protected static string $resource = BusinessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
