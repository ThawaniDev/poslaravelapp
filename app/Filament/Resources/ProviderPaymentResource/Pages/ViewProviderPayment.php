<?php

namespace App\Filament\Resources\ProviderPaymentResource\Pages;

use App\Filament\Resources\ProviderPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderPayment extends ViewRecord
{
    protected static string $resource = ProviderPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
