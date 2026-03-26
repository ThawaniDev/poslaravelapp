<?php

namespace App\Domain\Core\Enums;

enum BusinessType: string
{
    case Grocery = 'grocery';
    case Restaurant = 'restaurant';
    case Pharmacy = 'pharmacy';
    case Bakery = 'bakery';
    case Electronics = 'electronics';
    case Florist = 'florist';
    case Jewelry = 'jewelry';
    case Fashion = 'fashion';
}
