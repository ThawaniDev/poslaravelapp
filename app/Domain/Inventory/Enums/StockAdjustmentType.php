<?php

namespace App\Domain\Inventory\Enums;

enum StockAdjustmentType: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
    case Damage = 'damage';
}
