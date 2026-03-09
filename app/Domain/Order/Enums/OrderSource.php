<?php

namespace App\Domain\Order\Enums;

enum OrderSource: string
{
    case Pos = 'pos';
    case Thawani = 'thawani';
    case Hungerstation = 'hungerstation';
    case Jahez = 'jahez';
    case Marsool = 'marsool';
    case Phone = 'phone';
    case Web = 'web';
}
