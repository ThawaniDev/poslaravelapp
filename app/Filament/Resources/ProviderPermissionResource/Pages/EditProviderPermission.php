<?php

namespace App\Filament\Resources\ProviderPermissionResource\Pages;

use App\Filament\Resources\ProviderPermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProviderPermission extends EditRecord
{
    protected static string $resource = ProviderPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
