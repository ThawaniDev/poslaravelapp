<?php

namespace App\Filament\Resources\PredefinedProductResource\Pages;

use App\Filament\Resources\PredefinedProductResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPredefinedProduct extends EditRecord
{
    protected static string $resource = PredefinedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
