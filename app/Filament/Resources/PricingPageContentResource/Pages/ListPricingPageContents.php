<?php

namespace App\Filament\Resources\PricingPageContentResource\Pages;

use App\Filament\Resources\PricingPageContentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPricingPageContents extends ListRecords
{
    protected static string $resource = PricingPageContentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
