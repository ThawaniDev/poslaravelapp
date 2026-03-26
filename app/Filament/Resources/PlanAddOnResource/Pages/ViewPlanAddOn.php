<?php

namespace App\Filament\Resources\PlanAddOnResource\Pages;

use App\Filament\Resources\PlanAddOnResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPlanAddOn extends ViewRecord
{
    protected static string $resource = PlanAddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
