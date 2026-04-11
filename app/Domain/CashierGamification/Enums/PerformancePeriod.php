<?php

namespace App\Domain\CashierGamification\Enums;

enum PerformancePeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Shift = 'shift';
}
