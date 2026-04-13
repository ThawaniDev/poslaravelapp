<?php

namespace App\Filament\Resources\AdminIpAllowlistResource\Pages;

use App\Filament\Resources\AdminIpAllowlistResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminIpAllowlist extends ViewRecord
{
    protected static string $resource = AdminIpAllowlistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->label(__('security.remove_selected')),
        ];
    }
}
