<?php

namespace App\Filament\Resources\ImplementationFeeResource\Pages;

use App\Filament\Resources\ImplementationFeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImplementationFee extends EditRecord
{
    protected static string $resource = ImplementationFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
