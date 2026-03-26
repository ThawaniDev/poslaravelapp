<?php

namespace App\Domain\DeliveryIntegration\Events;

use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuSyncCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DeliveryMenuSyncLog $syncLog,
        public readonly bool $success,
    ) {}
}
