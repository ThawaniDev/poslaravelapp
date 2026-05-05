<?php

namespace App\Filament\Resources\LabelPrintHistoryResource\Pages;

use App\Filament\Resources\LabelPrintHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListLabelPrintHistories extends ListRecords
{
    protected static string $resource = LabelPrintHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
