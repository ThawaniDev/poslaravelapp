<?php

namespace App\Domain\DeliveryIntegration\Events;

use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DeliveryOrderMapping $order,
        public readonly ?DeliveryOrderStatus $oldStatus,
        public readonly DeliveryOrderStatus $newStatus,
    ) {}
}
