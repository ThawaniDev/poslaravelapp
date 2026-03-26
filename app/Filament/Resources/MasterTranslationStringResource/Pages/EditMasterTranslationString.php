<?php

namespace App\Filament\Resources\MasterTranslationStringResource\Pages;

use App\Filament\Resources\MasterTranslationStringResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditMasterTranslationString extends EditRecord
{
    protected static string $resource = MasterTranslationStringResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
