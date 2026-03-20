<?php

namespace App\Filament\Resources\OnboardingStepResource\Pages;

use App\Filament\Resources\OnboardingStepResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListOnboardingSteps extends ListRecords
{
    protected static string $resource = OnboardingStepResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
