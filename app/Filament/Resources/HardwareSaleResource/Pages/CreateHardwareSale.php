<?php

namespace App\Filament\Resources\HardwareSaleResource\Pages;

use App\Filament\Resources\HardwareSaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHardwareSale extends CreateRecord
{
    protected static string $resource = HardwareSaleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
