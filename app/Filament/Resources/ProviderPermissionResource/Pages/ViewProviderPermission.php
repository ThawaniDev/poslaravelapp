<?php

namespace App\Filament\Resources\ProviderPermissionResource\Pages;

use App\Filament\Resources\ProviderPermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderPermission extends ViewRecord
{
    protected static string $resource = ProviderPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
