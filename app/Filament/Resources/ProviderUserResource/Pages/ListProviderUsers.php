<?php

namespace App\Filament\Resources\ProviderUserResource\Pages;

use App\Filament\Resources\ProviderUserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListProviderUsers extends ListRecords
{
    protected static string $resource = ProviderUserResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
