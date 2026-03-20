<?php

namespace App\Filament\Resources\AccountingIntegrationConfigResource\Pages;

use App\Filament\Resources\AccountingIntegrationConfigResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAccountingIntegrationConfigs extends ListRecords
{
    protected static string $resource = AccountingIntegrationConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
