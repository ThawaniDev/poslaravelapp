<?php

namespace App\Filament\Resources\CfdThemeResource\Pages;

use App\Filament\Resources\CfdThemeResource;
use Filament\Resources\Pages\ListRecords;

class ListCfdThemes extends ListRecords
{
    protected static string $resource = CfdThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
