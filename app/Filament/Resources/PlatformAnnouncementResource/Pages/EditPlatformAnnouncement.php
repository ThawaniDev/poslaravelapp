<?php

namespace App\Filament\Resources\PlatformAnnouncementResource\Pages;

use App\Filament\Resources\PlatformAnnouncementResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPlatformAnnouncement extends EditRecord
{
    protected static string $resource = PlatformAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
