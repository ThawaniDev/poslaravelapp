<?php

namespace App\Domain\Security\Events;

use App\Domain\Security\Models\AdminSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an admin session is explicitly revoked
 * (by the session owner or by a super-admin).
 */
class AdminSessionRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AdminSession $session,
        /** UUID of the admin who performed the revocation */
        public readonly string $revokedById,
    ) {}
}
