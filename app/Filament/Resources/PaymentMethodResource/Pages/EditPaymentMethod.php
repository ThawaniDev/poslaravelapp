<?php

namespace App\Filament\Resources\PaymentMethodResource\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Filament\Resources\PaymentMethodResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPaymentMethod extends EditRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_payment_method',
            entityType: 'payment_method',
            entityId: $this->record->id,
            details: ['method_key' => $this->record->method_key->value ?? $this->record->method_key, 'name' => $this->record->name],
        );
    }
}
