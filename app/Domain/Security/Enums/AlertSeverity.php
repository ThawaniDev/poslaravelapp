<?php

namespace App\Domain\Security\Enums;

enum AlertSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('security.severity_low'),
            self::Medium => __('security.severity_medium'),
            self::High => __('security.severity_high'),
            self::Critical => __('security.severity_critical'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-information-circle',
            self::Medium => 'heroicon-o-exclamation-triangle',
            self::High => 'heroicon-o-fire',
            self::Critical => 'heroicon-o-bolt',
        };
    }
}
