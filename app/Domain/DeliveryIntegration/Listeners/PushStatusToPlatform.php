<?php

namespace App\Domain\DeliveryIntegration\Listeners;

use App\Domain\DeliveryIntegration\Events\DeliveryStatusChanged;
use App\Domain\DeliveryIntegration\Services\StatusPushService;
use Illuminate\Contracts\Queue\ShouldQueue;

class PushStatusToPlatform implements ShouldQueue
{
    public string $queue = 'delivery';

    public function __construct(
        private readonly StatusPushService $statusPushService,
    ) {}

    public function handle(DeliveryStatusChanged $event): void
    {
        $this->statusPushService->pushStatus(
            $event->order,
            $event->newStatus->value,
        );
    }
}
