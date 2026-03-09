<?php

namespace App\Domain\Order\Enums;

enum ExternalOrderType: string
{
    case Thawani = 'thawani';
    case Hungerstation = 'hungerstation';
    case Keeta = 'keeta';
}
