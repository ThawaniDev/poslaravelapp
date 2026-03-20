<?php

namespace App\Filament\Resources\ProviderRegistrationResource\Pages;

use App\Filament\Resources\ProviderRegistrationResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditProviderRegistration extends EditRecord
{
    protected static string $resource = ProviderRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
