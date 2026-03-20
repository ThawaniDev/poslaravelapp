<?php

namespace App\Filament\Resources\PlatformAnnouncementResource\Pages;

use App\Filament\Resources\PlatformAnnouncementResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPlatformAnnouncements extends ListRecords
{
    protected static string $resource = PlatformAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
