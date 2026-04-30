<?php

namespace App\Domain\AppUpdateManagement\Notifications;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to Super Admins when the auto-rollback job deactivates a release
 * due to an excessive failure rate.
 */
class AutoRollbackNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AppRelease $release,
        private readonly array $details,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $version = $this->release->version_number;
        $platform = $this->details['platform'] ?? 'unknown';
        $rate = $this->details['failure_rate'] ?? 0;

        return (new MailMessage)
            ->error()
            ->subject("[CRITICAL] Auto-Rollback: v{$version} ({$platform}) deactivated")
            ->greeting('Alert: App Release Auto-Rolled Back')
            ->line("Release **v{$version}** for **{$platform}** has been automatically deactivated.")
            ->line("**Failure Rate:** {$rate}% ({$this->details['failed_attempts']} / {$this->details['total_attempts']} installs failed)")
            ->line('The release has been deactivated. Stores will fall back to the previous active release on next update check.')
            ->action('Review in Admin Panel', url('/admin/updates/releases'))
            ->line('Please review the release and investigate the root cause before re-activating.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'auto_rollback',
            'title' => "Auto-Rollback: v{$this->release->version_number}",
            'body' => "Release v{$this->release->version_number} ({$this->details['platform']}) deactivated — {$this->details['failure_rate']}% failure rate.",
            'release_id' => $this->release->id,
            'details' => $this->details,
            'action_url' => '/admin/updates/releases/' . $this->release->id . '/edit',
        ];
    }
}
