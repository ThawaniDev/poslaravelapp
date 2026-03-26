<?php

namespace App\Filament\Resources\DatabaseBackupResource\Pages;

use App\Filament\Resources\DatabaseBackupResource;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseBackups extends ListRecords
{
    protected static string $resource = DatabaseBackupResource::class;
}
