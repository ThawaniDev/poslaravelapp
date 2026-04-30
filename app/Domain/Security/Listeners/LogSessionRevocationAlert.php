<?php

namespace App\Domain\Security\Listeners;

use App\Domain\Security\Events\AdminSessionRevoked;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Events\SecurityAlertCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Log a security alert whenever an admin session is force-revoked
 * by someone other than the session owner.
 */
class LogSessionRevocationAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'security';

    public function handle(AdminSessionRevoked $event): void
    {
        $session = $event->session;

        // Only create an alert when someone else revokes the session
        if ($session->admin_user_id === $event->revokedById) {
            return;
        }

        $alert = SecurityAlert::create([
            'admin_user_id' => $session->admin_user_id,
            'alert_type'    => 'session_revoked',
            'severity'      => 'medium',
            'description'   => "Admin session force-revoked by {$event->revokedById}",
            'ip_address'    => $session->ip_address,
            'status'        => 'open',
            'details'       => [
                'session_id'    => $session->id,
                'revoked_by'    => $event->revokedById,
                'session_ip'    => $session->ip_address,
                'user_agent'    => $session->user_agent,
                'started_at'    => $session->started_at?->toIso8601String(),
                'last_activity' => $session->last_activity_at?->toIso8601String(),
            ],
        ]);

        SecurityAlertCreated::dispatch($alert);
    }
}
