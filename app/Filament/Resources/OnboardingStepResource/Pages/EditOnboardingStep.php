<?php

namespace App\Filament\Resources\OnboardingStepResource\Pages;

use App\Filament\Resources\OnboardingStepResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditOnboardingStep extends EditRecord
{
    protected static string $resource = OnboardingStepResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
