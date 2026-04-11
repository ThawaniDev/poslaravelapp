<?php

namespace App\Domain\CashierGamification\Enums;

enum RiskLevel: string
{
    case Normal = 'normal';
    case Elevated = 'elevated';
    case High = 'high';
    case Critical = 'critical';

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 75 => self::Critical,
            $score >= 50 => self::High,
            $score >= 25 => self::Elevated,
            default => self::Normal,
        };
    }
}
