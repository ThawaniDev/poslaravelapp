<?php

namespace App\Domain\IndustryBakery\Enums;

enum CustomCakeOrderStatus: string
{
    case Ordered = 'ordered';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Delivered = 'delivered';
}
