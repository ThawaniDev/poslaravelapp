<?php

namespace App\Filament\Resources\SupportedLocaleResource\Pages;

use App\Filament\Resources\SupportedLocaleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListSupportedLocales extends ListRecords
{
    protected static string $resource = SupportedLocaleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
