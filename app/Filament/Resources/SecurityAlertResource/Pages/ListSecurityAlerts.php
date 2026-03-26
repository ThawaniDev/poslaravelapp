<?php

namespace App\Filament\Resources\SecurityAlertResource\Pages;

use App\Filament\Resources\SecurityAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityAlerts extends ListRecords
{
    protected static string $resource = SecurityAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
