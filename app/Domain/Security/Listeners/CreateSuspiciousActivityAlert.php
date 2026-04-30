<?php

namespace App\Domain\Security\Listeners;

use App\Domain\Security\Events\SuspiciousActivityDetected;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Events\SecurityAlertCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Convert every SuspiciousActivityDetected event into a SecurityAlert
 * that surfaces in the platform Security Center.
 */
class CreateSuspiciousActivityAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'security';

    public function handle(SuspiciousActivityDetected $event): void
    {
        $alert = SecurityAlert::create([
            'alert_type'  => $event->activityType,
            'severity'    => $event->severity,
            'description' => $event->description,
            'ip_address'  => $event->ipAddress,
            'status'      => 'open',
            'details'     => array_merge(
                $event->context,
                [
                    'store_id' => $event->storeId,
                    'user_id'  => $event->userId,
                ],
            ),
        ]);

        SecurityAlertCreated::dispatch($alert);
    }
}
