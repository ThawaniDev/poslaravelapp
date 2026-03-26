<?php

namespace App\Filament\Resources\PaymentGatewayConfigResource\Pages;

use App\Filament\Resources\PaymentGatewayConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentGatewayConfig extends ViewRecord
{
    protected static string $resource = PaymentGatewayConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
