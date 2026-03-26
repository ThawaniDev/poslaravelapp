<?php

namespace App\Domain\Support\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('support.priority_low'),
            self::Medium => __('support.priority_medium'),
            self::High => __('support.priority_high'),
            self::Critical => __('support.priority_critical'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-minus-circle',
            self::Medium => 'heroicon-o-exclamation-circle',
            self::High => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-fire',
        };
    }

    /** SLA first-response deadline in minutes. */
    public function slaFirstResponseMinutes(): int
    {
        return match ($this) {
            self::Critical => 30,
            self::High => 120,
            self::Medium => 480,
            self::Low => 1440,
        };
    }

    /** SLA resolution deadline in minutes. */
    public function slaResolutionMinutes(): int
    {
        return match ($this) {
            self::Critical => 240,
            self::High => 1440,
            self::Medium => 2880,
            self::Low => 7200,
        };
    }
}
