<?php

namespace App\Filament\Resources\AdminRoleResource\Pages;

use App\Filament\Resources\AdminRoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminRole extends ViewRecord
{
    protected static string $resource = AdminRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => !$this->record->is_system || auth('admin')->user()?->isSuperAdmin()),
        ];
    }
}
