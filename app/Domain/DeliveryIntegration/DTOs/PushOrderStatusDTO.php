<?php

namespace App\Domain\DeliveryIntegration\DTOs;

readonly class PushOrderStatusDTO
{
    public function __construct(
        public string $deliveryOrderMappingId,
        public string $platform,
        public string $externalOrderId,
        public string $newStatus,
        public ?string $reason = null,
        public ?int $estimatedMinutes = null,
    ) {}
}
