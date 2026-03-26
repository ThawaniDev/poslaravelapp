<?php

namespace App\Filament\Resources\PosLayoutTemplateResource\Pages;

use App\Filament\Resources\PosLayoutTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosLayoutTemplate extends EditRecord
{
    protected static string $resource = PosLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
