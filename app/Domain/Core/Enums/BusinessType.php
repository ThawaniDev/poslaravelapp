<?php

namespace App\Domain\Core\Enums;

enum BusinessType: string
{
    case Retail = 'retail';
    case Restaurant = 'restaurant';
    case Pharmacy = 'pharmacy';
    case Grocery = 'grocery';
    case Jewelry = 'jewelry';
    case MobileShop = 'mobile_shop';
    case FlowerShop = 'flower_shop';
    case Bakery = 'bakery';
    case Service = 'service';
    case Custom = 'custom';
}
