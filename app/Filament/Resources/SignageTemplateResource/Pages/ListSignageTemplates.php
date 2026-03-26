<?php

namespace App\Filament\Resources\SignageTemplateResource\Pages;

use App\Filament\Resources\SignageTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListSignageTemplates extends ListRecords
{
    protected static string $resource = SignageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
