<?php

namespace App\Filament\Resources\AIFeatureDefinitionResource\Pages;

use App\Filament\Resources\AIFeatureDefinitionResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditAIFeatureDefinition extends EditRecord
{
    protected static string $resource = AIFeatureDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
