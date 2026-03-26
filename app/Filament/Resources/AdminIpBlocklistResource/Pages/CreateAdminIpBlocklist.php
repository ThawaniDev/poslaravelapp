<?php

namespace App\Filament\Resources\AdminIpBlocklistResource\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Filament\Resources\AdminIpBlocklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminIpBlocklist extends CreateRecord
{
    protected static string $resource = AdminIpBlocklistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['blocked_by'] = auth('admin')->id();
        $data['blocked_at'] = now();
        $data['created_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'add_ip_blocklist',
            entityType: 'admin_ip_blocklist',
            entityId: $this->record->id,
            details: ['ip_address' => $this->record->ip_address],
        );
    }
}
