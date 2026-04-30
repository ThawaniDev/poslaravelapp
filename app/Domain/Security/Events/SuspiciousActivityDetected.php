<?php

namespace App\Domain\Security\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when suspicious activity is detected on a store's account
 * (e.g. login from an unknown IP, unusual transaction pattern, etc.).
 */
class SuspiciousActivityDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $storeId,
        public readonly string $activityType,
        public readonly string $description,
        public readonly string $severity = 'medium',
        public readonly ?string $ipAddress = null,
        public readonly ?string $userId = null,
        public readonly array $context = [],
    ) {}
}
