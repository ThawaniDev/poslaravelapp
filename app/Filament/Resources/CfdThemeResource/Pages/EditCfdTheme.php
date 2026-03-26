<?php

namespace App\Filament\Resources\CfdThemeResource\Pages;

use App\Filament\Resources\CfdThemeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCfdTheme extends EditRecord
{
    protected static string $resource = CfdThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
