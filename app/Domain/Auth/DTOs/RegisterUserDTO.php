<?php

namespace App\Domain\Auth\DTOs;

readonly class RegisterUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone = null,
        public ?string $organizationName = null,
        public ?string $organizationNameAr = null,
        public ?string $storeName = null,
        public ?string $storeNameAr = null,
        public string $country = 'OM',
        public string $currency = 'SAR',
        public string $locale = 'ar',
        public ?string $businessType = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            phone: $data['phone'] ?? null,
            organizationName: $data['organization_name'] ?? null,
            organizationNameAr: $data['organization_name_ar'] ?? null,
            storeName: $data['store_name'] ?? null,
            storeNameAr: $data['store_name_ar'] ?? null,
            country: $data['country'] ?? 'OM',
            currency: $data['currency'] ?? 'SAR',
            locale: $data['locale'] ?? 'ar',
            businessType: $data['business_type'] ?? null,
        );
    }
}
