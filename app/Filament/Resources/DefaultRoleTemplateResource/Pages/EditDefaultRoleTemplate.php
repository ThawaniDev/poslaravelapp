<?php

namespace App\Filament\Resources\DefaultRoleTemplateResource\Pages;

use App\Filament\Resources\DefaultRoleTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDefaultRoleTemplate extends EditRecord
{
    protected static string $resource = DefaultRoleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
