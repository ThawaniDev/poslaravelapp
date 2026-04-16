<?php

namespace App\Domain\IndustryBakery\Enums;

enum CustomCakeOrderStatus: string
{
    case Ordered = 'ordered';
    case InProgress = 'in_progress';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
