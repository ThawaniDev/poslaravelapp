<?php

namespace App\Domain\Hardware\Enums;

enum HardwareSaleItemType: string
{
    case Terminal = 'terminal';
    case Printer = 'printer';
    case Scanner = 'scanner';
    case Other = 'other';
}
