<?php

namespace App\Domain\Security\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user (cashier / customer) triggers brute-force lockout
 * by exceeding max_failed_attempts within the lockout window.
 */
class BruteForceDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $storeId,
        public readonly string $userIdentifier,
        public readonly string $ipAddress,
        public readonly int $failedAttempts,
        public readonly string $attemptType = 'pin',
    ) {}
}
