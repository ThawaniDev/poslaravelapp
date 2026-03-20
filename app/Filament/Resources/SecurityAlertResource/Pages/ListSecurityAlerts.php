<?php

namespace App\Filament\Resources\SecurityAlertResource\Pages;

use App\Filament\Resources\SecurityAlertResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListSecurityAlerts extends ListRecords
{
    protected static string $resource = SecurityAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
