<?php

namespace App\Filament\Resources\AIProviderConfigResource\Pages;

use App\Filament\Resources\AIProviderConfigResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditAIProviderConfig extends EditRecord
{
    protected static string $resource = AIProviderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
