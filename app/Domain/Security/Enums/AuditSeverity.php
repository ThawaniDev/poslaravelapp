<?php

namespace App\Domain\Security\Enums;

enum AuditSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => __('security.severity_info'),
            self::Warning => __('security.severity_warning'),
            self::Critical => __('security.severity_critical'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info => 'heroicon-o-information-circle',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-fire',
        };
    }
}
