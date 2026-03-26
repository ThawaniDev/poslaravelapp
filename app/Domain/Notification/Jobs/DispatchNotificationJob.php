<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Services\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(
        public string $eventKey,
        public NotificationChannel $channel,
        public string $recipient,
        public array $variables,
        public string $locale = 'en',
        public ?string $notificationId = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationTemplateService $service): void
    {
        $service->dispatch(
            eventKey: $this->eventKey,
            channel: $this->channel,
            recipient: $this->recipient,
            variables: $this->variables,
            locale: $this->locale,
            notificationId: $this->notificationId,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DispatchNotificationJob failed permanently', [
            'event_key' => $this->eventKey,
            'channel' => $this->channel->value,
            'recipient' => $this->recipient,
            'error' => $e->getMessage(),
        ]);
    }
}
