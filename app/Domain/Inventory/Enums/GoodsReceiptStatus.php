<?php

namespace App\Domain\Inventory\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
}
