<?php

namespace App\Filament\Resources\InstallmentProviderConfigResource\Pages;

use App\Filament\Resources\InstallmentProviderConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallmentProviderConfig extends ViewRecord
{
    protected static string $resource = InstallmentProviderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
