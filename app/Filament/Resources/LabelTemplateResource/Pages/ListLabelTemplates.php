<?php

namespace App\Filament\Resources\LabelTemplateResource\Pages;

use App\Filament\Resources\LabelTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLabelTemplates extends ListRecords
{
    protected static string $resource = LabelTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
