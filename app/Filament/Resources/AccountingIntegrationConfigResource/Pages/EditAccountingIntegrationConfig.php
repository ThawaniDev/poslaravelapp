<?php

namespace App\Filament\Resources\AccountingIntegrationConfigResource\Pages;

use App\Filament\Resources\AccountingIntegrationConfigResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditAccountingIntegrationConfig extends EditRecord
{
    protected static string $resource = AccountingIntegrationConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
