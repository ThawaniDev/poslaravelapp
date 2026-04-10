<?php

namespace App\Filament\Resources\AIProviderConfigResource\Pages;

use App\Filament\Resources\AIProviderConfigResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAIProviderConfigs extends ListRecords
{
    protected static string $resource = AIProviderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
