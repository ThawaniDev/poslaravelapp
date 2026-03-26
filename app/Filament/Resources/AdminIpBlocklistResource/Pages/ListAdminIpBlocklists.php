<?php

namespace App\Filament\Resources\AdminIpBlocklistResource\Pages;

use App\Filament\Resources\AdminIpBlocklistResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAdminIpBlocklists extends ListRecords
{
    protected static string $resource = AdminIpBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
