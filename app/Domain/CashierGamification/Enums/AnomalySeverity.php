<?php

namespace App\Domain\CashierGamification\Enums;

enum AnomalySeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
