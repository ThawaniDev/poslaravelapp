<?php

namespace App\Filament\Resources\AdminIpBlocklistResource\Pages;

use App\Filament\Resources\AdminIpBlocklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminIpBlocklist extends ViewRecord
{
    protected static string $resource = AdminIpBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->label(__('security.unblock')),
        ];
    }
}
