<?php

namespace App\Filament\Resources\OnboardingStepResource\Pages;

use App\Filament\Resources\OnboardingStepResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOnboardingStep extends ViewRecord
{
    protected static string $resource = OnboardingStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
