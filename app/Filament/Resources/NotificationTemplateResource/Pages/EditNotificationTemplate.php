<?php

namespace App\Filament\Resources\NotificationTemplateResource\Pages;

use App\Domain\Notification\Services\NotificationTemplateService;
use App\Filament\Resources\NotificationTemplateResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditNotificationTemplate extends EditRecord
{
    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        app(NotificationTemplateService::class)->flushTemplateCache($this->record);
    }
}
