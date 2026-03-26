<?php

namespace App\Domain\DeliveryIntegration\Jobs;

use App\Domain\DeliveryIntegration\DTOs\PushOrderStatusDTO;
use App\Domain\DeliveryIntegration\Services\StatusPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StatusPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly PushOrderStatusDTO $dto,
    ) {
        $this->queue = 'delivery';
    }

    public function handle(StatusPushService $service): void
    {
        $service->pushStatusForDTO($this->dto);
    }
}
