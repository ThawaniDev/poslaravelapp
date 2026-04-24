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

    public function __construct(
        private readonly PushOrderStatusDTO $dto,
    ) {
        $this->queue = 'delivery';
    }

    /** Exponential backoff: 30s, 2min, 10min */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(StatusPushService $service): void
    {
        $service->pushStatusForDTO($this->dto);
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('StatusPushJob permanently failed', [
            'order_mapping_id' => $this->dto->orderMappingId,
            'status' => $this->dto->status,
            'error' => $e->getMessage(),
        ]);
    }
}
