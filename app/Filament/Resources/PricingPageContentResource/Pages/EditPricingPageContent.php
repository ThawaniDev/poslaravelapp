<?php

namespace App\Filament\Resources\PricingPageContentResource\Pages;

use App\Filament\Resources\PricingPageContentResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPricingPageContent extends EditRecord
{
    protected static string $resource = PricingPageContentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
