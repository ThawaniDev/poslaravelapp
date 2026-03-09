<?php

namespace App\Domain\DeliveryIntegration\Enums;

enum DeliveryConfigPlatform: string
{
    case Hungerstation = 'hungerstation';
    case Jahez = 'jahez';
    case Marsool = 'marsool';
}
