<?php

namespace App\Filament\Resources\PricingPageContentResource\Pages;

use App\Filament\Resources\PricingPageContentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingPageContent extends ViewRecord
{
    protected static string $resource = PricingPageContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
