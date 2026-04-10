<?php

namespace App\Filament\Resources\InstallmentProviderConfigResource\Pages;

use App\Filament\Resources\InstallmentProviderConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentProviderConfigs extends ListRecords
{
    protected static string $resource = InstallmentProviderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
