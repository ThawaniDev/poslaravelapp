<?php

namespace App\Filament\Resources\DefaultRoleTemplateResource\Pages;

use App\Filament\Resources\DefaultRoleTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDefaultRoleTemplates extends ListRecords
{
    protected static string $resource = DefaultRoleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
