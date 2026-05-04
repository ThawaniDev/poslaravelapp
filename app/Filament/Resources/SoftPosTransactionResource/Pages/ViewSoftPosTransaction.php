<?php

namespace App\Filament\Resources\SoftPosTransactionResource\Pages;

use App\Filament\Resources\SoftPosTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSoftPosTransaction extends ViewRecord
{
    protected static string $resource = SoftPosTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
