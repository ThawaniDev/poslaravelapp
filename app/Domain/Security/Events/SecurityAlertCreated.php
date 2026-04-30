<?php

namespace App\Domain\Security\Events;

use App\Domain\Security\Models\SecurityAlert;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a new SecurityAlert record is created,
 * whether by automated detection or manual admin action.
 */
class SecurityAlertCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SecurityAlert $alert,
    ) {}
}
