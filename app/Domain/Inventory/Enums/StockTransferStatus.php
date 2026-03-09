<?php

namespace App\Domain\Inventory\Enums;

enum StockTransferStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
