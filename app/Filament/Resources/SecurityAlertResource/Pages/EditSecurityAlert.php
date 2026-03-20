<?php

namespace App\Filament\Resources\SecurityAlertResource\Pages;

use App\Filament\Resources\SecurityAlertResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditSecurityAlert extends EditRecord
{
    protected static string $resource = SecurityAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
