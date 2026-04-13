<?php

namespace App\Filament\Resources\DatabaseBackupResource\Pages;

use App\Filament\Resources\DatabaseBackupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDatabaseBackup extends ViewRecord
{
    protected static string $resource = DatabaseBackupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => auth('admin')->user()?->hasPermissionTo('infrastructure.manage')),
        ];
    }
}
