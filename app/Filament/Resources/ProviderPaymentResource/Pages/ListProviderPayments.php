<?php

namespace App\Filament\Resources\ProviderPaymentResource\Pages;

use App\Filament\Resources\ProviderPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderPayments extends ListRecords
{
    protected static string $resource = ProviderPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProviderPaymentResource\Widgets\PaymentStatsOverview::class,
        ];
    }
}
