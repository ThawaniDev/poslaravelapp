<?php

namespace App\Filament\Resources\SubscriptionDiscountResource\Pages;

use App\Filament\Resources\SubscriptionDiscountResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditSubscriptionDiscount extends EditRecord
{
    protected static string $resource = SubscriptionDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
