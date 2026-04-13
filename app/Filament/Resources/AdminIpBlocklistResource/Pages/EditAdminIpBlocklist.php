<?php

namespace App\Filament\Resources\AdminIpBlocklistResource\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Filament\Resources\AdminIpBlocklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdminIpBlocklist extends EditRecord
{
    protected static string $resource = AdminIpBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->label(__('security.unblock')),
        ];
    }

    protected function afterSave(): void
    {
        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_ip_blocklist',
            entityType: 'admin_ip_blocklist',
            entityId: $this->record->id,
            details: ['ip_address' => $this->record->ip_address],
        );
    }
}
