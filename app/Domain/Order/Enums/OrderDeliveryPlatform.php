<?php

namespace App\Domain\Order\Enums;

enum OrderDeliveryPlatform: string
{
    case Hungerstation = 'hungerstation';
    case Jahez = 'jahez';
    case Marsool = 'marsool';
    case Internal = 'internal';
    case Phone = 'phone';
}
