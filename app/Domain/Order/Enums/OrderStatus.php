<?php

namespace App\Domain\Order\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Voided = 'voided';
}
