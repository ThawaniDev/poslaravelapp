<?php

namespace App\Filament\Resources\AdminIpAllowlistResource\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Filament\Resources\AdminIpAllowlistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminIpAllowlist extends CreateRecord
{
    protected static string $resource = AdminIpAllowlistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by'] = auth('admin')->id();
        $data['created_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'add_ip_allowlist',
            entityType: 'admin_ip_allowlist',
            entityId: $this->record->id,
            details: ['ip_address' => $this->record->ip_address],
        );
    }
}
