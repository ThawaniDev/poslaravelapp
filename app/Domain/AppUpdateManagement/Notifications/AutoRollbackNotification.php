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
            ->subject(__('infrastructure.rollback_subject', ['version' => $version, 'platform' => $platform]))
            ->greeting(__('infrastructure.rollback_greeting'))
            ->line(__('infrastructure.rollback_deactivated', ['version' => $version, 'platform' => $platform]))
            ->line(__('infrastructure.rollback_failure_rate', ['rate' => $rate, 'failed' => $this->details['failed_attempts'], 'total' => $this->details['total_attempts']]))
            ->line(__('infrastructure.rollback_fallback'))
            ->action(__('infrastructure.rollback_action'), url('/admin/updates/releases'))
            ->line(__('infrastructure.rollback_review'));
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
