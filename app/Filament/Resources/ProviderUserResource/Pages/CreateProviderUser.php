<?php

namespace App\Filament\Resources\ProviderUserResource\Pages;

use App\Filament\Resources\ProviderUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateProviderUser extends CreateRecord
{
    protected static string $resource = ProviderUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password'], $data['password_confirmation']);

        // Mark email as verified immediately since we're creating it via admin.
        $data['email_verified_at'] = now();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
