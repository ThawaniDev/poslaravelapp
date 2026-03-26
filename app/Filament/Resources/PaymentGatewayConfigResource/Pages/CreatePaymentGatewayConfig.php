<?php

namespace App\Filament\Resources\PaymentGatewayConfigResource\Pages;

use App\Filament\Resources\PaymentGatewayConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentGatewayConfig extends CreateRecord
{
    protected static string $resource = PaymentGatewayConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
