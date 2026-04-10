<?php

namespace App\Filament\Resources\AIFeatureDefinitionResource\Pages;

use App\Filament\Resources\AIFeatureDefinitionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAIFeatureDefinitions extends ListRecords
{
    protected static string $resource = AIFeatureDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
