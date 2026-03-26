<?php

namespace App\Filament\Resources\TemplateMarketplaceListingResource\Pages;

use App\Filament\Resources\TemplateMarketplaceListingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTemplateMarketplaceListings extends ListRecords
{
    protected static string $resource = TemplateMarketplaceListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
