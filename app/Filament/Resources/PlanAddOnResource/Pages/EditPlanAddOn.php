<?php

namespace App\Filament\Resources\PlanAddOnResource\Pages;

use App\Filament\Resources\PlanAddOnResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPlanAddOn extends EditRecord
{
    protected static string $resource = PlanAddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
