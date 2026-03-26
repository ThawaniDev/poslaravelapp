<?php

namespace App\Filament\Resources\ImplementationFeeResource\Pages;

use App\Filament\Resources\ImplementationFeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImplementationFee extends CreateRecord
{
    protected static string $resource = ImplementationFeeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
