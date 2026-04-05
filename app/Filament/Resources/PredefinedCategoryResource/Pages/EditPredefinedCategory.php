<?php

namespace App\Filament\Resources\PredefinedCategoryResource\Pages;

use App\Filament\Resources\PredefinedCategoryResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPredefinedCategory extends EditRecord
{
    protected static string $resource = PredefinedCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
