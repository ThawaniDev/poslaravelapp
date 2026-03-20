<?php

namespace App\Filament\Resources\AdminIpAllowlistResource\Pages;

use App\Filament\Resources\AdminIpAllowlistResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAdminIpAllowlists extends ListRecords
{
    protected static string $resource = AdminIpAllowlistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
