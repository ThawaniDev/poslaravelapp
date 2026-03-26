<?php

namespace App\Filament\Resources\HardwareSaleResource\Pages;

use App\Filament\Resources\HardwareSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHardwareSales extends ListRecords
{
    protected static string $resource = HardwareSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
