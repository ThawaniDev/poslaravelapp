<?php

namespace App\Filament\Resources\CertifiedHardwareResource\Pages;

use App\Filament\Resources\CertifiedHardwareResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditCertifiedHardware extends EditRecord
{
    protected static string $resource = CertifiedHardwareResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
