<?php

namespace App\Filament\Resources\StoreSubscriptionResource\Pages;

use App\Filament\Resources\StoreSubscriptionResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditStoreSubscription extends EditRecord
{
    protected static string $resource = StoreSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
