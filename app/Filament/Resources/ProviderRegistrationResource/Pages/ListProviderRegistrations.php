<?php

namespace App\Filament\Resources\ProviderRegistrationResource\Pages;

use App\Filament\Resources\ProviderRegistrationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListProviderRegistrations extends ListRecords
{
    protected static string $resource = ProviderRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
