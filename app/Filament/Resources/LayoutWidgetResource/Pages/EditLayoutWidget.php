<?php

namespace App\Filament\Resources\LayoutWidgetResource\Pages;

use App\Filament\Resources\LayoutWidgetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLayoutWidget extends EditRecord
{
    protected static string $resource = LayoutWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
