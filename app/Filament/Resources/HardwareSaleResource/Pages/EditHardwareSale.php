<?php

namespace App\Filament\Resources\HardwareSaleResource\Pages;

use App\Filament\Resources\HardwareSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHardwareSale extends EditRecord
{
    protected static string $resource = HardwareSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
