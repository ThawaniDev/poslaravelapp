<?php

namespace App\Filament\Resources\StoreSubscriptionResource\Pages;

use App\Filament\Resources\StoreSubscriptionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListStoreSubscriptions extends ListRecords
{
    protected static string $resource = StoreSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
