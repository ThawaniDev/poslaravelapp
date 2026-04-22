<?php

namespace App\Filament\Resources\PosSessionResource\Pages;

use App\Filament\Resources\PosSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListPosSessions extends ListRecords
{
    protected static string $resource = PosSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
