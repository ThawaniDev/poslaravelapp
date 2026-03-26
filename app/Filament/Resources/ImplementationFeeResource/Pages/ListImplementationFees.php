<?php

namespace App\Filament\Resources\ImplementationFeeResource\Pages;

use App\Filament\Resources\ImplementationFeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImplementationFees extends ListRecords
{
    protected static string $resource = ImplementationFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
