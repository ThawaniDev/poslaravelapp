<?php

namespace App\Domain\IndustryRestaurant\Enums;

enum KitchenTicketStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
}
