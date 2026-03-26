<?php

namespace App\Filament\Resources\MasterTranslationStringResource\Pages;

use App\Filament\Resources\MasterTranslationStringResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListMasterTranslationStrings extends ListRecords
{
    protected static string $resource = MasterTranslationStringResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
