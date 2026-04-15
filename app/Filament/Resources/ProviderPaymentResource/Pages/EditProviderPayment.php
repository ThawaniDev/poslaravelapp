<?php

namespace App\Filament\Resources\ProviderPaymentResource\Pages;

use App\Filament\Resources\ProviderPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProviderPayment extends EditRecord
{
    protected static string $resource = ProviderPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
