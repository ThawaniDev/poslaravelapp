<?php

namespace App\Domain\IndustryRestaurant\Enums;

enum KitchenTicketStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
