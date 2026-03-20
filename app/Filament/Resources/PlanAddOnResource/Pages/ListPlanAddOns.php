<?php

namespace App\Filament\Resources\PlanAddOnResource\Pages;

use App\Filament\Resources\PlanAddOnResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPlanAddOns extends ListRecords
{
    protected static string $resource = PlanAddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
