<?php

namespace App\Filament\Resources\ProviderUserResource\Pages;

use App\Filament\Resources\ProviderUserResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditProviderUser extends EditRecord
{
    protected static string $resource = ProviderUserResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
