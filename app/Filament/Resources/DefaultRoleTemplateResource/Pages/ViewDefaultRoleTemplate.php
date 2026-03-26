<?php

namespace App\Filament\Resources\DefaultRoleTemplateResource\Pages;

use App\Filament\Resources\DefaultRoleTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDefaultRoleTemplate extends ViewRecord
{
    protected static string $resource = DefaultRoleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
