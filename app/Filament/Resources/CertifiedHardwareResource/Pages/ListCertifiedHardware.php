<?php

namespace App\Filament\Resources\CertifiedHardwareResource\Pages;

use App\Filament\Resources\CertifiedHardwareResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCertifiedHardware extends ListRecords
{
    protected static string $resource = CertifiedHardwareResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
