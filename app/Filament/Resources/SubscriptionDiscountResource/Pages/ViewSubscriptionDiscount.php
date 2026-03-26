<?php

namespace App\Filament\Resources\SubscriptionDiscountResource\Pages;

use App\Filament\Resources\SubscriptionDiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriptionDiscount extends ViewRecord
{
    protected static string $resource = SubscriptionDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
