<?php

namespace App\Filament\Resources\ProviderUserResource\Pages;

use App\Filament\Resources\ProviderUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderUser extends ViewRecord
{
    protected static string $resource = ProviderUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
