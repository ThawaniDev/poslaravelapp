<?php

namespace App\Domain\Security\Listeners;

use App\Domain\Security\Events\BruteForceDetected;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Events\SecurityAlertCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * When brute-force is detected on a store, create a SecurityAlert
 * so platform admins are notified in the Security Center.
 */
class CreateBruteForceAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'security';

    public function handle(BruteForceDetected $event): void
    {
        // Avoid duplicate alerts: one per user identifier per hour
        $recentAlert = SecurityAlert::where('alert_type', 'brute_force')
            ->where('status', 'open')
            ->whereRaw("details->>'store_id' = ?", [$event->storeId])
            ->whereRaw("details->>'user_identifier' = ?", [$event->userIdentifier])
            ->where('created_at', '>=', now()->subHour())
            ->first();

        if ($recentAlert) {
            return;
        }

        $alert = SecurityAlert::create([
            'alert_type'  => 'brute_force',
            'severity'    => $event->failedAttempts >= 10 ? 'critical' : 'high',
            'description' => "Brute-force detected: {$event->failedAttempts} failed {$event->attemptType} attempts for user '{$event->userIdentifier}' from {$event->ipAddress}",
            'ip_address'  => $event->ipAddress,
            'status'      => 'open',
            'details'     => [
                'store_id'        => $event->storeId,
                'user_identifier' => $event->userIdentifier,
                'attempt_type'    => $event->attemptType,
                'failed_attempts' => $event->failedAttempts,
            ],
        ]);

        SecurityAlertCreated::dispatch($alert);
    }
}
