<?php

namespace App\Domain\SystemConfig\Enums;

enum CalendarSystem: string
{
    case Gregorian = 'gregorian';
    case Hijri = 'hijri';
    case Both = 'both';
}
