<?php

namespace App\Filament\Resources\TaxExemptionTypeResource\Pages;

use App\Filament\Resources\TaxExemptionTypeResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditTaxExemptionType extends EditRecord
{
    protected static string $resource = TaxExemptionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
