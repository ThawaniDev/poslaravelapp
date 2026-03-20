<?php

namespace App\Filament\Resources\AdminIpAllowlistResource\Pages;

use App\Filament\Resources\AdminIpAllowlistResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditAdminIpAllowlist extends EditRecord
{
    protected static string $resource = AdminIpAllowlistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
