<?php

namespace App\Filament\Resources\TaxExemptionTypeResource\Pages;

use App\Filament\Resources\TaxExemptionTypeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListTaxExemptionTypes extends ListRecords
{
    protected static string $resource = TaxExemptionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
