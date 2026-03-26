<?php

namespace App\Filament\Resources\PosLayoutTemplateResource\Pages;

use App\Filament\Resources\PosLayoutTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListPosLayoutTemplates extends ListRecords
{
    protected static string $resource = PosLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
