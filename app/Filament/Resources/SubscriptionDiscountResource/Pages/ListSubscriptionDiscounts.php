<?php

namespace App\Filament\Resources\SubscriptionDiscountResource\Pages;

use App\Filament\Resources\SubscriptionDiscountResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListSubscriptionDiscounts extends ListRecords
{
    protected static string $resource = SubscriptionDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
