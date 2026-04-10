<?php

namespace App\Filament\Resources\InstallmentProviderConfigResource\Pages;

use App\Filament\Resources\InstallmentProviderConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstallmentProviderConfig extends CreateRecord
{
    protected static string $resource = InstallmentProviderConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
