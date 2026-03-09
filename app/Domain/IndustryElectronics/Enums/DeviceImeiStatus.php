<?php

namespace App\Domain\IndustryElectronics\Enums;

enum DeviceImeiStatus: string
{
    case InStock = 'in_stock';
    case Sold = 'sold';
    case TradedIn = 'traded_in';
    case Returned = 'returned';
}
