<?php

namespace App\Domain\Order\Enums;

enum ReturnType: string
{
    case Full = 'full';
    case Partial = 'partial';
}
