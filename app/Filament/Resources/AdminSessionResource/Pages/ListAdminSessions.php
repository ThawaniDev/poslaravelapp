<?php

namespace App\Filament\Resources\AdminSessionResource\Pages;

use App\Filament\Resources\AdminSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminSessions extends ListRecords
{
    protected static string $resource = AdminSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
