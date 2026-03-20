<?php

namespace App\Filament\Resources\AppReleaseResource\Pages;

use App\Filament\Resources\AppReleaseResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAppReleases extends ListRecords
{
    protected static string $resource = AppReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
