<?php

namespace App\Domain\DeliveryIntegration\Enums;

enum MenuSyncTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case ProductChange = 'product_change';
    case StockChange = 'stock_change';
    case Initial = 'initial';
}
