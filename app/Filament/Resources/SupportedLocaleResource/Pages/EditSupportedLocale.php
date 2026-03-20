<?php

namespace App\Filament\Resources\SupportedLocaleResource\Pages;

use App\Filament\Resources\SupportedLocaleResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditSupportedLocale extends EditRecord
{
    protected static string $resource = SupportedLocaleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
