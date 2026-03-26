<?php

namespace App\Filament\Resources\StoreSubscriptionResource\Pages;

use App\Filament\Resources\StoreSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStoreSubscription extends ViewRecord
{
    protected static string $resource = StoreSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
