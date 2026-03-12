<?php

namespace App\Domain\Auth\DTOs;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $deviceId = null,
        public ?string $deviceName = null,
        public ?string $platform = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            deviceId: $data['device_id'] ?? null,
            deviceName: $data['device_name'] ?? null,
            platform: $data['platform'] ?? null,
        );
    }
}
