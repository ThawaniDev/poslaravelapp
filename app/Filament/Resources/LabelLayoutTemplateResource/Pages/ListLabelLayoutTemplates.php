<?php

namespace App\Filament\Resources\LabelLayoutTemplateResource\Pages;

use App\Filament\Resources\LabelLayoutTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListLabelLayoutTemplates extends ListRecords
{
    protected static string $resource = LabelLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
