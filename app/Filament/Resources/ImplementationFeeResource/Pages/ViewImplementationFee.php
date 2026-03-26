<?php

namespace App\Filament\Resources\ImplementationFeeResource\Pages;

use App\Filament\Resources\ImplementationFeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewImplementationFee extends ViewRecord
{
    protected static string $resource = ImplementationFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
