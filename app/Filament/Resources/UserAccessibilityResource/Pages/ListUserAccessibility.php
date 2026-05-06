<?php

namespace App\Filament\Resources\UserAccessibilityResource\Pages;

use App\Filament\Resources\UserAccessibilityResource;
use Filament\Resources\Pages\ListRecords;

class ListUserAccessibility extends ListRecords
{
    protected static string $resource = UserAccessibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
