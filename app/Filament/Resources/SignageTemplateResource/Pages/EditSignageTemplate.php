<?php

namespace App\Filament\Resources\SignageTemplateResource\Pages;

use App\Filament\Resources\SignageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSignageTemplate extends EditRecord
{
    protected static string $resource = SignageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
