<?php

namespace App\Filament\Resources\CannedResponseResource\Pages;

use App\Filament\Resources\CannedResponseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCannedResponse extends CreateRecord
{
    protected static string $resource = CannedResponseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth('admin')->id();

        return $data;
    }
}
