<?php

namespace App\Filament\Resources\HeldCartResource\Pages;

use App\Filament\Resources\HeldCartResource;
use Filament\Resources\Pages\ListRecords;

class ListHeldCarts extends ListRecords
{
    protected static string $resource = HeldCartResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
