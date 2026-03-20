<?php

namespace App\Filament\Resources\AgeRestrictedCategoryResource\Pages;

use App\Filament\Resources\AgeRestrictedCategoryResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditAgeRestrictedCategory extends EditRecord
{
    protected static string $resource = AgeRestrictedCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
