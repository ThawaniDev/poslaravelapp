<?php

namespace App\Filament\Resources\TemplatePurchaseResource\Pages;

use App\Filament\Resources\TemplatePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTemplatePurchase extends EditRecord
{
    protected static string $resource = TemplatePurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
