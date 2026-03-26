<?php

namespace App\Filament\Resources\ReceiptLayoutTemplateResource\Pages;

use App\Filament\Resources\ReceiptLayoutTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListReceiptLayoutTemplates extends ListRecords
{
    protected static string $resource = ReceiptLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
