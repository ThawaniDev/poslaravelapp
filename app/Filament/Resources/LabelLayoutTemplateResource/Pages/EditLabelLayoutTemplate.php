<?php

namespace App\Filament\Resources\LabelLayoutTemplateResource\Pages;

use App\Filament\Resources\LabelLayoutTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLabelLayoutTemplate extends EditRecord
{
    protected static string $resource = LabelLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
