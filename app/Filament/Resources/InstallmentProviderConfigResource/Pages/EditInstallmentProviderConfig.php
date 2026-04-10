<?php

namespace App\Filament\Resources\InstallmentProviderConfigResource\Pages;

use App\Filament\Resources\InstallmentProviderConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentProviderConfig extends EditRecord
{
    protected static string $resource = InstallmentProviderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
