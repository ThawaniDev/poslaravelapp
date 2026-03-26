<?php

namespace App\Filament\Resources\AdminTrustedDeviceResource\Pages;

use App\Filament\Resources\AdminTrustedDeviceResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminTrustedDevices extends ListRecords
{
    protected static string $resource = AdminTrustedDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
