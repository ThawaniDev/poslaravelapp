<?php

namespace App\Domain\DeliveryIntegration\Events;

use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryOrderReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly DeliveryOrderMapping $order,
    ) {}
}
