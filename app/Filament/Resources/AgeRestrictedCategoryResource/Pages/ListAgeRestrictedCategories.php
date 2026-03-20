<?php

namespace App\Filament\Resources\AgeRestrictedCategoryResource\Pages;

use App\Filament\Resources\AgeRestrictedCategoryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAgeRestrictedCategories extends ListRecords
{
    protected static string $resource = AgeRestrictedCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
